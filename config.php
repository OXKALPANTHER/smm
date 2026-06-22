<?php
// Advanced config.php - SMM Platform Configuration

// Guard: only execute once per request. Prevents "Constant already defined"
// warnings and double session_start() if config.php is pulled in more than once
// (some server setups resolve the include path so require_once can't dedupe).
if (defined('ROYAL_CONFIG_LOADED')) { return; }
define('ROYAL_CONFIG_LOADED', true);

// In production, never let stray warnings/notices corrupt output or headers.
if (!headers_sent()) {
    ini_set('display_errors', '0');
}
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ENVIRONMENT & SECURITY SETTINGS
// ============================================
define('APP_NAME', 'Royal');
define('APP_VERSION', '2.0.0');
define('ENVIRONMENT', 'production'); // development, staging, production
define('DEBUG_MODE', false);

// ============================================
// DATABASE CONFIGURATION
// ============================================
// Driver is chosen by the DB_DRIVER env var:
//   'sqlite' (default) -> local file DB, zero setup
//   'pgsql'            -> Supabase / PostgreSQL (set DB_* env vars below)
//
// For Supabase, copy the "Connection string" values from
//   Project Settings -> Database -> Connection info  (use the Session pooler)
// and set these environment variables on your host:
//   DB_DRIVER=pgsql
//   DB_HOST=aws-0-xxx.pooler.supabase.com
//   DB_PORT=5432
//   DB_NAME=postgres
//   DB_USER=postgres.xxxxxxxx
//   DB_PASS=your-db-password
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite');
define('DB_PATH', __DIR__ . '/data/booster.db');

// Supabase REST API Credentials (optional, for REST integrations)
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://urrdrmyewuvfzqjuceng.supabase.co');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: 'sb_publishable_hDDdvKmEw560zXvS6_8fQQ_UiU8-bsh');

// Create database connection with error handling
try {
    if (DB_DRIVER === 'pgsql') {
        // ---- PostgreSQL / Supabase ----
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT') ?: '5432';
        $name = getenv('DB_NAME') ?: 'postgres';
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        $dsn  = "pgsql:host={$host};port={$port};dbname={$name};sslmode=require";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->query('SELECT 1');
        // Schema is created once via supabase_schema.sql in the SQL editor,
        // so no init/migration is run here.
    } else {
        // ---- SQLite (local default) ----
        if (!is_dir(__DIR__ . '/data')) {
            mkdir(__DIR__ . '/data', 0755, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->query('SELECT 1');
        initializeSQLiteDatabase($pdo);
        ensureRuntimeColumns($pdo);
    }

    // Wrap PDO with MySQLi compatibility layer
    require_once __DIR__ . '/includes/MySQLiCompat.php';
    $conn = new MySQLiCompatibility($pdo);

    if (ENVIRONMENT === 'development') {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (Exception $e) {
    error_log("DB connection failed: " . $e->getMessage());
    if (DEBUG_MODE) {
        die("Connection failed: " . $e->getMessage());
    } else {
        die("System Error: Unable to connect to database");
    }
}

/**
 * Cross-driver date() expression for grouping/formatting by day.
 */
function db_date_expr($col) {
    return DB_DRIVER === 'pgsql' ? "CAST({$col} AS DATE)" : "DATE({$col})";
}

/**
 * Initialize SQLite database with schema
 */
function initializeSQLiteDatabase($pdo) {
    try {
        // Check if tables exist
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if ($result->fetch()) {
            return; // Database already initialized
        }
        
        // Create tables
        $pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                phone TEXT,
                password TEXT NOT NULL,
                balance REAL DEFAULT 0.00,
                role TEXT DEFAULT 'user',
                status TEXT DEFAULT 'active',
                two_factor_enabled INTEGER DEFAULT 0,
                two_factor_secret TEXT,
                referral_code TEXT UNIQUE,
                referred_by INTEGER REFERENCES users(id),
                api_key TEXT UNIQUE,
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                service_id INTEGER NOT NULL,
                service_name TEXT NOT NULL,
                service_category TEXT,
                platform TEXT,
                quantity INTEGER NOT NULL,
                price REAL NOT NULL,
                status TEXT DEFAULT 'Pending',
                progress INTEGER DEFAULT 0,
                external_order_id TEXT,
                link TEXT NOT NULL,
                notes TEXT,
                refund_requested INTEGER DEFAULT 0,
                refund_reason TEXT,
                refund_amount REAL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
                amount REAL NOT NULL,
                type TEXT NOT NULL,
                payment_method TEXT,
                gateway TEXT,
                description TEXT,
                external_ref TEXT,
                status TEXT DEFAULT 'pending',
                metadata TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME
            );
            
            CREATE TABLE activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER REFERENCES users(id),
                action TEXT NOT NULL,
                details TEXT,
                status TEXT DEFAULT 'success',
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE support_tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                subject TEXT NOT NULL,
                message TEXT NOT NULL,
                status TEXT DEFAULT 'open',
                priority TEXT DEFAULT 'medium',
                response TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE promo_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                discount_type TEXT,
                discount_value REAL,
                usage_limit INTEGER,
                used_count INTEGER DEFAULT 0,
                expiry_date DATETIME,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE INDEX idx_users_email ON users(email);
            CREATE INDEX idx_users_referral_code ON users(referral_code);
            CREATE INDEX idx_orders_user_id ON orders(user_id);
            CREATE INDEX idx_orders_status ON orders(status);
            CREATE INDEX idx_transactions_user_id ON transactions(user_id);
            CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
        ");
    } catch (Exception $e) {
        // Tables may already exist, ignore
    }
}

/**
 * Add columns introduced after the initial schema (idempotent).
 */
function ensureRuntimeColumns($pdo) {
    try {
        $cols = [];
        foreach ($pdo->query("PRAGMA table_info(orders)") as $c) {
            $cols[$c['name']] = true;
        }
        $additions = [
            'refill_available'    => "INTEGER DEFAULT 0",
            'refill_requested'    => "INTEGER DEFAULT 0",
            'refill_status'       => "TEXT",
            'refill_requested_at' => "DATETIME",
        ];
        foreach ($additions as $name => $def) {
            if (!isset($cols[$name])) {
                $pdo->exec("ALTER TABLE orders ADD COLUMN {$name} {$def}");
            }
        }
    } catch (Exception $e) {
        error_log("ensureRuntimeColumns: " . $e->getMessage());
    }
}

// ============================================
// SOCIAL MEDIA PLATFORMS
// ============================================
define('PLATFORMS', json_encode([
    'instagram' => ['name' => 'Instagram', 'icon' => 'fab fa-instagram'],
    'facebook' => ['name' => 'Facebook', 'icon' => 'fab fa-facebook'],
    'tiktok' => ['name' => 'TikTok', 'icon' => 'fab fa-tiktok'],
    'twitter' => ['name' => 'Twitter/X', 'icon' => 'fab fa-x-twitter'],
    'youtube' => ['name' => 'YouTube', 'icon' => 'fab fa-youtube'],
    'linkedin' => ['name' => 'LinkedIn', 'icon' => 'fab fa-linkedin'],
    'telegram' => ['name' => 'Telegram', 'icon' => 'fab fa-telegram'],
    'snapchat' => ['name' => 'Snapchat', 'icon' => 'fab fa-snapchat'],
    'pinterest' => ['name' => 'Pinterest', 'icon' => 'fab fa-pinterest']
]));

// ============================================
// EXTERNAL APIs - SOCIAL MEDIA SERVICES
// ============================================

// Primary SMM Service - Boost API (Lazack Organization)
define('BOOST_API_KEY', '5673ca1f6e026c293a54efb2c2cc228e8b08c48488e3df12e0f1136b87f3770b');
define('BOOST_API_BASE_URL', 'https://boostapi.lazackorganisation.my.id/api/v1');
define('BOOST_API_TIMEOUT', 30);
define('BOOST_API_VERIFY_SSL', true);

// Backup SMM Service - Alternative Provider
define('SMMDADDY_API_KEY', 'your_smmdaddy_api_key');
define('SMMDADDY_API_BASE_URL', 'https://api.smmdaddy.com/v1');
define('SMMDADDY_API_TIMEOUT', 30);

// ============================================
// PAYMENT GATEWAY INTEGRATIONS
// ============================================

// MPESA (Tanzania Mobile Money)
define('MPESA_API_TOKEN', 'token toka palmpesa');
define('MPESA_USER_ID', 'weka id toka palmpesa');
define('MPESA_BASE_URL', 'https://palmpesa.drmlelwa.co.tz/api');
define('MPESA_CALLBACK_URL', 'https://yourdomain.com/webhooks/mpesa.php');
define('MPESA_TIMEOUT', 30);

// Stripe (International Payments)
define('STRIPE_PUBLIC_KEY', 'pk_test_your_stripe_key');
define('STRIPE_SECRET_KEY', 'sk_test_your_stripe_key');
define('STRIPE_WEBHOOK_SECRET', 'whsec_your_webhook_secret');

// Flutterwave (African Payments)
define('FLUTTERWAVE_PUBLIC_KEY', 'FLWPUBK_your_key');
define('FLUTTERWAVE_SECRET_KEY', 'FLWSECK_your_key');
define('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3');
define('FLUTTERWAVE_CALLBACK_URL', 'https://yourdomain.com/webhooks/flutterwave.php');

// Paystack (African Payments)
define('PAYSTACK_PUBLIC_KEY', 'pk_test_your_paystack_key');
define('PAYSTACK_SECRET_KEY', 'sk_test_your_paystack_key');
define('PAYSTACK_BASE_URL', 'https://api.paystack.co');
define('PAYSTACK_CALLBACK_URL', 'https://yourdomain.com/webhooks/paystack.php');

// ============================================
// NOTIFICATION SERVICES
// ============================================

// Email Configuration (SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password');
define('SMTP_FROM_NAME', APP_NAME);
define('SMTP_FROM_EMAIL', 'noreply@boostpro.com');
define('SMTP_USE_TLS', true);

// SMS Gateway - Africa's Talking
define('AFRICAS_TALKING_API_KEY', 'your_africas_talking_key');
define('AFRICAS_TALKING_USERNAME', 'your_username');
define('AFRICAS_TALKING_BASE_URL', 'https://api.sandbox.africastalking.com');

// SMS Gateway - Twilio
define('TWILIO_ACCOUNT_SID', 'your_twilio_sid');
define('TWILIO_AUTH_TOKEN', 'your_twilio_token');
define('TWILIO_FROM_NUMBER', '+1234567890');

// ============================================
// WEBHOOK CONFIGURATION
// ============================================
define('WEBHOOK_TIMEOUT', 30);
define('WEBHOOK_RETRY_ATTEMPTS', 3);
define('WEBHOOK_RETRY_DELAY', 300); // 5 minutes
define('WEBHOOK_SECRET_KEY', 'your_webhook_secret_key_for_signing');

// ============================================
// SECURITY SETTINGS
// ============================================
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('JWT_SECRET_KEY', 'your_jwt_secret_key_here');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 86400); // 24 hours
define('API_RATE_LIMIT', 100); // requests per hour
define('API_RATE_LIMIT_WINDOW', 3600);

// ============================================
// FEATURE FLAGS
// ============================================
define('FEATURE_TWO_FACTOR', true);
define('FEATURE_AFFILIATE', true);
define('FEATURE_BULK_ORDERS', true);
define('FEATURE_SCHEDULING', true);
define('FEATURE_ANALYTICS', true);
define('FEATURE_API_ACCESS', true);

// ============================================
// CURRENCY & PRICING
// ============================================
define('CURRENCY_CODE', 'TZS');
define('CURRENCY_SYMBOL', 'TSh');
define('MINIMUM_TOPUP', 1000);
define('MAXIMUM_TOPUP', 10000000);

// Profit margin added on top of the provider's real price (percent).
// e.g. 20 => the user sees & pays 20% more than the API price.
define('PRICE_MARKUP_PERCENT', 20);

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Sanitize input
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate unique token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Log activities
 */
function logActivity($user_id, $action, $details = '', $status = 'success') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $status);
    return $stmt->execute();
}

/**
 * Get user by ID
 */
function getUser($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Check if user is admin
 */
function isAdmin($user_id = null) {
    $uid = $user_id ?? ($_SESSION['user_id'] ?? null);
    if (!$uid) return false;
    $user = getUser($uid);
    return $user && $user['role'] === 'admin';
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Redirect if not admin
 */
function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2, '.', ',');
}

/**
 * API Response Helper
 */
function apiResponse($success, $message = '', $data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    return json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
}

/**
 * Make API Call with advanced features
 */
function makeAPICall($service, $endpoint, $method = 'GET', $data = null, $headers = []) {
    $api_key = '';
    $base_url = '';
    $timeout = 30;
    
    switch($service) {
        case 'boost':
            $api_key = BOOST_API_KEY;
            $base_url = BOOST_API_BASE_URL;
            $timeout = BOOST_API_TIMEOUT;
            break;
        case 'mpesa':
            $api_key = MPESA_API_TOKEN;
            $base_url = MPESA_BASE_URL;
            $timeout = MPESA_TIMEOUT;
            break;
        case 'smmdaddy':
            $api_key = SMMDADDY_API_KEY;
            $base_url = SMMDADDY_API_BASE_URL;
            break;
        default:
            return ['success' => false, 'error' => 'Unknown service'];
    }
    
    $url = $base_url . $endpoint;
    $ch = curl_init();
    
    $default_headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
        'User-Agent: ' . APP_NAME . '/' . APP_VERSION
    ];
    
    $headers = array_merge($default_headers, $headers);
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => defined('BOOST_API_VERIFY_SSL') ? BOOST_API_VERIFY_SSL : true,
    ]);
    
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error, 'code' => $http_code];
    }
    
    return [
        'success' => $http_code >= 200 && $http_code < 300,
        'code' => $http_code,
        'data' => json_decode($response, true)
    ];
}

?>