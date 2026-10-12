<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ui.php';
requireLogin();
$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT o.id, o.service_id, o.service_name, o.platform, o.quantity, o.price, o.link, o.provider, f.created_at AS saved_at FROM favorite_orders f JOIN orders o ON o.id = f.order_id WHERE f.user_id = ? ORDER BY f.created_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$favorites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
function favH($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
ui_head('Favorites — ' . APP_NAME, 'app', '<style>
.favorite-hero{padding:1.45rem 1.25rem;border-radius:26px;color:#fff;background:linear-gradient(135deg,#6c5ce7,#4834d4);box-shadow:0 18px 40px rgba(72,52,212,.27);position:relative;overflow:hidden}.favorite-hero:after{content:"♡";position:absolute;right:1.2rem;top:.15rem;font-size:6rem;line-height:1;opacity:.12}.favorite-card{background:#fff;border-radius:22px;padding:1rem;box-shadow:0 14px 34px rgba(43,54,116,.07);border:1px solid rgba(43,54,116,.05);margin-bottom:.75rem;animation:favoriteIn .35s both}.favorite-card:hover{transform:translateY(-2px);box-shadow:0 18px 38px rgba(43,54,116,.12)}.favorite-card{transition:transform .2s,box-shadow .2s}.favorite-name{font-weight:700;font-size:.86rem;line-height:1.25}.favorite-meta{font-size:.72rem;color:#8a93b2;margin-top:.25rem}.favorite-link{font-size:.7rem;color:#526174;word-break:break-all;margin-top:.45rem;padding:.5rem .6rem;border-radius:10px;background:#f8f9fd}.favorite-actions{display:flex;gap:.45rem;margin-top:.75rem}.favorite-actions a,.favorite-actions button{border:0;border-radius:12px;padding:.5rem .7rem;font-size:.72rem;font-weight:700}.reorder-btn{background:linear-gradient(135deg,#6c5ce7,#4834d4);color:#fff}.remove-btn{background:#fff1f0;color:#c0392b}.empty-favorites{background:#fff;border-radius:24px;padding:2.5rem 1.3rem;text-align:center;box-shadow:0 14px 34px rgba(43,54,116,.06)}@keyframes favoriteIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
</style>');
ui_nav('favorites');
?>
<main class="container px-3" style="padding-top:4.5rem;padding-bottom:4rem;">
  <div class="favorite-hero mb-3"><div class="pill mb-2" style="background:rgba(255,255,255,.18);color:#fff"><i class="bi bi-heart-fill"></i> Saved orders</div><h3 class="fw-bold mb-1">Favorites</h3><p class="mb-0 small opacity-75">Hifadhi huduma unazotumia mara kwa mara na uzi-order tena kwa urahisi.</p></div>
  <?php if (!$favorites): ?><div class="empty-favorites"><i class="bi bi-heart fs-1" style="color:#b8b0ff"></i><h5 class="fw-bold mt-3">Bado huna favorite</h5><p class="text-muted small">Nenda Orders Zangu, bonyeza moyo kwenye order yoyote, kisha itahifadhiwa hapa.</p><a href="orders.php" class="btn btn-grad" style="max-width:220px;margin:auto">Angalia orders</a></div><?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-2"><span class="small text-muted"><?= count($favorites) ?> saved order<?= count($favorites) === 1 ? '' : 's' ?></span><a href="orders.php" class="small fw-semibold" style="color:var(--primary)">Orders Zangu</a></div>
    <div id="favoriteList">
    <?php foreach ($favorites as $f): ?><article class="favorite-card" data-favorite-id="<?= (int) $f['id'] ?>"><div class="d-flex gap-2 align-items-start"><div class="section-ico" style="flex:0 0 auto"><i class="bi bi-heart-fill"></i></div><div class="flex-grow-1"><div class="favorite-name"><?= favH($f['service_name']) ?></div><div class="favorite-meta">Service ID <?= (int) $f['service_id'] ?> · <?= favH($f['platform'] ?: 'Social') ?> · <?= number_format((int) $f['quantity']) ?> · <?= number_format((float) $f['price']) ?> TSh</div><div class="favorite-link"><i class="bi bi-link-45deg me-1"></i><?= favH($f['link']) ?></div></div></div><div class="favorite-actions"><a class="reorder-btn" href="index.php?service_id=<?= (int) $f['service_id'] ?>&reorder_id=<?= (int) $f['id'] ?>"><i class="bi bi-arrow-repeat me-1"></i> Reorder</a><button class="remove-btn remove-favorite" data-id="<?= (int) $f['id'] ?>"><i class="bi bi-trash3 me-1"></i> Remove</button></div></article><?php endforeach; ?></div>
  <?php endif; ?>
</main>
<script>document.querySelectorAll('.remove-favorite').forEach(b=>b.addEventListener('click',async()=>{b.disabled=true;const r=await fetch('favorite.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'remove',order_id:b.dataset.id})});const j=await r.json();if(j.success){const c=b.closest('.favorite-card');c.style.opacity='0';c.style.transform='scale(.96)';setTimeout(()=>{c.remove();if(!document.querySelector('.favorite-card'))location.reload()},220)}else{b.disabled=false;alert(j.message)}}));</script>
<?php ui_foot(); ?>
