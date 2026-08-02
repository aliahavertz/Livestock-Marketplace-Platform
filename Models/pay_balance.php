<?php
session_start();
include '../db_connect.php';
include '../inc/header.php';

if (!isset($_SESSION['customer_id']) || !isset($_GET['auction_id'])) {
    header("Location: customer_dashboard.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$auction_id = $_GET['auction_id'];

$stmt = $pdo->prepare("
    SELECT 
        a.auction_id, 
        a.current_bid, 
        l.name, 
        l.livestock_id,
        COALESCE((
            SELECT SUM(amount) 
            FROM auction_deposits_paid 
            WHERE auction_id = a.auction_id 
            AND customer_id = :cid
        ), 0) as deposit_paid
    FROM auction a
    JOIN livestock l ON a.livestock_id = l.livestock_id
    WHERE a.auction_id = :aid 
    AND a.last_bidder_id = :cid 
    AND a.status = 'closed'
");
$stmt->execute([':aid' => $auction_id, ':cid' => $customer_id]);
$details = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$details) {
    die("Unauthorized or Auction not found.");
}

$winning_bid = $details['current_bid'];
$deposit_paid = $details['deposit_paid'] ?? 0;
$balance_due = $winning_bid - $deposit_paid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $insertOrder = $pdo->prepare("
            INSERT INTO orders (customer_id, livestock_id, total_price, order_status, created_at)
            VALUES (?, ?, ?, 'Processing', NOW())
            RETURNING order_id
        ");
        $insertOrder->execute([$customer_id, $details['livestock_id'], $winning_bid]);
        $order_id = $insertOrder->fetchColumn();

        $insertPay = $pdo->prepare("
            INSERT INTO payments (order_id, amount, payment_method, payment_status, created_at)
            VALUES (?, ?, 'Online Banking', 'Completed', NOW())
        ");
        $insertPay->execute([$order_id, $balance_due]);

        $updLive = $pdo->prepare("UPDATE livestock SET availability_status = 'Sold' WHERE livestock_id = ?");
        $updLive->execute([$details['livestock_id']]);

        $pdo->commit();
        header("Location: customer_dashboard.php?msg=payment_complete");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Payment failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settle Balance | Ranch Outlet</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { background: #fdf6ec; font-family: 'PT Serif', serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .payment-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        h2 { font-family: 'Cinzel'; color: #1a1a1a; }
        .line-item { display: flex; justify-content: space-between; margin: 15px 0; font-size: 1.1rem; }
        .total { border-top: 2px solid #eee; padding-top: 15px; font-weight: bold; font-size: 1.4rem; color: #1976d2; }
        .btn-confirm { background: #1976d2; color: white; border: none; width: 100%; padding: 15px; border-radius: 30px; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="payment-card">
        <h2>Settle Balance</h2>
        <p>Item: <strong><?= htmlspecialchars($details['name']) ?></strong></p>
        
        <div class="line-item">
            <span>Winning Bid:</span>
            <span>RM <?= number_format($winning_bid, 2) ?></span>
        </div>
        <div class="line-item">
            <span>Deposit Paid:</span>
            <span>- RM <?= number_format($deposit_paid, 2) ?></span>
        </div>
        <div class="line-item total">
            <span>Balance Due:</span>
            <span>RM <?= number_format($balance_due, 2) ?></span>
        </div>

        <form action="../payment/unified_checkout.php" method="GET">
            <input type="hidden" name="livestock_id" value="<?= $details['livestock_id'] ?>">
            <input type="hidden" name="auction_id" value="<?= $details['auction_id'] ?>">
            
            <input type="hidden" name="price" value="<?= $balance_due ?>">
            
            <button type="submit" class="btn-confirm">Checkout</button>
        </form>
        <br>
        <a href="customer_dashboard.php" style="color: #666; text-decoration: none; font-size: 0.9rem;">Cancel and Go Back</a>
    </div>
</body>
</html>