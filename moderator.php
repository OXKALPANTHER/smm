<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ui.php';
requireModerator();

$moderatorId = (int) ($_SESSION['user_id'] ?? 0);
$allowedOrderStatuses = ['Pending', 'Processing', 'In progress', 'Completed', 'Partial', 'Canceled', 'Failed'];
$allowedRefillStatuses = ['requested', 'processing', 'done', 'rejected'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSameOriginRequest();
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = (string) ($input['action'] ?? '');
    $reply = static function (bool $ok, string $message, array $extra = []): void {
        echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
        exit;
    };

    try {
        if ($action === 'update_order') {
            $orderId = (int) ($input['order_id'] ?? 0);
            $status = trim((string) ($input['status'] ?? ''));
            if ($orderId <= 0 || !in_array($status, $allowedOrderStatuses, true)) {
                $reply(false, 'Chagua order na status sahihi.');
            }
            $stmt = $conn->prepare('UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->bind_param('si', $status, $orderId);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $reply(false, 'Order haijapatikana au haikubadilika.');
            }
            logActivity($moderatorId, 'moderator_order_status', "Order #{$orderId} -> {$status}");
            $reply(true, 'Order status imesasishwa.');
        }

        if ($action === 'update_refill') {
            $orderId = (int) ($input['order_id'] ?? 0);
            $refillStatus = trim((string) ($input['refill_status'] ?? ''));
            if ($orderId <= 0 || !in_array($refillStatus, $allowedRefillStatuses, true)) {
                $reply(false, 'Chagua refill na status sahihi.');
            }
            $requested = in_array($refillStatus, ['done', 'rejected'], true) ? 0 : 1;
            $stmt = $conn->prepare('UPDATE orders SET refill_status = ?, refill_requested = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->bind_param('sii', $refillStatus, $requested, $orderId);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $reply(false, 'Refill haijapatikana au haikubadilika.');
            }
            logActivity($moderatorId, 'moderator_refill_status', "Order #{$orderId} -> {$refillStatus}");
            $reply(true, 'Refill status imesasishwa.');
        }

        if ($action === 'send_message') {
            $userId = (int) ($input['user_id'] ?? 0);
            $title = trim((string) ($input['title'] ?? ''));
            $message = trim((string) ($input['message'] ?? ''));
            $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
            $messageLength = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
            if ($userId <= 0 || $title === '' || $message === '' || $titleLength > 120 || $messageLength > 2000) {
                $reply(false, 'Jaza ujumbe kwa usahihi.');
            }
            $lookup = $conn->prepare('SELECT id FROM users WHERE id = ? AND status <> \'deleted\'');
            $lookup->bind_param('i', $userId);
            $lookup->execute();
            if (!$lookup->get_result()->fetch_assoc()) {
                $reply(false, 'Mtumiaji hakupatikana.');
            }
            if (!createNotification($userId, $title, $message, 'info', 'user', ['sender' => $moderatorId, 'role' => 'moderator'])) {
                $reply(false, 'Ujumbe haukutumwa.');
            }
            logActivity($moderatorId, 'moderator_user_message', "Message sent to user #{$userId}");
            $reply(true, 'Ujumbe umetumwa kwa mtumiaji.');
        }

        $reply(false, 'Kitendo hakijulikani.');
    } catch (Throwable $e) {
        error_log('Moderator action failed: ' . $e->getMessage());
        $reply(false, 'Kuna hitilafu. Jaribu tena.');
    }
}

$orders = $conn->query("SELECT o.id, o.user_id, o.service_name, o.platform, o.quantity, o.price, o.status,
                               o.external_order_id, o.link, o.created_at, o.refill_requested, o.refill_status,
                               u.username
                        FROM orders o LEFT JOIN users u ON u.id = o.user_id
                        ORDER BY o.id DESC LIMIT 120")->fetch_all(MYSQLI_ASSOC);
$refills = $conn->query("SELECT o.id, o.user_id, o.service_name, o.quantity, o.link, o.refill_status,
                                o.refill_requested_at, u.username
                         FROM orders o LEFT JOIN users u ON u.id = o.user_id
                         WHERE o.refill_requested = 1
                         ORDER BY o.refill_requested_at DESC LIMIT 80")->fetch_all(MYSQLI_ASSOC);
$openOrders = (int) ($conn->query("SELECT COUNT(*) c FROM orders WHERE LOWER(status) IN ('pending','processing','in progress')")->fetch_assoc()['c'] ?? 0);
$pendingRefills = count($refills);
$activeUsers = (int) ($conn->query("SELECT COUNT(*) c FROM users WHERE status = 'active'")->fetch_assoc()['c'] ?? 0);

function moderatorStatusClass($status): string
{
    $s = strtolower((string) $status);
    if (strpos($s, 'complete') !== false || $s === 'done') return 'badge-success';
    if (strpos($s, 'cancel') !== false || strpos($s, 'fail') !== false || $s === 'rejected') return 'badge-danger';
    if (strpos($s, 'process') !== false || strpos($s, 'pending') !== false || $s === 'requested') return 'badge-warning';
    return 'badge-secondary';
}
function moderatorH($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

ui_head('Moderator Desk — ' . APP_NAME, 'app', '<style>
.moderator-shell{padding:1.2rem 0 4rem}.mod-card{background:#fff;border:1px solid rgba(43,54,116,.06);border-radius:22px;padding:1.15rem;box-shadow:0 14px 34px rgba(43,54,116,.06)}
.mod-stat{display:flex;align-items:center;gap:.8rem}.mod-stat i{width:42px;height:42px;display:flex;align-items:center;justify-content:center;border-radius:13px;background:#f0f2fb;color:var(--primary);font-size:1.2rem}.mod-stat strong{display:block;font-size:1.35rem;line-height:1.1}.mod-stat span{font-size:.72rem;color:var(--muted)}
.mod-table{min-width:760px}.mod-table th{font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:.35px;white-space:nowrap}.mod-table td{font-size:.8rem;vertical-align:middle}.table-wrap{overflow-x:auto}.mod-note{background:linear-gradient(135deg,#f4f2ff,#f0fbfa);border:1px solid rgba(108,92,231,.12);border-radius:18px;padding:.9rem 1rem;font-size:.78rem;color:#526174}.btn-sm{border-radius:10px;font-weight:600}.mod-search{max-width:300px}
</style>');
ui_nav('moderator');
?>
<main class="container moderator-shell">
    <div class="hero mb-3">
        <div class="d-flex align-items-start justify-content-between gap-3 position-relative" style="z-index:1">
            <div><div class="pill mb-2" style="background:rgba(255,255,255,.18);color:#fff"><i class="bi bi-shield-check"></i> Moderator access</div><h2 class="fw-bold mb-1">Moderator Desk</h2><p class="mb-0" style="opacity:.86;font-size:.82rem">Simamia orders, refills na mawasiliano ya wateja.</p></div>
            <i class="bi bi-shield-lock" style="font-size:3rem;opacity:.28"></i>
        </div>
    </div>

    <div class="mod-note mb-3"><i class="bi bi-info-circle me-1"></i> Moderator anaweza kusimamia order/refill na kutuma ujumbe wa support. Salio, users deletion, roles, provider keys na settings zinalindwa kwa Admin pekee.</div>

    <div class="row g-2 mb-3">
        <div class="col-4"><div class="mod-card mod-stat"><i class="bi bi-hourglass-split"></i><div><strong><?= number_format($openOrders) ?></strong><span>Open orders</span></div></div></div>
        <div class="col-4"><div class="mod-card mod-stat"><i class="bi bi-arrow-repeat"></i><div><strong><?= number_format($pendingRefills) ?></strong><span>Refills</span></div></div></div>
        <div class="col-4"><div class="mod-card mod-stat"><i class="bi bi-people"></i><div><strong><?= number_format($activeUsers) ?></strong><span>Active users</span></div></div></div>
    </div>

    <section class="mod-card mb-3" id="refills">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div class="section-title"><span class="section-ico"><i class="bi bi-arrow-repeat"></i></span> Refill queue</div><span class="badge-soft badge-warning"><?= number_format($pendingRefills) ?> pending</span></div>
        <?php if (!$refills): ?><p class="text-muted small mb-0">Hakuna refill request inayosubiri.</p><?php else: ?>
        <div class="table-wrap"><table class="table mod-table align-middle mb-0"><thead><tr><th>Order</th><th>Customer</th><th>Service</th><th>Link</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($refills as $r): ?><tr><td class="fw-bold">#<?= (int) $r['id'] ?></td><td><?= moderatorH($r['username'] ?: ('User #' . $r['user_id'])) ?></td><td><?= moderatorH($r['service_name']) ?><br><small class="text-muted"><?= number_format((int) $r['quantity']) ?></small></td><td><a href="<?= moderatorH($r['link']) ?>" target="_blank" rel="noopener" class="small">Open <i class="bi bi-box-arrow-up-right"></i></a></td><td><span class="badge-soft <?= moderatorStatusClass($r['refill_status']) ?>"><?= moderatorH($r['refill_status'] ?: 'requested') ?></span></td><td><select class="form-select form-select-sm refill-select" data-id="<?= (int) $r['id'] ?>"><option value="requested">Requested</option><option value="processing">Processing</option><option value="done">Done</option><option value="rejected">Rejected</option></select></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>

    <section class="mod-card mb-3" id="orders">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div class="section-title"><span class="section-ico"><i class="bi bi-bag-check"></i></span> Order desk</div><input id="orderSearch" class="form-control mod-search" placeholder="Search order, customer or service..." aria-label="Search orders"></div>
        <div class="table-wrap"><table class="table mod-table align-middle mb-0"><thead><tr><th>Order</th><th>Customer</th><th>Service</th><th>Qty</th><th>Price</th><th>Provider ID</th><th>Status</th><th>Update</th></tr></thead><tbody id="orderRows">
        <?php foreach ($orders as $o): ?><tr data-search="<?= moderatorH(strtolower('#'.$o['id'].' '.$o['username'].' '.$o['service_name'].' '.$o['external_order_id'])) ?>"><td class="fw-bold">#<?= (int) $o['id'] ?><br><small class="text-muted"><?= moderatorH(date('d M H:i', strtotime($o['created_at']))) ?></small></td><td><?= moderatorH($o['username'] ?: ('User #' . $o['user_id'])) ?></td><td><?= moderatorH($o['service_name']) ?><br><small class="text-muted"><?= moderatorH($o['platform']) ?></small></td><td><?= number_format((int) $o['quantity']) ?></td><td><?= number_format((float) $o['price']) ?> TSh</td><td><?= moderatorH($o['external_order_id'] ?: '—') ?></td><td><span class="badge-soft <?= moderatorStatusClass($o['status']) ?>"><?= moderatorH($o['status']) ?></span></td><td><select class="form-select form-select-sm order-status" data-id="<?= (int) $o['id'] ?>"><?php foreach ($allowedOrderStatuses as $status): ?><option <?= $o['status'] === $status ? 'selected' : '' ?>><?= moderatorH($status) ?></option><?php endforeach; ?></select></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section class="mod-card" id="support">
        <div class="section-title mb-3"><span class="section-ico"><i class="bi bi-chat-left-text"></i></span> Send support message</div>
        <form id="messageForm"><div class="row g-2"><div class="col-md-3"><label class="form-label">User ID</label><input class="form-control" name="user_id" type="number" min="1" required placeholder="e.g. 24"></div><div class="col-md-4"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="120" required placeholder="Order update"></div><div class="col-md-5"><label class="form-label">Message</label><textarea class="form-control" name="message" maxlength="2000" rows="2" required placeholder="Write a helpful update..."></textarea></div></div><button class="btn-grad mt-3" style="max-width:220px" type="submit"><i class="bi bi-send"></i> Send message</button></form>
    </section>
</main>
<script>
const postModerator = async (payload) => { const r=await fetch('moderator.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); return r.json(); };
const notify = (msg, ok=true) => { const el=document.createElement('div'); el.className='alert '+(ok?'alert-success':'alert-danger')+' shadow-sm'; el.textContent=msg; document.getElementById('toastWrap').appendChild(el); setTimeout(()=>el.remove(),3000); };
document.querySelectorAll('.order-status').forEach(el=>el.addEventListener('change',async()=>{const old=el.dataset.old||el.value; const r=await postModerator({action:'update_order',order_id:el.dataset.id,status:el.value}); if(!r.success){el.value=old; notify(r.message,false)}else{el.dataset.old=el.value;notify(r.message)}}));
document.querySelectorAll('.refill-select').forEach(el=>{el.value=el.closest('tr').querySelector('.badge-soft').textContent.trim(); el.addEventListener('change',async()=>{const r=await postModerator({action:'update_refill',order_id:el.dataset.id,refill_status:el.value}); if(!r.success) notify(r.message,false); else {notify(r.message);setTimeout(()=>location.reload(),500)}})});
document.getElementById('orderSearch').addEventListener('input',e=>{const q=e.target.value.toLowerCase().trim();document.querySelectorAll('#orderRows tr').forEach(row=>row.style.display=(!q||row.dataset.search.includes(q))?'':'none')});
document.getElementById('messageForm').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.target);const r=await postModerator({action:'send_message',user_id:f.get('user_id'),title:f.get('title'),message:f.get('message')});notify(r.message,r.success);if(r.success)e.target.reset()});
</script>
<?php ui_foot(); ?>
