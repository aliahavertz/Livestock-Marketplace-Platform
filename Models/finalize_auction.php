<?php
session_start();
include '../db_connect.php'; 

header('Content-Type: application/json'); 

if (isset($_POST['auction_id'])) {
    $auction_id = (int)$_POST['auction_id'];

    try {
        // FIX 1: Explicitly select 'status' to verify if this auction has already been finalized
        $stmtGetInfo = $pdo->prepare("
            SELECT a.livestock_id, a.title, a.status, l.farmer_id 
            FROM auction a
            JOIN livestock l ON a.livestock_id = l.livestock_id
            WHERE a.auction_id = ?
        ");
        $stmtGetInfo->execute([$auction_id]);
        $info = $stmtGetInfo->fetch(PDO::FETCH_ASSOC);

        if ($info) {
            if (strtolower($info['status']) === 'closed') {
                echo json_encode(['status' => 'success', 'message' => 'Auction was already finalized by another process.']);
                exit();
            }

            $livestock_id = $info['livestock_id'];
            $farmer_id = $info['farmer_id'];
            $auction_title = $info['title'];

            $stmtWinner = $pdo->prepare("
                SELECT bid_id, customer_id 
                FROM bidding 
                WHERE livestock_id = ? 
                ORDER BY current_bid DESC LIMIT 1
            ");
            $stmtWinner->execute([$livestock_id]);
            $winnerRow = $stmtWinner->fetch();

            if ($winnerRow) {
                $updateBid = $pdo->prepare("UPDATE bidding SET winner_id = ? WHERE bid_id = ?");
                $updateBid->execute([$winnerRow['customer_id'], $winnerRow['bid_id']]);

                $notif_title = "Action Required: Approve Winner";
                $notif_msg = "The auction for '$auction_title' has ended. Please review the bids and approve the winner.";
                
                $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, created_at) VALUES (?, 'farmer', ?, ?, NOW())");
                $stmtNotif->execute([$farmer_id, $notif_title, $notif_msg]);

                $updateAuction = $pdo->prepare("UPDATE auction SET status = 'closed' WHERE auction_id = ?");
                $updateAuction->execute([$auction_id]);

                $updateLivestock = $pdo->prepare("UPDATE livestock SET availability_status = 'Pending Approval' WHERE livestock_id = ?");
                $updateLivestock->execute([$livestock_id]);

                echo json_encode(['status' => 'success', 'message' => 'Winner marked: ' . $winnerRow['customer_id']]);
            } else {
                
                $notif_title = "Auction Ended: No Bids";
                $notif_msg = "The auction for '$auction_title' has ended, but there were no active bids to process.";
                
                $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, created_at) VALUES (?, 'farmer', ?, ?, NOW())");
                $stmtNotif->execute([$farmer_id, $notif_title, $notif_msg]);

                $updateAuction = $pdo->prepare("UPDATE auction SET status = 'closed' WHERE auction_id = ?");
                $updateAuction->execute([$auction_id]);
                
                echo json_encode(['status' => 'success', 'message' => 'Closed: No bids found. Notification dispatched.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid Auction ID']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No ID provided']);
}
?>