<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ui.php';
requireLogin();
$baseUrl = defined('APP_URL') && APP_URL ? rtrim(APP_URL, '/') : ((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com'));
ui_head('Developer API Documentation — ' . APP_NAME, 'app', '<style>
.docs-hero{background:linear-gradient(135deg,#102a43,#087f8b);border-radius:28px;padding:1.4rem 1.2rem;color:#fff;box-shadow:0 20px 42px rgba(8,127,139,.25);margin-bottom:1rem}.docs-card{background:#fff;border-radius:22px;padding:1.1rem;box-shadow:0 14px 34px rgba(43,54,116,.06);border:1px solid rgba(43,54,116,.04);margin-bottom:1rem}.docs-card h4{font-weight:800;font-size:1rem;color:#164e63}.docs-card h5{font-weight:800;font-size:.86rem;margin-top:1.1rem}.endpoint{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin:.65rem 0}.method{font:800 .68rem ui-monospace,monospace;border-radius:7px;padding:.35rem .5rem;background:#e4faf3;color:#00876a}.path{font:700 .76rem ui-monospace,monospace;color:#164e63;word-break:break-all}.code{background:#111827;color:#f9fafb;border-radius:14px;padding:.85rem;font:.71rem/1.55 ui-monospace,SFMono-Regular,Menlo,monospace;overflow:auto;white-space:pre-wrap}.note{border-left:4px solid #087f8b;background:#effafb;color:#365b65;border-radius:10px;padding:.7rem .8rem;font-size:.76rem}.docs-nav{display:flex;gap:.45rem;overflow:auto;margin-bottom:1rem}.docs-nav a{white-space:nowrap;border-radius:999px;background:#fff;border:1px solid #e5edf0;padding:.45rem .7rem;color:#087f8b;font-size:.7rem;font-weight:700}.param{width:100%;border-collapse:collapse;font-size:.72rem}.param th,.param td{text-align:left;padding:.5rem;border-bottom:1px solid #edf0f6;vertical-align:top}.param th{color:#8a93b2;font-size:.64rem;text-transform:uppercase}.badge-req{color:#b83227;font-weight:800}.badge-opt{color:#8a93b2}
</style>');
ui_nav('api');
?>
<main class="container px-3" style="padding-top:4.4rem;padding-bottom:4rem;">
  <div class="docs-hero"><div class="d-flex align-items-center gap-2"><i class="bi bi-journal-code" style="font-size:1.5rem"></i><div><h3 class="fw-bold mb-1">Developer API Documentation</h3><p class="mb-0 small opacity-75">Version 1 · Secure service catalogue and order integration</p></div></div></div>
  <nav class="docs-nav"><a href="#access">Access</a><a href="#auth">Authentication</a><a href="#services">Services</a><a href="#orders">Orders</a><a href="#errors">Errors</a><a href="#examples">Examples</a></nav>
  <section class="docs-card" id="access"><h4><i class="bi bi-shield-check me-2"></i>Before you integrate</h4><p class="small text-muted">Developer API access is intentionally gated to protect the platform and customers.</p><div class="note"><strong>Activation requirement:</strong> complete either a deposit of at least <strong>$5</strong>, or spend at least <strong>$10</strong> on completed orders. The account only needs to satisfy one condition. The API Center shows your current progress.</div><p class="small text-muted mt-3 mb-0">If a key leaks, open <a href="api-center.php">Developer API</a> and click <strong>Regenerate API key</strong>. The old key is invalid immediately; update your application with the new key.</p></section>
  <section class="docs-card" id="auth"><h4><i class="bi bi-key-fill me-2"></i>Authentication</h4><p class="small text-muted">Send your key on every developer request. The recommended header is:</p><div class="code">X-API-Key: YOUR_API_KEY</div><p class="small text-muted mt-2">Bearer authentication is also accepted:</p><div class="code">Authorization: Bearer YOUR_API_KEY</div><div class="note mt-3"><strong>Never</strong> expose the key in browser JavaScript, mobile app binaries, public Git repositories, screenshots, or client-side HTML. Keep it in server-side environment variables.</div></section>
  <section class="docs-card" id="services"><h4><i class="bi bi-list-stars me-2"></i>List services</h4><div class="endpoint"><span class="method">GET</span><span class="path"><?= htmlspecialchars($baseUrl) ?>/api-services.php</span></div><p class="small text-muted">Returns the current FastWay service catalogue. Use query parameters to filter or find a specific service.</p><table class="param"><tr><th>Parameter</th><th>Required</th><th>Description</th></tr><tr><td>platform</td><td class="badge-opt">Optional</td><td>Filter by platform, for example instagram, tiktok, youtube.</td></tr><tr><td>q</td><td class="badge-opt">Optional</td><td>Search by Service ID, name, or category.</td></tr><tr><td>service_id</td><td class="badge-opt">Optional</td><td>Return the matching service by numeric ID.</td></tr><tr><td>refresh</td><td class="badge-opt">Optional</td><td>Set true to request a fresh provider catalogue.</td></tr></table><h5>Example</h5><div class="code">curl -s "<?= htmlspecialchars($baseUrl) ?>/api-services.php?platform=instagram" \
  -H "X-API-Key: $ROYAL_SMM_API_KEY"</div><h5>Success response</h5><div class="code">{
  "success": true,
  "data": [{
    "id": 123,
    "name": "Instagram Followers",
    "category": "Followers",
    "min": 100,
    "max": 100000,
    "rate": 0.25,
    "price_per_1000": 250
  }],
  "count": 1,
  "provider": "fastway"
}</div></section>
  <section class="docs-card" id="orders"><h4><i class="bi bi-bag-check-fill me-2"></i>Create an order</h4><div class="endpoint"><span class="method" style="background:#fff6e5;color:#9a6700">POST</span><span class="path"><?= htmlspecialchars($baseUrl) ?>/place-order.php</span></div><p class="small text-muted">The server resolves the service from the live FastWay catalogue, validates limits, checks balance, submits the provider order, and records the order atomically.</p><table class="param"><tr><th>Field</th><th>Required</th><th>Description</th></tr><tr><td>service_id</td><td class="badge-req">Required</td><td>Numeric ID returned by the services endpoint.</td></tr><tr><td>quantity</td><td class="badge-req">Required</td><td>Integer within the service min/max limits.</td></tr><tr><td>link</td><td class="badge-req">Required</td><td>Public profile, post, video, or target link accepted by the selected service.</td></tr></table><h5>cURL</h5><div class="code">curl -X POST "<?= htmlspecialchars($baseUrl) ?>/place-order.php" \
  -H "X-API-Key: $ROYAL_SMM_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"service_id":123,"quantity":500,"link":"https://instagram.com/username"}'</div><h5>Success response</h5><div class="code">{
  "success": true,
  "message": "Order placed successfully.",
  "order_id": 456,
  "external_order_id": "FW-789",
  "service_id": 123,
  "quantity": 500,
  "cost": 125,
  "new_balance": 4875
}</div></section>
  <section class="docs-card" id="examples"><h4><i class="bi bi-terminal me-2"></i>Server-side examples</h4><h5>PHP</h5><div class="code">$key = getenv('ROYAL_SMM_API_KEY');
$ch = curl_init('<?= htmlspecialchars($baseUrl) ?>/api-services.php?q=123');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER =&gt; true,
  CURLOPT_HTTPHEADER =&gt; ['X-API-Key: ' . $key]
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);</div><h5>Node.js</h5><div class="code">const response = await fetch('<?= htmlspecialchars($baseUrl) ?>/place-order.php', {
  method: 'POST',
  headers: {'X-API-Key': process.env.ROYAL_SMM_API_KEY, 'Content-Type': 'application/json'},
  body: JSON.stringify({service_id: 123, quantity: 500, link: 'https://instagram.com/username'})
});
const result = await response.json();</div><h5>Python</h5><div class="code">import os, requests
r = requests.post('<?= htmlspecialchars($baseUrl) ?>/place-order.php',
  headers={'X-API-Key': os.environ['ROYAL_SMM_API_KEY']},
  json={'service_id': 123, 'quantity': 500, 'link': 'https://instagram.com/username'})
print(r.json())</div></section>
  <section class="docs-card" id="errors"><h4><i class="bi bi-exclamation-triangle me-2"></i>Errors and HTTP codes</h4><table class="param"><tr><th>Code</th><th>Meaning</th><th>Action</th></tr><tr><td>401</td><td>Missing, invalid, or old API key</td><td>Check the secret or regenerate and update your server.</td></tr><tr><td>403</td><td>Account is not eligible or inactive</td><td>Complete the $5 deposit or $10 completed-spend requirement.</td></tr><tr><td>405</td><td>Wrong HTTP method</td><td>Use GET for services and POST for orders.</td></tr><tr><td>422</td><td>Invalid service, link, or quantity</td><td>Read the service limits and correct the request.</td></tr><tr><td>503</td><td>Provider catalogue unavailable</td><td>Retry with backoff; do not submit repeatedly.</td></tr></table><div class="code mt-3">{
  "success": false,
  "message": "Human-readable explanation",
  "error": "Machine-readable context"
}</div></section>
</main>
<?php ui_foot(); ?>
