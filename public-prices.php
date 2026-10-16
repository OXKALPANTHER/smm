<?php
require_once 'config.php';
require_once 'includes/public-prices-cache.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=600, stale-while-revalidate=120');
$data = royalPublicPriceSnapshot();
if ($data) {
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    http_response_code(503);
    echo json_encode(['success' => false, 'data' => [], 'message' => 'Live pricing is temporarily unavailable.']);
}
