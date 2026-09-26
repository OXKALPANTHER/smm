<?php
/**
 * Free Royal support assistant.
 *
 * This endpoint intentionally uses no paid AI provider. It produces deterministic,
 * account-aware answers from the real user's balance, recent orders, and the
 * documented Royal workflows. It never claims to perform protected actions.
 */
require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

function chatReply($success, $message, $code = 200)
{
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    chatReply(false, 'Please log in to use Royal support chat.', 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chatReply(false, 'Method not allowed.', 405);
}
requireSameOriginRequest();

$now = microtime(true);
$lastRequest = (float) ($_SESSION['chatbot_last_request'] ?? 0);
if ($now - $lastRequest < 1) {
    chatReply(false, 'Please wait a moment before sending another message.', 429);
}
$_SESSION['chatbot_last_request'] = $now;

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$messages = is_array($input['messages'] ?? null) ? $input['messages'] : [];
$question = '';
for ($i = count($messages) - 1; $i >= 0; $i--) {
    if (($messages[$i]['role'] ?? '') === 'user') {
        $question = trim((string) ($messages[$i]['content'] ?? ''));
        break;
    }
}
$question = mb_substr($question, 0, 1500);
if ($question === '') {
    chatReply(false, 'Send a question to start the conversation.', 400);
}

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, balance, email FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];

$orderStmt = $conn->prepare("SELECT id, service_name, quantity, price, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 8");
$orderStmt->bind_param('i', $userId);
$orderStmt->execute();
$orders = $orderStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$text = function_exists('mb_strtolower') ? mb_strtolower($question, 'UTF-8') : strtolower($question);
$hasSwahili = preg_match('/\b(nawezaje|ninawezaje|salio|ongeza|order|huduma|bei|kiasi|hii|hii|yangu|imefika|imekamilika|wapi|tafadhali|asante|msaada|namba|kiwango)\b/u', $text) === 1;
$hello = preg_match('/\b(hello|hi|hey|habari|mambo|hujambo|salama)\b/u', $text) === 1;
$balance = preg_match('/\b(balance|salio|money|pesa|credit|credits)\b/u', $text) === 1;
$topup = preg_match('/\b(top.?up|deposit|ongeza|kuweka|เติม|payment|malipo)\b/u', $text) === 1;
$orderHelp = preg_match('/\b(order|agizo|maagizo|purchase|nunua|buy|weka)\b/u', $text) === 1;
$statusHelp = preg_match('/\b(status|progress|endelea|imefika|imekamilika|pending|processing|complete|completed|cancel|refund)\b/u', $text) === 1;
$serviceHelp = preg_match('/\b(service|huduma|bei|price|platform|followers|likes|views|comments|id)\b/u', $text) === 1;
$apiHelp = preg_match('/\b(api|key|developer|integration)\b/u', $text) === 1;
$securityHelp = preg_match('/\b(password|siri|security|hack|stolen|usalama|whatsapp)\b/u', $text) === 1;

$money = number_format((float) ($user['balance'] ?? 0), 0) . ' TZS';
$latest = $orders[0] ?? null;
$latestLine = $latest
    ? 'Your latest order is #' . (int) $latest['id'] . ' (' . $latest['status'] . ') for ' . (string) $latest['service_name'] . '.'
    : 'You do not have any orders yet.';

// Answer a specific order number from the user's own records only.
$orderId = 0;
if (preg_match('/(?:#|order\s*)(\d{1,12})/i', $question, $match)) {
    $orderId = (int) $match[1];
}
if ($orderId > 0) {
    foreach ($orders as $order) {
        if ((int) $order['id'] === $orderId) {
            $message = $hasSwahili
                ? 'Order #' . $orderId . ' iko kwenye hali ya ' . $order['status'] . '. Huduma: ' . $order['service_name'] . ', kiasi: ' . number_format((int) $order['quantity']) . '. Fungua Orders Zangu kwa progress ya sasa.'
                : 'Order #' . $orderId . ' is currently ' . $order['status'] . '. Service: ' . $order['service_name'] . ', quantity: ' . number_format((int) $order['quantity']) . '. Open Orders Zangu for the latest progress.';
            chatReply(true, $message);
        }
    }
    chatReply(true, $hasSwahili ? 'Sioni order #' . $orderId . ' kwenye akaunti yako. Tafadhali hakikisha namba ni sahihi.' : 'I cannot find order #' . $orderId . ' in your account. Please check the number and try again.');
}

if ($hello && !$balance && !$topup && !$orderHelp && !$statusHelp && !$serviceHelp && !$apiHelp && !$securityHelp) {
    chatReply(true, $hasSwahili
        ? 'Habari ' . ($user['username'] ?? 'Mteja') . '! Niko tayari kusaidia kuhusu salio, huduma, order, top-up, au API. Unahitaji msaada gani?'
        : 'Hello ' . ($user['username'] ?? 'customer') . '! I can help with your balance, services, orders, top-ups, or API access. What do you need?');
}

if ($balance && !$topup) {
    chatReply(true, $hasSwahili
        ? 'Salio lako la sasa ni ' . $money . '. Unaweza kuongeza salio kupitia kitufe cha Ongeza Salio.'
        : 'Your current balance is ' . $money . '. You can add balance through the Ongeza Salio button.');
}
if ($topup) {
    chatReply(true, $hasSwahili
        ? 'Ili kuongeza salio: fungua Ongeza Salio, weka kiasi, jina, email na namba ya simu, kisha thibitisha malipo kwenye simu. Salio litaongezwa baada ya malipo kuthibitishwa.'
        : 'To top up: open Ongeza Salio, enter the amount, name, email, and mobile number, then approve the payment on your phone. Your balance is updated after the payment is confirmed.');
}
if ($statusHelp || ($orderHelp && $latest)) {
    if ($latest) {
        chatReply(true, $hasSwahili
            ? $latestLine . ' Fungua Orders Zangu au bonyeza refresh ili kuona hali mpya kutoka kwa provider.'
            : $latestLine . ' Open Orders Zangu or refresh the page to see the latest provider status.');
    }
    chatReply(true, $hasSwahili ? 'Bado huna order. Chagua huduma kwenye Dashboard kuanza.' : 'You do not have an order yet. Choose a service on the Dashboard to get started.');
}
if ($orderHelp) {
    chatReply(true, $hasSwahili
        ? 'Kuweka order: chagua platform na huduma, unaweza kutafuta kwa jina au Service ID, weka link/username halali, chagua quantity ndani ya min/max, kagua gharama ya TSh, kisha thibitisha.'
        : 'To place an order: choose a platform and service, search by name or Service ID, enter a valid link/username, choose a quantity within the displayed min/max, review the TSh cost, and confirm.');
}
if ($serviceHelp) {
    chatReply(true, $hasSwahili
        ? 'Huduma zinaonekana kwa bei ya mteja kwa TSh. Tumia search kwenye Dashboard au fungua Huduma kutafuta kwa jina, platform, au Service ID kama 5557.'
        : 'Services are shown at the customer price in TSh. Use the Dashboard search or open Huduma to search by name, platform, or a Service ID such as 5557.');
}
if ($apiHelp) {
    chatReply(true, $hasSwahili
        ? 'API Center inaruhusu user aliyeingia kutengeneza API key na kuona documentation. Usishiriki API key yako na mtu mwingine.'
        : 'API Center lets a logged-in user create an API key and read the documentation. Keep your API key private.');
}
if ($securityHelp) {
    chatReply(true, $hasSwahili
        ? 'Kwa password, account security, dispute ya malipo, refund, au jambo linalohitaji hatua ya staff, usishiriki siri zako kwenye chat. Wasiliana na human support kupitia WhatsApp link kwenye menu.'
        : 'For passwords, account security, payment disputes, refunds, or anything requiring staff action, do not share secrets in chat. Contact human support through the WhatsApp link in the menu.');
}

chatReply(true, $hasSwahili
    ? 'Naweza kusaidia kuhusu salio, top-up, huduma, Service ID, order status, API Center na navigation ya Royal. Eleza swali lako kwa ufupi, kwa mfano: "salio langu ni kiasi gani?"'
    : 'I can help with your balance, top-ups, services, Service IDs, order status, API Center, and Royal navigation. Ask briefly, for example: "What is my balance?"');
