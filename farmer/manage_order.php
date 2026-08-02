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

$stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();

$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$status_filter = $_GET['status'] ?? 'All';
$search_query = $_GET['search'] ?? '';
$sort_filter = $_GET['sort'] ?? 'newest';
$date_filter = $_GET['date_range'] ?? 'all';

try {
    $conditions = ["l.farmer_id = :fid"];
    $params = [':fid' => $farmer_id];

    if ($status_filter !== 'All') {
    if ($status_filter === 'Suspended') {
        $conditions[] = "p.payment_status = 'Suspicious'";
    } elseif ($status_filter === 'Cancelled Order') {
        $conditions[] = "oi.item_status = 'Cancelled Order'";
    } elseif ($status_filter === 'Terminated') {
        $conditions[] = "oi.item_status = 'Terminated'";
    } else {
        $conditions[] = "oi.item_status = :status";
        $params[':status'] = $status_filter;
    }
}

    if (!empty($search_query)) {
        $search_term = trim($search_query);
        $search_param = "%$search_term%";

        if (is_numeric($search_term)) {
            $conditions[] = "(c.name ILIKE :search OR o.order_id::text = :exact_id)";
            $params[':exact_id'] = $search_term;
        } else {
            $decoded_val = base_convert($search_term, 36, 10); 
            
            if (is_numeric($decoded_val)) {
                $possible_id = floatval($decoded_val) - 10485760;
                
                if ($possible_id > 0 && $possible_id <= 2147483647) {
                    $conditions[] = "(c.name ILIKE :search OR o.order_id = :decoded_id)";
                    $params[':decoded_id'] = (int)$possible_id;
                } else {
                    $conditions[] = "c.name ILIKE :search";
                }
            } else {
                $conditions[] = "c.name ILIKE :search";
            }
        }

        $params[':search'] = $search_param;
    }


    switch ($date_filter) {
        case 'today':
            $conditions[] = "o.order_date >= CURRENT_DATE";
            break;
        case 'yesterday':
            $conditions[] = "o.order_date >= CURRENT_DATE - INTERVAL '1 day' AND o.order_date < CURRENT_DATE";
            break;
        case '7days':
            $conditions[] = "o.order_date >= CURRENT_DATE - INTERVAL '7 days'";
            break;
        case '30days':
            $conditions[] = "o.order_date >= CURRENT_DATE - INTERVAL '30 days'";
            break;
    }

    $where_sql = implode(" AND ", $conditions);

    $count_sql = "SELECT COUNT(DISTINCT o.order_id) 
                  FROM orders o 
                  JOIN order_items oi ON o.order_id = oi.order_id
                  JOIN livestock l ON oi.livestock_id = l.livestock_id
                  JOIN customer c ON o.customer_id = c.customer_id
                  LEFT JOIN payments p ON o.order_id = p.order_id
                  WHERE $where_sql";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    $sql = "SELECT o.order_id, o.order_date, o.selected_services, o.refund_reason, 
               c.name as customer_name, c.phone_number, c.email,
               d.recipient_name, d.deliveryaddress, d.city, d.postcode, d.state,
               d.shipping_method, d.deliveryfee,
               p.payment_status, 
               p.amount as total_paid_amount,
               hs.servicefee as specific_service_fee,
               STRING_AGG(DISTINCT l.name || ' (#' || l.farmer_livestock_no || ')', ', ') as livestock_no,
               STRING_AGG(DISTINCT l.name, '||') as animal_name,
               STRING_AGG(DISTINCT l.image, '||') as animal_image,
               COALESCE(o.selected_services, '') as service_names, 
               CASE 
                   WHEN p.payment_status = 'Suspicious' THEN 'Suspended'
                   WHEN EXISTS (SELECT 1 FROM order_items WHERE order_id = o.order_id AND item_status = 'Cancelled Order') THEN 'Cancelled Order'
                   ELSE MAX(oi.item_status) 
               END as item_status,
               SUM(oi.price_at_purchase) as item_price
        FROM orders o
        JOIN customer c ON o.customer_id = c.customer_id
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN livestock l ON oi.livestock_id = l.livestock_id
        LEFT JOIN delivery d ON o.order_id = d.order_id
        LEFT JOIN payments p ON o.order_id = p.order_id 
        LEFT JOIN harvestservice hs ON (l.livestock_id = hs.livestockid AND o.selected_services = hs.servicetype)
        WHERE $where_sql
        GROUP BY 
            o.order_id, o.order_date, o.selected_services, o.refund_reason, 
            c.name, c.phone_number, c.email,
            d.recipient_name, d.deliveryaddress, d.city, d.postcode, d.state,
            d.shipping_method, d.deliveryfee,
            p.payment_status, p.amount, hs.servicefee
        ORDER BY o.order_date DESC, o.order_id DESC
        LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$base_params = [
    'status' => $status_filter,
    'search' => $search_query,
    'sort' => $sort_filter,
    'date_range' => $date_filter
];
$base_url = "?" . http_build_query($base_params);

if (isset($_GET['msg']) && $_GET['msg'] === 'success' && isset($_GET['id'])) {
    $order_id = htmlspecialchars($_GET['id']);
    $new_status = htmlspecialchars($_GET['new_val']);
    $message = "Order #$order_id has been updated to $new_status.";
} else {
    $message = $_GET['msg'] ?? '';
}

$error = $_GET['error'] ?? '';

$sql = "SELECT * FROM farmer WHERE farmer_id = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id', $farmer_id, PDO::PARAM_INT);
$stmt->execute();
$farmer = $stmt->fetch(PDO::FETCH_ASSOC);

$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND user_type = 'farmer' AND is_read = FALSE");
$stmtUnread->execute(['uid' => $farmer_id]);
$unreadCount = $stmtUnread->fetchColumn();

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
    <title>Customer Order Management | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">
    
    <style>
        .main-content {
            transition: margin-left 0.3s ease, width 0.3s ease;
            margin-left: 260px; 
            width: calc(100% - 260px);
            min-height: 100vh;
            display: flex;      
            flex-direction: column;
            align-items: center; 
        }

        .main-content.expanded {
            margin-left: 80px; 
            width: calc(100% - 80px);
        }

        .page-wrapper { 
            width: 100%;        
            display: flex;      
            justify-content: center; 
            padding: 0 40px 40px 40px; 
            box-sizing: border-box;
        }
        header {
            width: 100% !important; 
            box-sizing: border-box;
            margin-bottom: 50px;
            padding-left: 20px ;
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
            width: 100%;
            max-width: 1200px; 
            margin: 0 auto; 
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(15px);
            padding: 40px;
            border-radius: 30px;
            border: 1px solid rgba(144, 202, 249, 0.4);
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
            box-sizing: border-box;
        }

        .filter-tabs {
            display: flex;
            justify-content: flex-start;
            gap: 8px;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(25, 118, 210, 0.1);
            padding-bottom: 20px;
            overflow-x: auto; 
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            padding-left: 5px;
            padding-right: 5px;
        }

        .filter-tabs::-webkit-scrollbar {
            height: 4px;
        }

        .filter-tabs::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }

        .filter-tabs::-webkit-scrollbar-thumb {
            background: rgba(25, 118, 210, 0.3); 
            border-radius: 10px;
        }

        .filter-tabs::-webkit-scrollbar-thumb:hover {
            background: rgba(25, 118, 210, 0.6);
        }

        .filter-tabs {
            scrollbar-width: thin;
            scrollbar-color: rgba(25, 118, 210, 0.3) rgba(0, 0, 0, 0.05);
        }

        .filter-tabs::-webkit-scrollbar {
            display: none;
        }

        .filter-tabs a { 
            padding: 8px 16px; 
            text-decoration: none; 
            color: #1976d2; 
            font-family: 'Cinzel', serif; 
            font-size: 0.7rem; 
            font-weight: bold; 
            border-radius: 50px; 
            transition: 0.3s; 
            background: rgba(255, 255, 255, 0.5); 
            border: 1px solid #1976d2;
            flex-shrink: 0; 
        }

        .filter-tabs a.active { 
            background: #1976d2; 
            color: white; 
            box-shadow: 0 5px 15px rgba(25, 118, 210, 0.3); 
            border-color: #1976d2;
        }

        .filter-tabs a:hover:not(.active) {
            background: rgba(25, 118, 210, 0.1);
            color: #1976d2;
        }

        .toolbar { 
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px; background: rgba(25, 118, 210, 0.05); padding: 20px; border-radius: 20px;
        }
        
        .bulk-group { display: flex; gap: 10px; align-items: center; }
        .input-field { padding: 10px; border: 1px solid rgba(0,0,0,0.1); border-radius: 8px; background: #fff; outline: none; }

        .search-box { position: relative; display: flex; align-items: center; }
        .search-box input { padding: 10px 65px 10px 15px; width: 320px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.1); font-family: 'PT Serif'; }
        .search-icons { position: absolute; right: 15px; display: flex; gap: 10px; align-items: center; }
        .fa-times { color: #ccc; cursor: pointer; display: <?= !empty($search_query) ? 'block' : 'none' ?>; }
        .fa-search { color: #999; }

        .btn-update { background: #1976d2; color: white; padding: 10px 20px; border-radius: 8px; border: none; font-family: 'Cinzel'; font-weight: bold; cursor: pointer; }

        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0 12px;}
        .modern-table th { font-family: 'Cinzel', serif; color: #1976d2; font-size: 0.75rem; text-transform: uppercase;  text-align: center; padding: 15px 10px;}
        .modern-table td { background: white; padding: 20px 10px;
            vertical-align: middle; text-align: center; border-top: 1px solid rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.02); }
        .modern-table tr td:first-child { border-left: 1px solid rgba(0,0,0,0.02); border-radius: 15px 0 0 15px; }
        .modern-table tr td:last-child { border-right: 1px solid rgba(0,0,0,0.02); border-radius: 0 15px 15px 0; }

        .animal-img { width: 50px; height: 50px; object-fit: cover; border-radius: 10px; margin-right: 10px; }
        .service-text { color: #a67c52; font-weight: bold; font-size: 0.85rem; }
        
        .badge { font-size: 0.65rem; padding: 5px 12px; font-family: 'Cinzel', serif; font-weight: bold; border-radius: 50px; text-transform: uppercase; }
        .status-paid { background: #e3f2fd; color: #1976d2; }
        .status-delivered { background: #e8f5e9; color: #2e7d32; }
        .status-processing { background: #fff3e0; color: #ef6c00; }
        .status-ready { background: #f3e5f5; color: #7b1fa2; }
        .status-rejected { background-color: #e74c3c; color: white; }
        .status-cancelled { background-color: #95a5a6; color: white; }
        .status-refund-requested { background: #fffde7; color: #fbc02d; border: 1px solid #fdd835; }
        .status-refunded {
            background-color: #f5f5f5;
            color: #616161;
            border: 1px solid #bdbdbd;
        }
        .btn-approve { background: #2e7d32; color: white; padding: 5px 10px; border-radius: 5px; font-size: 11px; text-decoration: none; }
        .btn-reject-refund { background: #c62828; color: white; padding: 5px 10px; border-radius: 5px; font-size: 11px; text-decoration: none; }

        .status-cancellation-pending { 
            background: #fff0f0; 
            color: #d32f2f; 
            border: 1px solid #ffcdd2; 
        }

        .btn-action-approve {
            background: #2e7d32;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 11px;
            font-weight: bold;
            transition: 0.2s;
        }

        .btn-action-reject {
            background: #757575;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 11px;
            font-weight: bold;
            transition: 0.2s;
        }

        .btn-action-approve:hover { background: #1b5e20; }
        .btn-action-reject:hover { background: #424242; }

        .cancellation-box {
            margin-top: 8px;
            padding: 8px;
            background: rgba(211, 47, 47, 0.05);
            border-radius: 8px;
            border-left: 3px solid #d32f2f;
        }

        .action-icon { color: #666; font-size: 1.1rem; margin: 0 8px; transition: 0.3s; }
        .action-icon:hover { color: #1976d2; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 30px; }
        .pagination a { 
            text-decoration: none; color: #1976d2; padding: 8px 16px; border-radius: 8px; 
            background: white; border: 1px solid rgba(25, 118, 210, 0.2); 
            font-family: 'Cinzel', serif; font-weight: bold; font-size: 0.8rem; transition: 0.3s;
        }
        .pagination a.active { background: #1976d2; color: white; border-color: #1976d2; }
        .pagination a:hover:not(.active) { background: rgba(25, 118, 210, 0.05); }
        .pagination span { font-family: 'Cinzel', serif; font-size: 0.8rem; color: #888; }

        .alert {
            padding: 15px 25px;
            margin-bottom: 25px;
            border-radius: 15px;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(5px);
            animation: slideDown 0.4s ease;
        }
        .alert-success {
            background: rgba(232, 245, 233, 0.8);
            border: 1px solid #2e7d32;
            color: #2e7d32;
        }
        .alert-error {
            background: rgba(255, 235, 238, 0.8);
            border: 1px solid #c62828;
            color: #c62828;
        }
        .alert-close {
            cursor: pointer;
            font-size: 1.1rem;
            opacity: 0.6;
        }
        .alert-close:hover { opacity: 1; }
        .status-suspicious {
            background: #fff0f0 !important;
            color: #d32f2f !important;
            border: 1px solid #d32f2f !important;
            padding: 5px 15px;
            animation: pulse-red 2s infinite;
        }
        .toolbar p {
            margin: 0;
            font-family: 'Cinzel', serif;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark-navy);
            letter-spacing: 0.5px;
        }
        
        .date-filter-box {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.4);
            padding: 5px 15px;
            border-radius: 50px; 
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .date-filter-box p {
            margin: 0;
            font-family: 'Cinzel', serif;
            font-size: 0.75rem;
            font-weight: 700;
            color: #0d1b2a;
            white-space: nowrap; 
            display: flex;
            align-items: center;
        }

        .date-select {
            height: 32px; 
            border: none;
            background: transparent;
            font-family: 'PT Serif', serif;
            font-size: 0.85rem;
            color: #444;
            cursor: pointer;
            padding-right: 25px; /
            outline: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23444' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right center;
        }

        .date-select:hover {
            color: #1976d2;
        }

        .date-select:focus {
            outline: none;
            border-color: var(--primary-blue);
        }
        .drop-item {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: #444;
            font-size: 11px;
        }
        .drop-item:hover {
            background: #f8f9fa;
            color: #1976d2;
        }

        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(211, 47, 47, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(211, 47, 47, 0); }
            100% { box-shadow: 0 0 0 0 rgba(211, 47, 47, 0); }
        }

        @keyframes slideDown {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .main-content.expanded {
                margin-left: 0;
                width: 100%;
            }
        }
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
                <a href="manage_order.php"class="active">
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
                <a href="farmer_manage_reports.php">
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
                        <li class="active">Customer Order Management</li>
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
                <h2 class="main-title">Customer Orders & Delivery</h2>
            </div>
                <?php if ($message): ?>
                    <div class="alert alert-success" id="statusAlert">
                        <span><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></span>
                        <i class="fas fa-times alert-close" onclick="this.parentElement.style.display='none';"></i>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error" id="statusAlert">
                        <span><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></span>
                        <i class="fas fa-times alert-close" onclick="this.parentElement.style.display='none';"></i>
                    </div>
                <?php endif; ?>

                <div class="filter-tabs">
                    <?php 
                    $tabs = ['All' => 'All', 'Preparing' => 'Preparing', 
                     'Ready for Pickup' => 'Ready for Pickup', 'In Transit' => 'In Transit',  'Out for Delivery' => 'Out for Delivery', 'Delivered' => 'Delivered', 'Refunded' => 'Refunded', 'Cancelled Order' => 'Cancelled Order', 'Terminated' => 'Terminated' ];
                    foreach ($tabs as $key => $label):
                        ?>
                        <a href="?status=<?= urlencode($key) ?>&search=<?= urlencode($search_query) ?>" class="<?= ($status_filter == $key) ? 'active' : '' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="toolbar">
                    <form action="process_orders.php" method="POST" id="bulkForm" class="bulk-group">
                        <select name="bulk_status" class="input-field" required>
                            <option value="">Mark Order Status</option>
                            <option value="Preparing">Preparing</option>
                            <!-- <option value="Health Inspection">Health Inspection</option> -->
                            <option value="Ready for Pickup">Ready for Pickup (Self-Pickup)</option>
                            <option value="In Transit">In Transit</option>
                            <!-- <option value="Arrived at Transit Hub">Arrived at Transit Hub</option> -->
                            <option value="Out for Delivery">Out for Delivery</option>
                            <option value="Delivered">Mark Delivered</option>
                            <!-- <option value="Refunded">Mark Refunded</option> -->
                            <!-- <option value="Rejected">Mark Rejected</option> -->
                            <option value="Terminated">Mark Terminated</option>
                        </select>
                        <button type="submit" class="btn-update">Apply</button>
                    </form>

                    <div class="date-filter-box">
                        <form method="GET" style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-filter"></i>
                            <p>SORT BY:</p>

                            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_filter) ?>">

                            <select name="date_range" class="date-select" onchange="this.form.submit()">
                                <option value="all" <?= $date_filter == 'all' ? 'selected' : '' ?>>All</option>
                                <option value="today" <?= $date_filter == 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="yesterday" <?= $date_filter == 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                                <option value="7days" <?= $date_filter == '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                                <option value="30days" <?= $date_filter == '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                            </select>
                        </form>
                    </div>

                        <!-- <form method="GET" id="sortForm">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                            <select name="sort" class="input-field" onchange="this.form.submit()" style="font-family: 'PT Serif';">
                                <option value="newest" <?= $sort_filter == 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort_filter == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                <option value="total_high" <?= $sort_filter == 'total_high' ? 'selected' : '' ?>>Total: High to Low</option>
                                <option value="total_low" <?= $sort_filter == 'total_low' ? 'selected' : '' ?>>Total: Low to High</option>
                                <option value="customer_az" <?= $sort_filter == 'customer_az' ? 'selected' : '' ?>>Customer: A-Z</option>
                            </select>
                        </form>
 -->
                    <form method="GET" class="search-box" id="searchForm">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                        <input type="text" name="search" id="tableSearch" placeholder="Search customer or ID..." value="<?= htmlspecialchars($search_query) ?>">
                        <div class="search-icons">
                            <i class="fas fa-times" id="clearBtn" onclick="clearSearch()"></i>
                            <i class="fas fa-search"></i>
                        </div>
                    </form>
                </div>

                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th style="width: 3%;"><input type="checkbox" id="selectAll"></th>
                                <th style="width: 2%;">No.</th>
                                <th style="width: 7%;">Order Number</th>
                                <th style="width: 10%;">Date</th>
                                <th style="width: 15%;">Customer</th>
                                <th style="width: 15%;">Livestock & Services</th>
                                <th style="width: 22%;">Delivery Info</th>
                                <th style="width: 8%;">Status</th>
                                <th style="width: 8%;">Total</th>
                                <th style="width: 8%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = $offset + 1;
                            if ($orders): foreach ($orders as $o): ?>
                                <tr>
                                    <td><input type="checkbox" name="order_ids[]" value="<?= $o['order_id'] ?>" form="bulkForm" class="order-check"></td>
                                    <td style="font-weight: bold; color: #888;">
                                        <?= $no++ ?>.
                                    </td>
                                    <td class="order-number">
                                        <?= formatOrderNumber($o['order_id']) ?>
                                    </td>
                                    <td style="color: #666; font-size: 0.85rem;">
                                        <div style="font-weight: 500; color: #333;">
                                            <?= date('d M Y', strtotime($o['order_date'])) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #999;">
                                            <i class="far fa-clock"></i> <?= date('h:i A', strtotime($o['order_date'])) ?>
                                        </div>
                                    </td>
                                    <td style="text-align: left;">
                                        <div style="font-weight: bold;"><?= htmlspecialchars($o['customer_name']) ?></div>
                                        <small style="color: #999;"><?= htmlspecialchars($o['phone_number']) ?></small>
                                        <small style="color: #1976d2; display: block;"><i class="fas fa-envelope"></i> <?= htmlspecialchars($o['email']) ?></small>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 10px;">
                                            <?php 
                                            $names = explode('||', $o['animal_name']);
                                            $nos = explode('||', $o['livestock_no']);
                                            $images = explode('||', $o['animal_image']);

                                            foreach($names as $index => $animalName): 
                                                $all_images = explode(',', $images[$index] ?? '');
                                                $img = !empty($all_images[0]) ? $all_images[0] : 'default.png'; 
                                                $livestockNo = $nos[$index] ?? 'N/A';
                                                ?>
                                                <div style="display: flex; flex-direction: column; align-items: center; text-align: center; background: rgba(0,0,0,0.03); padding: 8px; border-radius: 8px;">
                                                    <span style="font-size: 0.75rem; font-weight: bold; margin-bottom: 5px;">
                                                        <?= htmlspecialchars($animalName) ?>
                                                    </span>

                                                    <img src="uploads/<?= htmlspecialchars($img ?: 'placeholder.jpg') ?>" 
                                                    class="animal-img" 
                                                    style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">

                                                    <div style="font-size: 0.7rem; color: #d32f2f; font-weight: bold; margin-top: 5px;">
                                                        ID: <?= htmlspecialchars($livestockNo) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div><br>
                                        <?php if (!empty($o['service_names'])): ?>
                                            <div style="padding: 10px; background: #fffcf5; border: 1px dashed #ffd9a3; border-radius: 8px;">
                                                <div style="font-size: 0.65rem; color: #e67e22; font-weight: 800; text-transform: uppercase; margin-bottom: 4px;">
                                                    <i class="fas fa-cut"></i> Services
                                                </div>
                                                <div style="font-size: 0.8rem; color: #5d4037; line-height: 1.4; font-weight: 500;">
                                                    <?= htmlspecialchars($o['service_names']) ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #d35400; font-weight: bold; margin-top: 4px;">
                                                    + RM <?= number_format($o['specific_service_fee'] ?? 0, 2) ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #ccc; font-size: 0.75rem; font-style: italic;">No services</span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="text-align: left; font-size: 0.8rem; line-height: 1.4;">
                                        <?php if($o['recipient_name']): ?>
                                            <strong style="color: #0d1b2a;"><?= htmlspecialchars($o['recipient_name']) ?></strong><br>

                                            <span style="color: #555;">
                                                <?= htmlspecialchars($o['deliveryaddress']) ?>,<br>
                                                <?= htmlspecialchars($o['postcode']) ?> <?= htmlspecialchars($o['city']) ?>, <?= htmlspecialchars($o['state']) ?>
                                            </span><br>

                                            <div style="margin-top: 5px;">
                                                <small class="badge status-paid" style="padding: 2px 8px;"><?= htmlspecialchars($o['shipping_method']) ?></small>
                                                <span style="font-weight: bold; color: #d35400; margin-left: 5px;">
                                                    RM <?= number_format($o['deliveryfee'], 2) ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #ccc;">Self-Pickup / N/A</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php 
                                        $item_status = $o['item_status']; 
                                        
                                        $s = strtolower(str_replace(' ', '-', $item_status));

                                        $class = match($s) {
                                            'suspended', 'suspicious'  => 'status-suspicious', 
                                            'paid'                     => 'status-paid',
                                            'delivered'                => 'status-delivered',
                                            'rejected'                 => 'status-rejected',
                                            'terminated'                => 'status-cancelled',
                                            'refunded'         => 'status-refunded',
                                            'cancelled-order'     => 'status-canceled-order',
                                            'preparing'                => 'status-processing',
                                            'health-inspection'        => 'status-processing',
                                            'ready-for-pickup'         => 'status-paid', 
                                            'in-transit'               => 'status-processing',
                                            // 'arrived-at-transit-hub'   => 'status-processing',
                                            'out-for-delivery'         => 'status-processing',
                                            default                    => 'status-ready',
                                        };
                                        ?>

                                        <div style="margin-bottom: 8px;">
                                            <span class="badge <?= $class ?>">
                                                <?php if ($item_status === 'Suspended'): ?>
                                                    <i class="fas fa-exclamation-triangle"></i> SUSPENDED
                                                <?php else: ?>
                                                    <?= htmlspecialchars($item_status) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>

                                        <?php 
                                        $has_reason = isset($o['refund_reason']) && trim($o['refund_reason']) !== '';

                                        if (strtolower($item_status) === 'refunded' && $has_reason): 
                                            ?>
                                            <div style="margin-top: 5px;">
                                                <a href="view_refund_evidence.php?order_id=<?= $o['order_id'] ?>" 
                                                 class="btn-view-reason" 
                                                 style="font-size: 0.65rem; padding: 4px 8px; background: #607d8b; color: white; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: bold;">
                                                 <i class="fas fa-comment-dots"></i> VIEW REASON
                                             </a>
                                         </div>
                                     <?php endif; ?>

                                        <?php if ($item_status === 'Suspended'): ?>
                                            <div class="cancellation-box" style="border-left-color: #d32f2f; background: rgba(211, 47, 47, 0.05);">
                                                <small style="display:block; margin-bottom:5px; color:#d32f2f; font-weight:bold;">
                                                    PAYMENT FLAGGED
                                                </small>
                                                <p style="font-size: 10px; color: #666; margin-bottom: 5px;">This order is locked until payment is verified by admin.</p>
                                            </div>
                                        <?php endif; ?>

                                       <!--  <?php if ($item_status === 'Cancelled Order'): ?>
                                            <div class="cancellation-box">
                                                <small style="display:block; margin-bottom:5px; color:#d32f2f; font-weight:bold;">Action Required:</small>
                                                <div style="display: flex; gap: 5px; justify-content: center;">
                                                    <a href="process_cancellation.php?action=approve&order_id=<?= $o['order_id'] ?>" 
                                                     class="btn-action-approve" 
                                                     onclick="return confirm('APPROVE CANCELLATION?\n\n1. Money will be marked for refund.\n2. Livestock will be returned to inventory.')">
                                                     <i class="fas fa-check"></i> Approve
                                                 </a>
                                                 <a href="process_cancellation.php?action=reject&order_id=<?= $o['order_id'] ?>" 
                                                     class="btn-action-reject" 
                                                     onclick="return confirm('Reject this request and continue with the order?')">
                                                     <i class="fas fa-times"></i> Reject
                                                 </a>
                                             </div>
                                         </div>
                                     <?php endif; ?> -->
                                 </td>
                                 <td style="font-family: 'Cinzel'; font-weight: bold; color: #2d5a27;">
                                    <?php 
                                    if (!empty($o['total_paid_amount'])) {
                                        $grandTotal = $o['total_paid_amount'];
                                    } else {
                                        $grandTotal = $o['item_price'] + ($o['deliveryfee'] ?? 0);
                                    }
                                    ?>

                                    <div style="font-size: 1.1rem;">
                                        RM <?= number_format($grandTotal, 2) ?>
                                    </div>

                                    <?php if (!empty($o['deliveryfee']) && $o['deliveryfee'] > 0): ?>
                                        <small style="display:block; color: #888; font-size: 0.65rem; font-family: 'Raleway';">
                                            (Inc. RM <?= number_format($o['deliveryfee'], 2) ?> delivery)
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td style="vertical-align: middle; position: relative; overflow: visible !important;">
                                    <?php 
                                    $item_status = isset($o['item_status']) ? trim($o['item_status']) : '';
                                    $shipping_method = isset($o['shipping_method']) ? strtolower($o['shipping_method']) : '';

                                    $allow_delivery_btn = [
                                        'Paid', 'Preparing', 'Health Inspection', 'Ready for Pickup', 
                                        'In Transit', 'Out for Delivery', 'Delivered'
                                    ];

                                    if ($item_status === 'Cancelled Order'): ?>
                                            <div class="cancellation-box">
                                                <small style="display:block; margin-bottom:5px; color:#d32f2f; font-weight:bold;">Action Required:</small>
                                                <div style="display: flex; gap: 5px; justify-content: center;">
                                                    <a href="process_cancellation.php?action=approve&order_id=<?= $o['order_id'] ?>" 
                                                     class="btn-action-approve" 
                                                     onclick="return confirm('APPROVE CANCELLATION?')">
                                                     <i class="fas fa-check"></i> Approve
                                                 </a>
                                                 <a href="process_cancellation.php?action=reject&order_id=<?= $o['order_id'] ?>" 
                                                     class="btn-action-reject" 
                                                     onclick="return confirm('Reject this request and continue with the order?')">
                                                     <i class="fas fa-times"></i> Reject
                                                 </a>
                                             </div>
                                         </div>

                                    <?php elseif (in_array($item_status, $allow_delivery_btn)): ?>
                                        <div style="display: flex; flex-direction: column; gap: 8px; align-items: center; overflow: visible !important;">

                                            <div class="dropdown" style="width: 100%; position: relative; overflow: visible !important;">
                                                <button type="button" class="btn-mark-status" onclick="toggleRowMenu(<?= $o['order_id'] ?>)" 
                                                    style="width: 100%; font-size: 0.6rem; padding: 6px; background-color: #f39c12; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; white-space: nowrap;">
                                                    <i class="fas fa-list-ul"></i> MARK STATUS <i class="fas fa-caret-down"></i>
                                                </button>

                                                <div id="menu-<?= $o['order_id'] ?>" class="dropdown-content" 
                                                 style="display:none; position: absolute; left: 0; top: 100%; background: white; border: 1px solid #ddd; z-index: 9999 !important; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); width: 180px; text-align: left; padding: 5px 0;">

                                                 <a href="update_status.php?order_id=<?= $o['order_id'] ?>&status=Preparing" class="drop-item" style="padding: 8px 12px; display: block; text-decoration: none; color: #333; font-size: 13px;">Preparing</a>
                                                 
                                                 <!-- <a href="update_status.php?order_id=<?= $o['order_id'] ?>&status=Health Inspection" class="drop-item" style="padding: 8px 12px; display: block; text-decoration: none; color: #333; font-size: 13px;">Health Inspection</a> -->

                                                 <?php if (strpos($shipping_method, 'pickup') !== false): ?>
                                                    <a href="update_status.php?order_id=<?= $o['order_id'] ?>&status=Ready for Pickup" class="drop-item" style="color: #7b1fa2; font-weight: bold; padding: 8px 12px; display: block; text-decoration: none; font-size: 13px;">Ready for Pickup</a>
                                                <?php else: ?>
                                                    <a href="update_status.php?order_id=<?= $o['order_id'] ?>&status=In Transit" class="drop-item" style="padding: 8px 12px; display: block; text-decoration: none; color: #333; font-size: 13px;">In Transit</a>
                                                    <a href="update_status.php?order_id=<?= $o['order_id'] ?>&status=Out for Delivery" class="drop-item" style="padding: 8px 12px; display: block; text-decoration: none; color: #333; font-size: 13px;">Out for Delivery</a>
                                                <?php endif; ?>

                                                <hr style="margin: 5px 0; border: 0; border-top: 1px solid #eee;">

                                                <a href="update_status.php?order_id=<?= $o['order_id'] ?>&status=Delivered" class="drop-item" 
                                                style="color: #2e7d32; font-weight: bold;">Mark Delivered</a>

                                                <a href="update_status.php?order_id=<?= $o['order_id'] ?>&status=Terminated" 
                                                 class="drop-item" 
                                                 style="color: #e74c3c; padding: 8px 12px; display: block; text-decoration: none; font-size: 13px;" 
                                                 onclick="return confirmStatusChange(event, this, 'Terminated')">
                                                 <i class="fas fa-times-circle"></i> Mark Terminated
                                             </a>

                                             <a href="update_status.php?order_id=<?= $o['order_id'] ?>&status=Rejected" 
                                                 class="drop-item" 
                                                 style="color: #c0392b; padding: 8px 12px; display: block; text-decoration: none; font-size: 13px;" 
                                                 onclick="return confirmStatusChange(event, this, 'Rejected')">
                                                 <i class="fas fa-ban"></i> Mark Rejected
                                             </a>
                                            </div>
                                        </div>

                                        <a href="arrange_delivery.php?order_id=<?= $o['order_id'] ?>" 
                                         class="btn-update" 
                                         style="text-decoration: none; font-size: 0.65rem; padding: 8px 12px; display: inline-block; white-space: nowrap; width: 100%; text-align: center; border-radius: 4px;
                                         background-color: <?= ($item_status === 'Delivered') ? '#2ecc71' : '#1976d2' ?>; color: white;">
                                         <i class="fas <?= ($item_status === 'Ready for Pickup') ? 'fa-store' : 'fa-truck' ?>"></i> 
                                         <?= ($item_status === 'Delivered') ? 'VIEW LOGS' : 'Delivery <br>Update' ?>
                                     </a>
                                 </div>

                             <?php else: ?>
                                <p style="font-size: 0.75rem; color:#999">No action.</p>
                            <?php endif; ?>
                        </td>
                        <!-- <td>
                            <a href="../payment/view_receipt.php?session_id=<?= urlencode($o['stripe_payment_id']) ?>" class="action-icon" title="Receipt" target="_blank"><i class="fas fa-print"></i></a>
                            <a href="../Models/livestock_view.php?id=<?= $o['order_id'] ?>" class="action-icon" title="View"><i class="far fa-eye"></i></a>
                        </td> -->
                    </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="9" style="padding: 50px; color: #999;">No records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?= $base_url ?>&page=<?= $page - 1 ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
        <?php endif; ?>

        <?php 
        $start_loop = max(1, $page - 2);
        $end_loop = min($total_pages, $page + 2);

        for ($i = $start_loop; $i <= $end_loop; $i++): ?>
            <a href="<?= $base_url ?>&page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="<?= $base_url ?>&page=<?= $page + 1 ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 10px; font-family: 'Cinzel', serif; font-size: 0.7rem; color: #888;">
        Showing Page <?= $page ?> of <?= $total_pages ?> (<?= $total_rows ?> total orders)
    </div>
<?php endif; ?>
</div>
</div>

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

function toggleRowMenu(id) {
    const targetMenu = document.getElementById('menu-' + id);
    if (!targetMenu) return;

    document.querySelectorAll('.dropdown-content').forEach(m => {
        if (m !== targetMenu) m.style.display = 'none';
    });

    targetMenu.style.display = (targetMenu.style.display === 'block') ? 'none' : 'block';
}

window.onclick = function(event) {
    // Handle Status Dropdown
    if (!event.target.closest('.btn-mark-status')) {
        document.querySelectorAll('.dropdown-content').forEach(m => m.style.display = 'none');
    }
    
    if (!event.target.closest('.profile-trigger')) {
        const profileMenu = document.getElementById('dropdownMenu');
        if (profileMenu) profileMenu.style.display = 'none';
    }
}

    document.getElementById('selectAll').onclick = function() {
        var checkboxes = document.querySelectorAll('.order-check');
        for (var checkbox of checkboxes) { checkbox.checked = this.checked; }
    }

function clearSearch() {
    document.getElementById('tableSearch').value = '';
    document.getElementById('searchForm').submit();
}

document.getElementById('tableSearch').oninput = function() {
    document.getElementById('clearBtn').style.display = (this.value.length > 0) ? 'block' : 'none';
}
setTimeout(function() {
    var alert = document.getElementById('statusAlert');
    if (alert) {
        alert.style.transition = '0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.style.display = 'none', 500);
    }
}, 5000);

document.getElementById('bulkForm').onsubmit = function(e) {
    const statusSelect = this.querySelector('select[name="bulk_status"]');
    const selectedStatus = statusSelect.value;

    const checked = document.querySelectorAll('.order-check:checked');
    if (checked.length === 0) {
        alert("Please select at least one order.");
        e.preventDefault();
        return false;
    }

    if (selectedStatus === 'Rejected' || selectedStatus === 'Terminated') {
        const reason = prompt("Please provide a reason for " + selectedStatus + ":");
        if (reason === null || reason.trim() === "") {
            alert("Reason is required.");
            e.preventDefault();
            return false;
        }

        const oldReason = this.querySelector('input[name="status_reason"]');
        if (oldReason) oldReason.remove();

        let reasonInput = document.createElement('input');
        reasonInput.type = 'hidden';
        reasonInput.name = 'status_reason';
        reasonInput.value = reason;
        this.appendChild(reasonInput);
    }
    return true;
};

function confirmStatusChange(event, element, statusType) {
    const reason = prompt("Please provide a reason for marking this order as " + statusType + ":");
    
    if (reason === null) {
        event.preventDefault();
        return false;
    }
    
    if (reason.trim() === "") {
        alert("A reason is required to update this status.");
        event.preventDefault();
        return false;
    }
    
    element.href += "&status_reason=" + encodeURIComponent(reason.trim());
    
    return true;
}
</script>

</body>
</html>