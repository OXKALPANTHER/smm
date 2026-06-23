<?php
/**
 * Webhook Handler
 * Processes incoming webhooks from payment gateways and external APIs
 */

require_once 'config.php';

class WebhookHandler {
    
    private $payload;
    private $signature;
    private $event_type;
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->payload = file_get_contents('php://input');
        $this->signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
        
        $data = json_decode($this->payload, true);
        $this->event_type = $data['event'] ?? 'unknown';
    }
    
    /**
     * Verify webhook signature
     */
    public function verifySignature() {
        $computed_sig = hash_hmac('sha256', $this->payload, WEBHOOK_SECRET_KEY);
        return hash_equals($computed_sig, $this->signature);
    }
    
    /**
     * Process webhook based on event type
     */
    public function process() {
        if (!$this->verifySignature()) {
            return $this->respondError('Invalid signature', 401);
        }
        
        $data = json_decode($this->payload, true);
        
        switch ($this->event_type) {
            case 'order.completed':
                return $this->handleOrderCompleted($data);
                
            case 'order.failed':
                return $this->handleOrderFailed($data);
                
            case 'payment.completed':
                return $this->handlePaymentCompleted($data);
                
            case 'payment.failed':
                return $this->handlePaymentFailed($data);
                
            case 'refund.issued':
                return $this->handleRefundIssued($data);
                
            default:
                return $this->respondError('Unknown event type', 400);
        }
    }
    
    /**
     * Handle completed order
     */
    private function handleOrderCompleted($data) {
        try {
            $external_order_id = $data['order_id'] ?? null;
            $status = $data['status'] ?? 'Completed';
            
            if (!$external_order_id) {
                return $this->respondError('Missing order_id', 400);
            }
            
            // Update order status
            $stmt = $this->conn->prepare("UPDATE orders SET status = ? WHERE external_order_id = ?");
            $stmt->bind_param("ss", $status, $external_order_id);
            $stmt->execute();
            
            // Get order details for logging
            $stmt = $this->conn->prepare("SELECT id, user_id FROM orders WHERE external_order_id = ?");
            $stmt->bind_param("s", $external_order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                logActivity($row['user_id'], 'order_completed', "Order: $external_order_id", 'success');
            }
            
            return $this->respondSuccess("Order updated successfully");
            
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle failed order
     */
    private function handleOrderFailed($data) {
        try {
            $external_order_id = $data['order_id'] ?? null;
            $reason = $data['reason'] ?? 'Unknown';
            
            if (!$external_order_id) {
                return $this->respondError('Missing order_id', 400);
            }
            
            // Get order details
            $stmt = $this->conn->prepare("SELECT id, user_id, price FROM orders WHERE external_order_id = ?");
            $stmt->bind_param("s", $external_order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $this->conn->begin_transaction();
                
                try {
                    // Update order status
                    $status = 'Failed';
                    $stmt = $this->conn->prepare("UPDATE orders SET status = ? WHERE external_order_id = ?");
                    $stmt->bind_param("ss", $status, $external_order_id);
                    $stmt->execute();
                    
                    // Refund to user balance
                    $stmt = $this->conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->bind_param("di", $row['price'], $row['user_id']);
                    $stmt->execute();
                    
                    // Create refund transaction
                    $stmt = $this->conn->prepare("INSERT INTO transactions (user_id, amount, type, description, external_ref, status) VALUES (?, ?, 'refund', ?, ?, 'completed')");
                    $desc = "Automatic refund for failed order: $external_order_id";
                    $stmt->bind_param("idss", $row['user_id'], $row['price'], $desc, $external_order_id);
                    $stmt->execute();
                    
                    $this->conn->commit();
                    
                    logActivity($row['user_id'], 'order_failed', "Order: $external_order_id, Reason: $reason", 'failed');
                    
                    return $this->respondSuccess("Order failed, refund processed");
                    
                } catch (Exception $e) {
                    $this->conn->rollback();
                    throw $e;
                }
            }
            
            return $this->respondError('Order not found', 404);
            
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle completed payment
     */
    private function handlePaymentCompleted($data) {
        try {
            $external_ref = $data['transaction_id'] ?? null;
            $amount = $data['amount'] ?? 0;
            
            if (!$external_ref) {
                return $this->respondError('Missing transaction_id', 400);
            }
            
            // Find pending transaction
            $stmt = $this->conn->prepare("SELECT id, user_id FROM transactions WHERE external_ref = ? AND status = 'pending'");
            $stmt->bind_param("s", $external_ref);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $this->conn->begin_transaction();
                
                try {
                    // Update transaction status
                    $status = 'completed';
                    $stmt = $this->conn->prepare("UPDATE transactions SET status = ?, completed_at = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $status, $row['id']);
                    $stmt->execute();
                    
                    // Add balance to user (if amount doesn't already exist in DB)
                    $stmt = $this->conn->prepare("SELECT amount FROM transactions WHERE id = ?");
                    $stmt->bind_param("i", $row['id']);
                    $stmt->execute();
                    $trans_result = $stmt->get_result();
                    $transaction = $trans_result->fetch_assoc();
                    
                    if ($transaction) {
                        $stmt = $this->conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->bind_param("di", $transaction['amount'], $row['user_id']);
                        $stmt->execute();

                        // Reward the inviter on this user's first deposit.
                        applyReferralBonus($this->conn, $row['user_id'], $transaction['amount']);
                    }

                    $this->conn->commit();
                    
                    logActivity($row['user_id'], 'payment_completed', "Transaction: $external_ref, Amount: $amount", 'success');
                    
                    return $this->respondSuccess("Payment confirmed");
                    
                } catch (Exception $e) {
                    $this->conn->rollback();
                    throw $e;
                }
            }
            
            return $this->respondError('Transaction not found', 404);
            
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($data) {
        try {
            $external_ref = $data['transaction_id'] ?? null;
            $reason = $data['reason'] ?? 'Unknown';
            
            if (!$external_ref) {
                return $this->respondError('Missing transaction_id', 400);
            }
            
            // Update transaction status
            $stmt = $this->conn->prepare("UPDATE transactions SET status = ? WHERE external_ref = ? AND status = 'pending'");
            $status = 'failed';
            $stmt->bind_param("ss", $status, $external_ref);
            $stmt->execute();
            
            logActivity(null, 'payment_failed', "Transaction: $external_ref, Reason: $reason", 'failed');
            
            return $this->respondSuccess("Payment failure recorded");
            
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle refund issued
     */
    private function handleRefundIssued($data) {
        try {
            $order_id = $data['order_id'] ?? null;
            $refund_amount = $data['refund_amount'] ?? 0;
            
            if (!$order_id) {
                return $this->respondError('Missing order_id', 400);
            }
            
            // Get order details
            $stmt = $this->conn->prepare("SELECT user_id FROM orders WHERE external_order_id = ?");
            $stmt->bind_param("s", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $this->conn->begin_transaction();
                
                try {
                    // Refund to user balance
                    $stmt = $this->conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->bind_param("di", $refund_amount, $row['user_id']);
                    $stmt->execute();
                    
                    // Create refund transaction
                    $stmt = $this->conn->prepare("INSERT INTO transactions (user_id, amount, type, description, external_ref, status) VALUES (?, ?, 'refund', ?, ?, 'completed')");
                    $desc = "Refund issued for order: $order_id";
                    $stmt->bind_param("idss", $row['user_id'], $refund_amount, $desc, $order_id);
                    $stmt->execute();
                    
                    // Update order status
                    $status = 'Refunded';
                    $stmt = $this->conn->prepare("UPDATE orders SET status = ? WHERE external_order_id = ?");
                    $stmt->bind_param("ss", $status, $order_id);
                    $stmt->execute();
                    
                    $this->conn->commit();
                    
                    logActivity($row['user_id'], 'refund_issued', "Order: $order_id, Amount: $refund_amount", 'success');
                    
                    return $this->respondSuccess("Refund processed");
                    
                } catch (Exception $e) {
                    $this->conn->rollback();
                    throw $e;
                }
            }
            
            return $this->respondError('Order not found', 404);
            
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }
    
    /**
     * Send success response
     */
    private function respondSuccess($message) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'timestamp' => time()
        ]);
        exit;
    }
    
    /**
     * Send error response
     */
    private function respondError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'timestamp' => time()
        ]);
        exit;
    }
}

// Initialize and process webhook
$handler = new WebhookHandler($conn);
$handler->process();

?>
