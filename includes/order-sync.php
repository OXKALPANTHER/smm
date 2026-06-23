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

/**
 * Sync a single user's non-final orders with the live provider.
 *
 * @param mixed $conn    MySQLiCompatibility connection
 * @param int   $user_id
 * @param bool  $force   bypass the per-session throttle (e.g. manual refresh)
 * @return int           number of orders whose status changed
 */
function syncUserOrders($conn, $user_id, $force = false) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return 0;
    }

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
        "SELECT id, external_order_id, status, price, refund_amount
           FROM orders
          WHERE user_id = ?
            AND external_order_id IS NOT NULL
            AND external_order_id <> ''
            AND LOWER(status) NOT IN
                ('completed','complete','canceled','cancelled','refunded','partial','failed','error')
          ORDER BY id DESC
          LIMIT 30"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($rows)) {
        return 0;
    }

    try {
        $api = new APIHandler('boost');
    } catch (Exception $e) {
        error_log("syncUserOrders: cannot init API: " . $e->getMessage());
        return 0;
    }

    $changed = 0;

    foreach ($rows as $o) {
        $info = $api->getOrderStatus($o['external_order_id']);
        if (empty($info['success'])) {
            continue;
        }

        $newStatus = trim((string)($info['status'] ?? ''));
        if ($newStatus === '' || strcasecmp($newStatus, 'unknown') === 0) {
            continue;
        }

        $low         = strtolower($newStatus);
        $isCompleted = strpos($low, 'complet') !== false;
        $isCanceled  = strpos($low, 'cancel') !== false || strpos($low, 'refund') !== false;
        $unchanged   = strcasecmp($newStatus, (string)$o['status']) === 0;

        if ($unchanged && !$isCanceled) {
            continue; // nothing to do
        }

        $conn->begin_transaction();
        try {
            if ($isCompleted) {
                $stmt = $conn->prepare(
                    "UPDATE orders
                        SET status = ?, progress = 100,
                            completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                      WHERE id = ?"
                );
                $stmt->bind_param("si", $newStatus, $o['id']);
                $stmt->execute();

            } elseif ($isCanceled && $o['refund_amount'] === null) {
                // Provider canceled/refunded -> return the money once.
                $price = (float)$o['price'];

                $stmt = $conn->prepare(
                    "UPDATE orders
                        SET status = ?, refund_amount = ?,
                            refund_reason = 'Provider canceled/refunded',
                            updated_at = CURRENT_TIMESTAMP
                      WHERE id = ?"
                );
                $stmt->bind_param("sdi", $newStatus, $price, $o['id']);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->bind_param("di", $price, $user_id);
                $stmt->execute();

                $desc = "Refund for canceled order #{$o['id']}";
                $ext  = (string)$o['external_order_id'];
                $stmt = $conn->prepare(
                    "INSERT INTO transactions
                        (user_id, order_id, amount, type, payment_method, gateway,
                         description, external_ref, status, completed_at)
                     VALUES (?, ?, ?, 'credit', 'balance', 'boost', ?, ?, 'completed', CURRENT_TIMESTAMP)"
                );
                $stmt->bind_param("iidss", $user_id, $o['id'], $price, $desc, $ext);
                $stmt->execute();

                logActivity($user_id, 'order_refunded',
                    "Order #{$o['id']} refunded {$price} TZS (provider: {$newStatus})", 'success');

            } else {
                // In progress / processing / partial-but-not-final etc.
                $stmt = $conn->prepare(
                    "UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
                );
                $stmt->bind_param("si", $newStatus, $o['id']);
                $stmt->execute();
            }

            $conn->commit();
            $changed++;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("syncUserOrders order #{$o['id']}: " . $e->getMessage());
        }
    }

    return $changed;
}
