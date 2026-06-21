<?php
require_once 'config.php';
require_once 'includes/ui.php';
requireLogin();

$steps = [
    ['bi-grid-3x3-gap', 'Chagua Platform na Huduma', 'Kwenye home page, chagua platform (Instagram, TikTok, n.k.) kisha huduma unayotaka.'],
    ['bi-link-45deg', 'Weka Link / Username', 'Ingiza link ya post au username. Hakikisha akaunti SI private.'],
    ['bi-sort-numeric-up', 'Weka Idadi', 'Angalia kiwango cha chini (min) na cha juu (max) cha huduma uliyochagua.'],
    ['bi-rocket-takeoff', 'Thibitisha Order', 'Bofya "THIBITISHA ORDER". Hakikisha una salio la kutosha.'],
    ['bi-graph-up-arrow', 'Fuatilia Order Yako', 'Nenda kwenye Profile kuona maendeleo na status ya orders zako.'],
];

ui_head('Jinsi ya Kutumia — ' . APP_NAME, 'app');
?>
<style>
.howto-hero{background:linear-gradient(135deg,var(--primary),var(--primary-2));border-radius:0 0 30px 30px;padding:1.8rem 1.3rem;color:#fff;box-shadow:0 12px 30px rgba(72,52,212,.3);}
.step-card{background:#fff;border-radius:20px;padding:1.1rem;margin-bottom:.8rem;box-shadow:0 8px 22px rgba(43,54,116,.05);display:flex;align-items:center;gap:1rem;}
.step-num{width:48px;height:48px;background:linear-gradient(135deg,var(--primary),var(--primary-2));border-radius:15px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;flex:0 0 auto;box-shadow:0 8px 16px rgba(108,92,231,.25);}
.info-card{background:#eef4ff;border-radius:18px;padding:1.1rem;margin-top:1rem;border-left:5px solid var(--primary);font-size:.88rem;}
.floating-btn{position:fixed;right:18px;width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.7rem;color:#fff;box-shadow:0 10px 25px rgba(0,0,0,.2);z-index:1050;}
.btn-wa{bottom:100px;background:#25D366;}
</style>

<div class="howto-hero">
    <h4 class="fw-bold mb-1"><i class="bi bi-book-half me-2"></i>Mwongozo wa Matumizi</h4>
    <p class="mb-0 small opacity-75">Hatua 5 rahisi za kuweka order</p>
</div>

<div class="container px-3 pt-3">
    <?php foreach ($steps as $i => [$icon, $title, $desc]): ?>
        <div class="step-card">
            <div class="step-num"><?= $i + 1 ?></div>
            <div>
                <h6 class="fw-bold mb-1"><i class="bi <?= $icon ?> me-1 text-primary"></i><?= htmlspecialchars($title) ?></h6>
                <p class="mb-0 text-muted" style="font-size:.85rem;"><?= htmlspecialchars($desc) ?></p>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="info-card">
        <i class="bi bi-info-circle-fill text-primary me-1"></i>
        <strong>Zingatia:</strong> Ukihitaji msaada zaidi, tumia kitufe cha WhatsApp pembeni.
    </div>
</div>

<a href="https://wa.me/745720609" target="_blank" class="floating-btn btn-wa"><i class="bi bi-whatsapp"></i></a>

<?php
ui_bottom_nav('howto');
ui_topup_modal();
ui_foot();
