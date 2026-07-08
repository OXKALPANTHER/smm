<?php
require_once 'config.php';
require_once 'includes/ui.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, balance, api_key FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$apiKey = $user['api_key'] ?? '';
if ($apiKey === '') {
    $apiKey = bin2hex(random_bytes(16));
    $stmt = $conn->prepare("UPDATE users SET api_key = ? WHERE id = ?");
    $stmt->bind_param("si", $apiKey, $user_id);
    $stmt->execute();
}

ui_head('API Center — ' . APP_NAME, 'app');
?>
<style>
.api-hero{background:linear-gradient(135deg,#6c5ce7 0%,#4834d4 100%);border-radius:28px;padding:1.35rem 1.2rem;color:#fff;box-shadow:0 20px 42px rgba(72,52,212,.28);margin-bottom:1rem;}
.api-hero h2{font-weight:800;font-size:1.25rem;margin:0;}
.api-hero p{margin:.3rem 0 0;opacity:.9;font-size:.85rem;}
.api-card{background:#fff;border-radius:22px;padding:1rem 1.05rem;box-shadow:0 14px 34px rgba(43,54,116,.06);border:1px solid rgba(43,54,116,.04);margin-bottom:1rem;}
.section-title{font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.6rem;margin-bottom:.2rem;}
.section-ico{width:40px;height:40px;border-radius:13px;background:linear-gradient(135deg,#6c5ce7,#4834d4);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex:0 0 auto;}
.small-muted{font-size:.78rem;color:#8a93b2;}
.code-block{background:#111827;color:#f9fafb;border-radius:14px;padding:.9rem;font-size:.8rem;overflow:auto;white-space:pre;}
.key-box{display:flex;gap:.6rem;align-items:center;justify-content:space-between;background:#f8faff;border:1px solid #edf0f7;border-radius:14px;padding:.8rem .9rem;flex-wrap:wrap;}
.key-box code{word-break:break-all;font-size:.8rem;}
.btn-copy{background:#6c5ce7;color:#fff;border:none;border-radius:999px;padding:.55rem .8rem;font-weight:700;}
.doc-list{margin:0;padding-left:1rem;color:#4b5563;font-size:.85rem;}
.doc-list li{margin:.35rem 0;}
</style>

<div class="container px-3" style="margin-top:-1.2rem;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="orders.php" class="btn btn-outline-light btn-sm rounded-pill" style="border-color:rgba(255,255,255,.35);color:#fff;background:rgba(255,255,255,.14);backdrop-filter:blur(8px);">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="api-hero">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-key-fill" style="font-size:1.4rem;"></i>
            <div>
                <h2>API Center</h2>
                <p>Generate, manage, and test your API access for reseller integrations.</p>
            </div>
        </div>
    </div>

    <div class="api-card">
        <div class="section-title"><span class="section-ico"><i class="bi bi-shield-lock"></i></span> Your API Key</div>
        <p class="small-muted mb-3">Keep this key private. Use it in your applications and scripts.</p>
        <div class="key-box">
            <code><?= htmlspecialchars($apiKey) ?></code>
            <button class="btn-copy" onclick="navigator.clipboard.writeText('<?= addslashes($apiKey) ?>'); toast('API key copied.', 'success');"><i class="bi bi-clipboard"></i> Copy</button>
        </div>
    </div>

    <div class="api-card">
        <div class="section-title"><span class="section-ico"><i class="bi bi-lightning-charge"></i></span> Quick Start</div>
        <div class="mb-3">
            <div class="fw-bold mb-2">Fetch services</div>
            <div class="code-block">GET /api-services.php?provider=premium</div>
        </div>
        <div class="mb-3">
            <div class="fw-bold mb-2">Place an order</div>
            <div class="code-block">POST /place-order.php
Headers:
  Authorization: Bearer <?= htmlspecialchars($apiKey) ?>
Body:
  {
    "service_id": 123,
    "quantity": 100,
    "link": "https://instagram.com/username",
    "provider": "premium"
  }</div>
        </div>
    </div>

    <div class="api-card">
        <div class="section-title"><span class="section-ico"><i class="bi bi-journal-text"></i></span> Documentation</div>
        <p class="small-muted mb-3">Read the full reference and integration examples.</p>
        <a href="api-docs.php" class="btn-copy" style="display:inline-flex;text-decoration:none;">Open Full Documentation</a>
        <ul class="doc-list mt-3">
            <li>Service listing endpoint with visible price and limits</li>
            <li>Order placement endpoint with balance deduction and provider routing</li>
            <li>Safe handling for premium services and hidden provider selection</li>
        </ul>
    </div>
</div>

<?php ui_foot(); ?>
