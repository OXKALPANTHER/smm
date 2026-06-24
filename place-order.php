<?php
/**
 * Live order endpoint.
 *
 * Submits the order to the live Boost provider FIRST, and only on success
 * deducts the user's balance and records the order locally (atomic). This
 * guarantees a user is never charged for an order the provider rejected.
 *
 * Accepts JSON or form-encoded POST: service_id, quantity, link.
 * Returns JSON.
 */

require_once 'config.php';
require_once 'includes/APIHandler.php';

header('Content-Type: application/json');

function jsonOut($success, $message, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra));
    exit;
}

if (!isLoggedIn()) {
    jsonOut(false, 'Tafadhali ingia kwanza.', [], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(false, 'Method not allowed.', [], 405);
}

// Accept JSON body or form-encoded.
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$user_id    = $_SESSION['user_id'];
$service_id = (int)($input['service_id'] ?? 0);
$quantity   = (int)($input['quantity'] ?? 0);
$link       = trim($input['link'] ?? '');

if ($service_id <= 0 || $quantity <= 0 || $link === '') {
    jsonOut(false, 'Tafadhali jaza huduma, idadi na link.', [], 422);
}

try {
    // Get services with automatic fallback: tries Boost first, then FastWay
    $services = callApiWithFallback('getAllServices');

    // Resolve the service from the live catalogue (authoritative price/limits).
    $service = null;
    foreach ($services as $s) {
        if ((int)$s['id'] === $service_id) {
            $service = $s;
            break;
        }
    }

    if (!$service) {
        jsonOut(false, 'Huduma haijapatikana au haipatikani tena.', [], 404);
    }

    $min  = max(1, (int)$service['min']);
    $max  = (int)$service['max'];
    $rate = (float)$service['rate']; // per-unit TZS

    if ($quantity < $min || ($max > 0 && $quantity > $max)) {
        jsonOut(false, "Idadi lazima iwe kati ya {$min} na {$max}.", [
            'min' => $min, 'max' => $max,
        ], 422);
    }

    $cost = (int)ceil($quantity * $rate);
    if ($cost <= 0) {
        jsonOut(false, 'Bei ya huduma hii haipatikani.', [], 422);
    }

    // Check the user's balance.
    $stmt = $conn->prepare("SELECT balance, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $balance = (float)($u['balance'] ?? 0);
    $email   = $u['email'] ?? null;

    if ($cost > $balance) {
        jsonOut(false, 'Salio lako halitoshi kukamilisha order hii.', [
            'required' => $cost,
            'balance'  => $balance,
        ], 402);
    }

    // Derive platform label from the service category.
    $platform = 'General';
    $platforms = json_decode(PLATFORMS, true);
    $hay = strtolower($service['name'] . ' ' . $service['category']);
    foreach (array_keys($platforms) as $pkey) {
        if (strpos($hay, $pkey) !== false) {
            $platform = $platforms[$pkey]['name'];
            break;
        }
    }

    // Check if user wants to try fallback provider
    $use_fallback = filter_var($input['use_fallback'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // 1) Try primary provider (Lazack Boost) first
    if (!$use_fallback) {
        $api_primary = new APIHandler('boost');
        $result = $api_primary->placeOrder($service_id, $link, $quantity, $email);

        if (!$result['success']) {
            // Primary failed - ask user if they want to try alternative provider
            logActivity($user_id, 'order_attempt_primary_failed', $service['name'] . ' - ' . ($result['error'] ?? ''), 'failed');
            
            jsonOut(false, 'Huduma yetu ya kawaida haipatikani kwa sasa. Ingekuwa na njia nyingine ya kukamilisha order hii?', [
                'provider_unavailable' => true,
                'can_retry_alt' => true,
                'service_id' => $service_id,
                'quantity' => $quantity,
                'link' => $link,
            ], 503);
        }
    } else {
        // User approved fallback - try FastWay with USD conversion
        $api_fallback = new APIHandler('fastway');
        
        // Convert cost from TSH to USD for FastWay
        $usd_rate = (float)(getenv('USD_TO_TZS_RATE') ?: USD_TO_TZS_RATE ?: 3500);
        $cost_usd = max(1, (int)ceil($cost / $usd_rate));

        $result = $api_fallback->placeOrder($service_id, $link, $quantity, $email);

        if (!$result['success']) {
            logActivity($user_id, 'order_attempt_fallback_failed', $service['name'] . ' - ' . ($result['error'] ?? ''), 'failed');
            
            // Both providers failed - suggest similar orders
            jsonOut(false, 'Huduma hii haiwezi kutengenezwa kwa sasa. Tafadhali jaribu huduma nyingine.', [
                'both_failed' => true,
                'suggest_alternatives' => true,
                'service_id' => $service_id,
                'platform' => $platform,
            ], 503);
        }
    }

    $external_id = $result['order_id'] ?? null;
    $status      = $result['status'] ?? 'Pending';

    // 2) Provider accepted -> record locally and charge the user atomically.
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->bind_param("di", $cost, $user_id);
        $stmt->execute();

        $stmt = $conn->prepare(
            "INSERT INTO orders
                (user_id, service_id, service_name, service_category, platform, quantity, price, status, external_order_id, link, refill_available)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ext = $external_id !== null ? (string)$external_id : null;
        $refillAvail = !empty($service['refill']) ? 1 : 0;
        $stmt->bind_param(
            "iisssidsssi",
            $user_id, $service_id, $service['name'], $service['category'],
            $platform, $quantity, $cost, $status, $ext, $link, $refillAvail
        );
        $stmt->execute();
        $order_id = $conn->insert_id();

        $desc = "Order #{$order_id} - {$service['name']}";
        $gateway = $use_fallback ? 'partner' : 'primary';
        $stmt = $conn->prepare(
            "INSERT INTO transactions
                (user_id, order_id, amount, type, payment_method, gateway, description, external_ref, status, completed_at)
             VALUES (?, ?, ?, 'debit', 'balance', ?, ?, ?, 'completed', CURRENT_TIMESTAMP)"
        );
        $stmt->bind_param("iidss", $user_id, $order_id, $cost, $gateway, $desc, $ext);
        $stmt->execute();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Local order persistence failed (provider order $external_id placed!): " . $e->getMessage());
        // The provider order WAS placed; surface success but flag the local issue.
        jsonOut(true, 'Order imewekwa, lakini kumetokea tatizo la kuhifadhi. Wasiliana na support.', [
            'external_order_id' => $external_id,
            'warning' => true,
        ]);
    }

    $provider_used = $use_fallback ? 'partner_service' : 'primary_service';
    logActivity($user_id, 'order_placed', "Order #{$order_id} ({$service['name']}) x{$quantity} = {$cost} TZS via {$provider_used}");

    jsonOut(true, 'Order imefanikiwa!', [
        'order_id'          => $order_id,
        'external_order_id' => $external_id,
        'status'            => $status,
        'cost'              => $cost,
        'new_balance'       => $balance - $cost,
    ]);

} catch (Exception $e) {
    error_log("place-order fatal: " . $e->getMessage());
    jsonOut(false, 'Kosa la mfumo: ' . $e->getMessage(), [], 500);
}
