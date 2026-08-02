<?php
session_start();
require_once '../db_connect.php';
include '../inc/numbers.php';

if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../Models/farmer_login.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

// Fetch Farmer Name
$stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();

$sql_rev = "SELECT 
            SUM(CASE 
                WHEN p.payment_status = 'paid' 
                AND LOWER(TRIM(o.status)) NOT IN ('refunded', 'terminated', 'cancelled') 
                THEN (p.amount + COALESCE(ad.amount, 0)) 
                ELSE 0 END) as total_calculated_rev
            FROM payments p
            JOIN orders o ON p.order_id = o.order_id
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN livestock l ON oi.livestock_id = l.livestock_id
            LEFT JOIN auction a ON l.livestock_id = a.livestock_id
            LEFT JOIN auction_deposits ad ON a.auction_id = ad.auction_id
            WHERE l.farmer_id = :fid"; 

$stmt_rev = $pdo->prepare($sql_rev);
$stmt_rev->execute(['fid' => $farmer_id]);
$total_revenue = $stmt_rev->fetchColumn() ?: 0;

$sql_avail = "SELECT COUNT(*) FROM livestock WHERE farmer_id = :fid AND availability_status IN ('Available', 'In Auction')";
$stmt_avail = $pdo->prepare($sql_avail);
$stmt_avail->execute(['fid' => $farmer_id]);
$available_livestock_count = $stmt_avail->fetchColumn() ?: 0;

$sql_pend = "SELECT COUNT(*) FROM orders o 
             JOIN livestock l ON o.livestock_id = l.livestock_id 
             WHERE l.farmer_id = :fid AND o.status IN ('Pending', 'Preparing', 'pending', 'preparing')";
$stmt_pend = $pdo->prepare($sql_pend);
$stmt_pend->execute(['fid' => $farmer_id]);
$pending_count = $stmt_pend->fetchColumn() ?: 0;

$sql_upcoming = "SELECT o.order_id, o.total_price, c.name as customer_name 
                 FROM orders o 
                 JOIN customer c ON o.customer_id = c.customer_id
                 JOIN order_items oi ON o.order_id = oi.order_id
                 JOIN livestock l ON oi.livestock_id = l.livestock_id
                 WHERE l.farmer_id = :fid
                 AND o.status IN ('Pending', 'Preparing', 'pending', 'preparing')
                 ORDER BY o.order_date DESC LIMIT 3";

$stmt_upcoming = $pdo->prepare($sql_upcoming);
$stmt_upcoming->execute(['fid' => $farmer_id]);
$upcoming_orders = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);

$sql_auctions = "SELECT a.auction_id, a.title, a.end_time, l.name as livestock_name,
                 COALESCE(
                    (SELECT MAX(current_bid) FROM bidding WHERE livestock_id = l.livestock_id), 
                    a.starting_price, 0
                 ) as current_bid,
                 (SELECT COUNT(*) FROM bidding WHERE livestock_id = l.livestock_id) as total_bids
                 FROM auction a
                 JOIN livestock l ON a.livestock_id = l.livestock_id
                 WHERE l.farmer_id = :fid AND a.status = 'active' AND a.end_time > NOW()
                 ORDER BY a.end_time ASC LIMIT 3";
$stmt_auctions = $pdo->prepare($sql_auctions);
$stmt_auctions->execute(['fid' => $farmer_id]);
$live_auctions = $stmt_auctions->fetchAll(PDO::FETCH_ASSOC);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Raleway:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">

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
                <a href="farmer_dashboard.php" class="active">
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
            <div class="header-left">
                <!-- <button class="toggle-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button> -->
                <h2>Overview</h2>
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

        <div class="stats-row">
            <div class="stat-item rev">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <div>
                    <p class="stat-label">Total Revenue</p>
                    <h3 class="stat-value">RM <?= number_format($total_revenue, 2) ?></h3>
                </div>
            </div>
            <div class="stat-item livestock">
                <div class="stat-icon" style="background:#ACC7A9; color:#2d5a27;"><i class="fas fa-horse-head"></i></div>
                <div>
                    <p class="stat-label">Available Livestock</p>
                    <h3 class="stat-value"><?= $available_livestock_count ?> Livestock</h3>
                </div>
            </div>
            <div class="stat-item order">
                <div class="stat-icon" style="background:#EDD5B7; color:#fb8c00;"><i class="fas fa-truck-loading"></i></div>
                <div>
                    <p class="stat-label">Pending Orders</p>
                    <h3 class="stat-value"><?= $pending_count ?> Items</h3>
                </div>
            </div>
        </div>

        <div class="dashboard-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            
            <div class="table-card" style="background: #fff; padding: 20px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="color:#1976d2;"><i class="fas fa-clock text-warning" style="color:#1976d2;"></i> Pending Orders</h3>
                    <a href="manage_order.php" style="font-size: 12px; color: #1976d2;">View All</a>
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tr style="border-bottom: 1px solid #eee; text-align: left; color: #888;">
                        <th style="padding: 10px 0;">Order Number</th>
                        <th>Customer</th>
                        <th>Amount</th>
                    </tr>
                    <?php if (empty($upcoming_orders)): ?>
                        <tr><td colspan="3" style="padding: 20px; text-align: center; color: #ccc;">No pending orders</td></tr>
                    <?php else: ?>
                        <?php foreach ($upcoming_orders as $order): ?>
                        <tr style="border-bottom: 1px solid #f9f9f9;">
                            <td style="padding: 12px 0;"><?= formatOrderNumber($order['order_id']) ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><strong>RM <?= number_format($order['total_price'], 2) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Live Auctions -->
            <div class="table-card" style="background: #fff; padding: 20px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="color:#1976d2;"><i class="fas fa-gavel text-danger" style="color:#1976d2;"></i> Live Bids</h3>
                    <a href="farmer_manage_auction.php" style="font-size: 12px; color: #1976d2;">Manage</a>
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tr style="border-bottom: 1px solid #eee; text-align: left; color: #888;">
                        <th style="padding: 10px 0;">Auction Item</th>
                        <th>Current Bid</th>
                        <th>Total Bids</th>
                        <th>Ends In</th>
                    </tr>
                    <?php if (empty($live_auctions)): ?>
                        <tr><td colspan="3" style="padding: 20px; text-align: center; color: #ccc;">No active auctions</td></tr>
                    <?php else: ?>
                        <?php foreach ($live_auctions as $auction): ?>
                        <tr style="border-bottom: 1px solid #f9f9f9;">
                            <td style="padding: 12px 0;"><?= htmlspecialchars($auction['livestock_name']) ?></td>
                            <td style="color: #27ae60;"><strong>RM <?= number_format($auction['current_bid'] ?? 0, 2) ?></strong></td>
                            <td>
                                <span style="background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: bold;">
                                    <?= $auction['total_bids'] ?> bids
                                </span>
                            </td>
                            <td><span style="background: #fff0f0; color: #e74c3c; padding: 2px 8px; border-radius: 10px; font-size: 11px;">
                                <?= date('H:i', strtotime($auction['end_time'])) ?>
                            </span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <header><h3>Quick Actions</h3></header>
        
        <div class="action-grid">
            <a href="add_livestock.php" class="action-card">
                <i class="fas fa-plus-circle"></i>
                <strong>Add Listing</strong>
                <span>New Livestock</span>
            </a>
            <a href="farmer_manage_auction.php" class="action-card">
                <i class="fas fa-gavel"></i>
                <strong>Auctions</strong>
                <span>Live Bids</span>
            </a>
            <a href="manage_feedback.php" class="action-card">
                <i class="fas fa-star"></i>
                <strong>Reviews</strong>
                <span>Feedbacks</span>
            </a>
            <a href="view_livestock.php" class="action-card">
                <i class="fas fa-layer-group"></i>
                <strong>Inventory</strong>
                <span>Stock Check</span>
            </a>
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
    </script>
</body>
</html>