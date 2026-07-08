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

// The app uses the MYSQLI_ASSOC constant with the PDO compat layer. When the
// mysqli extension isn't loaded (e.g. the Docker image), define it ourselves so
// fetch_all(MYSQLI_ASSOC) doesn't fatal on PHP 8.
if (!defined('MYSQLI_ASSOC')) { define('MYSQLI_ASSOC', 1); }
if (!defined('MYSQLI_NUM'))   { define('MYSQLI_NUM', 2); }
if (!defined('MYSQLI_BOTH'))  { define('MYSQLI_BOTH', 3); }

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
        // Schema is created once via supabase_schema.sql in the SQL editor.
        // We still reconcile columns added after that schema was first applied,
        // otherwise an order INSERT referencing a missing column (e.g. gateway)
        // aborts the whole transaction on Postgres and silently rolls back the
        // balance deduction — the order is never saved yet the user sees success.
        ensurePgRuntimeColumns($pdo);
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
                gateway TEXT DEFAULT 'primary',
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
 * Reconcile orders columns on Postgres/Supabase (idempotent).
 *
 * Postgres supports `ADD COLUMN IF NOT EXISTS`, so unlike the SQLite path we
 * can issue these blindly. Keep this list in sync with the orders columns the
 * app writes to (see place-order.php and order-sync.php).
 */
function ensurePgRuntimeColumns($pdo) {
    $additions = [
        'refill_available'    => "SMALLINT DEFAULT 0",
        'refill_requested'    => "SMALLINT DEFAULT 0",
        'refill_status'       => "TEXT",
        'refill_requested_at' => "TIMESTAMPTZ",
        'provider'            => "TEXT DEFAULT 'boost'",
        // Provider lane shown on the orders page (primary = Kawaida,
        // partner = Pro/FastWay). The order INSERT depends on this column;
        // without it every order INSERT aborts the transaction and rolls back.
        'gateway'             => "TEXT DEFAULT 'primary'",
    ];
    foreach ($additions as $name => $def) {
        try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS {$name} {$def}");
        } catch (Exception $e) {
            error_log("ensurePgRuntimeColumns ({$name}): " . $e->getMessage());
        }
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
            // Which SMM provider this order was placed with, so status syncs and
            // refunds route to the right API. Existing rows default to 'boost'.
            'provider'            => "TEXT DEFAULT 'boost'",
            // Provider lane used by the orders page (primary = Kawaida,
            // partner = Pro/FastWay). The order INSERT and orders.php both
            // depend on this column; without it every order INSERT rolls back.
            'gateway'             => "TEXT DEFAULT 'primary'",
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
// Keys are the lowercase keyword matched against the provider's service
// name + category (see APIHandler::getServices). Order is the display order.
define('PLATFORMS', json_encode([
    'instagram'  => ['name' => 'Instagram',  'icon' => 'fab fa-instagram'],
    'tiktok'     => ['name' => 'TikTok',     'icon' => 'fab fa-tiktok'],
    'facebook'   => ['name' => 'Facebook',   'icon' => 'fab fa-facebook'],
    'youtube'    => ['name' => 'YouTube',    'icon' => 'fab fa-youtube'],
    'twitter'    => ['name' => 'Twitter/X',  'icon' => 'fab fa-x-twitter'],
    'telegram'   => ['name' => 'Telegram',   'icon' => 'fab fa-telegram'],
    'whatsapp'   => ['name' => 'WhatsApp',   'icon' => 'fab fa-whatsapp'],
    'spotify'    => ['name' => 'Spotify',    'icon' => 'fab fa-spotify'],
    'threads'    => ['name' => 'Threads',    'icon' => 'fab fa-threads'],
    'snapchat'   => ['name' => 'Snapchat',   'icon' => 'fab fa-snapchat'],
    'linkedin'   => ['name' => 'LinkedIn',   'icon' => 'fab fa-linkedin'],
    'pinterest'  => ['name' => 'Pinterest',  'icon' => 'fab fa-pinterest'],
    'discord'    => ['name' => 'Discord',    'icon' => 'fab fa-discord'],
    'twitch'     => ['name' => 'Twitch',     'icon' => 'fab fa-twitch'],
    'soundcloud' => ['name' => 'SoundCloud', 'icon' => 'fab fa-soundcloud'],
    'reddit'     => ['name' => 'Reddit',     'icon' => 'fab fa-reddit'],
    'google'     => ['name' => 'Google',     'icon' => 'fab fa-google'],
    'kick'       => ['name' => 'Kick',       'icon' => 'fas fa-broadcast-tower'],
    'audiomack'  => ['name' => 'Audiomack',  'icon' => 'fas fa-music'],
    'shazam'     => ['name' => 'Shazam',     'icon' => 'fas fa-music'],
]));

// ============================================
// EXTERNAL APIs - SOCIAL MEDIA SERVICES
// ============================================

// Primary SMM Service - Boost API (Lazack Organization).
define('BOOST_API_KEY', '5673ca1f6e026c293a54efb2c2cc228e8b08c48488e3df12e0f1136b87f3770b');
define('BOOST_API_BASE_URL', 'https://boostapi.lazackorganisation.my.id/api/v1');
define('BOOST_API_TIMEOUT', 30);
define('BOOST_API_VERIFY_SSL', true);

// Fallback SMM Service - FastWay (Perfect Panel API: POST /api/v2 with key+action).
// FastWay quotes rates in USD/1000, so prices are converted to TZS via USD_TO_TZS_RATE.
define('FASTWAY_API_KEY', getenv('FASTWAY_API_KEY') ?: '1b4f31a4b94de5c1fd6f3314e7a42294');
define('FASTWAY_API_BASE_URL', 'https://fastwaysmm.com/api/v2');
define('FASTWAY_API_TIMEOUT', 30);
define('FASTWAY_API_VERIFY_SSL', true);

// USD -> TZS conversion for providers that quote in USD (FastWay). Applied to
// the provider's raw rate BEFORE PRICE_MARKUP_PERCENT is added.
define('USD_TO_TZS_RATE', (float)(getenv('USD_TO_TZS_RATE') ?: 3500));

// SMM providers in priority order: primary first, fallback after.
define('SMM_PROVIDERS', json_encode(['boost', 'fastway']));

// Backup SMM Service - Alternative Provider
define('SMMDADDY_API_KEY', 'your_smmdaddy_api_key');
define('SMMDADDY_API_BASE_URL', 'https://api.smmdaddy.com/v1');
define('SMMDADDY_API_TIMEOUT', 30);

// ============================================
// PAYMENT GATEWAY INTEGRATIONS
// ============================================

// MPESA / PalmPesa (Tanzania Mobile Money) — https://palmpesa.drmlelwa.co.tz/
define('MPESA_API_TOKEN', getenv('PALMPESA_API_TOKEN') ?: 'E6NERlSG1Q0d8IFAILb1PoawHpCliX5fQKonfcxNtqExtpr8Mo7GzwsrE6q5');
define('MPESA_USER_ID', getenv('PALMPESA_USER_ID') ?: '498');
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
// e.g. 60 => the user sees & pays 60% more than the API price.
define('PRICE_MARKUP_PERCENT', 60);

// Referral reward: the inviter earns this % of a friend's FIRST top-up.
define('REFERRAL_BONUS_PERCENT', 50);

// ============================================
// SUPPORT / COMMUNITY LINKS
// ============================================
define('WHATSAPP_SUPPORT_PHONE', '255627417402'); // direct chat (wa.me)
define('WHATSAPP_GROUP_URL',   'https://chat.whatsapp.com/IPY94YyDh8N4qUx1yN3zj5');
define('WHATSAPP_CHANNEL_URL', 'https://whatsapp.com/channel/0029VbAjawl9MF8vQQa0ZT32');

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
 * Award the referral bonus when a referred user makes their FIRST top-up.
 *
 * Credits the inviter REFERRAL_BONUS_PERCENT% of the friend's first completed
 * deposit. Must be called *inside* the DB transaction that marks the deposit
 * completed (so it's atomic and runs exactly once per first deposit).
 *
 * @param mixed $conn         active connection
 * @param int   $depositorId  the user who just deposited
 * @param float $depositAmount the completed deposit amount
 * @return float the bonus credited (0 if none)
 */
function applyReferralBonus($conn, $depositorId, $depositAmount) {
    $depositorId = (int)$depositorId;
    $depositAmount = (float)$depositAmount;
    if ($depositorId <= 0 || $depositAmount <= 0) return 0;

    // Who invited this user?
    $stmt = $conn->prepare("SELECT referred_by, username FROM users WHERE id = ?");
    $stmt->bind_param("i", $depositorId);
    $stmt->execute();
    $dep = $stmt->get_result()->fetch_assoc();
    $referrerId = (int)($dep['referred_by'] ?? 0);
    if ($referrerId <= 0) return 0;

    // Only on the depositor's FIRST completed top-up (type='credit').
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM transactions WHERE user_id = ? AND type = 'credit' AND status = 'completed'");
    $stmt->bind_param("i", $depositorId);
    $stmt->execute();
    $completed = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    if ($completed !== 1) return 0; // not the first deposit

    $pct   = defined('REFERRAL_BONUS_PERCENT') ? (float)REFERRAL_BONUS_PERCENT : 50;
    $bonus = floor($depositAmount * $pct / 100);
    if ($bonus <= 0) return 0;

    // Credit the inviter.
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->bind_param("di", $bonus, $referrerId);
    $stmt->execute();

    $depName = $dep['username'] ?? ('#' . $depositorId);
    $desc = "Zawadi ya rufaa: {$pct}% ya deposit ya kwanza ya {$depName}";
    $stmt = $conn->prepare(
        "INSERT INTO transactions (user_id, amount, type, payment_method, gateway, description, external_ref, status, completed_at)
         VALUES (?, ?, 'referral_bonus', 'bonus', 'referral', ?, ?, 'completed', CURRENT_TIMESTAMP)"
    );
    $ref = 'REF-' . $depositorId;
    $stmt->bind_param("idss", $referrerId, $bonus, $desc, $ref);
    $stmt->execute();

    logActivity($referrerId, 'referral_bonus', "Bonus {$bonus} TZS kutoka {$depName} (deposit {$depositAmount})", 'success');
    return $bonus;
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
        // Without Accept: application/json, Laravel APIs (e.g. PalmPesa) treat
        // the call as a browser request and 302-redirect to their homepage.
        'Accept: application/json',
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

/**
 * Call API with automatic fallback from Fastway to Boost
 * Usage: $result = callApiWithFallback('getServices', 'instagram');
 */
function callApiWithFallback($method, ...$args) {
    require_once __DIR__ . '/includes/APIHandler.php';
    return APIHandler::withFallback($method, ...$args);
}

?>