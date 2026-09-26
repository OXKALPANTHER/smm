<?php
/**
 * Free Royal support assistant.
 *
 * Uses deterministic account-aware answers with no paid AI dependency. The
 * assistant detects the latest user message and answers fully in English or
 * Kiswahili; product names such as Service ID, API Center, WhatsApp, and TSh
 * remain unchanged because they are interface/provider names.
 */
require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

function chatReply($success, $message, $code = 200)
{
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function chatText($value)
{
    return function_exists('mb_strtolower') ? mb_strtolower((string) $value, 'UTF-8') : strtolower((string) $value);
}

function chatHasAny($text, array $words)
{
    foreach ($words as $word) {
        if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu', $text)) {
            return true;
        }
    }
    return false;
}

function chatDetectLanguage($question)
{
    $text = chatText($question);
    $swahili = [
        'habari', 'hujambo', 'mambo', 'salama', 'nawezaje', 'ninawezaje', 'salio',
        'ongeza', 'kuweka', 'huduma', 'bei', 'kiasi', 'yangu', 'imefika',
        'imekamilika', 'wapi', 'tafadhali', 'asante', 'msaada', 'namba', 'kiwango',
        'agizo', 'maagizo', 'nunua', 'weka', 'endelea', 'cancel', 'futa', 'siri',
        'neno', 'usalama', 'jina', 'barua', 'akaunti', 'langu', 'hii', 'nini',
    ];
    $english = [
        'hello', 'hi', 'hey', 'how', 'what', 'where', 'when', 'why', 'please',
        'thanks', 'thank', 'help', 'my', 'account', 'balance', 'add', 'service',
        'price', 'quantity', 'buy', 'order', 'status', 'progress', 'pending',
        'complete', 'completed', 'cancel', 'refund', 'password', 'security',
        'name', 'email', 'can', 'do', 'is', 'are', 'the', 'for', 'with',
    ];
    $sw = 0;
    $en = 0;
    foreach ($swahili as $word) {
        if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu', $text)) {
            $sw++;
        }
    }
    foreach ($english as $word) {
        if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu', $text)) {
            $en++;
        }
    }
    if ($sw > $en) {
        return 'sw';
    }
    if ($en > $sw) {
        return 'en';
    }
    return ($_SESSION['chatbot_language'] ?? 'en') === 'sw' ? 'sw' : 'en';
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

$language = chatDetectLanguage($question);
$_SESSION['chatbot_language'] = $language;
$isSwahili = $language === 'sw';

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, balance, email FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];

$orderStmt = $conn->prepare("SELECT id, service_name, quantity, price, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 8");
$orderStmt->bind_param('i', $userId);
$orderStmt->execute();
$orders = $orderStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$text = chatText($question);
$hello = chatHasAny($text, ['hello', 'hi', 'hey', 'habari', 'mambo', 'hujambo', 'salama']);
$balance = chatHasAny($text, ['balance', 'salio', 'money', 'pesa', 'credit', 'credits']);
$topup = chatHasAny($text, ['top-up', 'topup', 'deposit', 'ongeza', 'kuweka', 'payment', 'malipo']);
$orderHelp = chatHasAny($text, ['order', 'agizo', 'maagizo', 'purchase', 'nunua', 'buy', 'weka']);
$statusHelp = chatHasAny($text, ['status', 'progress', 'endelea', 'imefika', 'imekamilika', 'pending', 'processing', 'complete', 'completed', 'cancel', 'refund', 'futa']);
$serviceHelp = chatHasAny($text, ['service', 'huduma', 'bei', 'price', 'platform', 'followers', 'likes', 'views', 'comments', 'id']);
$apiHelp = chatHasAny($text, ['api', 'key', 'developer', 'integration']);
$securityHelp = chatHasAny($text, ['password', 'siri', 'security', 'hack', 'stolen', 'usalama', 'whatsapp']);

$money = number_format((float) ($user['balance'] ?? 0), 0) . ' TSh';
$latest = $orders[0] ?? null;
$latestLineEn = $latest
    ? 'Your latest order is #' . (int) $latest['id'] . ' (' . (string) $latest['status'] . ') for ' . (string) $latest['service_name'] . '.'
    : 'You do not have any orders yet.';
$latestLineSw = $latest
    ? 'Agizo lako la mwisho ni #' . (int) $latest['id'] . ' (' . (string) $latest['status'] . ') la huduma ya ' . (string) $latest['service_name'] . '.'
    : 'Bado huna maagizo yoyote.';

// Answer a specific order number from the user's own records only.
$orderId = 0;
if (preg_match('/(?:#|order\s*|agizo\s*|maagizo\s*)(\d{1,12})/iu', $question, $match)) {
    $orderId = (int) $match[1];
}
if ($orderId > 0) {
    foreach ($orders as $order) {
        if ((int) $order['id'] === $orderId) {
            $message = $isSwahili
                ? 'Agizo #' . $orderId . ' liko kwenye hali ya ' . $order['status'] . '. Huduma: ' . $order['service_name'] . ', kiasi: ' . number_format((int) $order['quantity']) . '. Fungua Maagizo yangu kuona maendeleo ya sasa.'
                : 'Order #' . $orderId . ' is currently ' . $order['status'] . '. Service: ' . $order['service_name'] . ', quantity: ' . number_format((int) $order['quantity']) . '. Open My Orders to see the latest progress.';
            chatReply(true, $message);
        }
    }
    chatReply(true, $isSwahili
        ? 'Sioni agizo #' . $orderId . ' kwenye akaunti yako. Tafadhali hakikisha namba ni sahihi.'
        : 'I cannot find order #' . $orderId . ' in your account. Please check the number and try again.');
}

if ($hello && !$balance && !$topup && !$orderHelp && !$statusHelp && !$serviceHelp && !$apiHelp && !$securityHelp) {
    chatReply(true, $isSwahili
        ? 'Habari ' . ($user['username'] ?? 'mteja') . '! Niko tayari kusaidia kuhusu salio, huduma, maagizo, kuongeza salio, au API. Unahitaji msaada gani?'
        : 'Hello ' . ($user['username'] ?? 'customer') . '! I can help with your balance, services, orders, top-ups, or API access. What do you need?');
}

if ($balance && !$topup) {
    chatReply(true, $isSwahili
        ? 'Salio lako la sasa ni ' . $money . '. Unaweza kuongeza salio kupitia kitufe cha Ongeza Salio.'
        : 'Your current balance is ' . $money . '. You can add balance through the Add Balance button.');
}
if ($topup) {
    chatReply(true, $isSwahili
        ? 'Ili kuongeza salio: fungua Ongeza Salio, weka kiasi, jina, barua pepe na namba ya simu, kisha thibitisha malipo kwenye simu yako. Salio litaongezwa baada ya malipo kuthibitishwa.'
        : 'To add balance: open Add Balance, enter the amount, name, email, and phone number, then approve the payment on your phone. Your balance is updated after the payment is confirmed.');
}
if ($statusHelp || ($orderHelp && $latest)) {
    if ($latest) {
        chatReply(true, $isSwahili
            ? $latestLineSw . ' Fungua Maagizo yangu au bonyeza Onyesha upya kuona hali mpya kutoka kwa mtoa huduma.'
            : $latestLineEn . ' Open My Orders or click Refresh to see the latest provider status.');
    }
    chatReply(true, $isSwahili
        ? 'Bado huna agizo. Chagua huduma kwenye Dashibodi kuanza.'
        : 'You do not have an order yet. Choose a service on the Dashboard to get started.');
}
if ($orderHelp) {
    chatReply(true, $isSwahili
        ? 'Kuweka agizo: chagua jukwaa na huduma, tafuta kwa jina au Service ID, weka kiungo au jina la mtumiaji halali, chagua kiasi ndani ya kiwango kinachoonekana, kagua gharama ya TSh, kisha thibitisha.'
        : 'To place an order: choose a platform and service, search by name or Service ID, enter a valid link or username, choose a quantity within the displayed limits, review the TSh cost, and confirm.');
}
if ($serviceHelp) {
    chatReply(true, $isSwahili
        ? 'Huduma zinaonekana kwa bei ya mteja kwa TSh. Tumia utafutaji kwenye Dashibodi au fungua Huduma kutafuta kwa jina, jukwaa, au Service ID kama 5557.'
        : 'Services are shown at the customer price in TSh. Use Dashboard search or open Services to search by name, platform, or a Service ID such as 5557.');
}
if ($apiHelp) {
    chatReply(true, $isSwahili
        ? 'API Center inaruhusu mtumiaji aliyeingia kutengeneza API key na kusoma nyaraka. Usimpe mtu mwingine API key yako.'
        : 'API Center lets a signed-in user create an API key and read the documentation. Keep your API key private.');
}
if ($securityHelp) {
    chatReply(true, $isSwahili
        ? 'Kwa nenosiri, usalama wa akaunti, malalamiko ya malipo, marejesho ya fedha, au jambo linalohitaji hatua ya mfanyakazi, usishiriki siri zako kwenye mazungumzo. Wasiliana na msaada wa kibinadamu kupitia WhatsApp kwenye menyu.'
        : 'For passwords, account security, payment disputes, refunds, or anything requiring staff action, do not share secrets in chat. Contact human support through WhatsApp in the menu.');
}

chatReply(true, $isSwahili
    ? 'Naweza kusaidia kuhusu salio, kuongeza salio, huduma, Service ID, hali ya agizo, API Center na matumizi ya Royal. Eleza swali lako kwa mfano: "salio langu ni kiasi gani?"'
    : 'I can help with your balance, top-ups, services, Service IDs, order status, API Center, and using Royal. Ask a question such as: "What is my balance?"');
