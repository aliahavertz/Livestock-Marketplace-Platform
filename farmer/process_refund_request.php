<?php
session_start();
require_once '../db_connect.php';
require_once '../inc/functions.php'; 
include '../inc/numbers.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $reason = $_POST['reason'];
    $notes = $_POST['notes'];
    $now = date('Y-m-d H:i:s');

    $targetDir = "../uploads/refunds/";
    
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . '_' . basename($_FILES["evidence"]["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

    $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
    if (in_array(strtolower($fileType), $allowTypes)) {
        if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $targetFilePath)) {
            
            try {
                $sql = "UPDATE orders SET 
                            status = 'Refund Requested', 
                            refund_reason = :reason, 
                            refund_notes = :notes, 
                            refund_evidence_image = :image,
                            refund_requested_at = :requested_at
                        WHERE order_id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':reason' => $reason,
                    ':notes' => $notes,
                    ':image' => $fileName,
                    ':requested_at' => $now,
                    ':id' => $order_id
                ]);

                $info_sql = "SELECT l.farmer_id, l.name as animal_name, c.name as customer_name 
                             FROM orders o 
                             JOIN livestock l ON o.livestock_id = l.livestock_id 
                             JOIN customer c ON o.customer_id = c.customer_id
                             WHERE o.order_id = :oid";
                $info_stmt = $pdo->prepare($info_sql);
                $info_stmt->execute([':oid' => $order_id]);
                $info = $info_stmt->fetch(PDO::FETCH_ASSOC);

                if ($info) {

                    $formattedOrderNumber = formatOrderNumber($order_id);

                    $title = "New Refund Request";
                    $message = "{$info['customer_name']} has requested a refund for {$info['animal_name']} (Order #$formattedOrderNumber.";
                    
                    notify($pdo, $info['farmer_id'], 'farmer', $title, $message);
                }

                header("Location: ../Models/my_orders.php?msg=Refund request submitted successfully.");
                exit();

            } catch (PDOException $e) {
                header("Location: ../Models/my_orders.php?error=Database error: " . $e->getMessage());
                exit();
            }
        } else {
            header("Location: ../Models/my_orders.php?error=Sorry, there was an error uploading your file.");
            exit();
        }
    } else {
        header("Location: ../Models/my_orders.php?error=Only JPG, JPEG, PNG, & GIF files are allowed.");
        exit();
    }
}