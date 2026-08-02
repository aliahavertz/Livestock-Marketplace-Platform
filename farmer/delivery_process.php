<?php
session_start();
require_once '../db_connect.php';
require_once '../pusher/pusher_config.php';
include '../inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['farmer_id'])) {
    $order_id = $_POST['order_id'];
    
    $est_date = !empty($_POST['estimated_date']) ? $_POST['estimated_date'] : null;
    $notes = $_POST['delivery_notes'];    

    try {
        $pdo->beginTransaction();

        $stmtFetch = $pdo->prepare("SELECT * FROM delivery WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmtFetch->execute([$order_id]);
        $latest = $stmtFetch->fetch(PDO::FETCH_ASSOC);

        if (!$latest) {
             throw new Exception("Original delivery record not found.");
        }

        $stmtInsert = $pdo->prepare("INSERT INTO delivery (
            order_id, 
            deliveryfee,      
            shipping_method, 
            deliverystatus, 
            delivery_notes, 
            deliverydate, 
            recipient_name, 
            deliveryaddress, 
            city, 
            postcode, 
            state, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmtInsert->execute([
            $order_id,
            $latest['deliveryfee'],    
            $latest['shipping_method'],
            $latest['deliverystatus'],
            $notes,
            $est_date, 
            $latest['recipient_name'],
            $latest['deliveryaddress'],
            $latest['city'],
            $latest['postcode'],
            $latest['state']
        ]);
        
        $stmtGetCustomer = $pdo->prepare("SELECT customer_id FROM orders WHERE order_id = ?");
        $stmtGetCustomer->execute([$order_id]);
        $customer_row = $stmtGetCustomer->fetch(PDO::FETCH_ASSOC);

        if (!$customer_row || empty($customer_row['customer_id'])) {
            throw new Exception("Could not send notification: No valid customer found linked to Order ID " . $order_id);
        }
        
        $final_customer_id = (int)$customer_row['customer_id'];

        $notification_title = "Delivery Update Received";
        $notification_msg = "Delivery Update: " . (!empty($notes) ? '"' . substr($notes, 0, 60) . '..."' : "New delivery date scheduled.");

        notify($pdo, $final_customer_id, 'customer', $notification_title, $notification_msg);

        if (isset($pusher)) {
            $pusher_payload = [
                'title' => $notification_title,
                'message' => $notification_msg,
                'order_id' => $order_id
            ];
            
            $pusher->trigger('customer-channel-' . $final_customer_id, 'new-delivery-update', $pusher_payload);
        }

        if (ob_get_length()) ob_clean();

        $pdo->commit();
        header("Location: manage_order.php?msg=History log saved!");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}