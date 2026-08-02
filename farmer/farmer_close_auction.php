<?php
ob_start(); 
session_start();
include '../db_connect.php';

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

if (isset($_GET['auction_id'])) {
    $auction_id = (int)$_GET['auction_id']; 
    $farmer_id = $_SESSION['farmer_id'];

    try {
        $sql = "UPDATE auction
                SET status = 'closed'
                FROM livestock
                WHERE auction.livestock_id = livestock.livestock_id
                AND auction.auction_id = :aid
                AND livestock.farmer_id = :fid";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':aid' => $auction_id,
            ':fid' => $farmer_id
        ]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['msg'] = "Auction closed successfully.";
        } else {
            $_SESSION['error'] = "Unauthorized or auction not found.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }
}

header("Location: farmer_manage_auction.php");
exit();