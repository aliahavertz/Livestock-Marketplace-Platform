<?php
session_start();
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auction_id = $_POST['auction_id'];
    $customer_id = $_POST['customer_id']; 
    $reason = $_POST['reason'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE auction SET status = 'rejected', rejection_reason = ? WHERE auction_id = ?");
        $stmt->execute([$reason, $auction_id]);

        $stmtTitle = $pdo->prepare("SELECT title FROM auction WHERE auction_id = ?");
        $stmtTitle->execute([$auction_id]);
        $auction = $stmtTitle->fetch();

        $notif_title = "Bid Rejected";
        $notif_msg = "Your bid for '" . $auction['title'] . "' was not accepted. Reason: " . $reason;
        
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, created_at) VALUES (?, 'customer', ?, ?, NOW())");
        $stmtNotif->execute([$customer_id, $notif_title, $notif_msg]);

        $pdo->commit();
        header("Location: view_auction_bids.php?id=$auction_id&msg=rejected");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}