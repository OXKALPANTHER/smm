<?php
require_once 'config.php';
require_once 'includes/ui.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!rate_limit('register', 5, 900)) {
        $error = 'Majaribio mengi sana. Subiri dakika chache kisha ujaribu tena.';
    }
    if ($error === '' && !captcha_is_valid($_POST['cf-turnstile-response'] ?? '', $_POST['website'] ?? '')) {
        $error = 'Thibitisha kuwa wewe si roboti kisha ujaribu tena.';
    }
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $phone = $phone ? $phone : null;  // Convert empty string to NULL
    $password = $_POST['password'] ?? '';

    if ($error !== '') {
        // Rate limit message is already set.
    } elseif ($username === '' || $email === '' || $password === '') {
        $error = 'Tafadhali jaza taarifa zote.';
    } elseif (!validateEmail($email)) {
        $error = 'Barua pepe si sahihi.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Neno siri liwe na herufi angalau ' . PASSWORD_MIN_LENGTH . '.';
    } else {
        // Duplicate check
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $error = 'Jina au email tayari limetumika.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // Generate unique referral code: use time-based + random to avoid collisions
            $ref = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $username), 0, 3)) . substr(time(), -4) . rand(1000, 9999);

            // Resolve the inviter from a referral code (?ref= or the form field).
            $refInput = trim($_POST['ref'] ?? '');
            if ($refInput === '') {
                $refInput = 'ROYA886';
            }
            $referredBy = null;
            if ($refInput !== '') {
                $rs = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
                $rs->bind_param("s", $refInput);
                $rs->execute();
                $rr = $rs->get_result()->fetch_assoc();
                if ($rr)
                    $referredBy = (int) $rr['id'];
            }

            // Build insert: phone can be NULL (already converted above)
            $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $username, $email, $phone, $hash, $ref, $referredBy);

            if ($stmt->execute()) {
                $_SESSION['user_id'] = $conn->insert_id();
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'user';
                createNotification(
                    $_SESSION['user_id'],
                    'Karibu kwa ' . APP_NAME,
                    'Akaunti yako imeundwa kwa mafanikio. Karibu kwenye jukwaa letu ambapo unaweza kuagiza huduma, kuongeza salio, na kufuatilia maagizo yako kwa urahisi.',
                    'success',
                    'user',
                    ['source' => 'registration']
                );
                sendWelcomeEmail($email, $username);
                header("Location: index.php");
                exit;
            } else {
                // Log the actual error for debugging
                error_log("Registration error for user $username: " . $stmt->error);
                $error = 'Usajili umeshindikana. Jaribu tena.';
            }
        }
    }
}

ui_head('Jisajili — ' . APP_NAME, 'auth register-page', '<meta name="theme-color" content="#151e3b"><link rel="stylesheet" href="assets/login.css">');
?>
<main class="auth-shell">
    <section class="auth-story" aria-labelledby="registerStoryHeadline">
        <img class="auth-story-art" src="assets/og-image.png" alt="Royal Panel huduma za mitandao ya kijamii">
        <div class="auth-story-copy">
            <p class="auth-kicker">Karibu Royal SMM</p>
            <h1 id="registerStoryHeadline">Anza safari yako hapa.</h1>
            <p>Unda akaunti yako ili kuvinjari huduma, kuweka oda na kufuatilia maendeleo kutoka sehemu moja.</p>
        </div>
        <div class="auth-story-footer"><span aria-hidden="true"></span> Akaunti moja. Oda na taarifa zako zote.</div>
    </section>

    <section class="auth-panel" aria-labelledby="registerHeadline">
        <div class="auth-panel-inner">
            <p class="auth-kicker">Tengeneza akaunti</p>
            <h2 id="registerHeadline">Jiunge nasi</h2>
            <p class="auth-lede">Jaza taarifa zako ili kuanza kutumia dashboard yako.</p>

            <?php if ($error): ?>
                <div class="login-error" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php
            $refPrefill = trim($_POST['ref'] ?? $_GET['ref'] ?? '');
            if ($refPrefill === '') $refPrefill = 'ROYA886';
            ?>
            <form method="post" class="register-form">
                <?= csrf_field() ?>
                <div class="register-field">
                    <label for="username">Jina la mtumiaji</label>
                    <input type="text" id="username" name="username" placeholder="Mfano: Amina" value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required>
                </div>
                <div class="register-field">
                    <label for="email">Barua pepe</label>
                    <input type="email" id="email" name="email" placeholder="jina@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" required>
                </div>
                <div class="register-field">
                    <label for="phone">Namba ya simu <span class="register-hint">(hiari)</span></label>
                    <input type="tel" id="phone" name="phone" placeholder="07XXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="tel" inputmode="tel">
                </div>
                <div class="register-field">
                    <label for="password">Neno siri</label>
                    <div class="login-password-control">
                        <input type="password" id="password" name="password" placeholder="Unda neno siri" autocomplete="new-password" minlength="<?= (int) PASSWORD_MIN_LENGTH ?>" required>
                        <button type="button" class="password-toggle" id="passwordToggle" aria-label="Onyesha neno siri" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                    <p class="register-hint">Angalau <?= (int) PASSWORD_MIN_LENGTH ?> herufi.</p>
                </div>
                <div class="register-field register-field-wide">
                    <label for="ref">Msimbo wa rufaa <span class="register-hint">(hiari)</span></label>
                    <input type="text" id="ref" name="ref" placeholder="ROYA886" value="<?= htmlspecialchars($refPrefill, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                </div>
                <?php captcha_widget(); ?>
                <button type="submit" class="login-submit"><span>Tengeneza akaunti</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </form>

            <p class="login-signup">Tayari una akaunti? <a href="login.php">Ingia</a></p>
        </div>
    </section>
</main>
<script>
document.getElementById('passwordToggle').addEventListener('click', function () {
    const password = document.getElementById('password');
    const showing = password.type === 'password';
    password.type = showing ? 'text' : 'password';
    this.setAttribute('aria-pressed', showing ? 'true' : 'false');
    this.setAttribute('aria-label', showing ? 'Ficha neno siri' : 'Onyesha neno siri');
    this.querySelector('i').className = showing ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>
<?php ui_foot();
