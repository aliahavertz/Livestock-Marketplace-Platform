<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$reason = $_GET['reason'] ?? 'No specific reason provided.';

if ($action && $id) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT farmer_id, name FROM livestock WHERE livestock_id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        if ($item) {
            if ($action === 'approve') {
                $update = $pdo->prepare("UPDATE livestock SET availability_status = 'Available' WHERE livestock_id = ?");
                $update->execute([$id]);
                
                $msg = "Your listing for '{$item['name']}' has been approved..";
                $title = "Listing Approved";
            } 
            elseif ($action === 'reject') {
                $update = $pdo->prepare("UPDATE livestock SET availability_status = 'Rejected' WHERE livestock_id = ?");
                $update->execute([$id]);
                
                $msg = "Your listing for '{$item['name']}' was rejected. Reason: " . $reason;
                $title = "Listing Rejected";
            }

            $notif = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, is_read, created_at) 
                                   VALUES (?, 'farmer', ?, ?, FALSE, NOW())");
            $notif->execute([$item['farmer_id'], $title, $msg]);
        }

        $pdo->commit();
        header("Location: verify_listings.php?msg=success");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
header("Location: verify_listings.php");
exit();