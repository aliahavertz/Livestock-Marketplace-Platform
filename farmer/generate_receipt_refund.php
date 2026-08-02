<?php
session_start();
require_once '../db_connect.php';
include '../inc/numbers.php';
require_once '../vendor/autoload.php';
require_once '../inc/functions.php';

if (!function_exists('notify')) {
    function notify($pdo, $user_id, $user_type, $title, $message) {
        $sql = "INSERT INTO notifications (user_id, user_type, title, message) 
                VALUES (:uid, :utype, :title, :msg)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            'uid' => $user_id,
            'utype' => $user_type,
            'title' => $title,
            'msg' => $message
        ]);
    }
}

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['farmer_id']) && !isset($_SESSION['customer_id'])) {
    die("Unauthorized access. Please log in to view this receipt.");
}

$order_id = $_GET['order_id'] ?? null;
$receipt_type = $_GET['type'] ?? 'full'; 

if (!$order_id) {
    die("Error: Missing Reference/Order ID.");
}

try {
    if ($receipt_type === 'deposit') {
        $stmt = $pdo->prepare("
            SELECT 
                c.customer_id,
                p.transaction_id as receipt_no,
                adp.created_at as action_date,
                p.amount as refund_amount, 
                c.name as customer_name,
                c.email as customer_email,
                c.phone_number as customer_phone,
                p.stripe_payment_id,
                p.payment_method,
                l.name as item_description,
                'Auction Entry Deposit' as item_label,
                p.payment_status,
                p.payment_id
            FROM auction_deposits_paid adp
            JOIN customer c ON adp.customer_id = c.customer_id
            JOIN auction a ON adp.auction_id = a.auction_id
            JOIN livestock l ON a.livestock_id = l.livestock_id
            INNER JOIN payments p ON adp.payment_id = p.payment_id
            WHERE p.transaction_id = ?
        ");
        $stmt->execute([$order_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                c.phone_number,
                CAST(o.order_id AS TEXT) as receipt_no,
                o.refund_completed_at as action_date,
                pay.amount as refund_amount,
                c.name as customer_name,
                c.email as customer_email,
                c.phone_number as customer_phone,
                o.stripe_payment_id,
                pay.payment_method,
                STRING_AGG(l.name, ', ') as item_description,
                'Full Livestock Purchase' as item_label,
                o.status as payment_status,
                pay.payment_id
            FROM orders o
            JOIN customer c ON o.customer_id = c.customer_id
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN livestock l ON oi.livestock_id = l.livestock_id
            LEFT JOIN payments pay ON o.order_id = pay.order_id
            WHERE CAST(o.order_id AS TEXT) = ?
            GROUP BY o.order_id, o.refund_completed_at, pay.amount, c.name, c.email, c.phone_number, o.stripe_payment_id, pay.payment_method, o.status, pay.payment_id
        ");
        $stmt->execute([$order_id]);
    }

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Error: Refund data record not found for reference code: " . htmlspecialchars($order_id));
    }

    if ($receipt_type === 'deposit') {
        $display_reference = $data['receipt_no'] ?? 'N/A';
    } else {
        $display_reference = formatOrderNumber($data['receipt_no']);
    }

    if (!empty($data['stripe_payment_id'])) {
        try {
            $intent_check = \Stripe\PaymentIntent::retrieve([
                'id' => $data['stripe_payment_id'],
                'expand' => ['latest_charge.refunds']
            ]);

            $charge_check = $intent_check->latest_charge;
            
            if (!empty($charge_check->refunds) && !empty($charge_check->refunds->data)) {
                
                $latest_refund = $charge_check->refunds->data[0]; 
                
                if ($latest_refund->status === 'succeeded') {
                    $dt = new DateTime('@' . $latest_refund->created);
                    $dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur')); 
                    
                    $stripe_refund_time = $dt->format('Y-m-d H:i:s');
                    
                    $data['action_date'] = $stripe_refund_time;
                    $data['refund_amount'] = $latest_refund->amount / 100;
                    $data['payment_status'] = 'refunded';
                    if ($receipt_type !== 'deposit') {
                        $update_stmt = $pdo->prepare("UPDATE orders SET status = 'refunded', refund_completed_at = ? WHERE order_id = ?");
                        $update_stmt->execute([$stripe_refund_time, (int)$data['receipt_no']]);
                    }
                }
            }
        } catch (Exception $stripe_err) {
            error_log("Live Stripe check failed: " . $stripe_err->getMessage());
        }
    }

    if ($receipt_type === 'deposit' && strtolower($data['payment_status'] ?? '') === 'refunded' && empty($real_time_date)) {
         $data['action_date'] = date('Y-m-d H:i:s');
    }

    // $status_clean = strtolower($data['payment_status'] ?? '');
    
    // if ($status_clean === 'refunded' && !empty($data['customer_id'])) {
        
    //     $check_notif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND message LIKE ?");
    //     $check_notif->execute([$data['customer_id'], '%' . $order_id . '%']);
    //     $already_sent = $check_notif->fetchColumn() > 0;

    //     if (!$already_sent) {
    //         $receipt_link = "../farmer/generate_receipt_refund.php?order_id=" . urlencode($order_id) . "&type=" . urlencode($receipt_type);
            
    //         $formatted_amount = "RM " . number_format($data['refund_amount'], 2);
    //         $title = "Refund Processed: " . ($receipt_type === 'deposit' ? 'Auction Deposit' : 'Livestock Purchase');
            
    //         if ($receipt_type === 'deposit') {
    //             $message = "Your auction entry deposit of {$formatted_amount} for '{$data['item_description']}' has been successfully refunded. View your receipt here: {$receipt_link}";
    //         } else {
    //             $message = "Your refund of {$formatted_amount} for Order Reference #{$display_reference} has been completed. View your receipt here: {$receipt_link}";
    //         }
            
    //         // 3. Insert notification row into database for customer
    //         notify($pdo, $data['customer_id'], 'customer', $title, $message);
    //     }
    // }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Receipt</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Lora:ital,wght@0,400;0,600;1,400&family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #333333;
            margin: 0;
            padding: 40px 20px;
        }

        .receipt-container {
            max-width: 850px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .brand-header {
            background-color: #0d1b2a;
            color: #ffffff;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-logo {
            font-family: 'Cinzel', serif;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .receipt-title {
            font-family: 'Cinzel', serif;
            font-size: 24px;
            font-weight: 500;
            letter-spacing: 2px;
            opacity: 0.9;
        }

        .receipt-body {
            padding: 40px;
        }

        .meta-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            gap: 20px;
        }

        .info-block h3 {
            color: #1976d2;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 12px 0;
            font-weight: 700;
        }

        .customer-name {
            font-size: 20px;
            font-weight: 700;
            color: #111111;
            margin: 0 0 8px 0;
        }

        .contact-detail {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #555555;
            margin-bottom: 6px;
        }

        .contact-detail i {
            color: #777777;
            width: 16px;
        }

        .payment-info-block {
            text-align: right;
            font-size: 14px;
            color: #333333;
            line-height: 1.6;
        }

        .payment-info-block strong {
            color: #111111;
        }

        .badge-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }

        .refunded-stamp {
            border: 2px solid #2d6a4f;
            color: #2d6a4f;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            border-radius: 4px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .items-table th {
            background-color: #f1f3f5;
            color: #44546a;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            text-align: left;
            border-top: 1px solid #dddddd;
            border-bottom: 2px solid #cbd5e1;
        }

        .items-table td {
            padding: 18px 16px;
            font-size: 14px;
            color: #2c3e50;
            border-bottom: 1px solid #eaedf1;
            vertical-align: middle;
        }

        .item-main-title {
            font-weight: 600;
            color: #111111;
            display: block;
        }

        .item-sub-title {
            font-size: 12px;
            color: #6c757d;
            margin-top: 3px;
            display: block;
        }

        .mono-ref {
            font-family: monospace;
            font-size: 13px;
            color: #495057;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }

        .text-right {
            text-align: right;
        }

        .summary-wrapper {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-top: 30px;
            padding-right: 16px;
        }

        .subtotal-row {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .grand-total-row {
            text-align: right;
            border-top: 1px solid #495057;
            padding-top: 15px;
            width: 250px;
        }

        .total-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #111111;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }

        .total-val {
            font-size: 26px;
            font-weight: 700;
            color: #c62828; 
        }

        .receipt-footer {
            text-align: center;
            padding: 40px;
            font-size: 12px;
            color: #888888;
            border-top: 1px solid #eaeaea;
            background-color: #fafbfc;
        }

        .actions-toolbar {
            max-width: 850px;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: flex-end; 
            gap: 12px;                  
        }

        .btn-print {
            background-color: #0d1b2a;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s;
        }

        .btn-print:hover {
            background-color: #DB2C4F;
        }

        .btn-pdf {
            background-color: #DB2C4F;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s;
        }

        .btn-pdf:hover {
            background-color: #121011;
        }

        @media print {
            body { background-color: #ffffff; padding: 0; }
            .receipt-container { box-shadow: none; }
            .actions-toolbar { display: none; }
        }
    </style>
</head>
<body>

    <div class="actions-toolbar">
        <button class="btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="download_refund_pdf.php?order_id=<?= urlencode($order_id) ?>&type=<?= urlencode($receipt_type) ?>" class="btn-pdf" style="text-decoration: none;">
            <i class="fas fa-download"></i> Download PDF
        </a>
    </div>

    <div class="receipt-container">
        <div class="brand-header">
            <div class="brand-logo">RANCHLINK</div>
            <div class="receipt-title">REFUND RECEIPT</div>
        </div>

        <div class="receipt-body">
            <div class="meta-section">
                <div class="info-block">
                    <h3>Refunded To:</h3>
                    <h2 class="customer-name"><?= htmlspecialchars($data['customer_name']) ?></h2>
                    
                    <?php if(!empty($data['customer_email'])): ?>
                        <div class="contact-detail">
                            <i class="far fa-envelope"></i>
                            <span><?= htmlspecialchars($data['customer_email']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if(!empty($data['customer_phone'])): ?>
                        <div class="contact-detail">
                            <i class="fas fa-phone-alt"></i>
                            <span><?= htmlspecialchars($data['customer_phone']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="payment-info-block">
                    <h3>Payment Info:</h3>
                    <div><strong>Refund Date:</strong> <?= date('d M Y, h:i A', strtotime($data['action_date'])) ?></div>
                    <div>
                        <strong>Payment Method:</strong> 
                        <?= htmlspecialchars(strpos(strtolower($data['payment_method'] ?? ''), 'Online Banking') !== false ? 'Online Banking' : 'Visa Card') ?>
                    </div>
                    <?php if(!empty($data['stripe_payment_id'])): ?>
                        <div><strong>Transaction ID:</strong> <span style="font-size:12px; font-family:monospace;"><?= htmlspecialchars(preg_replace('/-\d+$/', '', $data['stripe_payment_id'])) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="badge-wrapper">
                <?php if (strtolower($data['payment_status'] ?? '') === 'refunded'): ?>
                    <div class="refunded-stamp" style="border-color: #2d6a4f; color: #2d6a4f; background-color: #fff8f8;">
                        Successfully Refunded
                    </div>
                <?php else: ?>
                    <div class="refunded-stamp" style="border-color: #d97706; color: #d97706; background-color: #fffbeb;">
                        <i class="fas fa-clock"></i> Refund Pending
                    </div>
                <?php endif; ?>
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
                            <span class="item-main-title"><?= htmlspecialchars($data['item_label']) ?></span>
                            <span class="item-sub-title">Subject: <?= htmlspecialchars($data['item_description']) ?></span>
                        </td>
                        <td>
                           <span class="mono-ref"><?= htmlspecialchars($display_reference) ?></span>
                        </td>
                        <td class="text-right" style="font-weight: 500;">
                            RM <?= number_format($data['refund_amount'], 2) ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="summary-wrapper">
                <div class="subtotal-row">
                    Subtotal: RM <?= number_format($data['refund_amount'], 2) ?>
                </div>
                <div class="grand-total-row">
                    <span class="total-label">Total Refunded</span>
                    <span class="total-val">RM <?= number_format($data['refund_amount'], 2) ?></span>
                </div>
            </div>
        </div>

        <div class="receipt-footer">
            RanchLink Marketplace | Secure Livestock Trading Platform
            <div style="margin-top: 5px; font-size: 11px; opacity: 0.7;">This is a system-generated refund reversal receipt. No signature is required.</div>
        </div>
    </div>

</body>
</html>