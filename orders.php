<?php
/**
 * Orders — a dedicated page listing all of the user's orders, grouped by
 * status (tabs), with a refill button on every eligible order.
 */

require_once 'config.php';
require_once 'includes/ui.php';
require_once 'includes/order-sync.php';
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
    "SELECT id, service_name, platform, quantity, price, status, external_order_id,
            link, created_at, refill_available, refill_requested, refill_status
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

$counts = ['all' => 0, 'active' => 0, 'completed' => 0, 'canceled' => 0];
foreach ($orders as $o) {
    $counts['all']++;
    $counts[orderGroup($o['status'])]++;
}

$tabs = [
    'all'       => ['Zote',          'bi-collection'],
    'active'    => ['Zinaendelea',   'bi-hourglass-split'],
    'completed' => ['Zimekamilika',  'bi-check2-circle'],
    'canceled'  => ['Zilizoghairiwa','bi-x-circle'],
];

ui_head('Orders Zangu — ' . APP_NAME, 'app');
?>
<style>
.orders-hero{background:linear-gradient(135deg,var(--primary),var(--primary-2));border-radius:0 0 30px 30px;padding:1.7rem 1.3rem 2.4rem;color:#fff;box-shadow:0 12px 30px rgba(72,52,212,.3);}
.otabs{display:flex;gap:.5rem;overflow-x:auto;padding:.2rem 0 .5rem;scrollbar-width:none;}
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

    <div class="otabs mb-2">
        <?php foreach ($tabs as $key => [$label, $icon]): ?>
            <button class="otab<?= $key === 'all' ? ' active' : '' ?>" data-g="<?= $key ?>">
                <i class="bi <?= $icon ?>"></i> <?= $label ?>
                <span class="cnt"><?= (int)$counts[$key] ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
        <div class="card-soft text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
            Bado hujaweka order yoyote.<br>
            <a href="index.php" class="btn-grad mt-3 d-inline-flex" style="width:auto;padding:.6rem 1.4rem;"><i class="bi bi-plus-circle"></i> Weka Order</a>
        </div>
    <?php else: ?>
        <div id="ordersList">
            <?php foreach ($orders as $o): ?>
                <?php
                    $group       = orderGroup($o['status']);
                    $isCompleted = strpos(strtolower($o['status']), 'complet') !== false
                                || strpos(strtolower($o['status']), 'partial') !== false;
                    $canRefill   = !empty($o['refill_available']) && empty($o['refill_requested']) && $isCompleted;
                ?>
                <div class="ocard" data-group="<?= $group ?>">
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
        <div id="emptyFiltered" class="text-center text-muted py-5" style="display:none;">
            <i class="bi bi-funnel fs-2 d-block mb-2 opacity-50"></i>
            Hakuna orders katika kundi hili.
        </div>
    <?php endif; ?>

</div>

<?php
ui_nav('orders', ['balance' => $user['balance']]);
ui_topup_modal();
ui_foot(<<<'JS'
<script>
// Status tab filtering
(function(){
  const tabs  = document.querySelectorAll('.otab');
  const cards = document.querySelectorAll('#ordersList [data-group]');
  const empty = document.getElementById('emptyFiltered');
  tabs.forEach(t => t.addEventListener('click', () => {
    tabs.forEach(x => x.classList.remove('active'));
    t.classList.add('active');
    const g = t.dataset.g;
    let shown = 0;
    cards.forEach(c => {
      const ok = (g === 'all' || c.dataset.group === g);
      c.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });
    if (empty) empty.style.display = shown ? 'none' : '';
  }));
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
