<?php
session_start();
include '../db_connect.php';
include '../inc/header.php';

if (!isset($_SESSION['customer_id']) || !isset($_POST['livestock_id'])) {
    header("Location: ../Models/customer_dashboard.php");
    exit();
}

$livestock_id = (int)$_POST['livestock_id'];
$customer_id = $_SESSION['customer_id'];
$auction_id = $_POST['auction_id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM livestock WHERE livestock_id = ?");
$stmt->execute([$livestock_id]);
$livestock = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$livestock) die("Livestock not found.");

$stmtCust = $pdo->prepare("SELECT name, email, phone_number, address FROM customer WHERE customer_id = ?");
$stmtCust->execute([$customer_id]);
$customer = $stmtCust->fetch(PDO::FETCH_ASSOC);

$stmtHarvest = $pdo->prepare("SELECT * FROM harvestservice WHERE livestockid = ?");
$stmtHarvest->execute([$livestock_id]);
$harvestServices = $stmtHarvest->fetchAll(PDO::FETCH_ASSOC);

$images = !empty($livestock['image']) ? explode(',', $livestock['image']) : ['../assets/no-image.png'];
$firstImg = trim($images[0]);
$imgSrc = (strpos($firstImg, '../') === false) ? "../farmer/uploads/" . $firstImg : $firstImg;

$stmtFarm = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmtFarm->execute([$livestock['farmer_id']]);
$farm_name = $stmtFarm->fetchColumn() ?: "Verified Rancher";

$is_auction = !empty($auction_id);
$base_price = $livestock['price'];
$deposit_paid = 0;
$winning_bid = 0;

if ($is_auction) {
    $stmtAuc = $pdo->prepare("
        SELECT a.current_bid, ad.amount as deposit 
        FROM auction a 
        LEFT JOIN auction_deposits_paid ad ON a.auction_id = ad.auction_id 
        AND ad.customer_id = ? AND ad.status = 'paid'
        WHERE a.auction_id = ?
    ");
    $stmtAuc->execute([$customer_id, $auction_id]);
    $aucData = $stmtAuc->fetch(PDO::FETCH_ASSOC);
    
    if ($aucData) {
        $winning_bid = $aucData['current_bid'];
        $deposit_paid = $aucData['deposit'] ?? 0;
        $base_price = $winning_bid - $deposit_paid;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Checkout | Ranch Outlet</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-navy: #0d1b2a;
            --accent-blue: #1976d2;
            --vintage-cream: #fdf6ec;
            --soft-ivory: #f9f7f2;
            --border-light: #e0dcd0;
        }

        body { 
            background-color: var(--vintage-cream); 
            font-family: 'PT Serif', serif; 
            color: #1a1a1a; 
            margin: 0;
            padding: 0;
        }

        /* Luxury Progress Stepper */
        .stepper-wrapper {
            max-width: 800px;
            margin: 40px auto;
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        .stepper-item {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 1;
        }
        .stepper-item::before {
            content: "";
            position: absolute;
            top: 10px;
            left: -50%;
            width: 100%;
            height: 1px;
            background: var(--border-light);
            z-index: -1;
        }
        .stepper-item:first-child::before { display: none; }
        .step-counter {
            width: 20px;
            height: 20px;
            background: white;
            border: 1px solid var(--border-light);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stepper-item.active .step-counter {
            background: var(--primary-navy);
            border-color: var(--primary-navy);
        }
        .step-name {
            font-family: 'Cinzel';
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #888;
        }
        .stepper-item.active .step-name { color: var(--primary-navy); font-weight: bold; }

        .checkout-main {
            max-width: 1200px;
            margin: 0 auto 60px;
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 30px;
            padding: 0 20px;
        }

        /* Left Section: Details */
        .section-box {
            background: white;
            padding: 40px;
            border: 1px solid var(--border-light);
        }

        .item-details-row {
            display: flex;
            gap: 25px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
            margin-bottom: 30px;
        }
        .item-details-row img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 2px;
            border: 1px solid #eee;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .info-group h4 {
            font-family: 'Cinzel';
            font-size: 12px;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 15px;
            border-bottom: 1px solid #fafafa;
            padding-bottom: 5px;
            text-transform: uppercase;
        }

        .info-group p {
            margin: 5px 0;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .harvest-header {
            font-family: 'Cinzel';
            font-size: 13px;
            letter-spacing: 1px;
            background: #fcfcfc;
            padding: 12px;
            border-left: 3px solid var(--primary-navy);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
        }

        /* Service List */
        .service-row-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .service-info label {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .service-price { font-family: 'PT Serif'; font-weight: bold; color: var(--accent-blue); }

        /* Right Section: Totals */
        .summary-side {
            background: var(--soft-ivory);
            padding: 40px;
            border: 1px solid var(--border-light);
            height: fit-content;
        }
        .summary-title {
            font-family: 'Cinzel';
            font-size: 16px;
            margin-bottom: 25px;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 10px;
            letter-spacing: 1px;
        }

        .price-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        .price-line.total {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--primary-navy);
            font-size: 1.4rem;
            font-family: 'Cinzel';
            font-weight: bold;
        }

        textarea {
            width: 100%;
            border: 1px solid var(--border-light);
            padding: 15px;
            font-family: inherit;
            margin-top: 10px;
            background: #fff;
            box-sizing: border-box;
            resize: vertical;
        }

        .btn-pay {
            width: 100%;
            background: var(--primary-navy);
            color: white;
            padding: 22px;
            border: none;
            font-family: 'Cinzel';
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 30px;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-pay:hover { background: #1a2a3a; letter-spacing: 3px; }

        .btn-back {
            display: block;
            text-align: center;
            margin-top: 25px;
            font-family: 'Cinzel';
            font-size: 11px;
            color: #999;
            text-decoration: none;
            letter-spacing: 1px;
            transition: color 0.3s;
        }
        .btn-back:hover { color: #cc0000; }

        @media (max-width: 900px) {
            .checkout-main { grid-template-columns: 1fr; }
            .details-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="stepper-wrapper">
    <div class="stepper-item active"><div class="step-counter"></div><div class="step-name">Selection</div></div>
    <div class="stepper-item"><div class="step-counter"></div><div class="step-name">Checkout</div></div>
    <div class="stepper-item"><div class="step-counter"></div><div class="step-name">Confirmation</div></div>
</div>

<div class="checkout-main">
    <div class="section-box">
        <div class="item-details-row">
            <img src="<?= $imgSrc ?>" alt="Product Preview">
            <div style="flex:1;">
                <h2 style="font-family: 'Cinzel'; margin: 0 0 5px 0; font-size: 1.5rem; letter-spacing: 1px;"><?= htmlspecialchars($livestock['name']) ?></h2>
                <p style="margin: 0; font-family: 'Cinzel'; color: var(--accent-blue); font-size: 0.75rem; font-weight: bold;">
                    <i class="fas fa-certificate"></i> Verified by <?= htmlspecialchars($farm_name) ?>
                </p>
                <div style="margin-top: 15px; display: flex; gap: 20px; font-size: 0.85rem; color: #666;">
                    <span><strong>Breed:</strong> <?= htmlspecialchars($livestock['breed']) ?></span>
                    <span><strong>Weight:</strong> <?= htmlspecialchars($livestock['weight']) ?> KG</span>
                </div>
            </div>
        </div>

        <div class="details-grid">
            <div class="info-group">
                <h4><i class="fas fa-truck"></i> Recipient Information</h4>
                <p><strong>Name:</strong> <?= htmlspecialchars($customer['name']) ?></p>
                <p><strong>Contact:</strong> <?= htmlspecialchars($customer['phone_number'] ?: 'Required at Checkout') ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($customer['email']) ?></p>
                <p style="font-size: 0.75rem; color: #999; margin-top: 10px; font-style: italic;">Note: Full address will be confirmed on the secure payment page.</p>
            </div>
            <div class="info-group">
                <h4><i class="fas fa-tractor"></i> Farmer Information</h4>
                <p><strong>Farm Name:</strong> <?= htmlspecialchars($farm_name) ?></p>
                <p><strong>Method:</strong> <?= strtoupper($livestock['sale_type']) ?> Purchase</p>
                <p><strong>Security:</strong> Guaranteed Quality & Health</p>
            </div>
        </div>

        <div class="harvest-header">
            <i class="fas fa-concierge-bell"></i> Available Harvest Services
        </div>

        <?php if (!empty($harvestServices)): ?>
            <?php foreach ($harvestServices as $service): ?>
                <div class="service-row-item">
                    <div class="service-info">
                        <label>
                            <input type="checkbox" class="service-cb" data-price="<?= $service['servicefee'] ?>" name="services[]" value="<?= $service['serviceid'] ?>" form="checkout-form">
                            <?= htmlspecialchars($service['servicetype']) ?>
                        </label>
                    </div>
                    <div class="service-price">+ RM <?= number_format($service['servicefee'], 2) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="font-size: 0.85rem; color: #999; font-style: italic;">No additional services currently available for this livestock.</p>
        <?php endif; ?>

        <div style="margin-top: 40px;">
            <div class="harvest-header"><i class="fas fa-pen-nib"></i> Special Handling Instructions</div>
            <textarea name="harvest_remarks" form="checkout-form" rows="4" placeholder="e.g. Preferred portion sizes, organ handling, or specific collection notes..."></textarea>
        </div>
    </div>

    <div class="summary-side">
        <h3 class="summary-title">Order Summary</h3>
        
        <div class="price-line">
            <span>Item Price</span>
            <span>RM <?= number_format($base_price, 2) ?></span>
        </div>
        
        <div id="service-row" style="display:none;">
            <div class="price-line" style="color: var(--accent-blue);">
                <span>Harvest Service Fees</span>
                <span id="service-total">RM 0.00</span>
            </div>
        </div>

        <div class="price-line">
            <span>Delivery Fees</span>
            <span style="font-style: italic; color: #999;">TBD</span>
        </div>

        <div class="price-line total">
            <span>Final Amount</span>
            <span id="final-display-total">RM <?= number_format($base_price, 2) ?></span>
        </div>

        <form id="checkout-form" action="checkout.php" method="POST">
            <input type="hidden" name="livestock_id" value="<?= $livestock_id ?>">
            <input type="hidden" name="auction_id" value="<?= $auction_id ?>">
            <input type="hidden" name="total_with_services" id="hidden-total" value="<?= $base_price ?>">
            <button type="submit" class="btn-pay">Checkout</button>
        </form>

        <a href="../Models/livestock_view.php?livestock_id=<?= $livestock_id ?>" class="btn-back">
            <i class="fas fa-times"></i> Cancel & Return to Inventory
        </a>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border-light); text-align: center;">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/2560px-Visa_Inc._logo.svg.png" style="height: 12px; opacity: 0.5; margin: 0 10px;">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/1280px-Mastercard-logo.svg.png" style="height: 15px; opacity: 0.5; margin: 0 10px;">
        </div>
    </div>
</div>

<script>
    const basePrice = <?= $base_price ?>;
    const checkboxes = document.querySelectorAll('.service-cb');
    const serviceRow = document.getElementById('service-row');
    const serviceTotalText = document.getElementById('service-total');
    const finalDisplay = document.getElementById('final-display-total');
    const hiddenTotal = document.getElementById('hidden-total');

    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            let servicesSum = 0;
            checkboxes.forEach(box => {
                if (box.checked) servicesSum += parseFloat(box.getAttribute('data-price'));
            });

            serviceRow.style.display = servicesSum > 0 ? 'block' : 'none';
            serviceTotalText.innerText = 'RM ' + servicesSum.toFixed(2);

            const total = basePrice + servicesSum;
            finalDisplay.innerText = 'RM ' + total.toLocaleString(undefined, {minimumFractionDigits: 2});
            hiddenTotal.value = total;
        });
    });
</script>

</body>
</html>