<?php
session_start();
require_once '../db_connect.php';
require_once '../pusher/pusher_config.php'; 
include '../inc/numbers.php';

if (!isset($_SESSION['admin_id'])) exit();

$bid_id = $_GET['bid_id'];
$auction_id = $_GET['auction_id'];

try {
    $bid_info_stmt = $pdo->prepare("
        SELECT b.customer_id, b.current_bid, a.livestock_id 
        FROM bidding b
        JOIN auction a ON a.auction_id = ?
        WHERE b.bid_id = ?
    ");
    $bid_info_stmt->execute([$auction_id, $bid_id]);
    $bid_data = $bid_info_stmt->fetch(PDO::FETCH_ASSOC);

    if ($bid_data) {
        $customer_id = $bid_data['customer_id'];
        $voided_amount = $bid_data['current_bid'];
        $livestock_id = $bid_data['livestock_id'];

        $stmt = $pdo->prepare("DELETE FROM bidding WHERE bid_id = ?");
        $stmt->execute([$bid_id]);

        $stmtNextBid = $pdo->prepare('
            SELECT current_bid FROM bidding 
            WHERE livestock_id = ? 
            ORDER BY current_bid DESC LIMIT 1
        ');
        $stmtNextBid->execute([$livestock_id]);
        $nextBid = $stmtNextBid->fetchColumn();

        if (!$nextBid) {
            $stmtBasePrice = $pdo->prepare('SELECT price FROM livestock WHERE livestock_id = ?');
            $stmtBasePrice->execute([$livestock_id]);
            $nextBid = $stmtBasePrice->fetchColumn();
        }

        $update_stmt = $pdo->prepare("UPDATE auction SET current_bid = ? WHERE auction_id = ?");
        $update_stmt->execute([$nextBid, $auction_id]);
        
        $notif_msg = "Your bid of RM " . number_format($voided_amount, 2) . " for Auction " . formatAuctionID($auction_id) . " has been voided by the administrator.";
        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES (?, 'customer', ?, FALSE, NOW())");
        $notif_stmt->execute([$customer_id, $notif_msg]);

        if (isset($pusher)) {
            $stmtBidderName = $pdo->prepare("
                SELECT c.name FROM bidding b 
                JOIN customer c ON b.customer_id = c.customer_id 
                WHERE b.livestock_id = ? ORDER BY b.current_bid DESC LIMIT 1
            ");
            $stmtBidderName->execute([$livestock_id]);
            $newHighestBidder = $stmtBidderName->fetchColumn() ?: 'No Bidders';

            $pusherData = [
                'current_bid' => (float)$nextBid,
                'bidder_name' => $newHighestBidder,
                'message'     => 'A bid was voided.'
            ];
            $pusher->trigger('auction-' . $auction_id, 'new-bid', $pusherData);
        }
    }

    header("Location: view_auction_details.php?id=$auction_id&status=void_success");
    exit();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}