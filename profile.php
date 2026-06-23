<?php
require_once 'config.php';
require_once 'includes/ui.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$flash = '';
$flash_type = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !password_verify($old, $row['password'])) {
        $flash = 'Neno siri la zamani si sahihi.'; $flash_type = 'danger';
    } elseif (strlen($new) < PASSWORD_MIN_LENGTH) {
        $flash = 'Neno siri jipya liwe na herufi angalau ' . PASSWORD_MIN_LENGTH . '.'; $flash_type = 'danger';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute();
        logActivity($user_id, 'password_changed', 'User changed password');
        $flash = 'Neno siri limebadilishwa kikamilifu.'; $flash_type = 'success';
    }
}

// User info
$stmt = $conn->prepare("SELECT username, email, phone, balance, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Refresh order statuses from the live provider before displaying them.
require_once 'includes/order-sync.php';
syncUserOrders($conn, $user_id, isset($_GET['sync']));

// Orders
$stmt = $conn->prepare("SELECT id, service_name, platform, quantity, price, status, external_order_id, created_at, refill_available, refill_requested, refill_status FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$spent = 0; $done = 0;
foreach ($orders as $o) {
    $spent += (float)$o['price'];
    if (strpos(strtolower($o['status']), 'complet') !== false) $done++;
}

function pbadge($status) {
    $s = strtolower($status);
    if (strpos($s,'complet')!==false) return 'badge-success';
    if (strpos($s,'pend')!==false || strpos($s,'process')!==false || strpos($s,'progress')!==false) return 'badge-warning';
    if (strpos($s,'cancel')!==false || strpos($s,'fail')!==false) return 'badge-danger';
    return 'badge-secondary';
}

ui_head('Profile — ' . APP_NAME, 'app');
?>
<style>
.profile-hero{background:linear-gradient(135deg,var(--primary),var(--primary-2));border-radius:0 0 30px 30px;padding:1.8rem 1.3rem 2.2rem;color:#fff;box-shadow:0 12px 30px rgba(72,52,212,.3);}
.avatar{width:74px;height:74px;background:rgba(255,255,255,.2);border-radius:22px;display:flex;align-items:center;justify-content:center;font-size:2.2rem;border:2px solid rgba(255,255,255,.3);}
.btn-logout{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:30px;color:#fff;padding:.45rem 1rem;font-size:.8rem;}
.stat-mini{background:#fff;border-radius:18px;padding:1rem;box-shadow:0 8px 22px rgba(43,54,116,.05);display:flex;align-items:center;gap:.7rem;}
.stat-ico{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;}
.order-item{display:flex;align-items:center;gap:.8rem;padding:.7rem 0;border-bottom:1px solid #f0f2f8;}
.order-item:last-child{border-bottom:none;}
.order-ico{width:40px;height:40px;border-radius:12px;background:#f0f2fb;color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex:0 0 auto;}
</style>

<div class="profile-hero">
    <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="avatar"><i class="bi bi-person-circle"></i></div>
            <div>
                <h4 class="fw-bold mb-0"><?= htmlspecialchars($user['username']) ?></h4>
                <p class="mb-0 small opacity-75"><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="container px-3" style="margin-top:-1rem;">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash_type ?> rounded-4 border-0 shadow-sm mb-3"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-2 mb-3">
        <div class="col-6"><div class="stat-mini"><div class="stat-ico" style="background:#eef0ff;color:var(--primary)"><i class="bi bi-wallet2"></i></div><div><div class="text-muted" style="font-size:.66rem;text-transform:uppercase;">Salio</div><div class="fw-bold"><?= number_format($user['balance']) ?> TZS</div></div></div></div>
        <div class="col-3"><div class="stat-mini"><div><div class="text-muted" style="font-size:.66rem;text-transform:uppercase;">Orders</div><div class="fw-bold"><?= count($orders) ?></div></div></div></div>
        <div class="col-3"><div class="stat-mini"><div><div class="text-muted" style="font-size:.66rem;text-transform:uppercase;">Done</div><div class="fw-bold text-success"><?= $done ?></div></div></div></div>
    </div>

    <!-- Account info -->
    <div class="card-soft mb-3">
        <div class="section-title mb-3"><div class="section-ico"><i class="bi bi-person-vcard"></i></div> Taarifa za Akaunti</div>
        <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted small">Namba ya Simu</span><span class="fw-semibold"><?= htmlspecialchars($user['phone'] ?: '—') ?></span></div>
        <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted small">Jumla Imetumika</span><span class="fw-semibold"><?= number_format($spent) ?> TZS</span></div>
        <div class="d-flex justify-content-between py-2"><span class="text-muted small">Mwanachama Tangu</span><span class="fw-semibold"><?= date('M Y', strtotime($user['created_at'])) ?></span></div>
    </div>

    <!-- Change password -->
    <div class="card-soft mb-3">
        <div class="section-title mb-3"><div class="section-ico"><i class="bi bi-shield-lock"></i></div> Badilisha Neno Siri</div>
        <form method="post">
            <input type="password" name="old_password" class="form-control mb-2" placeholder="Neno siri la zamani" required>
            <input type="password" name="new_password" class="form-control mb-3" placeholder="Neno siri jipya (min <?= PASSWORD_MIN_LENGTH ?>)" required>
            <button class="btn-grad" name="change_password"><i class="bi bi-check2-circle"></i> Badilisha</button>
        </form>
    </div>

    <!-- Orders -->
    <div class="card-soft mb-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="section-title" style="font-size:.98rem;"><div class="section-ico" style="width:34px;height:34px;font-size:1rem;"><i class="bi bi-clock-history"></i></div> Historia ya Orders</div>
            <a href="profile.php?sync=1" class="btn btn-sm" style="background:#eef0ff;color:#4834d4;border:none;border-radius:20px;font-size:.7rem;font-weight:600;padding:.25rem .7rem;"><i class="bi bi-arrow-clockwise"></i> Sasisha</a>
        </div>
        <?php if (empty($orders)): ?>
            <div class="text-center text-muted py-4" style="font-size:.85rem;"><i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>Hakuna orders bado.</div>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <?php
                    $isCompleted = strpos(strtolower($o['status']),'complet')!==false || strpos(strtolower($o['status']),'partial')!==false;
                    $canRefill = !empty($o['refill_available']) && empty($o['refill_requested']) && $isCompleted;
                ?>
                <div class="order-item">
                    <div class="order-ico"><i class="bi bi-bag-check"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size:.82rem;line-height:1.2;"><?= htmlspecialchars(mb_substr($o['service_name'],0,42)) ?></div>
                        <div class="text-muted" style="font-size:.7rem;">#<?= $o['id'] ?> · <?= number_format($o['quantity']) ?> · <?= number_format($o['price']) ?> TZS<?= !empty($o['refill_available']) ? ' · <i class="bi bi-arrow-repeat"></i> refill' : '' ?></div>
                        <?php if ($canRefill): ?>
                            <button class="btn btn-sm mt-1 refill-btn" data-id="<?= (int)$o['id'] ?>" style="background:#e4faf3;color:#00876a;border:none;border-radius:20px;font-size:.68rem;font-weight:600;padding:.2rem .7rem;"><i class="bi bi-arrow-repeat"></i> Omba Refill</button>
                        <?php elseif (!empty($o['refill_requested'])): ?>
                            <span class="badge-soft badge-warning mt-1 d-inline-block" style="font-size:.66rem;"><i class="bi bi-hourglass-split"></i> Refill: <?= htmlspecialchars($o['refill_status'] ?: 'requested') ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="badge-soft <?= pbadge($o['status']) ?>"><?= htmlspecialchars($o['status']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
ui_nav('profile', ['balance' => $user['balance']]);
ui_topup_modal();
ui_foot(<<<'JS'
<script>
document.querySelectorAll('.refill-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    if(!confirm('Omba refill kwa order hii?')) return;
    btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split"></i> Inatuma...';
    try{
      const r=await fetch('refill.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({order_id:parseInt(btn.dataset.id)})});
      const j=await r.json();
      toast(j.message, j.success?'success':'danger');
      if(j.success) setTimeout(()=>location.reload(),1200);
      else { btn.disabled=false; btn.innerHTML='<i class="bi bi-arrow-repeat"></i> Omba Refill'; }
    }catch(e){ toast('Kosa la mtandao.','danger'); btn.disabled=false; btn.innerHTML='<i class="bi bi-arrow-repeat"></i> Omba Refill'; }
  });
});
</script>
JS);
