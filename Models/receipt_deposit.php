<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
include '../inc/numbers.php'; 

$auction_id = $_GET['auction_id'] ?? null;
$payment_id = $_GET['payment_id'] ?? null;
$customer_id = $_SESSION['customer_id'] ?? null;

$is_pdf = isset($_GET['pdf_mode']);

if (!$payment_id) {
    if ($auction_id && $customer_id) {
        $stmt = $pdo->prepare("
            SELECT payment_id 
            FROM auction_deposits_paid 
            WHERE auction_id = ? AND customer_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([(int)$auction_id, (int)$customer_id]);
        $payment_id = $stmt->fetchColumn();
    }
}

if (!$payment_id) {
    die("Invalid Request: Could not locate a valid payment record for this customer.");
}

try {
    $sql = "
        SELECT 
            p.transaction_id, 
            p.amount, 
            p.transaction_date, 
            p.payment_method,
            p.stripe_payment_id,
            p.cust_name,   
            p.cust_email,  
            p.cust_phone, 
            l.name as item_name,
            adp.auction_id,
            adp.customer_id
        FROM auction_deposits_paid adp
        JOIN payments p ON adp.payment_id = p.payment_id
        JOIN auction a ON adp.auction_id = a.auction_id
        JOIN livestock l ON a.livestock_id = l.livestock_id
        WHERE adp.payment_id = ? 
        AND adp.status = 'paid'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        die("No verified deposit found for this transaction ID.");
    }
    $auction_id = $payment['auction_id'];

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?= formatAuctionID($auction_id) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=PT+Serif:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { font-family: 'PT Serif', serif; color: #333; background: #f4f7f6; margin: 0; padding: 40px; }
        
        .receipt-box { 
            max-width: 850px; 
            margin: auto; 
            background: #fff; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .header { 
            background: #0d1b2a; 
            color: #ffffff; 
            padding: 40px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .logo { font-family: 'Cinzel', serif; font-size: 28px; letter-spacing: 2px; }
        .receipt-title { text-align: right; }
        .receipt-title h1 { margin: 0; font-family: 'Cinzel'; font-size: 32px; letter-spacing: 4px; }
        .receipt-title p { margin: 5px 0 0; opacity: 0.7; font-size: 11px; text-transform: uppercase; }

        .main-content { padding: 40px; }

        .info-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 40px; margin-bottom: 40px; }
        
        .section-label { 
            color: #1976d2; 
            font-weight: bold; 
            text-transform: uppercase; 
            font-size: 11px; 
            letter-spacing: 1px; 
            margin-bottom: 10px; 
            display: block; 
        }

        .billed-to h2 { margin: 0; font-size: 1.4rem; color: #0d1b2a; }
        .billed-to p { margin: 4px 0; color: #555; }

        .payment-details { text-align: right; }
        
        .status-stamp { 
            border: 3px solid #2d6a4f; 
            color: #2d6a4f; 
            padding: 8px 15px; 
            display: inline-block; 
            font-weight: bold; 
            font-size: 14px; 
            text-transform: uppercase; 
            margin-top: 15px;
            transform: rotate(-2deg);
        }

        table.items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.items-table th { 
            background: #f8fafc; 
            color: #0d1b2a; 
            padding: 15px; 
            text-align: left; 
            border-bottom: 2px solid #0d1b2a; 
            font-family: 'Cinzel'; 
            font-size: 12px; 
            text-transform: uppercase; 
        }
        table.items-table td { padding: 15px; border-bottom: 1px solid #edf2f7; font-size: 14px; }

        .total-section { margin-top: 40px; text-align: right; }
        .total-row { margin-bottom: 10px; font-size: 14px; color: #718096; }
        .final-total { border-top: 2px solid #0d1b2a; padding-top: 20px; margin-top: 15px; }
        .total-amount { font-size: 28px; font-weight: bold; color: #1976d2; }

        .footer { 
            background: #fdfdfd; 
            padding: 30px; 
            text-align: center; 
            font-size: 12px; 
            color: #94a3b8; 
            border-top: 1px solid #eee; 
        }

        .no-print { margin-bottom: 20px; text-align: center; }
        .btn-print { background: #0d1b2a; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-family: 'Cinzel'; transition: 0.3s; gap: 15px; }
        .btn-return { background: #64748b; color: white; text-decoration: none; padding: 12px 25px; border-radius: 5px; font-family: 'Cinzel'; font-size: 14px; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }

        <?php if ($is_pdf): ?>
            body { background: #fff; padding: 0; }
            .receipt-box { box-shadow: none; border: none; max-width: 100%; }
            .header { display: block; overflow: auto; height: 120px; }
            .logo { float: left; width: 50%; }
            .receipt-title { float: right; width: 50%; text-align: right; }
            .info-grid { display: block; width: 100%; margin-bottom: 40px; clear: both; }
            .billed-to { float: left; width: 60%; }
            .payment-details { float: right; width: 40%; text-align: right; }
            .items-table { clear: both; margin-top: 30px; width: 100%; }
        <?php endif; ?>

        @media print { .no-print { display: none; } body { padding: 0; background: #fff; } .receipt-box { box-shadow: none; border: none; } }
    </style>
</head>
<body>

    <?php if (!$is_pdf): ?>

<div class="no-print">
    <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Print Receipt</button>
    <a href="generate_receipt_deposit.php?payment_id=<?= $payment_id ?>&auction_id=<?= $auction_id ?>" class="btn-print" style="text-decoration:none; display:inline-flex; align-items:center;">
        <i class="fas fa-file-pdf"></i> Download PDF
    </a>
    <a href="Join_Auction.php?auction_id=<?= $auction_id ?>" class="btn-return">
        <i class="fas fa-gavel"></i> Join Bid
    </a>
</div>
<?php endif; ?>

<div class="receipt-box">
    <div class="header">
        <div class="logo">RANCHLINK</div>
        <div class="receipt-title">
            <h1>RECEIPT</h1>
            <p>Transaction: <?= htmlspecialchars($payment['stripe_payment_id']) ?></p>
        </div>
    </div>

    <div class="main-content">
        <div class="info-grid">
            <div class="billed-to">
                <span class="section-label">Billed To:</span>
                <h2><?= htmlspecialchars($payment['cust_name'] ?? 'N/A') ?></h2>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($payment['cust_email'] ?? 'N/A') ?></p>
                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($payment['cust_phone'] ?? 'N/A') ?></p>
            </div>
            
            <div class="payment-details">
                <span class="section-label">Payment Info:</span>
                <p><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($payment['transaction_date'])) ?></p>
                <p><strong>Payment Method:</strong> <?= htmlspecialchars($payment['payment_method']) ?></p>
                <div class="status-stamp">Successfully Paid</div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                    <th style="width: 25%;">Reference</th>
                    <th style="width: 25%; text-align: right;">Amount (RM)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong style="color: #0d1b2a;">Auction Entry Deposit</strong><br>
                        <small style="color: #718096;">Subject: Livestock - <?= htmlspecialchars($payment['item_name']) ?></small>
                    </td>
                    <td><?= formatAuctionID($auction_id) ?></td>
                    <td style="text-align: right; font-weight: bold;">RM <?= number_format($payment['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="total-section">
            <div class="total-row">Subtotal: RM <?= number_format($payment['amount'], 2) ?></div>
            <div class="total-row final-total">
                <span style="text-transform: uppercase; font-weight: bold; color: #0d1b2a; font-size: 12px;">Total Paid</span><br>
                <span class="total-amount">RM <?= number_format($payment['amount'], 2) ?></span>
            </div>
        </div>
    </div>

    <div class="footer">
        <p><strong>RanchLink Marketplace</strong> | Secure Livestock Trading Platform</p>
        <p>This is a computer-generated receipt. No signature is required.</p>
    </div>
</div>

</body>
</html>