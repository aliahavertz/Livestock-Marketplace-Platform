<?php
session_start();
require_once '../db_connect.php';
require_once '../vendor/autoload.php';
include '../inc/numbers.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['farmer_id']) && !isset($_SESSION['customer_id'])) {
    die("Unauthorized access.");
}


$order_id = $_GET['order_id'] ?? null;
$type = $_GET['type'] ?? 'full';

if (!$order_id) {
    die("Error: Missing Order ID.");
}

try {
    if ($type === 'deposit') {
        $stmt = $pdo->prepare("
            SELECT 
                p.transaction_id as receipt_no,
                adp.created_at as action_date,
                p.amount as refund_amount,
                c.name as customer_name,
                c.email as customer_email,
                c.phone_number as customer_phone,
                p.stripe_payment_id,
                p.payment_method,
                l.name as item_description,
                'Auction Entry Deposit' as item_label
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
                CAST(o.order_id AS TEXT) as receipt_no,
                o.refund_completed_at as action_date,
                pay.amount as refund_amount,
                c.name as customer_name,
                c.email as customer_email,
                c.phone_number as customer_phone,
                o.stripe_payment_id,
                pay.payment_method,
                STRING_AGG(l.name, ', ') as item_description,
                'Full Livestock Purchase' as item_label
            FROM orders o
            JOIN customer c ON o.customer_id = c.customer_id
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN livestock l ON oi.livestock_id = l.livestock_id
            LEFT JOIN payments pay ON o.order_id = pay.order_id
            WHERE CAST(o.order_id AS TEXT) = ?
            GROUP BY o.order_id, o.refund_completed_at, pay.amount, c.name, c.email, c.phone_number, o.stripe_payment_id, pay.payment_method
        ");
        $stmt->execute([$order_id]);
    }

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Error: Refund data record not found.");
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$display_no = $type === 'deposit' ? htmlspecialchars($data['receipt_no'] ?? 'N/A') : formatOrderNumber($data['receipt_no']);
$display_date = !empty($data['action_date']) ? date('d M Y, h:i A', strtotime($data['action_date'])) : date('d M Y, h:i A');
$display_method = htmlspecialchars(strpos(strtolower($data['payment_method'] ?? ''), 'banking') !== false ? 'Online Banking' : 'Visa Card');
$stripe_id = !empty($data['stripe_payment_id']) ? htmlspecialchars(preg_replace('/-\d+$/', '', $data['stripe_payment_id'])) : 'N/A';

$html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Refund Receipt #' . $display_no . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333333;
            font-size: 14px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .brand-header {
            background-color: #0d1b2a;
            color: #ffffff;
            width: 100%;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .brand-logo {
            font-family: Cinzel, serif;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #ffffff;
            padding: 25px 0 25px 35px;
        }
        .receipt-title {
            font-family: Cinzel, serif;
            font-size: 20px;
            letter-spacing: 2px;
            text-align: right;
            color: #ffffff;
            padding: 25px 35px 25px 0;
        }
        .receipt-container {
            padding: 35px;
            width: 100%;
        }
        .layout-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-heading {
            color: #1976d2;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
            padding-bottom: 10px;
        }
        .customer-name {
            font-size: 18px;
            font-weight: bold;
            color: #111111;
            padding-bottom: 8px;
        }
        .contact-detail {
            font-size: 13px;
            color: #555555;
            padding-bottom: 4px;
        }
        .payment-info-line {
            font-size: 13px;
            color: #333333;
            padding-bottom: 5px;
        }
        .payment-info-line strong {
            color: #111111;
        }
        
        .badge-wrapper {
            text-align: right;
            margin-bottom: 25px;
        }

        .refunded-stamp {
            border: 2px solid #2d6a4f;
            color: #2d6a4f;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            background-color: #fff8f8;
            display: inline-block;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            background-color: #f1f3f5;
            color: #44546a;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 10px 12px;
            border-top: 1px solid #dddddd;
            border-bottom: 2px solid #cbd5e1;
        }
        .items-table td {
            padding: 16px 12px;
            font-size: 13px;
            color: #2c3e50;
            border-bottom: 1px solid #eaedf1;
            vertical-align: top;
        }
        .item-main-title {
            font-weight: bold;
            color: #111111;
        }
        .item-sub-title {
            font-size: 12px;
            color: #6c757d;
            padding-top: 4px;
        }
        .mono-ref {
            font-family: monospace;
            font-size: 12px;
            color: #495057;
            background-color: #f1f3f5;
            padding: 3px 8px;
            border: 1px solid #e2e8f0;
        }
        
        .summary-border-top {
            border-top: 1px solid #495057;
            padding-top: 12px;
        }
        .subtotal-row {
            font-size: 13px;
            color: #6c757d;
            padding-bottom: 10px;
        }
        .total-label {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #111111;
            letter-spacing: 0.5px;
        }
        .total-val {
            font-size: 24px;
            font-weight: bold;
            color: #c62828;
            padding-top: 4px;
        }
        .receipt-footer {
            text-align: center;
            padding: 25px 0;
            font-size: 11px;
            color: #888888;
            border-top: 1px solid #eaeaea;
            background-color: #fafbfc;
            position: absolute;
            bottom: 0;
            width: 100%;
            left: 0;
        }
    </style>
</head>
<body>

    <div class="brand-header">
        <table class="header-table">
            <tr>
                <td class="brand-logo" style="width: 50%; text-align: left;">RANCHLINK</td>
                <td class="receipt-title" style="width: 50%; text-align: right;">REFUND RECEIPT</td>
            </tr>
        </table>
    </div>

    <table class="layout-table">
        <tr>
            <td class="receipt-container">
            
                <table class="layout-table" style="margin-bottom: 35px;">
                    <tr>
                        <td style="width: 55%; vertical-align: top; text-align: left;">
                            <div class="info-heading">Refunded To:</div>
                            <div class="customer-name">' . htmlspecialchars($data['customer_name']) . '</div>
                            ' . (!empty($data['customer_email']) ? '<div class="contact-detail">' . htmlspecialchars($data['customer_email']) . '</div>' : '') . '
                            ' . (!empty($data['customer_phone']) ? '<div class="contact-detail">' . htmlspecialchars($data['customer_phone']) . '</div>' : '') . '
                        </td>
                        <td style="width: 45%; vertical-align: top; text-align: right;">
                            <div class="info-heading">Payment Info:</div>
                            <div class="payment-info-line"><strong>Refund Date:</strong> ' . $display_date . '</div>
                            <div class="payment-info-line"><strong>Payment Method:</strong> ' . $display_method . '</div>
                            <div class="payment-info-line"><strong>Transaction ID:</strong> <span style="font-family: monospace; font-size: 11px;">' . $stripe_id . '</span></div>
                        </td>
                    </tr>
                </table>

                <div class="badge-wrapper">
                    <div class="refunded-stamp">Successfully Refunded</div>
                </div>

                <table class="items-table" style="margin-bottom: 35px;">
                    <thead>
                        <tr>
                            <th style="width: 55%; text-align: left;">Description</th>
                            <th style="width: 20%; text-align: left;">Reference</th>
                            <th style="width: 25%; text-align: right;">Amount (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: left;">
                                <div class="item-main-title">' . htmlspecialchars($data['item_label']) . '</div>
                                <div class="item-sub-title">Subject: ' . htmlspecialchars($data['item_description']) . '</div>
                            </td>
                            <td style="text-align: left; vertical-align: middle;">
                                <span class="mono-ref">' . $display_no . '</span>
                            </td>
                            <td style="text-align: right; vertical-align: middle; font-weight: bold; color: #111111;">
                                RM ' . number_format($data['refund_amount'], 2) . '
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="layout-table">
                    <tr>
                        <td style="width: 55%;"></td>
                        <td style="width: 45%; text-align: right;" class="summary-border-top">
                            <div class="subtotal-row">Subtotal: RM ' . number_format($data['refund_amount'], 2) . '</div>
                            <div class="total-label">Total Refunded</div>
                            <div class="total-val">RM ' . number_format($data['refund_amount'], 2) . '</div>
                        </td>
                    </tr>
                </table>
                
            </td>
        </tr>
    </table>

    <div class="receipt-footer">
        RanchLink Marketplace | Secure Livestock Trading Platform
        <div style="margin-top: 5px; font-size: 10px; opacity: 0.7;">This is a system-generated refund reversal receipt. No signature is required.</div>
    </div>

</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); 

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Automatically names file correctly depending on logic paths
$dompdf->stream("Refund_Receipt_" . $display_no . ".pdf", ["Attachment" => true]);
exit();