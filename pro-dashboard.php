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
.hero{border-radius:26px;padding:1.5rem 1.4rem;color:#fff;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--primary),var(--primary-2));box-shadow:0 18px 40px rgba(72,52,212,.30);margin-bottom:1rem;}
.hero::after{content:'';position:absolute;right:-40px;top:-40px;width:160px;height:160px;background:rgba(255,255,255,.12);border-radius:50%;}
.card-soft{background:var(--card);border-radius:26px;padding:1.4rem 1.25rem;box-shadow:0 18px 40px rgba(43,54,116,.06);border:1px solid rgba(43,54,116,.04);margin-bottom:1rem;}
.section-title{font-weight:700;font-size:1.05rem;display:flex;align-items:center;gap:.55rem;}
.section-ico{width:40px;height:40px;border-radius:13px;background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.15rem;}
.stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem;margin-top:.8rem;}
.stat-box{background:#f8faff;border:1px solid #edf0f7;border-radius:16px;padding:.75rem;text-align:center;}
.stat-box .k{font-size:.62rem;text-transform:uppercase;letter-spacing:.6px;color:#8a93b2;font-weight:700;}
.stat-box .v{font-size:1rem;font-weight:800;color:#2b3674;margin-top:.2rem;}
.form-label{font-weight:600;font-size:.74rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:.4rem;}
.form-control,.form-select{border-radius:15px;border:1.5px solid #e9edf7;padding:.8rem 1rem;background:#fafbff;font-size:.95rem;}
.form-control:focus,.form-select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 4px rgba(108,92,231,.12);background:#fff;}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:.75rem .85rem;border:1px solid #eceff7;border-radius:14px;background:#f9fbff;gap:.6rem;flex-wrap:wrap;}
.toggle-row input{width:18px;height:18px;accent-color:var(--primary);}
.price-box{background:linear-gradient(135deg,#f3f1ff,#ecfbf8);border:1px dashed rgba(108,92,231,.35);border-radius:20px;padding:1.15rem;text-align:center;margin-top:.4rem;}
.price-box .lbl{font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
.price-box .val{font-size:1.6rem;font-weight:800;color:var(--primary-2);margin:.2rem 0 .1rem;}
.price-box .hint{font-size:.78rem;color:#5a6a85;}
.btn-grad{background:linear-gradient(135deg,var(--primary),var(--primary-2));border:none;border-radius:15px;padding:.85rem;font-weight:700;color:#fff;width:100%;box-shadow:0 12px 26px rgba(72,52,212,.30);transition:.2s;display:flex;align-items:center;justify-content:center;gap:.5rem;}
.btn-grad:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 16px 32px rgba(72,52,212,.42);color:#fff;}
.btn-grad:disabled{opacity:.55;}
.code-block{background:#111827;color:#f9fafb;border-radius:14px;padding:.9rem;font-size:.8rem;overflow:auto;white-space:pre;}
.doc-list{margin:0;padding-left:1rem;color:#4b5563;font-size:.85rem;}
.doc-list li{margin-bottom:.4rem;}
.small-muted{font-size:.78rem;color:#8a93b2;}
@media (max-width:768px){.stat-grid{grid-template-columns:1fr;}}
</style>

<div class="container px-3" style="margin-top:-1.2rem;">
    <div class="hero">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-rocket-fill" style="font-size:1.4rem;"></i>
            <div>
                <h4 class="fw-bold mb-0">Pro Reseller Dashboard</h4>
                <p class="mb-0 small opacity-75">Browse premium services, view real prices, and place orders from a polished reseller workspace.</p>
            </div>
        </div>
    </div>

    <div class="card-soft">
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

    <div class="card-soft">
        <div class="section-title"><span class="section-ico"><i class="bi bi-box-seam"></i></span> Service Explorer</div>
        <p class="small-muted mb-3">Choose a service, see its price clearly, and place an order in seconds.</p>

        <div class="toggle-row">
            <label for="usePremium" style="display:flex;align-items:center;gap:.55rem;font-weight:700;color:#374151;cursor:pointer;margin:0;">
                <input type="checkbox" id="usePremium">
                Use Premium Network
            </label>
            <span class="small-muted">This routes through our premium service pool in the background.</span>
        </div>

        <div class="mt-3">
            <label class="form-label" for="serviceSelect">Service</label>
            <select id="serviceSelect" class="form-select" required>
                <option value="">Loading services...</option>
            </select>
        </div>

        <div class="mt-3">
            <label class="form-label" for="quantityInput">Quantity</label>
            <input type="number" id="quantityInput" class="form-control" min="1" value="100" placeholder="Enter quantity">
        </div>

        <div class="mt-3">
            <label class="form-label" for="linkInput">Link / Username</label>
            <input type="text" id="linkInput" class="form-control" placeholder="https://...">
        </div>

        <div id="servicePreview" class="price-box" style="display:none;">
            <div class="lbl">Selected Service</div>
            <div class="val" id="servicePrice">0 TZS</div>
            <div class="hint" id="serviceHint">Choose a service to view pricing and limits.</div>
        </div>

        <button class="btn-grad mt-3" id="placeOrderBtn"><i class="bi bi-send-fill"></i> Place Order</button>
    </div>

    <div class="card-soft">
        <div class="section-title"><span class="section-ico"><i class="bi bi-key-fill"></i></span> API Access</div>
        <p class="small-muted mb-3">Open the API center to get your key and documentation.</p>
        <a href="api-center.php" class="btn-grad" style="width:auto;padding:.7rem 1rem;border-radius:999px;display:inline-flex;">Open API Center</a>
    </div>

    <div class="card-soft">
        <div class="section-title"><span class="section-ico"><i class="bi bi-code-square"></i></span> Quick API Example</div>
        <div class="code-block">GET /api-services.php?provider=premium

POST /place-order.php
{
  "service_id": 123,
  "quantity": 100,
  "link": "https://instagram.com/username",
  "provider": "premium"
}</div>
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
