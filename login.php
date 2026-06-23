<?php
require_once 'config.php';
require_once 'includes/ui.php';

if (isLoggedIn()) { header("Location: index.php"); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        $conn->query("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = " . (int)$user['id']);
        header("Location: " . ($user['role'] === 'admin' ? 'admin.php' : 'index.php'));
        exit;
    }
    $error = "Jina la mtumiaji au neno siri si sahihi.";
}

ui_head('Ingia — ' . APP_NAME, 'auth');
?>
<div class="glass-card">
    <div class="brand-icon"><?= ui_crown_svg(38) ?></div>
    <h3 class="fw-bold text-center mb-1" style="color:var(--ink)"><?= APP_NAME ?></h3>
    <p class="text-center text-muted mb-4" style="font-size:.9rem;">Karibu tena — ingia kwenye akaunti yako</p>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-4 border-0 py-2"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Jina la mtumiaji / Email</label>
            <input type="text" name="username" class="form-control" placeholder="royal" required autofocus>
        </div>
        <div class="mb-2">
            <label class="form-label">Neno siri</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-grad mt-3"><i class="bi bi-box-arrow-in-right"></i> INGIA SASA</button>
    </form>

    <p class="text-center mt-4 mb-0" style="font-size:.9rem;">
        Huna akaunti? <a href="register.php" class="fw-semibold" style="color:var(--primary)">Jisajili</a>
    </p>
</div>
<?php ui_foot();
