<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectFolder = "/LivestockMarketplace";
$db_path = $_SERVER['DOCUMENT_ROOT'] . $projectFolder . "/db_connect.php";
if (file_exists($db_path)) { 
    include_once($db_path); 
}

$isGuest = !isset($_SESSION['customer_id']);
$customer_id = $_SESSION['customer_id'] ?? null;
$Name = 'Guest';
$orderCount = 0;
$unpaidWins = [];
$isFiltering = false;

if (!$isGuest) {
    $stmtCust = $pdo->prepare("SELECT name FROM customer WHERE customer_id = ? LIMIT 1");
    $stmtCust->execute([$customer_id]);
    $userRow = $stmtCust->fetch(PDO::FETCH_ASSOC);
    $Name = $userRow['name'] ?? 'User';

    $stmtOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
    $stmtOrders->execute([$customer_id]);
    $orderCount = $stmtOrders->fetchColumn();

$stmtCheck = $pdo->prepare("SELECT auction_id, livestock_id FROM auction WHERE end_time <= NOW() AND status = 'active'");
$stmtCheck->execute();
$expiredAuctions = $stmtCheck->fetchAll();

foreach ($expiredAuctions as $auc) {
    $stmtWinner = $pdo->prepare("
        SELECT customer_id, current_bid 
        FROM bidding 
        WHERE livestock_id = ? 
        ORDER BY current_bid DESC LIMIT 1
    ");
    $stmtWinner->execute([$auc['livestock_id']]);
    $winner = $stmtWinner->fetch();

    if ($winner) {
        $updateBid = $pdo->prepare("UPDATE bidding SET winner_id = ? WHERE livestock_id = ? AND current_bid = ?");
        $updateBid->execute([$winner['customer_id'], $auc['livestock_id'], $winner['current_bid']]);

        $pdo->prepare("UPDATE auction SET status = 'closed' WHERE auction_id = ?")->execute([$auc['auction_id']]);
        $pdo->prepare("UPDATE livestock SET availability_status = 'Sold' WHERE livestock_id = ?")->execute([$auc['livestock_id']]);
    } else {
        $pdo->prepare("UPDATE auction SET status = 'expired' WHERE auction_id = ?")->execute([$auc['auction_id']]);
    }
}

$stmtUnpaid = $pdo->prepare("
    SELECT 
        a.auction_id, 
        l.name, 
        a.current_bid, 
        (SELECT COALESCE(SUM(amount), 0) FROM auction_deposits_paid WHERE auction_id = a.auction_id) as deposit
    FROM auction a
    JOIN livestock l ON a.livestock_id = l.livestock_id
    WHERE a.last_bidder_id = :cust_id 
    AND a.status = 'closed'
    AND NOT EXISTS (
        SELECT 1 FROM orders 
        WHERE orders.livestock_id = l.livestock_id 
        AND orders.customer_id = :cust_id
        AND orders.order_status ILIKE 'paid' 
    )
");
$stmtUnpaid->execute([':cust_id' => $customer_id]);
$unpaidWins = $stmtUnpaid->fetchAll(PDO::FETCH_ASSOC);
}

$isNewArrivalFilter = (isset($_GET['filter']) && $_GET['filter'] === 'new_arrivals');

$where = [];
$params = [];

if ($isNewArrivalFilter) {
    $where[] = "livestock.date_listed >= NOW() - INTERVAL '7 days'";
}

if (!empty($_GET['category']) || !empty($_GET['breed']) || !empty($_GET['sale_type']) || !empty($_GET['farm_name']) || !empty($_GET['filter']) || !empty($_GET['price_min']) || !empty($_GET['price_max']) || !empty($_GET['search'])) {
    $isFiltering = true;
}

if (!empty($_GET['search'])) {
    $searchTerm = "%" . trim($_GET['search']) . "%";
    $where[] = "(livestock.name ILIKE :search OR livestock.breed ILIKE :search OR livestock.category ILIKE :search)";
    $params[':search'] = $searchTerm;
}

if (!empty($_GET['category'])) {
    $where[] = "livestock.category ILIKE :cat";
    $params[':cat'] = "%" . trim($_GET['category']) . "%";
}

if (!empty($_GET['breed'])) {
    $where[] = "livestock.breed ILIKE :breed";
    $params[':breed'] = "%" . trim($_GET['breed']) . "%";
}

if (!empty($_GET['farm_name'])) {
    $where[] = "farmer.farm_name ILIKE :farm_name"; 
    $params[':farm_name'] = "%" . trim($_GET['farm_name']) . "%";
}

if (!empty($_GET['sale_type'])) {
    $where[] = "livestock.sale_type = :stype";
    $params[':stype'] = $_GET['sale_type'];
}

if (!empty($_GET['price_min'])) {
    $where[] = "COALESCE(
        (SELECT MAX(current_bid) FROM bidding WHERE livestock_id = livestock.livestock_id), 
        auction.starting_price, 
        livestock.price
    ) >= :pmin";
    $params[':pmin'] = $_GET['price_min'];
}

if (!empty($_GET['price_max'])) {
    $where[] = "COALESCE(
        (SELECT MAX(current_bid) FROM bidding WHERE livestock_id = livestock.livestock_id), 
        auction.starting_price, 
        livestock.price
    ) <= :pmax";
    $params[':pmax'] = $_GET['price_max'];
}

$where[] = "TRIM(LOWER(livestock.availability_status)) IN ('available', 'in auction')";

$sql = "SELECT 
            livestock.*, 
            farmer.farm_name,
            auction.starting_price AS auction_start,
            (SELECT MAX(current_bid) 
             FROM bidding 
             WHERE bidding.livestock_id = livestock.livestock_id) AS current_high_bid,
            (SELECT COALESCE(COUNT(bid_id), 0) 
             FROM bidding 
             WHERE bidding.livestock_id = livestock.livestock_id) AS total_bids,
            (SELECT COALESCE(COUNT(DISTINCT customer_id), 0) 
             FROM bidding 
             WHERE bidding.livestock_id = livestock.livestock_id) AS total_bidders
        FROM livestock 
        JOIN farmer ON livestock.farmer_id = farmer.farmer_id
        LEFT JOIN auction ON livestock.livestock_id = auction.livestock_id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY livestock.livestock_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filteredLivestock = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtFarmers = $pdo->query("SELECT DISTINCT farm_name FROM farmer WHERE farm_name IS NOT NULL AND farm_name != '' ORDER BY farm_name ASC");
$allFarmers = $stmtFarmers->fetchAll(PDO::FETCH_ASSOC);

$stmtBreeds = $pdo->query("SELECT DISTINCT category, LOWER(TRIM(breed)) AS breed 
                           FROM livestock 
                           WHERE breed IS NOT NULL AND breed != '' 
                           ORDER BY category ASC, breed ASC");
$breedRows = $stmtBreeds->fetchAll(PDO::FETCH_ASSOC);

$categoriesWithBreeds = [];
foreach ($breedRows as $row) {
    $cat = $row['category'];
    $displayName = ucwords($row['breed']); 
    
    if (!isset($categoriesWithBreeds[$cat])) {
        $categoriesWithBreeds[$cat] = [];
    }
    
    if (!in_array($displayName, $categoriesWithBreeds[$cat])) {
        $categoriesWithBreeds[$cat][] = $displayName;
    }
}

$directPurchaseItems = [];
$auctionItems = [];
$available = []; 

foreach ($filteredLivestock as $item) {
    $status = strtolower(trim((string)($item['availability_status'] ?? '')));
    $mode = strtolower(trim((string)($item['sale_type'] ?? $item['sale_type'] ?? '')));

    if ($status === 'available' || $status === 'in auction') {
        $available[] = $item;

        if ($mode === 'auction') {
            $auctionItems[] = $item;
        } else {
            $directPurchaseItems[] = $item;
        }
    }
}

if (!$isFiltering) {
    $stmtNew = $pdo->query("SELECT livestock.*, farmer.farm_name, 
                            auction.starting_price AS auction_start,
                            (SELECT MAX(current_bid) 
                             FROM bidding 
                             WHERE bidding.livestock_id = livestock.livestock_id) AS current_high_bid,
                            (SELECT COALESCE(COUNT(bid_id), 0) 
                             FROM bidding 
                             WHERE bidding.livestock_id = livestock.livestock_id) AS total_bids,
                            (SELECT COALESCE(COUNT(DISTINCT customer_id), 0) 
                             FROM bidding 
                             WHERE bidding.livestock_id = livestock.livestock_id) AS total_bidders
                            FROM livestock 
                            JOIN farmer ON livestock.farmer_id = farmer.farmer_id 
                            LEFT JOIN auction ON livestock.livestock_id = auction.livestock_id
                            WHERE livestock.availability_status IN ('Available' , 'In Auction')
                            AND livestock.date_listed >= NOW() - INTERVAL '7 days'
                            ORDER BY livestock.date_listed DESC 
                            LIMIT 4");
    $newArrivalItems = $stmtNew->fetchAll(PDO::FETCH_ASSOC);
}


include_once $_SERVER['DOCUMENT_ROOT'] . $projectFolder . "/inc/header.php";

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
            echo '  <img src="' . $imgSrc . '" style="width:100%; height:200px; object-fit:cover;" onerror="this.src=\'../assets/no-image.png\';">';
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
<title>Customer Dashboard | RanchLink</title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html {
        scroll-behavior: smooth;
        scroll-padding-top: 100px; 
    }
    body { background: radial-gradient(circle at top, #fdf6ec, #f4efe6); font-family: 'PT Serif', serif; color: #1a1a1a; min-height: 100vh; }
    
    .hero, .stats-container, .winner-banner, .filter-wrapper, .main-container { 
        max-width: 1200px; 
        margin-left: auto; 
        margin-right: auto; 
    }

    .hero { height: 80px; display: flex; justify-content: center; align-items: center; margin-top: 10px; }
    .hero h1 { font-family: 'Cinzel', serif; font-size: 32px; }
    .stats-container { margin: 20px auto 30px; padding: 0 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; }
    .stat-box { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); padding: 25px; border-radius: 24px; border: 1px solid rgba(144, 202, 249, 0.4); display: flex; align-items: center; gap: 20px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03); }
    .stat-box:hover { transform: translateY(-5px); background: #ffffff; box-shadow: 0 12px 20px rgba(144, 202, 249, 0.2); border-color: #90caf9; }
    .stat-box i { font-size: 28px; color: #1976d2; background: #e3f2fd; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 18px; }
    .stat-box strong { font-size: 22px; font-family: 'Cinzel', serif; color: #1a1a1a; display: block; }
    .winner-banner { margin: 0 auto 30px; padding: 0 30px; flex-direction: column; display: flex; align-items: center;}
    .winner-card {
        background: #e3f2fd;
        border: 1px solid #1976d2;
        padding: 12px 20px;
        border-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 30px; 
        width: 100%;      
        min-width: 400px;       
        max-width: 800px;         
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .winner-card h3 {
        font-size: 0.95rem !important; 
        margin-bottom: 2px;
    }

    .winner-card p {
        font-size: 0.85rem; 
        margin: 0;
        line-height: 1.4;
    }

    .btn-pay-compact {
        display: inline-block;
        padding: 8px 16px !important;
        background: #1976d2;
        color: #fff !important;
        border-radius: 6px;
        font-size: 13px !important;
        text-decoration: none;
        font-weight: bold;
        white-space: nowrap;
    }
    .filter-wrapper { margin: 0 auto 30px; padding: 0 30px; }
    .search-bar { display: flex; align-items: center; background-color: #efefef; border-radius: 50px; padding: 10px 25px; margin-bottom: 20px; }
    .search-bar input { border: none; background: transparent; flex: 1; font-size: 16px; outline: none; font-family: 'PT Serif', serif; }
    .btn-search { background: #1976d2; color: white; border: none; border-radius: 50px; padding: 8px 25px; cursor: pointer; font-family: 'Cinzel', serif; font-weight: bold; }
    .pills-container { display: flex; align-items: center; gap: 12px; overflow-x: auto; padding-bottom: 15px; }
    .filter-pill { padding: 10px 22px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 14px; white-space: nowrap; transition: 0.2s; border: none; cursor: pointer; font-family: 'PT Serif', serif; }
    .btn-apply-filter { background: #111; color: #fff; border: none; border-radius: 50px; padding: 10px 25px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; font-family: 'PT Serif', serif; }
    .main-container { 
        flex: 1;            
        min-width: 0;       
        padding: 0;         
    }

    .catalogue { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
        gap: 20px; 
        width: 100%;        
    }
    .section-title { font-family: 'Cinzel', serif; font-size: 28px; margin: 40px 0 20px; border-left: 5px solid #90caf9; padding-left: 15px; }
    .card { background: rgba(255,255,255,0.7); backdrop-filter: blur(14px); border-radius: 18px; padding: 12px; border: 1px solid rgba(144, 202, 249, 0.4); transition: 0.35s; position: relative; }
    .card:hover { transform: translateY(-8px); box-shadow: 0 10px 30px rgba(144,202,249,0.4); }
    .card-img-wrapper { width: 100%; height: 160px; border-radius: 18px; overflow: hidden; margin-bottom: 15px; position: relative; }
    .status-tag { position: absolute; top: 10px; left: 10px; padding: 6px 14px; font-size: 11px; font-weight: bold; border-radius: 18px; color: #fff; z-index: 10; text-transform: uppercase; }
    .status-tag.available { background: #4caf50; }
    .status-tag.sold { background: #f44336; }
    .status-tag.auction { background: #ff9800; } 
    .price { color: #1976d2; font-size: 1.1rem; font-weight: bold; margin-bottom: 15px; }
    .btn-view { display: block; text-align: center; padding: 8px; background: linear-gradient(135deg, #90caf9, #64b5f6); color: #0d1b2a; text-decoration: none; border-radius: 25px; font-weight: bold; }

    .dashboard-layout {
        display: flex;
        flex-direction: row;
        align-items: flex-start;
        width: 100%;
        max-width: 1540px; 
        margin: 30px auto; 
        padding: 0 30px; 
        gap: 30px; 
    }

    .sidebar-filter {
        width: 320px;
        flex-shrink: 0;
        background: #FDF6EC;
        border-radius: 16px; 
        padding: 24px 28px;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.10); 
        border: 1px solid #eeeeee; 
        position: sticky;
        top: 40px; 
        max-height: calc(100vh - 80px);
        overflow-y: auto;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000;
    }

    .sidebar-filter.collapsed {
        margin-left: -320px;
        opacity: 0;
        visibility: hidden;
    }

    .sidebar-trigger-btn {
        position: fixed;
        left: 20px;
        top: 50%;
        transform: translateY(-50%);
        background: #1976d2; 
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3);
        z-index: 999;
        opacity: 0;
        pointer-events: none;
        transition: 0.3s ease;
    }

    .sidebar-trigger-btn.visible {
        opacity: 1;
        pointer-events: auto;
    }

    .filter-main-header {
        border-bottom: 1px solid #f3f3f3; 
        margin-bottom: 5px;
        padding-bottom: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .filter-main-header span {
        font-family: 'PT Serif', serif; 
        color: #1a1a1a; 
        font-size: 16px;
        letter-spacing: 0.5px;
    }

    .filter-section {
        margin-bottom: 0;
        border-radius: 0;
        background: transparent; 
        border: none;
        border-bottom: 1px solid #f3f3f3; 
        overflow: hidden;
    }
    
    .filter-section:last-of-type {
        border-bottom: none; 
    }

    .filter-section-header {
        padding: 20px 0;
        background: transparent; 
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
    }

    .filter-section-header h4 {
        font-family: 'PT Serif', serif; 
        font-size: 16px;
        color: #1a1a1a;
        text-transform: none;
        font-weight: 600;
    }

    .sidebar-input {
        width: 100%;
        padding: 12px 14px; 
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-family: 'PT Serif', serif;
        background: #f9fafb; 
        color: #1a1a1a;
        transition: 0.2s;
    }

    .sidebar-input:focus {
        outline: none;
        border-color: #1976d2;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.08);
    }

    .input-group {
        margin-bottom: 15px;
        margin-top: 5px;
    }

    .btn-apply {
        width: 100%;
        background: linear-gradient(135deg, #1976d2, #1565c0);
        color: white;
        padding: 15px;
        border-radius: 30px;
        border: none;
        font-family: 'Cinzel', serif;
        font-weight: bold;
        cursor: pointer;
        margin-top: 20px;
        box-shadow: 0 4px 10px rgba(25, 118, 210, 0.2);
        transition: 0.3s;
    }

    .btn-apply:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(25, 118, 210, 0.4);
    }

    .clear-all-link {
        font-size: 13px; 
        color: #888888; 
        text-decoration: none;
        font-weight: normal;
        text-transform: none;
        padding: 0;
        border-radius: 0;
        background: transparent;
        transition: color 0.2s;
        font-family: 'PT Serif', serif;
    }

    .clear-all-link:hover {
        color: #1976d2;
        background: transparent;
    }

    .sidebar-close-btn {
        background: #f3f3f3; 
        border: none;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #1a1a1a;
        transition: 0.3s;
    }

    .sidebar-close-btn:hover {
        background: #d32f2f;
        color: white;
        transform: rotate(90deg);
    }

    .open-sidebar-btn {
        position: fixed;
        left: 0; 
        top: 50%;
        transform: translateY(-50%);
        z-index: 1001;
        background: #1a1a1a; 
        color: white;
        border: none;
        border-radius: 0 8px 8px 0; 
        padding: 15px 12px;
        cursor: pointer;
        display: none; 
        box-shadow: 2px 0 10px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
        font-family: 'Cinzel', serif;
        writing-mode: vertical-rl; 
        text-orientation: mixed;
    }

    .open-sidebar-btn:hover {
        padding-left: 20px; 
        background: #1976d2;
    }

    .open-sidebar-btn.visible {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .main-container {
        flex: 1;
        padding: 0 0 60px 0;
        min-width: 0;
        background: transparent;
        transition: all 0.4s ease;
    }
    .card:has(.new-ribbon):hover {
        box-shadow: 0 15px 35px rgba(25, 118, 210, 0.2);
        border-color: #1976d2;
        transform: translateY(-10px) scale(1.02);
    }

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

    .card-img-wrapper:hover .swiper-button-next, 
    .card-img-wrapper:hover .swiper-button-prev {    
        background: rgba(13, 71, 161, 1) !important;
        transform: translateY(-50%) scale(1);
    }

    .filter-pill.active {
        background: #1976d2; 
        color: white;
        border-color: #1976d2;
    }

    .tab-content {
        display: none; 
        animation: fadeIn 0.3s ease; 
    }
    
    .range-slider {
        position: relative;
        height: 4px; 
        background: #e5e7eb;
        border-radius: 2px;
        margin-top: 15px;
    }

    .range-slider input[type="range"] {
        position: absolute;
        width: 100%;
        -webkit-appearance: none;
        background: none;
        pointer-events: none; 
        top: -6px; 
        margin: 0;
    }

    .range-slider input[type="range"]::-webkit-slider-thumb {
        height: 16px; 
        width: 16px;
        border-radius: 50%;
        background: #1a1a1a; 
        cursor: pointer;
        -webkit-appearance: none;
        pointer-events: auto; 
        border: 2px solid #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }

    .range-slider input[type="range"]::-moz-range-thumb {
        height: 16px;
        width: 16px;
        border-radius: 50%;
        background: #1a1a1a;
        cursor: pointer;
        pointer-events: auto;
        border: 2px solid #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 992px) {
        .dashboard-layout { flex-direction: column; gap: 30px; padding: 0 20px; }
        .sidebar-filter { width: 100%; position: relative; top: 0; display: none; max-height: none; }
        .sidebar-filter.active { display: block; }
        .sidebar-toggle-btn { display: block; margin: 0 30px 20px; }
    }

    /*.swiper-pagination-bullet-active {
        background: #1976d2 !important;
    }

    .swiper-pagination-fraction {
        background: rgba(0, 0, 0, 0.5); 
        color: #fff !important;
        font-family: 'PT Serif', serif;
        font-size: 11px;
        font-weight: bold;
        padding: 2px 8px;
        border-radius: 12px;
        width: fit-content !important;
        left: 50% !important;
        transform: translateX(-50%);
        bottom: 10px !important; 
    }

    .swiper-button-next, .swiper-button-prev {
        top: 50% !important;
        transform: translateY(-50%) scale(0.8) !important;
    }*/

    @media (max-width: 992px) {
        .sidebar-filter {
            width: 280px;
        }
    }

    @media (max-width: 992px) {
        .dashboard-layout { flex-direction: column; }
        .sidebar-filter { width: 100%; position: relative; display: none; }
        .sidebar-filter.active { display: block; }
        .sidebar-toggle-btn { display: block; margin: 0 30px 20px; }
    }
</style>
</head>
<body>

    <div class="hero">
        <h1>Welcome, <?= htmlspecialchars($Name); ?>!</h1>
    </div>
    <?php if (!$isGuest): ?>
        <?php if ($unpaidWins): ?>
            <div class="winner-banner">
                <?php foreach ($unpaidWins as $win): 
                    $currentBid = (float)$win['current_bid'];
                    $depositPaid = (float)($win['deposit'] ?? 0);
                    $balance = $currentBid - $depositPaid;
                    ?>
                    <div class="winner-card" style="margin-bottom: 10px;">
                        <div>
                            <h3 style="font-family: 'Cinzel'; color: #1976d2;">
                                🏆 Won: <?= htmlspecialchars($win['name']) ?>
                            </h3>
                            <p>
                        Total Bid: <strong>RM <?= number_format($currentBid, 2) ?></strong> | 
                        Deposit: <strong>RM <?= number_format($depositPaid, 2) ?></strong><br>
                        Balance to Pay: <strong style="color: #d32f2f;">RM <?= number_format($balance, 2) ?></strong><br>
                        <p style="font-style: italic;">Note: <strong style="color:#1976d2">Kindly complete the payment within 3 days after winning the bid.</strong>
                    </p>
                        </div>
                        <div>
                            <a href="pay_balance.php?auction_id=<?= $win['auction_id'] ?>" class="btn-pay-compact">
                                <i class="fas fa-wallet"></i> Pay Now
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;?>

    <?php else: ?>
        <div class="winner-banner">
            <div class="winner-card" style="background: #fff3e0; border-color: #ff9800;">
                <div>
                    <h3 style="font-family: 'Cinzel'; color: #e65100;">Unlock Full Access!</h3>
                    <p>Register now to bid on auctions and track your livestock orders.</p>
                </div>
                <a href="../Models/Customer.php" class="btn-view" style="width: auto; padding: 10px 30px; background: #e65100; color: #fff;">Join Now</a>
            </div>
        </div>
    <?php endif; ?>

    <div id="openBtn" class="sidebar-trigger-btn" onclick="toggleSidebar()">
        <i class="fas fa-filter"></i>
    </div>

    <div class="dashboard-layout">
        <button id="openSidebarBtn" class="open-sidebar-btn" onclick="toggleSidebar()">
    <i class="fas fa-filter"></i> Filters
</button>

<aside class="sidebar-filter" id="mySidebar">
    <form method="GET" action="customer_dashboard.php" id="filterForm">

        <?php if (!empty($_GET['category'])): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($_GET['category']) ?>">
        <?php endif; ?>
        
        <?php if (!empty($_GET['breed'])): ?>
            <input type="hidden" name="breed" value="<?= htmlspecialchars($_GET['breed']) ?>">
        <?php endif; ?>

        <?php if (!empty($_GET['filter'])): ?>
            <input type="hidden" name="filter" value="<?= htmlspecialchars($_GET['filter']) ?>">
        <?php endif; ?>
        <div class="filter-main-header" style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 15px; border-bottom: 2px solid #eaddca; margin-bottom: 20px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i class="fas fa-sliders-h" style="color: #795548;"></i> 
                <span style="font-family: 'Cinzel'; font-weight: bold; text-transform: uppercase; font-size: 14px; color: #5d4037;">Filters</span> 
            </div>
            
            <div style="display: flex; align-items: center; gap: 12px;">
                <a href="customer_dashboard.php" class="clear-all-link">
                    <i class="fas fa-sync-alt" style="font-size: 10px;"></i> Reset
                </a>
                
                <button type="button" class="sidebar-close-btn" onclick="toggleSidebar()" title="Close Sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-section-header" onclick="toggleSection('farm-content')">
                <h4>Farm Name</h4>
                <i class="fas fa-chevron-up"></i>
            </div>
            <div id="farm-content" class="filter-content" style="padding: 0 15px 15px; display: block;">
                <div class="input-group">
                    <select name="farm_name" class="sidebar-input" onchange="this.form.submit()">
                        <option value="">All Farms</option>
                        <?php foreach ($allFarmers as $farm): ?>
                            <option value="<?= htmlspecialchars($farm['farm_name']) ?>" 
                                <?= (isset($_GET['farm_name']) && $_GET['farm_name'] == $farm['farm_name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($farm['farm_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

                <!-- <div class="filter-section">
                    <div class="filter-section-header" onclick="toggleSection('breed-group-content')">
                        <h4>Filter by Breed</h4>
                        <i class="fas fa-chevron-up"></i>
                    </div>
                    <div id="breed-group-content" class="filter-content" style="padding: 15px; display: block;">
                        <input type="hidden" name="category" id="selected_category" value="<?= htmlspecialchars($_GET['category'] ?? '') ?>">

                        <?php foreach ($categoriesWithBreeds as $category => $breeds): ?>
                            <div style="margin-bottom: 15px;">
                                <label style="font-size: 11px; font-weight: bold; color: #795548; text-transform: uppercase; display: block; margin-bottom: 5px;">
                                    <?= htmlspecialchars($category) ?>
                                </label>
                                <select name="breed" class="sidebar-input breed-select" onchange="handleBreedChange(this, '<?= htmlspecialchars($category) ?>')">
                                    <option value="">All <?= htmlspecialchars(ucfirst($category)) ?> Breeds</option>
                                    <?php foreach ($breeds as $breedName): ?>
                                        <option value="<?= htmlspecialchars($breedName) ?>" 
                                            <?= (isset($_GET['breed']) && $_GET['breed'] == $breedName) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($breedName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div> -->

                <div class="filter-section">
                    <div class="filter-section-header" onclick="toggleSection('price-content')">
                        <h4>Price Range (RM)</h4>
                        <i class="fas fa-chevron-up"></i>
                    </div>
                    <div id="price-content" class="filter-content" style="padding: 15px; display: block;">
                        <div class="price-slider-container" style="padding: 0 10px;">
                            <div class="price-input-values" style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; font-weight: bold; color: #795548;">
                                <span>RM <span id="min-val"><?= $_GET['price_min'] ?? '0' ?></span></span>
                                <span>RM <span id="max-val"><?= $_GET['price_max'] ?? '5000' ?></span></span>
                            </div>

                            <div class="range-slider" style="position: relative; height: 5px; background: #e0e0e0; border-radius: 5px;">
                                <div id="slider-track" style="position: absolute; height: 100%; background: #795548; border-radius: 5px;"></div>

                                <input type="range" name="price_min" id="price_min" min="0" max="10000" step="50" 
                                value="<?= $_GET['price_min'] ?? '0' ?>" 
                                oninput="handleSliderInput()">

                                <input type="range" name="price_max" id="price_max" min="0" max="10000" step="50" 
                                value="<?= $_GET['price_max'] ?? '5000' ?>" 
                                oninput="handleSliderInput()">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </aside>

        <div class="content-wrapper" style="flex: 1; min-width: 0;">

        <div class="filter-wrapper" style="margin-top: 20px;">
            <div class="pills-container">
                <button class="filter-pill active" onclick="showTab('all')">All Listings</button>
                <button class="filter-pill" onclick="showTab('new-arrivals')">✨ New Arrivals</button>
                <button class="filter-pill" onclick="showTab('available')">Available</button>
                <button class="filter-pill" onclick="showTab('direct')">Buy Now</button>
                <button class="filter-pill" onclick="showTab('auction')">Auctions</button>
            </div>
        </div>


        <div class="main-container" id="listings">
    
    <div id="tab-new-arrivals" class="tab-content">
    <div style="display: flex; align-items: center; gap: 15px; margin: 40px 0 20px;">
        <h2 class="section-title" style="margin: 0;">✨ New Arrivals</h2>
        <span style="background: #fff3e0; color: #e65100; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; font-family: 'Cinzel'; border: 1px solid #ffcc80;">Fresh Stock</span>
    </div>
    
    <div class="catalogue" style="margin-bottom: 50px;">
        <?php if (!empty($newArrivalItems)): ?>
            <?php foreach ($newArrivalItems as $lv): ?>
                <div class="card" style="border: 2px solid #90caf9; background: linear-gradient(to bottom, #ffffff, #f0f7ff);">
                    <div class="new-ribbon" style="position: absolute; top: -10px; right: -10px; background: #d32f2f; color: white; padding: 5px 15px; font-size: 10px; font-weight: bold; transform: rotate(15px); z-index: 20; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); font-family: 'Cinzel';">NEW</div>
                    
                    <?php 
                    $isAuction = (strtolower(trim($lv['sale_type'] ?? $lv['sale_type'] ?? '')) === 'auction');                        
                    $badgeLabel = $isAuction ? 'New Auction' : 'New Arrival';

                    renderCardImage($lv, $badgeLabel, false, $isAuction); 
                    ?>
                    <div class="name" style="font-family:'Cinzel'; font-weight:bold; margin-top: 10px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span><?= htmlspecialchars($lv['name']); ?></span>
                        <?php if (!empty($lv['category'])): ?>
                            <span style="background: #e3f2fd; color: #0d47a1; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-family: 'Cinzel'; font-weight: bold; border: 1px solid #90caf9; text-transform: uppercase; letter-spacing: 0.5px;">
                                <?= htmlspecialchars($lv['category']); ?>
                            </span>
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
                    <div class="price" style="color: #e65100;">
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
                    <a class="btn-view" href="../Models/livestock_view.php?livestock_id=<?= $lv['livestock_id']; ?>">Check It Out</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
                <i class="fas fa-sparkles" style="color: #ffb74d; font-size: 1.5rem; margin-bottom: 10px;"></i>
                <div style="grid-column: 1/-1; padding: 20px; color:#666;">No new arrivals found.</div>
        <?php endif; ?>
    </div>
</div>
    <!-- Available Tab (All Listings) -->
    <div id="tab-available" class="tab-content">
        <h2 class="section-title">
            <?php 
            if ($isFiltering && !empty($_GET['breed'])) {
                echo 'Available: ' . htmlspecialchars($_GET['breed']) . ' Breed';
            } elseif ($isFiltering) {
                if (!empty($_GET['category'])) {
                    echo 'Available: ' . htmlspecialchars(ucwords($_GET['category']));
                } else {
                    echo 'Search Results';
                }
            } else {
                echo 'Available Listings';
            }
            ?>
        </h2>
        <div class="catalogue">
            <?php if (!empty($available)): ?>
                <?php foreach ($available as $lv): ?>
                    <div class="card">
                        <?php 
                        $isAuction = (strtolower(trim($lv['sale_type'] ?? $lv['sale_type'] ?? '')) === 'auction');
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
                <div style="grid-column: 1/-1; padding: 20px; color:#666;">No listings found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Direct Purchase Tab -->
    <div id="tab-direct" class="tab-content">
        <h2 class="section-title">
            <?php 
            if ($isFiltering && !empty($_GET['breed'])) {
                echo 'Buy Now: ' . htmlspecialchars($_GET['breed']) . ' Breed ';
                } elseif ($isFiltering) {
                if (!empty($_GET['category'])) {
                    echo 'Buy Now: '. htmlspecialchars(ucwords($_GET['category']));
                } else {
                    echo 'Search Results';
                }
            } else {
                echo 'Buy Now Listings';
            }
            ?>
        </h2>
        <div class="catalogue">
            <?php if (!empty($directPurchaseItems)): ?>
                <?php foreach ($directPurchaseItems as $row): 
                    $status = $row['availability_status'] ?? 'Available';
                    $isSold = (stripos($status, 'sold') !== false); 
                    $isAuction = false; 
                ?>
                    <div class="card">
                        <?php renderCardImage($row, $status, $isSold, $isAuction); ?>
                        <div class="name" style="font-family:'Cinzel'; font-weight:bold; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span><?= htmlspecialchars($row['name']); ?></span>
                            <?php if (!empty($row['category'])): ?>
                                <span style="background: #e3f2fd; color: #0d47a1; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-family: 'Cinzel'; font-weight: bold; border: 1px solid #90caf9; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <?= htmlspecialchars($row['category']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.9rem; color:#666; margin: 10px 0;">
                            <i class="fas fa-dna" style="color:#90caf9;"></i> <?= htmlspecialchars($row['breed']) ?><br>
                            <i class="fas fa-tractor" style="color:#90caf9;"></i> <?= htmlspecialchars($row['farm_name']) ?><br>
                            <i class="fas fa-calendar-alt" style="color:#90caf9;"></i> Listed on: <?= date('d M Y', strtotime($row['date_listed'])); ?>
                        </div>
                        <hr style="margin:10px 0; border:0; border-top:1px solid #eee;">
                        <div class="price">
                            <span style="font-size: 0.8rem; color: #666;">Price:</span><br>
                            RM <?= number_format($row['price'] ?? 0, 2); ?>
                        </div>
                        <a class="btn-view" href="../Models/livestock_view.php?livestock_id=<?= $row['livestock_id']; ?>">View Details</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; padding: 20px; color:#666;">No direct purchase items found.</div>
            <?php endif; ?>
        </div>
    </div> 

    <!-- Auction Tab -->
    <div id="tab-auction" class="tab-content">
        <h2 class="section-title">
            <?php 
            if ($isFiltering && !empty($_GET['breed'])) {
                echo 'Auctions: ' . htmlspecialchars($_GET['breed']) . ' Breed ';
            } elseif ($isFiltering) {
                if (!empty($_GET['category'])) {
                    echo 'Auctions: ' . htmlspecialchars(ucwords($_GET['category']));
                } else {
                    echo 'Search Results';
                }
            } else {
                echo 'Auctions Listings';
            }
            ?>
        </h2>
        <div class="catalogue">
            <?php if (!empty($auctionItems)): ?>
                <?php foreach ($auctionItems as $row): 
                    $status = $row['availability_status'] ?? 'Available';
                    $isClosed = (stripos($status, 'sold') !== false || stripos($status, 'unavailable') !== false); 
                ?>
                    <div class="card" style="border-color: <?= $isClosed ? 'rgba(244, 67, 54, 0.4)' : 'rgba(255, 152, 0, 0.4)' ?>;">
                        <?php renderCardImage($row, $isClosed ? $status : 'Auction', $isClosed, true); ?>
                        <div class="name" style="font-family:'Cinzel'; font-weight:bold; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span><?= htmlspecialchars($row['name']); ?></span>
                            <?php if (!empty($row['category'])): ?>
                                <span style="background: #e3f2fd; color: #0d47a1; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-family: 'Cinzel'; font-weight: bold; border: 1px solid #90caf9; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <?= htmlspecialchars($row['category']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.9rem; color:#666; margin: 10px 0;">
                            <i class="fas fa-tractor" style="color:#90caf9;"></i> <?= htmlspecialchars($row['farm_name']) ?><br>
                            <i class="fas fa-dna" style="color: <?= $isClosed ? '#f44336' : '#ff9800' ?>;"></i> <?= htmlspecialchars($row['breed']) ?><br>
                            <i class="fas fa-calendar-alt" style="color: <?= $isClosed ? '#767676' : '#ff9800' ?>;"></i> Listed on: <?= date('d M Y', strtotime($row['date_listed'])); ?><br>
                            <i class="fas fa-gavel" style="color: <?= $isClosed ? '#f44336' : '#ff9800' ?>;"></i> 
                            <span style="font-weight: bold; color: <?= $isClosed ? '#f44336' : '#2e7d32' ?>;">
                                <?= $isClosed ? 'Bidding Closed' : 'Bidding Open' ?>
                            </span>
                           <div style="margin-top: 5px; font-size: 0.85rem; color: #e65100; font-weight: 500;">
                                    <i class="fas fa-gavel"></i> Total Bids: <strong><?= (int)($row['total_bids'] ?? 0); ?></strong> | 
                                    <i class="fas fa-users"></i> Bidders: <strong><?= (int)($row['total_bidders'] ?? 0); ?></strong>
                                </div>
                        </div>
                        <hr style="margin:10px 0; border:0; border-top:1px solid #eee;">
                        <div class="price" style="color: <?= $isClosed ? '#767676' : '#e65100' ?>;">
                            <span style="font-size: 0.8rem; color: #666;">
                                <?= !empty($row['current_high_bid']) ? 'Current High Bid:' : 'Starting Price:'; ?>
                            </span><br>
                            RM <?= number_format($row['current_high_bid'] ?? $row['auction_start'] ?? 0, 2); ?>
                        </div>
                        <a class="btn-view" 
                           style="background: <?= $isClosed ? '#ccc' : 'linear-gradient(135deg, #ffb74d, #ff9800)' ?>; margin-top: 10px;" 
                           href="../Models/livestock_view.php?livestock_id=<?= $row['livestock_id']; ?>">
                            <?= $isClosed ? 'View Result' : 'Join Auction' ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; padding: 20px; color:#666;">No auctions found.</div>
            <?php endif; ?>
        </div>
    </div> 
</div> 
</div> 
</div> 

<?php include '../inc/footer.php'; ?>

<script>
    function clearOthers(currentSelect) {
        const allBreedSelects = document.querySelectorAll('.breed-select');
        allBreedSelects.forEach(select => {
            if (select !== currentSelect) {
                select.selectedIndex = 0;
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        const selects = document.querySelectorAll('.breed-select');
        selects.forEach(select => {
            if (select.value !== "") {
                const content = select.closest('.filter-content');
                if (content) {
                    content.style.display = 'block';
                    const headerIcon = content.previousElementSibling.querySelector('.fas.fa-chevron-down');
                    if(headerIcon) headerIcon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                }
            }
        });

        const swiper = new Swiper(".mySwiper", {
            loop: true,
            pagination: { el: ".swiper-pagination", clickable: true },
            navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev" },
            effect: "fade",
            fadeEffect: { crossFade: true }
        });

        showTab('all');
    });

    function moveSlider(event, id, direction) {
        event.preventDefault(); 
        const slider = document.getElementById('slider-' + id);
        if(!slider) return;
        const imgs = slider.getElementsByClassName('slider-img');
        const counter = slider.querySelector('.image-counter');
        let activeIndex = 0;

        for (let i = 0; i < imgs.length; i++) {
            if (imgs[i].style.display === 'block') {
                activeIndex = i;
                imgs[i].style.display = 'none';
                break;
            }
        }
        let nextIndex = (activeIndex + direction + imgs.length) % imgs.length;
        imgs[nextIndex].style.display = 'block';
        if(counter) counter.innerText = (nextIndex + 1) + '/' + imgs.length;
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('mySidebar');
        const openBtn = document.getElementById('openBtn');
        sidebar.classList.toggle('collapsed');
        if (sidebar.classList.contains('collapsed')) {
            openBtn.classList.add('visible');
        } else {
            openBtn.classList.remove('visible');
        }
    }

    function toggleSection(id) {
        const el = document.getElementById(id);
        el.style.display = (el.style.display === "none") ? "block" : "none";
    }

    function showTab(tabName, event) {
        const contents = document.querySelectorAll('.tab-content');
        const pills = document.querySelectorAll('.filter-pill');
        
        pills.forEach(pill => pill.classList.remove('active'));
        
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('active');
        } else {
            pills.forEach(pill => {
                if (pill.getAttribute('onclick').includes("'" + tabName + "'")) {
                    pill.classList.add('active');
                }
            });
        }

        if (tabName === 'all') {
            contents.forEach(content => content.style.display = 'block');
        } else {
            contents.forEach(content => content.style.display = 'none');
            const target = document.getElementById('tab-' + tabName);
            if (target) target.style.display = 'block';
        }
    }

    function handleBreedChange(currentSelect, categoryName) {
        if (currentSelect.value !== "") {
            const allSelects = document.querySelectorAll('.breed-select');
            allSelects.forEach(select => {
                if (select !== currentSelect) {
                    select.selectedIndex = 0;
                    select.disabled = true; 
                }
            });

            const catInput = document.getElementById('selected_category');
            if (catInput) {
                catInput.value = categoryName;
            }
        } else {
            document.getElementById('selected_category').value = "";
        }

        currentSelect.form.submit();
    }
    let filterTimer;

    function updateSliderUI() {
        const minInput = document.getElementById('price_min');
        const maxInput = document.getElementById('price_max');
        const track = document.getElementById('slider-track');

        if (!minInput || !maxInput) return;

        const minVal = parseInt(minInput.value);
        const maxVal = parseInt(maxInput.value);

        if (minVal > maxVal - 200) { 
        }

        document.getElementById('min-val').innerText = minInput.value;
        document.getElementById('max-val').innerText = maxInput.value;

        const minPercent = (minInput.value / minInput.max) * 100;
        const maxPercent = (maxInput.value / maxInput.max) * 100;
        track.style.left = minPercent + "%";
        track.style.width = (maxPercent - minPercent) + "%";
    }

    function handleSliderInput() {
        updateSliderUI(); 
        debounceSubmit(); 
    }

    function debounceSubmit() {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => {
            console.log("Auto-filtering now...");
            document.getElementById('filterForm').submit();
        }, 800); 
    }

    document.addEventListener("DOMContentLoaded", () => {
        updateSliderUI();
    });
</script>
</body>
</html>