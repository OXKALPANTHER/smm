<?php
require_once 'config.php';
require_once 'includes/ui.php';

$success = false;
$message = 'Link hii ya uthibitisho si sahihi au imekwisha muda.';
$token = trim((string) ($_GET['token'] ?? ''));
if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare('SELECT ev.id, ev.user_id, u.username, u.email FROM email_verifications ev JOIN users u ON u.id = ev.user_id WHERE ev.token_hash = ? AND ev.verified_at IS NULL AND ev.expires_at > CURRENT_TIMESTAMP LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare('UPDATE email_verifications SET verified_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $row['id']]);
        $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND id <> ?')->execute([(int) $row['user_id'], (int) $row['id']]);
        createNotification((int) $row['user_id'], 'Email imethibitishwa', 'Email yako imethibitishwa kwa mafanikio.', 'success', 'user', ['source' => 'email_verification']);
        sendWelcomeEmail($row['email'], $row['username']);
        $success = true;
        $message = 'Email yako imethibitishwa kwa mafanikio. Sasa unaweza kuingia.';
    }
} elseif (!empty($_SESSION['verification_sent'])) {
    $message = $_SESSION['verification_sent']
        ? 'Tumetuma link ya uthibitisho kwenye inbox yako. Fungua email hiyo ili kuamilisha akaunti.'
        : 'Akaunti imehifadhiwa, lakini email haikutumwa. Wasiliana na support ili tukusaidie.';
    unset($_SESSION['verification_sent']);
}
ui_head('Thibitisha Email — ' . APP_NAME, 'auth');
?>
<div class="glass-card text-center">
    <div class="brand-icon"><i class="bi <?= $success ? 'bi-check2-circle' : 'bi-envelope-check' ?>"></i></div>
    <h3 class="fw-bold mb-2" style="color:var(--ink)"><?= $success ? 'Email imethibitishwa' : 'Thibitisha email yako' ?></h3>
    <p class="text-muted mb-4" style="font-size:.9rem;"><?= htmlspecialchars($message) ?></p>
    <?php if ($success): ?>
        <a class="btn-grad" href="login.php"><i class="bi bi-box-arrow-in-right"></i> INGIA SASA</a>
    <?php else: ?>
        <p class="small text-muted mb-0">Angalia pia Spam/Junk. Link inaisha baada ya saa 24.</p>
        <a class="d-inline-block mt-3 fw-semibold" href="register.php" style="color:var(--primary)">Rudi kwenye usajili</a>
    <?php endif; ?>
</div>
<?php ui_foot();
