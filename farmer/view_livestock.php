<?php
session_start();
include '../db_connect.php';

date_default_timezone_set('Asia/Kuala_Lumpur');
if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

$stmt = $pdo->prepare("SELECT * FROM farmer WHERE farmer_id = :id");
$stmt->bindParam(':id', $farmer_id, PDO::PARAM_INT);
$stmt->execute();
$farmer = $stmt->fetch(PDO::FETCH_ASSOC);
$name = $farmer['farm_name'] ?? 'Farmer';

$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$current_time = date('Y-m-d H:i:s');

$where_clauses = ["l.farmer_id = :fid"];
$params = [':fid' => $farmer_id];

if (!empty($search)) {
    $where_clauses[] = "(l.name ILIKE :search OR l.breed ILIKE :search OR CAST(l.livestock_id AS TEXT) ILIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category_filter)) {
    $where_clauses[] = "l.category = :cat";
    $params[':cat'] = $category_filter;
}

$availability_filter = $_GET['availability'] ?? ''; 

if (empty($availability_filter)) {
    $where_clauses[] = "l.availability_status IN ('Available', 'Pending', 'In Auction')";
} else {
    $where_clauses[] = "l.availability_status = :avail";
    $params[':avail'] = $availability_filter; 
}

$where_sql = " WHERE " . implode(" AND ", $where_clauses);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM livestock l $where_sql");
$countStmt->execute($params);
$total_items = $countStmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$query = "SELECT DISTINCT ON (l.livestock_id) 
    l.*, l.date_listed,
    a.status as auction_status,
    a.start_time, a.end_time, 
    ad.amount as deposit_amount,
    (SELECT STRING_AGG(vaccination, ', ') FROM health 
      WHERE health.livestockid = l.livestock_id) as vax,
    (SELECT STRING_AGG(vitamin, ', ') FROM health 
      WHERE health.livestockid = l.livestock_id) as vit,
    (SELECT STRING_AGG(medicine, ', ') FROM health 
      WHERE health.livestockid = l.livestock_id) as med,
    (SELECT STRING_AGG(servicetype, ', ') FROM harvestservice 
      WHERE harvestservice.livestockid = l.livestock_id) as available_services,
    (SELECT STRING_AGG(CAST(servicefee AS TEXT), ', ') FROM harvestservice 
      WHERE harvestservice.livestockid = l.livestock_id) as individual_service_fees
FROM livestock l
LEFT JOIN auction a ON l.livestock_id = a.livestock_id
LEFT JOIN auction_deposits ad ON a.auction_id = ad.auction_id
$where_sql
ORDER BY l.livestock_id DESC, a.auction_id DESC 
LIMIT $limit OFFSET $offset";

$stmt_livestock = $pdo->prepare($query);
$stmt_livestock->execute($params);

$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND user_type = 'farmer' AND is_read = FALSE");
$stmtUnread->execute(['uid' => $farmer_id]);
$unreadCount = $stmtUnread->fetchColumn();

$imageFolder = "uploads/";
$imagePath = (!empty($farmer['profile_image']) && file_exists($imageFolder . $farmer['profile_image'])) 
? $imageFolder . $farmer['profile_image'] 
: $imageFolder . "default.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Livestock Inventory Ledger | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">
    
    <style>
        .main-content {
            transition: margin-left 0.3s ease;
            width: 100%;
            overflow-x: hidden; 
        }

        .page-wrapper {
            max-width: 100%;
            overflow: hidden; 
            padding: 0 20px;
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
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(15px);
            padding: 30px;
            border-radius: 30px;
            border: 1px solid rgba(144, 202, 249, 0.4);
            width: 100%; 
            box-sizing: border-box;
        }

        .toolbar { 
            display: flex; justify-content: center; align-items: center; 
            margin-bottom: 30px; gap: 20px; flex-wrap: wrap;
            background: rgba(25, 118, 210, 0.03); padding: 20px; border-radius: 20px;
        }
        .filter-group { display: flex; gap: 15px; align-items: flex-end; justify-content: center; flex-grow: 1; }
        .input-box { display: flex; flex-direction: column; gap: 5px; }
        .input-box label { font-family: 'Cinzel', serif; font-size: 0.75rem; font-weight: bold; color: #1976d2; }
        .input-field {
            padding: 12px 20px;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            background: white;
            min-width: 200px;
        }

        .btn { 
            padding: 12px 20px; border-radius: 50px; font-family: 'Cinzel', serif; 
            font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; 
            gap: 8px; font-size: 0.85rem; border: none; transition: 0.3s; text-decoration: none;
        }
        .btn-add { background: #2d5a27; color: white; box-shadow: 0 4px 15px rgba(45, 90, 39, 0.2); }
        .btn-search {
            background: #1976d2; 
            color: white; 
            border: none; 
            padding: 12px 25px; 
            border-radius: 15px; 
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover { transform: translateY(-2px); opacity: 0.9; }
        
        .table-container {
            width: 100%;
            overflow-x: auto; 
            margin-top: 20px;
            border-radius: 15px;
        }

        .modern-table {
            width: 100%;
            min-width: 1200px; 
            border-collapse: separate;
            border-spacing: 0 12px;
        }
        .modern-table th { 
            font-family: 'Cinzel', serif; color: #1976d2; font-size: 0.8rem; 
            text-transform: uppercase; padding: 10px 20px; text-align: center;
        }
        .modern-table td { 
            background: white; padding: 15px; text-align: center; 
            border-top: 1px solid rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.02);
        }
        .modern-table tr td:first-child { border-left: 1px solid rgba(0,0,0,0.02); border-radius: 15px 0 0 15px; }
        .modern-table tr td:last-child { border-right: 1px solid rgba(0,0,0,0.02); border-radius: 0 15px 15px 0; }
        .modern-table th, .modern-table td {
            text-align: center;     
            vertical-align: middle; 
        }

        .modern-table td:nth-child(5) {
            text-align: center; 
        }

        .animal-img { width: 65px; height: 65px; object-fit: cover; border-radius: 12px; border: 2px solid #f4efe6; }
        .price-text { color: #2d5a27; font-weight: bold; font-family: 'Cinzel', serif; font-size: 1rem; }
        
        .badge { 
            font-size: 0.7rem; padding: 4px 10px; font-family: 'Cinzel', serif; font-weight: bold;
            border-radius: 50px; display: inline-block;
        }
        .sale-type { background: #f0f4f8; color: #1976d2; }
        
        .auc-box { font-size: 0.75rem; line-height: 1.4; color: #555; }
        .status-tag { padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; display: block; margin-top: 4px; }
        .status-live { background: #e8f5e9; color: #2e7d32; }
        .status-upcoming { background: #fff3e0; color: #ef6c00; }
        .status-closed { background: #ffebee; color: #c62828; }

        .action-link { 
            width: 35px; height: 35px; display: inline-flex; align-items: center; 
            justify-content: center; border-radius: 10px; transition: 0.3s; text-decoration: none;
        }
        .bg-edit { background: rgba(25, 118, 210, 0.1); color: #1976d2; }
        .bg-edit:hover { background: #1976d2; color: white; }
        .bg-delete { background: rgba(211, 47, 47, 0.1); color: #d32f2f; }
        .bg-delete:hover { background: #d32f2f; color: white; }
        
        .img-container { position: relative; display: inline-block; }
        .img-count-badge { position: absolute; bottom: -5px; right: -5px; background: #1976d2; color: white; font-size: 10px; padding: 2px 5px; border-radius: 6px; border: 2px solid white; font-weight: bold; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 30px; }
        .pagination a { 
            text-decoration: none; color: #1976d2; padding: 8px 16px; border-radius: 8px; 
            background: white; border: 1px solid rgba(25, 118, 210, 0.2); 
            font-family: 'Cinzel', serif; font-weight: bold; font-size: 0.8rem; transition: 0.3s;
        }
        .pagination a.active { background: #1976d2; color: white; border-color: #1976d2; }
        .pagination a:hover:not(.active) { background: rgba(25, 118, 210, 0.05); }
        .pagination span { font-family: 'Cinzel', serif; font-size: 0.8rem; color: #888; }

        .custom-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #1976d2; }
        .success-banner {
            background: #e6f4ea;
            color: #1e7e34;
            padding: 15px 25px;
            border-radius: 15px;
            border: 1px solid #c3e6cb;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'PT Serif', serif;
            animation: slideIn 0.5s ease-out;
        }

        .success-banner i { font-size: 1.2rem; }
        .success-banner button { margin-left: auto; background: none; border: none; cursor: pointer; font-size: 20px; color: #1e7e34; }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
                <a onclick="toggleSubmenu(this)"class="active">
                    <i class="fa-solid fa-cow"></i> <span>Livestock Inventory</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="submenu">
                    <li><a href="view_livestock.php" class="active"><i class="fas fa-list"></i> View All Livestock</a></li>
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
                        <li class="active">Livestock Inventory Ledger</li>
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
                <h2 class="main-title">Livestock Inventory Ledger</h2>
            </div>
            <?php if (isset($_GET['msg']) || isset($_GET['status'])): 
            $messageType = $_GET['msg'] ?? $_GET['status'];
            ?>

            <?php if ($messageType == 'success'): ?>
                <div class="success-banner">
                    <i class="fas fa-check-circle"></i>
                    <span>Livestock details updated successfully!</span>
                    <button onclick="this.parentElement.style.display='none'">&times;</button>
                </div>

            <?php elseif ($messageType == 'listing_approved' || $messageType == 'approved'): ?>
                <div class="success-banner" style="background: #e8f5e9; color: #2e7d32; border-color: #c8e6c9;">
                    <i class="fas fa-check-circle"></i>
                    <span>Livestock listed successfully! Your animal is now on the marketplace.</span>
                    <button onclick="this.parentElement.style.display='none'" style="color: #2e7d32;">&times;</button>
                </div>

            <?php elseif ($messageType == 'pending_approval'): ?>
                <div class="success-banner" style="background: #fff3e0; color: #e65100; border-color: #ffe0b2;">
                    <i class="fas fa-clock"></i>
                    <span>Listing Submitted! Your livestock listing is currently <strong>pending admin approval.</strong></span>
                    <button onclick="this.parentElement.style.display='none'" style="color: #e65100;">&times;</button>
                </div>
            <?php endif; ?>

        <?php endif; ?>

                <form method="GET" class="toolbar">
                    <div class="filter-group">
                        <div class="input-box">
                            <label>Keyword Search</label>
                            <input type="text" name="search" class="input-field" placeholder="Name, Breed, or ID..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="input-box">
                            <label>Category</label>
                            <select name="category" class="input-field">
                                <option value="">-- All Livestock --</option>
                                <option value="Cattle" <?= $category_filter == 'Cattle' ? 'selected' : '' ?>>Cattle</option>
                                <option value="Goat" <?= $category_filter == 'Goat' ? 'selected' : '' ?>>Goat</option>
                                <option value="Sheep" <?= $category_filter == 'Sheep' ? 'selected' : '' ?>>Sheep</option>
                            </select>
                        </div>
                        <div class="input-box">
                            <label>Availability</label>
                            <select name="availability" class="input-field">
                                <option value="">-- Active Only --</option>
                                <option value="Available" <?= ($availability_filter == 'Available') ? 'selected' : '' ?>>Available Now</option>
                                <option value="Pending" <?= ($availability_filter == 'Pending') ? 'selected' : '' ?>>Pending Approval</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-search"><i class="fas fa-filter"></i> Apply Filter</button>
                        <?php if($search || $category_filter): ?>
                            <a href="view_livestock.php" class="btn" style="background:#eee; color:#666;">Clear</a>
                        <?php endif; ?>
                    </div>
                        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                            <a href="livestock_archive.php" class="btn" style="background: #607d8b; color: white;">
                                <i class="fas fa-archive"></i> View Archive
                            </a>
                            <a href="add_livestock.php" class="btn btn-add">
                                <i class="fas fa-plus-circle"></i> Register New Livestock
                            </a>
                            <button type="submit" form="bulkActionForm" class="btn btn-delete" 
                            style="background: #d32f2f; color: white;" 
                            onclick="return confirm('Delete selected items?')">
                            <i class="fas fa-trash"></i> Delete</button>
                        </div>
                </form>

                <?php if(isset($_SESSION['msg'])): ?>
                    <div style="padding: 15px; background: #e8f5e9; color: #2e7d32; border-radius: 10px; margin-bottom: 20px; font-family: 'Cinzel', serif; font-size: 0.8rem;">
                        <i class="fas fa-check-circle"></i> <?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
                    </div>
                <?php endif; ?>

                <form id="bulkActionForm" method="POST" action="bulk_delete.php">
                    <div class="table-container">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll" class="custom-checkbox"></th>
                                    <th>No.
                                    <th>ID</th>
                                    <th>Images</th>
                                    <th style="text-align: left;">Livestock Details</th>
                                    <th>Health Records</th> <th>Services</th><th>Type</th>
                                    <th>Price/Bid</th>
                                    <th>Deposit</th>
                                    <th>Schedule</th>
                                    <th>Availability</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $counter = $offset + 1; 
                                if ($total_items > 0): ?>
                                    <?php while($row = $stmt_livestock->fetch(PDO::FETCH_ASSOC)) : ?>
                                        <tr>
                                            <td><input type="checkbox" name="ids[]" value="<?= $row['livestock_id'] ?>" class="custom-checkbox item-checkbox"></td>
                                            <td style="font-family: 'Cinzel', serif; font-weight: bold; color: #999; font-weight: bold;">
                                                <?= $counter++ ?>.
                                            </td>
                                            <td style="font-size: 0.8rem; font-weight: bold; color: #1976d2;">
                                                <?= htmlspecialchars($row['farmer_livestock_no']) ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $image_list = !empty($row['image']) ? explode(',', $row['image']) : [];
                                                $display_image = !empty($image_list) ? trim($image_list[0]) : 'placeholder.jpg';
                                                $img_path = ($display_image !== 'placeholder.jpg' && strpos($display_image, 'uploads/') === false) ? 'uploads/'.$display_image : $display_image;
                                                ?>
                                                <div class="img-container">
                                                    <img src="<?= $img_path ?>" class="animal-img" alt="Animal">
                                                    <?php if(count($image_list) > 1): ?>
                                                        <span class="img-count-badge">+<?= count($image_list) - 1 ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td style="text-align: left;">
                                                <strong style="color:#0d1b2a; display:block;"><?= htmlspecialchars($row['name']) ?></strong>
                                                <span style="font-size: 0.75rem; color: #666;"><?= htmlspecialchars($row['breed']) ?> • <?= $row['age'] ?>m</span>
                                                <div style="font-size: 0.75rem; color: #333;">
                                                    <?= date('d M Y', strtotime($row['date_listed'])) ?>
                                                </div>
                                                <!-- <div style="font-size: 0.7rem; color: #999;">
                                                    <?= date('h:i A', strtotime($row['date_listed'])) ?>
                                                </div> -->
                                            </td>

                                            <td style="font-size: 0.7rem; text-align: left; line-height: 1.4; min-width: 140px;">
                                                <?php if($row['vax'] || $row['vit'] || $row['med']): ?>
                                                    <div title="Vaccinations"><i class="fas fa-syringe" style="color:#1976d2; width:15px;"></i> <?= htmlspecialchars($row['vax'] ?: 'None') ?></div>
                                                    <div title="Vitamins"><i class="fas fa-capsules" style="color:#2d5a27; width:15px;"></i> <?= htmlspecialchars($row['vit'] ?: 'None') ?></div>
                                                    <div title="Medicine"><i class="fas fa-pills" style="color:#d32f2f; width:15px;"></i> <?= htmlspecialchars($row['med'] ?: 'None') ?></div>
                                                <?php else: ?>
                                                    <span style="color:#ccc;">No Records</span>
                                                <?php endif; ?>
                                            </td>

                                            <td style="font-size: 0.85rem; line-height: 1.4; vertical-align: top;">
                                                <?php 
                                                if (!empty($row['available_services']) && $row['available_services'] !== 'None') {

                                                    $serviceList = explode(',', $row['available_services']);
                                                    $feeList = !empty($row['individual_service_fees']) ? explode(', ', $row['individual_service_fees']) : [];

                                                    foreach ($serviceList as $index => $service) {
                                                        $serviceName = htmlspecialchars(trim($service));            
                                                        $feeAmount = isset($feeList[$index]) ? trim($feeList[$index]) : 0;

                                                        echo '<div style="margin-bottom: 10px; padding-left: 8px;">';                
                                                        echo '<div style="color: #a67c52; font-weight: bold; font-size: 0.75rem; text-transform: uppercase;">' . $serviceName . '</div>';                
                                                        echo '<div style="color: #2d5a27; font-weight: bold; font-size: 0.72rem; margin-top: 2px;">';
                                                        echo ($feeAmount > 0) ? '(RM ' . number_format((float)$feeAmount, 2) . ')' : '<span style="color:#ccc;">—</span>';
                                                        echo '</div>';

                                                        echo '</div>';
                                                    }
                                                } else {
                                                    echo '<span style="color:#ccc; font-style: italic;">No services</span>';
                                                }
                                                ?>
                                            </td>

                                            <td><span class="badge sale-type"><?= $row['sale_type'] ?></span></td>
                                            <td class="price-text">RM <?= number_format($row['price'], 2) ?></td>

                                            <td>
                                                <?php if ($row['sale_type'] === 'Auction'): ?>
                                                    <span style="color:#d32f2f; font-weight:bold;">RM <?= number_format($row['deposit_amount'] ?? 0, 2) ?></span>
                                                <?php else: ?>
                                                    <span style="color:#ccc;">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if ($row['sale_type'] === 'Auction' && $row['start_time']): 
                                                    $start = $row['start_time']; $end = $row['end_time'];
                                                    if ($current_time < $start) { $label = "Upcoming"; $class = "status-upcoming"; }
                                                    elseif ($current_time >= $start && $current_time <= $end) { $label = "Live"; $class = "status-live"; }
                                                    else { $label = "Closed"; $class = "status-closed"; }
                                                    ?>
                                                    <div class="auc-box">
                                                        <span class="status-tag <?= $class ?>"><?= $label ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color:#ccc; font-size:0.75rem;">Direct</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php 
                                                $base_status = $row['availability_status']; 
                                                $auction_active = ($row['sale_type'] === 'Auction' && $row['auction_status'] === 'active');

                                                if ($base_status === 'Pending') {
                                                    $label = 'Pending Approval';
                                                    $badge_color = '#fff3e0'; 
                                                    $text_color = '#e65100';
                                                    $icon = 'fa-hourglass-half';
                                                } elseif ($auction_active) {
                                                    $label = 'In Auction';
                                                    $badge_color = '#e3f2fd'; 
                                                    $text_color = '#1565c0';
                                                    $icon = 'fa-gavel';
                                                } elseif ($base_status === 'Available') {
                                                    $label = 'Available';
                                                    $badge_color = '#e8f5e9'; 
                                                    $text_color = '#2e7d32';
                                                    $icon = 'fa-check-circle';
                                                } else {
                                                    $label = htmlspecialchars($base_status);
                                                    $badge_color = '#f5f5f5';
                                                    $text_color = '#666';
                                                    $icon = 'fa-info-circle';
                                                }
                                                ?>
                                                <span class="badge" style="background: <?= $badge_color ?>; color: <?= $text_color ?>; padding: 5px 10px; border-radius: 4px; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                    <i class="fas <?= $icon ?>"></i> <?= $label ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display:flex; gap:5px; justify-content:center;">
                                                    <a href="farmer_edit_livestock.php?livestock_id=<?= $row['livestock_id'] ?>" class="action-link bg-edit"><i class="fas fa-edit"></i></a>
                                                    <a href="farmer_delete_livestock.php?livestock_id=<?= $row['livestock_id'] ?>" class="action-link bg-delete" onclick="return confirm('Archive record?')"><i class="fas fa-trash-alt"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="13" style="padding: 100px 20px; text-align: center; background: white;">
                                            <div style="color: #ccc; margin-bottom: 20px;">
                                                <i class="fas fa-folder-open" style="font-size: 5rem; opacity: 0.3;"></i>
                                            </div>
                                            <h3 style="font-family: 'Cinzel', serif; color: #0d1b2a; margin-bottom: 10px;">No Records Found</h3>
                                            <p style="font-family: 'PT Serif', serif; color: #777; max-width: 500px; margin: 0 auto;">
                                                <?php if(!empty($search) || !empty($category_filter)): ?>
                                                    Your search for "<?= htmlspecialchars($search) ?>" didn't return any results. Try adjusting your filters.
                                                <?php else: ?>
                                                    Your livestock ledger is currently empty. Click "Register New Livestock" to add your first animal.
                                                <?php endif; ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="display:flex; justify-content:center; gap:10px; margin-top:20px;">
                        <?php if($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>"><i class="fas fa-chevron-left"></i></a>
                        <?php endif; ?>

                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" 
                               class="btn" style="background: <?= $i == $page ? '#1976d2' : '#fff' ?>; color: <?= $i == $page ? '#fff' : '#1976d2' ?>; border:1px solid #1976d2;">
                               <?= $i ?>
                           </a>
                       <?php endfor; ?>

                       <?php if($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                    </div> <?php endif; ?>

                    <div style="text-align: center; margin-top: 10px; font-family: 'Cinzel', serif; font-size: 0.7rem; color: #888;">
                        Page <?= $page ?> of <?= $total_pages ?> (<?= $total_items ?> total records)
                    </div>
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
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    </script>
</body>
</html>