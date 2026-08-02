<?php
session_start();
include '../db_connect.php';

require_once dirname(__FILE__) . '/../vendor/autoload.php';
require_once '../pusher/pusher_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login first.']);
    exit();
}

$auction_id  = isset($_POST['auction_id']) ? (int)$_POST['auction_id'] : null;
$bid_amount  = isset($_POST['bid_amount']) ? (float)$_POST['bid_amount'] : null;
$customer_id = (int)$_SESSION['customer_id'];

if (!$auction_id || !$bid_amount) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data.']);
    exit();
}

$stmt = $pdo->prepare("SELECT name FROM customer WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$bidder_name = $stmt->fetchColumn() ?: 'Anonymous';

try {
    $pdo->beginTransaction();

    $checkPaid = $pdo->prepare("SELECT COUNT(*) FROM auction_deposits_paid 
                                WHERE auction_id = ? AND customer_id = ? AND status = 'paid'");
    $checkPaid->execute([$auction_id, $customer_id]);
    
    if ($checkPaid->fetchColumn() == 0) {
        throw new Exception("Access Denied: You must pay the deposit to bid.");
    }

    $stmt = $pdo->prepare("SELECT a.auction_id, a.current_bid, a.end_time, a.livestock_id, l.price as starting_price 
                           FROM auction a 
                           JOIN livestock l ON a.livestock_id = l.livestock_id 
                           WHERE a.auction_id = ? FOR UPDATE");
    $stmt->execute([$auction_id]);
    $auction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$auction) throw new Exception("Auction not found.");
    
    if (strtotime($auction['end_time']) <= time()) {
        throw new Exception("Sorry, this auction has already ended.");
    }

    $minRequired = ($auction['current_bid'] > 0) ? (float)$auction['current_bid'] : (float)$auction['starting_price'];
    
    if ($bid_amount <= $minRequired) {
        throw new Exception("Bid must be higher than RM " . number_format($minRequired, 2));
    }

    $updateStmt = $pdo->prepare("UPDATE auction SET current_bid = ?, last_bidder_id = ? WHERE auction_id = ?");
    $updateStmt->execute([$bid_amount, $customer_id, $auction_id]);

    $historyStmt = $pdo->prepare("INSERT INTO bidding (livestock_id, customer_id, current_bid, status) VALUES (?, ?, ?, 'active')");
    $historyStmt->execute([$auction['livestock_id'], $customer_id, $bid_amount]);

    $pdo->commit();

    $pusher->trigger("auction-$auction_id", "new-bid", [
        'current_bid' => $bid_amount, 
        'bidder_id'   => $customer_id,
        'bidder_name' => $bidder_name,
        'formatted_bid' => "RM " . number_format($bid_amount, 2)
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Bid placed successfully!']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>