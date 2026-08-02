<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../inc/numbers.php';

\Stripe\Stripe::setApiKey('sk_test_51SipzdEhjpQ4R31fUn7iS5Ld3K4vigl5Hzx05UWBokwZ1dypneBTDXsSG0yAq4NiR4Bbag336ykhYseXJw5CHDJZ00Pi7SPtFt');

$payment_intent_id = $_GET['payment_intent'] ?? null;

$order_id = 0;
$order_id_to_report = 0;
$service_list = 'No services selected';
$display_animal_price = 0;
$display_harvest_fee = 0;
$display_shipping_fee = 0;
$final_amount = 0;
$payment_method_display = "Stripe Payment"; 

if (!$payment_intent_id) {
    die("Invalid Session.");
}

try {
    $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id, [
        'expand' => ['payment_method']
    ]);

    if ($intent->status === 'succeeded') {
        $final_amount = $intent->amount / 100;
        if ($intent->status === 'succeeded') {
        $final_amount = $intent->amount / 100;

        // 4. NOW DETECT THE PAYMENT METHOD (Now $intent actually exists)
        if (isset($intent->payment_method)) {
            $method_obj = $intent->payment_method;
            if (is_string($method_obj)) {
                $method_obj = \Stripe\PaymentMethod::retrieve($method_obj);
            }
            
            if ($method_obj->type === 'card') {
                $payment_method_display = "Card (" . ucfirst($method_obj->card->brand) . " ****" . $method_obj->card->last4 . ")";
            } elseif ($method_obj->type === 'fpx') {
                $bank_name = ucwords(str_replace('_', ' ', $method_obj->fpx->bank));
                $payment_method_display = "Online Banking ($bank_name)";
            }
        }



        $check_id = $intent->id . "-0"; 
        $checkStmt = $pdo->prepare("SELECT order_id FROM orders WHERE stripe_payment_id = ?");
        $checkStmt->execute([$check_id]);
        $already_exists_id = $checkStmt->fetchColumn();

        if ($already_exists_id) {
            $order_id_to_report = $already_exists_id;
            $stmtService = $pdo->prepare("SELECT selected_services FROM orders WHERE order_id = ?");
            $stmtService->execute([$already_exists_id]);
            $saved_services = $stmtService->fetchColumn();
            $service_list = $saved_services ?: 'No services selected';
            $display_animal_price = (float)($intent->metadata->animal_amount ?? 0);
            $total_harvest_fee = (float)($intent->metadata->harvest_amount ?? 0);
            $total_shipping_fee = (float)($intent->metadata->shipping_fee ?? 0);
            $display_harvest_fee = $total_harvest_fee;
            $display_shipping_fee = $total_shipping_fee;
        } else {
            $livestock_ids_string = $intent->metadata->livestock_ids ?? ''; 
            $livestock_ids = !empty($livestock_ids_string) ? explode(',', $livestock_ids_string) : [];

            if (empty($livestock_ids)) {
                die("Error: No livestock items found.");
            }

            $customer_id = (int)($intent->metadata->customer_id ?? ($_SESSION['customer_id'] ?? 0));
            $selected_services = $intent->metadata->service_names ?? '';
            $total_harvest_fee = (float)($intent->metadata->harvest_amount ?? 0);
            $service_list = $intent->metadata->service_names ?? 'No services selected';
            $total_shipping_fee = (float)($intent->metadata->shipping_fee ?? 0);

            $payment_method_display = "Stripe Payment"; 
            if (isset($intent->payment_method)) {
                $method_obj = $intent->payment_method;
                if (is_string($method_obj)) {
                    $method_obj = \Stripe\PaymentMethod::retrieve($method_obj);
                }
                if ($method_obj->type === 'card') {
                    $payment_method_display = "Card (" . ucfirst($method_obj->card->brand) . " ****" . $method_obj->card->last4 . ")";
                } elseif ($method_obj->type === 'fpx') {
                    $bank_name = ucwords(str_replace('_', ' ', $method_obj->fpx->bank));
                    $payment_method_display = "Online Banking ($bank_name)";
                }
            }

        $pdo->beginTransaction();

        $created_order_ids = [];
        $is_first_item = true;
        $captured_total = $intent->amount / 100; 

        foreach ($livestock_ids as $index => $l_id) {
            $l_id = (int)trim($l_id);
            if ($l_id <= 0) continue;

            $base_price = 0;
            $remaining_animal_balance = 0;
            $item_shipping = $is_first_item ? $total_shipping_fee : 0;
            $item_harvest = $is_first_item ? $total_harvest_fee : 0;

            $stmtAuction = $pdo->prepare("SELECT current_bid, auction_id FROM auction WHERE livestock_id = ? AND status = 'closed'");
            $stmtAuction->execute([$l_id]);
            $auction_data = $stmtAuction->fetch(PDO::FETCH_ASSOC);

            if ($auction_data) {
                $base_price = (float)$auction_data['current_bid'];
                $auction_id = $auction_data['auction_id'];

                $stmtDeposit = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM auction_deposits_paid WHERE auction_id = ?");
                $stmtDeposit->execute([$auction_id]);
                $deposit_paid = (float)$stmtDeposit->fetchColumn();

                $remaining_animal_balance = $base_price - $deposit_paid;
            } else {
                $stmtNormal = $pdo->prepare("SELECT price FROM livestock WHERE livestock_id = ?");
                $stmtNormal->execute([$l_id]);
                $base_price = (float)$stmtNormal->fetchColumn();
                $remaining_animal_balance = $base_price;
            }

            if (count($livestock_ids) === 1) {
                $item_total = $captured_total;
            } else {
                $item_total = $remaining_animal_balance + $item_shipping + $item_harvest;
            }

            $unique_stripe_db_id = $intent->id . "-" . $index;

            $sqlOrder = "INSERT INTO orders (customer_id, livestock_id, stripe_payment_id, total_price, order_status, selected_services, order_date) 
            VALUES (?, ?, ?, ?, 'Paid', ?, NOW()) RETURNING order_id";
            $stmtOrder = $pdo->prepare($sqlOrder);
            $stmtOrder->execute([$customer_id, $l_id, $unique_stripe_db_id, $item_total, $selected_services]);
            $order_id = $stmtOrder->fetchColumn();
            $created_order_ids[] = $order_id;

            $sqlItem = "INSERT INTO order_items (order_id, livestock_id, selected_services, price_at_purchase, item_status) 
            VALUES (?, ?, ?, ?, 'Paid')";
            $pdo->prepare($sqlItem)->execute([$order_id, $l_id, $selected_services, $base_price]);

            $sqlDelivery = "INSERT INTO delivery (
                order_id, recipient_name, phone_number, email, 
                deliveryaddress, city, postcode, state, 
                shipping_method, deliveryfee, deliverystatus
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
            $pdo->prepare($sqlDelivery)->execute([
                $order_id,
                trim(($intent->metadata->first_name ?? '') . " " . ($intent->metadata->last_name ?? '')),
                $intent->metadata->phone ?? '',
                $intent->metadata->email ?? '',
                $intent->metadata->address ?? '',
                $intent->metadata->city ?? '',
                $intent->metadata->postcode ?? '',
                $intent->metadata->state ?? '',
                $intent->metadata->shipping_method ?? 'Self-Pickup',
                $item_shipping 
            ]);


            $sqlPay = "INSERT INTO payments (order_id, stripe_payment_id, amount, payment_status, payment_method, transaction_date) 
                       VALUES (?, ?, ?, 'paid', ?, NOW())";
            $pdo->prepare($sqlPay)->execute([$order_id, $unique_stripe_db_id, $item_total, $payment_method_display]);

            $pdo->prepare("UPDATE livestock SET availability_status = 'Sold' WHERE livestock_id = ?")->execute([$l_id]);
            $pdo->prepare("DELETE FROM cart WHERE customer_id = ? AND livestock_id = ?")->execute([$customer_id, $l_id]);

            $is_first_item = false;
        }

        $pdo->commit();

        $_SESSION['cart'] = [];
        setcookie('persistent_cart', json_encode([]), time() + (86400 * 30), "/");
        
        $order_id_to_report = $created_order_ids[0];

        $display_animal_price = (float)($intent->metadata->animal_amount ?? $base_price);
        $display_harvest_fee = $total_harvest_fee;
        $display_shipping_fee = $total_shipping_fee;
    }

    } else {
        header("Location: ../Models/customer_dashboard.php?error=not_confirmed");
        exit();
    }
}
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    die("Database Error: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- <meta http-equiv="refresh" content="6;url=download_receipt.php?order_id=<?= $order_id_to_report ?>"> -->
    <title>Payment Successful</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=PT+Serif&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    body { background: #fdf6ec; font-family: 'PT Serif', serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
    .receipt-card { 
        background: white; 
        padding: 40px; 
        border-radius: 25px; 
        box-shadow: 0 20px 45px rgba(0,0,0,0.08); 
        text-align: center; 
        max-width: 450px; 
        width: 90%; 
        border-top: 12px solid #0d1b2a; 
    }
    .check-icon { 
        width: 70px; height: 70px; background: #f0fff4; color: #2d6a4f; 
        border-radius: 50%; display: flex; align-items: center; justify-content: center; 
        font-size: 30px; margin: 0 auto 15px; border: 2px solid #c6f6d5;
    }
    .amount { font-size: 32px; font-weight: bold; color: #1976d2; margin-bottom: 25px; font-family: 'Cinzel', serif; }
    
    .details { 
        background: #f8fafc; padding: 20px; border-radius: 15px; 
        border: 1px solid #edf2f7; text-align: left; margin-bottom: 25px;
    }
    /* This keeps every row consistent */
    .receipt-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 14px;
        color: #4a5568;
    }
    .receipt-row strong { 
        color: #0d1b2a; font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; 
    }
    .service-tag {
        font-size: 13px; color: #718096; font-style: italic; margin-top: -5px; margin-bottom: 10px;
        display: block; padding-left: 5px; border-left: 2px solid #cbd5e0;
    }

    .btn-receipt { 
        background: #0d1b2a; color: white; padding: 16px; text-decoration: none; 
        border-radius: 12px; display: block; font-weight: bold; font-family: 'Cinzel', serif; 
        transition: 0.3s; margin-bottom: 15px; font-size: 14px;
    }
    .btn-receipt:hover { background: #1976d2; transform: translateY(-2px); }
    .btn-dashboard { color: #718096; text-decoration: none; font-size: 13px; font-weight: 600; display: block; }
</style>
</head>
<body>
    <div class="receipt-card">
        <div class="check-icon"><i class="fas fa-check"></i></div>
        <h2 style="font-family: 'Cinzel', serif; color: #0d1b2a; margin-bottom: 5px;">Success!</h2>
        <p style="margin-top:0; color: #718096;">We have received your payment.</p>
        
        <div class="amount">RM <?= number_format($final_amount, 2) ?></div>
        
        <div class="details">
        <div class="receipt-row">
            <strong>Order Number</strong>
            <span><?= formatOrderNumber($order_id_to_report) ?></span>
        </div>

        <div class="receipt-row">
            <strong>Livestock Price</strong>
            <span>RM <?= number_format($display_animal_price, 2) ?></span>
        </div>

        <?php if ($display_harvest_fee > 0): ?>
            <div class="receipt-row">
                <strong>Service Fee</strong>
                <span>RM <?= number_format($display_harvest_fee, 2) ?></span>
            </div>
            <span class="service-tag">Service: <?= htmlspecialchars($service_list); ?></span>
        <?php endif; ?>

        <?php if ($display_shipping_fee > 0): ?>
            <div class="receipt-row">
                <strong>Shipping Fee</strong>
                <span>RM <?= number_format($display_shipping_fee, 2) ?></span>
            </div>
        <?php endif; ?>

        <div class="receipt-row">
            <strong>Payment Method</strong>
            <span><?= htmlspecialchars($payment_method_display) ?></span>
        </div>

        <hr style="border: 0; border-top: 1px dashed #cbd5e0; margin: 15px 0;">

        <div class="receipt-row" style="font-weight: bold; color: #0d1b2a;">
            <span>TOTAL PAID</span>
            <span>RM <?= number_format($final_amount, 2) ?></span>
        </div>
    </div>
        
        <a href="download_receipt.php?order_id=<?= $order_id_to_report ?>" class="btn-receipt">
            <i class="fas fa-file-download"></i> Download Receipt
        </a><br>
        <a href="../Models/order_tracking.php?order_id=<?= $order_id_to_report ?>" class="btn-dashboard">
            <i class="fas fa-truck"></i> View Your Order
        </a>
    </div>

    <!-- <script>
        let timeLeft = 6;
        let timerElement = document.getElementById('timer');
        let downloadUrl = "download_receipt.php?order_id=<?= $order_id_to_report ?>";

        setInterval(() => {
            timeLeft--;
            if(timeLeft >= 0) {
                timerElement.innerText = timeLeft;
            }
            if(timeLeft === 0) {
                window.location.href = downloadUrl;
            }
        }, 1000);
    </script> -->
</body>
</html>