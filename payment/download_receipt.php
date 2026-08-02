<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    die("Order ID is required.");
}

try {
    $stmtId = $pdo->prepare("SELECT stripe_payment_id FROM orders WHERE order_id = :oid");
    $stmtId->execute([':oid' => $order_id]);
    $full_stripe_id = $stmtId->fetchColumn();

    if (!$full_stripe_id) {
        die("Transaction not found.");
    }

    $base_stripe_id = explode('-', $full_stripe_id)[0];

    $query = "SELECT o.*, 
        oi.price_at_purchase as item_price,
        oi.item_status, 
        l.livestock_id, l.name as livestock_name, l.breed, l.category, 
        f.farm_name, f.farmer_id, f.name as farmer_name,
        c.name as customer_name, c.email as customer_email,
        p.payment_method, p.amount as total_paid_amount,
        d.recipient_name, d.phone_number as delivery_phone, 
        d.deliveryaddress, d.city, d.postcode, d.state, 
        d.shipping_method, d.deliveryfee as shipping_fee
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN livestock l ON oi.livestock_id = l.livestock_id
    JOIN farmer f ON l.farmer_id = f.farmer_id
    JOIN customer c ON o.customer_id = c.customer_id
    LEFT JOIN payments p ON o.order_id = p.order_id 
    LEFT JOIN delivery d ON o.order_id = d.order_id
    WHERE o.stripe_payment_id LIKE :stripe_base"; 

    $stmt = $pdo->prepare($query);
    $stmt->execute([':stripe_base' => $base_stripe_id . '%']); 
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$all_items) {
        die("Record not found.");
    }

    $data = $all_items[0];

    if ($data['farmer_id'] != ($_SESSION['farmer_id'] ?? 0) && $data['customer_id'] != ($_SESSION['customer_id'] ?? 0)) {
        header("HTTP/1.1 403 Forbidden");
        die("Access Denied.");
    }

    $total_deposit_paid = 0;
    $is_auction_order = false;
    $harvest_fee_actual = 0;
    $shipping_fee_actual = 0;
    $total_paid = 0;
    $display_payment_method = "Stripe Payment";

    foreach ($all_items as $item) {
        $stmtAuc = $pdo->prepare("SELECT auction_id FROM auction WHERE livestock_id = ?");
        $stmtAuc->execute([$item['livestock_id']]);
        $auc_id = $stmtAuc->fetchColumn();

        if ($auc_id) {
            $is_auction_order = true;
            $stmtDep = $pdo->prepare("SELECT amount FROM auction_deposits_paid 
                                      WHERE auction_id = ? 
                                      ORDER BY created_at DESC LIMIT 1");
            $stmtDep->execute([$auc_id]);
            $dep_amount = (float)$stmtDep->fetchColumn();
            $total_deposit_paid += $dep_amount;
        }
    }

    try {
        $stripe = new \Stripe\StripeClient('sk_test_51SipzdEhjpQ4R31fUn7iS5Ld3K4vigl5Hzx05UWBokwZ1dypneBTDXsSG0yAq4NiR4Bbag336ykhYseXJw5CHDJZ00Pi7SPtFt');
        $intent = $stripe->paymentIntents->retrieve($base_stripe_id, ['expand' => ['payment_method']]);
        
        $total_paid = $intent->amount / 100; 
        $harvest_fee_actual = (float)($intent->metadata->harvest_amount ?? 0);
        $shipping_fee_actual = (float)($intent->metadata->shipping_fee ?? 0);
        $service_display_name = $intent->metadata->service_names ?? 'Professional Harvesting Services';
        $meta_shipping_method = $intent->metadata->shipping_method ?? null;

        if (isset($intent->payment_method) && !is_string($intent->payment_method)) {
            $method = $intent->payment_method;
            if ($method->type === 'card') {
                $display_payment_method = "Card (" . ucfirst($method->card->brand) . " ****" . $method->card->last4 . ")";
            } elseif ($method->type === 'fpx') {
                $bank_name = ucwords(str_replace('_', ' ', $method->fpx->bank));
                $display_payment_method = "Online Banking ($bank_name)";
            }
        }
    } catch (Exception $e) {
        $total_paid = (float)$data['total_paid_amount'];
        $shipping_fee_actual = (float)($data['shipping_fee'] ?? 0);
        $display_payment_method = $data['payment_method'] ?? "Stripe Payment";
    }

    $calculated_subtotal = ($total_paid + $total_deposit_paid) - $harvest_fee_actual - $shipping_fee_actual;

    $items_rows_html = '';
    foreach ($all_items as $item) {
        $price = (float)($item['item_price'] ?? 0); 
        $items_rows_html .= '
        <tr>
        <td>
        <strong style="color: #0d1b2a;">' . htmlspecialchars($item['livestock_name']) . '</strong><br>
        <span style="color: #718096; font-size: 11px;">
        Breed: ' . htmlspecialchars($item['breed']) . ' | Category: ' . htmlspecialchars($item['category']) . '
        </span>
        </td>
        <td>Livestock</td>
        <td style="text-align: right;">' . number_format($price, 2) . '</td>
        </tr>';
    }

    if ($harvest_fee_actual > 0) {
        $items_rows_html .= '
        <tr>
        <td>
            <strong style="color: #0d1b2a;">' . htmlspecialchars($service_display_name) . '</strong><br>
            <span style="color: #718096; font-size: 11px;">Additional farm services requested</span>
        </td>
        <td>Service</td>
        <td style="text-align: right;">' . number_format($harvest_fee_actual, 2) . '</td>
        </tr>';
    }

    if ($shipping_fee_actual > 0) {
        $method_name = $meta_shipping_method ?? $data['shipping_method'] ?? 'Standard Delivery';
        $items_rows_html .= '
        <tr>
        <td>
        <strong style="color: #0d1b2a;">Delivery Fee</strong><br>
        <span style="color: #718096; font-size: 11px;">Method: ' . htmlspecialchars($method_name) . '</span>
        </td>
        <td>Shipping</td>
        <td style="text-align: right;">' . number_format($shipping_fee_actual, 2) . '</td>
        </tr>';
    }

    $html = '
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #333; margin: 0; padding: 0; }
        .header { background: #0d1b2a; color: #ffffff; padding: 40px 30px; text-align: left; }
        .header h1 { margin: 0; font-size: 26px; color: #ffffff; letter-spacing: 1px; }
        .header p { margin: 5px 0 0 0; opacity: 0.7; font-size: 11px; text-transform: uppercase; }
        .main-container { padding: 40px; }
        .meta-table { width: 100%; margin-bottom: 40px; border-spacing: 0; }
        .meta-col { vertical-align: top; font-size: 13px; line-height: 1.5; }
        .section-label { color: #1976d2; font-weight: bold; text-transform: uppercase; font-size: 10px; margin-bottom: 8px; display: block; }
        .address-box { color: #555; font-size: 12px; margin-top: 5px; line-height: 1.4; }
        .stamp-status { border: 2px solid #2d6a4f; color: #2d6a4f; padding: 8px 15px; display: inline-block; font-weight: bold; font-size: 14px; text-transform: uppercase; margin-top: 15px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.items th { background: #f8fafc; color: #0d1b2a; padding: 15px; text-align: left; border-bottom: 2px solid #1976d2; font-size: 11px; text-transform: uppercase; }
        table.items td { padding: 15px; border-bottom: 1px solid #edf2f7; font-size: 13px; }
        .total-section { margin-top: 30px; text-align: right; }
        .total-row { display: inline-block; width: 300px; padding-top: 5px; text-align: right; }
        .final-total { border-top: 2px solid #0d1b2a; margin-top: 10px; padding-top: 15px; }
        .total-amount { font-size: 24px; font-weight: bold; color: #1976d2; }
        .footer { text-align: center; margin-top: 100px; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 20px; }
    </style>

    <div class="header">
        <h1>' . htmlspecialchars($data['farm_name']) . '</h1>
        <p>Official Transaction Receipt • RanchLink Marketplace</p>
    </div>

    <div class="main-container">
        <table class="meta-table">
            <tr>
                <td class="meta-col" style="width: 55%;">
                    <span class="section-label">Billed To:</span>
                    <strong>' . htmlspecialchars($data['customer_name']) . '</strong><br>
                    ' . htmlspecialchars($data['customer_email']) . '<br>
                    
                    <span class="section-label" style="margin-top:15px;">Shipping & Delivery Details:</span>
                    <div class="address-box">
                        <strong>Recipient:</strong> ' . htmlspecialchars($data['recipient_name'] ?? $data['customer_name']) . '<br>
                        <strong>Phone:</strong> ' . htmlspecialchars($data['delivery_phone'] ?? 'N/A') . '<br>
                        ' . nl2br(htmlspecialchars($data['deliveryaddress'])) . '<br>
                        ' . htmlspecialchars($data['postcode']) . ' ' . htmlspecialchars($data['city']) . ', ' . htmlspecialchars($data['state']) . '<br>
                        <strong>Method:</strong> ' . htmlspecialchars($meta_shipping_method ?? $data['shipping_method'] ?? 'Standard Delivery') . '
                    </div>
                </td>
                <td class="meta-col" style="width: 45%; text-align: right;">
                    <span class="section-label">Invoice Details:</span>
                    <strong>Payment ID:</strong> ' . htmlspecialchars($base_stripe_id) . '<br>
                    <strong>Date:</strong> ' . date('d F Y', strtotime($data['order_date'])) . '<br>
                    <strong>Payment Method:</strong> ' . htmlspecialchars($display_payment_method) . '<br>
                    <div class="stamp-status">SUCCESSFULLY PAID</div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 50%;">Item Description</th>
                    <th style="width: 25%;">Type</th>
                    <th style="width: 25%; text-align: right;">Amount (RM)</th>
                </tr>
            </thead>
            <tbody>
                ' . $items_rows_html . '
            </tbody>
        </table>

        <div class="total-section">
        <div class="total-row">
                <span style="font-size: 12px; color: #718096;">
                    ' . ($is_auction_order ? 'WINNING BID SUBTOTAL:' : 'LIVESTOCK SUBTOTAL:') . '
                </span>
                <span style="font-weight: bold;">RM ' . number_format($calculated_subtotal, 2) . '</span>
            </div><br>
            
            ' . ($is_auction_order && $total_deposit_paid > 0 ? '
            <div class="total-row">
                <span style="font-size: 12px; color: #e53e3e;"> DEPOSIT PAID: </span>
                <span style="font-weight: bold; color: #e53e3e;">- RM ' . number_format($total_deposit_paid, 2) . '</span>
            </div><br>' : '') . '
        
        ' . ($harvest_fee_actual > 0 ? '
            <div class="total-row">
            <span style="font-size: 12px; color: #718096;">HARVEST SERVICE: </span>
            <span style="font-weight: bold;">RM ' . number_format($harvest_fee_actual, 2) . '</span>
            </div><br>' : '') . '

        ' . ($shipping_fee_actual > 0 ? '
            <div class="total-row">
            <span style="font-size: 12px; color: #718096;">SHIPPING FEE: </span>
            <span style="font-weight: bold;">RM ' . number_format($shipping_fee_actual, 2) . '</span>
            </div><br>' : '') . '

        <div class="total-row final-total">
        <span style="font-size: 12px; color: #0d1b2a; text-transform: uppercase; font-weight: bold;">Total Amount Paid</span><br>
        <span class="total-amount">RM ' . number_format($total_paid, 2) . '</span>
        </div>
        </div>
    </div>';

    $options = new Options();
    $options->set('isRemoteEnabled', true); 
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    if (ob_get_length()) ob_end_clean(); 
    
    $dompdf->stream("Receipt_Transaction_" . $base_stripe_id . ".pdf", ["Attachment" => false]);
    exit();

} catch (Exception $e) {
    die("Error generating PDF: " . $e->getMessage());
}