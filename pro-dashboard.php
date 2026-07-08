<?php
require_once 'config.php';
require_once 'includes/ui.php';
requireLogin();

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT username, balance, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$tzsBalance = (float)($user['balance'] ?? 0);
$username = $user['username'] ?? 'Mteja';

ui_head('Pro Dashboard — ' . APP_NAME, 'app');
?>
<style>
.pro-hero{background:linear-gradient(135deg,#6c5ce7 0%,#4834d4 100%);border-radius:28px;padding:1.35rem 1.2rem;color:#fff;box-shadow:0 20px 42px rgba(72,52,212,.28);margin-bottom:1rem;}
.pro-hero h2{font-weight:800;font-size:1.25rem;margin:0;}
.pro-hero p{margin:.3rem 0 0;opacity:.9;font-size:.85rem;}
.pro-card{background:#fff;border-radius:22px;padding:1rem 1.05rem;box-shadow:0 14px 34px rgba(43,54,116,.06);border:1px solid rgba(43,54,116,.04);margin-bottom:1rem;}
.section-title{font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.6rem;margin-bottom:.2rem;}
.section-ico{width:40px;height:40px;border-radius:13px;background:linear-gradient(135deg,#6c5ce7,#4834d4);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex:0 0 auto;}
.stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem;margin-top:.8rem;}
.stat-box{background:#f8faff;border:1px solid #edf0f7;border-radius:16px;padding:.75rem;text-align:center;}
.stat-box .k{font-size:.62rem;text-transform:uppercase;letter-spacing:.6px;color:#8a93b2;font-weight:700;}
.stat-box .v{font-size:1rem;font-weight:800;color:#2b3674;margin-top:.2rem;}
.form-group{display:flex;flex-direction:column;gap:.35rem;margin-bottom:.8rem;}
.form-group label{font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;}
.form-control,.form-select{border-radius:14px;border:1.5px solid #e9edf7;padding:.75rem .85rem;background:#fafbff;font-size:.9rem;}
.form-control:focus,.form-select:focus{outline:none;border-color:#6c5ce7;box-shadow:0 0 0 4px rgba(108,92,231,.12);background:#fff;}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:.75rem .85rem;border:1px solid #eceff7;border-radius:14px;background:#f9fbff;}
.toggle-row input{width:18px;height:18px;accent-color:#6c5ce7;}
.price-box{background:linear-gradient(135deg,#f3f1ff,#ecfbf8);border:1px dashed rgba(108,92,231,.35);border-radius:18px;padding:1rem;text-align:center;margin-top:.4rem;}
.price-box .lbl{font-size:.72rem;font-weight:700;color:#8a93b2;text-transform:uppercase;letter-spacing:.5px;}
.price-box .val{font-size:1.6rem;font-weight:800;color:#4834d4;margin:.2rem 0 .1rem;}
.price-box .hint{font-size:.78rem;color:#5a6a85;}
.btn-pro{width:100%;background:linear-gradient(135deg,#6c5ce7,#4834d4);color:#fff;border:none;border-radius:14px;padding:.8rem 1rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:.45rem;box-shadow:0 12px 24px rgba(72,52,212,.24);}
.btn-pro:disabled{opacity:.6;cursor:not-allowed;}
.code-block{background:#111827;color:#f9fafb;border-radius:14px;padding:.9rem;font-size:.8rem;overflow:auto;white-space:pre;}
.doc-list{margin:0;padding-left:1rem;color:#4b5563;font-size:.85rem;}
.doc-list li{margin-bottom:.4rem;}
.small-muted{font-size:.78rem;color:#8a93b2;}
@media (max-width:768px){.stat-grid{grid-template-columns:1fr;}}
</style>

<div class="container px-3" style="margin-top:-1.2rem;">
    <div class="pro-hero">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-rocket-fill" style="font-size:1.4rem;"></i>
            <div>
                <h2>Pro Reseller Dashboard</h2>
                <p>Browse premium services, view real prices, and place orders from a polished reseller workspace.</p>
            </div>
        </div>
    </div>

    <div class="pro-card">
        <div class="section-title"><span class="section-ico"><i class="bi bi-speedometer2"></i></span> Dashboard Overview</div>
        <div class="stat-grid">
            <div class="stat-box">
                <div class="k">Balance</div>
                <div class="v"><?= number_format($tzsBalance, 0) ?> TZS</div>
            </div>
            <div class="stat-box">
                <div class="k">Account</div>
                <div class="v"><?= htmlspecialchars($username) ?></div>
            </div>
            <div class="stat-box">
                <div class="k">Mode</div>
                <div class="v">Premium</div>
            </div>
        </div>
    </div>

    <div class="pro-card">
        <div class="section-title"><span class="section-ico"><i class="bi bi-box-seam"></i></span> Service Explorer</div>
        <p class="small-muted mb-3">Choose a service, see its price clearly, and place an order in seconds.</p>

        <div class="toggle-row">
            <label for="usePremium" style="display:flex;align-items:center;gap:.55rem;font-weight:700;color:#374151;cursor:pointer;margin:0;">
                <input type="checkbox" id="usePremium">
                Use Premium Network
            </label>
            <span class="small-muted">This routes through our premium service pool in the background.</span>
        </div>

        <div class="form-group" style="margin-top:.9rem;">
            <label for="serviceSelect">Service</label>
            <select id="serviceSelect" class="form-select" required>
                <option value="">Loading services...</option>
            </select>
        </div>

        <div class="form-group">
            <label for="quantityInput">Quantity</label>
            <input type="number" id="quantityInput" class="form-control" min="1" value="100" placeholder="Enter quantity">
        </div>

        <div class="form-group">
            <label for="linkInput">Link / Username</label>
            <input type="text" id="linkInput" class="form-control" placeholder="https://...">
        </div>

        <div id="servicePreview" class="price-box" style="display:none;">
            <div class="lbl">Selected Service</div>
            <div class="val" id="servicePrice">0 TZS</div>
            <div class="hint" id="serviceHint">Choose a service to view pricing and limits.</div>
        </div>

        <button class="btn-pro mt-3" id="placeOrderBtn"><i class="bi bi-send-fill"></i> Place Order</button>
    </div>

    <div class="pro-card">
        <div class="section-title"><span class="section-ico"><i class="bi bi-code-square"></i></span> API & Documentation</div>
        <p class="small-muted mb-3">Use the endpoints below to resell services programmatically.</p>

        <div class="mb-3">
            <div class="fw-bold mb-2">1. Fetch services</div>
            <div class="code-block">GET /api-services.php?provider=premium</div>
        </div>

        <div class="mb-3">
            <div class="fw-bold mb-2">2. Place an order</div>
            <div class="code-block">POST /place-order.php
{
  "service_id": 123,
  "quantity": 100,
  "link": "https://instagram.com/username",
  "provider": "premium"
}</div>
        </div>

        <div>
            <div class="fw-bold mb-2">Response fields</div>
            <ul class="doc-list">
                <li>Service name, category, minimum and maximum order size</li>
                <li>Visible price per 1,000 units and per-unit rate</li>
                <li>Automatic order creation and balance deduction</li>
            </ul>
        </div>
    </div>
</div>

<script>
(function () {
  const serviceSelect = document.getElementById('serviceSelect');
  const quantityInput = document.getElementById('quantityInput');
  const linkInput = document.getElementById('linkInput');
  const usePremium = document.getElementById('usePremium');
  const placeOrderBtn = document.getElementById('placeOrderBtn');
  const servicePreview = document.getElementById('servicePreview');
  const servicePrice = document.getElementById('servicePrice');
  const serviceHint = document.getElementById('serviceHint');
  let services = [];

  async function loadServices() {
    const provider = usePremium.checked ? 'premium' : 'boost';
    try {
      const url = new URL('api-services.php', window.location.href);
      url.searchParams.set('provider', provider);
      const r = await fetch(url.toString());
      const j = await r.json();
      if (!j.success) throw new Error(j.error || 'Unable to load services');
      services = j.data || [];
      serviceSelect.innerHTML = '<option value="">-- Choose a service --</option>';
      services.forEach((s) => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = `${s.name} • ${Number(s.price_per_1000 || s.rate || 0).toLocaleString()} TZS / 1K`;
        opt.dataset.service = JSON.stringify(s);
        serviceSelect.appendChild(opt);
      });
      updatePreview();
    } catch (e) {
      serviceSelect.innerHTML = '<option value="">Unable to load services</option>';
      console.error(e);
      toast('Unable to load services right now.', 'warning');
    }
  }

  function updatePreview() {
    const selected = serviceSelect.value;
    const qty = parseInt(quantityInput.value) || 0;
    const svc = services.find((s) => String(s.id) === String(selected));
    if (!svc) {
      servicePreview.style.display = 'none';
      return;
    }
    servicePreview.style.display = 'block';
    const rate = Number(svc.rate || 0);
    const cost = Math.ceil(qty * rate);
    servicePrice.textContent = `${cost.toLocaleString()} TZS`;
    serviceHint.textContent = `${svc.name} • Min ${svc.min || 1} • Max ${svc.max || 0} • ${Number(svc.price_per_1000 || 0).toLocaleString()} TZS / 1K`;
  }

  serviceSelect.addEventListener('change', updatePreview);
  quantityInput.addEventListener('input', updatePreview);
  usePremium.addEventListener('change', loadServices);

  placeOrderBtn.addEventListener('click', async () => {
    const serviceId = serviceSelect.value;
    const quantity = parseInt(quantityInput.value) || 0;
    const link = linkInput.value.trim();
    const provider = usePremium.checked ? 'premium' : 'boost';

    if (!serviceId || !quantity || !link) {
      toast('Please choose a service, quantity, and link.', 'warning');
      return;
    }

    placeOrderBtn.disabled = true;
    placeOrderBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';

    try {
      const r = await fetch('place-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ service_id: parseInt(serviceId), quantity, link, provider })
      });
      const j = await r.json();
      if (j.success) {
        toast(`Order placed successfully. Order #${j.order_id}`, 'success');
        setTimeout(() => location.reload(), 1200);
      } else {
        toast(j.message || 'Order could not be placed.', 'danger');
      }
    } catch (e) {
      toast('Network error. Please try again.', 'danger');
    } finally {
      placeOrderBtn.disabled = false;
      placeOrderBtn.innerHTML = '<i class="bi bi-send-fill"></i> Place Order';
    }
  });

  loadServices();
})();
</script>

<?php ui_foot(); ?>
