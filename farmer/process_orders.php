<?php
session_start();
require_once '../db_connect.php';
require_once '../pusher/pusher_config.php'; 
require_once '../inc/numbers.php'; 


if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_ids = $_POST['order_ids'] ?? [];
    $new_status = $_POST['bulk_status'] ?? '';
    $status_reason = $_POST['status_reason'] ?? '';
    $farmer_id = $_SESSION['farmer_id'];

    if (!empty($order_ids) && !empty($new_status)) {
        try {
            $pdo->beginTransaction();

            $checkPayment = $pdo->prepare("SELECT payment_status FROM payments WHERE order_id = ?");

            $updateItems = $pdo->prepare("UPDATE order_items SET item_status = ? 
                WHERE order_id = ? 
                AND livestock_id IN (
                    SELECT livestock_id FROM livestock WHERE farmer_id = ?
                )");

            $updateOrderMain = $pdo->prepare("UPDATE orders SET status = ?, status_reason = ? WHERE order_id = ?");
            $getCust = $pdo->prepare("SELECT customer_id FROM orders WHERE order_id = ?");
            $saveNotif = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, created_at) VALUES (?, 'customer', ?, ?, NOW())");

            foreach ($order_ids as $id) {
                $checkPayment->execute([$id]);
                $payment_status = $checkPayment->fetchColumn();

                if ($payment_status === 'Suspicious') {
                    continue; 
                }

                $updateItems->execute([$new_status, $id, $farmer_id]);
                $updateOrderMain->execute([$new_status, $status_reason, $id]);

                $getCust->execute([$id]);
                $customerId = $getCust->fetchColumn();

                if ($customerId) {

                    $formattedOrderNumber = formatOrderNumber($id);

                    $title = "Order Update: $new_status";
                    $message = "Your order #$formattedOrderNumber has been updated to: $new_status.";
                    
                    if (!empty($status_reason)) {
                        $message .= " Reason: " . $status_reason;
                    }

                    $saveNotif->execute([$customerId, $title, $message]);

                    if (isset($pusher)) {
                        $pusher->trigger('customer-channel-' . $customerId, 'order-updated', [
                            'order_id' => $id,
                            'order_number'   => $formattedOrderNumber,
                            'status'   => $new_status,
                            'message'  => $message
                        ]);
                    }
                }
            }

            $pdo->commit();
            header("Location: manage_order.php?msg=" . urlencode("Orders updated to $new_status."));
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            header("Location: manage_order.php?error=" . urlencode("Database Error: " . $e->getMessage()));
            exit();
        }
    } else {
        header("Location: manage_order.php?error=" . urlencode("Please select at least one order and a status."));
        exit();
    }
} else {
    header("Location: manage_order.php");
    exit();
}