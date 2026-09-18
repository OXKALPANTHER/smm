<?php
require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to use support chat.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (OPENAI_API_KEY === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Live support is not configured yet. Please contact support directly.']);
    exit;
}

if (!filter_var(OPENAI_BASE_URL, FILTER_VALIDATE_URL)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Live support has an invalid AI provider URL. Check OPENAI_BASE_URL in Render.']);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Live support cannot start because the server is missing PHP cURL. Please redeploy the latest application image.']);
    exit;
}

$now = microtime(true);
$lastRequest = (float) ($_SESSION['chatbot_last_request'] ?? 0);
if ($now - $lastRequest < 1) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Please wait a moment before sending another message.']);
    exit;
}
$_SESSION['chatbot_last_request'] = $now;
session_write_close();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$messages = $input['messages'] ?? [];
if (!is_array($messages)) {
    $messages = [];
}

$cleanMessages = [];
foreach (array_slice($messages, -8) as $message) {
    if (!is_array($message) || !in_array($message['role'] ?? '', ['user', 'assistant'], true)) {
        continue;
    }
    $content = trim((string) ($message['content'] ?? ''));
    if ($content !== '') {
        $cleanMessages[] = ['role' => $message['role'], 'content' => mb_substr($content, 0, 1500)];
    }
}
if (!$cleanMessages || end($cleanMessages)['role'] !== 'user') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Send a question to start the conversation.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, balance, email FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];

$orderStmt = $conn->prepare("SELECT service_name, quantity, price, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$orderStmt->bind_param('i', $userId);
$orderStmt->execute();
$orders = $orderStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$context = [
    'username' => $user['username'] ?? 'customer',
    'balance_tzs' => (float) ($user['balance'] ?? 0),
    'recent_orders' => $orders,
];

$systemPrompt = <<<PROMPT
You are the real customer-support assistant for Royal SMM, a social-media marketing platform. You are not a salesperson and must never invent features, payment results, order statuses, prices, policies, URLs, or actions. Answer in the same language as the customer: English, Swahili, or a natural mix when they mix languages.

Stay strictly within Royal SMM support. You may answer questions about this website, account navigation, registration, top-ups, balances, orders, services, notifications, the API Center, the help guide, and contacting support. If a question is unrelated to Royal SMM, politely say that you only support Royal SMM and invite the customer to ask how to use the platform. Do not answer general knowledge questions.

You can explain these real workflows:
- Dashboard: choose a platform and service, enter a valid public link or username, choose a quantity within the displayed minimum and maximum, review the price, and submit the order.
- Top-up: open Ongeza Salio, enter the amount, name, email, and mobile-money phone number, approve the payment on the phone, and wait for the balance confirmation. A successful top-up increases the account balance.
- Orders: customers can review order status and progress on Orders Zangu or the dashboard. Pending or processing orders may take time.
- Notifications: account and payment updates appear in Notisi.
- API Center: logged-in users can generate and use their API key; they should keep it private.
- Help: the app has a Mwongozo page and WhatsApp support/community links. For a payment dispute, account security issue, refund, or anything requiring staff action, clearly say that a human support agent must handle it and direct the customer to WhatsApp support rather than pretending to perform the action.

Be concise but helpful. Give numbered steps for procedures. Ask one focused follow-up question when the request lacks necessary detail. Never request or reveal passwords, API keys, SMTP credentials, payment PINs, or other secrets. Do not claim you changed an order, credited an account, sent an email, or contacted staff. You may use the private customer context below only to explain what is visible in their account; do not expose their email.

PRIVATE CUSTOMER CONTEXT:
PROMPT;
$systemPrompt .= "\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$payload = json_encode([
    'model' => OPENAI_MODEL,
    'messages' => array_merge([['role' => 'system', 'content' => $systemPrompt]], $cleanMessages),
    'temperature' => 0.2,
    'max_tokens' => 350,
]);

$ch = curl_init(OPENAI_BASE_URL . '/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . OPENAI_API_KEY,
        'Content-Type: application/json',
    ],
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => OPENAI_TIMEOUT,
]);
$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($responseBody === false || $curlError !== '') {
    error_log('Chatbot provider connection failed: ' . $curlError . ' URL=' . OPENAI_BASE_URL);
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'The AI provider could not be reached. Check outbound network access and OPENAI_BASE_URL in Render.']);
    exit;
}

$response = json_decode($responseBody, true);
$answer = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
if ($statusCode < 200 || $statusCode >= 300 || $answer === '') {
    error_log('Chatbot provider returned HTTP ' . $statusCode . ': ' . mb_substr($responseBody, 0, 500));
    http_response_code(502);
    $providerMessage = (string) ($response['error']['message'] ?? '');
    if ($statusCode === 401) {
        $message = 'The AI provider rejected OPENAI_API_KEY. Check that the key is valid and saved in Render.';
    } elseif ($statusCode === 403) {
        $message = 'The AI provider denied this request. Check API key permissions and project access.';
    } elseif ($statusCode === 404) {
        $message = 'The configured AI model or provider URL was not found. Check OPENAI_MODEL and OPENAI_BASE_URL.';
    } elseif ($statusCode === 429) {
        $message = 'Support chat is temporarily busy. Please try again shortly or contact human support on WhatsApp.';
    } elseif ($providerMessage !== '') {
        $message = 'The AI provider returned an error. Check the API key, model, and account billing settings.';
    } else {
        $message = 'The AI provider returned an unexpected response. Check the Render logs for the HTTP status.';
    }
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

echo json_encode(['success' => true, 'message' => $answer], JSON_UNESCAPED_UNICODE);
