<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['customer_id']) || !isset($_POST['order_id'])) {
    header("Location: my_orders.php?error=invalid_request");
    exit();
}

$order_id = (int)$_POST['order_id'];
$customer_id = (int)$_SESSION['customer_id'];

try {
    $check_sql = "SELECT order_id FROM orders 
                  WHERE order_id = :oid AND customer_id = :cid 
                  AND status NOT IN ('Terminated', 'Delivered', 'Refunded', 'Cancelled Order')";
    
    $stmt = $pdo->prepare($check_sql);
    $stmt->execute(['oid' => $order_id, 'cid' => $customer_id]);
    $order = $stmt->fetch();

    if ($order) {
        $pdo->beginTransaction();

        $update_order = "UPDATE orders SET status = 'Cancelled Order' WHERE order_id = :oid";
        $pdo->prepare($update_order)->execute(['oid' => $order_id]);

        $update_items = "UPDATE order_items SET item_status = 'Cancelled Order' WHERE order_id = :oid";
        $pdo->prepare($update_items)->execute(['oid' => $order_id]);

        $pdo->commit();
        
        header("Location: my_orders.php?msg=cancellation_requested");
        exit();
    } else {
        header("Location: my_orders.php?error=order_not_eligible_or_not_found");
        exit();
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Database Error: " . $e->getMessage());
}