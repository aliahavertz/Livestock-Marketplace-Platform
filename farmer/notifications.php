<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['farmer_id'])) { 
    header("Location: ../Models/farmer_login.php"); 
    exit(); 
}

$farmer_id = $_SESSION['farmer_id'];
$type = 'farmer'; 
$dashboard_link = "farmer_dashboard.php";

$stmt = $pdo->prepare("SELECT name, profile_image, farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$farmer = $stmt->fetch(PDO::FETCH_ASSOC); 

$farmerName = $farmer['farm_name'] ?? 'Farmer';
$imageFolder = "uploads/";

if (!empty($farmer['profile_image']) && file_exists($imageFolder . $farmer['profile_image'])) {
    $imagePath = $imageFolder . $farmer['profile_image'];
} else {
    $imagePath = $imageFolder . "default.png";
}


$filter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'today';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$order = ($sort === 'oldest') ? 'ASC' : 'DESC';

$where_clause = "user_id = :uid AND user_type = :type";
$params = ['uid' => $farmer_id, 'type' => $type];

switch ($filter) {
    case 'today':
        $where_clause .= " AND created_at >= CURRENT_DATE";
        break;
    case '2_days':
        $where_clause .= " AND created_at >= NOW() - INTERVAL '2 days'";
        break;
    case '7_days':
        $where_clause .= " AND created_at >= NOW() - INTERVAL '7 days'";
        break;
    case 'older':
        $where_clause .= " AND created_at < NOW() - INTERVAL '7 days'";
        break;
    case 'all':
    default:
        break;
}

if (isset($_POST['clear_all'])) {
    $pdo->prepare("DELETE FROM notifications WHERE $where_clause")
        ->execute($params);
    header("Location: notifications.php?time_filter=" . urlencode($filter));
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE $where_clause ORDER BY created_at $order");
$stmt->execute($params);
$notifications = $stmt->fetchAll();

function time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    $minutes = round($seconds / 60);           
    $hours   = round($seconds / 3600);         
    $days    = round($seconds / 86400);        
    if ($seconds <= 60) return "Just Now";
    else if ($minutes <= 60) return ($minutes == 1) ? "1 min ago" : "$minutes mins ago";
    else if ($hours <= 24) return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    else if ($days <= 7) return ($days == 1) ? "Yesterday" : "$days days ago";
    else return date('d M Y', $time_ago);
}

$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND user_type = 'farmer' AND is_read = FALSE");
$stmtUnread->execute(['uid' => $farmer_id]);
$unreadCount = $stmtUnread->fetchColumn();

// Mark as Read
$pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = :uid AND user_type = :type")
    ->execute(['uid' => $farmer_id, 'type' => $type]);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&family=Raleway:wght@600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">
    <style>
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
            letter-spacing: 1px;
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

        .notif-card { 
            background: var(--white); 
            border-radius: 10px;
            padding: 12px 18px;  
            border: 1px solid rgba(144, 202, 249, 0.3); 
            margin-bottom: 10px; 
            display: flex; 
            gap: 12px;           
            transition: 0.3s; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            max-width: 900px;     
            width: 100%;          
            margin-left: 0;
        }
        .section-header, .breadcrumb-wrapper {
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .notif-card.unread { border-left: 4px solid #1976d2; }
        .notif-icon { 
            background: #e3f2fd; 
            color: #1976d2; 
            width: 35px;         
            height: 35px;        
            border-radius: 8px;  
            display: flex; 
            align-items: center; 
            justify-content: center; 
            flex-shrink: 0; 
            font-size: 14px;     
        }

        .notif-content div:first-child {
            font-size: 14px;     
            font-weight: bold;
            font-family: 'Cinzel', serif;
        }

        .notif-content div:nth-child(2) {
            font-size: 13px;     
            color: #453c34; 
            margin: 2px 0;      
        }

        .notif-content div:last-child {
            font-size: 10px;    
            color: #888;
        }
        .section-header { 
            max-width: 900px;     
            margin-left: 0;       
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
        }        
        .btn-clear { background: transparent; border: 1px solid #d32f2f; color: #d32f2f; padding: 6px 15px; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: bold; }
        .btn-clear:hover { background: #d32f2f; color: #fff; }

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
            border: 1px solid rgba(0, 0, 0, 0.1); 
            padding: 6px 30px 6px 12px; 
            border-radius: 20px;
            font-family: 'PT Serif', serif;
            font-size: 0.85rem;
            color: #444;
            cursor: pointer;
            transition: all 0.3s ease;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23444' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            margin: 0; 
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
                <h4 class="farmer-name"><?php echo htmlspecialchars($farmerName); ?></h4>
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
                        <li class="active">Notifications</li>
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

        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; max-width: 900px; margin-bottom: 25px;">
            <h2 style="font-family:'Cinzel'; margin: 0;">Notifications</h2>

            <div style="display: flex; align-items: center; gap: 15px;">

                <form method="GET" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                    <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                        <i class="fas fa-filter" style="font-size: 0.8rem; color: #0d1b2a;"></i>
                        <p style="margin: 0; font-family: 'Cinzel', serif; font-size: 0.9rem; font-weight: 600; color: #0d1b2a;">SORT BY:</p>
                    </div>
                    <select name="time_filter" class="date-select" onchange="this.form.submit()">
                        <option value="today" <?= $filter == 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="2_days" <?= $filter == '2_days' ? 'selected' : '' ?>>Past 2 Days</option>
                        <option value="7_days" <?= $filter == '7_days' ? 'selected' : '' ?>>This Week</option>
                        <option value="older" <?= $filter == 'older' ? 'selected' : '' ?>>Older</option>
                        <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All</option>
                    </select>
                </form>

                <!-- <?php if (count($notifications) > 0): ?>
                    <form method="POST" onsubmit="return confirm('Clear filtered notifications?');" style="margin: 0; display: flex; align-items: center;">
                        <button type="submit" name="clear_all" class="btn-clear">CLEAR ALL</button>
                    </form>
                <?php endif; ?> -->

            </div> 
        </div> 

        <?php if ($notifications): foreach ($notifications as $n): ?>
            <div class="notif-card <?= $n['is_read'] ? '' : 'unread' ?>">
                <div class="notif-icon">
                    <?php 
                    $msg = strtolower($n['message']);
                    $icon = 'fa-bell'; 
                    
                    if (strpos($msg, 'approve') !== false) {
                        $icon = 'fa-user-check'; 
                        $icon_color = '#f39c12'; 
                    } elseif (strpos($msg, 'paid') !== false) {
                        $icon = 'fa-money-bill-wave';
                        $icon_color = '#27ae60';
                    }
                    ?>
                    <i class="fas <?= $icon ?>" style="color: <?= $icon_color ?? '#1976d2' ?>;"></i>
                </div>
                <div class="notif-content">
                    <div style="font-weight:bold; font-family:'Cinzel';"><?= htmlspecialchars($n['title']) ?></div>
                    <div style="font-size:14px; color:#453c34; margin:5px 0;"><?= htmlspecialchars($n['message']) ?></div>
                    <div style="font-size:11px; color:#888;"><i class="far fa-clock"></i> <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></div>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div style="text-align:center; padding:50px; border:2px dashed #ccc; border-radius:20px;">
                <p style="font-family:'Cinzel'; color:#888;">No notifications found.</p>
            </div>
        <?php endif; ?>
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
    </script>
</body>
</html>