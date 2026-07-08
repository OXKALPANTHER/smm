<?php
require_once 'config.php';
require_once 'includes/APIHandler.php';
requireLogin();

header('Content-Type: application/json');

function jsonOut($success, $message, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(false, 'Method not allowed.', [], 405);
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$orderId = (int)($input['order_id'] ?? 0);
if ($orderId <= 0) {
    jsonOut(false, 'Order id is required.', [], 422);
}

$stmt = $conn->prepare("SELECT id, service_id, external_order_id, gateway, status FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $orderId, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    jsonOut(false, 'Order not found.', [], 404);
}

$gateway = strtolower((string)($order['gateway'] ?? 'primary'));
$provider = ($gateway === 'partner' || $gateway === 'pro' || $gateway === 'premium' || $gateway === 'fastway') ? 'fastway' : 'boost';
$serviceId = (int)($order['service_id'] ?? 0);

try {
    $api = new APIHandler($provider);
    $services = $api->getAllServices();
    $supportsCancel = false;
    foreach ($services as $service) {
        if ((int)($service['id'] ?? 0) === $serviceId) {
            $supportsCancel = !empty($service['cancel']);
            break;
        }
    }

    if (!$supportsCancel) {
        jsonOut(false, 'This service does not support cancellation.', [], 400);
    }

    $externalId = $order['external_order_id'];
    if ($externalId === null || $externalId === '') {
        jsonOut(false, 'This order has no external reference to cancel.', [], 400);
    }

    $result = $api->request('/order/cancel', 'POST', ['order_id' => $externalId]);
    if (!($result['success'] ?? false)) {
        jsonOut(false, $result['error'] ?? 'Cancellation failed.', [], 502);
    }

    $stmt = $conn->prepare("UPDATE orders SET status = 'Canceled', progress = 100 WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    jsonOut(true, 'Cancellation requested successfully.', ['order_id' => $orderId]);
} catch (Exception $e) {
    error_log('cancel-order fatal: ' . $e->getMessage());
    jsonOut(false, 'Cancellation could not be completed right now.', [], 500);
}
