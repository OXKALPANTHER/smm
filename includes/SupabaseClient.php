<?php
/**
 * Supabase API Client
 * Provides database-like interface using Supabase REST API
 */

class SupabaseClient {
    private $url;
    private $key;
    private $http;
    
    public function __construct($url, $key) {
        $this->url = rtrim($url, '/');
        $this->key = $key;
    }
    
    /**
     * Execute a query against Supabase REST API
     */
    public function query($sql) {
        // For now, return a mock object that works with existing code
        return new SupabaseResult();
    }
    
    /**
     * Prepare a statement for execution
     */
    public function prepare($sql) {
        return new SupabasePrepared($this->url, $this->key, $sql);
    }
    
    /**
     * Direct REST API call
     */
    private function apiCall($method, $endpoint, $data = null) {
        $ch = curl_init();
        $url = $this->url . '/rest/v1' . $endpoint;
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->key,
                'apikey: ' . $this->key,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $http_code,
            'data' => json_decode($response, true)
        ];
    }
}

/**
 * Mock Prepared Statement
 */
class SupabasePrepared {
    private $url;
    private $key;
    private $sql;
    private $bindings = [];
    
    public function __construct($url, $key, $sql) {
        $this->url = $url;
        $this->key = $key;
        $this->sql = $sql;
    }
    
    public function bind_param($types, &...$vars) {
        $this->bindings = $vars;
        return true;
    }
    
    public function execute() {
        // Mock execution - return true for now
        return true;
    }
    
    public function get_result() {
        return new SupabaseResult();
    }
}

/**
 * Mock Result Set
 */
class SupabaseResult {
    private $data = [];
    private $position = 0;
    
    public function fetch_assoc() {
        return null;
    }
    
    public function fetch_all($type = MYSQLI_ASSOC) {
        return [];
    }
    
    public function num_rows() {
        return 0;
    }
}

?>
