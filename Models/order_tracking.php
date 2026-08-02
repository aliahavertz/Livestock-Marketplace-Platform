<?php
session_start();
require_once '../db_connect.php';
include '../inc/header.php';
include '../inc/numbers.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit();
}

$customer_id = (int)$_SESSION['customer_id']; 
$order_input = $_GET['order_id'] ?? null;

if (!$order_input) {
    die("Error: No Order ID provided.");
}

try {
    if (strpos($order_input, 'pi_') === 0) {
        $sql = "SELECT o.*, l.name as animal_name, l.breed, l.image, oi.item_status,
               d.recipient_name, d.phone_number, d.deliveryaddress, d.city, 
               d.postcode, d.state, d.shipping_method 
        FROM orders o 
        JOIN order_items oi ON o.order_id = oi.order_id 
        JOIN livestock l ON oi.livestock_id = l.livestock_id 
        LEFT JOIN delivery d ON o.order_id = d.order_id
        LEFT JOIN payments p ON o.order_id = p.order_id
                WHERE p.stripe_payment_id = :oid AND o.customer_id = :cid";
    } else {
        $sql = "SELECT o.*, l.name as animal_name, l.breed, l.image, oi.item_status,
               d.recipient_name, d.phone_number, d.deliveryaddress, d.city, 
               d.postcode, d.state, d.shipping_method 
        FROM orders o 
        JOIN order_items oi ON o.order_id = oi.order_id 
        JOIN livestock l ON oi.livestock_id = l.livestock_id 
        LEFT JOIN delivery d ON o.order_id = d.order_id
                WHERE o.order_id = :oid AND o.customer_id = :cid";
        $order_input = (int)$order_input;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['oid' => $order_input, 'cid' => $customer_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found or you do not have permission to view it.");
    }
    $shipping_method = $order['shipping_method'] ?? '';

    $is_pickup = (isset($order['shipping_method']) && stripos($order['shipping_method'], 'pickup') !== false);

    if ($is_pickup) {
        $statuses = ['Paid', 'Preparing', 'Ready for Pickup', 'Delivered'];
    } else {
        $statuses = ['Paid', 'Preparing', 'In Transit', 'Out for Delivery', 'Delivered'];
    }

    $db_status = $order['status'];
    $current_index = array_search($db_status, $statuses);
    
   if ($db_status === 'pending' || $db_status === 'Paid') {
        $current_index = 0;
    } elseif ($current_index === false) {
        $current_index = -1; 
    }

    $is_terminated = (strcasecmp($order['status'], 'Terminated') === 0 || strcasecmp($order['status'], 'Cancelled') === 0 );
    $is_cancelled_order = ($order['status'] == 'Cancelled Order');
    $is_refunded = ($order['status'] == 'Refunded');
    $is_delivered = ($order['status'] == 'Delivered');

    $stmtLogs = $pdo->prepare("SELECT * FROM delivery WHERE order_id = :oid ORDER BY created_at DESC");
    $stmtLogs->execute([':oid' => $order['order_id']]);
    $delivery_history = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    $checkReview = $pdo->prepare("SELECT feedback_id FROM feedback WHERE order_id = ?");
    $checkReview->execute([$order['order_id']]);
    $already_reviewed = $checkReview->fetch();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$images = explode(',', $order['image']);
$first_image = trim($images[0]);
$display_img = !empty($first_image) ? '../farmer/uploads/'.$first_image : '../assets/no-image.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --primary: #1976d2;
            --dark: #0d1b2a;
            --bg-page: #fdf6ec;
            --gray: #e2e8f0; 
            --danger: #c53030; 
            --warning: #ff9800; 
            --success: #2d6a4f;
        }

        body { 
            font-family: 'PT Serif', serif; 
            background: var(--bg-page); 
            margin: 0; 
            padding-top: 100px; 
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }

        .track-card { 
            width: 90%;
            max-width: 700px; 
            background: #ffffff; 
            padding: 30px; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            margin: 40px auto;
        }

        .customer-details {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 10px;
        }

        .detail-item label {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #a0aec0;
            font-weight: 800;
            margin-bottom: 2px;
        }

        .detail-item p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--dark);
            font-weight: 600;
        }

        .full-width {
            grid-column: span 2;
        }

        h2 { font-weight: 800; text-align: center; margin-bottom: 25px; font-size: 1.5rem; }
        
        .order-meta { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
            background: #f8fafc; 
            padding: 15px; 
            border-radius: 18px; 
            margin-bottom: 30px; 
            border: 1px solid #edf2f7;
        }
        
        .order-meta img { 
            width: 80px; height: 80px; 
            object-fit: cover; 
            border-radius: 12px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .order-meta h3 { margin: 0; color: var(--primary); font-size: 1.2em; }

        .progressbar { 
            display: flex; 
            justify-content: space-between; 
            list-style: none; 
            padding: 0; 
            margin: 40px 0; 
            position: relative; 
        }
        .progressbar li { 
            width: 100%; 
            position: relative; 
            text-align: center; 
            font-size: 9px; 
            color: #a0aec0; 
            z-index: 1; 
            text-transform: uppercase;
            font-weight: 600;
        }
        .progressbar li::after { 
            content: ''; 
            position: absolute; 
            width: 100%; 
            height: 3px; 
            background: var(--gray); 
            top: 12px; 
            left: 50%; 
            z-index: -1; 
        }
        .progressbar li:last-child::after { content: none; }
        .progressbar li::before { 
            content: ''; 
            width: 24px; height: 24px; 
            line-height: 24px; 
            border: 3px solid #fff; 
            display: block; 
            margin: 0 auto 10px; 
            border-radius: 50%; 
            background: var(--gray); 
            box-shadow: 0 0 0 1px var(--gray);
        }

        .progressbar li.active { color: var(--dark); }
        .progressbar li.active::before { 
            background: var(--primary); 
            box-shadow: 0 0 0 1px var(--primary);
            content: '\f00c'; 
            font-family: "Font Awesome 5 Free"; 
            font-weight: 900; 
            color: white; 
            font-size: 10px;
        }
        .progressbar li.active::after { background: var(--primary); }

        .status-badge {
            background: #ebf8ff;
            color: var(--primary);
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 700;
            display: inline-block;
            font-size: 0.85em;
        }

        .main-content-wrapper {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }

        .left-column {
            flex: 1.2; 
            min-width: 0;
        }

        .right-column {
            flex: 0.8;
            background: #f8fafc;
            border-radius: 18px;
            padding: 20px;
            border: 1px solid #edf2f7;
            position: sticky;
            top: 120px; 
            max-height: 500px; 
            display: flex;
            flex-direction: column;
        }

        .history-scroll-area {
            overflow-y: auto;
            padding-right: 10px;
        }

        .history-scroll-area::-webkit-scrollbar {
            width: 6px;
        }
        .history-scroll-area::-webkit-scrollbar-thumb {
            background-color: #cbd5e0;
            border-radius: 10px;
        }

        @media (max-width: 850px) {
            .main-content-wrapper {
                flex-direction: column;
            }
            .right-column {
                width: 100%;
                max-height: none;
            }
        }

        .history-section { 
            margin-top: 0; 
            padding-top: 0; 
            border-top: none; 
        }
        .history-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px; }
        .history-timeline { position: relative; padding-left: 30px; text-align: left; }
        .timeline-item { position: relative; padding-bottom: 25px; }
        .timeline-item:not(:last-child)::before { content: ''; position: absolute; left: -21px; top: 10px; height: 100%; width: 2px; background: #e2e8f0; }
        .timeline-item::after { content: ''; position: absolute; left: -27px; top: 5px; width: 14px; height: 14px; background: var(--primary); border: 3px solid #fff; border-radius: 50%; box-shadow: 0 0 0 1px var(--primary); z-index: 2; }
        .timeline-date { font-size: 0.75rem; font-weight: 700; color: #a0aec0; }
        .timeline-content { background: #f8fafc; padding: 12px 15px; border-radius: 12px; margin-top: 5px; }
        .timeline-note { 
            font-size: 0.9rem; 
            color: #4a5568; 
            margin: 5px 0 0 0; 
            font-style: italic; 
            white-space: pre-line; 
        }

        .btn-review { display: inline-block; background: var(--dark); color: white !important; padding: 12px 24px; text-decoration: none; border-radius: 10px; font-weight: 700; margin-top: 15px; }
        .btn-back { display: inline-block; margin-top: 25px; text-decoration: none; color: #718096; font-size: 0.9em; font-weight: 600; }
    </style>
</head>
<body>

<div class="track-card" style="max-width: 1000px;"> 
    <h2>Your Order Details</h2>

    <div class="main-content-wrapper">
        <div class="left-column">
            <div class="customer-details">
                <h4 style="margin: 0 0 15px 0; font-size: 1rem; color: var(--dark); border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                    <i class="fas fa-shipping-fast" style="color: var(--primary);"></i> Shipping Information
                </h4>
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Recipient Name</label>
                        <p><?= htmlspecialchars($order['recipient_name'] ?? 'N/A') ?></p>
                    </div>
                    <div class="detail-item">
                        <label>Phone Number</label>
                        <p><?= htmlspecialchars($order['phone_number'] ?? 'N/A') ?></p>
                    </div>
                    <div class="detail-item full-width">
                        <label>Delivery Address</label>
                        <p>
                            <?= htmlspecialchars($order['deliveryaddress'] ?? 'No address provided') ?><br>
                            <?= htmlspecialchars($order['postcode'] ?? '') ?> <?= htmlspecialchars($order['city'] ?? '') ?>, 
                            <?= htmlspecialchars($order['state'] ?? '') ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="order-meta">
                <img src="<?= $display_img ?>" alt="Livestock">
                <div>
                    <p style="margin:0; color:#a0aec0; font-size: 0.75em; font-weight: 700;">Order number: <?= formatOrderNumber($order['order_id']) ?></p>
                    <h3><?= htmlspecialchars($order['animal_name']) ?></h3>
                    <p style="margin:0; font-size: 0.9em; color: #718096;">
                        Delivery Method: <strong><?= !empty($order['shipping_method']) ? htmlspecialchars($order['shipping_method']) : 'Standard Delivery' ?></strong>
                    </p>
                </div>
            </div>

            <?php if (!$is_terminated && !$is_cancelled_order && !$is_refunded): ?>
                <ul class="progressbar">
                    <?php foreach ($statuses as $index => $step): ?>
                        <li class="<?= ($index <= $current_index) ? 'active' : '' ?>"><?= $step ?></li>
                    <?php endforeach; ?>
                </ul>
                
                <div style="text-align: center; margin-top: 20px;">
                    <?php if ($is_delivered && !$already_reviewed): ?>
                        <div style="background: #f0fff4; padding: 15px; border-radius: 15px; border: 1px solid #c6f6d5;">
                            <a href="feedback.php?order_id=<?= $order['order_id'] ?>" class="btn-review">Rate Now</a>
                        </div>
                    <?php else: ?>
                        <div class="status-badge"><?= strtoupper($order['item_status']) ?></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 20px; background: #fff5f5; border-radius: 15px;">
                    <p>Order is currently: <strong><?= $order['status'] ?></strong></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="right-column">
    <h3 class="history-title" style="margin-top: 0;"><i class="fas fa-history"></i> Delivery Updates</h3>
    <div class="history-scroll-area">
        <div class="history-timeline">
            <?php if (!empty($delivery_history)): ?>
                <?php foreach ($delivery_history as $log): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?= date('M d, h:i A', strtotime($log['created_at'])) ?></div>
                        <div class="timeline-content">
                            <strong style="display: block; font-size: 0.85em; color: var(--primary);">
                                <?php 
                                if (!empty($log['deliverydate']) && $log['deliverydate'] != '0000-00-00') {
                                    echo 'Scheduled: ' . date('d M', strtotime($log['deliverydate']));
                                } else {
                                    echo 'Order Status: ' . htmlspecialchars(!empty($order['item_status']) ? $order['item_status'] : $order['status']);
                                }
                                ?>
                            </strong>
                            
                            <?php if (!empty(trim($log['delivery_notes']))): ?>
                                <p class="timeline-note">"<?= htmlspecialchars($log['delivery_notes']) ?>"</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="timeline-item">
                    <div class="timeline-date"><?= date('M d, h:i A') ?></div>
                    <div class="timeline-content">
                        <?php if ($is_terminated || $is_cancelled_order): ?>
                            <strong style="display: block; font-size: 0.85em; color: var(--danger);">
                                Current Status: TERMINATED
                            </strong>
                        <?php else: ?>
                            <strong style="display: block; font-size: 0.85em; color: var(--success);">
                                Current Status: <?= htmlspecialchars(!empty($order['item_status']) ? $order['item_status'] : $order['status']) ?>
                            </strong>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

    <div style="text-align: center; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px;">
        <a href="my_orders.php" class="btn-back"><i class="fas fa-chevron-left"></i> MY ORDERS</a>
    </div>
</div>

</body>
</html>