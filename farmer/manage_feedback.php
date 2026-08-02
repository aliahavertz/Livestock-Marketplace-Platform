<?php
session_start();
require_once '../db_connect.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

$stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();

$farmer_stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$farmer_stmt->execute([$farmer_id]);
$farmer_data = $farmer_stmt->fetch(PDO::FETCH_ASSOC);
$display_farm_name = $farmer_data['farm_name'] ?? 'Farmer';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_action'])) {
    $feedback_id = $_POST['feedback_id'];
    $new_status = $_POST['status'];
    $farmer_reply = trim($_POST['farmer_reply']);
    
    $update = $pdo->prepare("UPDATE feedback SET status = ?, farmer_reply = ? WHERE feedback_id = ? AND farmer_id = ?");
    $update->execute([$new_status, $farmer_reply, $feedback_id, $farmer_id]);
    
    $current_status_filter = $_GET['status_filter'] ?? 'all';
    $current_date_range = $_GET['date_range'] ?? 'all';

    header("Location: manage_feedback.php?success=1&status_filter=$current_status_filter&date_range=$current_date_range");
    exit();
}

$stats_stmt = $pdo->prepare("SELECT rating, COUNT(*) as count FROM feedback WHERE farmer_id = ? GROUP BY rating");
$stats_stmt->execute([$farmer_id]);
$ratings_raw = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
$ratings_map = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
$total_reviews = 0;
$sum_ratings = 0;
foreach($ratings_raw as $r) {
    $ratings_map[$r['rating']] = $r['count'];
    $total_reviews += $r['count'];
    $sum_ratings += ($r['rating'] * $r['count']);
}
$avg_rating = $total_reviews > 0 ? round($sum_ratings / $total_reviews, 1) : 0;

$date_filter = $_GET['date_range'] ?? 'all';
$status_filter = $_GET['status_filter'] ?? 'all';

$filter_query = "";
$params = ['fid' => $farmer_id];

switch ($date_filter) {
    case 'today':
        $filter_query .= " AND f.feedback_date >= CURRENT_DATE";
        break;
    case '2_days':
        $filter_query .= " AND f.feedback_date >= NOW() - INTERVAL '2 days'";
        break;
    case '7_days':
        $filter_query .= " AND f.feedback_date >= NOW() - INTERVAL '7 days'";
        break;
    case '30_days':
        $filter_query .= " AND f.feedback_date >= NOW() - INTERVAL '30 days'";
        break;
}

if ($status_filter === 'Pending') {
    $filter_query .= " AND f.status = :status";
    $params['status'] = 'Pending';
} elseif ($status_filter === 'Approved') {
    $filter_query .= " AND f.status = :status";
    $params['status'] = 'Approved';
}

$sql = "SELECT f.*, l.name as livestock_name, l.image as animal_image, 
c.name as customer_name, c.profile_image 
FROM feedback f
JOIN livestock l ON f.livestock_id = l.livestock_id
JOIN customer c ON f.customer_id = c.customer_id
WHERE f.farmer_id = :fid $filter_query
ORDER BY f.feedback_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Customer Feedback Management | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">

    <style>
        :root { 
            --primary-blue: #1976d2; 
            --dark-navy: #0d1b2a; 
        }

        .page-wrapper { max-width: 1250px; margin: 20px auto; padding: 0 20px; }

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

        .stats-card {
            background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            display: grid; grid-template-columns: 1fr 2fr; gap: 40px; margin-bottom: 30px; align-items: center;
        }
        .avg-box { text-align: center; border-right: 1px solid #eee; }
        .avg-box h2 { font-size: 3rem; font-family: 'Cinzel'; color: #1a1a1a; margin: 0; }
        .rating-bars { display: flex; flex-direction: column; gap: 8px; }
        .bar-row { display: flex; align-items: center; gap: 15px; font-size: 0.85rem; color: #666; }
        .bar-bg { flex-grow: 1; height: 8px; background: #eee; border-radius: 10px; overflow: hidden; }
        .bar-fill { height: 100%; background: #2e7d32; border-radius: 10px; }

        .glass-card-container { 
            background: rgba(255, 255, 255, 0.6); 
            backdrop-filter: blur(15px);
            padding: 40px; 
            border-radius: 30px; 
            border: 1px solid rgba(144, 202, 249, 0.4);
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
            margin-top: 20px;
        }

        .modern-table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0 12px; 
        }

        .modern-table th { 
            font-family: 'Cinzel', serif; 
            color: var(--primary-blue); 
            font-size: 0.8rem; 
            text-transform: uppercase; 
            padding: 10px 20px; 
            text-align: left;
        }

        .modern-table td { 
            background: white; 
            padding: 20px; 
            border-top: 1px solid rgba(0,0,0,0.02); 
            border-bottom: 1px solid rgba(0,0,0,0.02);
            vertical-align: middle;
        }

        .modern-table tr td:first-child { 
            border-left: 1px solid rgba(0,0,0,0.02); 
            border-radius: 15px 0 0 15px; 
        }
        .modern-table tr td:last-child { 
            border-right: 1px solid rgba(0,0,0,0.02); 
            border-radius: 0 15px 15px 0; 
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.8); 
            padding: 30px; 
            border-radius: 20px; 
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07);
            display: grid; 
            grid-template-columns: 1fr 2fr; 
            gap: 40px; 
            margin-bottom: 30px; 
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .customer-cell { display: flex; align-items: center; gap: 12px; }
        .customer-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .livestock-cell { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        .livestock-img { 
            width: 45px; 
            height: 45px; 
            border-radius: 8px; 
            object-fit: cover; 
            border: 1px solid #eee; 
        }
        .feedback-container {
            max-width: 200px;
            position: relative;
        }

        .feedback-text {
            color: #666;
            font-style: italic;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            transition: all 0.3s ease;
        }

        .view-more-indicator {
            font-size: 10px;
            color: var(--primary-blue);
            cursor: pointer;
            font-weight: bold;
            text-transform: uppercase;
            display: block;
            margin-top: 2px;
        }

        .is-expanded .feedback-text {
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
            max-width: none;
        }

        .is-expanded .view-more-indicator::after {
            content: "Show Less";
        }
        .is-expanded .view-more-indicator {
            content: ""; 
            font-size: 0; 
        }
        .is-expanded .view-more-indicator::after {
            font-size: 10px; 
        }

        .status-badge { 
            padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; 
            font-family: 'Cinzel', serif; font-weight: bold; text-transform: uppercase;
        }
        .badge-approved { background: #e8f5e9; color: #2e7d32; }
        .badge-pending { background: #fff3e0; color: #e65100; }

        .action-btn { 
            background: var(--dark-navy); color: white; border: none; padding: 8px 18px; 
            border-radius: 50px; cursor: pointer; font-family: 'Cinzel', serif; 
            font-size: 0.75rem; transition: 0.3s; font-weight: bold;
        }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); background: var(--primary-blue); }

        .inner-reply-card { 
            padding: 25px; border-radius: 15px; background: #f9f9f9; 
            margin: 0 20px 20px 20px; border: 1px solid #eee;
        }
        .inner-reply-card { padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; background: #fdfdfd; margin: 10px; }
        textarea { width: 100%; height: 80px; border: 1px solid #ddd; border-radius: 8px; padding: 12px; font-family: inherit; resize: none; outline: none; transition: 0.3s; }
        textarea:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1); }
        .save-btn { background: var(--primary-blue); color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-family: 'Cinzel'; font-weight: bold; font-size: 0.75rem; }

        /*.controls-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
            padding-bottom: 5px;
        }*/

        .filter-tabs {
            display: flex;
            gap: 10px;
            justify-content: center;
            border-bottom: none;
            margin-bottom: 10px;
            padding-bottom: 10px;
        }

        .tab-link {
            text-decoration: none;
            font-family: 'Cinzel', serif;
            font-size: 0.75rem;
            font-weight: bold;
            color: #1976d2;
            padding: 8px 20px;
            border:1px solid #1976d2;
            border-radius: 50px;
            transition: 0.3s;
        }

        .tab-link.active {
            background: #1976d2;
            color: white;
        }

        .tab-link:hover:not(.active) {
            background: rgba(25, 118, 210, 0.1);
            color: #1976d2;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: flex-end; 
            gap: 12px;
            margin-bottom: 20px;
            padding: 0 10px;
        }

        .toolbar p {
            margin: 0;
            font-family: 'Cinzel', serif;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark-navy);
            letter-spacing: 0.5px;
        }

        .date-select {
            appearance: none; 
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 35px 8px 15px;
            border-radius: 20px;
            font-family: 'PT Serif', serif;
            font-size: 0.85rem;
            color: #444;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23444' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }

        .date-select:hover {
            background-color: #fff;
            border-color: var(--primary-blue);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .date-select:focus {
            outline: none;
            border-color: var(--primary-blue);
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
                <a href="manage_feedback.php"class="active">
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
                        <li class="active">Customer Feedbacks</li>
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
            <div class="stats-card">
                <div class="avg-box">
                    <h2><?= $avg_rating ?></h2>
                    <div class="rating-stars">
                        <?php for($i=1;$i<=5;$i++) echo ($i <= round($avg_rating)) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                    </div>
                    <p style="font-size: 0.8rem; color: #888; margin-top: 10px;">(<?= $total_reviews ?> Reviews)</p>
                </div>
                <div class="rating-bars">
                    <?php foreach([5,4,3,2,1] as $num): 
                        $pct = $total_reviews > 0 ? ($ratings_map[$num] / $total_reviews) * 100 : 0; ?>
                        <div class="bar-row">
                            <span style="width: 40px;"><?= $num ?> star</span>
                            <div class="bar-bg"><div class="bar-fill" style="width: <?= $pct ?>%;"></div></div>
                            <span style="width: 60px; text-align: right;"><?= $ratings_map[$num] ?> reviews</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div style="background:#e8f5e9; color:#2e7d32; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center; font-weight:bold;">
                    <i class="fas fa-check-circle"></i> Feedback updated successfully!
                </div>
            <?php endif; ?>

            <div class="glass-card-container">
                <div class="card-header-row">
                    <a href="farmer_dashboard.php" class="back-btn">
                        <i class="bi bi-arrow-left-circle-fill"></i> Back
                    </a>
                <h3 class="main-title">Customer Feedbacks</h3>
            </div>

                <div class="controls-header">
                    <div class="filter-tabs">
                        <a href="manage_feedback.php?status_filter=all&date_range=<?= $date_filter ?>" class="tab-link <?= $status_filter == 'all' ? 'active' : '' ?>">All Feedback</a>
                        <a href="manage_feedback.php?status_filter=Pending&date_range=<?= $date_filter ?>" class="tab-link <?= $status_filter == 'Pending' ? 'active' : '' ?>">Pending</a>
                        <a href="manage_feedback.php?status_filter=Approved&date_range=<?= $date_filter ?>" class="tab-link <?= $status_filter == 'Approved' ? 'active' : '' ?>">Approved</a>
                    </div>

                <div class="toolbar">
                    <p><i class="fas fa-filter" style="margin-right: 5px; font-size: 0.8rem;"></i> Sort by:</p>
                    <form method="GET" id="filterForm">
                        <select name="date_range" class="date-select" onchange="this.form.submit()">
                            <option value="all" <?= $date_filter == 'all' ? 'selected' : '' ?>>All</option>
                            <option value="today" <?= $date_filter == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="2_days" <?= $date_filter == '2_days' ? 'selected' : '' ?>>Past 2 Days</option>
                            <option value="7_days" <?= $date_filter == '7_days' ? 'selected' : '' ?>>Past 7 Days</option>
                            <option value="30_days" <?= $date_filter == '30_days' ? 'selected' : '' ?>>Past 30 Days</option>
                        </select>
                    </form>
                </div>
            </div>

                <div style="width: 100%; overflow-x: auto;">
                    <table class="modern-table" style="min-width: 1000px;">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Customer</th>
                                <th>Livestock</th>
                                <th>Rating</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($feedbacks)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 50px; background: white; border-radius: 15px;">
                                        <div style="color: #888; font-family: 'PT Serif', serif;">
                                            <i class="fas fa-comment-slash" style="font-size: 3rem; margin-bottom: 15px; color: #ddd;"></i>
                                            <p style="font-size: 1.1rem; margin: 0;">No feedback found for this period.</p>
                                            <p style="font-size: 0.8rem; color: #bbb;">Try changing the date filter or check back later.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php 
                            $no=1;
                            foreach ($feedbacks as $fb): 
                                $customer_pic = (!empty($fb['profile_image']) && file_exists("../Models/uploads/" . $fb['profile_image'])) 
                                ? "../Models/uploads/" . $fb['profile_image'] 
                                : "../Models/uploads/default.png";
                                ?>
                                <tr>
                                    <td style="font-family: 'Cinzel'; font-weight: bold; color: #777; width: 40px;">
                                        <?= $no++ ?>.
                                    </td>
                                    <td>
                                        <div class="customer-cell">
                                            <img src="<?= $customer_pic ?>" class="customer-img">
                                            <div>
                                                <div style="font-weight:bold; color:#333;"><?= htmlspecialchars($fb['customer_name']) ?></div>
                                                <div style="font-size:0.7rem; color:#999;"><?= date('d M Y', strtotime($fb['feedback_date'])) ?>
                                                <div style="font-size: 0.75rem; color: #999;">
                                                    <i class="far fa-clock"></i> <?= date('h:i A', strtotime($fb['feedback_date'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $raw_images = $fb['animal_image'];
                                    $first_image = '';

                                    if (!empty($raw_images)) {
                                        $image_array = explode(',', $raw_images);
                                        $first_image = trim($image_array[0]);
                                    }

                                    $animal_pic = (!empty($first_image) && file_exists("uploads/" . $first_image)) 
                                    ? "uploads/" . $first_image 
                                    : "uploads/default_livestock.png";
                                    ?>

                                    <img src="<?= $animal_pic ?>" class="livestock-img" alt="Livestock">
                                    <div>
                                        <strong style="font-family: 'PT Serif'; display: block;">
                                            <?= htmlspecialchars($fb['livestock_name']) ?>
                                        </strong>
                                    </div>
                                </td>
                                <td style="color: #d4a017; font-size: 0.8rem;">
                                    <?php for($i=1;$i<=5;$i++) echo ($i <= $fb['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                </td>
                                <td>
                                    <div class="feedback-container">
                                        <div class="feedback-text" 
                                        onclick="this.parentElement.classList.toggle('is-expanded')"
                                        title="Click to view full message">
                                        "<?= htmlspecialchars($fb['feedback_message']) ?>"
                                    </div>
                                    <span class="view-more-indicator" onclick="this.parentElement.classList.toggle('is-expanded')">
                                        View More
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?= $fb['status'] == 'Approved' ? 'badge-approved' : 'badge-pending' ?>">
                                    <?= $fb['status'] ?>
                                </span>
                            </td>
                            <td>
                                <button class="action-btn" onclick="toggleReplyForm(<?= $fb['feedback_id'] ?>)">
                                    <i class="fas fa-cog"></i> Reply
                                </button>
                            </td>
                        </tr>

                        <tr id="reply-row-<?= $fb['feedback_id'] ?>" style="display: none;">
                            <td colspan="6" style="background: transparent; padding: 0;">
                                <div class="inner-reply-card">
                                    <form method="POST">
                                        <input type="hidden" name="feedback_id" value="<?= $fb['feedback_id'] ?>">
                                        <div style="display: flex; gap: 20px;">
                                            <div style="flex-grow: 1;">
                                                <label style="font-family: 'Cinzel'; font-size: 0.7rem; font-weight: bold; display: block; margin-bottom: 8px;">Your Reply:</label>
                                                <textarea name="farmer_reply" style="width: 100%; height: 80px; padding: 12px; border: 1px solid #ddd; border-radius: 10px; resize: none;"><?= htmlspecialchars($fb['farmer_reply'] ?? '') ?></textarea>
                                            </div>
                                            <div style="width: 200px;">
                                                <label style="font-family: 'Cinzel'; font-size: 0.7rem; font-weight: bold; display: block; margin-bottom: 8px;">Visibility:</label>
                                                <select name="status" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                                                    <option value="Pending" <?= $fb['status'] == 'Pending' ? 'selected' : '' ?>> Pending</option>
                                                    <option value="Approved" <?= $fb['status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                                </select>
                                                <button type="submit" name="submit_action" class="action-btn" style="width: 100%; border-radius: 8px; padding: 12px;">Update Feedback</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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

    function toggleReplyForm(id) {
        const row = document.getElementById('reply-row-' + id);
        row.style.display = (row.style.display === 'none' || row.style.display === '') ? 'table-row' : 'none';
    }

</script>
</body>
</html>