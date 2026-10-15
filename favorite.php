<?php
require_once __DIR__ . '/config.php';
requireLogin();
requireSameOriginRequest();
require_csrf();
header('Content-Type: application/json; charset=utf-8');

$userId = (int) $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$orderId = (int) ($input['order_id'] ?? 0);
$action = (string) ($input['action'] ?? 'toggle');
if ($orderId <= 0 || !in_array($action, ['toggle', 'remove'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Order haijatambulika.']);
    exit;
}

$check = $conn->prepare('SELECT id FROM orders WHERE id = ? AND user_id = ?');
$check->bind_param('ii', $orderId, $userId);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order hii si yako au haipo.']);
    exit;
}

try {
    $exists = $conn->prepare('SELECT id FROM favorite_orders WHERE user_id = ? AND order_id = ?');
    $exists->bind_param('ii', $userId, $orderId);
    $exists->execute();
    $row = $exists->get_result()->fetch_assoc();
    $favorited = false;
    if ($row && ($action === 'toggle' || $action === 'remove')) {
        $delete = $conn->prepare('DELETE FROM favorite_orders WHERE user_id = ? AND order_id = ?');
        $delete->bind_param('ii', $userId, $orderId);
        $delete->execute();
    } elseif (!$row && $action === 'toggle') {
        $insert = $conn->prepare('INSERT INTO favorite_orders (user_id, order_id) VALUES (?, ?)');
        $insert->bind_param('ii', $userId, $orderId);
        $insert->execute();
        $favorited = true;
    }
    logActivity($userId, $favorited ? 'order_favorited' : 'order_unfavorited', "Order #{$orderId}");
    echo json_encode(['success' => true, 'favorited' => $favorited, 'message' => $favorited ? 'Order imehifadhiwa kwenye favorites.' : 'Order imeondolewa kwenye favorites.']);
} catch (Throwable $e) {
    error_log('favorite action failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Imeshindikana kuhifadhi favorite.']);
}
