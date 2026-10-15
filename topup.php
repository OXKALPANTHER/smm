<?php
// Performance direction: preserve the payment workflow while serving precompiled styles instead of compiling CSS in the browser.
require_once 'config.php';
require_once 'includes/pwa.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

define('PALMPESA_TOKEN', PALMPESA_API_TOKEN);
define('PALMPESA_ACCOUNT_ID', PALMPESA_USER_ID);
define('PALMPESA_API_URL', PALMPESA_BASE_URL);
const VERIFICATION_FEE_TZS = 5000;
const VERIFICATION_PAYMENT_DESCRIPTION = 'Account verification fee via PalmPesa';
const VERIFICATION_WALLET_DESCRIPTION = 'Account verification fee paid from wallet';
function callAPI($method, $url, $data = false)
{
    $curl = curl_init();
    $headers = [
        'Authorization: Bearer ' . PALMPESA_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => PALMPESA_TIMEOUT,
    ]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        if ($data) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
    $result = curl_exec($curl);
    $curlError = $result === false ? curl_error($curl) : '';
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $decoded = json_decode((string) $result, true);
    if (!is_array($decoded)) $decoded = [];
    $decoded['_http_status'] = $httpStatus;
    if ($curlError !== '') {
        $decoded['_transport_error'] = $curlError;
        error_log('PalmPesa transport failure (HTTP ' . $httpStatus . '): ' . $curlError);
    } elseif ($httpStatus < 200 || $httpStatus >= 300) {
        error_log('PalmPesa rejected request (HTTP ' . $httpStatus . '): ' . palmPesaProviderMessage($decoded));
    }
    return $decoded;
}
function palmPesaPayload($response)
{
    if (!is_array($response)) return [];
    $data = $response['data'] ?? null;
    if (is_array($data) && array_is_list($data)) $data = $data[0] ?? [];
    if (!is_array($data)) $data = [];
    $result = $response['result'] ?? [];
    if (!is_array($result)) $result = [];
    return array_merge($response, $result, $data);
}
function palmPesaOrderId($response)
{
    $payload = palmPesaPayload($response);
    foreach (['order_id', 'transaction_id', 'reference', 'id'] as $key) {
        if (isset($payload[$key]) && is_scalar($payload[$key]) && trim((string) $payload[$key]) !== '') {
            return trim((string) $payload[$key]);
        }
    }
    return '';
}
function palmPesaProviderMessage($response)
{
    $payload = palmPesaPayload($response);
    foreach (['message', 'error_description', 'error', 'detail', 'description'] as $key) {
        if (!isset($payload[$key]) || !is_scalar($payload[$key])) continue;
        $message = trim(strip_tags((string) $payload[$key]));
        $message = preg_replace('/\s+/', ' ', $message);
        if ($message !== '') return substr($message, 0, 220);
    }
    if (!empty($response['_transport_error'])) return 'Could not connect to PalmPesa. Please check your connection and try again.';
    $code = (int) ($response['_http_status'] ?? 0);
    return $code >= 400 ? 'PalmPesa rejected the request (HTTP ' . $code . '). Please check payment configuration and try again.' : '';
}
function palmPesaPaymentStatus($response)
{
    $payload = palmPesaPayload($response);
    return normalizePaymentStatus($payload['payment_status'] ?? $payload['status'] ?? $payload['state'] ?? '');
}
function palmPesaRequestFailed($response)
{
    $code = (int) ($response['_http_status'] ?? 0);
    $payload = palmPesaPayload($response);
    $hasProviderBody = count(array_diff_key($response, ['_http_status' => true, '_transport_error' => true])) > 0;
    return !empty($response['_transport_error'])
        || ($code > 0 && ($code < 200 || $code >= 300))
        || !$hasProviderBody
        || (array_key_exists('success', $payload) && $payload['success'] === false)
        || !empty($payload['error']);
}
function formatPhoneNumber($phone)
{
    $phone = preg_replace('/[^0-9]/', '', (string) $phone);
    if (strpos($phone, '0') === 0) $phone = '255' . substr($phone, 1);
    if (!preg_match('/^255[67][0-9]{8}$/', $phone)) return false;
    return $phone;
}
function verificationReply($success, $message, $extra = [])
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}
function requireVerificationToken($input)
{
    $provided = (string) ($input['_token'] ?? '');
    $expected = (string) ($_SESSION['verification_csrf'] ?? '');
    if ($provided === '' || $expected === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        verificationReply(false, 'Session expired. Refresh the page and try again.');
    }
}
function completeVerificationPayment($externalRef, $userId)
{
    global $conn;
    $externalRef = trim((string) $externalRef);
    $userId = (int) $userId;
    if ($externalRef === '' || $userId <= 0) throw new RuntimeException('Missing payment reference.');
    $conn->begin_transaction();
    try {
        $lock = (defined('DB_DRIVER') && in_array(DB_DRIVER, ['mysql', 'pgsql'], true)) ? ' FOR UPDATE' : '';
        $stmt = $conn->prepare("SELECT t.id, t.amount, t.status, u.email, u.username FROM transactions t JOIN users u ON u.id = t.user_id WHERE t.external_ref = ? AND t.user_id = ? AND t.description = ? LIMIT 1{$lock}");
        $description = VERIFICATION_PAYMENT_DESCRIPTION;
        $stmt->bind_param('sis', $externalRef, $userId, $description);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        if (!$transaction || (int) round((float) $transaction['amount']) !== VERIFICATION_FEE_TZS) {
            throw new RuntimeException('Verification payment was not found or has an invalid amount.');
        }
        if (strtolower((string) $transaction['status']) === 'failed') {
            throw new RuntimeException('This verification payment failed. Please start a new request.');
        }
        $wasPending = strtolower((string) $transaction['status']) === 'pending';
        if ($wasPending) {
            $status = 'completed';
            $stmt = $conn->prepare("UPDATE transactions SET status = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'");
            $stmt->bind_param('si', $status, $transaction['id']);
            if (!$stmt->execute()) throw new RuntimeException('Could not record the confirmed payment.');
        }
        $stmt = $conn->prepare('UPDATE users SET is_verified = 1 WHERE id = ?');
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new RuntimeException('Could not update account verification.');
        $conn->commit();
        if ($wasPending) {
            try {
                createNotification($userId, 'Akaunti imethibitishwa', 'Malipo ya uthibitisho yamekamilika. Sasa unaweza kutumia bei za chini za akaunti zilizothibitishwa.', 'success', 'user', ['source' => 'verification']);
                logActivity($userId, 'verification_payment_completed', 'Account verified after confirmed PalmPesa fee of 5000 TZS');
            } catch (Throwable $noticeError) {
                error_log('Verification notification failed: ' . $noticeError->getMessage());
            }
            try {
                sendVerificationSuccessEmail($transaction['email'], $transaction['username'], 'mobile_money');
            } catch (Throwable $emailError) {
                error_log('Verification email failed for user ' . $userId . ': ' . $emailError->getMessage());
            }
        }
        return ['verified' => true, 'already_verified' => !$wasPending];
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }
}
// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];
    $action = (string) ($input['action'] ?? '');
    $currentUserId = (int) $_SESSION['user_id'];

    if (in_array($action, ['pay_verification_wallet', 'initiate_verification_payment', 'check_verification_status'], true)) {
        requireVerificationToken($input);
        $stmt = $conn->prepare('SELECT id, username, email, phone, balance, is_verified FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $currentUserId);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        if (!$account) verificationReply(false, 'Account not found.');
        if ((int) ($account['is_verified'] ?? 0) === 1) verificationReply(true, 'Your account is already verified.', ['verified' => true, 'already_verified' => true]);

        if ($action === 'pay_verification_wallet') {
            if (empty($input['confirmed'])) verificationReply(false, 'Please confirm the 5,000 TZS wallet payment before continuing.');
            $conn->begin_transaction();
            try {
                $lock = (defined('DB_DRIVER') && in_array(DB_DRIVER, ['mysql', 'pgsql'], true)) ? ' FOR UPDATE' : '';
                $stmt = $conn->prepare("SELECT balance, is_verified FROM users WHERE id = ?{$lock}");
                $stmt->bind_param('i', $currentUserId);
                $stmt->execute();
                $lockedUser = $stmt->get_result()->fetch_assoc();
                if (!$lockedUser) throw new RuntimeException('Account not found.');
                if ((int) ($lockedUser['is_verified'] ?? 0) === 1) {
                    $conn->commit();
                    verificationReply(true, 'Your account is already verified.', ['verified' => true, 'already_verified' => true]);
                }
                $balance = (float) ($lockedUser['balance'] ?? 0);
                if ($balance < VERIFICATION_FEE_TZS) {
                    $conn->rollback();
                    verificationReply(false, 'Your wallet balance is not enough. Top up at least 5,000 TZS to continue.', ['insufficient_balance' => true, 'balance' => $balance, 'required' => VERIFICATION_FEE_TZS]);
                }
                $stmt = $conn->prepare('UPDATE users SET balance = balance - ?, is_verified = 1 WHERE id = ? AND balance >= ? AND is_verified = 0');
                $amount = VERIFICATION_FEE_TZS;
                $stmt->bind_param('dii', $amount, $currentUserId, $amount);
                if (!$stmt->execute()) throw new RuntimeException('Wallet debit failed.');
                $stmt = $conn->prepare('SELECT balance, is_verified FROM users WHERE id = ?');
                $stmt->bind_param('i', $currentUserId);
                $stmt->execute();
                $afterDebit = $stmt->get_result()->fetch_assoc();
                if (!$afterDebit || abs((float) $afterDebit['balance'] - ($balance - VERIFICATION_FEE_TZS)) > 0.01 || (int) $afterDebit['is_verified'] !== 1) {
                    throw new RuntimeException('Wallet balance changed before verification could complete.');
                }
                $description = VERIFICATION_WALLET_DESCRIPTION;
                $gateway = 'internal';
                $method = 'wallet';
                $externalRef = 'VERIFY-WALLET-' . $currentUserId . '-' . bin2hex(random_bytes(8));
                $status = 'completed';
                $stmt = $conn->prepare('INSERT INTO transactions (user_id, amount, type, payment_method, gateway, description, external_ref, status, completed_at) VALUES (?, ?, \'debit\', ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
                $stmt->bind_param('idsssss', $currentUserId, $amount, $method, $gateway, $description, $externalRef, $status);
                if (!$stmt->execute()) throw new RuntimeException('Could not record the wallet payment.');
                $stmt = $conn->prepare('SELECT balance FROM users WHERE id = ?');
                $stmt->bind_param('i', $currentUserId);
                $stmt->execute();
                $newBalance = (float) ($stmt->get_result()->fetch_assoc()['balance'] ?? 0);
                $conn->commit();
                try {
                    createNotification($currentUserId, 'Akaunti imethibitishwa', 'Uthibitisho umekamilika. Sasa unaweza kutumia bei za chini za akaunti zilizothibitishwa.', 'success', 'user', ['source' => 'verification']);
                    logActivity($currentUserId, 'verification_wallet_payment_completed', 'Account verified after confirmed wallet debit of 5000 TZS');
                } catch (Throwable $noticeError) {
                    error_log('Verification notification failed: ' . $noticeError->getMessage());
                }
                try {
                    sendVerificationSuccessEmail($account['email'], $account['username'], 'wallet');
                } catch (Throwable $emailError) {
                    error_log('Verification email failed for user ' . $currentUserId . ': ' . $emailError->getMessage());
                }
                verificationReply(true, 'Payment received. Your account is now verified.', ['verified' => true, 'balance' => $newBalance]);
            } catch (Throwable $e) {
                try { $conn->rollback(); } catch (Throwable $ignored) {}
                error_log('Wallet verification failed for user ' . $currentUserId . ': ' . $e->getMessage());
                verificationReply(false, 'The wallet payment could not be completed. No payment was recorded. Please try again.');
            }
        }

        if ($action === 'initiate_verification_payment') {
            if (empty($input['confirmed'])) verificationReply(false, 'Please confirm the 5,000 TZS mobile-money payment before continuing.');
            $phone = formatPhoneNumber($input['phone'] ?? $account['phone'] ?? '');
            if (!$phone) verificationReply(false, 'Enter a valid Tanzania mobile-money number.');
            $reference = 'TXN' . time() . random_int(1000, 9999);
            $name = trim((string) ($input['name'] ?? $account['username']));
            if (strlen($name) < 2 || strlen($name) > 120) verificationReply(false, 'Enter the payer name used for the mobile-money payment.');
            $email = trim((string) $account['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) verificationReply(false, 'Add a valid email address to your account before paying.');
            $payload = [
                'user_id' => PALMPESA_ACCOUNT_ID,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'amount' => VERIFICATION_FEE_TZS,
                'transaction_id' => $reference,
                'address' => 'Royal HQ',
                'postcode' => '00000',
                'buyer_uuid' => random_int(100000, 999999),
            ];
            $response = callAPI('POST', PALMPESA_API_URL . '/pay-via-mobile', $payload);
            $providerOrderId = palmPesaOrderId($response);
            if ($providerOrderId === '') {
                verificationReply(false, palmPesaProviderMessage($response) ?: 'PalmPesa did not return a payment reference. Check the number and try again.', ['provider_status' => (int) ($response['_http_status'] ?? 0)]);
            }
            $description = VERIFICATION_PAYMENT_DESCRIPTION;
            $amount = VERIFICATION_FEE_TZS;
            $method = 'mobile_money';
            $gateway = 'palmpesa';
            $status = 'pending';
            $stmt = $conn->prepare('INSERT INTO transactions (user_id, amount, type, payment_method, gateway, description, external_ref, status) VALUES (?, ?, \'debit\', ?, ?, ?, ?, ?)');
            $stmt->bind_param('idsssss', $currentUserId, $amount, $method, $gateway, $description, $providerOrderId, $status);
            if (!$stmt->execute()) {
                error_log('Could not save PalmPesa verification transaction for user ' . $currentUserId);
                verificationReply(false, 'Payment was started but could not be recorded. Contact support before trying again.');
            }
            verificationReply(true, 'Payment prompt sent. Approve it on your phone to verify your account.', ['order_id' => $providerOrderId, 'status' => 'PENDING']);
        }

        if ($action === 'check_verification_status') {
            $orderId = trim((string) ($input['order_id'] ?? ''));
            if ($orderId === '' || strlen($orderId) > 100) verificationReply(false, 'Invalid payment reference.');
            $description = VERIFICATION_PAYMENT_DESCRIPTION;
            $stmt = $conn->prepare('SELECT id, amount, status FROM transactions WHERE external_ref = ? AND user_id = ? AND description = ? LIMIT 1');
            $stmt->bind_param('sis', $orderId, $currentUserId, $description);
            $stmt->execute();
            $transaction = $stmt->get_result()->fetch_assoc();
            if (!$transaction || (int) round((float) $transaction['amount']) !== VERIFICATION_FEE_TZS) verificationReply(false, 'Verification payment was not found.');
            if (strtolower((string) $transaction['status']) === 'completed') verificationReply(true, 'Your account is verified.', ['status' => 'COMPLETED', 'verified' => true]);
            if (strtolower((string) $transaction['status']) === 'failed') verificationReply(true, 'Payment failed.', ['status' => 'FAILED']);
            $response = callAPI('POST', PALMPESA_API_URL . '/order-status', ['order_id' => $orderId]);
            if (palmPesaRequestFailed($response)) {
                verificationReply(false, palmPesaProviderMessage($response) ?: 'Could not check the PalmPesa payment yet. Your payment remains pending; try checking again shortly.', ['status' => 'ERROR', 'retryable' => true]);
            }
            $status = palmPesaPaymentStatus($response);
            if ($status === 'COMPLETED') {
                try {
                    completeVerificationPayment($orderId, $currentUserId);
                    verificationReply(true, 'Payment received. Your account is now verified.', ['status' => 'COMPLETED', 'verified' => true]);
                } catch (Throwable $e) {
                    error_log('PalmPesa verification completion failed for ' . $orderId . ': ' . $e->getMessage());
                    verificationReply(false, 'Payment was confirmed but verification did not finish. Contact support with your payment reference.');
                }
            }
            if ($status === 'FAILED') {
                $failed = 'failed';
                $stmt = $conn->prepare("UPDATE transactions SET status = ? WHERE id = ? AND status = 'pending'");
                $stmt->bind_param('si', $failed, $transaction['id']);
                $stmt->execute();
            }
            verificationReply(true, 'Payment status checked.', ['status' => $status]);
        }
    }

    if ($action === 'initiate_payment') {
        $phone = formatPhoneNumber($input['phone'] ?? '');
        $amount = filter_var($input['amount'] ?? null, FILTER_VALIDATE_INT);
        $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $name = trim((string) ($input['name'] ?? ''));
        if (!$phone || !$amount || $amount < MINIMUM_TOPUP || $amount > MAXIMUM_TOPUP || !$email || $name === '') {
            verificationReply(false, 'Please provide a valid amount (minimum 1,000 TZS) and complete payment details.');
        }
        $transactionId = 'TXN' . time() . random_int(1000, 9999);
        $payload = [
            'user_id' => PALMPESA_ACCOUNT_ID,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'amount' => (int) $amount,
            'transaction_id' => $transactionId,
            'address' => 'Royal HQ',
            'postcode' => '00000',
            'buyer_uuid' => random_int(100000, 999999),
        ];
        $response = callAPI('POST', PALMPESA_API_URL . '/pay-via-mobile', $payload);
        $orderId = palmPesaOrderId($response);
        if ($orderId !== '') {
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, payment_method, gateway, description, external_ref, status) VALUES (?, ?, 'credit', 'mobile_money', 'palmpesa', ?, ?, 'pending')");
            $description = 'Top-up via PalmPesa';
            $stmt->bind_param('idss', $currentUserId, $amount, $description, $orderId);
            if (!$stmt->execute()) verificationReply(false, 'Payment was started but could not be recorded. Contact support before trying again.');
            verificationReply(true, 'Payment prompt sent to your phone.', ['order_id' => $orderId, 'message' => 'Ombi la malipo limetumwa kwenye simu yako.']);
        }
        verificationReply(false, palmPesaProviderMessage($response) ?: 'PalmPesa did not return a payment reference. Please try again.', ['provider_status' => (int) ($response['_http_status'] ?? 0)]);
    }

    if ($action === 'check_status') {
        $orderId = trim((string) ($input['order_id'] ?? ''));
        if ($orderId === '' || strlen($orderId) > 100) verificationReply(false, 'Invalid payment reference.');
        $stmt = $conn->prepare("SELECT id FROM transactions WHERE external_ref = ? AND user_id = ? AND type = 'credit' LIMIT 1");
        $stmt->bind_param('si', $orderId, $currentUserId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) verificationReply(false, 'Top-up payment was not found for this account.');
        $response = callAPI('POST', PALMPESA_API_URL . '/order-status', ['order_id' => $orderId]);
        if (palmPesaRequestFailed($response)) {
            verificationReply(false, palmPesaProviderMessage($response) ?: 'Could not check the PalmPesa payment yet. Your top-up remains pending; check again shortly.', ['status' => 'ERROR', 'retryable' => true]);
        }
        $status = palmPesaPaymentStatus($response);
        if ($status === 'COMPLETED') {
            try {
                $credit = completeTopupTransaction($orderId);
                verificationReply(true, 'Payment completed.', ['status' => 'COMPLETED', 'credited' => $credit['credited'], 'balance' => $credit['balance']]);
            } catch (Throwable $e) {
                error_log('Top-up completion failed for ' . $orderId . ': ' . $e->getMessage());
                verificationReply(false, 'Payment was confirmed but the balance could not be updated yet.');
            }
        }
        if ($status === 'FAILED') {
            $stmt = $conn->prepare("UPDATE transactions SET status = 'failed' WHERE external_ref = ? AND user_id = ? AND type = 'credit' AND status = 'pending'");
            $stmt->bind_param('si', $orderId, $currentUserId);
            $stmt->execute();
        }
        verificationReply(true, 'Payment status checked.', ['status' => $status]);
    }
}
// If not a POST request, show the HTML form
?>
<!DOCTYPE html>
<html lang="sw">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php pwa_head(); ?>
    <title><?php echo APP_NAME; ?> - Malipo</title>
    <link rel="stylesheet" href="assets/topup-tailwind.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f3f4f6;
        }
        body.min-h-screen { min-height: 100vh; background: radial-gradient(900px 500px at 8% -5%, #dceafe 0, transparent 55%), radial-gradient(760px 500px at 100% 100%, #e3e7ff 0, transparent 55%), #f4f6fb; }
        #paymentForm { max-width: 470px; border: 1px solid rgba(108,92,231,.12); border-radius: 28px; padding: 2rem; box-shadow: 0 24px 70px rgba(43,54,116,.14); }
        #paymentForm input { min-height: 50px; border-color: #e4e8f2; border-radius: 14px; }
        #paymentForm input:focus { box-shadow: 0 0 0 4px rgba(59,130,246,.12); }
        #submitBtn { border-radius: 15px; min-height: 54px; letter-spacing: .01em; }
        .payment-note { border: 1px solid #e7eaff; background: linear-gradient(135deg,#f7f8ff,#f2fbff); border-radius: 16px; padding: .8rem .9rem; color: #526174; font-size: .76rem; line-height: 1.55; }
        @media(max-width:480px) { #paymentForm { padding: 1.45rem 1.2rem; border-radius: 23px; } }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }

        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .success-checkmark {
            color: #10b981;
            font-size: 5rem;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .error-cross {
            color: #ef4444;
            font-size: 5rem;
            animation: popIn 0.5s;
        }

        @keyframes popIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 p-4">

    <div id="paymentForm" class="glass-card w-full max-w-md rounded-2xl p-8 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-500 to-indigo-600"></div>

        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600">
                <i class="fas fa-wallet text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 tracking-tight"><?php echo APP_NAME; ?></h1>
            <p class="text-gray-500 text-sm">Salama, rahisi na ya haraka kwa simu yako</p>
        </div>

        <div class="payment-note mb-5"><i class="fas fa-shield-alt text-blue-600 mr-1"></i> Lipa kupitia mobile money. Hakikisha namba yako inapokea ombi la malipo kabla ya kuendelea.</div>
        <form id="payForm" onsubmit="handlePayment(event)" class="space-y-5">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Kiasi (TZS)</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-gray-400 font-bold">TSh</span>
                    <input type="number" id="amount" name="amount" required placeholder="1000" min="<?= (int) MINIMUM_TOPUP ?>" max="<?= (int) MAXIMUM_TOPUP ?>"
                        class="w-full pl-14 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all font-bold text-gray-700 text-lg">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Jina Kamili</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-gray-400"><i class="fas fa-user"></i></span>
                    <input type="text" id="name" name="name" required placeholder="Mfano: Royal"
                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all text-gray-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Barua Pepe</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-gray-400"><i class="fas fa-envelope"></i></span>
                    <input type="email" id="email" name="email" required placeholder="royal@example.com"
                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all text-gray-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Namba ya Simu
                    (PalmPesa mobile money)</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-gray-400"><i class="fas fa-mobile-alt"></i></span>
                    <input type="tel" id="phone" name="phone" required placeholder="0744000000"
                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all text-gray-700 font-medium">
                </div>
                <p class="text-xs text-gray-400 mt-1 ml-1">Ingiza namba inayoanza na 06 au 07.</p>
            </div>

            <button type="submit" id="submitBtn"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center gap-2">
                <span>Lipa Sasa</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-xs text-gray-400">Imelindwa na <?php echo APP_NAME; ?> Technology</p>
        </div>
    </div>

    <div id="processingScreen"
        class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl animate-bounce-in">
            <div class="loader mx-auto mb-6"></div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Angalia Simu Yako</h2>
            <p class="text-gray-600 mb-6">Ombi la malipo limetumwa. Tafadhali weka namba ya siri kwenye simu yako
                kukamilisha muamala.</p>
            <div class="bg-yellow-50 text-yellow-800 text-sm py-2 px-4 rounded-lg mb-4">
                <i class="fas fa-spinner fa-spin mr-2"></i> Tunasubiri malipo...
            </div>
            <p class="text-xs text-gray-400">Usi-refresh ukurasa huu.</p>
        </div>
    </div>

    <div id="successScreen"
        class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
            <div class="mb-4 flex justify-center">
                <i class="fas fa-check-circle success-checkmark"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Malipo Yamekamilika!</h2>
            <p class="text-gray-600 mb-2">Asante kwa kutumia <?php echo APP_NAME; ?>. Muamala wako umefanikiwa.</p>
            <p id="successBalance" class="text-indigo-700 font-bold mb-6" aria-live="polite"></p>
            <button onclick="resetForm()"
                class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded-xl shadow transition-all">
                Fanya Malipo Mengine
            </button>
        </div>
    </div>

    <div id="errorScreen"
        class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
            <div class="mb-4 flex justify-center">
                <i class="fas fa-times-circle error-cross"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Malipo Yameshindikana</h2>
            <p id="errorMsg" class="text-gray-600 mb-6">Kuna tatizo limejitokeza au muda umekwisha.</p>
            <button onclick="hideScreens()"
                class="w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 rounded-xl shadow transition-all">
                Jaribu Tena
            </button>
        </div>
    </div>

    <script>
        let pollingInterval;
        let pollAttempts = 0;
        const MAX_ATTEMPTS = 40;

        async function handlePayment(e) {
            e.preventDefault();

            const btn = document.getElementById('submitBtn');
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Inachakata...';

            const formData = {
                action: 'initiate_payment',
                amount: document.getElementById('amount').value,
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value
            };

            try {
                const response = await fetch('topup.php', {   // <-- changed to topup.php
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success && result.order_id) {
                    showProcessingScreen();
                    startPolling(result.order_id);
                } else {
                    showError(result.message || 'Hitilafu imetokea.');
                }

            } catch (err) {
                console.error(err);
                showError('Tatizo la mtandao. Tafadhali jaribu tena.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        function startPolling(orderId) {
            pollAttempts = 0;
            pollingInterval = setInterval(async () => {
                pollAttempts++;
                try {
                    const response = await fetch('topup.php', {   // <-- changed to topup.php
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'check_status', order_id: orderId })
                    });
                    const result = await response.json();

                    if (result.success) {
                        if (result.status === 'COMPLETED') {
                            stopPolling();
                            showSuccess(result.balance);
                        } else if (result.status === 'FAILED' || result.status === 'CANCELLED') {
                            stopPolling();
                            showError('Malipo yamesitishwa au yameshindikana.');
                        }
                    } else if (result.status === 'ERROR') {
                        stopPolling();
                        showError(result.message || 'PalmPesa status could not be checked. Your payment may still be pending.');
                    }

                    if (pollAttempts >= MAX_ATTEMPTS) {
                        stopPolling();
                        showError('Muda wa malipo umekwisha. Tafadhali jaribu tena.');
                    }

                } catch (err) {
                    console.error("Polling error", err);
                }
            }, 3000);
        }

        function stopPolling() {
            clearInterval(pollingInterval);
        }

        function showProcessingScreen() {
            document.getElementById('processingScreen').classList.remove('hidden');
        }

        function showSuccess(balance) {
            document.getElementById('processingScreen').classList.add('hidden');
            document.getElementById('successScreen').classList.remove('hidden');
            if (typeof balance === 'number' && Number.isFinite(balance)) {
                document.getElementById('successBalance').textContent = 'New wallet balance: TSh ' + balance.toLocaleString('en-US');
            }
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'royal-topup-complete', balance: balance }, window.location.origin);
            }
        }

        function showError(msg) {
            stopPolling();
            document.getElementById('processingScreen').classList.add('hidden');
            document.getElementById('errorMsg').innerText = msg;
            document.getElementById('errorScreen').classList.remove('hidden');
        }

        function hideScreens() {
            document.getElementById('processingScreen').classList.add('hidden');
            document.getElementById('successScreen').classList.add('hidden');
            document.getElementById('errorScreen').classList.add('hidden');
        }

        function resetForm() {
            document.getElementById('payForm').reset();
            hideScreens();
        }
    </script>
    <?php pwa_foot(); ?>
