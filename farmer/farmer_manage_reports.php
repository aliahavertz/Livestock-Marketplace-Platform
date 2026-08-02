<?php
session_start();
require_once '../db_connect.php';
require_once '../inc/numbers.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

$stmt = $pdo->prepare("SELECT * FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$farmer = $stmt->fetch();

$name = $farmer['farm_name'] ?? 'Farmer';
$imagePath = !empty($farmer['image']) ? "uploads/" . $farmer['image'] : "../assets/img/default-profile.png";

$unreadCount = 0; 

$report_type = $_GET['report_type'] ?? 'all'; 
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$status_filter = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';
$report_type = $_GET['report_type'] ?? 'all'; 

if ($report_type !== 'all') {
    $end_date = date('Y-m-d');
    switch ($report_type) {
        case 'daily':   $start_date = date('Y-m-d'); break;
        case 'weekly':  $start_date = date('Y-m-d', strtotime('-7 days')); break;
        case 'monthly': $start_date = date('Y-m-d', strtotime('-30 days')); break;
        case '60_days': $start_date = date('Y-m-d', strtotime('-60 days')); break;
        case '90_days': $start_date = date('Y-m-d', strtotime('-90 days')); break;
        case 'annually': $start_date = date('Y-m-d', strtotime('-1 year')); break;
    }
}

if ($report_type !== 'custom' && $report_type !== 'all') {
    $end_date = date('Y-m-d');
    switch ($report_type) {
        case 'daily':   $start_date = date('Y-m-d'); break;
        case 'weekly':  $start_date = date('Y-m-d', strtotime('-7 days')); break;
        case 'monthly': $start_date = date('Y-m-d', strtotime('-1 month')); break;
        case 'annually': $start_date = date('Y-m-d', strtotime('-1 year')); break;
    }
}

$filter_params = [];
$filter_query = "";

if ($report_type !== 'all' && !empty($start_date) && !empty($end_date)) {
    $filter_query .= " AND o.order_date BETWEEN ? AND ?";
    $filter_params[] = $start_date . " 00:00:00";
    $filter_params[] = $end_date . " 23:59:59";
}

if (!empty($status_filter)) {
    $filter_query .= " AND (LOWER(o.status) = LOWER(?) OR LOWER(p.payment_status) = LOWER(?) OR LOWER(oi.item_status) = LOWER(?))";
    $filter_params[] = $status_filter;
    $filter_params[] = $status_filter;
    $filter_params[] = $status_filter;
}

if (!empty($search_query)) {
    $filter_query .= " AND (c.name LIKE ? OR l.name LIKE ? OR o.order_id LIKE ?)";
    $searchTerm = "%$search_query%";
    $filter_params[] = $searchTerm;
    $filter_params[] = $searchTerm;
    $filter_params[] = $searchTerm;
}
$single_query_params = array_merge([$farmer_id], $filter_params);

$stats_sql = "SELECT COUNT(DISTINCT o.order_id) as total_count, 
              SUM(CASE 
                  WHEN p.payment_status = 'paid' 
                  AND LOWER(o.status) NOT IN ('refunded', 'terminated', 'cancelled') 
                  THEN (p.amount + COALESCE(ad.amount, 0)) 
                  ELSE 0 END) as total_rev, 
              AVG(CASE 
                  WHEN p.payment_status = 'paid' 
                  AND LOWER(o.status) NOT IN ('refunded', 'terminated', 'cancelled') 
                  THEN (p.amount + COALESCE(ad.amount, 0)) 
                  ELSE NULL END) as avg_val 
              FROM orders o 
              JOIN order_items oi ON o.order_id = oi.order_id 
              JOIN livestock l ON oi.livestock_id = l.livestock_id 
              LEFT JOIN payments p ON o.order_id = p.order_id 
              LEFT JOIN auction a ON l.livestock_id = a.livestock_id
              LEFT JOIN auction_deposits ad ON a.auction_id = ad.auction_id
              WHERE l.farmer_id = ?" . $filter_query;

$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($single_query_params);
$kpi = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$cat_sql = "SELECT l.category, COUNT(oi.order_id) as total_sold, SUM(l.price) as revenue
            FROM order_items oi 
            JOIN livestock l ON oi.livestock_id = l.livestock_id
            JOIN orders o ON oi.order_id = o.order_id
            WHERE l.farmer_id = ? $filter_query
            GROUP BY l.category";
$cat_stmt = $pdo->prepare($cat_sql);
$cat_stmt->execute($single_query_params);
$cat_data = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

$trend_stmt = $pdo->prepare("SELECT DATE(o.order_date) as date, SUM(p.amount) as revenue 
    FROM orders o JOIN payments p ON o.order_id = p.order_id 
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN livestock l ON oi.livestock_id = l.livestock_id
    WHERE l.farmer_id = ? AND p.payment_status = 'paid' $filter_query 
    GROUP BY DATE(o.order_date) ORDER BY date ASC");
$trend_stmt->execute($single_query_params);
$trend_rows = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

$dist_stmt = $pdo->prepare("SELECT p.payment_status, COUNT(*) as count 
    FROM orders o JOIN payments p ON o.order_id = p.order_id 
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN livestock l ON oi.livestock_id = l.livestock_id
    WHERE l.farmer_id = ? $filter_query GROUP BY p.payment_status");
$dist_stmt->execute($single_query_params);
$dist_data = $dist_stmt->fetchAll(PDO::FETCH_ASSOC);

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT (
    (SELECT COUNT(DISTINCT o.order_id) FROM orders o 
     JOIN order_items oi ON o.order_id = oi.order_id 
     JOIN livestock l ON oi.livestock_id = l.livestock_id 
     WHERE l.farmer_id = ? $filter_query) 
    + 
    (SELECT COUNT(*) FROM auction_deposits_paid ad
     JOIN auction a ON ad.auction_id = a.auction_id
     JOIN livestock l ON a.livestock_id = l.livestock_id
     WHERE l.farmer_id = ? " . str_replace('o.order_date', 'ad.created_at', $filter_query) . ")
) as total";

$count_params = array_merge([$farmer_id], $filter_params, [$farmer_id], $filter_params);

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params); 
$order_count = $count_stmt->fetchColumn();
$total_pages = ceil($order_count / $limit);

$log_sql = "SELECT 
    CAST(o.order_id AS TEXT) as order_id, 
    o.order_date, o.total_price, o.selected_services,
    o.status as internal_status, oi.item_status, 
    COALESCE(p.payment_status, 'unpaid') as payment_status, 
    COALESCE(c.name, 'Guest') as customer_name, 
    l.name as livestock_name, l.image, 
    COALESCE(d.deliveryfee, 0) as deliveryfee, 
    COALESCE(hs.servicefee, 0) as specific_service_fee,
    o.is_suspicious, 0 as deposit_amount 
FROM orders o
JOIN order_items oi ON o.order_id = oi.order_id 
JOIN livestock l ON oi.livestock_id = l.livestock_id 
LEFT JOIN payments p ON o.order_id = p.order_id 
LEFT JOIN customer c ON o.customer_id = c.customer_id 
LEFT JOIN delivery d ON o.order_id = d.order_id 
LEFT JOIN harvestservice hs ON (l.livestock_id = hs.livestockid AND o.selected_services = hs.servicetype)
WHERE l.farmer_id = ? $filter_query

UNION ALL

SELECT 
    CAST(p.transaction_id AS TEXT) as order_id, 
    ad.created_at as order_date, l.price as total_price, 
    'Deposit' as selected_services, 'Paid' as internal_status,
    'Paid' as item_status, 'paid' as payment_status, 
    c.name as customer_name, l.name as livestock_name, l.image, 
    0 as deliveryfee, 0 as specific_service_fee,
    FALSE as is_suspicious, ad.amount as deposit_amount
FROM auction_deposits_paid ad
JOIN auction a ON ad.auction_id = a.auction_id
JOIN livestock l ON a.livestock_id = l.livestock_id
JOIN customer c ON ad.customer_id = c.customer_id
LEFT JOIN payments p ON (p.amount = ad.amount AND p.order_id IS NULL)
WHERE l.farmer_id = ? " . str_replace('o.order_date', 'ad.created_at', $filter_query) . "

ORDER BY order_date DESC LIMIT $limit OFFSET $offset";

$log_params = array_merge([$farmer_id], $filter_params, [$farmer_id], $filter_params);

$log_stmt = $pdo->prepare($log_sql);
$log_stmt->execute($log_params);
$transactions = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

$imageFolder = "uploads/";
if (!empty($farmer['profile_image'])) {
    $imagePath = $imageFolder . $farmer['profile_image'];
} else {
    $imagePath = $imageFolder . "default.png";
}

if (!file_exists($imagePath)) {
    $imagePath = $imageFolder . "default.png";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales & Business Performance | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .page-wrapper { 
            width: 95%; 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px 0; 
            box-sizing: border-box; 
        }
        #mainContent {
            margin-left: 280px; 
            width: calc(100% - 280px) !important;
            padding: 40px 60px; 
            box-sizing: border-box; 
            transition: margin-left 0.3s ease;
        }

        #mainContent.expanded {
            margin-left: 80px !important; 
            width: calc(100% - 80px) !important;
        }
        
        .breadcrumb-wrapper {
            max-width: 850px;
            margin: 40px auto 10px; 
            padding: 0 10px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            list-style: none;
            font-family: 'Cinzel', serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .breadcrumb li {
            display: flex;
            align-items: center;
            color: #777;
        }

        .breadcrumb li a {
            text-decoration: none;
            color: #1976d2;
            transition: 0.3s;
        }

        .breadcrumb li a:hover {
            color: #0d1b2a;
        }

        .breadcrumb li::after {
            content: "\f105"; 
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            margin: 0 15px;
            color: #ccc;
            font-size: 12px;
        }

        .breadcrumb li:last-child::after {
            display: none;
        }

        .breadcrumb li.active {
            color: #453c34;
        }

        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #1976d2; margin-bottom: 0; font-weight: bold; border: 1px solid #1976d2; border-radius: 30px; padding: 8px; font-size: 0.85rem;}
        .back-btn:hover {
            color: white;
            background-color: #1976d2;
        }
        
        .card-header-row {
            display: flex;
            align-items: center;       
            justify-content: space-between; 
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding-bottom: 15px;
        }

        .main-title { 
            font-family: 'Cinzel', serif; 
            text-align: center; 
            text-transform: uppercase; 
            color: #0d1b2a; 
            margin: 0;                
            padding: 0;               
            letter-spacing: 1px;
            flex-grow: 1;             
        }

        .glass-card { 
            background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            padding: 30px; border-radius: 30px; border: 1px solid rgba(144, 202, 249, 0.4); box-shadow: 0 15px 35px rgba(0,0,0,0.05); 
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-top: 20px;
        }
        
        .report-type-tabs { display: flex; justify-content: center; gap: 10px; margin-bottom: 25px; }
        .report-type-tabs a { padding: 10px 20px; text-decoration: none; color: #1976d2; font-family: 'Cinzel', serif; font-size: 0.75rem; font-weight: bold; border-radius: 50px; border: 1px solid #1976d2; transition: 0.3s; }
        .report-type-tabs a.active { background: #1976d2; color: white; box-shadow: 0 5px 15px rgba(25, 118, 210, 0.3); }
        .report-type-tabs a:hover:not(.active){
            backgorund: rgba(25, 118, 210, 0.1);
            color: #1976d2;
        }
        .category-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .category-mini-card { 
            background: white; padding: 15px 20px; border-radius: 15px; border-left: 5px solid #1976d2;
            display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }
        .category-mini-card span { font-family: 'Cinzel'; font-size: 0.7rem; color: #666; }
        .category-mini-card h5 { margin: 5px 0 0; color: #0d1b2a; font-size: 1rem; }

        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 30px; border-radius: 20px; text-align: center; border: 1px solid rgba(0,0,0,0.05); }
        .kpi-card h4 { font-family: 'Cinzel', serif; font-size: 0.8rem; color: #1976d2; margin-bottom: 12px; }
        .kpi-card .amount { font-size: 1.8rem; font-weight: bold; color: #2d5a27; }

        .visuals-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)) !important;r 1fr; gap: 25px; margin-bottom: 40px; }
        .chart-box { background: white; padding: 25px; border-radius: 25px; border: 1px solid rgba(0,0,0,0.05); height: 350px; box-shadow: 0 5px 15px rgba(0,0,0,0.02); min-width:0; }
        .chart-container {
            position: relative;
            height: 100%;
            width: 100%;
        }
        .no-data-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #999;
            font-family: 'Cinzel';
            font-size: 0.7rem;
            text-align: center;
            pointer-events: none;
        }

        .status-container { display: flex; flex-direction: column; align-items: center; gap: 5px; }
        .pay-paid { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
        .pay-pending { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        .pay-refunded { background: #f3e5f5; color: #4a148c; border: 1px solid #e1bee7; }
        .order-ship { background: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; }
        .order-process { background: #f5f5f5; color: #424242; border: 1px solid #e0e0e0; }
        .order-complete { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .status-suspended { background: #ff0000; color: #fff; border: 1px solid #cc0000; }

        .badge { 
            padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; 
            font-family: 'Cinzel', serif; font-weight: bold; text-transform: uppercase;
        }

        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; }
        .modern-table th { font-family: 'Cinzel', serif; color: #1976d2; font-size: 0.7rem; text-transform: uppercase; padding: 15px; text-align: center; }
        .modern-table td { background: white; padding: 15px; text-align: center; border-top: 1px solid rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.02); }
        .modern-table tr td:first-child { border-left: 1px solid rgba(0,0,0,0.02); border-radius: 15px 0 0 15px; }
        .modern-table tr td:last-child { border-right: 1px solid rgba(0,0,0,0.02); border-radius: 0 15px 15px 0; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 30px; }
        .pagination a { 
            text-decoration: none; color: #1976d2; padding: 8px 16px; border-radius: 8px; 
            background: white; border: 1px solid rgba(25, 118, 210, 0.2); 
            font-family: 'Cinzel', serif; font-weight: bold; font-size: 0.8rem; transition: 0.3s;
        }
        .pagination a.active { background: #1976d2; color: white; border-color: #1976d2; }
        .pagination a:hover:not(.active) { background: rgba(25, 118, 210, 0.05); }
        .pagination span { font-family: 'Cinzel', serif; font-size: 0.8rem; color: #888; }
    </style>
</head>
<body>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-toggle-arrow" onclick="toggleSidebar()">
            <i class="fas fa-chevron-left"></i>
        </div>

        <div class="brand" style="display: flex; align-items: center; gap: 10px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="30" height="28" style="flex-shrink: 0; width: 28px; height: 28px; display: inline-block; fill: currentColor; transform: translateY(-1px);">
                <path d="M22 15 C 32 15, 38 32, 42 45 C 45 42, 47 40, 50 40 C 53 40, 55 42, 58 45 C 62 32, 68 15, 78 15 C 72 30, 66 45, 62 50 C 62 68, 58 80, 50 80 C 42 80, 38 68, 38 50 C 34 45, 28 30, 22 15 Z M30 52 C 26 52, 22 48, 25 42 C 30 46, 34 48, 37 49 C 35 51, 32 52, 30 52 Z M70 52 C 68 52, 65 51, 63 49 C 66 48, 70 46, 75 42 C 78 48, 74 52, 70 52 Z"/>
            </svg>
            <span>RanchLink</span>
        </div>

        <div class="profile-section">
            <button class="profile-trigger" onclick="toggleProfileDropdown()">
                <div class="profile-img">
                    <img src="<?php echo $imagePath; ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                </div>
                <p class="welcome-text">Welcome,</p>
                <h4 class="farmer-name"><?php echo htmlspecialchars($name); ?></h4>
            </button>
            <div id="dropdownMenu" class="dropdown-menu">
                <a href="farmer_profile.php">My Profile</a>
                <hr style="border:0; border-top:1px solid #eee; margin: 10px 0;">
                <a href="../Models/logout.php" style="color: #e74c3c;">Logout</a>
            </div>
        </div>

        <ul class="nav-links">
            <li>
                <a href="farmer_dashboard.php">
                    <i class="fas fa-th-large"></i> <span>Dashboard</span>
                </a>
            </li>
            
            <li>
                <a onclick="toggleSubmenu(this)">
                    <i class="fa-solid fa-cow"></i> <span>Livestock Inventory</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="submenu">
                    <li><a href="view_livestock.php"><i class="fas fa-list"></i> View All Livestock</a></li>
                    <li><a href="add_livestock.php"><i class="fas fa-plus"></i> Add Livestock</a></li>
                    <li><a href="livestock_archive.php"><i class="fas fa-archive"></i> Livestock Archive</a></li>
                </ul>
            </li>
            
            <li>
                <a href="manage_order.php">
                    <i class="fas fa-shopping-basket"></i> <span>Customer Orders</span>
                </a>
            </li>
            
            <li>
                <a onclick="toggleSubmenu(this)">
                    <i class="fas fa-gavel"></i> <span>Livestock Auctions</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="submenu">
                    <li><a href="farmer_manage_auction.php"><i class="fas fa-gavel"></i> Manage Auctions</a></li>
                    <li><a href="create_auction.php"><i class="fas fa-plus-circle"></i> Start New Auction</a></li>
                </ul>
            </li>
            
            <li>
                <a href="manage_payments.php">
                    <i class="fas fa-receipt"></i> <span>Customer Payments</span>
                </a>
            </li>
            <li>
                <a href="manage_feedback.php">
                    <i class="fas fa-comment"></i> <span>Customer Feedbacks</span>
                </a>
            </li>
            <li>
                <a href="farmer_manage_reports.php"class="active">
                    <i class="fas fa-chart-bar"></i> <span>Sales & Report Business</span>
                </a>
            </li>
            <li>
                <a href="../Models/logout.php" style="margin-top: 30px; color: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <main class="main-content" id="mainContent">
        <header>
            <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
                <nav class="breadcrumb-wrapper" style="margin: 0; padding: 0;">
                    <ul class="breadcrumb" style="margin: 0; padding: 0; display: flex; list-style: none;">
                        <li><a href="farmer_dashboard.php"><i class="fas fa-home"></i> My Dashboard</a></li>
                        <li class="active">Sales & Business Performance</li>
                    </ul>
                </nav>
            </div>
            <div class="top-actions">
                <span style="font-size: 14px; color: #888; margin-right: 15px;">
                    <i class="far fa-calendar"></i> <?= date('d M Y') ?>
                </span>

                <a href="notifications.php" class="icon-link">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>


        <div class="page-wrapper">
            <div class="glass-card">
                <div class="card-header-row">
                    <a href="farmer_dashboard.php" class="back-btn">
                        <i class="bi bi-arrow-left-circle-fill"></i> Back
                    </a>
                <h2 class="main-title">Sales & Business Performance</h2>
            </div>

                <div class="report-type-tabs">
                    <?php foreach(['all' => 'All', 'daily'=>'Today', 'weekly'=>'Week', 'monthly'=>'Month', 'annually'=>'Year'] as $k => $v): ?>
                        <a href="?report_type=<?= $k ?>" class="<?= $report_type == $k ? 'active' : '' ?>"><?= $v ?></a>
                    <?php endforeach; ?>
                </div>

                <!-- <div class="category-row">
                    <?php if(empty($cat_data)): ?>
                        <p style="text-align:center; width:100%; color:#999; font-size:0.8rem;">No category data for this period.</p>
                    <?php else: ?>
                        <?php foreach($cat_data as $cat): ?>
                            <div class="category-mini-card">
                                <div>
                                    <span><?= htmlspecialchars($cat['category']) ?></span>
                                    <h5><?= $cat['total_sold'] ?> Units Sold</h5>
                                </div>
                                <i class="fas fa-tags" style="color: #1976d2; opacity: 0.2; font-size: 1.5rem;"></i>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div> -->

                <div class="report-grid">
                    <div class="kpi-card">
                        <h4>Total Revenue (Paid)</h4>
                        <div class="amount">RM <?= number_format($kpi['total_rev'] ?? 0, 2) ?></div>
                    </div>
                    <div class="kpi-card">
                        <h4>Total Orders</h4>
                        <div class="amount"><?= $kpi['total_count'] ?></div>
                    </div>
                    <div class="kpi-card">
                        <h4>Avg. Order Value</h4>
                        <div class="amount">RM <?= number_format($kpi['avg_val'] ?? 0, 2) ?></div>
                    </div>
                </div>

                <!-- Charts Section -->
                <!-- <div class="visuals-row" style="display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 20px; margin-bottom: 40px;">
                    <div class="chart-box">
                        <h5 style="font-family:'Cinzel'; margin-bottom:15px; font-size:0.75rem; color:#1976d2;">Revenue Growth</h5>
                        <div class="chart-container">
                            <?php if (empty($trend_rows)): ?>
                                <div class="no-data-overlay">No sales data available</div>
                            <?php endif; ?>
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-box">
                        <h5 style="font-family:'Cinzel'; margin-bottom:15px; font-size:0.75rem; color:#1976d2;">Sales by Category</h5>
                        <div class="chart-container">
                            <?php if (empty($cat_data)): ?>
                                <div class="no-data-overlay">No sales data available</div>
                            <?php endif; ?>
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-box">
                        <h5 style="font-family:'Cinzel'; margin-bottom:15px; font-size:0.75rem; color:#1976d2;">Payment Status</h5>
                        <div class="chart-container">
                            <?php if (empty($dist_data)): ?>
                                <div class="no-data-overlay">No sales data available</div>
                            <?php endif; ?>
                            <canvas id="distChart"></canvas>
                        </div>
                    </div>
                </div> -->

                <!-- <div class="date-filter-container" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                    <p style="font-family: 'Cinzel'; font-size: 0.8rem; margin: 0; color: #1976d2; font-weight: bold;">
                        <i class="fas fa-calendar-alt"></i> Sort by:
                    </p>
                    <form method="GET" id="dateFilterForm">
                        <select name="report_type" onchange="document.getElementById('dateFilterForm').submit()" 
                        style="padding: 8px 15px; border-radius: 20px; border: 1px solid #ddd; font-family: 'PT Serif'; outline: none; cursor: pointer; background: white;">
                        <option value="all" <?= $report_type == 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="daily" <?= $report_type == 'daily' ? 'selected' : '' ?>>Today</option>
                        <option value="weekly" <?= $report_type == 'weekly' ? 'selected' : '' ?>>Past 7 Days</option>
                        <option value="monthly" <?= $report_type == 'monthly' ? 'selected' : '' ?>>Past 30 Days</option>
                        <option value="60_days" <?= $report_type == '60_days' ? 'selected' : '' ?>>Past 60 Days</option>
                        <option value="90_days" <?= $report_type == '90_days' ? 'selected' : '' ?>>Past 90 Days</option>
                        <option value="annually" <?= $report_type == 'annually' ? 'selected' : '' ?>>Past Year</option>
                    </select>
                </form>
            </div> -->

                <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                    <a href="export_report.php?report_type=<?= $report_type ?>" style="background:#2e7d32; color:white; padding:12px 25px; border-radius:8px; text-decoration:none; font-family:'Cinzel'; font-size:0.75rem; font-weight:bold;">
                        <i class="fas fa-file-excel"></i> Generate Report
                    </a>
                </div>

                <div style="width: 100%; overflow-x: auto; border-radius: 15px; margin-bottom: 20px;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Date / Time</th>
                            <th>Ref Number</th>
                            <th>Customer</th>
                            <th>Livestock</th>
                            <th>Payment Status</th>
                            <th>Order Status</th>
                            <th>Services</th>
                            <th style="text-align: right;">Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="8" style="padding: 50px; color: #999; font-family: 'Cinzel';">
                                    <i class="fas fa-folder-open" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                                    No transaction records found for this period.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $idx = $offset + 1; 
                            foreach($transactions as $t): 
                                $img = !empty($t['image']) ? explode(',', $t['image'])[0] : 'default.png';
                                $p_status = strtolower($t['payment_status']);
                                $is_deposit = ($t['deposit_amount'] > 0);

                                if ($is_deposit) {
                                    $display_ref =  ($t['order_id'] ?? 'N/A');
                                } else {
                                    $display_ref = formatOrderNumber($t['order_id']);
                                }

                                $display_status = !empty($t['internal_status']) ? $t['internal_status'] : $t['item_status'];
                                $o_status = strtolower($display_status);

                                $p_class = ($p_status == 'paid') ? 'pay-paid' : (($p_status == 'refunded') ? 'pay-refunded' : 'pay-pending');
                                $p_icon = ($p_status == 'paid') ? 'fa-check-double' : (($p_status == 'refunded') ? 'fa-undo' : 'fa-clock');

                                if ($o_status == 'completed') { 
                                    $o_class = 'order-complete'; $o_icon = 'fa-flag-checkered'; 
                                } elseif (in_array($o_status, ['shipped', 'delivered'])) { 
                                    $o_class = 'order-ship'; $o_icon = 'fa-truck'; 
                                } elseif (in_array($o_status, ['refunded', 'cancelled', 'rejected', 'full payment'])) { 
                                    $o_class = 'pay-refunded'; $o_icon = 'fa-undo'; 
                                } else { 
                                    $o_class = 'order-process'; $o_icon = 'fa-box'; 
                                }

                                if ($t['is_suspicious']) { 
                                    $display_status = 'Suspended'; $o_class = 'status-suspended'; $o_icon = 'fa-exclamation-triangle'; 
                                }

                                $final_display_price = $is_deposit ? $t['deposit_amount'] : $t['total_price'];
                                ?>
                        <tr>
                            <td style="color: #999; font-weight: bold;"><?= $idx++ ?>.</td>
                            <td>
                                <div style="font-size:0.8rem;"><?= date('d M Y', strtotime($t['order_date'])) ?></div>
                                <div style="font-size:0.7rem; color:#aaa;"><?= date('h:i A', strtotime($t['order_date'])) ?></div>
                            </td>
                            <td><strong style="font-family:'Courier New';"><?= $display_ref ?></strong>
                            </td>
                            <td style="font-weight: bold;"><?= htmlspecialchars($t['customer_name']) ?>
                            </td>
                            <td style="vertical-align: middle; white-space: nowrap;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; text-align: center;">
                                    
                                    <img src="uploads/<?= $img ?>" 
                                    style="width: 45px; height: 45px; border-radius: 6px; object-fit: cover; border: 1px solid #eee; flex-shrink: 0;">
                                    
                                    <span style="font-family: 'PT Serif', serif; font-size: 0.85rem; color: #333; font-weight: 500;">
                                        <?= htmlspecialchars($t['livestock_name']) ?>
                                    </span>

                                </div>
                            </td>
                            <td>
                                <div class="status-container">
                                    <span class="badge <?= $p_class ?>"><i class="fas <?= $p_icon ?>"></i> <?= ucfirst($p_status) ?></span>
                                    <!-- <span class="badge <?= $o_class ?>"><i class="fas <?= $o_icon ?>"></i> <?= ucfirst($display_status) ?></span> -->
                                </div>
                            </td>
                            <td>
                                 <div class="status-container">
                                     <span class="badge <?= $o_class ?>"><i class="fas <?= $o_icon ?>"></i> <?= ucfirst($display_status) ?></span>
                                 </div>
                            </td>

                            <td style="font-size: 0.7rem; color: #666; text-align: left;">
                                <?php if ($is_deposit): ?>
                                    <span style="color: #1976d2; font-weight: bold;">DEPOSIT PAYMENT</span><br>
                                    No Services (Deposit Only)
                                <?php else: ?>
                                    Service: <br> 
                                    <?= htmlspecialchars($t['selected_services'] ?? 'None') ?> 
                                    (RM <?= number_format($t['specific_service_fee'] ?? 0, 2) ?>)<br>
                                    Delivery: RM <?= number_format($t['deliveryfee'] ?? 0, 2) ?>
                                <?php endif; ?>
                            </td>

                            <td style="text-align: right; font-weight: bold; font-family: 'Cinzel'; color: #2d5a27;">
                                RM <?= number_format($final_display_price, 2) ?>
                                <?php if ($is_deposit): ?>
                                    <div style="font-size: 0.6rem; color: #1976d2;">(Auction Deposit)</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php 
                    $base_url = "?" . http_build_query([
                        'status' => $status_filter,
                        'search' => $search_query,
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'report_type' => $report_type
                    ]);

                    if ($page > 1): ?>
                        <a href="<?= $base_url ?>&page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="<?= $base_url ?>&page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?= $base_url ?>&page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <div style="text-align: center; margin-top: 10px; font-family: 'Cinzel', serif; font-size: 0.7rem; color: #888;">
                    Page <?= $page ?> of <?= $total_pages ?> (<?= $order_count ?> total records)
                </div>
            <?php endif; ?>

            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('closed');
            mainContent.classList.toggle('expanded');
            
            if(window.innerWidth <= 768) {
                sidebar.classList.toggle('open');
            }
        }

        function toggleSubmenu(element) {
            const parentLi = element.parentElement;
            
            if(document.getElementById('sidebar').classList.contains('closed')) {
                toggleSidebar();
                return;
            }
            
            parentLi.classList.toggle('open');
        }

        function toggleProfileDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        window.onclick = function(event) {
            if (!event.target.closest('.profile-trigger')) {
                const dropdown = document.getElementById('dropdownMenu');
                if (dropdown) dropdown.style.display = 'none';
            }
        }


        const trendData = <?= json_encode($trend_rows) ?>;
        if (trendData.length > 0) {
            new Chart(document.getElementById('revenueChart'), {
                type: 'line',
                data: {
                    labels: trendData.map(row => row.date),
                    datasets: [{
                        label: 'Revenue (RM)',
                        data: trendData.map(row => row.revenue),
                        borderColor: '#1976d2',
                        backgroundColor: 'rgba(25, 118, 210, 0.1)',
                        fill: true, tension: 0.4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        const catData = <?= json_encode($cat_data) ?>;
        if (catData.length > 0) {
            new Chart(document.getElementById('categoryChart'), {
                type: 'pie',
                data: {
                    labels: catData.map(row => row.category),
                    datasets: [{
                        data: catData.map(row => row.total_sold),
                        backgroundColor: ['#1976d2', '#388e3c', '#fbc02d', '#8e24aa', '#e64a19'],
                        borderWidth: 2, borderColor: '#ffffff'
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        const distData = <?= json_encode($dist_data) ?>;
        if (distData.length > 0) {
            new Chart(document.getElementById('distChart'), {
                type: 'doughnut',
                data: {
                    labels: distData.map(row => row.payment_status),
                    datasets: [{
                        data: distData.map(row => row.count),
                        backgroundColor: ['#2e7d32', '#ffa000', '#c62828', '#757575']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    </script>
</body>
</html>