<?php
/**
 * Refill request endpoint.
 *
 * The Boost provider has no refill API, so a refill is handled as a request:
 * the eligible order is flagged and a support ticket is opened for the admin
 * to process. Accepts JSON/form POST: order_id. Returns JSON.
 */

require_once 'config.php';

header('Content-Type: application/json');

function rOut($ok, $msg, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

if (!isLoggedIn())                      rOut(false, 'Tafadhali ingia kwanza.', [], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') rOut(false, 'Method not allowed.', [], 405);

$input = $_POST;
if (empty($input)) {
    $decoded = json_decode(file_get_contents('php://input'), true);
    if (is_array($decoded)) $input = $decoded;
}

$user_id  = $_SESSION['user_id'];
$order_id = (int)($input['order_id'] ?? 0);
if ($order_id <= 0) rOut(false, 'Order haijatambulika.', [], 422);

// Load the order (must belong to this user)
$stmt = $conn->prepare("SELECT id, service_name, status, refill_available, refill_requested FROM orders WHERE id = ? AND user_id = ?");
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
    $conn->begin_transaction();

    $stmt = $conn->prepare("UPDATE orders SET refill_requested = 1, refill_status = 'requested', refill_requested_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    // Open a support ticket so the admin sees the request
    $subject = "Refill request - Order #{$order_id}";
    $message = "Mteja ameomba refill kwa order #{$order_id} ({$order['service_name']}).";
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
