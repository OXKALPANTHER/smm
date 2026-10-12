<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ui.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSameOriginRequest();
    if (($_POST['action'] ?? '') === 'regenerate_key') {
        $eligibility = developerApiEligibility($userId);
        if (!$eligibility['eligible']) {
            $notice = ['type' => 'warning', 'text' => 'API access bado haijawezeshwa. Kamilisha deposit ya angalau $5 au tumia angalau $10 kwenye orders zilizokamilika.'];
        } else {
            $newKey = bin2hex(random_bytes(32));
            $stmt = $conn->prepare('UPDATE users SET api_key = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->bind_param('si', $newKey, $userId);
            $stmt->execute();
            $notice = ['type' => 'success', 'text' => 'API key mpya imetengenezwa. Key ya zamani imezimwa mara moja.'];
        }
    }
}
$stmt = $conn->prepare('SELECT username, balance, api_key FROM users WHERE id = ?');
$stmt->bind_param('i', $userId); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];
$apiKey = (string) ($user['api_key'] ?? '');
$eligibility = developerApiEligibility($userId);
ui_head('Developer API — ' . APP_NAME, 'app', '<style>
.api-hero{background:linear-gradient(135deg,#102a43,#087f8b);border-radius:28px;padding:1.4rem 1.2rem;color:#fff;box-shadow:0 20px 42px rgba(8,127,139,.25);margin-bottom:1rem}.api-hero h2{font-weight:800;font-size:1.25rem;margin:0}.api-hero p{margin:.3rem 0 0;opacity:.86;font-size:.82rem}.api-card{background:#fff;border-radius:22px;padding:1.05rem;box-shadow:0 14px 34px rgba(43,54,116,.06);border:1px solid rgba(43,54,116,.04);margin-bottom:1rem}.api-title{font-weight:800;font-size:1rem;display:flex;align-items:center;gap:.6rem}.api-ico{width:40px;height:40px;border-radius:13px;background:linear-gradient(135deg,#00a8a3,#087f8b);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.05rem}.status{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .65rem;border-radius:99px;font-size:.7rem;font-weight:800}.status.ok{background:#e4faf3;color:#00876a}.status.locked{background:#fff6e5;color:#9a6700}.key-box{background:#f7fbfc;border:1px solid #dcebed;border-radius:15px;padding:.85rem;word-break:break-all;font:600 .76rem ui-monospace,SFMono-Regular,Menlo,monospace;color:#164e63}.btn-api{border:0;border-radius:13px;padding:.65rem .85rem;font-size:.76rem;font-weight:800}.btn-primary-api{background:#087f8b;color:#fff}.btn-danger-api{background:#fff0ed;color:#b83227}.code-block{background:#111827;color:#f9fafb;border-radius:14px;padding:.9rem;font:.72rem/1.55 ui-monospace,SFMono-Regular,Menlo,monospace;overflow:auto;white-space:pre-wrap}.metric{background:#f8faff;border:1px solid #edf0f7;border-radius:15px;padding:.7rem}.metric small{display:block;color:#8a93b2;font-size:.65rem}.metric strong{display:block;color:#2b3674;margin-top:.15rem}.api-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}@media(max-width:400px){.api-grid{grid-template-columns:1fr}}
</style>');
ui_nav('api', ['balance' => $user['balance'] ?? 0]);
?>
<main class="container px-3" style="padding-top:4.4rem;padding-bottom:4rem;">
  <div class="api-hero"><div class="d-flex align-items-center gap-2"><i class="bi bi-code-slash" style="font-size:1.5rem"></i><div><h2>Developer API</h2><p>Build integrations, automate orders, and read services securely.</p></div></div></div>
  <?php if ($notice): ?><div class="alert alert-<?= htmlspecialchars($notice['type']) ?> py-2 small"><?= htmlspecialchars($notice['text']) ?></div><?php endif; ?>
  <div class="api-card"><div class="d-flex justify-content-between align-items-start gap-2"><div class="api-title"><span class="api-ico"><i class="bi bi-shield-lock"></i></span> API access</div><span class="status <?= $eligibility['eligible'] ? 'ok' : 'locked' ?>"><i class="bi <?= $eligibility['eligible'] ? 'bi-check-circle-fill' : 'bi-lock-fill' ?>"></i><?= $eligibility['eligible'] ? 'Enabled' : 'Locked' ?></span></div>
    <?php if (!$eligibility['eligible']): ?><p class="small text-muted mt-3 mb-2">Ili kuwezesha developer API, timiza sharti moja:</p><ul class="small text-muted mb-3"><li>tumia angalau <strong>$10</strong> kwenye orders zilizokamilika; au</li><li>kamilisha deposit moja ya angalau <strong>$5</strong>.</li></ul><?php else: ?><p class="small text-muted mt-3 mb-2">Umetimiza sharti la kutumia API. Key mpya huanza kufanya kazi mara moja.</p><?php endif; ?>
    <div class="api-grid mb-3"><div class="metric"><small>Completed spend</small><strong><?= number_format($eligibility['spend']) ?> TSh</strong></div><div class="metric"><small>Largest deposit</small><strong><?= number_format($eligibility['deposit']) ?> TSh</strong></div></div>
    <form method="post" onsubmit="return confirm('Regenerate API key? Key ya zamani itaacha kufanya kazi mara moja.')"><input type="hidden" name="action" value="regenerate_key"><button class="btn-api <?= $eligibility['eligible'] ? 'btn-primary-api' : 'btn-secondary' ?>" <?= $eligibility['eligible'] ? '' : 'disabled' ?>><i class="bi bi-arrow-repeat me-1"></i><?= $apiKey ? 'Regenerate API key' : 'Generate API key' ?></button></form>
    <?php if ($apiKey && $eligibility['eligible']): ?><div class="key-box mt-3" id="apiKeyValue"><?= htmlspecialchars($apiKey) ?></div><button class="btn-api btn-primary-api mt-2" onclick="navigator.clipboard.writeText(document.getElementById('apiKeyValue').textContent.trim());toast('API key copied.','success')"><i class="bi bi-clipboard me-1"></i> Copy key</button><p class="small text-danger mt-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Usiiweke key kwenye frontend ya public website. Ikivuja, regenerate mara moja.</p><?php endif; ?>
  </div>
  <div class="api-card"><div class="api-title mb-2"><span class="api-ico"><i class="bi bi-lightning-charge"></i></span> Quick start</div><div class="code-block">GET <?= htmlspecialchars((defined('APP_URL') ? rtrim(APP_URL,'/') : '') . '/api-services.php') ?>
X-API-Key: YOUR_API_KEY

POST <?= htmlspecialchars((defined('APP_URL') ? rtrim(APP_URL,'/') : '') . '/place-order.php') ?>
Content-Type: application/json
X-API-Key: YOUR_API_KEY

{"service_id":123,"quantity":100,"link":"https://instagram.com/username"}</div></div>
  <div class="api-card"><div class="api-title mb-2"><span class="api-ico"><i class="bi bi-journal-code"></i></span> Full documentation</div><p class="small text-muted">Endpoint reference, authentication, eligibility errors, request examples, and integration guidance.</p><a href="api-docs.php" class="btn-api btn-primary-api"><i class="bi bi-book me-1"></i> Open API documentation</a></div>
</main>
<?php ui_foot(); ?>
