<?php
/**
 * Orders — Enhanced page listing all orders grouped by provider (Normal/Pro)
 * with status tabs, search functionality, and refill options.
 */

require_once 'config.php';
require_once 'includes/ui.php';
require_once 'includes/order-sync.php';
require_once 'includes/progress-bar.php';
require_once 'includes/APIHandler.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Pull latest status only when the user explicitly requests a refresh; normal
// order-page navigation should not wait for multiple provider API calls.
$orderNotifications = [];
syncUserOrders($conn, $user_id, isset($_GET['sync']) && $_GET['sync'] === '1', $orderNotifications);

// User (for the nav balance pill).
$stmt = $conn->prepare("SELECT username, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// All orders, newest first.
$stmt = $conn->prepare(
    "SELECT id, service_id, service_name, platform, quantity, price, status, progress, external_order_id,
            link, created_at, refill_available, refill_requested, refill_status, provider, gateway,
            delivered_quantity, remaining_quantity
       FROM orders WHERE user_id = ? ORDER BY id DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$reviewedOrders = [];
$reviewStmt = $pdo->prepare('SELECT order_id FROM testimonials WHERE user_id = ? AND order_id IS NOT NULL');
$reviewStmt->execute([$user_id]);
foreach ($reviewStmt->fetchAll(PDO::FETCH_ASSOC) as $reviewRow) {
    $reviewedOrders[(int) $reviewRow['order_id']] = true;
}
$reviewFlash = $_SESSION['testimonial_flash'] ?? null;
unset($_SESSION['testimonial_flash']);
$favoriteIds = [];
$favoriteStmt = $conn->prepare('SELECT order_id FROM favorite_orders WHERE user_id = ?');
$favoriteStmt->bind_param('i', $user_id);
$favoriteStmt->execute();
foreach ($favoriteStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $favoriteRow) {
    $favoriteIds[(int) $favoriteRow['order_id']] = true;
}

/** Bucket a provider status string into one of our tab groups. */
function orderGroup($status)
{
    $s = normalizeOrderStatus($status);
    if ($s === 'Completed')
        return 'completed';
    if ($s === 'Canceled')
        return 'canceled';
    return 'active'; // pending / processing / in progress / unknown
}

function normalizeOrderStatus($status)
{
    $s = strtolower((string) $status);
    if (
        strpos($s, 'cancel') !== false || strpos($s, 'fail') !== false
        || strpos($s, 'refund') !== false || strpos($s, 'error') !== false
    ) {
        return 'Canceled';
    }
    if (strpos($s, 'complet') !== false || strpos($s, 'partial') !== false) {
        return 'Completed';
    }
    if (strpos($s, 'process') !== false || strpos($s, 'progress') !== false || strpos($s, 'in progress') !== false) {
        return 'Processing';
    }
    return 'Pending';
}

/** Soft badge class for a status. */
function obadge($status)
{
    $s = normalizeOrderStatus($status);
    if ($s === 'Completed')
        return 'badge-success';
    if ($s === 'Canceled')
        return 'badge-danger';
    return 'badge-warning';
}

function serviceSupportsCancellation($order)
{
    static $serviceCache = [];

    $serviceId = (int) ($order['service_id'] ?? 0);
    if ($serviceId <= 0) {
        return false;
    }

    $gateway = strtolower((string) ($order['gateway'] ?? 'primary'));
    $provider = strtolower((string) ($order['provider'] ?? ''));
    if (!in_array($provider, ['fastway', 'boost'], true)) {
        $provider = ($gateway === 'partner' || $gateway === 'pro' || $gateway === 'premium' || $gateway === 'fastway') ? 'fastway' : 'boost';
    }

    if (!isset($serviceCache[$provider])) {
        try {
            $api = new APIHandler($provider);
            $serviceCache[$provider] = [];
            foreach ($api->getAllServices() as $service) {
                $serviceCache[$provider][(int) ($service['id'] ?? 0)] = $service;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    $service = $serviceCache[$provider][$serviceId] ?? null;
    return !empty($service['cancel']);
}

// Separate orders by gateway (provider)
$ordersByGateway = ['primary' => [], 'partner' => []];
$counts = [
    'all' => 0,
    'primary' => ['all' => 0, 'active' => 0, 'completed' => 0, 'canceled' => 0],
    'partner' => ['all' => 0, 'active' => 0, 'completed' => 0, 'canceled' => 0]
];

foreach ($orders as $o) {
    $gateway = $o['gateway'] ?? 'primary';
    if (!isset($ordersByGateway[$gateway]))
        $ordersByGateway[$gateway] = [];
    $ordersByGateway[$gateway][] = $o;
    $counts['all']++;
    $counts[$gateway]['all']++;
    $group = orderGroup($o['status']);
    $counts[$gateway][$group]++;
}

$tabs = [
    'all' => ['Zote', 'bi-collection'],
    'active' => ['Inasubiri / Inaendelea', 'bi-hourglass-split'],
    'completed' => ['Imekamilika', 'bi-check2-circle'],
    'canceled' => ['Imeghairiwa', 'bi-x-circle'],
];

$gateways = [
    'primary' => ['Huduma Kawaida', 'bi-lightning-charge-fill', 'badge-info'],
    'partner' => ['Huduma Pro', 'bi-rocket-fill', 'badge-primary'],
];

ui_head('Orders Zangu — ' . APP_NAME, 'app');
?>


<div class="orders-hero">
    <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-bag-check-fill" style="font-size:1.6rem;"></i>
            <div>
                <h4 class="fw-bold mb-0">Orders Zangu</h4>
                <p class="mb-0 small opacity-75"><?= (int) $counts['all'] ?> orders kwa jumla</p>
            </div>
        </div>
        <a href="orders.php?sync=1" class="btn btn-sm"
            style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:30px;font-size:.74rem;font-weight:600;padding:.4rem .85rem;"><i
                class="bi bi-arrow-clockwise"></i> Sasisha</a>
    </div>
</div>

<div class="container px-3" style="margin-top:-1.5rem;">
    <?php if ($reviewFlash): ?><div class="alert alert-<?= htmlspecialchars((string) $reviewFlash['type'], ENT_QUOTES, 'UTF-8') ?> rounded-4" role="status"><?= htmlspecialchars((string) $reviewFlash['message'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <!-- Search Box -->
    <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="searchInput" placeholder="Tafuta order kwa ID, huduma, au link..." />
        <button class="btn-clear" onclick="document.getElementById('searchInput').value = ''; filterAll();"
            style="display:none;" id="clearBtn"><i class="bi bi-x-circle"></i></button>
    </div>

    <!-- Primary Provider Section -->
    <div class="gwrapper" data-gw="primary">
        <div class="ghead">
            <i class="bi <?= $gateways['primary'][1] ?>" style="color:var(--primary);"></i>
            <h5><?= $gateways['primary'][0] ?></h5>
            <span class="gcount"><?= (int) $counts['primary']['all'] ?> orders</span>
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
                        <span class="cnt"><?= (int) $counts['primary'][$key] ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="ordersList-primary" class="olist">
                <?php foreach ($ordersByGateway['primary'] as $o): ?>
                    <?php
                    $group = orderGroup($o['status']);
                    $statusLabel = normalizeOrderStatus($o['status']);
                    $isCompleted = $statusLabel === 'Completed';
                    $isCanceled = $statusLabel === 'Canceled';
                    $canReview = $isCompleted && empty($reviewedOrders[(int) $o['id']]);
                    $canRefill = !empty($o['refill_available']) && empty($o['refill_requested']) && $isCompleted;
                    $showCancel = !$isCompleted && !$isCanceled && serviceSupportsCancellation($o);
                    ?>
                    <div class="ocard" data-group="<?= $group ?>"
                        data-search="<?= strtolower(htmlspecialchars($o['id'] . ' ' . $o['service_name'] . ' ' . ($o['link'] ?? ''))) ?>">
                        <div class="oico"><i class="bi bi-bag-check"></i></div>
                        <div class="flex-grow-1">
                            <div class="oname"><?= htmlspecialchars((function_exists('mb_substr') ? mb_substr($o['service_name'], 0, 60) : substr($o['service_name'], 0, 60))) ?></div>
                            <div class="ometa">
                                #<?= $o['id'] ?> · <?= number_format($o['quantity']) ?> · <?= number_format($o['price']) ?> TZS
                                · <?= date('d M Y', strtotime($o['created_at'])) ?>
                                <?= !empty($o['refill_available']) ? ' · <i class="bi bi-arrow-repeat"></i> refill' : '' ?>
                            </div>
                            <?php if (!empty($o['link'])): ?>
                                <div class="olink"><i class="bi bi-link-45deg"></i>
                                    <a href="<?= htmlspecialchars($o['link']) ?>" target="_blank" rel="noopener noreferrer"
                                        style="color:inherit;text-decoration:underline;">
                                        <?= htmlspecialchars((function_exists('mb_substr') ? mb_substr($o['link'], 0, 60) : substr($o['link'], 0, 60))) ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- Order Progress Bar -->
                            <?= renderProgressBar($o['status'], $o['progress'] ?? null) ?>
                            <?php if (isset($o['delivered_quantity']) || isset($o['remaining_quantity'])): ?>
                                <div class="text-muted" style="font-size:.8rem;margin-top:.35rem;">
                                    Imetumwa: <?= number_format($o['delivered_quantity'] ?? 0) ?> · Zimebaki:
                                    <?= number_format($o['remaining_quantity'] ?? ($o['quantity'] - ($o['delivered_quantity'] ?? 0))) ?>
                                </div>
                            <?php endif; ?>

                            <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                                <?php if ($canReview): ?>
                                    <button class="btn btn-sm rate-order-btn" type="button" data-order-id="<?= (int) $o['id'] ?>" data-bs-toggle="modal" data-bs-target="#rateOrderModal"><i class="bi bi-star-fill"></i> <span data-i18n="orders.rate">Rate this order</span></button>
                                <?php endif; ?>
                                <span
                                    class="badge-soft <?= obadge($o['status']) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                <button class="btn btn-sm favorite-btn <?= !empty($favoriteIds[(int) $o['id']]) ? 'is-favorite' : '' ?>" data-id="<?= (int) $o['id'] ?>" data-favorite="<?= !empty($favoriteIds[(int) $o['id']]) ? '1' : '0' ?>"><i class="bi <?= !empty($favoriteIds[(int) $o['id']]) ? 'bi-heart-fill' : 'bi-heart' ?>"></i> <span><?= !empty($favoriteIds[(int) $o['id']]) ? 'Saved' : 'Favorite' ?></span></button>
                                <a class="btn btn-sm favorite-btn" href="index.php?service_id=<?= (int) $o['service_id'] ?>&reorder_order_id=<?= (int) $o['id'] ?>"><i class="bi bi-arrow-repeat"></i> Reorder</a>
                                <?php if ($canRefill): ?>
                                    <button class="btn btn-sm refill-btn" data-id="<?= (int) $o['id'] ?>"><i
                                            class="bi bi-arrow-repeat"></i> Omba Refill</button>
                                <?php elseif (!empty($o['refill_requested'])): ?>
                                    <span class="badge-soft badge-warning" style="font-size:.66rem;"><i
                                            class="bi bi-hourglass-split"></i> Refill:
                                        <?= htmlspecialchars($o['refill_status'] ?: 'requested') ?></span>
                                <?php endif; ?>
                                <?php if ($showCancel): ?>
                                    <button class="btn btn-sm cancel-btn" data-id="<?= (int) $o['id'] ?>"><i
                                            class="bi bi-slash-circle"></i> Cancel</button>
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
            <span class="gcount"><?= (int) $counts['partner']['all'] ?> orders</span>
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
                        <span class="cnt"><?= (int) $counts['partner'][$key] ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="ordersList-partner" class="olist">
                <?php foreach ($ordersByGateway['partner'] as $o): ?>
                    <?php
                    $group = orderGroup($o['status']);
                    $statusLabel = normalizeOrderStatus($o['status']);
                    $isCompleted = $statusLabel === 'Completed';
                    $isCanceled = $statusLabel === 'Canceled';
                    $canReview = $isCompleted && empty($reviewedOrders[(int) $o['id']]);
                    $canRefill = !empty($o['refill_available']) && empty($o['refill_requested']) && $isCompleted;
                    $showCancel = !$isCompleted && !$isCanceled && serviceSupportsCancellation($o);
                    ?>
                    <div class="ocard" data-group="<?= $group ?>"
                        data-search="<?= strtolower(htmlspecialchars($o['id'] . ' ' . $o['service_name'] . ' ' . ($o['link'] ?? ''))) ?>">
                        <div class="oico"><i class="bi bi-bag-check"></i></div>
                        <div class="flex-grow-1">
                            <div class="oname"><?= htmlspecialchars((function_exists('mb_substr') ? mb_substr($o['service_name'], 0, 60) : substr($o['service_name'], 0, 60))) ?></div>
                            <div class="ometa">
                                #<?= $o['id'] ?> · <?= number_format($o['quantity']) ?> · <?= number_format($o['price']) ?> TZS
                                · <?= date('d M Y', strtotime($o['created_at'])) ?>
                                <?= !empty($o['refill_available']) ? ' · <i class="bi bi-arrow-repeat"></i> refill' : '' ?>
                            </div>
                            <?php if (!empty($o['link'])): ?>
                                <div class="olink"><i class="bi bi-link-45deg"></i>
                                    <a href="<?= htmlspecialchars($o['link']) ?>" target="_blank" rel="noopener noreferrer"
                                        style="color:inherit;text-decoration:underline;">
                                        <?= htmlspecialchars((function_exists('mb_substr') ? mb_substr($o['link'], 0, 60) : substr($o['link'], 0, 60))) ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- Order Progress Bar -->
                            <?= renderProgressBar($o['status'], $o['progress'] ?? null) ?>
                            <?php if (isset($o['delivered_quantity']) || isset($o['remaining_quantity'])): ?>
                                <div class="text-muted" style="font-size:.8rem;margin-top:.35rem;">
                                    Imetumwa: <?= number_format($o['delivered_quantity'] ?? 0) ?> · Zimebaki:
                                    <?= number_format($o['remaining_quantity'] ?? ($o['quantity'] - ($o['delivered_quantity'] ?? 0))) ?>
                                </div>
                            <?php endif; ?>

                            <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                                <?php if ($canReview): ?>
                                    <button class="btn btn-sm rate-order-btn" type="button" data-order-id="<?= (int) $o['id'] ?>" data-bs-toggle="modal" data-bs-target="#rateOrderModal"><i class="bi bi-star-fill"></i> <span data-i18n="orders.rate">Rate this order</span></button>
                                <?php endif; ?>
                                <span
                                    class="badge-soft <?= obadge($o['status']) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                <button class="btn btn-sm favorite-btn <?= !empty($favoriteIds[(int) $o['id']]) ? 'is-favorite' : '' ?>" data-id="<?= (int) $o['id'] ?>" data-favorite="<?= !empty($favoriteIds[(int) $o['id']]) ? '1' : '0' ?>"><i class="bi <?= !empty($favoriteIds[(int) $o['id']]) ? 'bi-heart-fill' : 'bi-heart' ?>"></i> <span><?= !empty($favoriteIds[(int) $o['id']]) ? 'Saved' : 'Favorite' ?></span></button>
                                <a class="btn btn-sm favorite-btn" href="index.php?service_id=<?= (int) $o['service_id'] ?>&reorder_order_id=<?= (int) $o['id'] ?>"><i class="bi bi-arrow-repeat"></i> Reorder</a>
                                <?php if ($canRefill): ?>
                                    <button class="btn btn-sm refill-btn" data-id="<?= (int) $o['id'] ?>"><i
                                            class="bi bi-arrow-repeat"></i> Omba Refill</button>
                                <?php elseif (!empty($o['refill_requested'])): ?>
                                    <span class="badge-soft badge-warning" style="font-size:.66rem;"><i
                                            class="bi bi-hourglass-split"></i> Refill:
                                        <?= htmlspecialchars($o['refill_status'] ?: 'requested') ?></span>
                                <?php endif; ?>
                                <?php if ($showCancel): ?>
                                    <button class="btn btn-sm cancel-btn" data-id="<?= (int) $o['id'] ?>"><i
                                            class="bi bi-slash-circle"></i> Cancel</button>
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

<div class="modal fade" id="rateOrderModal" tabindex="-1" aria-labelledby="rateOrderTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down"><div class="modal-content rounded-4 border-0">
    <form method="post" action="submit-testimonial.php" id="orderReviewForm">
      <div class="modal-header"><div><h2 class="modal-title fs-5 fw-bold" id="rateOrderTitle"><span data-i18n="orders.rateTitle">Rate this order</span></h2><p class="small text-muted mb-0"><span data-i18n="orders.reviewPending">Your review is private until it is approved.</span></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <?= csrf_field() ?><input type="hidden" name="order_id" id="reviewOrderId" value="">
        <label class="form-label d-block" data-i18n="orders.rating">Your rating</label>
        <div class="star-picker" role="radiogroup" aria-label="Rating from one to five stars">
          <?php for ($star = 1; $star <= 5; $star++): ?><input type="radio" id="orderRating<?= $star ?>" name="rating" value="<?= $star ?>" <?= $star === 5 ? 'checked' : '' ?> required><label data-rating="<?= $star ?>" for="orderRating<?= $star ?>" aria-label="<?= $star ?> stars" title="<?= $star ?> stars">★</label><?php endfor; ?>
        </div>
        <label class="form-label mt-3" for="reviewDisplayName" data-i18n="orders.displayName">Public name (first name + last initial)</label>
        <input class="form-control" id="reviewDisplayName" name="display_name" maxlength="60" autocomplete="name" placeholder="e.g. Amina K." required>
        <label class="form-label mt-3" for="reviewMessage" data-i18n="orders.reviewText">Comment (20–300 characters; no links)</label>
        <textarea class="form-control" id="reviewMessage" name="message" minlength="20" maxlength="300" rows="4" required></textarea>
        <div class="d-flex justify-content-between small text-muted mt-1"><span data-i18n="orders.reviewNoLinks">HTML is removed; links are not allowed.</span><span id="reviewCharCount">0 / 300</span></div>
        <label class="review-consent mt-3"><input type="checkbox" name="consent_to_publish" value="1" required><span data-i18n="orders.consent">I allow Royal SMM to show this review with my first name and initial.</span></label>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal" data-i18n="common.cancel">Cancel</button><button type="submit" class="btn btn-primary" data-i18n="orders.submitReview">Submit review</button></div>
    </form>
  </div></div>
</div>

<script>
(function(){
  const idField=document.getElementById('reviewOrderId');
  document.querySelectorAll('.rate-order-btn').forEach(button=>button.addEventListener('click',()=>{idField.value=button.dataset.orderId||'';}));
  const message=document.getElementById('reviewMessage'),count=document.getElementById('reviewCharCount');
  if(message&&count) message.addEventListener('input',()=>{count.textContent=Array.from(message.value).length+' / 300';});
  const ratingInputs=Array.from(document.querySelectorAll('.star-picker input[name="rating"]'));
  const paintStars=()=>{const selected=Number(ratingInputs.find(input=>input.checked)?.value||0);document.querySelectorAll('.star-picker label[data-rating]').forEach(label=>label.classList.toggle('selected',Number(label.dataset.rating)<=selected));};
  ratingInputs.forEach(input=>input.addEventListener('change',paintStars));paintStars();
})();
</script>
<?php
ui_nav('orders', ['balance' => $user['balance']]);
ui_topup_modal();
$notificationJson = json_encode($orderNotifications, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if (!is_string($notificationJson)) $notificationJson = '[]';
$ordersFooterScript = <<<'JS'
<script>
(function(){
  const notes = __ORDER_NOTIFICATION_JSON__;
  if(Array.isArray(notes)){
    notes.forEach(n=>{ if(n && n.message){ toast(n.message, n.type || 'primary'); } });
  }
})();
// Tab and search filtering
(function(){
  const searchInput = document.getElementById('searchInput');
  const clearBtn = document.getElementById('clearBtn');
  const providers = ['primary', 'partner'];
  const activeGroup = {primary:'all', partner:'all'};

  function applyFilter(provider) {
    const query = (searchInput.value || '').toLowerCase().trim();
    const group = activeGroup[provider] || 'all';
    const cards = document.querySelectorAll(`#ordersList-${provider} [data-group]`);
    const empty = document.getElementById(`emptyFiltered-${provider}`);
    let shown = 0;
    cards.forEach(card => {
      const matchesGroup = group === 'all' || card.dataset.group === group;
      const matchesSearch = !query || (card.dataset.search || '').toLowerCase().includes(query);
      card.hidden = !(matchesGroup && matchesSearch);
      if (!card.hidden) shown++;
    });
    if (empty) empty.style.display = cards.length && !shown ? '' : 'none';
  }

  function applyAll() {
    clearBtn.style.display = searchInput.value.trim() ? 'block' : 'none';
    providers.forEach(applyFilter);
  }

  searchInput.addEventListener('input', applyAll);
  document.querySelectorAll('.otab').forEach(tab => tab.addEventListener('click', () => {
    const provider = tab.dataset.gw;
    activeGroup[provider] = tab.dataset.g;
    document.querySelectorAll(`.otabs[data-gw="${provider}"] .otab`).forEach(item => item.classList.toggle('active', item === tab));
    applyFilter(provider);
  }));
  applyAll();
})();

// Favorite orders: save or remove without leaving the order list.
document.querySelectorAll('.favorite-btn[data-id]').forEach(btn => {
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    const wasSaved = btn.dataset.favorite === '1';
    try {
      const r = await fetch('favorite.php', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name=csrf-token]')?.content||''}, body:JSON.stringify({action:'toggle', order_id:parseInt(btn.dataset.id)})});
      const j = await r.json();
      if (!j.success) throw new Error(j.message || 'Imeshindikana');
      const saved = !!j.favorited;
      btn.dataset.favorite = saved ? '1' : '0';
      btn.classList.toggle('is-favorite', saved);
      btn.querySelector('i').className = 'bi ' + (saved ? 'bi-heart-fill' : 'bi-heart');
      btn.querySelector('span').textContent = saved ? 'Saved' : 'Favorite';
      toast(j.message, 'success');
    } catch (e) { toast(e.message || 'Imeshindikana kuhifadhi favorite.', 'danger'); }
    finally { btn.disabled = false; }
  });
});

// Refill requests
document.querySelectorAll('.refill-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    if(!confirm('Omba refill kwa order hii?')) return;
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Inatuma...';
    try{
      const r = await fetch('refill.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name=csrf-token]')?.content||''},body:JSON.stringify({order_id:parseInt(btn.dataset.id)})});
      const j = await r.json();
      toast(j.message, j.success?'success':'danger');
      if(j.success) setTimeout(()=>location.reload(),1200);
      else { btn.disabled=false; btn.innerHTML='<i class="bi bi-arrow-repeat"></i> Omba Refill'; }
    }catch(e){ toast('Kosa la mtandao.','danger'); btn.disabled=false; btn.innerHTML='<i class="bi bi-arrow-repeat"></i> Omba Refill'; }
  });
});

document.querySelectorAll('.cancel-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if(!confirm('Omba cancellation kwa order hii?')) return;
        btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Inatuma...';
        try{
            const r = await fetch('cancel-order.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name=csrf-token]')?.content||''},body:JSON.stringify({order_id:parseInt(btn.dataset.id)})});
            const j = await r.json();
            toast(j.message, j.success?'success':'warning');
            if(j.success) setTimeout(()=>location.reload(),1200);
            else { btn.disabled=false; btn.innerHTML='<i class="bi bi-slash-circle"></i> Cancel'; }
        }catch(e){ toast('Kosa la mtandao.','danger'); btn.disabled=false; btn.innerHTML='<i class="bi bi-slash-circle"></i> Cancel'; }
    });
});
// Pull down from the top of the Orders page to refresh provider status.
(function(){
  let startY = 0, refreshing = false;
  document.addEventListener('touchstart', e => { if (window.scrollY <= 2 && e.touches[0]) startY = e.touches[0].clientY; }, {passive:true});
  document.addEventListener('touchend', e => {
    if (refreshing || !startY || window.scrollY > 2 || !e.changedTouches[0]) return;
    const distance = e.changedTouches[0].clientY - startY;
    startY = 0;
    if (distance > 90) { refreshing = true; toast('Inasasisha orders...', 'primary'); setTimeout(() => location.href = 'orders.php?sync=1', 350); }
  }, {passive:true});
})();
</script>
JS;
ui_foot(str_replace('__ORDER_NOTIFICATION_JSON__', $notificationJson, $ordersFooterScript));
?>
