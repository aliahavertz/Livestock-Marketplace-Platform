<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['farmer_id'])) {
    header("Location: ../index.php"); 
    exit();
}

$session_id = $_GET['session_id'] ?? null;
$payment_id = $_GET['payment_id'] ?? null;

if (!$session_id && !$payment_id) {
    die("Invalid request: No transaction reference provided.");
}

try {
    $sql = "SELECT 
                p.*, 
                o.order_id, o.order_date, o.shipping_address, o.contact_name, o.customer_id,
                l.name as animal_name, l.breed, l.farmer_id,
                c.name as customer_name, c.email,
                f.farm_name
            FROM payments p
            JOIN orders o ON p.order_id = o.order_id
            JOIN livestock l ON o.livestock_id = l.livestock_id
            JOIN customer c ON o.customer_id = c.customer_id
            JOIN farmer f ON l.farmer_id = f.farmer_id
            WHERE (o.stripe_payment_id = :sid OR p.stripe_payment_id = :pid)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'sid' => $session_id,
        'pid' => $payment_id
    ]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        die("Receipt not found. If you just finished paying, please wait a moment and refresh.");
    }

    $can_view = false;
    
    if (isset($_SESSION['customer_id']) && $_SESSION['customer_id'] == $receipt['customer_id']) {
        $can_view = true;
        $home_url = "../Models/customer_dashboard.php";
    }
    
    if (isset($_SESSION['farmer_id']) && $_SESSION['farmer_id'] == $receipt['farmer_id']) {
        $can_view = true;
        $home_url = "../farmer/manage_payments.php";
    }

    if (!$can_view) {
        die("Access Denied: You do not have permission to view this receipt.");
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - #<?= htmlspecialchars($receipt['order_id']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f4f7f6; margin: 0; padding: 0; font-family: 'Helvetica Neue', Arial, sans-serif; }
        .receipt-box {
            max-width: 800px;
            margin: 50px auto;
            padding: 40px;
            background: #fff;
            border: 1px solid #eee;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            position: relative;
        }
        .header { border-bottom: 3px solid #90caf9; padding-bottom: 20px; margin-bottom: 20px; }
        .flex { display: flex; justify-content: space-between; }
        .table { width: 100%; text-align: left; border-collapse: collapse; margin-top: 30px; }
        .table th { background: #f9f9f9; padding: 12px; border-bottom: 2px solid #eee; text-transform: uppercase; font-size: 0.85em; color: #888; }
        .table td { padding: 15px; border-bottom: 1px solid #f2f2f2; }
        .total-row td { border-bottom: none; }
        .total-amount { font-weight: bold; color: #8b0000; font-size: 1.5em; }
        
        .status-stamp {
            position: absolute;
            top: 20px;
            right: 40px;
            border: 3px solid #2e7d32;
            color: #2e7d32;
            padding: 5px 15px;
            font-weight: bold;
            text-transform: uppercase;
            transform: rotate(5deg);
            opacity: 0.7;
        }

        .action-bar { text-align: center; margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 5px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }
        .btn-print { background: #453c34; color: white; border: none; }
        .btn-download { background: #90caf9; color: #fff; }
        .btn-home { background: #eee; color: #333; }
        .btn:hover { opacity: 0.8; transform: translateY(-1px); }

        @media print { 
            .action-bar, .status-stamp { display: none !important; } 
            .receipt-box { box-shadow: none; border: none; margin: 0; width: 100%; }
        }
    </style>
</head>
<body>

<div class="receipt-box">
    <div class="status-stamp">Verified Paid</div>

    <div class="header">
        <div class="flex">
            <div>
                <h1 style="color: #444; margin: 0; letter-spacing: -1px;"><?= htmlspecialchars($receipt['farm_name']) ?></h1>
                <p style="margin: 5px 0; color: #90caf9; font-weight: bold;">Official Purchase Receipt</p>
            </div>
            <div style="text-align: right;">
                <p style="margin: 0;"><strong>Date:</strong> <?= date('d M Y', strtotime($receipt['transaction_date'])) ?></p>
                <p style="margin: 5px 0;"><strong>Order ID:</strong> #<?= $receipt['order_id'] ?></p>
            </div>
        </div>
    </div>

    <div class="flex" style="margin-bottom: 40px; gap: 40px;">
        <div style="flex: 1;">
            <strong style="text-transform: uppercase; font-size: 0.75em; color: #888; display: block; margin-bottom: 5px;">Billed To:</strong>
            <span style="font-size: 1.1em; color: #333;"><strong><?= htmlspecialchars($receipt['contact_name'] ?: $receipt['customer_name']) ?></strong></span><br>
            <div style="font-size: 0.9em; color: #666; line-height: 1.5; margin-top: 5px;">
                <?= nl2br(htmlspecialchars($receipt['shipping_address'])) ?><br>
                <?= htmlspecialchars($receipt['email']) ?>
            </div>
        </div>
        <div style="text-align: right; flex: 1;">
            <strong style="text-transform: uppercase; font-size: 0.75em; color: #888; display: block; margin-bottom: 5px;">Payment Details:</strong>
            <span style="font-size: 0.9em;">Stripe Secure Payment</span><br>
            <span style="font-family: monospace; font-size: 0.8em; color: #999;">REF: <?= htmlspecialchars($receipt['stripe_payment_id']) ?></span>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Livestock Description</th>
                <th>Breed</th>
                <th style="text-align: right;">Unit Price</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong><?= htmlspecialchars($receipt['animal_name']) ?></strong></td>
                <td><?= htmlspecialchars($receipt['breed']) ?></td>
                <td style="text-align: right;">RM <?= number_format($receipt['amount'], 2) ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="2" style="text-align: right; padding-top: 30px;"><strong>Amount Paid:</strong></td>
                <td style="text-align: right; padding-top: 30px;" class="total-amount">RM <?= number_format($receipt['amount'], 2) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="action-bar">
        <p style="font-size: 0.85em; color: #999; margin-bottom: 20px;">This receipt is a valid proof of transaction for your records.</p>
        
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print Receipt
        </button>

        <a href="download_receipt.php?order_id=<?= $receipt['order_id'] ?>" class="btn btn-download">
            <i class="fas fa-file-pdf"></i> Download PDF
        </a>

        <a href="<?= $home_url ?>" class="btn btn-home">Return</a>
    </div>
</div>

</body>
</html>