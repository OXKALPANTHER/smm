# Quick Reference Guide - Boost Pro API & Configuration

## 🔑 Essential Configuration Variables

All located in `config.php`:

### Application Settings
```php
APP_NAME              // "Boost Pro"
APP_VERSION           // "2.0.0"
ENVIRONMENT           // 'production'|'development'|'staging'
DEBUG_MODE            // true|false
```

### Database
```php
DB_HOST       // 'localhost'
DB_USER       // 'root'
DB_PASS       // password
DB_NAME       // 't20_booster'
DB_PORT       // 3306
DB_CHARSET    // 'utf8mb4'
```

### Payment Gateways
```php
// MPESA
MPESA_API_TOKEN
MPESA_USER_ID
MPESA_BASE_URL
MPESA_CALLBACK_URL

// Stripe
STRIPE_PUBLIC_KEY
STRIPE_SECRET_KEY
STRIPE_WEBHOOK_SECRET

// Flutterwave
FLUTTERWAVE_PUBLIC_KEY
FLUTTERWAVE_SECRET_KEY
FLUTTERWAVE_BASE_URL

// Paystack
PAYSTACK_PUBLIC_KEY
PAYSTACK_SECRET_KEY
PAYSTACK_BASE_URL
```

### SMM Service APIs
```php
// Primary
BOOST_API_KEY
BOOST_API_BASE_URL

// Backup
SMMDADDY_API_KEY
SMMDADDY_API_BASE_URL
```

### Security
```php
JWT_SECRET_KEY        // Your secret key
JWT_ALGORITHM         // 'HS256'
JWT_EXPIRY            // 86400 (24 hours)
API_RATE_LIMIT        // 100 requests/hour
SESSION_TIMEOUT       // 3600 (1 hour)
PASSWORD_MIN_LENGTH   // 8 characters
```

---

## 📚 Using the APIHandler Class

### Initialize
```php
require_once 'includes/APIHandler.php';

// For Boost API
$api = new APIHandler('boost');

// For MPESA
$mpesa = new APIHandler('mpesa');

// For Stripe
$stripe = new APIHandler('stripe');
```

### Methods
```php
// Get services
$services = $api->getServices('instagram');
$services = $api->getServices(); // All services

// Place order
$response = $api->placeOrder(
    $service_id,
    'https://instagram.com/username',
    500,
    'TZS'
);

// Check order status
$status = $api->getOrderStatus('ORDER_ID');

// Process payment
$payment = $api->processPayment(
    50000,           // amount
    '255744123456',  // phone
    'royal@example.com',
    'Royal'
);

// Check payment status
$payment_status = $api->checkPaymentStatus('TRANSACTION_REF');

// Retry with backoff
$response = $api->requestWithRetry($endpoint, 'POST', $data, 3);
```

### Error Handling
```php
$response = $api->request('/services');

if (!$response['success']) {
    $error = $api->getLastError();
    $code = $api->getLastResponseCode();
}
```

---

## 🔐 Built-in Security Functions

### User Validation
```php
requireLogin();        // Require login, redirect if not
requireAdmin();        // Require admin, redirect if not
isLoggedIn();         // Check if logged in
isAdmin($user_id);    // Check if admin
```

### Input Validation
```php
sanitize($data);      // XSS prevention
validateEmail($email); // Email validation
```

### Token Generation
```php
$token = generateToken(32); // Generate secure token
```

### Activity Logging
```php
logActivity(
    $user_id,
    'action_name',
    'details',
    'success'|'failed'
);
```

---

## 💰 Currency & Formatting

```php
CURRENCY_CODE         // 'TZS'
CURRENCY_SYMBOL       // 'TSh'
MINIMUM_TOPUP         // 1000 TZS
MAXIMUM_TOPUP         // 10,000,000 TZS

// Format amount
formatCurrency(50000); // "TSh 50,000.00"
```

---

## 📊 Platforms & Services

### Supported Platforms
```php
$platforms = [
    'instagram'  => Instagram,
    'facebook'   => Facebook,
    'tiktok'     => TikTok,
    'twitter'    => Twitter/X,
    'youtube'    => YouTube,
    'linkedin'   => LinkedIn,
    'telegram'   => Telegram,
    'snapchat'   => Snapchat,
    'pinterest'  => Pinterest
];
```

---

## 🔔 Webhook Events

### Event Types
- `order.completed` - Order done
- `order.failed` - Order failed
- `payment.completed` - Payment confirmed
- `payment.failed` - Payment failed
- `refund.issued` - Refund processed

### Webhook Payload
```json
{
  "event": "order.completed",
  "data": {
    "order_id": "ORD_123",
    "status": "Completed"
  },
  "timestamp": 1234567890,
  "signature": "hmac_signature"
}
```

### Verify Webhook
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];

$computed = hash_hmac('sha256', $payload, WEBHOOK_SECRET_KEY);
if (hash_equals($computed, $signature)) {
    // Valid webhook
}
```

---

## 🎯 Response Format

All API responses follow this structure:

```php
apiResponse(
    $success,      // true|false
    $message,      // "Message text"
    $data,         // array or object
    $code          // HTTP status code
);

// Returns:
{
  "success": true,
  "message": "Order created",
  "data": { ... },
  "timestamp": 1234567890
}
```

---

## 📈 Database Tables Quick Reference

### Core Tables
- `users` - User accounts
- `orders` - Service orders
- `transactions` - Payments
- `activity_logs` - Activity history

### Feature Tables
- `api_keys` - Developer API keys
- `webhooks` - Webhook subscriptions
- `webhook_events` - Event queue
- `services_cache` - Cached services
- `analytics` - Daily statistics
- `support_tickets` - Customer support
- `affiliate_referrals` - Referral tracking
- `promo_codes` - Discount codes
- `bulk_orders` - Batch orders
- `scheduled_orders` - Recurring orders

---

## 🚀 Common Operations

### Create Order
```php
$api = new APIHandler('boost');
$response = $api->placeOrder(123, '@username', 500, 'TZS');

if ($response['success']) {
    $order_id = $response['data']['order_id'];
}
```

### Process Payment
```php
$mpesa = new APIHandler('mpesa');
$payment = $mpesa->processPayment(
    50000,
    '255744123456',
    'royal@example.com',
    'Royal'
);

$transaction_id = $payment['data']['transaction_id'];
```

### Log Activity
```php
logActivity(
    $_SESSION['user_id'],
    'order_placed',
    'Placed order for Instagram followers',
    'success'
);
```

### Format Response
```php
if ($error) {
    echo apiResponse(false, 'Insufficient balance', null, 400);
} else {
    echo apiResponse(true, 'Order created', $order_data, 201);
}
```

---

## 🔗 File Locations

```
smm/
├── config.php                 ← All configuration
├── includes/APIHandler.php    ← API integration class
├── webhooks/handler.php       ← Webhook processor
├── api-docs.php              ← API documentation
├── README.md                 ← System overview
├── UPGRADE_SUMMARY.md        ← What changed
└── database.sql              ← Database schema
```

---

## 📞 Support

- **API Docs:** Visit `/api-docs.php` in admin panel
- **Configuration:** Edit `config.php`
- **Issues:** Check `activity_logs` table
- **Errors:** Check error responses format above

---

**Happy coding! Your Boost Pro system is ready to scale. 🚀**
