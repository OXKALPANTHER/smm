<?php
/**
 * API Endpoint to fetch services from Boost API
 * Used by JavaScript to populate dropdowns
 */

require_once 'config.php';
require_once 'includes/APIHandler.php';

header('Content-Type: application/json');

try {
    $platform = $_GET['platform'] ?? null;
    $query    = trim($_GET['q'] ?? '');
    $refresh  = isset($_GET['refresh']) && $_GET['refresh'] === 'true';

    // Provider selection: 'boost' (Huduma Kawaida) is the default; 'fastway'
    // (Huduma Pro) is offered when the primary provider can't place an order.
    $provider = strtolower(trim($_GET['provider'] ?? 'boost'));
    if (!in_array($provider, ['boost', 'fastway'], true)) {
        $provider = 'boost';
    }

    // "all"/empty platform means: do not filter by platform.
    if ($platform === '__all__' || $platform === 'all' || $platform === '') {
        $platform = null;
    }

    // Initialize API handler for the chosen provider
    $api = new APIHandler($provider);

    if ($query !== '') {
        // Global search across the whole catalogue (name + category).
        // Reachable for every service, regardless of platform chips.
        $needle = function_exists('mb_strtolower') ? mb_strtolower($query) : strtolower($query);
        $all = $api->getAllServices(!$refresh);
        $matched = array_values(array_filter($all, function ($s) use ($needle) {
            $hay = strtolower(($s['name'] ?? '') . ' ' . ($s['category'] ?? ''));
            return strpos($hay, $needle) !== false;
        }));
        // Cap so the dropdown stays responsive; tell the client if we trimmed.
        $services = array_slice($matched, 0, 300);
        echo json_encode([
            'success'   => true,
            'data'      => $services,
            'count'     => count($services),
            'total'     => count($matched),
            'truncated' => count($matched) > count($services),
            'query'     => $query,
            'provider'  => $provider,
            'timestamp' => time(),
        ]);
        exit;
    }

    // Fetch services for a platform (or all when $platform is null)
    $services = $api->getServices($platform, !$refresh);

    if (empty($services)) {
        // Return error if no services
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => $api->getLastError() ?: 'No services available',
            'message' => 'Unable to fetch services from API'
        ]);
        exit;
    }
    
    // Return services
    echo json_encode([
        'success' => true,
        'data' => $services,
        'count' => count($services),
        'provider' => $provider,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>
