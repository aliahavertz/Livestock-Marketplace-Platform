<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();
include '../db_connect.php';

$is_logged_in = isset($_SESSION['customer_id']);
$logged_customer_id = $_SESSION['customer_id'] ?? 0;

if (!isset($_GET['livestock_id'])) {
    die("Livestock ID is required.");
}

$livestock_id = $_GET['livestock_id'];

$sql = "SELECT * FROM livestock WHERE livestock_id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id', $livestock_id, PDO::PARAM_INT);
$stmt->execute();
$livestock = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$livestock) {
    die("Livestock not found.");
}

$images = !empty($livestock['image']) ? explode(',', $livestock['image']) : ['../assets/no-image.png'];

$sqlFarm = "SELECT name, farm_name, address, email, phone_number, profile_image FROM farmer WHERE farmer_id = :id LIMIT 1";
$stmtFarm = $pdo->prepare($sqlFarm);
$stmtFarm->bindParam(':id', $livestock['farmer_id'], PDO::PARAM_INT); 
$stmtFarm->execute();
$farmerDetails = $stmtFarm->fetch(PDO::FETCH_ASSOC);

if (!$farmerDetails) {
    $farmerDetails = ['name' => 'Unknown', 'farm_name' => 'Unknown Farm', 'email' => 'N/A', 'phone_number' => 'N/A', 'address' => 'N/A'];
}

$sqlHealth = "SELECT * FROM health WHERE livestockid = :id ORDER BY healthdate DESC";
$stmtHealth = $pdo->prepare($sqlHealth);
$stmtHealth->bindParam(':id', $livestock_id, PDO::PARAM_INT);
$stmtHealth->execute();
$healthRecords = $stmtHealth->fetchAll(PDO::FETCH_ASSOC);

$sqlHarvest = "SELECT * FROM harvestservice WHERE livestockid = :id ORDER BY serviceid DESC";
$stmtHarvest = $pdo->prepare($sqlHarvest);
$stmtHarvest->bindParam(':id', $livestock_id, PDO::PARAM_INT);
$stmtHarvest->execute();
$harvestRecords = $stmtHarvest->fetchAll(PDO::FETCH_ASSOC);

$logged_customer_id = $_SESSION['customer_id'] ?? 0;

$sqlFeedback = "SELECT f.*, c.name as customer_name, fr.farm_name 
                FROM feedback f 
                JOIN customer c ON f.customer_id = c.customer_id 
                LEFT JOIN farmer fr ON f.farmer_id = fr.farmer_id
                WHERE f.livestock_id = :id 
                AND (f.status = 'Approved' OR f.customer_id = :current_user)
                ORDER BY f.feedback_date DESC";

$stmtFeedback = $pdo->prepare($sqlFeedback);
$stmtFeedback->execute([
    'id' => $livestock_id, 
    'current_user' => $logged_customer_id
]);

$feedbackRecords = $stmtFeedback->fetchAll(PDO::FETCH_ASSOC);

$total_rating = 0;
if (count($feedbackRecords) > 0) {
    foreach ($feedbackRecords as $fb) {
        $total_rating += $fb['rating'];
    }
    $average = $total_rating / count($feedbackRecords);
} else {
    $average = 0;
}

$sqlAuction = "SELECT * FROM auction WHERE livestock_id = :id LIMIT 1";
$stmtAuction = $pdo->prepare($sqlAuction);
$stmtAuction->bindParam(':id', $livestock_id, PDO::PARAM_INT);
$stmtAuction->execute();
$auctionData = $stmtAuction->fetch(PDO::FETCH_ASSOC);

$startingPrice = $auctionData['starting_price'] ?? 0.00; 
$currentBid = $livestock['price']; 

if ($auctionData) {
$sqlMaxBid = "SELECT MAX(current_bid) as highest_bid FROM bidding WHERE livestock_id = :lid";
$stmtMax = $pdo->prepare($sqlMaxBid);
$stmtMax->execute(['lid' => $livestock_id]); 
$bidRow = $stmtMax->fetch(PDO::FETCH_ASSOC);

    if (!empty($bidRow['highest_bid'])) {
        $currentBid = $bidRow['highest_bid'];
    } else {
        $currentBid = $auctionData['starting_price'] ?? $livestock['price'];
    }
} 

$requiredDepositAmount = 0.00; 

if ($auctionData) {
    $sqlReqDeposit = "SELECT amount FROM auction_deposits WHERE auction_id = :aid LIMIT 1";
    $stmtReq = $pdo->prepare($sqlReqDeposit);
    $stmtReq->execute(['aid' => $auctionData['auction_id']]);
    $reqDepRow = $stmtReq->fetch(PDO::FETCH_ASSOC);

    $requiredDepositAmount = $reqDepRow ? (float)$reqDepRow['amount'] : 0.00;
}

$hasPaidDeposit = false;
if ($is_logged_in && $auctionData) {
    $sqlCheckDeposit = "SELECT 1 FROM auction_deposits_paid 
                        WHERE customer_id = :cid AND auction_id = :aid LIMIT 1";
    $stmtCheck = $pdo->prepare($sqlCheckDeposit);
    $stmtCheck->execute([
        'cid' => $logged_customer_id,
        'aid' => $auctionData['auction_id']
    ]);
    if ($stmtCheck->fetch()) {
        $hasPaidDeposit = true;
    }
}

$isAuctionActive = false;
$auctionStatusMsg = "";
if ($livestock['sale_type'] === 'Auction' && $auctionData) {
    $now = new DateTime("now", new DateTimeZone('Asia/Kuala_Lumpur'));
    $start = new DateTime($auctionData['start_time'], new DateTimeZone('Asia/Kuala_Lumpur'));
    $end = new DateTime($auctionData['end_time'], new DateTimeZone('Asia/Kuala_Lumpur'));
    $dbStatus = strtolower(trim($auctionData['status']));

    if ($dbStatus !== 'active') {
        $auctionStatusMsg = "Auction Status: " . ucfirst($dbStatus);
    } elseif ($now < $start) {
        $auctionStatusMsg = "Starts on: " . $start->format('d M Y, h:i A');
    } elseif ($now > $end) {
        $auctionStatusMsg = "Auction Closed";
    } else {
        $isAuctionActive = true;
        $diff = $now->diff($end);
        $auctionStatusMsg = '<span id="auction-timer-display">Closes in: ' . $diff->format('%d days, %h hours') . '</span>';
    }
}

$sqlDelivery = "SELECT * FROM livestock_delivery_options WHERE livestock_id = :id ORDER BY delivery_fee ASC";
$stmtDelivery = $pdo->prepare($sqlDelivery);
$stmtDelivery->bindParam(':id', $livestock_id, PDO::PARAM_INT);
$stmtDelivery->execute();
$deliveryRecords = $stmtDelivery->fetchAll(PDO::FETCH_ASSOC);

include '../inc/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Details | <?= htmlspecialchars($livestock['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: radial-gradient(circle at top, #fdf6ec, #f4efe6); font-family: 'PT Serif', serif; color: #1a1a1a; min-height: 100vh; }
        
        .hero-section { 
            height: 80px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-bottom: 25px;     
            width: 100%; 
            max-width: 1200px; 
            margin-top: 20px;
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box; 
            background: #E6F0FA; 
            color: #1976d2; 
            border-radius: 12px; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .hero-section h1 {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 1px;
            margin: 0;
            text-transform: uppercase;
            font-family: 'Cinzel', serif;
        }

        .details-wrapper { max-width: 1200px; margin: 0 auto 60px; padding: 0 20px; }

        .breadcrumb-vintage { list-style: none; display: flex; gap: 10px; margin-bottom: 25px; font-size: 14px; }
        .breadcrumb-vintage a { color: #1976d2; text-decoration: none; }
        .breadcrumb-vintage .current { color: #666; }

        .main-card { 
            display: flex; 
            gap: 30px; 
            background: rgba(255, 255, 255, 0.8); 
            backdrop-filter: blur(14px); 
            border-radius: 20px; 
            padding: 25px; 
            align-items: stretch; 
            max-height: 80vh; 
        }
        
        .image-container { flex: 1; min-width: 400px; position: sticky; top: 20px; display: flex;
            flex-direction: column; gap: 15px; height: 100%;}

        .image-container .action-btns {
                display: flex;
                gap: 10px;
                margin-top: 0; 
            }

        .image-container .buy-btn {
                padding: 12px;
                font-size: 13px;
            }
        .slider-viewport { position: relative; border-radius: 20px; overflow: hidden; height: 320px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.1); background: #eee; cursor: zoom-in; flex-shrink: 0;}
        .slider-img { width: 100%; height: 100%; object-fit: cover; }
        .slider-arrow { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.4); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; z-index: 15; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .slider-arrow:hover { background: rgba(0,0,0,0.7); }
        .image-counter { position: absolute; bottom: 15px; right: 20px; background: rgba(0,0,0,0.6); color: white; font-size: 12px; padding: 5px 15px; border-radius: 20px; z-index: 15; font-weight: bold; }
        
        .thumbnail-nav { display: flex; gap: 10px; margin-top: 15px; justify-content: center; overflow-x: auto; padding-bottom: 5px; }
        .thumb-item { width: 65px; height: 65px; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: 0.2s; object-fit: cover; opacity: 0.6; }
        .thumb-item.active { border-color: #90caf9; opacity: 1; transform: scale(1.05); }

        .specs-container { flex: 1.5; display: flex; flex-direction: column; gap: 15px; overflow-y: auto; max-height: 600px; position: relative; padding-bottom: 40px;}
        .specs-container::after {
            content: '';
            position: sticky;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 80px; 
            display: block;
            pointer-events: none; 
            z-index: 999;   
            background: linear-gradient(to bottom, rgba(253, 246, 236, 0), rgba(253, 246, 236, 1));

            margin-top: -80px;
            transition: opacity 0.3s ease;
        }
        .specs-container.hide-gradient::after {
            opacity: 0;
        }
        .specs-container::-webkit-scrollbar {
            width: 8px;
        }

        .specs-container::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 10px;
        }

        .specs-container::-webkit-scrollbar-thumb {
            background: #1976d2; 
            border-radius: 10px;
            border: 2px solid #f0f0f0;
        }
        .status-badge { display: inline-block; background: #e8f5e9; color: #2e7d32; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; width: fit-content; }
        
        .price-tag { font-family: 'Cinzel', serif; font-size: 2.2rem; color: #1976d2; font-weight: bold; margin-bottom: 5px; }
        
        .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 10px; }
        .info-item { background: white; padding: 10px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); }
        .info-item label { display: block; font-size: 10px; text-transform: uppercase; color: #888; font-weight: bold; margin-bottom: 4px; }
        .info-item span { font-weight: bold; font-size: 15px; }
        
        .seller-box { background: rgba(144, 202, 249, 0.1); border-left: 5px solid #90caf9; padding: 20px; border-radius: 0 15px 15px 0; margin: 10px 0; }
        .seller-box h4 { font-family: 'Cinzel', serif; margin-bottom: 10px; color: #333; }

        .section-box { background: white; border-top: 1px solid #1976d2; overflow: hidden; margin-bottom: 0px;  width: 100%; position: relative; height: fit-content;}

        .dropdown-grid {
            display: grid;
            flex-direction: column;
            gap: 15px;                      
            margin-top: 10px;
        }
        .dropdown-header { padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: #fff; transition: background 0.2s; user-select:none; }
        .dropdown-header:hover { background: #fcfcfc; }
        .dropdown-header h3 { font-family: 'Cinzel', serif; font-size: 1rem; margin: 0; display: flex; align-items: center; gap: 10px; color: #333; }
        
        .dropdown-content {
            display: none;
            padding: 10px 20px 20px 20px;
            background: #f9f9f9;
            overflow-x: auto;
        }

        .section-box.active .dropdown-content {
            display: block;
        }
        .plus { transition: transform 0.3s; color: #999; font-size: 12px; }
        .section-box.active .plus { 
            transform: rotate(180deg); 
            color: #1976d2; 
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }

        .close-modal {
            position: absolute;
            top: 30px;
            right: 40px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        .vintage-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 500px;}
        .vintage-table th { text-align: left; padding: 10px; color: #999; border-bottom: 1px solid #eee; font-weight: normal; text-transform: uppercase; font-size: 11px; }
        .vintage-table td { padding: 10px; border-bottom: 1px solid #f9f9f9; }

        .action-btns { display: flex; gap: 12px; margin-top: auto; padding-top: 100px;}
        .buy-btn { width: 100%; margin: 0 auto; padding: 8px 16px; border-radius: 30px; font-family: 'Cinzel', serif; font-weight: bold; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.3s; border: none; cursor: pointer; font-size: 14px; height:50px;}
        .btn-primary { background: #1976d2; color: white; }
        .btn-primary:hover { background: #0d1b2a; }
        .btn-secondary { background: white; color: #1976d2; border: 2px solid #90caf9; }
        .sold-text { width: 100%; text-align: center; color: #f44336; font-family: 'Cinzel', serif; font-weight: bold; padding: 15px; border: 2px dashed #f44336; border-radius: 50px; }
        .cart-sidebar {
            position: fixed;
            top: 0;
            right: -400px; 
            width: 380px;
            height: 100%;
            background: #fdf6ec; 
            box-shadow: -8px 0 25px rgba(0,0,0,0.15);
            z-index: 10001;
            transition: 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            display: flex;
            flex-direction: column;
            border-left: 1px solid #90caf9;
        }

        .cart-sidebar.open {
            right: 0;
        }

        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 27, 42, 0.6); 
            display: none;
            z-index: 10000;
            backdrop-filter: blur(3px); 
        }

        .cart-header {
            padding: 25px 20px;
            color: black;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e0d8cc;
            box-shadow: 0 1px 0 #fff; 
            /*box-shadow: 0 4px 10px rgba(0,0,0,0.1);*/
        }

        .cart-header h2 {
            font-family: 'Cinzel', serif;
            font-size: 1.2rem;
            margin: 0;
            letter-spacing: 1px;
        }

        .close-cart {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            transition: 0.3s;
        }

        .close-cart:hover {
            background: rgba(255,255,255,0.4);
        }

        .cart-items-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .cart-item {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid rgba(144, 202, 249, 0.3);
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: 0.3s;
        }

        .cart-item:hover {
            border-color: #1976d2;
            transform: translateY(-2px);
        }

        .cart-item img {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #eee;
        }

        .cart-item-info h4 {
            font-family: 'Cinzel', serif;
            font-size: 0.95rem;
            margin: 0 0 5px 0;
            color: #0d1b2a;
        }

        .cart-item-info p {
            font-family: 'PT Serif', serif;
            font-size: 0.85rem;
            color: #666;
            margin: 0;
        }

        .cart-footer {
            padding: 25px 20px;
            border-top: 2px double #90caf9;
            background: #fffaf0; 
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            color: #1976d2;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .btn-checkout {
            width: 100%;
            padding: 16px;
            background: #1976d2; 
            color: white;
            border: none;
            border-radius: 50px;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
        }

        .btn-checkout:hover {
            background: #0d1b2a;
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            transform: translateY(-1px);
        }

        .view-full-cart {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #5d6d7e; 
            font-family: 'PT Serif', serif;
            font-size: 0.85rem;
            font-style: italic;
            text-decoration: none;
            transition: all 0.3s ease;
            border-top: 1px solid rgba(144, 202, 249, 0.2); 
            padding-top: 12px;
        }

        .view-full-cart i {
            font-size: 10px;
            margin-left: 5px;
            transition: transform 0.3s ease;
        }

        .view-full-cart:hover {
            color: #1976d2; 
        }

        .view-full-cart:hover i {
            transform: translateX(5px);
        }
        .farmer-avatar-small {
          position: relative;
          width: 32px;
          height: 32px;
          background-color: #90caf9; 
          border-radius: 50%;
          overflow: hidden; 
      }

      .hat-top {
          position: absolute;
          top: 4px;
          left: 50%;
          transform: translateX(-50%);
          width: 14px;
          height: 8px;
          background-color: #eab308;
          border-radius: 4px 4px 0 0;
          z-index: 3;
      }

      .hat-brim {
          position: absolute;
          top: 11px;
          left: 50%;
          transform: translateX(-50%);
          width: 22px;
          height: 3px;
          background-color: #ca8a04;
          border-radius: 2px;
          z-index: 4;
      }

      .head {
          position: absolute;
          top: 11px;
          left: 50%;
          transform: translateX(-50%);
          width: 12px;
          height: 12px;
          background-color: #ffedd5;
          border-radius: 50%;
          z-index: 2;
      }

      .shirt {
          position: absolute;
          bottom: -2px;
          left: 50%;
          transform: translateX(-50%);
          width: 22px;
          height: 11px;
          background-color: #2563eb;
          border-radius: 6px 6px 0 0;
          z-index: 1;
      }

        @media (max-width: 950px) { .main-card { flex-direction: column; } .image-container { min-width: 100%; position: static; } .slider-viewport { height: 350px; } }

        @media (max-width: 600px) {
            .dropdown-grid {
                grid-template-columns: 1fr; 
            }
        }
    </style>
</head>
<body>

    <div class="details-wrapper">
        <div class="hero-section">
        <h1>Livestock Details</h1>
    </div>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb-vintage">
                <li><a href="../Models/customer_dashboard.php"><i class="fas fa-home"></i> Marketplace</a></li>
                <li><i class="fas fa-chevron-right" style="font-size: 10px; color: #ccc;"></i></li>
                <li class="current"><?= htmlspecialchars($livestock['name']); ?></li>
            </ul>
        </nav>

        <div class="main-card">
            <div class="image-container">
               <div class="slider-viewport" id="slider-details">
                    <div style="display: flex; width: 100%; height: 100%; overflow: hidden;">
                        <?php foreach ($images as $index => $img): 
                            $imgTrim = trim($img);
                            $imgSrc = (strpos($imgTrim, '../') === false) ? "../farmer/uploads/" . $imgTrim : $imgTrim;
                            $display = ($index === 0) ? 'block' : 'none';
                            ?>
                            <img src="<?= $imgSrc; ?>" class="slider-img" style="display: <?= $display; ?>; cursor: zoom-in;" 
                            data-index="<?= $index ?>"
                            onclick="openFullImage()">
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($images) > 1): ?>
                        <button type="button" class="slider-arrow" style="left: 10px;" onclick="moveSlider(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button type="button" class="slider-arrow" style="right: 10px;" onclick="moveSlider(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <div class="image-counter">1/<?= count($images) ?></div>
                    <?php endif; ?>
                </div>

                <?php if (count($images) > 1): ?>
                    <div class="thumbnail-nav">
                        <?php foreach ($images as $index => $img): 
                            $imgTrim = trim($img);
                            $imgSrc = (strpos($imgTrim, '../') === false) ? "../farmer/uploads/" . $imgTrim : $imgTrim;
                            ?>
                            <img src="<?= $imgSrc; ?>" class="thumb-item <?= ($index === 0) ? 'active' : '' ?>" 
                            onclick="jumpToImage(<?= $index ?>)" data-index="<?= $index ?>">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="action-btns">
                    <?php 
                    $status = trim($livestock['availability_status']);
                    $isAuctionType = ($livestock['sale_type'] === 'Auction');

                    if ($status === 'Available' || ($isAuctionType && $status !== 'Sold')): 
                        ?>

                        <?php if ($isAuctionType): ?>
                            <?php 
                            $current_time = time();
                            $end_time = strtotime($auctionData['end_time']);
                            $is_active = ($current_time < $end_time);
                            ?>

                            <?php if (!$is_logged_in): ?>
                                <a href="customer_login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="buy-btn btn-primary" style="background: #f57c00;">
                                    <i class="fas fa-gavel"></i> Login to Bid
                                </a>

                            <?php elseif (!$hasPaidDeposit && $is_active): ?>
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px; width: 100%; margin-bottom: 15px;">
                                    <div style="background: #fff5f5; padding: 4px 12px; border-radius: 20px; border: 1px solid #ffcdd2;">
                                        <span style="font-size: 13px; color: #c62828; font-weight: 600; font-family: 'Cinzel', serif;">
                                            DEPOSIT REQUIRED: RM <?= number_format($requiredDepositAmount, 2) ?>
                                        </span>
                                    </div>
                                    <a href="pay_deposit.php?auction_id=<?= $auctionData['auction_id'] ?>" class="buy-btn btn-primary" style="background: #e53935; width: 100%; text-align: center; margin: 0;">
                                        <i class="fas fa-wallet"></i> PAY DEPOSIT TO BID
                                    </a>
                                </div>

                            <?php elseif ($hasPaidDeposit && $is_active): ?>
                                <a href="Join_Auction.php?livestock_id=<?= $livestock['livestock_id'] ?>" class="buy-btn btn-primary" style="background: #4caf50;">
                                    <i class="fas fa-gavel"></i> Enter Auction Room
                                </a>

                            <?php else: ?>
                                <button class="buy-btn btn-secondary" disabled style="background: #9e9e9e; width: 100%;">
                                    <i class="fas fa-times-circle"></i> Auction Ended
                                </button>
                            <?php endif; ?>

                        <?php else: ?>
                            <?php if (!$is_logged_in): ?>
                                <a href="customer_login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="buy-btn btn-primary" style="background: #2e7d32; text-decoration: none; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-sign-in-alt"></i> &nbsp; Login to Buy
                                </a>
                            <?php else: ?>
                                <button onclick="addToCart(<?= $livestock['livestock_id'] ?>)" class="buy-btn btn-primary">
                                    <i class="fas fa-shopping-bag"></i> Buy Now
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php else: ?>
                        <span class="sold-text">Sold Out</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="specs-container" id="details-scroll">
                <span class="status-badge"><?= htmlspecialchars($livestock['availability_status']); ?></span>
                <h2 style="font-family: 'Cinzel', serif; font-size: 2.2rem; margin: 5px 0;">
                    <?= htmlspecialchars($livestock['name']); ?>
                </h2>

                <?php if ($livestock['sale_type'] === 'Auction' && $auctionData): ?>
                    <div class="auction-alert" ...>
                        <i class="fas fa-gavel"></i> <strong>Auction:</strong> <?= $auctionStatusMsg; ?>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px; ...">
                        <i class="fas fa-shield-alt"></i>
                        <span>Required Deposit: <strong>RM <?= number_format($requiredDepositAmount, 2); ?></strong></span>
                        <?php if ($hasPaidDeposit): ?>
                            <span class="badge bg-success" style="color: green; font-size: 12px; margin-left: 10px;">
                                <i class="fas fa-check-circle"></i> Paid
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="price-tag">
                    <?php if ($livestock['sale_type'] === 'Auction'): ?>
                        <div style="margin-bottom: 5px;">
                            <small style="font-size: 11px; color: #888; text-transform: uppercase; display: block;">
                                Starting Price
                            </small>
                            <span style="font-size: 14px; color: #555; text-decoration: none;">
                                RM <?= number_format($startingPrice, 2); ?>
                            </span>
                        </div>

                        <div>
                            <small style="font-size: 12px; color: #e65100; font-weight: bold; display: block; text-transform: uppercase;">
                                Current Bid
                            </small>
                            <span style="font-size: 24px; font-weight: bold; color: #0d1b2a;">
                                RM <?= number_format($currentBid, 2); ?>
                            </span>
                        </div>

                    <?php else: ?>
                        <small style="font-size: 12px; color: #888; display: block; text-transform: uppercase;">
                            Price
                        </small>
                        <span style="font-size: 24px; font-weight: bold;">
                            RM <?= number_format($livestock['price'], 2); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="info-grid">
                    <div class="info-item"><label>Breed</label><span><?= htmlspecialchars($livestock['breed']); ?></span></div>
                    <div class="info-item"><label>Weight</label><span><?= htmlspecialchars($livestock['weight']); ?> KG</span></div>
                    <div class="info-item"><label>Age</label><span><?= htmlspecialchars($livestock['age']); ?> Months</span></div>
                    <div class="info-item"><label>Gender</label><span><?= htmlspecialchars($livestock['gender']); ?></span></div>
                </div>

                <div class="seller-box" style="background: rgba(144, 202, 249, 0.05); border-left: 4px solid #1976d2; padding: 20px; border-radius: 0 15px 15px 0; margin: 15px 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                        <h4 style="font-family: 'Cinzel', serif; margin: 0; display: flex; align-items: center; gap: 12px;">
                            <?php 
                            $profilePic = trim($farmerDetails['profile_image'] ?? $farmerDetails['profile_image'] ?? ''); 
                            if (!empty($profilePic)): 
                                $imgSrc = (strpos($profilePic, '../') === false) ? '../farmer/uploads/' . $profilePic : $profilePic;
                                ?>
                                <img src="<?= $imgSrc; ?>" alt="Farm Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid #90caf9;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <span style="display: none; width: 32px; height: 32px; border-radius: 50%; background: #90caf9; color: #fff; align-items: center; justify-content: center;"><i class="fas fa-tractor" style="font-size: 14px;"></i></span>
                            <?php else: ?>
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: #90caf9; color: #fff; display: flex; align-items: center; justify-content: center;">
                                    <div class="farmer-avatar-small">
                                      <div class="hat-brim"></div>
                                      <div class="hat-top"></div>
                                      <div class="head"></div>
                                      <div class="shirt"></div>
                                  </div>
                                </div>
                            <?php endif; ?>

                            <a href="farmer_store.php?farmer_id=<?= urlencode($livestock['farmer_id']); ?>" style="color: #1976d2; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#0d1b2a'" onmouseout="this.style.color='#1976d2'">
                                <?= htmlspecialchars($farmerDetails['farm_name']); ?>
                            </a>
                        </h4>

                        <a href="farmer_store.php?farmer_id=<?= urlencode($livestock['farmer_id']); ?>" class="btn-visit-store" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: linear-gradient(135deg, #90caf9, #64b5f6); color: #0d1b2a; text-decoration: none; border-radius: 20px; font-weight: bold; font-size: 12px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                            Visit Store <i class="fas fa-external-link-alt" style="font-size: 10px;"></i>
                        </a>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px; color: #444;">
                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                            <i class="fas fa-map-marker-alt" style="width: 15px; color: #1976d2; margin-top: 3px;"></i> 
                            <span><?= htmlspecialchars($farmerDetails['address']); ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-envelope" style="width: 15px; color: #1976d2;"></i> 
                            <span><?= htmlspecialchars($farmerDetails['email']); ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-phone-alt" style="width: 15px; color: #1976d2;"></i> 
                            <span><?= htmlspecialchars($farmerDetails['phone_number']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="dropdown-grid">

                <div class="section-box">
                    <div class="dropdown-header" onclick="toggleDropdown(this)">
                        <h3><i class="fas fa-file-medical" style="color: #4caf50;"></i> Health Records</h3>
                        <i class="fas fa-plus plus"></i>
                    </div>
                    <div class="dropdown-content">
                        <?php if (count($healthRecords) > 0): ?>
                            <table class="vintage-table">
                                <thead>
                                    <tr>
                                        <th>Vaccination</th>
                                        <th>Medicine</th>
                                        <th>Vitamin</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($healthRecords as $h): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($h['vaccination']); ?></td>
                                            <td><?= htmlspecialchars($h['medicine']); ?></td>
                                            <td><?= htmlspecialchars($h['vitamin']); ?></td>
                                            <td><?= date("d M Y", strtotime($h['healthdate'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="color: #999; font-size: 13px;">No health records found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-box">
                    <div class="dropdown-header" onclick="toggleDropdown(this)">
                        <h3><i class="fas fa-plus-circle" style="color: #ff9800;"></i>Services</h3>
                        <i class="fas fa-plus plus"></i>
                    </div>
                    <div class="dropdown-content">
                        <?php if (count($harvestRecords) > 0): ?>
                            <table class="vintage-table">
                                <thead><tr><th>Service</th><th>Fee</th></tr></thead>
                                <tbody>
                                    <?php foreach ($harvestRecords as $hr): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($hr['servicetype']); ?></td>
                                            <td style="color: #1976d2; font-weight: bold;">RM <?= number_format($hr['servicefee'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="color: #999; font-size: 13px;">No services available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-box">
                    <div class="dropdown-header" onclick="toggleDropdown(this)">
                        <h3><i class="fas fa-truck" style="color: #607d8b;"></i> Transportation & Delivery</h3>
                        <i class="fas fa-plus plus"></i>
                    </div>
                    <div class="dropdown-content">
                        <?php if (!empty($deliveryRecords)): ?>
                            <table class="vintage-table">
                                <thead>
                                    <tr>
                                        <th>Method / Vehicle</th>
                                        <th>Max Capacity</th>
                                        <th>Fee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deliveryRecords as $dr): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: #333;"><?= htmlspecialchars($dr['method_name']); ?></strong>
                                            </td>
                                            <td style="color: #555; font-size: 0.9rem;">
                                                <?php if (!empty($dr['max_capacity'])): ?>
                                                    <i class="fas fa-weight-hanging" style="font-size: 10px; color: #999;"></i> 
                                                    <?= htmlspecialchars($dr['max_capacity']); ?> kg
                                                <?php else: ?>
                                                    <span style="color: #ccc;">Standard</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color: #1976d2; font-weight: bold;">
                                                <?= $dr['delivery_fee'] == 0 ? 'FREE' : 'RM ' . number_format($dr['delivery_fee'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p style="font-size: 11px; color: #888; margin-top: 10px; font-style: italic;">
                                * Ensure your total livestock weight does not exceed the vehicle capacity. Coordinate final timing with the farmer.
                            </p>
                        <?php else: ?>
                            <p style="color: #999; font-size: 13px;">No specific delivery options listed. Please contact the farmer for transport arrangements.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-box">
                    <div class="dropdown-header" onclick="toggleDropdown(this)">
                        <h3><i class="fas fa-star" style="color: #ffca08;"></i> Buyer Feedback</h3>
                        <i class="fas fa-plus plus"></i>
                    </div>
                    <div class="dropdown-content">
                        <?php if (count($feedbackRecords) > 0): ?>
                            <?php foreach ($feedbackRecords as $fb): ?>
                                <div style="padding: 15px 0; border-bottom: 1px solid #f5f5f5;">

                                    <?php if ($fb['status'] === 'Pending' && $fb['customer_id'] == $logged_customer_id): ?>
                                        <span style="font-size: 10px; background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; margin-bottom: 8px; display: inline-block; border: 1px solid #ffeeba;">
                                            <i class="fas fa-eye-slash"></i> Only you can see this (Pending Approval)
                                        </span>
                                    <?php endif; ?>

                                    <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                        <strong><?= htmlspecialchars($fb['customer_name']); ?></strong>
                                        <span style="color: #ffca08;"><?= str_repeat('★', (int)$fb['rating']); ?></span>
                                    </div>

                                    <p style="font-size: 13px; color: #555; margin-top: 5px; font-style: italic;">
                                        "<?= htmlspecialchars($fb['feedback_message']); ?>"
                                    </p>

                                    <?php if (!empty($fb['farmer_reply'])): ?>
                                        <div style="margin-top: 12px; margin-left: 25px; padding: 12px 18px; background: #f0f7ff; border-left: 4px solid #1976d2; border-radius: 8px;">
                                            <div style="font-size: 11px; font-weight: bold; color: #1976d2; text-transform: uppercase; margin-bottom: 5px;">
                                                <i class="fas fa-reply"></i> Response from <?= htmlspecialchars($fb['farm_name'] ?? 'The Farmer'); ?>
                                            </div>
                                            <p style="font-size: 12.5px; color: #2c3e50; margin: 0;">
                                                <?= htmlspecialchars($fb['farmer_reply']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <?php else: ?> <div style="text-align: center; padding: 30px; color: #999;">
                                <i class="fas fa-comment-dots" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p style="font-style: italic; font-size: 14px;">Be the first to review this livestock after purchase!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <div id="imageModal" class="modal" onclick="this.style.display='none'">
    <span class="close-modal">&times;</span>
    <img class="modal-content" id="fullImage">
</div>


    <div id="cartOverlay" class="cart-overlay" onclick="toggleCart()"></div>

    <div id="cartSidebar" class="cart-sidebar">
        <div class="cart-header">
            <h3><i class="fas fa-shopping-cart"></i> Your Cart</h3>
            <button class="close-cart" onclick="toggleCart()">&times;</button>
        </div>

        <div id="cartItemsContainer" class="cart-items-body">
            <div class="empty-cart-msg">Your basket is empty.</div>
        </div>

        <div class="cart-footer">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cartTotalAmount">RM 0.00</span>
            </div>

            <?php 
            $cart_items = !empty($_SESSION['cart']) ? implode(',', $_SESSION['cart']) : '';
            ?>

            <a id="checkoutLink" href="<?= $projectFolder ?>/payment/unified_checkout.php?items=<?= $cart_items ?>" style="text-decoration: none;">
                <button id="checkoutBtn" class="btn-checkout" <?= empty($cart_items) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                    Checkout
                </button>
            </a>

            <a href="<?= $projectFolder ?>/Models/cart.php" class="view-full-cart">
                View Cart <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
    <?php include '../inc/footer.php'; ?>

    <script>
        function moveSlider(direction) {
            const slider = document.getElementById('slider-details');
            const imgs = slider.getElementsByClassName('slider-img');
            let activeIndex = 0;
            for (let i = 0; i < imgs.length; i++) {
                if (imgs[i].style.display === 'block') { activeIndex = i; break; }
            }
            let nextIndex = (activeIndex + direction + imgs.length) % imgs.length;
            jumpToImage(nextIndex);
        }

        function jumpToImage(index) {
            const slider = document.getElementById('slider-details');
            const imgs = slider.getElementsByClassName('slider-img');
            const counter = slider.querySelector('.image-counter');
            const thumbs = document.querySelectorAll('.thumb-item');
            for (let i = 0; i < imgs.length; i++) {
                imgs[i].style.display = 'none';
                if(thumbs[i]) thumbs[i].classList.remove('active');
            }
            imgs[index].style.display = 'block';
            if(thumbs[index]) thumbs[index].classList.add('active');
            if(counter) counter.innerText = (index + 1) + '/' + imgs.length;
        }

        function toggleDropdown(headerElement) {
            const sectionBox = headerElement.parentElement;
            sectionBox.classList.toggle("active");
            console.log("clicked")
        }

        function toggleCart() {
            const sidebar = document.getElementById('cartSidebar');
            const overlay = document.getElementById('cartOverlay');
            
            sidebar.classList.toggle('open');
            overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
            
            if(sidebar.classList.contains('open')) {
                updateCartDisplay();
            }
        }

        function addToCart(livestockId) {
            let data = new FormData();
            data.append('livestock_id', livestockId);
            data.append('action', 'add');

            fetch('cart_handler.php', {
                method: 'POST',
                body: data
            })
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    const sidebar = document.getElementById('cartSidebar');
                    const overlay = document.getElementById('cartOverlay');

                    if (!sidebar.classList.contains('open')) {
                        sidebar.classList.add('open');
                        overlay.style.display = 'block';
                    }

                    updateCartDisplay(); 

                } else {
                    alert(data.message || "Error adding to cart");
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert("Could not connect to the server. Please try again.");
            });
        }

        function updateCartDisplay() {
    fetch('cart_handler.php?action=get') // Use relative path
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById('cartItemsContainer');
        const totalEl = document.getElementById('cartTotalAmount');
        const checkoutLink = document.getElementById('checkoutLink'); 
        const checkoutBtn = document.getElementById('checkoutBtn');   

        if (!data.items || data.items.length === 0) {
            container.innerHTML = '<div class="empty-cart-msg">Your basket is empty.</div>';
            totalEl.innerText = "RM 0.00";
            if(checkoutBtn) {
                checkoutBtn.disabled = true;
                checkoutBtn.style.opacity = "0.5";
            }
            return;
        }

        container.innerHTML = '';
        let itemIds = []; 

        data.items.forEach(item => {
            itemIds.push(item.id); 
            const price = parseFloat(item.price) || 0;
            container.innerHTML += `
                <div class="cart-item" style="display:flex; padding:10px; border-bottom:1px solid #ddd;">
                    <img src="../farmer/uploads/${item.image}" style="width:50px; height:50px; object-fit:cover; margin-right:10px;">
                    <div class="info">
                        <h4 style="margin:0; font-size:14px; font-family: 'Cinzel', serif;">${item.name}</h4>
                        <p style="margin:5px 0; color:#1976d2;">RM ${price.toFixed(2)}</p>
                        <button onclick="removeFromCart(${item.id})" style="color:red; background:none; border:none; cursor:pointer; padding:0; font-size:12px;">Remove</button>
                    </div>
                </div>`;
        });

        const grandTotal = parseFloat(data.total) || 0;
        totalEl.innerText = `RM ${grandTotal.toFixed(2)}`;

        if(checkoutLink) {
            checkoutLink.href = `../payment/unified_checkout.php?items=${itemIds.join(',')}`;
        }
        
        if(checkoutBtn) {
            checkoutBtn.disabled = false;
            checkoutBtn.style.opacity = "1";
            checkoutBtn.style.cursor = "pointer";
        }
    })
    .catch(err => console.error("Fetch error:", err));
}

        function openFullImage() {
            const images = document.querySelectorAll('.slider-img');
            let currentSrc = "";

            images.forEach(img => {
                if (img.style.display !== 'none') {
                    currentSrc = img.src;
                }
            });

            if (currentSrc) {
                document.getElementById('fullImage').src = currentSrc;
                document.getElementById('imageModal').style.display = 'flex';
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === "Escape") {
                document.getElementById('imageModal').style.display = 'none';
            }
        });

        const scrollDiv = document.getElementById('details-scroll');

        scrollDiv.addEventListener('scroll', () => {
            const isAtBottom = scrollDiv.scrollHeight - scrollDiv.scrollTop <= scrollDiv.clientHeight + 1;

            if (isAtBottom) {
                scrollDiv.classList.add('hide-gradient');
            } else {
                scrollDiv.classList.remove('hide-gradient');
            }
        });

        function removeFromCart(livestockId) {
            let data = new FormData();
            data.append('livestock_id', livestockId);
            data.append('action', 'remove'); 

            fetch('cart_handler.php', {
                method: 'POST',
                body: data
            })
            .then(res => res.json())
            .then(response => {
                if(response.success) {
                    updateCartDisplay();
                }
            });
        }
        <?php if ($isAuctionActive && isset($endTimeTimestamp)): ?>
            const endTime = <?= $endTimeTimestamp ?>;
            const timerDisplay = document.getElementById('auction-timer-display');

            const countdown = setInterval(function() {
                const now = new Date().getTime();
                const distance = endTime - now;

                if (distance < 0) {
                    clearInterval(countdown);
                    timerDisplay.innerHTML = "Auction Closed";
                    
                    location.reload(); 
                    return;
                }

                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                timerDisplay.innerHTML = `Closes in: ${days}d ${hours}h ${minutes}m ${seconds}s`;
            }, 1000);
        <?php endif; ?>

        <?php if ($livestock['sale_type'] === 'Auction' && isset($auctionData['auction_id'])): ?>
            setInterval(function(){
                fetch('get_current_bid.php?auction_id=<?= (int)$auctionData['auction_id'] ?>')
                .then(res => res.json())
                .then(data => {
                    if(data.current_bid) {
                        document.getElementById('current-bid-display').innerText = 'RM ' + data.current_bid;
                    }
                })
                .catch(err => console.error("Bid update error:", err));
            }, 5000); 
        <?php endif; ?>
        
    </script>

</body>
</html>