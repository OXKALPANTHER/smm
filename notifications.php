<?php
require_once 'config.php';
require_once 'includes/ui.php';
requireLogin();

$user_id = (int) $_SESSION['user_id'];
$me = getUser($user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'mark_read' && $id > 0) {
        markNotificationRead($id, $user_id);
        $redirect = 'notifications.php?msg=marked';
    } elseif ($action === 'mark_all_read') {
        markAllNotificationsRead($user_id);
        $redirect = 'notifications.php?msg=all_marked';
    } elseif ($action === 'delete' && $id > 0) {
        deleteNotification($id, $user_id);
        $redirect = 'notifications.php?msg=deleted';
    } elseif ($action === 'delete_all') {
        deleteAllNotifications($user_id);
        $redirect = 'notifications.php?msg=all_deleted';
    } else {
        $redirect = 'notifications.php';
    }
    header('Location: ' . $redirect);
    exit;
}

$notifications = getUserNotifications($user_id, 100);
$unreadCount = getUnreadNotificationCount($user_id);
$notice = '';
$noticeType = 'info';
switch ($_GET['msg'] ?? '') {
    case 'marked':
        $notice = 'Taarifa imewekwa kuwa imesomwa.';
        $noticeType = 'success';
        break;
    case 'all_marked':
        $notice = 'Notisi zote zimewekwa kuwa zimesomwa.';
        $noticeType = 'success';
        break;
    case 'deleted':
        $notice = 'Taarifa imefutwa.';
        $noticeType = 'warning';
        break;
    case 'all_deleted':
        $notice = 'Notisi zote zimefutwa.';
        $noticeType = 'warning';
        break;
}

ui_head('Notisi — ' . APP_NAME, 'app');
?>
<div class="container py-4" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-bell-fill text-primary"></i> Notisi Zako</h3>
            <p class="text-muted mb-0">Pata taarifa za order, salio, na taarifa muhimu kutoka kwa mfumo.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="index.php" class="btn btn-sm btn-outline-primary rounded-pill"><i
                    class="bi bi-house-door-fill"></i> Dashboard</a>
            <div class="badge-soft badge-warning"><?= number_format($unreadCount) ?> mpya</div>
        </div>
    </div>

    <?php if ($notice): ?>
        <div class="alert alert-<?= htmlspecialchars($noticeType) ?> rounded-4 border-0 mb-3">
            <i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($notice) ?>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php if ($unreadCount > 0): ?>
            <form method="post" class="d-inline">
                <input type="hidden" name="action" value="mark_all_read">
                <button class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-check2-all"></i> Weka zote
                    zimesomwa</button>
            </form>
        <?php endif; ?>
        <?php if (!empty($notifications)): ?>
            <form method="post" class="d-inline">
                <input type="hidden" name="action" value="delete_all">
                <button class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i> Futa zote</button>
            </form>
        <?php endif; ?>
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
                    <div class="border rounded-4 p-3 <?= ($n['status'] ?? 'unread') === 'unread' ? 'border-primary' : '' ?>"
                        style="background: <?= ($n['status'] ?? 'unread') === 'unread' ? '#f7f9ff' : '#ffffff' ?>;">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($n['title'] ?? 'Notification') ?></div>
                                <div class="text-muted small mt-1"><?= nl2br(htmlspecialchars($n['message'] ?? '')) ?></div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-2">
                                <span
                                    class="badge-soft badge-<?= htmlspecialchars($n['type'] ?? 'info') ?>"><?= htmlspecialchars($n['type'] ?? 'info') ?></span>
                                <span
                                    class="text-muted small"><?= htmlspecialchars(substr($n['created_at'] ?? '', 0, 16)) ?></span>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <?php if (($n['status'] ?? 'unread') === 'unread'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-check2"></i> Weka
                                        alisoma</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i>
                                    Futa</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php ui_foot(); ?>