<?php
session_start();
require_once '../db_connect.php';
require_once '../inc/functions.php';
include '../inc/numbers.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $decision = $_POST['decision']; 
    $rejection_reason = $_POST['rejection_reason'] ?? null; 
    $now = date('Y-m-d H:i:s');

    if (!$order_id || !$decision) {
        header("Location: manage_payments.php?error=Missing data");
        exit();
    }

    $formatted_order_id = formatOrderNumber($order_id);

    try {
        $pdo->beginTransaction();

        $get_info = $pdo->prepare("
            SELECT o.customer_id, o.status as current_order_status, pay.payment_status, oi.livestock_id, l.name as animal_name 
            FROM orders o 
            JOIN order_items oi ON o.order_id = oi.order_id 
            JOIN livestock l ON oi.livestock_id = l.livestock_id 
            LEFT JOIN payments pay ON o.order_id = pay.order_id
            WHERE o.order_id = ? 
            LIMIT 1
        ");
        $get_info->execute([$order_id]);
        $info = $get_info->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            throw new Exception("Order relation records not found.");
        }

        $customer_id = $info['customer_id'];
        $livestock_id = $info['livestock_id'];
        $animal_name = $info['animal_name'];

        if ($decision === 'Approved') {
            $new_status = 'Refunded';
            $livestock_status = 'Available'; 
            $completed_at = $now;
            $db_reason = null;
        } else {
            $new_status = 'Delivered'; 
            $livestock_status = 'Sold'; 
            $completed_at = null;
            $db_reason = !empty($rejection_reason) ? $rejection_reason : 'Requirements not met.';
        }

        $sql = "UPDATE orders SET 
                    status = :status, 
                    refund_completed_at = :completed_at,
                    refund_reason = :refund_reason
                WHERE order_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status' => $new_status,
            ':completed_at' => $completed_at, 
            ':refund_reason' => $db_reason,
            ':id' => $order_id
        ]);

        $sql_items = "UPDATE order_items SET item_status = :status WHERE order_id = :id";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute([
            ':status' => $new_status,
            ':id' => $order_id
        ]);

        $stmt = $pdo->prepare("UPDATE livestock SET availability_status = ? WHERE livestock_id = ?");
        $stmt->execute([$livestock_status, $livestock_id]);

        $notif_title = "Refund " . $decision;
        
        if ($decision === 'Approved') {
            $receipt_link = "<a href='/LivestockMarketplace/farmer/generate_receipt_refund.php?order_id=" . urlencode($order_id) . "&type=full' target='_blank' style='color: #1976d2; font-weight: bold; text-decoration: underline;'>Click Here</a>";
            
            $notif_msg = "Your full payment refund for " . $animal_name . " (Order #" . $formatted_order_id . ") has been processed successfully. View receipt here: " . $receipt_link;
        } else {
            $notif_msg = "Your refund request for " . $animal_name . " (Order #" . $formatted_order_id . ") was rejected. Reason: " . htmlspecialchars($db_reason);
        } 

        notify($pdo, $customer_id, 'customer', $notif_title, $notif_msg);

        $pdo->commit();
        header("Location: manage_payments.php?msg=Refund " . urlencode($decision) . " processed successfully.");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: manage_payments.php?error=Update failed: " . $e->getMessage());
        exit();
    }
}
?>