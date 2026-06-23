<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'initiate_payment') {
        $phone = $input['phone'] ?? '';
        $amount = intval($input['amount'] ?? 0);
        $email = $input['email'] ?? '';
        $name = $input['name'] ?? '';

        // Validate inputs
        if (!$phone || !$amount || !$email || !$name) {
            http_response_code(400);
            exit(apiResponse(false, 'Tafadhali jaza taarifa zote kwa usahihi.'));
        }

        // Validate amount
        if ($amount < MINIMUM_TOPUP || $amount > MAXIMUM_TOPUP) {
            http_response_code(400);
            exit(apiResponse(false, 'Kiasi lazima kiwe kati ya ' . formatCurrency(MINIMUM_TOPUP) . ' na ' . formatCurrency(MAXIMUM_TOPUP)));
        }

        // Validate email
        if (!validateEmail($email)) {
            http_response_code(400);
            exit(apiResponse(false, 'Barua pepe si sahihi.'));
        }

        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($phone, '0') === 0) {
            $phone = '255' . substr($phone, 1);
        } elseif (strpos($phone, '255') !== 0) {
            http_response_code(400);
            exit(apiResponse(false, 'Namba ya simu si sahihi.'));
        }

        $transaction_id = "TXN" . time() . rand(1000, 9999);

        // Create pending transaction record
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description, external_ref, status) VALUES (?, ?, 'credit', ?, ?, 'pending')");
        $desc = "Top-up via MPESA - $phone";
        $stmt->bind_param("idss", $user_id, $amount, $desc, $transaction_id);
        if (!$stmt->execute()) {
            http_response_code(500);
            exit(apiResponse(false, 'Hitilafu imetokea wakati wa kuandaa muamala.'));
        }

        // Prepare payment request for MPESA
        $payload = [
            "user_id" => MPESA_USER_ID,
            "name" => sanitize($name),
            "email" => sanitize($email),
            "phone" => $phone,
            "amount" => $amount,
            "transaction_id" => $transaction_id,
            "currency" => CURRENCY_CODE
        ];

        // Call MPESA API
        $response = makeAPICall('mpesa', '/pay-via-mobile', 'POST', $payload);

        if ($response['success'] && isset($response['data']['order_id'])) {
            // Update transaction with order ID
            $stmt = $conn->prepare("UPDATE transactions SET external_ref = ? WHERE external_ref = ?");
            $stmt->bind_param("ss", $response['data']['order_id'], $transaction_id);
            $stmt->execute();

            logActivity($user_id, 'top_up_initiated', "Amount: {$amount}, Order: {$response['data']['order_id']}", 'success');

            http_response_code(200);
            exit(apiResponse(true, 'Ombi la malipo limetumwa kwenye simu yako.', [
                'order_id' => $response['data']['order_id'],
                'transaction_id' => $transaction_id
            ]));
        } else {
            // Update transaction to failed
            $stmt = $conn->prepare("UPDATE transactions SET status = 'failed' WHERE external_ref = ?");
            $stmt->bind_param("s", $transaction_id);
            $stmt->execute();

            logActivity($user_id, 'top_up_failed', "Error: " . ($response['error'] ?? 'Unknown error'), 'failed');

            http_response_code(500);
            exit(apiResponse(false, 'Hitilafu imetokea wakati wa kuanzisha malipo: ' . ($response['error'] ?? 'Unknown')));
        }

    } elseif ($action === 'check_status') {
        $order_id = $input['order_id'] ?? '';
        
        if (!$order_id) {
            http_response_code(400);
            exit(apiResponse(false, 'Order ID inahitajika.'));
        }

        // Check status from MPESA API
        $response = makeAPICall('mpesa', '/order-status', 'POST', ["order_id" => $order_id]);

        if ($response['success'] && isset($response['data']['payment_status'])) {
            $status = strtoupper($response['data']['payment_status']);

            if ($status === 'COMPLETED') {
                $stmt = $conn->prepare("SELECT id, user_id, amount FROM transactions WHERE external_ref = ? AND status = 'pending' LIMIT 1");
                $stmt->bind_param("s", $order_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $conn->begin_transaction();
                    try {
                        // Update transaction status
                        $stmt = $conn->prepare("UPDATE transactions SET status = 'completed' WHERE id = ?");
                        $stmt->bind_param("i", $row['id']);
                        $stmt->execute();
                        
                        // Add to user balance
                        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->bind_param("di", $row['amount'], $row['user_id']);
                        $stmt->execute();
                        
                        $conn->commit();
                        
                        logActivity($row['user_id'], 'top_up_completed', "Amount: {$row['amount']}, Order: {$order_id}", 'success');
                    } catch (Exception $e) {
                        $conn->rollback();
                        logActivity($user_id, 'top_up_completion_failed', "Error: " . $e->getMessage(), 'failed');
                    }
                }
            } elseif ($status === 'FAILED') {
                $stmt = $conn->prepare("UPDATE transactions SET status = 'failed' WHERE external_ref = ? AND status = 'pending'");
                $stmt->bind_param("s", $order_id);
                $stmt->execute();
                
                logActivity($user_id, 'top_up_failed', "Order: {$order_id}", 'failed');
            }

            exit(apiResponse(true, 'Status ya malipo: ' . $status, ['status' => $status]));
        } else {
            exit(apiResponse(true, 'Kusubiria malipo...', ['status' => 'PENDING']));
        }
    }
}

// If not a POST request, show the HTML form
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Malipo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f3f4f6; }
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
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .success-checkmark { color: #10b981; font-size: 5rem; animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .error-cross { color: #ef4444; font-size: 5rem; animation: popIn 0.5s; }
        @keyframes popIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
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
            <p class="text-gray-500 text-sm">Fanya malipo kwa usalama</p>
        </div>

        <form id="payForm" onsubmit="handlePayment(event)" class="space-y-5">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Kiasi (TZS)</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-gray-400 font-bold">TSh</span>
                    <input type="number" id="amount" name="amount" required placeholder="500" min="100"
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
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Namba ya Simu (M-Pesa/Tigo/Airtel)</label>
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

    <div id="processingScreen" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl animate-bounce-in">
            <div class="loader mx-auto mb-6"></div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Angalia Simu Yako</h2>
            <p class="text-gray-600 mb-6">Ombi la malipo limetumwa. Tafadhali weka namba ya siri kwenye simu yako kukamilisha muamala.</p>
            <div class="bg-yellow-50 text-yellow-800 text-sm py-2 px-4 rounded-lg mb-4">
                <i class="fas fa-spinner fa-spin mr-2"></i> Tunasubiri malipo...
            </div>
            <p class="text-xs text-gray-400">Usi-refresh ukurasa huu.</p>
        </div>
    </div>

    <div id="successScreen" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
            <div class="mb-4 flex justify-center">
                <i class="fas fa-check-circle success-checkmark"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Malipo Yamekamilika!</h2>
            <p class="text-gray-600 mb-6">Asante kwa kutumia <?php echo APP_NAME; ?>. Muamala wako umefanikiwa.</p>
            <button onclick="resetForm()" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded-xl shadow transition-all">
                Fanya Malipo Mengine
            </button>
        </div>
    </div>

    <div id="errorScreen" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
            <div class="mb-4 flex justify-center">
                <i class="fas fa-times-circle error-cross"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Malipo Yameshindikana</h2>
            <p id="errorMsg" class="text-gray-600 mb-6">Kuna tatizo limejitokeza au muda umekwisha.</p>
            <button onclick="hideScreens()" class="w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 rounded-xl shadow transition-all">
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
                            showSuccess();
                        } else if (result.status === 'FAILED' || result.status === 'CANCELLED') {
                            stopPolling();
                            showError('Malipo yamesitishwa au yameshindikana.');
                        }
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

        function showSuccess() {
            document.getElementById('processingScreen').classList.add('hidden');
            document.getElementById('successScreen').classList.remove('hidden');
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
</body>
</html>