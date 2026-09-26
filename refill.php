<?php
/**
 * Refill request endpoint.
 *
 * FastWay refills are submitted directly using the documented `refill` action.
 * Legacy orders remain an administrator-supported request. Accepts JSON/form
 * POST: order_id. Returns JSON.
 */

require_once 'config.php';
require_once 'includes/APIHandler.php';

header('Content-Type: application/json');

function rOut($ok, $msg, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

if (!isLoggedIn())                      rOut(false, 'Tafadhali ingia kwanza.', [], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') rOut(false, 'Method not allowed.', [], 405);
requireSameOriginRequest();

$input = $_POST;
if (empty($input)) {
    $decoded = json_decode(file_get_contents('php://input'), true);
    if (is_array($decoded)) $input = $decoded;
}

$user_id  = $_SESSION['user_id'];
$order_id = (int)($input['order_id'] ?? 0);
if ($order_id <= 0) rOut(false, 'Order haijatambulika.', [], 422);

// Load the order (must belong to this user)
$stmt = $conn->prepare("SELECT id, service_name, status, external_order_id, provider, gateway, refill_available, refill_requested FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order)                            rOut(false, 'Order haijapatikana.', [], 404);
if (empty($order['refill_available']))  rOut(false, 'Huduma hii haina refill.', [], 422);
if (!empty($order['refill_requested'])) rOut(false, 'Tayari umeomba refill kwa order hii.', ['already' => true], 409);

$status = strtolower($order['status']);
if (strpos($status, 'complet') === false && strpos($status, 'partial') === false) {
    rOut(false, 'Refill inawezekana tu baada ya order kukamilika.', [], 422);
}

try {
    $provider = strtolower((string)($order['provider'] ?? ''));
    if (!in_array($provider, ['fastway', 'boost'], true)) {
        $provider = strtolower((string)($order['gateway'] ?? '')) === 'partner' ? 'fastway' : 'boost';
    }

    $refillId = null;
    if ($provider === 'fastway') {
        $externalId = trim((string)($order['external_order_id'] ?? ''));
        if ($externalId === '') {
            rOut(false, 'Order hii haina kumbukumbu ya FastWay ya refill.', [], 422);
        }
        $refill = (new APIHandler('fastway'))->createRefill($externalId);
        if (empty($refill['success'])) {
            rOut(false, $refill['error'] ?? 'FastWay haikupokea refill kwa sasa.', [], 502);
        }
        $refillId = (string)($refill['refill_id'] ?? '');
    }

    $conn->begin_transaction();

    $refillStatus = $refillId !== '' ? 'requested:' . $refillId : 'requested';
    $stmt = $conn->prepare("UPDATE orders SET refill_requested = 1, refill_status = ?, refill_requested_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("si", $refillStatus, $order_id);
    $stmt->execute();

    // Preserve the existing operator trail without changing the user-facing UI.
    $subject = "Refill request - Order #{$order_id}";
    $message = "Mteja ameomba refill kwa order #{$order_id} ({$order['service_name']}). Provider: {$provider}."
        . ($refillId !== '' ? " FastWay refill ID: {$refillId}." : ' Inahitaji ufuatiliaji wa support.');
    $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, message, status, priority) VALUES (?, ?, ?, 'open', 'high')");
    $stmt->bind_param("iss", $user_id, $subject, $message);
    $stmt->execute();

    $conn->commit();
    logActivity($user_id, 'refill_requested', "Order #{$order_id}");
    rOut(true, 'Ombi la refill limepokelewa. Tutalishughulikia hivi karibuni.', ['order_id' => $order_id]);
} catch (Exception $e) {
    @$conn->rollback();
    error_log("refill error: " . $e->getMessage());
    rOut(false, 'Kosa la mfumo. Jaribu tena.', [], 500);
}
