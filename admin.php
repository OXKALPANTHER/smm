<?php
require_once 'config.php';
require_once 'includes/ui.php';
requireAdmin();

$admin_id = $_SESSION['user_id'];

/* ============================================================
 * AJAX ACTION HANDLERS (return JSON, exit before HTML)
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $in['action'] ?? '';
    $reply = function ($ok, $msg, $extra = []) {
        echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
        exit;
    };

    try {
        switch ($action) {
            case 'add_balance':
                $uid = (int)($in['user_id'] ?? 0);
                $amt = (float)($in['amount'] ?? 0);
                if ($uid <= 0 || $amt == 0) $reply(false, 'Weka kiasi sahihi.');
                $conn->begin_transaction();
                $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->bind_param("di", $amt, $uid); $stmt->execute();
                $desc = ($amt >= 0 ? 'Admin credit' : 'Admin debit') . " by #{$admin_id}";
                $type = $amt >= 0 ? 'credit' : 'debit';
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, payment_method, gateway, description, status, completed_at) VALUES (?, ?, ?, 'admin', 'manual', ?, 'completed', CURRENT_TIMESTAMP)");
                $aabs = abs($amt);
                $stmt->bind_param("idss", $uid, $aabs, $type, $desc); $stmt->execute();
                $conn->commit();
                logActivity($admin_id, 'admin_balance_adjust', "User #{$uid} {$amt} TZS");
                $reply(true, 'Salio limesasishwa.');
                break;

            case 'set_role':
                $uid = (int)($in['user_id'] ?? 0);
                $role = in_array($in['role'] ?? '', ['user','admin','moderator']) ? $in['role'] : 'user';
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->bind_param("si", $role, $uid); $stmt->execute();
                logActivity($admin_id, 'admin_set_role', "User #{$uid} -> {$role}");
                $reply(true, 'Role imebadilishwa.');
                break;

            case 'set_status':
                $uid = (int)($in['user_id'] ?? 0);
                $status = in_array($in['status'] ?? '', ['active','suspended','banned']) ? $in['status'] : 'active';
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $uid); $stmt->execute();
                logActivity($admin_id, 'admin_set_status', "User #{$uid} -> {$status}");
                $reply(true, 'Status imebadilishwa.');
                break;

            case 'delete_user':
                $uid = (int)($in['user_id'] ?? 0);
                if ($uid === (int)$admin_id) $reply(false, 'Huwezi kufuta akaunti yako mwenyewe.');
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $uid); $stmt->execute();
                logActivity($admin_id, 'admin_delete_user', "User #{$uid}");
                $reply(true, 'Mtumiaji amefutwa.');
                break;

            case 'update_order_status':
                $oid = (int)($in['order_id'] ?? 0);
                $status = trim($in['status'] ?? '');
                if ($oid <= 0 || $status === '') $reply(false, 'Data pungufu.');
                $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("si", $status, $oid); $stmt->execute();
                logActivity($admin_id, 'admin_order_status', "Order #{$oid} -> {$status}");
                $reply(true, 'Order status imesasishwa.');
                break;

            case 'set_refill_status':
                $oid = (int)($in['order_id'] ?? 0);
                $rs  = in_array($in['refill_status'] ?? '', ['requested','processing','done','rejected']) ? $in['refill_status'] : 'requested';
                if ($oid <= 0) $reply(false, 'Order haijatambulika.');
                // Clear the requested flag once resolved so it leaves the queue.
                $cleared = in_array($rs, ['done','rejected']) ? 0 : 1;
                $stmt = $conn->prepare("UPDATE orders SET refill_status = ?, refill_requested = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("sii", $rs, $cleared, $oid); $stmt->execute();
                logActivity($admin_id, 'admin_refill_status', "Order #{$oid} -> {$rs}");
                $reply(true, 'Refill status imesasishwa.');
                break;

            case 'send_notification':
                $target = in_array($in['target'] ?? '', ['user', 'broadcast', 'admin'], true) ? $in['target'] : 'user';
                $userId = null;
                if ($target === 'user') {
                    $userId = (int)($in['user_id'] ?? 0);
                    if ($userId <= 0) {
                        $reply(false, 'Chagua mtumiaji sahihi.');
                    }
                    $userRow = $conn->prepare("SELECT id FROM users WHERE id = ?");
                    $userRow->bind_param("i", $userId);
                    $userRow->execute();
                    if (!$userRow->get_result()->fetch_assoc()) {
                        $reply(false, 'Mtumiaji haapatikani.');
                    }
                }
                $title = trim((string)($in['title'] ?? ''));
                $message = trim((string)($in['message'] ?? ''));
                $type = in_array($in['type'] ?? '', ['info', 'success', 'warning', 'danger'], true) ? $in['type'] : 'info';
                if ($title === '' || $message === '') {
                    $reply(false, 'Jaza kichwa na ujumbe.');
                }
                if (!createNotification($userId, $title, $message, $type, $target, ['sender' => $admin_id])) {
                    $reply(false, 'Haikuweza kutuma notisi.');
                }
                logActivity($admin_id, 'admin_send_notification', "Sent notification to {$target} user_id={$userId}");
                $reply(true, 'Notification imetumwa.');
                break;

            default:
                $reply(false, 'Kitendo hakijulikani.');
        }
    } catch (Exception $e) {
        if ($conn) { @$conn->rollback(); }
        $reply(false, 'Kosa: ' . $e->getMessage());
    }
}

/* ============================================================
 * DATA
 * ============================================================ */
$users  = $conn->query("SELECT id, username, email, phone, balance, role, status, created_at FROM users ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$orders = $conn->query("SELECT o.id, o.service_name, o.platform, o.quantity, o.price, o.status, o.external_order_id, o.link, o.created_at, u.username
                        FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);

$totalUsers = count($users);
$sumBalance = (float)($conn->query("SELECT COALESCE(SUM(balance),0) t FROM users")->fetch_assoc()['t'] ?? 0);

$totalOrders = (int)($conn->query("SELECT COUNT(*) c FROM orders")->fetch_assoc()['c'] ?? 0);
$sales = (float)($conn->query("SELECT COALESCE(SUM(price),0) t FROM orders")->fetch_assoc()['t'] ?? 0);

$statusCounts = ['completed'=>0,'pending'=>0,'failed'=>0];
foreach ($conn->query("SELECT status, COUNT(*) c FROM orders GROUP BY status")->fetch_all(MYSQLI_ASSOC) as $r) {
    $s = strtolower($r['status']);
    if (strpos($s,'complet')!==false) $statusCounts['completed'] += (int)$r['c'];
    elseif (strpos($s,'pend')!==false || strpos($s,'process')!==false) $statusCounts['pending'] += (int)$r['c'];
    elseif (strpos($s,'cancel')!==false || strpos($s,'fail')!==false) $statusCounts['failed'] += (int)$r['c'];
}

// Platform breakdown
$platRows = $conn->query("SELECT platform, COUNT(*) c FROM orders GROUP BY platform ORDER BY c DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

// Pending refill requests
$refills = $conn->query("SELECT o.id, o.service_name, o.quantity, o.link, o.refill_status, o.refill_requested_at, u.username
                         FROM orders o LEFT JOIN users u ON o.user_id = u.id
                         WHERE o.refill_requested = 1 ORDER BY o.refill_requested_at DESC")->fetch_all(MYSQLI_ASSOC);

$notifications = getNotifications(60);
$unreadNotifications = getUnreadNotificationCount($admin_id);

// 7-day order trend
$trend = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $trend[$d] = 0;
}
$dexpr = db_date_expr('created_at');
foreach ($conn->query("SELECT {$dexpr} d, COUNT(*) c FROM orders GROUP BY {$dexpr}")->fetch_all(MYSQLI_ASSOC) as $r) {
    if (isset($trend[$r['d']])) $trend[$r['d']] = (int)$r['c'];
}

// Live provider balances (cached ~2 min so the dashboard stays snappy / resilient)
$providerBalances = [];
$providerList = json_decode(defined('SMM_PROVIDERS') ? SMM_PROVIDERS : '[]', true) ?: ['boost', 'fastway'];
$pbCache = __DIR__ . '/data/cache/provider_balance.json';
$pbCacheData = null;
if (is_file($pbCache) && (time() - filemtime($pbCache) < 120)) {
    $pbCacheData = json_decode(file_get_contents($pbCache), true);
}

try {
    require_once 'includes/APIHandler.php';
    foreach ($providerList as $provider) {
        $providerName = is_string($provider) ? strtolower(trim($provider)) : '';
        if ($providerName === '') continue;

        $cachedBalance = null;
        if (is_array($pbCacheData) && isset($pbCacheData[$providerName])) {
            $cachedBalance = $pbCacheData[$providerName];
        }

        if ($cachedBalance !== null) {
            $providerBalances[$providerName] = (float)$cachedBalance;
            continue;
        }

        $providerBalances[$providerName] = (float)(new APIHandler($providerName))->getBalance('TZS');
    }

    @file_put_contents($pbCache, json_encode($providerBalances));
} catch (Exception $e) {
    if (is_array($pbCacheData)) {
        foreach ($pbCacheData as $providerName => $balance) {
            $providerBalances[$providerName] = (float)$balance;
        }
    }
}

function abadge($status) {
    $s = strtolower($status);
    if (strpos($s,'complet')!==false) return 'badge-success';
    if (strpos($s,'pend')!==false || strpos($s,'process')!==false) return 'badge-warning';
    if (strpos($s,'cancel')!==false || strpos($s,'fail')!==false) return 'badge-danger';
    return 'badge-secondary';
}
function ubadge($status) {
    return $status==='active' ? 'badge-success' : ($status==='suspended' ? 'badge-warning' : 'badge-danger');
}

$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
ui_head(APP_NAME . ' — Admin', 'admin', $extraHead);
?>
<style>
body.admin{background:#f4f7fe;}
.sidebar{width:250px;background:linear-gradient(180deg,#1b1f3b,#141830);color:#fff;position:fixed;height:100vh;padding:22px 18px;z-index:1000;transition:.3s;}
.sidebar-brand{font-size:1.35rem;font-weight:800;color:#fff;text-align:center;margin-bottom:28px;display:block;}
.sidebar-brand span{color:var(--accent);}
.sidebar a.nav-link{color:#aab1d6;display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:13px;margin-bottom:6px;font-weight:600;font-size:.92rem;}
.sidebar a.nav-link i{font-size:1.15rem;}
.sidebar a.nav-link:hover,.sidebar a.nav-link.active{background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;}
.main{margin-left:250px;padding:26px;}
.mobile-top{display:none;background:#fff;padding:14px 18px;border-radius:14px;box-shadow:0 4px 18px rgba(43,54,116,.06);margin-bottom:18px;align-items:center;justify-content:space-between;}
.stat-card{background:#fff;border-radius:18px;padding:1.2rem;box-shadow:0 8px 26px rgba(43,54,116,.05);height:100%;}
.stat-card .ico{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;}
.stat-card .lbl{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;font-weight:600;}
.stat-card .val{font-size:1.55rem;font-weight:800;color:var(--ink);}
.panel{background:#fff;border-radius:20px;padding:1.3rem;box-shadow:0 8px 26px rgba(43,54,116,.05);}
.table thead th{background:#f6f8ff;color:var(--ink);font-weight:700;font-size:.78rem;text-transform:uppercase;letter-spacing:.4px;border:none;padding:12px;}
.table tbody td{padding:12px;vertical-align:middle;border-bottom:1px solid #f1f3fa;font-size:.88rem;}
.btn-ico{border:none;background:#f0f2fb;color:var(--ink);width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;}
.btn-ico:hover{background:var(--primary);color:#fff;}
.search-box{border:1.5px solid var(--line);border-radius:12px;padding:.5rem .9rem;background:#fafbff;font-size:.9rem;}
@media(max-width:991px){.sidebar{transform:translateX(-100%);}.sidebar.show{transform:translateX(0);}.main{margin-left:0;padding:14px;}.mobile-top{display:flex;}}
</style>

<div class="sidebar" id="sidebar">
    <div class="sidebar-brand"><i class="bi bi-gem"></i> <?= APP_NAME ?> <span>Admin</span></div>
    <a href="#overview" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="#users" class="nav-link"><i class="bi bi-people"></i> Users</a>
    <a href="#orders" class="nav-link"><i class="bi bi-bag-check"></i> Orders</a>
    <a href="#refills" class="nav-link"><i class="bi bi-arrow-repeat"></i> Refills<?= count($refills) ? ' <span class="badge bg-danger rounded-pill ms-1">'.count($refills).'</span>' : '' ?></a>
    <a href="notifications.php" class="nav-link"><i class="bi bi-bell"></i> Notifications<?= $unreadNotifications ? ' <span class="badge bg-danger rounded-pill ms-1">'.number_format($unreadNotifications).'</span>' : '' ?></a>
    <a href="api-docs.php" class="nav-link"><i class="bi bi-file-code"></i> API Docs</a>
    <a href="index.php" class="nav-link"><i class="bi bi-box-arrow-up-right"></i> User Site</a>
    <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main">
    <div class="mobile-top">
        <h5 class="mb-0 fw-bold"><i class="bi bi-gem text-primary"></i> <?= APP_NAME ?> Admin</h5>
        <button class="btn btn-light" onclick="document.getElementById('sidebar').classList.toggle('show')"><i class="bi bi-list"></i></button>
    </div>

    <h4 class="fw-bold mb-1" id="overview">Dashboard</h4>
    <p class="text-muted mb-4">Karibu, <?= htmlspecialchars($_SESSION['username']) ?> 👋</p>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6"><div class="stat-card d-flex justify-content-between align-items-center"><div><div class="lbl">Total Users</div><div class="val"><?= number_format($totalUsers) ?></div></div><div class="ico" style="background:#eef0ff;color:var(--primary)"><i class="bi bi-people"></i></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="stat-card d-flex justify-content-between align-items-center"><div><div class="lbl">Total Orders</div><div class="val"><?= number_format($totalOrders) ?></div></div><div class="ico" style="background:#e6fbfa;color:#00a8a3"><i class="bi bi-bag-check"></i></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="stat-card d-flex justify-content-between align-items-center"><div><div class="lbl">Total Sales</div><div class="val"><?= number_format($sales) ?> <small class="text-muted" style="font-size:.9rem;">TZS</small></div></div><div class="ico" style="background:#e4faf3;color:#00876a"><i class="bi bi-graph-up-arrow"></i></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="stat-card d-flex justify-content-between align-items-center"><div><div class="lbl">Provider Balances</div><div class="val" style="font-size:1rem;line-height:1.4;">
            <?php if (!empty($providerBalances)): ?>
                <?php foreach ($providerBalances as $providerName => $balance): ?>
                    <div><?= htmlspecialchars(strtoupper($providerName)) ?>: <?= number_format($balance) ?> <small class="text-muted">TZS</small></div>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="text-danger" style="font-size:1rem;">offline</span>
            <?php endif; ?>
        </div></div><div class="ico" style="background:#fdeee9;color:#c0392b"><i class="bi bi-cloud-check"></i></div></div></div>
    </div>

    <!-- Charts + liability -->
    <div class="row g-3 mb-4">
        <div class="col-lg-5"><div class="panel"><h6 class="fw-bold mb-3">Order Status</h6><div style="height:230px;"><canvas id="statusChart"></canvas></div></div></div>
        <div class="col-lg-7"><div class="panel"><h6 class="fw-bold mb-3">Orders (Siku 7 zilizopita)</h6><div style="height:230px;"><canvas id="trendChart"></canvas></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="stat-card"><div class="lbl">User Liability (jumla ya salio)</div><div class="val text-primary"><?= number_format($sumBalance) ?> <small class="text-muted" style="font-size:.9rem;">TZS</small></div></div></div>
        <div class="col-md-4"><div class="stat-card"><div class="lbl">Completed Orders</div><div class="val text-success"><?= number_format($statusCounts['completed']) ?></div></div></div>
        <div class="col-md-4"><div class="stat-card"><div class="lbl">Pending Orders</div><div class="val" style="color:#b8860b"><?= number_format($statusCounts['pending']) ?></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-send-fill text-primary"></i> Tuma Notisi kwa Wateja</h5>
                    <span class="badge-soft badge-secondary">Admin Composer</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Mpokeaji</label>
                        <select class="form-select" id="notificationTarget">
                            <option value="user">Mteja mmoja</option>
                            <option value="broadcast">Wote</option>
                            <option value="admin">Wafanyakazi/Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="userSelectWrap">
                        <label class="form-label">Chagua mtumiaji</label>
                        <select class="form-select" id="notificationUserId">
                            <option value="">-- Chagua --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Aina</label>
                        <select class="form-select" id="notificationType">
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="danger">Danger</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Kichwa</label>
                        <input type="text" class="form-control" id="notificationTitle" placeholder="Mfano: Update ya mfumo">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Ujumbe</label>
                    <textarea class="form-control" id="notificationMessage" rows="4" placeholder="Andika ujumbe wa notisi..."></textarea>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Notisi zitatumwa moja kwa moja kwenye mfumo wa ndani na zinaweza kuonekana kwenye ukurasa wa notisi.</small>
                    <button class="btn-grad" style="width:auto;padding:.65rem 1.2rem;" onclick="sendNotification()"><i class="bi bi-send"></i> Tuma</button>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-bell-fill text-warning"></i> Notisi za hivi karibuni</h5>
                    <span class="badge-soft badge-warning"><?= number_format(count($notifications)) ?> total</span>
                </div>
                <div class="d-flex flex-column gap-2">
                    <?php foreach (array_slice($notifications, 0, 8) as $n): ?>
                        <div class="border rounded-4 p-2" style="background:#fbfcff;">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold small"><?= htmlspecialchars($n['title'] ?? 'Notification') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars(mb_substr($n['message'] ?? '',0,90)) ?></div>
                                </div>
                                <span class="badge-soft badge-<?= htmlspecialchars($n['type'] ?? 'info') ?>"><?= htmlspecialchars($n['type'] ?? 'info') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Refill requests -->
    <div class="panel mb-4" id="refills">
        <h5 class="fw-bold mb-3"><i class="bi bi-arrow-repeat text-primary"></i> Refill Requests (<?= count($refills) ?>)</h5>
        <?php if (empty($refills)): ?>
            <p class="text-muted mb-0" style="font-size:.9rem;">Hakuna maombi ya refill kwa sasa.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Order</th><th>User</th><th>Service</th><th>Link</th><th>Status</th><th>Vitendo</th></tr></thead>
                <tbody>
                <?php foreach ($refills as $r): ?>
                    <tr>
                        <td class="text-muted">#<?= $r['id'] ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($r['username'] ?? '—') ?></td>
                        <td><?= htmlspecialchars(mb_substr($r['service_name'],0,32)) ?></td>
                        <td class="small text-truncate" style="max-width:160px;"><a href="<?= htmlspecialchars($r['link']) ?>" target="_blank"><?= htmlspecialchars(mb_substr($r['link'],0,30)) ?></a></td>
                        <td><span class="badge-soft badge-warning"><?= htmlspecialchars($r['refill_status'] ?: 'requested') ?></span></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm" style="background:#e8efff;color:#2f54eb;border:none;border-radius:10px;font-size:.72rem;" onclick="setRefill(<?= (int)$r['id'] ?>,'processing')">Processing</button>
                            <button class="btn btn-sm" style="background:#e4faf3;color:#00876a;border:none;border-radius:10px;font-size:.72rem;" onclick="setRefill(<?= (int)$r['id'] ?>,'done')">Done</button>
                            <button class="btn btn-sm" style="background:#fdeee9;color:#c0392b;border:none;border-radius:10px;font-size:.72rem;" onclick="setRefill(<?= (int)$r['id'] ?>,'rejected')">Reject</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Users -->
    <div class="panel mb-4" id="users">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="fw-bold mb-0">Users (<?= $totalUsers ?>)</h5>
            <input type="text" class="search-box" id="userSearch" placeholder="🔍 Tafuta user...">
        </div>
        <div class="table-responsive">
            <table class="table align-middle" id="userTable">
                <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Balance</th><th>Role</th><th>Status</th><th>Vitendo</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="text-muted">#<?= $u['id'] ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td class="fw-bold text-success"><?= number_format($u['balance']) ?></td>
                        <td><span class="badge-soft badge-secondary text-uppercase"><?= htmlspecialchars($u['role']) ?></span></td>
                        <td><span class="badge-soft <?= ubadge($u['status']) ?>"><?= htmlspecialchars($u['status']) ?></span></td>
                        <td class="text-nowrap">
                            <button class="btn-ico" title="Salio" onclick='openBalance(<?= (int)$u['id'] ?>,"<?= htmlspecialchars(addslashes($u['username'])) ?>")'><i class="bi bi-cash-coin"></i></button>
                            <button class="btn-ico" title="Hariri" onclick='openManage(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'><i class="bi bi-gear"></i></button>
                            <button class="btn-ico" title="Futa" onclick='delUser(<?= (int)$u['id'] ?>,"<?= htmlspecialchars(addslashes($u['username'])) ?>")'><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Orders -->
    <div class="panel" id="orders">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="fw-bold mb-0">Orders (latest <?= count($orders) ?>)</h5>
            <input type="text" class="search-box" id="orderSearch" placeholder="🔍 Tafuta order...">
        </div>
        <div class="table-responsive">
            <table class="table align-middle" id="orderTable">
                <thead><tr><th>ID</th><th>User</th><th>Service</th><th>Qty</th><th>Price</th><th>Status</th><th>Ref</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td class="text-muted">#<?= $o['id'] ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($o['username'] ?? '—') ?></td>
                        <td><?= htmlspecialchars(mb_substr($o['service_name'],0,38)) ?></td>
                        <td><?= number_format($o['quantity']) ?></td>
                        <td class="fw-bold"><?= number_format($o['price']) ?></td>
                        <td><span class="badge-soft <?= abadge($o['status']) ?>"><?= htmlspecialchars($o['status']) ?></span></td>
                        <td class="text-muted small"><?= htmlspecialchars($o['external_order_id'] ?: '—') ?></td>
                        <td class="text-muted small"><?= date('M d', strtotime($o['created_at'])) ?></td>
                        <td><button class="btn-ico" title="Badili status" onclick='openOrder(<?= (int)$o['id'] ?>,"<?= htmlspecialchars(addslashes($o['status'])) ?>")'><i class="bi bi-pencil"></i></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Balance modal -->
<div class="modal fade" id="balanceModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0">
  <div class="modal-header border-0"><h5 class="modal-title fw-bold">Rekebisha Salio</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="text-muted small mb-2">User: <strong id="balUser"></strong></p>
    <label class="form-label">Kiasi (TZS) — tumia minus (-) kutoa</label>
    <input type="number" class="form-control" id="balAmount" placeholder="mfano: 5000 au -2000">
  </div>
  <div class="modal-footer border-0"><button class="btn-grad" style="width:auto;padding:.6rem 1.4rem;" onclick="saveBalance()"><i class="bi bi-check2"></i> Hifadhi</button></div>
</div></div></div>

<!-- Manage user modal -->
<div class="modal fade" id="manageModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0">
  <div class="modal-header border-0"><h5 class="modal-title fw-bold">Simamia Mtumiaji</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="text-muted small mb-3">User: <strong id="mgUser"></strong></p>
    <label class="form-label">Role</label>
    <select class="form-select mb-3" id="mgRole"><option value="user">user</option><option value="moderator">moderator</option><option value="admin">admin</option></select>
    <label class="form-label">Status</label>
    <select class="form-select" id="mgStatus"><option value="active">active</option><option value="suspended">suspended</option><option value="banned">banned</option></select>
  </div>
  <div class="modal-footer border-0"><button class="btn-grad" style="width:auto;padding:.6rem 1.4rem;" onclick="saveManage()"><i class="bi bi-check2"></i> Hifadhi</button></div>
</div></div></div>

<!-- Order status modal -->
<div class="modal fade" id="orderModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0">
  <div class="modal-header border-0"><h5 class="modal-title fw-bold">Badili Order Status</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label">Status mpya</label>
    <select class="form-select" id="orStatus">
      <option>Pending</option><option>Processing</option><option>Completed</option><option>Partial</option><option>Canceled</option><option>Failed</option>
    </select>
  </div>
  <div class="modal-footer border-0"><button class="btn-grad" style="width:auto;padding:.6rem 1.4rem;" onclick="saveOrder()"><i class="bi bi-check2"></i> Hifadhi</button></div>
</div></div></div>

<?php
$script = '<script>'
. 'const statusData=' . json_encode(array_values($statusCounts)) . ';'
. 'const trendLabels=' . json_encode(array_map(fn($d)=>date('D', strtotime($d)), array_keys($trend))) . ';'
. 'const trendData=' . json_encode(array_values($trend)) . ';'
. <<<'JS'
// Charts
new Chart(document.getElementById('statusChart'),{type:'doughnut',data:{labels:['Completed','Pending','Failed'],datasets:[{data:statusData,backgroundColor:['#00b894','#fdcb6e','#e17055'],borderWidth:0,hoverOffset:6}]},options:{plugins:{legend:{position:'bottom',labels:{usePointStyle:true,font:{family:'Poppins'}}}},cutout:'70%',maintainAspectRatio:false}});
new Chart(document.getElementById('trendChart'),{type:'bar',data:{labels:trendLabels,datasets:[{label:'Orders',data:trendData,backgroundColor:'#6c5ce7',borderRadius:8,maxBarThickness:34}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}},maintainAspectRatio:false}});

// Table search
function wireSearch(inputId, tableId){
  document.getElementById(inputId).addEventListener('input', function(){
    const q=this.value.toLowerCase();
    document.querySelectorAll('#'+tableId+' tbody tr').forEach(tr=>{
      tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}
wireSearch('userSearch','userTable');
wireSearch('orderSearch','orderTable');

// Sidebar nav active
document.querySelectorAll('.sidebar a[href^="#"]').forEach(a=>a.addEventListener('click',()=>{
  document.querySelectorAll('.sidebar .nav-link').forEach(x=>x.classList.remove('active'));a.classList.add('active');
}));

async function postAction(payload){
  const r=await fetch('admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  return r.json();
}

// Balance
let balId=null;
function openBalance(id,name){balId=id;document.getElementById('balUser').textContent=name;document.getElementById('balAmount').value='';new bootstrap.Modal('#balanceModal').show();}
async function saveBalance(){
  const amount=parseFloat(document.getElementById('balAmount').value);
  if(!amount){return toast('Weka kiasi.','warning');}
  const j=await postAction({action:'add_balance',user_id:balId,amount});
  toast(j.message, j.success?'success':'danger');
  if(j.success) setTimeout(()=>location.reload(),900);
}

// Manage
let mgId=null;
function openManage(u){mgId=u.id;document.getElementById('mgUser').textContent=u.username;document.getElementById('mgRole').value=u.role;document.getElementById('mgStatus').value=u.status;new bootstrap.Modal('#manageModal').show();}
async function saveManage(){
  const role=document.getElementById('mgRole').value, status=document.getElementById('mgStatus').value;
  const r1=await postAction({action:'set_role',user_id:mgId,role});
  const r2=await postAction({action:'set_status',user_id:mgId,status});
  toast((r1.success&&r2.success)?'Imehifadhiwa.':'Baadhi yameshindikana.', (r1.success&&r2.success)?'success':'danger');
  setTimeout(()=>location.reload(),900);
}

// Delete
async function delUser(id,name){
  if(!confirm('Futa mtumiaji "'+name+'"? Hii itafuta na orders zake zote.'))return;
  const j=await postAction({action:'delete_user',user_id:id});
  toast(j.message, j.success?'success':'danger');
  if(j.success) setTimeout(()=>location.reload(),900);
}

// Order status
let orId=null;
function openOrder(id,status){orId=id;document.getElementById('orStatus').value=status;new bootstrap.Modal('#orderModal').show();}
async function saveOrder(){
  const status=document.getElementById('orStatus').value;
  const j=await postAction({action:'update_order_status',order_id:orId,status});
  toast(j.message, j.success?'success':'danger');
  if(j.success) setTimeout(()=>location.reload(),900);
}

// Refill requests
async function setRefill(id,refill_status){
  const j=await postAction({action:'set_refill_status',order_id:id,refill_status});
  toast(j.message, j.success?'success':'danger');
  if(j.success) setTimeout(()=>location.reload(),900);
}

async function sendNotification(){
  const target=document.getElementById('notificationTarget').value;
  const userId=document.getElementById('notificationUserId').value;
  const type=document.getElementById('notificationType').value;
  const title=document.getElementById('notificationTitle').value.trim();
  const message=document.getElementById('notificationMessage').value.trim();
  if(!title || !message){ return toast('Jaza kichwa na ujumbe wa notisi.','warning'); }
  if(target==='user' && !userId){ return toast('Chagua mtumiaji wa kutuma notisi.','warning'); }
  const j=await postAction({action:'send_notification',target,user_id:userId,title,message,type});
  toast(j.message, j.success?'success':'danger');
  if(j.success){ document.getElementById('notificationTitle').value=''; document.getElementById('notificationMessage').value=''; }
}

const targetSelect=document.getElementById('notificationTarget');
const userSelectWrap=document.getElementById('userSelectWrap');
function toggleUserSelector(){
  if(!userSelectWrap) return;
  userSelectWrap.style.display = targetSelect.value === 'user' ? '' : 'none';
}
targetSelect.addEventListener('change', toggleUserSelector);
toggleUserSelector();
JS
. '</script>';

ui_foot($script);
