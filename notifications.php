<?php
require_once 'config.php';
require_once 'includes/ui.php';
requireLogin();

$user_id = (int)$_SESSION['user_id'];
$me = getUser($user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read' && !empty($_POST['id'])) {
        markNotificationRead((int)$_POST['id'], $user_id);
    }
    header('Location: notifications.php');
    exit;
}

$notifications = getUserNotifications($user_id, 100);
$unreadCount = getUnreadNotificationCount($user_id);

ui_head('Notisi — ' . APP_NAME, 'app');
?>
<div class="container py-4" style="max-width: 720px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="fw-bold mb-1"><i class="bi bi-bell-fill text-primary"></i> Notisi Zako</h3>
      <p class="text-muted mb-0">Pata taarifa za order, salio, na taarifa muhimu kutoka kwa mfumo.</p>
    </div>
    <div class="badge-soft badge-warning"><?= number_format($unreadCount) ?> mpya</div>
  </div>

  <div class="card-soft">
    <?php if (empty($notifications)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-bell-slash fs-1"></i>
        <div class="mt-2">Hakuna notisi kwa sasa.</div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-2">
        <?php foreach ($notifications as $n): ?>
          <div class="border rounded-4 p-3 <?= ($n['status'] ?? 'unread') === 'unread' ? 'border-primary' : '' ?>" style="background: <?= ($n['status'] ?? 'unread') === 'unread' ? '#f7f9ff' : '#ffffff' ?>;">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars($n['title'] ?? 'Notification') ?></div>
                <div class="text-muted small mt-1"><?= nl2br(htmlspecialchars($n['message'] ?? '')) ?></div>
              </div>
              <div class="d-flex flex-column align-items-end gap-2">
                <span class="badge-soft badge-<?= htmlspecialchars($n['type'] ?? 'info') ?>"><?= htmlspecialchars($n['type'] ?? 'info') ?></span>
                <span class="text-muted small"><?= htmlspecialchars(substr($n['created_at'] ?? '', 0, 16)) ?></span>
              </div>
            </div>
            <?php if (($n['status'] ?? 'unread') === 'unread'): ?>
              <form method="post" class="mt-2">
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <button class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-check2"></i> Weka alisoma</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php ui_foot(); ?>
