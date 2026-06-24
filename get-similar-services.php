<?php
/**
 * Get similar services endpoint.
 *
 * Returns services in the same category/platform as the original.
 * Used when a service placement fails to suggest alternatives.
 *
 * Accepts: service_id (to find similar by platform/category)
 * Returns: JSON array of similar services with hidden provider names
 */

require_once 'config.php';
require_once 'includes/APIHandler.php';

header('Content-Type: application/json');

function jsonOut($success, $message, $extra = [], $code = 200) {
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

$service_id = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
$platform = trim($_GET['platform'] ?? $_POST['platform'] ?? '');

if ($service_id <= 0 && $platform === '') {
    jsonOut(false, 'Tafadhali toa service_id au platform.', [], 422);
}

try {
    // Get all services from fallback provider
    $api = new APIHandler('fastway');
    $all_services = $api->getAllServices();

    if (empty($all_services)) {
        jsonOut(true, 'Huduma nyingine hazipo kwa sasa.', ['services' => []]);
    }

    $similar = [];
    
    if ($service_id > 0) {
        // Find the original service first
        $original = null;
        foreach ($all_services as $s) {
            if ((int)$s['id'] === $service_id) {
                $original = $s;
                break;
            }
        }

        if (!$original) {
            jsonOut(true, 'Huduma nyingine hazipo kwa sasa.', ['services' => []]);
        }

        $original_category = strtolower($original['category'] ?? '');
        $original_platform = strtolower($original['name'] ?? '');

        // Find similar services in same category/platform
        foreach ($all_services as $s) {
            if ((int)$s['id'] === $service_id) continue;

            $s_category = strtolower($s['category'] ?? '');
            $s_name = strtolower($s['name'] ?? '');

            // Match by category or platform keyword
            if (strpos($s_category, $original_category) !== false || 
                strpos($s_name, strtok($original_platform, ' ')) !== false) {
                $similar[] = $s;
            }
        }
    } else if ($platform !== '') {
        // Filter by platform
        $platform_lower = strtolower($platform);
        foreach ($all_services as $s) {
            $s_category = strtolower($s['category'] ?? '');
            $s_name = strtolower($s['name'] ?? '');
            
            if (strpos($s_category, $platform_lower) !== false || 
                strpos($s_name, $platform_lower) !== false) {
                $similar[] = $s;
            }
        }
    }

    // Limit to 10 similar services
    $similar = array_slice($similar, 0, 10);

    jsonOut(true, 'Huduma nyingine zinapatikana.', [
        'services' => $similar,
        'count' => count($similar),
    ]);

} catch (Exception $e) {
    error_log("get-similar-services fatal: " . $e->getMessage());
    jsonOut(false, 'Kosa la mfumo.', [], 500);
}
?>
