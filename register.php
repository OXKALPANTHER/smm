<?php
require_once 'config.php';
require_once 'includes/ui.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $phone = $phone ? $phone : null;  // Convert empty string to NULL
    $password = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
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

ui_head('Jisajili — ' . APP_NAME, 'auth');
?>
<div class="glass-card">
    <div class="brand-icon"><?= ui_crown_svg(38) ?></div>
    <h3 class="fw-bold text-center mb-1" style="color:var(--ink)">Tengeneza Akaunti</h3>
    <p class="text-center text-muted mb-4" style="font-size:.9rem;">Jaza taarifa zako hapa chini</p>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-4 border-0 py-2"><i
                class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Jina la mtumiaji</label>
            <input type="text" name="username" class="form-control" placeholder="royal"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Barua pepe</label>
            <input type="email" name="email" class="form-control" placeholder="royal@example.com"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Namba ya simu</label>
            <input type="tel" name="phone" class="form-control" placeholder="07XXXXXXXX"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">Neno siri</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <?php $refPrefill = trim($_POST['ref'] ?? $_GET['ref'] ?? '');
        if ($refPrefill === '') {
            $refPrefill = 'ROYA886';
        } ?>
        <div class="mb-2">
            <label class="form-label">Referral code <span class="text-muted">(hiari, default: ROYA886)</span></label>
            <input type="text" name="ref" class="form-control" placeholder="ROYA886"
                value="<?= htmlspecialchars($refPrefill) ?>">
        </div>
        <button type="submit" class="btn-grad mt-3"><i class="bi bi-check2-circle"></i> JISAJILI SASA</button>
    </form>

    <p class="text-center mt-4 mb-0" style="font-size:.9rem;">
        Tayari una akaunti? <a href="login.php" class="fw-semibold" style="color:var(--primary)">Ingia</a>
    </p>
</div>
<?php ui_foot();
