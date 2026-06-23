# Boost Pro - Advanced Social Media Management Platform

## Overview
Boost Pro is an enterprise-grade Social Media Management (SSM) system with advanced features for managing social media services, payments, and analytics across multiple platforms.

## Key Features

### 1. **Multi-Platform Support**
- Instagram, Facebook, TikTok, Twitter/X, YouTube, LinkedIn, Telegram, Snapchat, Pinterest
- Extensible architecture for adding new platforms

### 2. **Payment Gateway Integration**
- **MPESA** - M-Pesa mobile money (Tanzania)
- **Stripe** - International credit/debit card payments
- **Flutterwave** - African payment solutions
- **Paystack** - Pan-African payments
- Automatic currency conversion and rate calculation

### 3. **API Integrations**
- **Boost API** (Primary) - Lazack Organization services
- **SMMDADDY** (Backup) - Alternative SMM provider
- Fallback mechanism for service reliability
- Rate limiting and retry logic with exponential backoff

### 4. **Advanced Features**
- ✅ Two-Factor Authentication (2FA)
- ✅ Affiliate/Referral System
- ✅ Bulk Order Processing
- ✅ Scheduled Orders
- ✅ Real-time Analytics Dashboard
- ✅ Support Ticket System
- ✅ Promo Codes & Discounts
- ✅ Webhook Support for external integrations
- ✅ API Access for developers
- ✅ Activity Logging & Audit Trail
- ✅ Advanced Refund Management

### 5. **Security**
- Password hashing with bcrypt
- JWT token authentication
- Webhook signature verification
- HTTPS/SSL support
- SQL injection prevention via prepared statements
- CSRF protection
- Rate limiting (100 requests/hour by default)

## Database Tables

### Core Tables
- `users` - User accounts with roles (admin, moderator, user)
- `orders` - Service orders with status tracking
- `transactions` - Payment transactions with detailed logging
- `activity_logs` - All user activities for audit trail

### Advanced Tables
- `api_keys` - API access management
- `webhooks` - Webhook configuration and management
- `webhook_events` - Webhook event queue
- `services_cache` - Cached service data from APIs
- `analytics` - Daily analytics and statistics
- `support_tickets` - Customer support system
- `affiliate_referrals` - Referral program tracking
- `promo_codes` - Discount code management
- `bulk_orders` - Bulk order processing
- `scheduled_orders` - Recurring/scheduled orders

## Configuration

### API Keys & Endpoints
All API configurations are centralized in `config.php`:

```php
// Primary SMM Service
BOOST_API_KEY
BOOST_API_BASE_URL
BOOST_API_TIMEOUT

// Payment Gateways
MPESA_API_TOKEN
STRIPE_PUBLIC_KEY / STRIPE_SECRET_KEY
FLUTTERWAVE_PUBLIC_KEY / FLUTTERWAVE_SECRET_KEY
PAYSTACK_PUBLIC_KEY / PAYSTACK_SECRET_KEY

// Notifications
SMTP_HOST, SMTP_PORT, SMTP_USER
AFRICAS_TALKING_API_KEY
TWILIO_ACCOUNT_SID
```

## API Usage

### Using APIHandler Class

```php
require_once 'includes/APIHandler.php';

// Initialize API handler
$api = new APIHandler('boost'); // or 'mpesa', 'stripe', etc.

// Get services
$services = $api->getServices('instagram');

// Place order
$response = $api->placeOrder(123, 'https://instagram.com/user', 500, 'TZS');

// Check status
$status = $api->getOrderStatus('ORDER_ID');

// Process payment
$payment = $api->processPayment(50000, '255744123456', 'royal@example.com', 'Royal');
```

### REST API Endpoints
The system supports REST API endpoints for programmatic access:

```
POST /api/orders - Create new order
GET /api/orders/:id - Get order status
GET /api/services - List available services
POST /api/payments - Initiate payment
GET /api/analytics - Get user analytics
```

## Webhook System

### Event Types
- `order.completed` - Order completed successfully
- `order.failed` - Order failed
- `payment.completed` - Payment confirmed
- `payment.failed` - Payment failed
- `refund.issued` - Refund processed

### Webhook Verification
```php
$handler = new WebhookHandler($conn);
$handler->process(); // Automatically verifies signature
```

### Payload Example
```json
{
  "event": "order.completed",
  "order_id": "EXT_ORDER_123",
  "status": "Completed",
  "timestamp": 1234567890
}
```

## User Roles & Permissions

### User
- Place orders
- View own transactions
- Access referral link
- View analytics
- Submit support tickets
- API access

### Moderator
- Manage support tickets
- View user analytics
- Manage promo codes
- Basic reporting

### Admin
- Full system access
- User management
- API configuration
- Advanced analytics
- System settings

## Security Practices

### Password Policy
- Minimum 8 characters
- Special characters optional (configurable)
- Hashed with bcrypt (10 rounds)

### JWT Authentication
- Algorithm: HS256
- Expiry: 24 hours (configurable)
- Signed with: `JWT_SECRET_KEY`

### Rate Limiting
- 100 requests per hour by default
- Per API key or IP-based
- Configurable in `config.php`

## Utility Functions

### Available in config.php
- `sanitize($data)` - XSS prevention
- `validateEmail($email)` - Email validation
- `generateToken($length)` - Secure random tokens
- `logActivity($user_id, $action, $details)` - Activity logging
- `getUser($user_id)` - Get user info
- `isAdmin($user_id)` - Check admin status
- `formatCurrency($amount)` - Currency formatting
- `apiResponse($success, $message, $data)` - Standardized API response
- `requireLogin()` - Check login status
- `requireAdmin()` - Check admin access

## Error Handling

All API responses follow this format:
```json
{
  "success": true/false,
  "message": "Human readable message",
  "data": {
    // Response data
  },
  "timestamp": 1234567890
}
```

HTTP Status Codes:
- 200 - OK
- 201 - Created
- 400 - Bad Request
- 401 - Unauthorized
- 403 - Forbidden
- 404 - Not Found
- 429 - Too Many Requests
- 500 - Server Error

## Installation

1. Import `database.sql` to MySQL
2. Update credentials in `config.php`
3. Set up payment gateway API keys
4. Configure SMTP for emails
5. Install dependencies: `composer install`

## Files Structure

```
smm/
├── config.php                 # Advanced configuration
├── database.sql              # Database schema
├── index.php                 # Order placement
├── admin.php                 # Admin dashboard
├── login.php                 # Authentication
├── register.php              # Registration
├── profile.php               # User profile
├── topup.php                 # Payment topup
├── logout.php                # Logout
├── includes/
│   └── APIHandler.php        # API integration class
└── webhooks/
    └── handler.php           # Webhook processor
```

## Support & Documentation

For API documentation: `/api/docs`
For support: Create ticket in admin panel
Report bugs: support@boostpro.com

## License

Copyright © 2024 Boost Pro. All rights reserved.

## Version
v2.0.0 - Advanced Edition
