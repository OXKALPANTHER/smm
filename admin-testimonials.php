<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ui.php';
requireAdmin();

function adminTestimonialLength(string $value): int
{
    if (preg_match_all('/./us', $value, $matches) !== false) return count($matches[0]);
    return strlen($value);
}
function adminTestimonialHasLink(string $value): bool
{
    return (bool) preg_match('~(?:https?://|www\.|\b[\p{L}0-9-]+\.(?:com|net|org|co|io|me|ly|app|dev|info|biz|tz|uk|us)(?:\b|/))~iu', $value);
}
function adminTestimonialRedirect(string $kind, string $message): void
{
    $_SESSION['testimonial_admin_flash'] = ['type' => $kind, 'message' => $message];
    header('Location: admin-testimonials.php', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSameOriginRequest();
    require_csrf();
    if (!rate_limit('admin_testimonial_moderation', 40, 60)) {
        adminTestimonialRedirect('danger', 'Too many actions. Please wait a moment.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    try {
        if ($action === 'approve' || $action === 'reject') {
            $stmt = $conn->prepare('SELECT id, consent_to_publish FROM testimonials WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $review = $stmt->get_result()->fetch_assoc();
            if (!$review) adminTestimonialRedirect('warning', 'Review not found.');
            if ($action === 'approve' && (int) $review['consent_to_publish'] !== 1) {
                adminTestimonialRedirect('warning', 'This review cannot be approved without publication consent.');
            }
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE testimonials SET status = 'approved', approved_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param('i', $id);
            } else {
                $stmt = $conn->prepare("UPDATE testimonials SET status = 'rejected', approved_at = NULL WHERE id = ?");
                $stmt->bind_param('i', $id);
            }
            $stmt->execute();
            adminTestimonialRedirect('success', $action === 'approve' ? 'Review approved.' : 'Review rejected.');
        }

        if ($action === 'edit') {
            $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($_POST['display_name'] ?? ''))) ?? '');
            $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
            $messageRaw = trim((string) ($_POST['message'] ?? ''));
            $message = trim(strip_tags($messageRaw));
            $platform = trim(strip_tags((string) ($_POST['platform'] ?? '')));
            $validPublicName = (bool) preg_match('/^\p{L}[\p{L}\p{M} .\x27-]*\s+\p{L}\.?$/u', $name);
            $createdDate = (string) ($_POST['created_date'] ?? '');
            $date = DateTime::createFromFormat('!Y-m-d', $createdDate);
            if (!$validPublicName || adminTestimonialLength($name) < 3 || adminTestimonialLength($name) > 100 || !$rating || adminTestimonialLength($message) < 20 || adminTestimonialLength($message) > 300 || $platform === '' || adminTestimonialHasLink($messageRaw) || !$date || $date->format('Y-m-d') !== $createdDate) {
                adminTestimonialRedirect('danger', 'Check the name, 1–5 rating, 20–300 character text, platform, date, and ensure there are no links.');
            }
            $createdAt = $createdDate . ' 12:00:00';
            $stmt = $conn->prepare("UPDATE testimonials SET display_name = ?, rating = ?, message = ?, platform = ?, created_at = ? WHERE id = ? AND status = 'pending'");
            $stmt->bind_param('sisssi', $name, $rating, $message, $platform, $createdAt, $id);
            $stmt->execute();
            adminTestimonialRedirect('success', 'Pending review updated.');
        }

        if ($action === 'add') {
            $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($_POST['display_name'] ?? ''))) ?? '');
            $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
            $messageRaw = trim((string) ($_POST['message'] ?? ''));
            $message = trim(strip_tags($messageRaw));
            $platform = trim(strip_tags((string) ($_POST['platform'] ?? '')));
            $validPublicName = (bool) preg_match('/^\p{L}[\p{L}\p{M} .\x27-]*\s+\p{L}\.?$/u', $name);
            $createdDate = (string) ($_POST['created_date'] ?? '');
            $consent = isset($_POST['consent_to_publish']) && (string) $_POST['consent_to_publish'] === '1';
            $date = DateTime::createFromFormat('!Y-m-d', $createdDate);
            if (!$validPublicName || adminTestimonialLength($name) < 3 || adminTestimonialLength($name) > 100 || !$rating || adminTestimonialLength($message) < 20 || adminTestimonialLength($message) > 300 || $platform === '' || adminTestimonialHasLink($messageRaw) || !$date || $date->format('Y-m-d') !== $createdDate || !$consent) {
                adminTestimonialRedirect('danger', 'Complete all fields, confirm publication permission, and use 20–300 characters without links.');
            }
            $createdAt = $createdDate . ' 12:00:00';
            $stmt = $conn->prepare("INSERT INTO testimonials (user_id, order_id, display_name, rating, message, platform, status, consent_to_publish, created_at) VALUES (NULL, NULL, ?, ?, ?, ?, 'pending', 1, ?)");
            $stmt->bind_param('sisss', $name, $rating, $message, $platform, $createdAt);
            $stmt->execute();
            adminTestimonialRedirect('success', 'Manual testimonial added to the moderation queue.');
        }
        adminTestimonialRedirect('warning', 'Unknown moderation action.');
    } catch (Throwable $e) {
        error_log('Admin testimonial action failed: ' . $e->getMessage());
        adminTestimonialRedirect('danger', 'The review could not be saved. Please check the fields and try again.');
    }
}

$pending = [];
$pendingQueryError = '';
try {
    $pendingStmt = $pdo->prepare("SELECT id, user_id, order_id, display_name, rating, message, platform, consent_to_publish, created_at FROM testimonials WHERE LOWER(TRIM(status)) = 'pending' ORDER BY created_at ASC, id ASC");
    $pendingStmt->execute();
    $pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Admin pending testimonial query failed: ' . $e->getMessage());
    $pendingQueryError = 'Pending testimonials could not be loaded. Please check the database connection and schema.';
}
$flash = $_SESSION['testimonial_admin_flash'] ?? null;
unset($_SESSION['testimonial_admin_flash']);
ui_head('Testimonials — Admin', 'admin');
?>

<main class="review-admin">
  <div class="review-top"><div><h1 style="font-size:1.4rem;margin:0">Testimonial moderation</h1><p class="review-meta" style="margin:.25rem 0 0">Pending reviews are private until approved and consented.</p></div><a href="admin.php" style="background:#eeeafd;color:#4932c4">Back to Admin</a></div>
  <?php if ($flash): ?><div class="review-notice" role="status"><?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($pendingQueryError): ?><div class="review-notice" role="alert"><?= htmlspecialchars($pendingQueryError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <section class="review-form" aria-labelledby="manualReviewTitle"><h2 id="manualReviewTitle">Add testimonial manually</h2><p class="review-meta">Use only genuine customer feedback for which publication permission has been confirmed. Manual entries are not marked as verified customers.</p>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="review-grid"><div><label for="manualName">Name (first name and last initial)</label><input id="manualName" name="display_name" maxlength="100" required></div><div><label for="manualPlatform">Platform</label><input id="manualPlatform" name="platform" maxlength="80" required placeholder="Instagram"></div><div><label for="manualRating">Rating</label><select id="manualRating" name="rating" required><option value="5">5 stars</option><option value="4">4 stars</option><option value="3">3 stars</option><option value="2">2 stars</option><option value="1">1 star</option></select></div><div><label for="manualDate">Date</label><input id="manualDate" type="date" name="created_date" value="<?= date('Y-m-d') ?>" required></div></div>
      <label for="manualMessage">Text (20–300 characters, no links)</label><textarea id="manualMessage" name="message" minlength="20" maxlength="300" required></textarea>
      <label class="review-consent"><input type="checkbox" name="consent_to_publish" value="1" required><span>I confirm I have the customer's permission to publish this feedback.</span></label>
      <button class="review-add" type="submit">Add to moderation queue</button>
    </form>
  </section>
  <h2 style="font-size:1.1rem;margin:22px 0 12px">Pending reviews (<?= count($pending) ?>)</h2>
  <?php if (!$pending): ?><div class="review-empty">There are no pending reviews.</div><?php endif; ?>
  <?php foreach ($pending as $review): ?>
    <article class="review-card">
      <div class="review-meta">Review #<?= (int) $review['id'] ?> · <?= $review['order_id'] === null ? 'Manual entry (not verified)' : 'Order #' . (int) $review['order_id'] ?> · Consent: <?= (int) $review['consent_to_publish'] === 1 ? 'Yes' : 'No' ?></div>
      <form method="post" style="margin-top:8px">
        <?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
        <div class="review-grid"><div><label>Name</label><input name="display_name" maxlength="100" value="<?= htmlspecialchars((string) $review['display_name'], ENT_QUOTES, 'UTF-8') ?>" required></div><div><label>Platform</label><input name="platform" maxlength="80" value="<?= htmlspecialchars((string) $review['platform'], ENT_QUOTES, 'UTF-8') ?>" required></div><div><label>Rating</label><select name="rating" required><?php for ($star=5; $star>=1; $star--): ?><option value="<?= $star ?>" <?= (int) $review['rating'] === $star ? 'selected' : '' ?>><?= $star ?> stars</option><?php endfor; ?></select></div><div><label>Date</label><input type="date" name="created_date" value="<?= htmlspecialchars(substr((string) $review['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?>" required></div></div>
        <label>Review text (20–300 characters)</label><textarea name="message" minlength="20" maxlength="300" required><?= htmlspecialchars((string) $review['message'], ENT_QUOTES, 'UTF-8') ?></textarea>
        <div class="review-actions"><button class="review-edit" type="submit">Save edit</button></div>
      </form>
      <div class="review-actions">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int) $review['id'] ?>"><button class="review-approve" type="submit" <?= (int) $review['consent_to_publish'] !== 1 ? 'disabled title="Publication consent is required"' : '' ?>>Approve</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int) $review['id'] ?>"><button class="review-reject" type="submit">Reject</button></form>
      </div>
    </article>
  <?php endforeach; ?>
</main>
<?php ui_foot(); ?>
