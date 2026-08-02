<?php
session_start();
require_once '../db_connect.php';
require_once '../inc/functions.php';
include '../inc/numbers.php';

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$order_id = $_GET['order_id'] ?? null;
$new_status = $_GET['status'] ?? null;

$reason = isset($_GET['status_reason']) ? trim($_GET['status_reason']) : null;

if ($order_id && $new_status) {

    try {

        $info_sql = "SELECT o.customer_id, l.name as animal_name 
                     FROM orders o 
                     JOIN order_items oi ON o.order_id = oi.order_id 
                     JOIN livestock l ON oi.livestock_id = l.livestock_id 
                     WHERE o.order_id = :oid LIMIT 1";

        $info_stmt = $pdo->prepare($info_sql);
        $info_stmt->execute([':oid' => $order_id]);
        $order_info = $info_stmt->fetch(PDO::FETCH_ASSOC);

        if ($order_info) {

            $sql = "UPDATE order_items 
                    SET item_status = :status 
                    WHERE order_id = :oid";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':status' => $new_status,
                ':oid' => $order_id
            ]);

            if (($new_status === 'Rejected' || $new_status === 'Terminated') && !empty($reason)) {
                $sql2 = "UPDATE orders 
                         SET status = :status, rejection_reason = :reason 
                         WHERE order_id = :oid";
                
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([
                    ':status' => $new_status,
                    ':reason' => $reason,
                    ':oid' => $order_id
                ]);
            } else {
                $sql2 = "UPDATE orders 
                         SET status = :status 
                         WHERE order_id = :oid";

                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([
                    ':status' => $new_status,
                    ':oid' => $order_id
                ]);
            }

            if ($stmt->rowCount() > 0 || $stmt2->rowCount() > 0) {
                
                $notification_msg = "Your Order #" . formatOrderNumber($order_id) . " for {$order_info['animal_name']} is now $new_status.";
                
                if (!empty($reason)) {
                    $notification_msg .= " Reason: " . $reason;
                }

                notify(
                    $pdo,
                    $order_info['customer_id'],
                    'customer',
                    "Order Update",
                    $notification_msg
                );
                
                header("Location: manage_order.php?status=All&msg=success&id=" . urlencode(formatOrderNumber($order_id)) . "&new_val=" . urlencode($new_status));
                exit();
            } else {
                header("Location: manage_order.php?info=no_change");
                exit();
            }
        } else {
            die("Order not found.");
        }

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }

} else {
    die("Missing parameters. ID: $order_id, Status: $new_status");
}
?>