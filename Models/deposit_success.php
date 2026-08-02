<?php
ob_start();
session_start();

require_once dirname(__FILE__) . '/../vendor/autoload.php';
require_once dirname(__FILE__) . '/../db_connect.php';
include '../inc/numbers.php';

\Stripe\Stripe::setApiKey('sk_test_51SipzdEhjpQ4R31fUn7iS5Ld3K4vigl5Hzx05UWBokwZ1dypneBTDXsSG0yAq4NiR4Bbag336ykhYseXJw5CHDJZ00Pi7SPtFt');

$payment_intent_id = $_GET['payment_intent'] ?? null;
$session_id = $_GET['session_id'] ?? null;

if (!$payment_intent_id && !$session_id) {
    header("Location: ../index.php?error=invalid_session");
    exit();
}

try {
    if ($payment_intent_id) {
        $intent = \Stripe\PaymentIntent::retrieve([
            'id' => $payment_intent_id,
            'expand' => ['latest_charge']
        ]);

        $charge = $intent->latest_charge;
        $method_details = $charge->payment_method_details;
        $type = $method_details->type;

        if ($type === 'card') {
            $brand = ucfirst($method_details->card->brand); 
            $last4 = $method_details->card->last4;         
            $real_method = "Card ($brand ****$last4)";
        } elseif ($type === 'fpx') {
            $bank = strtoupper($method_details->fpx->bank); 
            $real_method = "Online Banking ($bank)";
        } else {
            $real_method = strtoupper($type); 
        }
        
        $auction_id  = $intent->metadata['auction_id'] ?? null;
        $customer_id = $intent->metadata['customer_id'] ?? null;
        $amount_paid = $intent->amount / 100;
        $is_paid     = ($intent->status === 'succeeded');
        $stripe_ref  = $payment_intent_id;
        
        $cust_name  = $intent->latest_charge->billing_details->name ?? 'N/A';
        $cust_email = $intent->latest_charge->billing_details->email ?? 'N/A';
        $cust_phone = $intent->latest_charge->billing_details->phone ?? 'N/A';

    } else {
        $session = \Stripe\Checkout\Session::retrieve($session_id);
        $auction_id  = $session->metadata['auction_id'] ?? null;
        $customer_id = $session->metadata['customer_id'] ?? null;
        $amount_paid = $session->amount_total / 100;
        $is_paid     = ($session->payment_status === 'paid');
        $stripe_ref  = $session->payment_intent ?? $session_id;

        $real_method = 'Online Payment';
    }

    if ($is_paid) {
        if (!$auction_id || !$customer_id) {
             header("Location: ../Join_Auction.php?error=missing_metadata");
             exit();
        }

        try {
            $pdo->beginTransaction();

            $check_sql = "SELECT transaction_id FROM payments WHERE stripe_payment_id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$stripe_ref]);
            $existing_payment = $check_stmt->fetch();

            if ($existing_payment) {
                $transaction_id = $existing_payment['transaction_id'];

                $get_id_sql = "SELECT payment_id FROM payments WHERE stripe_payment_id = ?";
                $get_id_stmt = $pdo->prepare($get_id_sql);
                $get_id_stmt->execute([$stripe_ref]);
                $db_payment_id = $get_id_stmt->fetchColumn();
            } else {

            $transaction_id = 'DEP-' . strtoupper(substr(md5(time()), 0, 8));

            $sql_pay = "INSERT INTO payments (order_id, transaction_id, amount, payment_method, payment_status, stripe_payment_id, transaction_date, cust_name, cust_email, cust_phone) 
            VALUES (NULL, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
            $stmt_pay = $pdo->prepare($sql_pay);
            $stmt_pay->execute([
                $transaction_id,   
                $amount_paid, 
                $real_method,      
                'paid',        
                $stripe_ref,
                $cust_name, 
                $cust_email, 
                $cust_phone         
            ]);

            $db_payment_id = $pdo->lastInsertId();
        }

        $sql_auc = "INSERT INTO auction_deposits_paid (auction_id, customer_id, amount, status, payment_id, created_at) 
        VALUES (?, ?, ?, 'paid', ?, NOW())";

        $stmt_auc = $pdo->prepare($sql_auc);

        $stmt_auc->execute([
            (int)$auction_id, 
            (int)$customer_id, 
            $amount_paid, 
            $db_payment_id
        ]);

            $pdo->commit();
            ob_end_clean();


        } catch (PDOException $e) {
            $pdo->rollBack();
            die("Database Error: " . $e->getMessage());
        }

    } else {
        die("Payment was not successful.");
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    header("Location: ../index.php?error=system_failure");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deposit Successful | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=PT+Serif&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #fdf6ec; font-family: 'PT Serif', serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .receipt-card { 
            background: white; 
            padding: 40px; 
            border-radius: 25px; 
            box-shadow: 0 20px 45px rgba(0,0,0,0.1); 
            text-align: center; 
            max-width: 480px; 
            width: 90%; 
            border-top: 10px solid #1976d2; 
        }
        .check-icon { 
            width: 80px; height: 80px; background: #ebf8ff; color: #1976d2; 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            font-size: 40px; margin: 0 auto 20px; border: 2px solid #bee3f8;
        }
        .amount { font-size: 36px; font-weight: bold; color: #1976d2; margin: 10px 0; }
        .details { 
            color: #4a5568; font-size: 14px; margin-bottom: 30px; text-align: left; 
            background: #f8fafc; padding: 25px; border-radius: 18px; border: 1px solid #edf2f7; line-height: 1.8; 
        }
        .details strong { color: #0d1b2a; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .btn-action { 
            background: #1976d2; color: white; padding: 16px 35px; text-decoration: none; 
            border-radius: 15px; display: inline-block; font-weight: bold; font-family: 'Cinzel', serif; 
            transition: 0.3s; margin-bottom: 12px; width: 80%;
        }
        .btn-action:hover { background: #0d1b2a; transform: translateY(-2px); }
        
        .btn-dashboard { color: #718096; text-decoration: none; font-size: 13px; font-weight: 600; display: block; margin-top: 10px; }
        .btn-dashboard:hover { color: #0d1b2a; }
        
        .status-badge { background: #c6f6d5; color: #2d6a4f; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .btn-receipt {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 80%;
            margin-top: 10px;
            padding: 12px 20px;
            background: transparent;
            color: #1976d2; 
            text-decoration: none;
            font-family: 'PT Serif', serif;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .btn-receipt:hover {
            background: #f0f7ff;
            border-color: #1976d2;
            color: #0d1b2a;
            transform: translateY(-1px);
        }

        .btn-receipt i {
            font-size: 16px;
            color: #1976d2;
        }

        .check-icon i {
            animation: gavel-wiggle 2s infinite;
        }

        @keyframes gavel-wiggle {
            0%, 100% { transform: rotate(0deg); }
            10%, 30% { transform: rotate(-15deg); }
            20%, 40% { transform: rotate(15deg); }
        }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="check-icon"><i class="fas fa-gavel"></i></div>
        <h2 style="font-family: 'Cinzel', serif; color: #0d1b2a; margin-bottom: 5px;">Access Unlocked!</h2>
        <p style="margin-top:0; color: #718096;">Your auction deposit has been verified.</p>
        
        <div class="amount">RM <?= number_format($amount_paid, 2) ?></div>
        
        <div class="details">
            <strong>Auction ID:</strong> <?= formatAuctionID($auction_id) ?><br>
            <strong>Payment Reference:</strong> <span style="font-family: monospace; font-size: 12px;"><?= $transaction_id ?></span><br>
            <strong>Payment Method:</strong> 
            <?= htmlspecialchars($real_method ?? $existing_payment['payment_method'] ?? 'Stripe') ?><br>           
            <strong>Status:</strong> <span class="status-badge">Payment Successful</span><br>

            <hr style="border: 0; border-top: 1px dashed #cbd5e0; margin: 15px 0;">

            <p style="margin:0; font-size: 12px; color: #4a5568;">
                <i class="fas fa-info-circle"></i> This deposit will be credited toward your final invoice if you win the auction.
            </p>
        </div>
        
        <a href="Join_Auction.php?auction_id=<?= $auction_id ?>" class="btn-action">
            <i class="fas fa-arrow-right"></i> Enter Live Bidding
        </a>

        <!-- <a href="../Models/customer_dashboard.php" class="btn-dashboard">View My Auctions</a> -->
        <a href="receipt_deposit.php?auction_id=<?= $auction_id ?>" class="btn-receipt">
            <i class="fas fa-file-download"></i> View Receipt
        </a>
        
        <p style="font-size: 11px; color: #a0aec0; margin-top: 25px; font-style: italic;">
            Thank you for using RanchLink Secure Payments.
        </p>
    </div>
</body>
</html>