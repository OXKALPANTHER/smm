<?php
/**
 * API Documentation & Testing
 * 
 * This file provides comprehensive API documentation and testing endpoints
 */

require_once 'config.php';
requireLogin();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - API Documentation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #2b3674;
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            padding: 2rem 0;
        }
        .container { max-width: 1200px; }
        .doc-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .doc-section h2 {
            color: var(--primary);
            border-bottom: 3px solid var(--secondary);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .endpoint {
            background: #f8f9fa;
            border-left: 4px solid var(--primary);
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 5px;
        }
        .method {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .method.get { background: #0dcaf0; }
        .method.post { background: #198754; }
        .method.put { background: #0d6efd; }
        .method.delete { background: #dc3545; }
        code {
            background: #f8f9fa;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            color: #e83e8c;
        }
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 1rem;
            border-radius: 5px;
            overflow-x: auto;
        }
        .api-key-display {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
        }
        .toc {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .toc ul { list-style: none; padding: 0; }
        .toc li { margin: 0.5rem 0; }
        .toc a {
            color: var(--primary);
            text-decoration: none;
            transition: 0.2s;
        }
        .toc a:hover { color: var(--secondary); text-decoration: underline; }
        .parameter-table {
            width: 100%;
            border-collapse: collapse;
        }
        .parameter-table th {
            background: var(--primary);
            color: white;
            padding: 0.8rem;
            text-align: left;
        }
        .parameter-table td {
            border: 1px solid #ddd;
            padding: 0.8rem;
        }
        .parameter-table tr:nth-child(even) { background: #f9f9f9; }
        .response-example {
            margin: 1rem 0;
        }
        .badge-required {
            background: #dc3545;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
        }
        .badge-optional {
            background: #6c757d;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="mb-3">
            <a href="orders.php" class="btn btn-outline-light btn-sm rounded-pill" style="border-color:rgba(255,255,255,.35);color:#fff;background:rgba(255,255,255,.14);backdrop-filter:blur(8px);">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <div class="doc-section">
            <h1 class="mb-3"><?php echo APP_NAME; ?> API Documentation</h1>
            <p class="lead">Complete reference for <?php echo APP_NAME; ?> REST API and integrations.</p>
            <p><strong>Version:</strong> <?php echo APP_VERSION; ?></p>
        </div>

        <!-- Table of Contents -->
        <div class="toc">
            <h4>Quick Navigation</h4>
            <ul>
                <li><a href="#authentication">Authentication</a></li>
                <li><a href="#services">Services API</a></li>
                <li><a href="#orders">Orders API</a></li>
                <li><a href="#payments">Payments API</a></li>
                <li><a href="#analytics">Analytics API</a></li>
                <li><a href="#webhooks">Webhooks</a></li>
                <li><a href="#errors">Error Handling</a></li>
            </ul>
        </div>

        <!-- Authentication Section -->
        <div class="doc-section" id="authentication">
            <h2>Authentication</h2>
            <p>All API requests require authentication using an API key or JWT token.</p>

            <h4 class="mt-4">API Key Authentication</h4>
            <p>Include your API key in the request header:</p>
            <pre><code class="language-bash">curl -H "Authorization: Bearer YOUR_API_KEY" https://yoursite.com/api/endpoint</code></pre>

            <h4 class="mt-4">JWT Token</h4>
            <p>Obtain a token by logging in, then use it for subsequent requests:</p>
            <pre><code class="language-bash">curl -H "Authorization: Bearer JWT_TOKEN" https://yoursite.com/api/endpoint</code></pre>

            <div class="api-key-display">
                <strong>🔑 Your API Key:</strong> Generate from account settings
            </div>
        </div>

        <!-- Services API -->
        <div class="doc-section" id="services">
            <h2>Services API</h2>
            <p>Retrieve available social media services and their details.</p>

            <div class="endpoint">
                <span class="method get">GET</span>
                <code>/api/services</code>
                <p class="mt-2"><strong>Description:</strong> Get list of all available services</p>
            </div>

            <h5 class="mt-4">Query Parameters</h5>
            <table class="parameter-table">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>platform</td>
                        <td>string</td>
                        <td><span class="badge-optional">Optional</span></td>
                        <td>Filter by platform (instagram, facebook, tiktok, etc.)</td>
                    </tr>
                    <tr>
                        <td>category</td>
                        <td>string</td>
                        <td><span class="badge-optional">Optional</span></td>
                        <td>Filter by category (followers, likes, views, etc.)</td>
                    </tr>
                    <tr>
                        <td>limit</td>
                        <td>integer</td>
                        <td><span class="badge-optional">Optional</span></td>
                        <td>Number of results (default: 50, max: 500)</td>
                    </tr>
                </tbody>
            </table>

            <h5 class="mt-4">Example Response</h5>
            <div class="response-example">
                <pre><code class="language-json">{
  "success": true,
  "data": {
    "services": [
      {
        "id": 123,
        "name": "Instagram Followers",
        "platform": "instagram",
        "category": "followers",
        "min_quantity": 100,
        "max_quantity": 100000,
        "prices": {
          "TZS": 150.00
        },
        "description": "Add Instagram followers to your account"
      }
    ]
  },
  "timestamp": 1234567890
}</code></pre>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <code>/api/services/:service_id</code>
                <p class="mt-2"><strong>Description:</strong> Get details of a specific service</p>
            </div>
        </div>

        <!-- Orders API -->
        <div class="doc-section" id="orders">
            <h2>Orders API</h2>
            <p>Create and manage service orders.</p>

            <div class="endpoint">
                <span class="method post">POST</span>
                <code>/api/orders</code>
                <p class="mt-2"><strong>Description:</strong> Create a new order</p>
            </div>

            <h5 class="mt-4">Request Body</h5>
            <table class="parameter-table">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>service_id</td>
                        <td>integer</td>
                        <td><span class="badge-required">Required</span></td>
                        <td>ID of the service to order</td>
                    </tr>
                    <tr>
                        <td>link</td>
                        <td>string</td>
                        <td><span class="badge-required">Required</span></td>
                        <td>Username or post link</td>
                    </tr>
                    <tr>
                        <td>quantity</td>
                        <td>integer</td>
                        <td><span class="badge-required">Required</span></td>
                        <td>Quantity of service</td>
                    </tr>
                </tbody>
            </table>

            <h5 class="mt-4">Example Request</h5>
            <pre><code class="language-bash">curl -X POST https://yoursite.com/api/orders \
  -H "Authorization: Bearer API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "service_id": 123,
    "link": "https://instagram.com/username",
    "quantity": 500
  }'</code></pre>

            <h5 class="mt-4">Example Response</h5>
            <pre><code class="language-json">{
  "success": true,
  "data": {
    "order_id": "ORD_1234567890",
    "external_order_id": "EXT_ORD_123",
    "status": "Pending",
    "service_name": "Instagram Followers",
    "quantity": 500,
    "price": 75000,
    "currency": "TZS",
    "created_at": "2024-01-15T10:30:00Z"
  },
  "message": "Order created successfully",
  "timestamp": 1234567890
}</code></pre>

            <div class="endpoint">
                <span class="method get">GET</span>
                <code>/api/orders/:order_id</code>
                <p class="mt-2"><strong>Description:</strong> Get order status</p>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <code>/api/orders</code>
                <p class="mt-2"><strong>Description:</strong> Get all user orders (paginated)</p>
            </div>
        </div>

        <!-- Payments API -->
        <div class="doc-section" id="payments">
            <h2>Payments API</h2>
            <p>Handle payment processing and balance management.</p>

            <div class="endpoint">
                <span class="method post">POST</span>
                <code>/api/payments/initiate</code>
                <p class="mt-2"><strong>Description:</strong> Initiate a payment/top-up</p>
            </div>

            <h5 class="mt-4">Request Body</h5>
            <table class="parameter-table">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>amount</td>
                        <td>decimal</td>
                        <td>Amount to top-up</td>
                    </tr>
                    <tr>
                        <td>phone</td>
                        <td>string</td>
                        <td>Phone number for payment</td>
                    </tr>
                    <tr>
                        <td>email</td>
                        <td>string</td>
                        <td>Email address</td>
                    </tr>
                    <tr>
                        <td>gateway</td>
                        <td>string</td>
                        <td>Payment gateway (mpesa, stripe, flutterwave, paystack)</td>
                    </tr>
                </tbody>
            </table>

            <div class="endpoint">
                <span class="method get">GET</span>
                <code>/api/payments/status/:transaction_id</code>
                <p class="mt-2"><strong>Description:</strong> Check payment status</p>
            </div>

            <div class="endpoint">
                <span class="method get">GET</span>
                <code>/api/account/balance</code>
                <p class="mt-2"><strong>Description:</strong> Get current account balance</p>
            </div>
        </div>

        <!-- Analytics API -->
        <div class="doc-section" id="analytics">
            <h2>Analytics API</h2>
            <p>Access detailed analytics and statistics.</p>

            <div class="endpoint">
                <span class="method get">GET</span>
                <code>/api/analytics/dashboard</code>
                <p class="mt-2"><strong>Description:</strong> Get dashboard analytics (summary)</p>
            </div>

            <h5 class="mt-4">Example Response</h5>
            <pre><code class="language-json">{
  "success": true,
  "data": {
    "total_orders": 150,
    "total_spent": 45000,
    "completed_orders": 145,
    "pending_orders": 5,
    "failed_orders": 0,
    "current_balance": 5000,
    "monthly_spending": 12000,
    "top_platform": "instagram"
  }
}</code></pre>

            <div class="endpoint">
                <span class="method get">GET</span>
                <code>/api/analytics/history</code>
                <p class="mt-2"><strong>Description:</strong> Get detailed order history with filters</p>
            </div>

            <h5 class="mt-4">Query Parameters</h5>
            <table class="parameter-table">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Type</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>start_date</td>
                        <td>date (YYYY-MM-DD)</td>
                        <td>Filter from date</td>
                    </tr>
                    <tr>
                        <td>end_date</td>
                        <td>date (YYYY-MM-DD)</td>
                        <td>Filter to date</td>
                    </tr>
                    <tr>
                        <td>status</td>
                        <td>string</td>
                        <td>Filter by status (Pending, Completed, Failed)</td>
                    </tr>
                    <tr>
                        <td>page</td>
                        <td>integer</td>
                        <td>Page number for pagination</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Webhooks -->
        <div class="doc-section" id="webhooks">
            <h2>Webhooks</h2>
            <p>Real-time event notifications for important platform events.</p>

            <h5 class="mt-4">Event Types</h5>
            <ul>
                <li><code>order.completed</code> - Order completed successfully</li>
                <li><code>order.failed</code> - Order failed</li>
                <li><code>payment.completed</code> - Payment confirmed</li>
                <li><code>payment.failed</code> - Payment failed</li>
                <li><code>refund.issued</code> - Refund processed</li>
            </ul>

            <h5 class="mt-4">Webhook Payload Structure</h5>
            <pre><code class="language-json">{
  "event": "order.completed",
  "data": {
    "order_id": "ORD_1234567890",
    "external_order_id": "EXT_ORD_123",
    "service_name": "Instagram Followers",
    "quantity": 500,
    "status": "Completed"
  },
  "timestamp": 1234567890,
  "signature": "hmac_sha256_signature"
}</code></pre>

            <h5 class="mt-4">Verifying Webhooks</h5>
            <p>Verify webhook signatures using HMAC-SHA256 with your webhook secret:</p>
            <pre><code class="language-php">$signature = hash_hmac('sha256', $payload, WEBHOOK_SECRET_KEY);
if (hash_equals($signature, $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'])) {
    // Webhook is valid
}</code></pre>

            <h5 class="mt-4">Setting Up Webhooks</h5>
            <div class="endpoint">
                <span class="method post">POST</span>
                <code>/api/webhooks</code>
                <p class="mt-2"><strong>Description:</strong> Create a new webhook</p>
            </div>

            <pre><code class="language-bash">curl -X POST https://yoursite.com/api/webhooks \
  -H "Authorization: Bearer API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-server.com/webhooks",
    "events": ["order.completed", "payment.completed"]
  }'</code></pre>
        </div>

        <!-- Error Handling -->
        <div class="doc-section" id="errors">
            <h2>Error Handling</h2>
            <p>The API returns standard HTTP status codes and error messages.</p>

            <h5 class="mt-4">HTTP Status Codes</h5>
            <table class="parameter-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Meaning</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>200</td>
                        <td>OK - Request successful</td>
                    </tr>
                    <tr>
                        <td>201</td>
                        <td>Created - Resource created successfully</td>
                    </tr>
                    <tr>
                        <td>400</td>
                        <td>Bad Request - Invalid parameters</td>
                    </tr>
                    <tr>
                        <td>401</td>
                        <td>Unauthorized - Missing or invalid API key</td>
                    </tr>
                    <tr>
                        <td>403</td>
                        <td>Forbidden - Insufficient permissions</td>
                    </tr>
                    <tr>
                        <td>404</td>
                        <td>Not Found - Resource not found</td>
                    </tr>
                    <tr>
                        <td>429</td>
                        <td>Too Many Requests - Rate limit exceeded</td>
                    </tr>
                    <tr>
                        <td>500</td>
                        <td>Server Error - Internal server error</td>
                    </tr>
                </tbody>
            </table>

            <h5 class="mt-4">Error Response Format</h5>
            <pre><code class="language-json">{
  "success": false,
  "message": "Insufficient balance",
  "error_code": "INSUFFICIENT_BALANCE",
  "timestamp": 1234567890
}</code></pre>

            <h5 class="mt-4">Common Error Codes</h5>
            <ul>
                <li><code>INVALID_API_KEY</code> - Invalid or expired API key</li>
                <li><code>INSUFFICIENT_BALANCE</code> - User has insufficient balance</li>
                <li><code>SERVICE_NOT_FOUND</code> - Requested service not found</li>
                <li><code>INVALID_QUANTITY</code> - Quantity is outside allowed range</li>
                <li><code>RATE_LIMIT_EXCEEDED</code> - Too many requests</li>
                <li><code>EXTERNAL_API_ERROR</code> - Error from upstream provider</li>
            </ul>
        </div>

        <!-- Footer -->
        <div class="doc-section text-center">
            <p class="mb-0"><small>Last updated: <?php echo date('Y-m-d'); ?> | Version <?php echo APP_VERSION; ?></small></p>
            <p><small>For support, contact: support@<?php echo strtolower(APP_NAME); ?>.com</small></p>
        </div>
    </div>

    <script>
        hljs.highlightAll();
    </script>
</body>
</html>
