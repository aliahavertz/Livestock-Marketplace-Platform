<?php
session_start();
include '../db_connect.php';
include '../inc/header.php';

if (!isset($_GET['farmer_id']) || empty(trim($_GET['farmer_id']))) {
    echo "Farmer ID is missing.";
    exit();
}

$farmer_id = intval($_GET['farmer_id']);

try {
    $farmerSql = "SELECT name, email, farm_name, phone_number, address, profile_image, date_registered, verify_status FROM farmer WHERE farmer_id = :farmer_id LIMIT 1";
    $farmerStmt = $pdo->prepare($farmerSql);
    $farmerStmt->execute([':farmer_id' => $farmer_id]);
    $farmer = $farmerStmt->fetch(PDO::FETCH_ASSOC);

    $date_registered = isset($farmer['date_registered']) ? date('d M Y', strtotime($farmer['date_registered'])) : 'N/A';

    if (!$farmer) {
        echo "Farmer record not found.";
        exit();
    }

    $ratingSql = "SELECT AVG(rating) as avg_rating FROM feedback WHERE farmer_id = :farmer_id";
    $ratingStmt = $pdo->prepare($ratingSql);
    $ratingStmt->execute([':farmer_id' => $farmer_id]);
    $ratingRow = $ratingStmt->fetch(PDO::FETCH_ASSOC);
    $farmerRating = !empty($ratingRow['avg_rating']) ? floatval($ratingRow['avg_rating']) : 0.0;

    $totalProductsSql = "SELECT COUNT(*) as total_count FROM livestock WHERE farmer_id = :farmer_id AND (availability_status ILIKE 'Available' OR availability_status ILIKE 'In Auction')";
    $totalProductsStmt = $pdo->prepare($totalProductsSql);
    $totalProductsStmt->execute([':farmer_id' => $farmer_id]);
    $totalProductsRow = $totalProductsStmt->fetch(PDO::FETCH_ASSOC);
    $totalProducts = (int)($totalProductsRow['total_count'] ?? 0);

    $totalBuyersSql = "SELECT COUNT(DISTINCT o.order_id) as total_buyers 
                       FROM orders o
                       JOIN livestock l ON o.livestock_id = l.livestock_id
                       WHERE l.farmer_id = :farmer_id 
                       AND o.status ILIKE 'Delivered'";
    
    $totalBuyersStmt = $pdo->prepare($totalBuyersSql);
    $totalBuyersStmt->execute([':farmer_id' => $farmer_id]);
    $totalBuyersRow = $totalBuyersStmt->fetch(PDO::FETCH_ASSOC);
    $totalBuyers = (int)($totalBuyersRow['total_buyers'] ?? 0);

    $livestockSql = "SELECT livestock.*, farmer.farm_name,
    auction.starting_price AS auction_start,
    (SELECT MAX(current_bid) FROM bidding WHERE bidding.livestock_id = livestock.livestock_id) AS current_high_bid,
    (SELECT COALESCE(COUNT(bid_id), 0) FROM bidding WHERE bidding.livestock_id = livestock.livestock_id) AS total_bids,
    (SELECT COALESCE(COUNT(DISTINCT customer_id), 0) FROM bidding WHERE bidding.livestock_id = livestock.livestock_id) AS total_bidders
    FROM livestock 
    JOIN farmer ON livestock.farmer_id = farmer.farmer_id
    LEFT JOIN auction ON livestock.livestock_id = auction.livestock_id AND LOWER(auction.status) = 'active'
    WHERE livestock.farmer_id = :farmer_id 
    AND (livestock.availability_status ILIKE 'Available' OR livestock.availability_status ILIKE 'In Auction') 
    ORDER BY livestock.livestock_id DESC";
    $livestockStmt = $pdo->prepare($livestockSql);
    $livestockStmt->execute([':farmer_id' => $farmer_id]);
    
    $available = $livestockStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
    exit();
}

function renderCardImage($row, $status, $isSold, $isAuction) {
    $cleanStatus = strtolower(trim((string)$status));
    $statusDisplay = ucfirst($cleanStatus); 
    
    $statusClass = (strpos(strtolower($status), 'sold') !== false) ? 'sold' : 'available'; 
    if ($isSold || strpos($cleanStatus, 'sold') !== false) {
        $statusClass = 'sold';
    } elseif ($isAuction || strpos($cleanStatus, 'auction') !== false) {
        $statusClass = 'auction';
    }

    $imageField = trim($row['image'] ?? '');
    $images = !empty($imageField) ? explode(',', $imageField) : [];
    
    echo '<div class="card-img-wrapper">';
    echo '<div class="status-tag ' . $statusClass . '">' . htmlspecialchars($statusDisplay) . '</div>';
    
    if (empty($images)) {
        echo '<div style="background: #eee; min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; color:#ccc;">
                <i class="fas fa-cow" style="font-size:3rem;"></i><br>
                <span style="font-size:10px;">No Image</span>
              </div>';
    } else {
        echo '<div class="swiper mySwiper">';
        echo '  <div class="swiper-wrapper">';
        foreach ($images as $img) {
            $imgTrim = trim($img);
            $imgSrc = (strpos($imgTrim, '../') === false) ? '../farmer/uploads/' . $imgTrim : $imgTrim;
            echo '<div class="swiper-slide">';
            echo '  <img src="' . $imgSrc . '" style="width:100%; height:160px; object-fit:cover;" onerror="this.src=\'../assets/no-image.png\';">';
            echo '</div>';
        }
        echo '  </div>';
        if (count($images) > 1) {
            echo '<div class="swiper-button-next"></div>';
            echo '<div class="swiper-button-prev"></div>';
            echo '<div class="swiper-pagination"></div>'; 
        }
        echo '</div>'; 
    }
    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($farmer['name']) ?> - Shop Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=PT+Serif:wght@400;700&family=Cinzel:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6);
            font-family: 'PT Serif', serif;
            color: #3b332a;
            padding: 40px 20px;
        }

        .breadcrumb-vintage { list-style: none; display: flex; gap: 10px; margin-bottom: 25px; font-size: 14px; margin-top:80px; }
        .breadcrumb-vintage a { color: #1976d2; text-decoration: none; }
        .breadcrumb-vintage .current { color: #666; }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .farmer-card {
            background: #fdf6ec;
            border: 2px solid #d7ccc8;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(69, 60, 52, 0.1);
            display: flex;
            align-items: center;
            gap: 30px;
            margin-top: 20px;
            margin-bottom: 40px;
            position: relative;
        }

        .farmer-avatar {
            width: 100px;
            height: 100px;
            background-color: #90caf9;
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 3rem;
            border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .farmer-info h1 {
            font-family: 'Cinzel', serif;
            font-size: 2.2rem;
            color: #453c34;
            text-transform: uppercase;
            margin-bottom: 5px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .verified-badge {
            background-color: #1e88e5;
            color: #ffffff;
            font-size: 0.85rem;
            font-family: 'PT Serif', serif;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: capitalize;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
        }

        .farmer-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 10px;
            font-size: 0.95rem;
            color: #5c4d3b;
        }

        .farmer-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .shop-stats {
            display: flex;
            gap: 25px;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px dashed #d7ccc8;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #453c34;
        }
        .stat-number {
            font-weight: bold;
            color: #1976d2;
            font-size: 1.1rem;
        }

        .section-title {
            font-family: 'Cinzel', serif;
            font-size: 28px;
            margin: 40px 0 20px;
            border-left: 5px solid #90caf9;
            padding-left: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #453c34;
        }

        .catalogue { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
            gap: 20px; 
            width: 100%;        
        }
        .card { background: rgba(255,255,255,0.7); backdrop-filter: blur(14px); border-radius: 18px; padding: 12px; border: 1px solid rgba(144, 202, 249, 0.4); transition: 0.35s; position: relative; }
        .card:hover { transform: translateY(-8px); box-shadow: 0 10px 30px rgba(144,202,249,0.4); }
        .card-img-wrapper { width: 100%; height: 160px; border-radius: 18px; overflow: hidden; margin-bottom: 15px; position: relative; }
        
        .status-tag { position: absolute; top: 10px; left: 10px; padding: 6px 14px; font-size: 11px; font-weight: bold; border-radius: 18px; color: #fff; z-index: 10; text-transform: uppercase; }
        .status-tag.available { background: #4caf50; }
        .status-tag.sold { background: #f44336; }
        .status-tag.auction { background: #ff9800; } 
        
        .price { color: #1976d2; font-size: 1.1rem; font-weight: bold; margin-bottom: 15px; }
        .btn-view { display: block; text-align: center; padding: 8px; background: linear-gradient(135deg, #90caf9, #64b5f6); color: #0d1b2a; text-decoration: none; border-radius: 25px; font-weight: bold; }

        .swiper {
            width: 100%;
            height: 160px !important;
            position: relative;
        }

        .swiper-button-next, 
        .swiper-button-prev {
            opacity: 1 !important; 
            visibility: visible !important;
            display: flex !important; 
            color: #fff !important;
            background: rgba(25, 118, 210, 0.9) !important; 
            width: 32px !important;
            height: 32px !important;
            border-radius: 50%;
            z-index: 100;
            top: 50% !important;
            margin-top: 0 !important; 
            transform: translateY(-50%) scale(0.85) !important; 
            transition: all 0.3s ease;
        }

        .swiper-button-next:after, 
        .swiper-button-prev:after {
            font-size: 14px !important;
            font-weight: bold;
        }

        .swiper-button-prev { left: 8px !important; }
        .swiper-button-next { right: 8px !important; }
    </style>
</head>
<body>

<div class="container">
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb-vintage">
            <li><a href="../Models/customer_dashboard.php"><i class="fas fa-home"></i> Marketplace</a></li>
            <li><i class="fas fa-chevron-right" style="font-size: 10px; color: #ccc;"></i></li>
            <li class="current"><?= htmlspecialchars($farmer['name']) ?> Store</li>
        </ul>
    </nav>
    <div class="farmer-card">
        <div class="farmer-avatar" style="overflow: hidden; display: flex; justify-content: center; align-items: center; background-color: #90caf9; position: relative;">
            <?php 
            $farmerImg = trim($farmer['profile_image'] ?? $farmer['image'] ?? ''); 
            
            if (!empty($farmerImg)): 
                $imgSrc = (strpos($farmerImg, '../') === false) ? '../farmer/uploads/' . $farmerImg : $farmerImg;
            ?>
                <img src="<?= $imgSrc; ?>" alt="Farm Profile" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 3rem; color: white;">
                    <i class="fas fa-tractor"></i>
                </div>
            <?php else: ?>
                <i class="fas fa-tractor" style="color: white; font-size: 3rem;"></i>
            <?php endif; ?>
        </div>
        <div class="farmer-info">
            <h1>
                <?= htmlspecialchars($farmer['name']) ?>
                <?php 
                $statusCheck = isset($farmer['verify_status']) ? strtolower(trim($farmer['verify_status'])) : '';

                if ($statusCheck === 'verified'): 
                    ?>
                    <span class="verified-badge" title="Verified Farmer">
                        <i class="fas fa-check-circle"></i> Verified Farm
                    </span>
                <?php endif; ?>
            </h1>
            
            <div class="farmer-meta">
                <span>
                    <i class="fas fa-star" style="color: #ffb300;"></i> 
                    <strong><?= number_format($farmerRating, 1); ?></strong> / 5.0
                </span>
                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($farmer['address'] ?? 'Not Specified') ?></span>
                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($farmer['email']) ?></span>
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($farmer['phone_number'] ?? 'N/A') ?></span>
            </div>

            <div class="shop-stats">
                <div class="stat-item">
                    <i class="fas fa-boxes" style="color: #1976d2;"></i>
                    <span>Total Products Available: <span class="stat-number"><?= $totalProducts; ?></span></span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-users" style="color: #2e7d32;"></i>
                    <span>Total Sold: <span class="stat-number"><?= $totalBuyers; ?></span></span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-users" style="color: #2e7d32;"></i>
                    <span>Joined Date: <span class="stat-number"><?= $date_registered; ?></span></span>
                </div>
            </div>
        </div>
    </div>

    <h2 class="section-title">Available Livestock</h2>

     <div class="catalogue">
            <?php if (!empty($available)): ?>
                <?php foreach ($available as $lv): ?>
                    <div class="card">
                        <?php 
                        $isAuction = (strtolower(trim($lv['sale_type'] ?? '')) === 'auction');
                        renderCardImage($lv, $lv['availability_status'], false, $isAuction); 
                        ?>
                        
                        <div class="name" style="font-family:'Cinzel'; font-weight:bold; display: flex; align-items: center; gap: 8px; justify-content: space-between; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span><?= htmlspecialchars($lv['name']); ?></span>
                                <?php if (!empty($lv['category'])): ?>
                                    <span style="background: #e3f2fd; color: #0d47a1; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-family: 'Cinzel'; font-weight: bold; border: 1px solid #90caf9; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <?= htmlspecialchars($lv['category']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($isAuction): ?>
                                <span style="background: #fff3e0; color: #ff9800; border: 1px solid #ffe0b2; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-family: 'Cinzel'; font-weight: 900; letter-spacing: 0.5px;">AUCTION</span>
                            <?php endif; ?>
                        </div>

                        <div style="font-size:0.9rem; color:#666; margin: 10px 0;">
                            <i class="fas fa-dna" style="color:#90caf9;"></i> <?= htmlspecialchars($lv['breed']) ?><br>
                            <i class="fas fa-tractor" style="color:#90caf9;"></i> <?= htmlspecialchars($lv['farm_name']) ?><br>
                            <i class="fas fa-calendar-alt" style="color:#90caf9;"></i> Listed on: <?= date('d M Y', strtotime($lv['date_listed'])); ?>

                            <?php if ($isAuction): ?>
                                <div style="margin-top: 5px; font-size: 0.85rem; color: #e65100; font-weight: 500;">
                                    <i class="fas fa-gavel"></i> Total Bids: <strong><?= (int)($lv['total_bids'] ?? 0); ?></strong> | 
                                    <i class="fas fa-users"></i> Bidders: <strong><?= (int)($lv['total_bidders'] ?? 0); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        <hr style="margin:10px 0; border:0; border-top:1px solid #eee;">
                        <div class="price">
                            <?php if ($isAuction): ?>
                                <span style="font-size: 0.8rem; color: #666;">
                                    <?= !empty($lv['current_high_bid']) ? 'Current Bid:' : 'Starting Price:'; ?>
                                </span><br>
                                RM <?= number_format($lv['current_high_bid'] ?? $lv['auction_start'] ?? 0, 2); ?>
                            <?php else: ?>
                                <span style="font-size: 0.8rem; color: #666;">Price:</span><br>
                                RM <?= number_format($lv['price'] ?? 0, 2); ?>
                            <?php endif; ?>
                        </div>
                        <a class="btn-view" href="../Models/livestock_view.php?livestock_id=<?= $lv['livestock_id']; ?>">View Details</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; padding: 20px; color:#666;">This farmer has no active livestock listings available at the moment.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Initialize Swiper engine cleanly now that links are added
        const swiper = new Swiper(".mySwiper", {
            loop: true,
            pagination: { el: ".swiper-pagination", clickable: true },
            navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev" },
            effect: "fade",
            fadeEffect: { crossFade: true }
        });
    });
</script>

</body>
</html>