<?php
require_once __DIR__ . '/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php', true, 303);
    exit;
}
requireSameOriginRequest();
require_csrf();
if (!rate_limit('testimonial_submission', 3, 3600)) {
    $_SESSION['testimonial_flash'] = ['type' => 'danger', 'message' => 'Too many review attempts. Please try again later.'];
    header('Location: orders.php', true, 303);
    exit;
}

function testimonialTextLength(string $text): int
{
    if (preg_match_all('/./us', $text, $matches) !== false) {
        return count($matches[0]);
    }
    return strlen($text);
}
function testimonialHasLink(string $text): bool
{
    return (bool) preg_match('~(?:https?://|www\.|\b[\p{L}0-9-]+\.(?:com|net|org|co|io|me|ly|app|dev|info|biz|tz|uk|us)(?:\b|/))~iu', $text);
}
function testimonialRedirect(string $type, string $message): void
{
    $_SESSION['testimonial_flash'] = ['type' => $type, 'message' => $message];
    header('Location: orders.php', true, 303);
    exit;
}

$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
$rawMessage = trim((string) ($_POST['message'] ?? ''));
$rawName = trim((string) ($_POST['display_name'] ?? ''));
$consent = isset($_POST['consent_to_publish']) && (string) $_POST['consent_to_publish'] === '1';

if (!$orderId || !$rating || !$consent) {
    testimonialRedirect('danger', 'Please complete the required review fields and publication consent.');
}
if (testimonialHasLink($rawMessage)) {
    testimonialRedirect('danger', 'Please remove links from your review.');
}
$message = trim(strip_tags($rawMessage));
$message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? '';
if (testimonialTextLength($message) < 20 || testimonialTextLength($message) > 300) {
    testimonialRedirect('danger', 'Your review must be between 20 and 300 characters.');
}
$displayName = trim(preg_replace('/\s+/u', ' ', strip_tags($rawName)) ?? '');
$nameLength = testimonialTextLength($displayName);
if ($nameLength < 3 || $nameLength > 60 || !preg_match('/^\p{L}[\p{L}\p{M} .\'-]*\s+\p{L}\.?$/u', $displayName)) {
    testimonialRedirect('danger', 'Enter your first name and last initial for the public display name.');
}

$userId = (int) $_SESSION['user_id'];
$orderStmt = $pdo->prepare("SELECT id, status, platform FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$orderStmt->execute([$orderId, $userId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);
if (!$order || strtolower(trim((string) $order['status'])) !== 'completed') {
    testimonialRedirect('danger', 'Only your completed orders can be reviewed.');
}

$existingStmt = $pdo->prepare('SELECT id FROM testimonials WHERE order_id = ? LIMIT 1');
$existingStmt->execute([$orderId]);
if ($existingStmt->fetch(PDO::FETCH_ASSOC)) {
    testimonialRedirect('warning', 'This order already has a review.');
}

$platform = trim((string) ($order['platform'] ?? '')) ?: 'Social media';
try {
    // Reconcile the runtime table before inserting in case the app worker
    // started before the deployment migration ran.
    ensureTestimonialsTable($pdo);
    $insert = $pdo->prepare("INSERT INTO testimonials (user_id, order_id, display_name, rating, message, platform, status, consent_to_publish) VALUES (?, ?, ?, ?, ?, ?, 'pending', 1)");
    if (!$insert->execute([$userId, $orderId, $displayName, $rating, $message, $platform])) {
        $errorInfo = $insert->errorInfo();
        throw new RuntimeException('Testimonial insert failed: ' . ((string) ($errorInfo[2] ?? 'database rejected the insert')));
    }
    testimonialRedirect('success', 'Thank you. Your review was submitted for moderation.');
} catch (Throwable $e) {
    error_log('Customer testimonial submission failed: ' . $e->getMessage());
    testimonialRedirect('warning', 'This order may already have a review. Please refresh your orders.');
}
