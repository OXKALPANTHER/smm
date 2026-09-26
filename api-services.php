<?php
/**
 * API Endpoint to fetch services from the FastWay API
 * Used by JavaScript to populate dropdowns
 */

require_once 'config.php';
require_once 'includes/APIHandler.php';

header('Content-Type: application/json');

try {
    $platform = $_GET['platform'] ?? null;
    $query    = trim($_GET['q'] ?? '');
    $serviceId = (int) ($_GET['service_id'] ?? 0);
    $refresh  = isset($_GET['refresh']) && $_GET['refresh'] === 'true';

    // FastWay is the authoritative catalogue for every new order. Accept old
    // client aliases so cached clients keep working, but never fetch a legacy
    // Boost catalogue for a new purchase.
    $provider = 'fastway';

    // "all"/empty platform means: do not filter by platform.
    if ($platform === '__all__' || $platform === 'all' || $platform === '') {
        $platform = null;
    }

    // Initialize API handler for the chosen provider
    $api = new APIHandler($provider);

    if ($query !== '' || $serviceId > 0) {
        // Global search across the whole catalogue (ID + name + category).
        // Reachable for every service, regardless of platform chips.
        $needle = function_exists('mb_strtolower') ? mb_strtolower($query) : strtolower($query);
        $all = $api->getAllServices(!$refresh);
        $matched = array_values(array_filter($all, function ($s) use ($needle, $serviceId) {
            $hay = strtolower(($s['name'] ?? '') . ' ' . ($s['category'] ?? ''));
            return ($serviceId > 0 && (int) ($s['id'] ?? 0) === $serviceId)
                || ($needle !== '' && (strpos((string) ($s['id'] ?? ''), $needle) !== false || strpos($hay, $needle) !== false));
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
            'service_id' => $serviceId ?: null,
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
