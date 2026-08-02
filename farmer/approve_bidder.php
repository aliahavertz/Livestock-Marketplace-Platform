<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['farmer_id']) && !isset($_SESSION['admin_id'])) {
    header("Content-Type: application/json");
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auction_id = isset($_POST['auction_id']) ? (int)$_POST['auction_id'] : null;
    $winner_id = isset($_POST['winner_id']) ? (int)$_POST['winner_id'] : null;

    if (!$auction_id || !$winner_id) {
        die("Missing required information.");
    }

    try {
        $pdo->beginTransaction();

        $stmtBid = $pdo->prepare("
            UPDATE bidding 
            SET winner_id = ? 
            WHERE customer_id = ? 
            AND livestock_id = (SELECT livestock_id FROM auction WHERE auction_id = ?)
        ");
        $stmtBid->execute([$winner_id, $winner_id, $auction_id]);

        $stmtAuction = $pdo->prepare("
            UPDATE auction 
            SET status = 'closed' 
            WHERE auction_id = ?
        ");
        $stmtAuction->execute([$auction_id]); 

        $stmtLivestock = $pdo->prepare("
            UPDATE livestock 
            SET availability_status = 'Sold' 
            WHERE livestock_id = (SELECT livestock_id FROM auction WHERE auction_id = ?)
        ");
        $stmtLivestock->execute([$auction_id]);

        $pdo->commit();

        header("Location: view_auction_bids.php?id=" . $auction_id . "&status=approved");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error approving winner: " . $e->getMessage());
    }
} else {
    header("Location: farmer_manage_auction.php");
    exit();
}