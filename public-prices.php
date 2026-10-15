<?php
require_once 'config.php';
require_once 'includes/APIHandler.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
try {
    $api = new APIHandler(defined('PRIMARY_PROVIDER') ? PRIMARY_PROVIDER : 'fastway');
    $markup = defined('UNVERIFIED_PRICE_MARKUP_PERCENT') ? (float) UNVERIFIED_PRICE_MARKUP_PERCENT : 100.0;
    $services = $api->getAllServices(true, $markup);
    $chosen = [];
    foreach ($services as $service) {
        $name = (string) ($service['name'] ?? '');
        $haystack = strtolower($name . ' ' . ($service['category'] ?? ''));
        $platform = 'Social media';
        foreach (['instagram','tiktok','youtube','facebook','telegram','twitter','x'] as $candidate) {
            if (strpos($haystack, $candidate) !== false) { $platform = ucfirst($candidate === 'x' ? 'X' : $candidate); break; }
        }
        $price = (float) ($service['price_per_1000'] ?? 0);
        if ($price <= 0 || isset($chosen[$platform]) && $chosen[$platform]['price'] <= $price) continue;
        $chosen[$platform] = ['platform' => $platform, 'name' => $name, 'price' => round($price), 'currency' => 'TZS', 'service_id' => (int) ($service['id'] ?? 0), 'min' => (int) ($service['min'] ?? 0), 'max' => (int) ($service['max'] ?? 0), 'refill' => !empty($service['refill'])];
    }
    usort($chosen, fn($a, $b) => $a['price'] <=> $b['price']);
    echo json_encode(['success' => true, 'data' => array_slice(array_values($chosen), 0, 6)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'data' => [], 'message' => 'Live pricing is temporarily unavailable.']);
}
