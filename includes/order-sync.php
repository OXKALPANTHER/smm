<?php
/**
 * Order status synchronisation.
 *
 * The provider processes orders asynchronously on their side, so an order that
 * is "Pending" when we place it only reaches "Completed"/"Partial"/"Canceled"
 * later. Nothing pushes that change to us, so we pull it: whenever a user looks
 * at their orders we re-query the provider for any order that isn't yet in a
 * final state and reflect the latest status locally.
 *
 * If the provider cancels/refunds an order, the user is refunded exactly once
 * (guarded by orders.refund_amount being NULL until we refund).
 */

require_once __DIR__ . '/APIHandler.php';

/** Canonicalize provider status aliases so a completed order is synced once. */
function normalizeSyncedOrderStatus($status) {
    $status = trim((string)$status);
    $low = strtolower($status);
    if (strpos($low, 'partial') !== false) return 'Partial';
    if (strpos($low, 'complet') !== false || in_array($low, ['success', 'successful', 'approved', 'done', 'finished'], true)) return 'Completed';
    if (strpos($low, 'cancel') !== false || strpos($low, 'refund') !== false) return 'Canceled';
    if (in_array($low, ['failed', 'failure', 'error', 'rejected'], true)) return 'Failed';
    return $status;
}

/**
 * Sync a single user's non-final orders with the live provider.
 *
 * @param mixed $conn    MySQLiCompatibility connection
 * @param int   $user_id
 * @param bool  $force   bypass the per-session throttle (e.g. manual refresh)
 * @return int           number of orders whose status changed
 */
function syncUserOrders($conn, $user_id, $force = false, array &$notifications = []) {
    $notifications = [];
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return 0;
    }

    $userStmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $syncUser = $userStmt->get_result()->fetch_assoc() ?: [];

    // Throttle automatic syncs so navigating around doesn't hammer the provider.
    $throttleSeconds = 45;
    $sessionKey = 'orders_synced_at';
    if (!$force
        && isset($_SESSION[$sessionKey])
        && (time() - (int)$_SESSION[$sessionKey]) < $throttleSeconds) {
        return 0;
    }
    $_SESSION[$sessionKey] = time();

    // Pull orders that can still change. Anything already in a final state is
    // skipped (and never re-refunded).
    $stmt = $conn->prepare(
            "SELECT id, service_name, external_order_id, status, price, refund_amount, provider, gateway,
                quantity, delivered_quantity, remaining_quantity
           FROM orders
          WHERE user_id = ?
            AND external_order_id IS NOT NULL
            AND external_order_id <> ''
            AND LOWER(status) NOT IN
                ('completed','complete','success','successful','approved','done','finished',
                 'canceled','cancelled','refunded','partial','failed','failure','error','rejected')
          ORDER BY id DESC
          LIMIT 30"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($rows)) {
        return 0;
    }

    // Route each order to the provider saved at creation time so the status
    // query speaks the right protocol. The gateway is only a UI lane; older
    // rows without a provider still retain the historic Boost fallback.
    // Handlers are cached so we build at most one per provider.
    $handlers = [];
    $handlerFor = function ($provider, $gateway) use (&$handlers) {
        $provider = strtolower((string) $provider);
        if (!in_array($provider, ['fastway', 'boost'], true)) {
            $provider = ($gateway === 'partner') ? 'fastway' : 'boost';
        }
        if (!isset($handlers[$provider])) {
            try {
                $handlers[$provider] = new APIHandler($provider);
            } catch (Exception $e) {
                error_log("syncUserOrders: cannot init API ($provider): " . $e->getMessage());
                $handlers[$provider] = false;
            }
        }
        return $handlers[$provider];
    };

    $changed = 0;

    foreach ($rows as $o) {
        $api = $handlerFor($o['provider'] ?? '', $o['gateway'] ?? 'primary');
        if (!$api) {
            continue;
        }
        $info = $api->getOrderStatus($o['external_order_id']);
        if (empty($info['success'])) {
            continue;
        }

        $newStatus = trim((string)($info['status'] ?? ''));
        if ($newStatus === '' || strcasecmp($newStatus, 'unknown') === 0) {
            continue;
        }

        $normalizedStatus = normalizeSyncedOrderStatus($newStatus);
        $low = strtolower($normalizedStatus);
        $isCompleted = $normalizedStatus === 'Completed';
        $isPartial   = $normalizedStatus === 'Partial';
        $isCanceled  = $normalizedStatus === 'Canceled';
        $unchanged   = strcasecmp($normalizedStatus, (string)$o['status']) === 0;

        $orderQuantity = (int)($o['quantity'] ?? 0);
        $existingDelivered = (int)($o['delivered_quantity'] ?? 0);
        $existingRemaining = (int)($o['remaining_quantity'] ?? 0);

        $remainingQty = null;
        $deliveredQty = null;
        if (isset($info['remaining'])) {
            $remainingQty = (int)$info['remaining'];
        }
        if (isset($info['delivered'])) {
            $deliveredQty = (int)$info['delivered'];
        }
        if ($remainingQty === null && isset($info['remains'])) {
            $remainingQty = (int)$info['remains'];
        }
        if ($remainingQty === null && isset($info['remains_count'])) {
            $remainingQty = (int)$info['remains_count'];
        }
        if ($remainingQty === null && isset($info['left'])) {
            $remainingQty = (int)$info['left'];
        }
        if ($deliveredQty === null && isset($info['delivered_quantity'])) {
            $deliveredQty = (int)$info['delivered_quantity'];
        }
        if ($deliveredQty === null && isset($info['received'])) {
            $deliveredQty = (int)$info['received'];
        }
        if ($deliveredQty === null && $orderQuantity > 0 && $remainingQty !== null) {
            $deliveredQty = max(0, $orderQuantity - $remainingQty);
        }
        if ($remainingQty === null && $orderQuantity > 0 && $deliveredQty !== null) {
            $remainingQty = max(0, $orderQuantity - $deliveredQty);
        }

        $deliveredChanged = $deliveredQty !== null && $deliveredQty !== $existingDelivered;
        $remainingChanged = $remainingQty !== null && $remainingQty !== $existingRemaining;
        if ($unchanged && !$isCanceled && !$deliveredChanged && !$remainingChanged) {
            continue; // nothing to do
        }

        $conn->begin_transaction();
        try {
            $updatedDelivered = $deliveredQty !== null ? $deliveredQty : $existingDelivered;
            $updatedRemaining = $remainingQty !== null ? $remainingQty : $existingRemaining;
            if ($isCompleted) {
                if ($remainingQty === null && $orderQuantity > 0) {
                    $updatedRemaining = 0;
                }
                if ($deliveredQty === null && $orderQuantity > 0) {
                    $updatedDelivered = $orderQuantity;
                }

                $stmt = $conn->prepare(
                    "UPDATE orders
                        SET status = ?, progress = 100,
                            completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP,
                            delivered_quantity = ?, remaining_quantity = ?
                      WHERE id = ?"
                );
                $stmt->bind_param("siii", $normalizedStatus, $updatedDelivered, $updatedRemaining, $o['id']);
                $stmt->execute();

                if (!$unchanged) {
                    $notifications[] = [
                        'type' => 'success',
                        'message' => "Order #{$o['id']} imekamilika: {$normalizedStatus}.",
                    ];
                }

            } elseif ($isCanceled && $o['refund_amount'] === null) {
                $price = (float)$o['price'];
                $updatedDelivered = $deliveredQty !== null ? $deliveredQty : $existingDelivered;
                $updatedRemaining = $remainingQty !== null ? $remainingQty : $existingRemaining;
                $stmt = $conn->prepare(
                    "UPDATE orders
                        SET status = ?, refund_amount = ?,
                            refund_reason = 'Provider canceled/refunded',
                            updated_at = CURRENT_TIMESTAMP,
                            delivered_quantity = ?, remaining_quantity = ?
                      WHERE id = ?"
                );
                $stmt->bind_param("sdiii", $normalizedStatus, $price, $updatedDelivered, $updatedRemaining, $o['id']);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->bind_param("di", $price, $user_id);
                $stmt->execute();

                $desc = "Refund for canceled order #{$o['id']}";
                $ext  = (string)$o['external_order_id'];
                $gw   = ($o['gateway'] ?? 'primary');
                $stmt = $conn->prepare(
                    "INSERT INTO transactions
                        (user_id, order_id, amount, type, payment_method, gateway,
                         description, external_ref, status, completed_at)
                     VALUES (?, ?, ?, 'credit', 'balance', ?, ?, ?, 'completed', CURRENT_TIMESTAMP)"
                );
                $stmt->bind_param("iidsss", $user_id, $o['id'], $price, $gw, $desc, $ext);
                $stmt->execute();

                logActivity($user_id, 'order_refunded',
                    "Order #{$o['id']} refunded {$price} TZS (provider: {$newStatus})", 'success');

                if (!$unchanged) {
                    $notifications[] = [
                        'type' => 'danger',
                        'message' => "Order #{$o['id']} imekatizwa/imirudishwa: {$normalizedStatus}.",
                    ];
                }

            } else {
                // In progress / processing / partial-but-not-final etc.
                // Calculate progress based on status keywords
                $progress = 50; // default for unknown processing status
                if (strpos($low, 'partial') !== false) {
                    $progress = 75; // partial completion
                } elseif (strpos($low, 'process') !== false || strpos($low, 'in progress') !== false) {
                    $progress = 60; // actively processing
                } elseif (strpos($low, 'pending') !== false || strpos($low, 'queue') !== false) {
                    $progress = 20; // waiting in queue
                }
                
                $updatedDelivered = $deliveredQty !== null ? $deliveredQty : $existingDelivered;
                $updatedRemaining = $remainingQty !== null ? $remainingQty : $existingRemaining;
                $stmt = $conn->prepare(
                    "UPDATE orders SET status = ?, progress = ?, updated_at = CURRENT_TIMESTAMP,
                        delivered_quantity = ?, remaining_quantity = ?
                      WHERE id = ?"
                );
                $stmt->bind_param("siiii", $normalizedStatus, $progress, $updatedDelivered, $updatedRemaining, $o['id']);
                $stmt->execute();

                if (!$unchanged && (strpos($low, 'delay') !== false || strpos($low, 'late') !== false || strpos($low, 'hold') !== false)) {
                    $notifications[] = [
                        'type' => 'warning',
                        'message' => "Order #{$o['id']} inaonekana kuchelewa: {$newStatus}.",
                    ];
                }
            }

            $conn->commit();
            $changed++;
            if (!empty($syncUser['email']) && !$unchanged) {
                $refundForEmail = $isCanceled && $o['refund_amount'] === null ? (float) $o['price'] : 0;
                sendOrderStatusEmail(
                    $syncUser['email'],
                    $syncUser['username'] ?? 'Mteja',
                    $o['id'],
                    $o['service_name'] ?? 'order',
                    $normalizedStatus,
                    $refundForEmail
                );
            }
        } catch (Exception $e) {
            $conn->rollback();
            error_log("syncUserOrders order #{$o['id']}: " . $e->getMessage());
        }
    }

    return $changed;
}
