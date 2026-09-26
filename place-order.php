<?php
/**
 * Live order endpoint.
 *
 * Submits the order to the live FastWay provider FIRST, and only on success
 * deducts the user's balance and records the order locally (atomic). This
 * guarantees a user is never charged for an order the provider rejected.
 *
 * Accepts JSON or form-encoded POST: service_id, quantity, link.
 * Returns JSON.
 */

require_once 'config.php';
require_once 'includes/APIHandler.php';

header('Content-Type: application/json');

function jsonOut($success, $message, $extra = [], $code = 200)
{
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

$user_id = $_SESSION['user_id'];
$service_id = (int) ($input['service_id'] ?? 0);
$quantity = (int) ($input['quantity'] ?? 0);
$link = trim($input['link'] ?? '');

if ($service_id <= 0 || $quantity <= 0 || $link === '') {
    jsonOut(false, 'Tafadhali jaza huduma, idadi na link.', [], 422);
}

try {
    // FastWay is the authoritative catalogue and order provider for all new
    // orders. Ignoring stale client-side provider flags prevents a FastWay ID
    // from being resolved against an old Boost catalogue.
    $provider = 'fastway';
    $services = (new APIHandler($provider))->getAllServices();

    // Resolve the service from the live catalogue (authoritative price/limits).
    $service = null;
    foreach ($services as $s) {
        if ((int) $s['id'] === $service_id) {
            $service = $s;
            break;
        }
    }

    if (!$service) {
        jsonOut(false, 'Huduma haijapatikana au haipatikani tena.', [], 404);
    }

    $min = max(1, (int) $service['min']);
    $max = (int) $service['max'];
    $rate = (float) $service['rate']; // per-unit TZS

    if ($quantity < $min || ($max > 0 && $quantity > $max)) {
        jsonOut(false, "Idadi lazima iwe kati ya {$min} na {$max}.", [
            'min' => $min,
            'max' => $max,
        ], 422);
    }

    $cost = (int) ceil($quantity * $rate);
    if ($cost <= 0) {
        jsonOut(false, 'Bei ya huduma hii haipatikani.', [], 422);
    }

    // Check the user's balance.
    $stmt = $conn->prepare("SELECT balance, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $balance = (float) ($u['balance'] ?? 0);
    $email = $u['email'] ?? null;

    if ($cost > $balance) {
        jsonOut(false, 'Salio lako halitoshi kukamilisha order hii.', [
            'required' => $cost,
            'balance' => $balance,
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

    // 1) FastWay accepts the order before the local balance is charged.
    $api = new APIHandler($provider);
    $result = $api->placeOrder($service_id, $link, $quantity, $email);
    if (!$result['success']) {
        logActivity($user_id, 'order_attempt_fastway_failed', $service['name'] . ' - ' . ($result['error'] ?? ''), 'failed');
        jsonOut(false, 'Huduma haikuweza kupokelewa kwa sasa. Tafadhali jaribu tena au chagua huduma nyingine.', [
            'suggest_alternatives' => true,
            'service_id' => $service_id,
            'platform' => $platform,
        ], 503);
    }

    $external_id = $result['order_id'] ?? null;
    $status = $result['status'] ?? 'Pending';

    // 2) Provider accepted -> record locally and charge the user atomically.
    $conn->begin_transaction();
    try {
        // The compat layer returns false (instead of throwing) when a statement
        // fails. On Postgres the first failed statement aborts the whole
        // transaction, so we MUST detect it here — otherwise commit() runs as a
        // silent ROLLBACK and we'd wrongly report success with nothing saved.
        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        if (!$stmt)
            throw new Exception('prepare failed (balance): ' . ($conn->error ?? ''));
        $stmt->bind_param("di", $cost, $user_id);
        if (!$stmt->execute())
            throw new Exception('balance update failed: ' . ($stmt->error ?? ''));

        $stmt = $conn->prepare(
            "INSERT INTO orders
                (user_id, service_id, service_name, service_category, platform,
                 quantity, price, status, progress, external_order_id,
                 link, provider, gateway, refill_available, delivered_quantity, remaining_quantity)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt)
            throw new Exception('prepare failed (orders): ' . ($conn->error ?? ''));
        $ext = $external_id !== null ? (string) $external_id : null;
        $refillAvail = !empty($service['refill']) ? 1 : 0;
        $gateway = 'primary';
        $initialProgress = 10; // Start at 10% for newly placed orders
        $deliveredQty = 0;
        $remainingQty = $quantity;
        $stmt->bind_param(
            "iisssidsissssiii",
            $user_id,
            $service_id,
            $service['name'],
            $service['category'],
            $platform,
            $quantity,
            $cost,
            $status,
            $initialProgress,
            $ext,
            $link,
            $provider,
            $gateway,
            $refillAvail,
            $deliveredQty,
            $remainingQty
        );
        if (!$stmt->execute())
            throw new Exception('order insert failed: ' . ($stmt->error ?? ''));
        $order_id = $conn->insert_id();

        $desc = "Order #{$order_id} - {$service['name']}";
        $gateway_dup = 'primary';
        $stmt = $conn->prepare(
            "INSERT INTO transactions
                (user_id, order_id, amount, type, payment_method, gateway, description, external_ref, status, completed_at)
             VALUES (?, ?, ?, 'debit', 'balance', ?, ?, ?, 'completed', CURRENT_TIMESTAMP)"
        );
        if (!$stmt)
            throw new Exception('prepare failed (transactions): ' . ($conn->error ?? ''));
        $stmt->bind_param("iidss", $user_id, $order_id, $cost, $gateway_dup, $desc, $ext);
        if (!$stmt->execute())
            throw new Exception('transaction insert failed: ' . ($stmt->error ?? ''));

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        // The provider order WAS placed but the local transaction rolled back, so
        // we have NO record of it and the user was NOT charged (the balance
        // deduction was reverted too). Do NOT report success — that would hide a
        // real inconsistency and the order would silently vanish from the list.
        // Log loudly with the external id so an operator can reconcile/cancel it.
        error_log("Local order persistence FAILED (provider order $external_id placed but NOT saved!): " . $e->getMessage());
        jsonOut(false, 'Order imeshindikana kuhifadhiwa kwa sasa. Hujakatwa pesa — tafadhali wasiliana na support kabla ya kujaribu tena.', [
            'external_order_id' => $external_id,
            'save_failed' => true,
        ], 500);
    }

    $provider_used = 'fastway';
    logActivity($user_id, 'order_placed', "Order #{$order_id} ({$service['name']}) x{$quantity} = {$cost} TZS via {$provider_used}");
    createNotification(
        $user_id,
        'Order imefanikiwa',
        "Order #{$order_id} ya {$service['name']} imepokelewa na imehifadhiwa kwa mafanikio.",
        'success',
        'user',
        ['order_id' => $order_id, 'source' => 'order_placed']
    );

    jsonOut(true, 'Order imefanikiwa!', [
        'order_id' => $order_id,
        'external_order_id' => $external_id,
        'status' => $status,
        'cost' => $cost,
        'new_balance' => $balance - $cost,
    ]);

} catch (Exception $e) {
    error_log("place-order fatal: " . $e->getMessage());
    jsonOut(false, 'Kosa la mfumo: ' . $e->getMessage(), [], 500);
}
