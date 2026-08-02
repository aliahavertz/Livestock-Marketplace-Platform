<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['farmer_id'])) { die("Unauthorized"); }

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($order_id <= 0 || empty($action)) {
    header("Location: manage_order.php?error=" . urlencode("Invalid order or action."));
    exit();
}

try {
    $pdo->beginTransaction();

    $custStmt = $pdo->prepare("SELECT customer_id FROM orders WHERE order_id = ?");
    $custStmt->execute([$order_id]);
    $orderData = $custStmt->fetch(PDO::FETCH_ASSOC);

    if (!$orderData) {
        throw new Exception("Order record not found.");
    }
    
    $customer_id = $orderData['customer_id'];

    if ($action === 'approve') {
        $stmt = $pdo->prepare("SELECT livestock_id FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $updateOrder = $pdo->prepare("UPDATE orders SET status = 'Terminated' WHERE order_id = ?");
        $updateOrder->execute([$order_id]);

        $updateItems = $pdo->prepare("UPDATE order_items SET item_status = 'Terminated' WHERE order_id = ?");
        $updateItems->execute([$order_id]);

        if (!empty($items)) {
            $placeholders = implode(',', array_fill(0, count($items), '?'));
            $updateLivestock = $pdo->prepare("UPDATE livestock SET availability_status = 'Available' WHERE livestock_id IN ($placeholders)");
            $updateLivestock->execute($items);
        }

        $msg = "Order successfully cancelled and livestock returned to inventory.";
        
        $notif_title = "Cancellation Request Approved";
        $notif_msg = "Your request to cancel Order #$order_id has been approved. The order status is now Terminated.";

    } else {
        $updateOrder = $pdo->prepare("UPDATE orders SET status = 'Preparing' WHERE order_id = ?");
        $updateOrder->execute([$order_id]);

        $updateItems = $pdo->prepare("UPDATE order_items SET item_status = 'Preparing' WHERE order_id = ?");
        $updateItems->execute([$order_id]);

        $msg = "Cancellation request rejected. Order returned to preparing.";
        
        $notif_title = "Cancellation Request Rejected";
        $notif_msg = "Your request to cancel Order #$order_id was declined. The farmer is currently preparing your order.";
    }

    $notifSql = "INSERT INTO notifications (user_id, user_type, title, message, is_read, created_at) VALUES (?, 'customer', ?, ?, FALSE, NOW())";
    $notifStmt = $pdo->prepare($notifSql);
    $notifStmt->execute([$customer_id, $notif_title, $notif_msg]);

    $pdo->commit();
    header("Location: manage_order.php?msg=" . urlencode($msg));
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: manage_order.php?error=" . urlencode($e->getMessage()));
    exit();
}