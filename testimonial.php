<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ui.php';
requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$flash = $_SESSION['testimonial_flash'] ?? null;
unset($_SESSION['testimonial_flash']);

function testimonialRedirect(string $type, string $message): void
{
    $_SESSION['testimonial_flash'] = ['type' => $type, 'message' => $message];
    header('Location: testimonial.php', true, 303);
    exit;
}

function testimonialLength(string $value): int
{
    if (preg_match_all('/./us', $value, $matches) !== false) return count($matches[0]);
    return strlen($value);
}

function testimonialHasLink(string $value): bool
{
    return (bool) preg_match('~(?:https?://|www\.|\b[\p{L}0-9-]+\.(?:com|net|org|co|io|me|ly|app|dev|info|biz|tz|uk|us)(?:\b|/))~iu', $value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSameOriginRequest();
    require_csrf();
    if (!rate_limit('customer_testimonial_submit', 3, 3600)) {
        testimonialRedirect('danger', 'You can submit up to three testimonials per hour.');
    }

    $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($_POST['display_name'] ?? ''))) ?? '');
    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $messageRaw = trim((string) ($_POST['message'] ?? ''));
    $message = trim(strip_tags($messageRaw));
    $platform = trim(strip_tags((string) ($_POST['platform'] ?? '')));
    $orderId = filter_var($_POST['order_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $consent = isset($_POST['consent_to_publish']) && (string) $_POST['consent_to_publish'] === '1';
    $validPublicName = (bool) preg_match('/^\p{L}[\p{L}\p{M} .\x27-]*\s+\p{L}\.?$/u', $name);

    if (!$validPublicName || testimonialLength($name) < 3 || testimonialLength($name) > 100 || !$rating || testimonialLength($message) < 20 || testimonialLength($message) > 300 || $platform === '' || testimonialHasLink($messageRaw) || !$consent) {
        testimonialRedirect('danger', 'Use your first name and last initial, choose a rating and platform, write 20–300 characters without links, and confirm publication permission.');
    }

    try {
        if ($orderId > 0) {
            $orderStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND LOWER(TRIM(status)) = 'completed' LIMIT 1");
            $orderStmt->execute([$orderId, $userId]);
            if (!$orderStmt->fetch(PDO::FETCH_ASSOC)) {
                testimonialRedirect('danger', 'That order is not eligible for a verified testimonial.');
            }
            $stmt = $pdo->prepare("INSERT INTO testimonials (user_id, order_id, display_name, rating, message, platform, status, consent_to_publish, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', 1, CURRENT_TIMESTAMP)");
            $ok = $stmt->execute([$userId, $orderId, $name, $rating, $message, $platform]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO testimonials (user_id, order_id, display_name, rating, message, platform, status, consent_to_publish, created_at) VALUES (?, NULL, ?, ?, ?, ?, 'pending', 1, CURRENT_TIMESTAMP)");
            $ok = $stmt->execute([$userId, $name, $rating, $message, $platform]);
        }
        if (!$ok) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException('Testimonial insert failed: ' . ((string) ($errorInfo[2] ?? 'database rejected the insert')));
        }
        testimonialRedirect('success', 'Thank you. Your testimonial was sent to the admin for approval.');
    } catch (Throwable $e) {
        error_log('Customer testimonial submission failed: ' . $e->getMessage());
        testimonialRedirect('danger', 'Your testimonial could not be submitted right now. Please try again.');
    }
}

$completedOrders = [];
try {
    $stmt = $conn->prepare("SELECT id, service_name, platform, created_at FROM orders WHERE user_id = ? AND LOWER(TRIM(status)) = 'completed' ORDER BY id DESC LIMIT 30");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $completedOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    error_log('Completed testimonial orders query failed: ' . $e->getMessage());
}

ui_head('Share feedback — ' . APP_NAME, 'app');
?>
<style>
    .testimonial-page{max-width:760px;margin:0 auto;padding:18px 16px 42px}
    .testimonial-hero{padding:22px;border-radius:22px;background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;margin-bottom:16px}
    .testimonial-hero h1{font-size:1.35rem;margin:0 0 6px;font-weight:800}
    .testimonial-hero p{margin:0;color:rgba(255,255,255,.84);font-size:.84rem;line-height:1.6}
    .testimonial-card{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:18px;box-shadow:0 10px 25px rgba(45,35,98,.06)}
    .testimonial-card label{display:block;margin:10px 0 5px;font-size:.8rem;font-weight:700;color:var(--ink)}
    .testimonial-card input,.testimonial-card select,.testimonial-card textarea{width:100%;min-height:44px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--card);color:var(--ink);font:inherit}
    .testimonial-card textarea{min-height:130px;resize:vertical}
    .testimonial-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .testimonial-consent{display:flex!important;align-items:flex-start;gap:8px;margin:14px 0!important;font-weight:500!important;line-height:1.45}
    .testimonial-consent input{width:18px;min-height:18px;flex:0 0 auto;margin-top:2px}
    .testimonial-note{color:var(--muted);font-size:.78rem;line-height:1.5;margin:12px 0 0}
    .testimonial-alert{padding:12px 14px;border-radius:12px;margin-bottom:14px;font-size:.84rem}
    .testimonial-alert.success{background:#e8faf3;color:#087d5b}.testimonial-alert.danger{background:#fff0ed;color:#a23b43}
    @media(max-width:600px){.testimonial-page{padding-inline:12px}.testimonial-grid{grid-template-columns:1fr}.testimonial-card{padding:15px}}
</style>
<main class="testimonial-page">
    <section class="testimonial-hero">
        <h1>Share your experience</h1>
        <p>Tell us how Royal SMM worked for you. Every submission stays private until an admin reviews and approves it.</p>
    </section>
    <?php if ($flash): ?><div class="testimonial-alert <?= htmlspecialchars((string) $flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <section class="testimonial-card" aria-labelledby="testimonialFormTitle">
        <h2 id="testimonialFormTitle" style="font-size:1.05rem;margin:0 0 4px;color:var(--ink)">Your testimonial</h2>
        <p class="testimonial-note">Use a first name and last initial. Do not include phone numbers, passwords, links, or other private information.</p>
        <form method="post" style="margin-top:12px">
            <?= csrf_field() ?>
            <div class="testimonial-grid">
                <div><label for="testimonialName">Name</label><input id="testimonialName" name="display_name" maxlength="100" placeholder="Amina J." required></div>
                <div><label for="testimonialPlatform">Platform used</label><select id="testimonialPlatform" name="platform" required><option value="">Choose platform</option><?php foreach (['Instagram','TikTok','Facebook','YouTube','Telegram','Twitter/X','Other'] as $platformOption): ?><option value="<?= htmlspecialchars($platformOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($platformOption, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <div><label for="testimonialRating">Rating</label><select id="testimonialRating" name="rating" required><option value="5">5 stars</option><option value="4">4 stars</option><option value="3">3 stars</option><option value="2">2 stars</option><option value="1">1 star</option></select></div>
                <div><label for="testimonialOrder">Completed order (optional)</label><select id="testimonialOrder" name="order_id"><option value="0">General feedback</option><?php foreach ($completedOrders as $order): ?><option value="<?= (int) $order['id'] ?>">#<?= (int) $order['id'] ?> · <?= htmlspecialchars(mb_substr((string) $order['service_name'], 0, 42), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            </div>
            <label for="testimonialMessage">Your feedback</label>
            <textarea id="testimonialMessage" name="message" minlength="20" maxlength="300" placeholder="Tell us what you liked about ordering or tracking your service..." required></textarea>
            <label class="testimonial-consent"><input type="checkbox" name="consent_to_publish" value="1" required><span>I confirm this is my genuine feedback and give Royal SMM permission to publish it after admin approval.</span></label>
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-send me-1"></i>Send for admin approval</button>
        </form>
    </section>
</main>
<?php ui_nav('testimonials'); ui_foot(); ?>
