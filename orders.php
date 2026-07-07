<?php
/**
 * Orders — Enhanced page listing all orders grouped by provider (Normal/Pro)
 * with status tabs, search functionality, and refill options.
 */

require_once 'config.php';
require_once 'includes/ui.php';
require_once 'includes/order-sync.php';
require_once 'includes/progress-bar.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Pull the latest status from the provider before rendering.
syncUserOrders($conn, $user_id, isset($_GET['sync']));

// User (for the nav balance pill).
$stmt = $conn->prepare("SELECT username, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// All orders, newest first.
$stmt = $conn->prepare(
    "SELECT id, service_name, platform, quantity, price, status, progress, external_order_id,
            link, created_at, refill_available, refill_requested, refill_status, gateway
       FROM orders WHERE user_id = ? ORDER BY id DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/** Bucket a provider status string into one of our tab groups. */
function orderGroup($status) {
    $s = strtolower((string)$status);
    if (strpos($s, 'complet') !== false || strpos($s, 'partial') !== false) return 'completed';
    if (strpos($s, 'cancel')  !== false || strpos($s, 'fail') !== false
        || strpos($s, 'refund') !== false || strpos($s, 'error') !== false)  return 'canceled';
    return 'active'; // pending / processing / in progress / unknown
}

/** Soft badge class for a status. */
function obadge($status) {
    $s = strtolower((string)$status);
    if (strpos($s, 'complet') !== false || strpos($s, 'partial') !== false) return 'badge-success';
    if (strpos($s, 'cancel')  !== false || strpos($s, 'fail') !== false
        || strpos($s, 'refund') !== false || strpos($s, 'error') !== false)  return 'badge-danger';
    return 'badge-warning';
}

// Separate orders by gateway (provider)
$ordersByGateway = ['primary' => [], 'partner' => []];
$counts = ['all' => 0, 'primary' => ['all' => 0, 'active' => 0, 'completed' => 0, 'canceled' => 0],
           'partner' => ['all' => 0, 'active' => 0, 'completed' => 0, 'canceled' => 0]];

foreach ($orders as $o) {
    $gateway = $o['gateway'] ?? 'primary';
    if (!isset($ordersByGateway[$gateway])) $ordersByGateway[$gateway] = [];
    $ordersByGateway[$gateway][] = $o;
    $counts['all']++;
    $counts[$gateway]['all']++;
    $group = orderGroup($o['status']);
    $counts[$gateway][$group]++;
}

$tabs = [
    'all'       => ['Zote',          'bi-collection'],
    'active'    => ['Zinaendelea',   'bi-hourglass-split'],
    'completed' => ['Zimekamilika',  'bi-check2-circle'],
    'canceled'  => ['Zilizoghairiwa','bi-x-circle'],
];

$gateways = [
    'primary' => ['Huduma Kawaida',  'bi-lightning-charge-fill', 'badge-info'],
    'partner' => ['Huduma Pro',      'bi-rocket-fill',          'badge-primary'],
];

ui_head('Orders Zangu — ' . APP_NAME, 'app');
?>
<style>
.orders-hero{background:linear-gradient(135deg,var(--primary),var(--primary-2));border-radius:0 0 30px 30px;padding:1.7rem 1.3rem 2.4rem;color:#fff;box-shadow:0 12px 30px rgba(72,52,212,.3);}
.search-box{display:flex;gap:.6rem;margin-bottom:1.2rem;background:#fff;border-radius:20px;padding:.6rem .8rem;box-shadow:0 6px 16px rgba(43,54,116,.06);}
.search-box input{border:none;outline:none;flex:1;font-size:.85rem;background:none;}
.search-box input::placeholder{color:var(--muted);}
.search-box .btn-clear{background:none;border:none;color:var(--muted);cursor:pointer;font-size:.9rem;padding:0;}
.gwrapper{margin-bottom:2.5rem;}
.ghead{display:flex;align-items:center;gap:.8rem;padding:1rem 0 .8rem;border-bottom:2px solid #f0f2f8;}
.ghead i{font-size:1.3rem;}
.ghead h5{margin:0;font-weight:700;font-size:1.05rem;}
.gcount{font-size:.75rem;color:var(--muted);margin-left:auto;background:#f5f7fc;padding:.3rem .8rem;border-radius:20px;}
.otabs{display:flex;gap:.5rem;overflow-x:auto;padding:.2rem 0 .5rem;scrollbar-width:none;margin-bottom:.8rem;}
.otabs::-webkit-scrollbar{display:none;}
.otab{flex:0 0 auto;border:none;background:#fff;color:var(--muted);border-radius:30px;padding:.5rem .95rem;font-size:.78rem;font-weight:600;box-shadow:0 6px 16px rgba(43,54,116,.06);display:inline-flex;align-items:center;gap:.4rem;transition:.18s;cursor:pointer;}
.otab .cnt{background:#eef1f8;color:#5a6a85;border-radius:20px;font-size:.66rem;font-weight:700;padding:.05rem .45rem;min-width:20px;text-align:center;}
.otab.active{background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;box-shadow:0 10px 22px rgba(72,52,212,.32);}
.otab.active .cnt{background:rgba(255,255,255,.25);color:#fff;}
.ocard{background:#fff;border-radius:20px;padding:.95rem 1rem;box-shadow:0 10px 26px rgba(43,54,116,.06);margin-bottom:.7rem;display:flex;gap:.85rem;align-items:flex-start;}
.ocard .oico{width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,#eef0ff,#e7fbf8);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto;}
.ocard .oname{font-weight:600;font-size:.86rem;line-height:1.25;}
.ocard .ometa{color:var(--muted);font-size:.7rem;margin-top:.15rem;}
.ocard .olink{color:var(--muted);font-size:.68rem;margin-top:.1rem;word-break:break-all;}
.refill-btn{background:#e4faf3;color:#00876a;border:none;border-radius:20px;font-size:.68rem;font-weight:600;padding:.25rem .75rem;}

/* === Order Progress Bar === */
.order-progress{padding:.8rem 0;margin:.8rem 0;}
.progress-track{position:relative;height:6px;background:#e8eaf6;border-radius:8px;overflow:hidden;box-shadow:inset 0 1px 2px rgba(0,0,0,.05);}
.progress-fill{height:100%;border-radius:8px;transition:width .4s cubic-bezier(.4,0,.2,1);background:linear-gradient(90deg,#667eea,#764ba2);}
.progress-info{display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;font-size:.75rem;}
.progress-label{font-weight:600;color:#667eea;}
.progress-percent{font-weight:700;color:#667eea;}
.progress-stages{display:flex;justify-content:space-around;gap:.5rem;margin-top:.4rem;font-size:.65rem;}
.pstage{display:flex;align-items:center;gap:.3rem;font-weight:600;color:#9ca3af;}

/* === Place Order Form Section === */
.place-order-section{background:linear-gradient(135deg,#f8f9ff,#fff);border-radius:20px;padding:1.2rem;box-shadow:0 8px 20px rgba(72,52,212,.08);border:1px solid rgba(72,52,212,.1);}
.place-order-card{background:#fff;border-radius:18px;padding:1.5rem;box-shadow:0 4px 12px rgba(72,52,212,.06);}
.place-order-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;}
.place-order-title{display:flex;align-items:center;gap:.8rem;}
.place-order-title h5{margin:0;font-size:1.05rem;font-weight:700;}
.place-order-title p{margin:0;font-size:.75rem;color:#6b7280;}
.balance-pill{background:linear-gradient(135deg,#fef3c7,#fde68a);color:#78350f;font-size:.8rem;font-weight:600;padding:.4rem .9rem;border-radius:30px;white-space:nowrap;}
.place-order-form{border-top:1px solid #e5e7eb;padding-top:1.2rem;}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;}
.form-group{display:flex;flex-direction:column;}
.form-group label{font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.5px;}
.form-control{background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:.65rem .8rem;font-size:.85rem;font-family:inherit;transition:.2s;}
.form-control:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.1);}
.form-control::placeholder{color:#9ca3af;}
.form-text{display:block;font-size:.7rem;color:#6b7280;margin-top:.3rem;}
.btn-place-order{width:100%;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:10px;padding:.75rem 1.2rem;font-size:.9rem;font-weight:600;cursor:pointer;transition:.3s;display:flex;align-items:center;justify-content:center;gap:.5rem;margin-top:.5rem;}
.btn-place-order:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(102,126,234,.3);}
.btn-place-order:active{transform:translateY(0);}

@media (max-width:768px) {
  .place-order-header{flex-direction:column;align-items:flex-start;}
  .balance-pill{align-self:flex-start;}
  .form-row{grid-template-columns:1fr;}
}
</style>

<div class="orders-hero">
    <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-bag-check-fill" style="font-size:1.6rem;"></i>
            <div>
                <h4 class="fw-bold mb-0">Orders Zangu</h4>
                <p class="mb-0 small opacity-75"><?= (int)$counts['all'] ?> orders kwa jumla</p>
            </div>
        </div>
        <a href="orders.php?sync=1" class="btn btn-sm" style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:30px;font-size:.74rem;font-weight:600;padding:.4rem .85rem;"><i class="bi bi-arrow-clockwise"></i> Sasisha</a>
    </div>
</div>

<div class="container px-3" style="margin-top:-1.5rem;">

    <!-- Place Order Section (Pro) -->
    <div class="place-order-section" style="margin-bottom:2.5rem;">
        <div class="place-order-card">
            <div class="place-order-header">
                <div class="place-order-title">
                    <i class="bi bi-rocket-fill" style="font-size:1.4rem;color:#7c3aed;margin-right:0.5rem;"></i>
                    <div>
                        <h5 class="mb-0 fw-bold">Weka Order Mpya</h5>
                        <p class="mb-0 small text-muted">Jaza huduma, idadi na link kwenye pointi hapa chini</p>
                    </div>
                </div>
                <span class="balance-pill">💰 Salio: <strong><?= number_format($user['balance'], 0) ?> TZS</strong></span>
            </div>

            <div class="place-order-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Huduma *</label>
                        <select id="serviceSelect" class="form-control" required>
                            <option value="">-- Chagua huduma --</option>
                        </select>
                        <small class="form-text">Min/Max: <span id="minMax">-</span></small>
                    </div>

                    <div class="form-group">
                        <label>Idadi *</label>
                        <input type="number" id="quantityInput" class="form-control" placeholder="Idadi" required min="1">
                        <small class="form-text">Bei: <strong><span id="costDisplay">0</span> TZS</strong></small>
                    </div>

                    <div class="form-group">
                        <label>Link / URL *</label>
                        <input type="text" id="linkInput" class="form-control" placeholder="https://..." required>
                    </div>

                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0;">
                            <input type="checkbox" id="useProProvider" style="width:18px;height:18px;cursor:pointer;">
                            Tumia Huduma Pro (FastWay)
                        </label>
                        <small class="form-text">Huduma mbadala inaweza kuwa na bei tofauti</small>
                    </div>

                    <button class="btn-place-order" id="placeOrderBtn">
                        <i class="bi bi-send-fill"></i> Weka Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Box -->
    <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="searchInput" placeholder="Tafuta order kwa ID, huduma, au link..." />
        <button class="btn-clear" onclick="document.getElementById('searchInput').value = ''; filterAll();" style="display:none;" id="clearBtn"><i class="bi bi-x-circle"></i></button>
    </div>

    <!-- Primary Provider Section -->
    <div class="gwrapper" data-gw="primary">
        <div class="ghead">
            <i class="bi <?= $gateways['primary'][1] ?>" style="color:var(--primary);"></i>
            <h5><?= $gateways['primary'][0] ?></h5>
            <span class="gcount"><?= (int)$counts['primary']['all'] ?> orders</span>
        </div>

        <?php if ($counts['primary']['all'] === 0): ?>
            <div class="card-soft text-center text-muted py-4">
                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                Hakuna orders katika huduma hii.
            </div>
        <?php else: ?>
            <div class="otabs mb-2" data-gw="primary">
                <?php foreach ($tabs as $key => [$label, $icon]): ?>
                    <button class="otab<?= $key === 'all' ? ' active' : '' ?>" data-g="<?= $key ?>" data-gw="primary">
                        <i class="bi <?= $icon ?>"></i> <?= $label ?>
                        <span class="cnt"><?= (int)$counts['primary'][$key] ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="ordersList-primary" class="olist">
                <?php foreach ($ordersByGateway['primary'] as $o): ?>
                    <?php
                        $group       = orderGroup($o['status']);
                        $isCompleted = strpos(strtolower($o['status']), 'complet') !== false
                                    || strpos(strtolower($o['status']), 'partial') !== false;
                        $canRefill   = !empty($o['refill_available']) && empty($o['refill_requested']) && $isCompleted;
                    ?>
                    <div class="ocard" data-group="<?= $group ?>" data-search="<?= strtolower(htmlspecialchars($o['id'] . ' ' . $o['service_name'] . ' ' . ($o['link'] ?? ''))) ?>">
                        <div class="oico"><i class="bi bi-bag-check"></i></div>
                        <div class="flex-grow-1">
                            <div class="oname"><?= htmlspecialchars(mb_substr($o['service_name'], 0, 60)) ?></div>
                            <div class="ometa">
                                #<?= $o['id'] ?> · <?= number_format($o['quantity']) ?> · <?= number_format($o['price']) ?> TZS
                                · <?= date('d M Y', strtotime($o['created_at'])) ?>
                                <?= !empty($o['refill_available']) ? ' · <i class="bi bi-arrow-repeat"></i> refill' : '' ?>
                            </div>
                            <?php if (!empty($o['link'])): ?>
                                <div class="olink"><i class="bi bi-link-45deg"></i> <?= htmlspecialchars(mb_substr($o['link'], 0, 50)) ?></div>
                            <?php endif; ?>
                            
                            <!-- Order Progress Bar -->
                            <?= renderProgressBar($o['status'], $o['progress'] ?? null) ?>
                            
                            <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge-soft <?= obadge($o['status']) ?>"><?= htmlspecialchars($o['status']) ?></span>
                                <?php if ($canRefill): ?>
                                    <button class="btn btn-sm refill-btn" data-id="<?= (int)$o['id'] ?>"><i class="bi bi-arrow-repeat"></i> Omba Refill</button>
                                <?php elseif (!empty($o['refill_requested'])): ?>
                                    <span class="badge-soft badge-warning" style="font-size:.66rem;"><i class="bi bi-hourglass-split"></i> Refill: <?= htmlspecialchars($o['refill_status'] ?: 'requested') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="emptyFiltered-primary" class="text-center text-muted py-5" style="display:none;">
                <i class="bi bi-funnel fs-2 d-block mb-2 opacity-50"></i>
                Hakuna orders katika kundi hili.
            </div>
        <?php endif; ?>
    </div>

    <!-- Partner Provider Section -->
    <div class="gwrapper" data-gw="partner">
        <div class="ghead">
            <i class="bi <?= $gateways['partner'][1] ?>" style="color:#7c3aed;"></i>
            <h5><?= $gateways['partner'][0] ?></h5>
            <span class="gcount"><?= (int)$counts['partner']['all'] ?> orders</span>
        </div>

        <?php if ($counts['partner']['all'] === 0): ?>
            <div class="card-soft text-center text-muted py-4">
                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                Hakuna orders katika huduma hii.
            </div>
        <?php else: ?>
            <div class="otabs mb-2" data-gw="partner">
                <?php foreach ($tabs as $key => [$label, $icon]): ?>
                    <button class="otab<?= $key === 'all' ? ' active' : '' ?>" data-g="<?= $key ?>" data-gw="partner">
                        <i class="bi <?= $icon ?>"></i> <?= $label ?>
                        <span class="cnt"><?= (int)$counts['partner'][$key] ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="ordersList-partner" class="olist">
                <?php foreach ($ordersByGateway['partner'] as $o): ?>
                    <?php
                        $group       = orderGroup($o['status']);
                        $isCompleted = strpos(strtolower($o['status']), 'complet') !== false
                                    || strpos(strtolower($o['status']), 'partial') !== false;
                        $canRefill   = !empty($o['refill_available']) && empty($o['refill_requested']) && $isCompleted;
                    ?>
                    <div class="ocard" data-group="<?= $group ?>" data-search="<?= strtolower(htmlspecialchars($o['id'] . ' ' . $o['service_name'] . ' ' . ($o['link'] ?? ''))) ?>">
                        <div class="oico"><i class="bi bi-bag-check"></i></div>
                        <div class="flex-grow-1">
                            <div class="oname"><?= htmlspecialchars(mb_substr($o['service_name'], 0, 60)) ?></div>
                            <div class="ometa">
                                #<?= $o['id'] ?> · <?= number_format($o['quantity']) ?> · <?= number_format($o['price']) ?> TZS
                                · <?= date('d M Y', strtotime($o['created_at'])) ?>
                                <?= !empty($o['refill_available']) ? ' · <i class="bi bi-arrow-repeat"></i> refill' : '' ?>
                            </div>
                            <?php if (!empty($o['link'])): ?>
                                <div class="olink"><i class="bi bi-link-45deg"></i> <?= htmlspecialchars(mb_substr($o['link'], 0, 50)) ?></div>
                            <?php endif; ?>
                            
                            <!-- Order Progress Bar -->
                            <?= renderProgressBar($o['status'], $o['progress'] ?? null) ?>
                            
                            <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge-soft <?= obadge($o['status']) ?>"><?= htmlspecialchars($o['status']) ?></span>
                                <?php if ($canRefill): ?>
                                    <button class="btn btn-sm refill-btn" data-id="<?= (int)$o['id'] ?>"><i class="bi bi-arrow-repeat"></i> Omba Refill</button>
                                <?php elseif (!empty($o['refill_requested'])): ?>
                                    <span class="badge-soft badge-warning" style="font-size:.66rem;"><i class="bi bi-hourglass-split"></i> Refill: <?= htmlspecialchars($o['refill_status'] ?: 'requested') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="emptyFiltered-partner" class="text-center text-muted py-5" style="display:none;">
                <i class="bi bi-funnel fs-2 d-block mb-2 opacity-50"></i>
                Hakuna orders katika kundi hili.
            </div>
        <?php endif; ?>
    </div>

</div>

<?php
ui_nav('orders', ['balance' => $user['balance']]);
ui_topup_modal();
ui_foot(<<<'JS'
<script>
// Tab and search filtering
(function(){
  const searchInput = document.getElementById('searchInput');
  const clearBtn = document.getElementById('clearBtn');
  const providers = ['primary', 'partner'];

  function filterAll() {
    const query = searchInput.value.toLowerCase().trim();
    clearBtn.style.display = query ? 'block' : 'none';

    providers.forEach(provider => {
      const cards = document.querySelectorAll(`#ordersList-${provider} [data-search]`);
      const empty = document.getElementById(`emptyFiltered-${provider}`);
      let shown = 0;

      cards.forEach(c => {
        const matches = c.dataset.search.includes(query);
        c.style.display = matches ? '' : 'none';
        if (matches) shown++;
      });

      if (empty) empty.style.display = (shown || !query) ? 'none' : '';
    });
  }

  // Search input listener
  searchInput.addEventListener('input', filterAll);

  // Tab filtering
  const tabs = document.querySelectorAll('.otab');
  tabs.forEach(t => t.addEventListener('click', () => {
    const provider = t.dataset.gw;
    const tabsInProvider = document.querySelectorAll(`.otabs[data-gw="${provider}"] .otab`);
    
    tabsInProvider.forEach(x => x.classList.remove('active'));
    t.classList.add('active');

    const group = t.dataset.g;
    const cards = document.querySelectorAll(`#ordersList-${provider} [data-group]`);
    const empty = document.getElementById(`emptyFiltered-${provider}`);
    let shown = 0;

    cards.forEach(c => {
      const ok = (group === 'all' || c.dataset.group === group);
      const searchOk = searchInput.value.toLowerCase().trim() === '' || c.dataset.search.includes(searchInput.value.toLowerCase());
      c.style.display = (ok && searchOk) ? '' : 'none';
      if (ok && searchOk) shown++;
    });

    if (empty) empty.style.display = shown ? 'none' : '';
  }));
})();

// === Order Placement Form ===
(async () => {
  const serviceSelect = document.getElementById('serviceSelect');
  const quantityInput = document.getElementById('quantityInput');
  const linkInput = document.getElementById('linkInput');
  const useProProvider = document.getElementById('useProProvider');
  const placeOrderBtn = document.getElementById('placeOrderBtn');
  const costDisplay = document.getElementById('costDisplay');
  const minMaxDisplay = document.getElementById('minMax');

  let services = [];

  // Load services on page load
  async function loadServices() {
    try {
      const r = await fetch('api-services.php');
      const j = await r.json();
      if (j.success) {
        services = j.data || [];
        serviceSelect.innerHTML = '<option value="">-- Chagua huduma --</option>';
        services.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.id;
          opt.textContent = `${s.name} (${s.category})`;
          serviceSelect.appendChild(opt);
        });
      }
    } catch (e) {
      console.error('Failed to load services:', e);
    }
  }

  // Calculate cost when service or quantity changes
  function updateCost() {
    const serviceId = parseInt(serviceSelect.value);
    const quantity = parseInt(quantityInput.value) || 0;

    if (!serviceId || !quantity) {
      costDisplay.textContent = '0';
      minMaxDisplay.textContent = '-';
      return;
    }

    const service = services.find(s => s.id == serviceId);
    if (service) {
      const min = Math.max(1, parseInt(service.min) || 1);
      const max = parseInt(service.max) || 0;
      const rate = parseFloat(service.rate) || 0;
      const cost = Math.ceil(quantity * rate);

      minMaxDisplay.textContent = max > 0 ? `${min} - ${max}` : `${min}+`;
      costDisplay.textContent = cost.toLocaleString();
    }
  }

  serviceSelect.addEventListener('change', updateCost);
  quantityInput.addEventListener('input', updateCost);

  // Place order
  placeOrderBtn.addEventListener('click', async () => {
    const serviceId = parseInt(serviceSelect.value);
    const quantity = parseInt(quantityInput.value) || 0;
    const link = linkInput.value.trim();
    const useAlt = useProProvider.checked;

    if (!serviceId || quantity <= 0 || !link) {
      toast('Tafadhali jaza huduma, idadi na link.', 'warning');
      return;
    }

    placeOrderBtn.disabled = true;
    placeOrderBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Inatuma...';

    try {
      const r = await fetch('place-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ service_id: serviceId, quantity, link, use_fallback: useAlt })
      });
      const j = await r.json();

      if (j.success) {
        toast('Order imeweka kwa mafanikio! Akasha inatuma...', 'success');
        setTimeout(() => location.reload(), 1500);
      } else if (j.provider_unavailable && j.can_retry_alt) {
        toast('Huduma ya kawaida haipatikani. Jaribu Huduma Pro?', 'warning');
        useProProvider.checked = true;
      } else {
        toast(j.message || 'Kosa limefanyika.', 'danger');
      }
    } catch (e) {
      toast('Kosa la mtandao.', 'danger');
      console.error(e);
    } finally {
      placeOrderBtn.disabled = false;
      placeOrderBtn.innerHTML = '<i class="bi bi-send-fill"></i> Weka Order';
    }
  });

  // Load services on page load
  await loadServices();
})();

// Refill requests
document.querySelectorAll('.refill-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    if(!confirm('Omba refill kwa order hii?')) return;
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Inatuma...';
    try{
      const r = await fetch('refill.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({order_id:parseInt(btn.dataset.id)})});
      const j = await r.json();
      toast(j.message, j.success?'success':'danger');
      if(j.success) setTimeout(()=>location.reload(),1200);
      else { btn.disabled=false; btn.innerHTML='<i class="bi bi-arrow-repeat"></i> Omba Refill'; }
    }catch(e){ toast('Kosa la mtandao.','danger'); btn.disabled=false; btn.innerHTML='<i class="bi bi-arrow-repeat"></i> Omba Refill'; }
  });
});
</script>
JS);
?>
