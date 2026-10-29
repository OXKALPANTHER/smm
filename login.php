<?php
require_once 'config.php';
require_once 'includes/ui.php';

if (isLoggedIn()) { header("Location: index.php"); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!rate_limit('login', 8, 900)) {
        $error = 'Majaribio mengi sana. Subiri dakika chache kisha ujaribu tena.';
    }
    if ($error === '' && !captcha_is_valid($_POST['cf-turnstile-response'] ?? '', $_POST['website'] ?? '')) {
        $error = 'Thibitisha kuwa wewe si roboti kisha ujaribu tena.';
    }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($error !== '') {
        $stmt = null;
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $conn->query("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = " . (int)$user['id']);
            $destination = in_array($user['role'], ['admin', 'moderator'], true) ? 'moderator.php' : 'index.php';
            if ($user['role'] === 'admin') {
                $destination = 'admin.php';
            }
            header("Location: " . $destination);
            exit;
        }
        $error = "Jina la mtumiaji au neno siri si sahihi.";
    }
}

ui_head('Sign in — ' . APP_NAME, 'auth', '<meta name="theme-color" content="#151e3b"><link rel="stylesheet" href="assets/login.css">');
?>
<main class="auth-shell" id="mainContent">
    <section class="auth-story" aria-labelledby="storyHeadline">
        <img class="auth-story-art" src="assets/og-image.png" alt="Royal Panel social media marketing">
        <div class="auth-story-copy">
            <p class="auth-kicker">Your creator workspace</p>
            <h1 id="storyHeadline">Good to see you again.</h1>
            <p>Pick up where you left off. Manage campaigns, follow order progress, and keep your account moving from one focused workspace.</p>
        </div>
        <div class="auth-story-footer"><span aria-hidden="true"></span> Orders, balance, and updates in one place</div>
    </section>

    <section class="auth-panel" aria-labelledby="loginHeadline">
        <div class="auth-panel-inner">
            <p class="auth-kicker">Royal SMM account</p>
            <h2 id="loginHeadline">Sign in</h2>
            <p class="auth-lede">Enter your account details to continue to your dashboard.</p>

            <?php if ($error): ?>
                <div class="login-error" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" class="login-form">
                <?= csrf_field() ?>
                <div class="login-field">
                    <label for="username">Username or email</label>
                    <input type="text" id="username" name="username" placeholder="you@example.com" autocomplete="username" required autofocus>
                </div>
                <div class="login-field">
                    <label for="password">Password</label>
                    <div class="login-password-control">
                        <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <?php captcha_widget(); ?>
                <button type="submit" class="login-submit"><span>Continue to dashboard</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </form>

            <p class="login-form-note"><i class="bi bi-shield-lock" aria-hidden="true"></i> Your sign-in details stay protected.</p>
            <p class="login-signup">New to Royal SMM? <a href="register.php">Create an account</a></p>
        </div>
    </section>
</main>
<script>
document.getElementById('passwordToggle').addEventListener('click', function () {
        const password = document.getElementById('password');
        const showing = password.type === 'password';
        password.type = showing ? 'text' : 'password';
        this.setAttribute('aria-pressed', showing ? 'true' : 'false');
        this.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
        this.querySelector('i').className = showing ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>
<?php ui_foot(); ?>
