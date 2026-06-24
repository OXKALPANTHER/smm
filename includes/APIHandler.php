<?php
/**
 * Advanced API Handler for Royal Platform.
 *
 * Speaks two SMM provider protocols behind one interface:
 *   - 'boost'        Lazack Boost REST API (JSON, prices already in TZS).
 *   - 'perfectpanel' FastWay (POST /api/v2, form-encoded key+action, USD rates).
 *
 * Provider selection / failover lives in includes/provider.php; this class just
 * talks to whichever single provider it was constructed with.
 */

class APIHandler {
    private $service;
    private $api_key;
    private $base_url;
    private $timeout;
    private $verify_ssl;
    private $protocol;        // 'boost' | 'perfectpanel'
    private $cache_dir;
    private $cache_duration = 3600; // 1 hour
    private $last_error = '';
    private $last_response_code = 0;

    public function __construct($service = 'boost') {
        $this->service = strtolower($service);
        $this->cache_dir = __DIR__ . '/../data/cache';

        // Ensure cache directory exists
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }

        $this->configureService($this->service);
    }

    /**
     * Configure API settings based on service
     */
    private function configureService($service) {
        switch($service) {
            case 'fastway':
                $this->api_key    = FASTWAY_API_KEY;
                $this->base_url   = FASTWAY_API_BASE_URL;
                $this->timeout    = FASTWAY_API_TIMEOUT;
                $this->verify_ssl = FASTWAY_API_VERIFY_SSL;
                $this->protocol   = 'perfectpanel';
                break;
            case 'boost':
                $this->api_key    = BOOST_API_KEY;
                $this->base_url   = BOOST_API_BASE_URL;
                $this->timeout    = BOOST_API_TIMEOUT;
                $this->verify_ssl = BOOST_API_VERIFY_SSL;
                $this->protocol   = 'boost';
                break;
            case 'smmdaddy':
                $this->api_key    = SMMDADDY_API_KEY;
                $this->base_url   = SMMDADDY_API_BASE_URL;
                $this->timeout    = SMMDADDY_API_TIMEOUT;
                $this->verify_ssl = true;
                $this->protocol   = 'perfectpanel'; // smmdaddy is also Perfect Panel
                break;
            case 'mpesa':
                $this->api_key    = MPESA_API_TOKEN;
                $this->base_url   = MPESA_BASE_URL;
                $this->timeout    = MPESA_TIMEOUT;
                $this->verify_ssl = true;
                $this->protocol   = 'boost';
                break;
            case 'stripe':
                $this->api_key    = STRIPE_SECRET_KEY;
                $this->base_url   = 'https://api.stripe.com/v1';
                $this->verify_ssl = true;
                $this->protocol   = 'boost';
                break;
            default:
                throw new Exception("Unknown service: $service");
        }
    }

    /** Which provider this handler talks to (e.g. 'fastway', 'boost'). */
    public function getProvider() {
        return $this->service;
    }

    /**
     * Static method for automatic fallback: tries services in priority order
     * defined in SMM_PROVIDERS config. Returns on first success or last error.
     *
     * Usage: $result = APIHandler::withFallback('placeOrder', $id, $link, $qty);
     */
    public static function withFallback($method, ...$args) {
        $providers = json_decode(SMM_PROVIDERS, true) ?: ['boost', 'fastway'];
        $lastError = null;
        
        foreach ($providers as $provider) {
            try {
                $handler = new self($provider);
                
                if (!method_exists($handler, $method)) {
                    continue;
                }
                
                $result = call_user_func_array([$handler, $method], $args);
                
                // Check if the result indicates success
                if (is_array($result) && isset($result['success']) && $result['success']) {
                    error_log("API call successful with provider: $provider, method: $method");
                    return $result;
                } elseif (is_array($result) && !isset($result['success'])) {
                    // If no success key, assume it worked (e.g., array of services)
                    error_log("API call returned data from provider: $provider, method: $method");
                    return $result;
                }
                
                $lastError = $result['error'] ?? 'Unknown error';
                error_log("API call failed with $provider/$method: " . json_encode($result));
                
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                error_log("Exception with $provider/$method: " . $e->getMessage());
            }
        }
        
        // All services failed
        return [
            'success' => false,
            'error' => "All providers failed. Last error: $lastError",
        ];
    }
    
    /**
     * Get services for a platform (or all). The Boost API ignores the
     * `category` query param and always returns the full catalogue, so we
     * cache the full formatted list once and filter by platform in PHP.
     */
    public function getServices($platform = null, $use_cache = true) {
        $all = $this->getAllServices($use_cache);

        if (!$platform) {
            return $all;
        }

        $needle = strtolower(trim($platform));
        // Normalise a couple of display names to their matchable keyword.
        $needle = str_replace(['twitter/x', 'twitter / x'], 'twitter', $needle);

        return array_values(array_filter($all, function ($s) use ($needle) {
            $haystack = strtolower(($s['name'] ?? '') . ' ' . ($s['category'] ?? ''));
            return strpos($haystack, $needle) !== false;
        }));
    }

    /**
     * Fetch and cache the full formatted service catalogue.
     */
    public function getAllServices($use_cache = true) {
        $cache_key = "services_all_v4"; // bumped when pricing/markup logic changes (markup 50%)

        if ($use_cache) {
            $cached = $this->getCache($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $response = $this->request('/services', 'GET');

        if ($response['success'] && !empty($response['data'])) {
            $services_data = $this->extractServicesFromResponse($response['data']);
            $services = $this->formatServices($services_data);

            if (!empty($services)) {
                $this->setCache($cache_key, $services);
            }

            return $services;
        }

        $this->last_error = $response['error'] ?? 'Failed to fetch services';
        error_log("API Error fetching services: " . json_encode($response));

        return [];
    }
    
    /**
     * Extract services data from various API response formats
     */
    private function extractServicesFromResponse($data) {
        // Try different common API response structures
        if (isset($data['services'])) {
            return $data['services'];
        } elseif (isset($data['data'])) {
            return $data['data'];
        } elseif (isset($data['results'])) {
            return $data['results'];
        } elseif (is_array($data) && !isset($data['id']) && !isset($data['name'])) {
            // Might be an array of services directly
            return $data;
        }
        
        return [];
    }
    
    /**
     * Format services for consistency
     */
    private function formatServices($services) {
        if (!is_array($services)) {
            return [];
        }
        
        $formatted = [];
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }

            $id = $service['service_id'] ?? $service['id'] ?? null;

            // The Boost API returns `price_tzs` = price per 1000 units in TZS
            // and `rate` = price per 1000 in USD. The app charges in TZS, so the
            // per-unit rate is price_tzs / 1000.
            $price_per_k = (float)($service['price_tzs'] ?? 0);
            $rate_usd = (float)($service['rate'] ?? $service['price'] ?? 0);

            if ($price_per_k <= 0 && $rate_usd > 0) {
                // Fallback: derive TZS from USD using the documented 3500 rate + 35% markup.
                $price_per_k = round($rate_usd * 3500 * 1.35);
            }

            // Apply our profit margin on top of the provider's real price.
            $markup = defined('PRICE_MARKUP_PERCENT') ? (float)PRICE_MARKUP_PERCENT : 0;
            $price_per_k = $price_per_k * (1 + $markup / 100);

            $per_unit = $price_per_k > 0 ? $price_per_k / 1000 : 0;

            $formatted[] = [
                'id' => $id !== null ? (int)$id : null,
                'name' => $service['name'] ?? $service['service_name'] ?? $service['title'] ?? 'Unknown',
                'category' => $service['category'] ?? $service['platform'] ?? $service['type'] ?? 'General',
                'description' => $service['description'] ?? $service['desc'] ?? '',
                'min' => (int)($service['min'] ?? $service['min_quantity'] ?? $service['minimum'] ?? 10),
                'max' => (int)($service['max'] ?? $service['max_quantity'] ?? $service['maximum'] ?? 10000),
                'rate' => round($per_unit, 5),          // per-unit price in TZS
                'price_per_1000' => round($price_per_k), // TZS per 1000 units
                'rate_usd' => $rate_usd,                 // provider per-1000 USD rate
                'currency' => CURRENCY_CODE,
                'api_id' => $id !== null ? (int)$id : null,
                'status' => $service['status'] ?? 'active',
                'refill' => !empty($service['refill']),
                'cancel' => !empty($service['cancel']),
            ];
        }

        return $formatted;
    }
    
    /**
     * Place an order
     */
    public function placeOrder($service_id, $link, $quantity, $email = null) {
        // Different providers expect different formats
        if ($this->protocol === 'perfectpanel') {
            // FastWay: Perfect Panel uses form-encoded POST with key+action
            $data = [
                'key'      => $this->api_key,
                'action'   => 'add',
                'service'  => (int)$service_id,
                'link'     => $link,
                'quantity' => (int)$quantity,
            ];
            if ($email) {
                $data['email'] = $email;
            }
            
            $response = $this->requestFormEncoded('/add', 'POST', $data);
        } else {
            // Boost API: JSON with service_id, username_or_link, quantity
            $data = [
                'service_id'       => (int)$service_id,
                'username_or_link' => $link,
                'quantity'         => (int)$quantity,
                'source'           => 'web',
            ];
            if ($email) {
                $data['email'] = $email;
            }
            
            $response = $this->request('/order', 'POST', $data);
        }
        
        $body = $response['data'] ?? [];

        if ($response['success'] && empty($body['error'])) {
            return [
                'success'  => true,
                'order_id' => $body['fastwayOrderId'] ?? $body['order_id'] ?? $body['id'] ?? $body['order'] ?? null,
                'status'   => $body['status'] ?? 'Pending',
                'charge'   => $body['charge'] ?? null,
                'data'     => $body,
            ];
        }

        return [
            'success' => false,
            'error'   => $body['error'] ?? $response['error'] ?? 'Order failed',
            'data'    => $body,
        ];
    }
    
    /**
     * Make form-encoded API request (for Perfect Panel / FastWay)
     */
    private function requestFormEncoded($endpoint, $method = 'POST', $data = null, $params = []) {
        $url = rtrim($this->base_url, '/') . $endpoint;
        
        // Add query parameters
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Royal/' . APP_VERSION,
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $this->verify_ssl,
            CURLOPT_SSL_VERIFYHOST => $this->verify_ssl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ]);
        
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                // Form-encode the data
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        
        $response = curl_exec($ch);
        $this->last_response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            $this->last_error = $error;
            error_log("CURL Error: $error (Endpoint: $endpoint)");
            return [
                'success' => false,
                'error' => $error,
                'code' => $this->last_response_code
            ];
        }
        
        $decoded = json_decode($response, true);
        $success = $this->last_response_code >= 200 && $this->last_response_code < 300;
        
        if (!$success) {
            $this->last_error = $decoded['error'] ?? $decoded['message'] ?? 'API Error';
            error_log("API Error ($method $endpoint): " . json_encode($decoded));
        }
        
        return [
            'success' => $success,
            'code' => $this->last_response_code,
            'data' => $decoded,
            'error' => !$success ? $this->last_error : null
        ];
    }
    
    /**
     * Check order status
     */
    public function getOrderStatus($order_id) {
        $response = $this->request('/order/' . rawurlencode((string)$order_id));

        if (empty($response['success'])) {
            return ['success' => false, 'error' => $this->last_error ?: 'Failed to check order'];
        }

        // The order object may sit at the top level or be nested under
        // `order` / `data`, depending on the provider's response shape.
        $body  = is_array($response['data'] ?? null) ? $response['data'] : [];
        $order = $body;
        if (isset($body['order']) && is_array($body['order'])) {
            $order = $body['order'];
        } elseif (isset($body['data']) && is_array($body['data'])) {
            $order = $body['data'];
        }

        $status = $order['status']
            ?? $order['order_status']
            ?? $order['state']
            ?? $body['status']
            ?? 'unknown';

        return [
            'success'  => true,
            'status'   => is_string($status) ? trim($status) : 'unknown',
            'progress' => $order['progress'] ?? $order['remains'] ?? 0,
            'data'     => $body,
        ];
    }
    
    /**
     * Get account balance
     */
    public function getBalance($currency = 'TZS') {
        $response = $this->request('/balance');

        if ($response['success']) {
            $data = $response['data'] ?? [];
            if (isset($data['balances'][$currency])) {
                return (float)$data['balances'][$currency];
            }
            return (float)($data['balance'] ?? 0);
        }

        return 0;
    }
    
    /**
     * Make API request with proper headers and error handling
     */
    private function request($endpoint, $method = 'GET', $data = null, $params = []) {
        $url = rtrim($this->base_url, '/') . $endpoint;
        
        // Add query parameters
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'User-Agent: Royal/' . APP_VERSION,
        ];
        
        // Add authentication. The Boost API authenticates order/balance
        // endpoints via the X-API-Key header (Bearer is only tolerated on
        // the public /services endpoint).
        $headers[] = 'X-API-Key: ' . $this->api_key;
        if ($this->service === 'boost') {
            $headers[] = 'Authorization: Bearer ' . $this->api_key;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $this->verify_ssl,
            CURLOPT_SSL_VERIFYHOST => $this->verify_ssl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ]);
        
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $this->last_response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            $this->last_error = $error;
            error_log("CURL Error: $error (Endpoint: $endpoint)");
            return [
                'success' => false,
                'error' => $error,
                'code' => $this->last_response_code
            ];
        }
        
        $decoded = json_decode($response, true);
        $success = $this->last_response_code >= 200 && $this->last_response_code < 300;
        
        if (!$success) {
            $this->last_error = $decoded['error'] ?? $decoded['message'] ?? 'API Error';
            error_log("API Error ($method $endpoint): " . json_encode($decoded));
        }
        
        return [
            'success' => $success,
            'code' => $this->last_response_code,
            'data' => $decoded,
            'error' => !$success ? $this->last_error : null
        ];
    }
    
    /**
     * Get cached data
     */
    private function getCache($key) {
        $file = $this->cache_dir . '/' . md5($key) . '.cache';
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            
            if (isset($data['expires']) && $data['expires'] > time()) {
                return $data['value'];
            }
            
            @unlink($file);
        }
        
        return false;
    }
    
    /**
     * Set cache
     */
    private function setCache($key, $value) {
        $file = $this->cache_dir . '/' . md5($key) . '.cache';
        
        $data = [
            'value' => $value,
            'expires' => time() + $this->cache_duration,
            'created' => time()
        ];
        
        @file_put_contents($file, json_encode($data));
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        $files = glob($this->cache_dir . '/*.cache');
        foreach ((array)$files as $file) {
            @unlink($file);
        }
    }
    
    /**
     * Get last error
     */
    public function getLastError() {
        return $this->last_error;
    }
    
    /**
     * Get last response code
     */
    public function getLastResponseCode() {
        return $this->last_response_code;
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        $response = $this->request('/services');
        return $response['success'];
    }
}

?>
