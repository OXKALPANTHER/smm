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
    $refresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';
    
    // Initialize API handler
    $api = new APIHandler('boost');
    
    // Fetch services
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
