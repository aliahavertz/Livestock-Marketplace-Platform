<?php
session_start();
require_once '../db_connect.php';
include '../inc/numbers.php';

$order_id = $_GET['order_id'] ?? null;
$farmer_id = $_SESSION['farmer_id'];

$stmt = $pdo->prepare("
    SELECT 
        o.*, 
        c.name as customer_name,
        oi.item_status 
    FROM orders o
    JOIN customer c ON o.customer_id = c.customer_id
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.order_id = ?
    LIMIT 1
");
$stmt->execute([$order_id]);
$refund = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refund) {
    die("Refund request not found.");
}

$stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();

$current_status = strtolower($refund['item_status'] ?? '');
$can_decide = ($current_status === 'refund-requested');

$back_url = 'manage_payments.php'; 

if (isset($_SERVER['HTTP_REFERER'])) {
    $back_url = $_SERVER['HTTP_REFERER'];
}

$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND user_type = 'farmer' AND is_read = FALSE");
$stmtUnread->execute(['uid' => $farmer_id]);
$unreadCount = $stmtUnread->fetchColumn();

$imageFolder = "uploads/";
$imagePath = (!empty($farmer['profile_image']) && file_exists($imageFolder . $farmer['profile_image'])) 
? $imageFolder . $farmer['profile_image'] 
: $imageFolder . "default.png";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Review Refund Evidence | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">
    <style>
        .page-wrapper {
            max-width: 100%;
            overflow: hidden; 
            padding: 0 20px;
        }
        
        .breadcrumb-wrapper {
            max-width: 850px;
            margin: 20px auto 10px; 
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
        .evidence-card { 
            max-width: 800px; margin: 0 auto; background: white; 
            padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }
        h2 { font-family: 'Cinzel', serif; color: #0d1b2a; border-bottom: 2px solid #1976d2; padding-bottom: 10px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .label { font-weight: bold; color: #666; font-size: 0.9rem; }
        .value { color: #1a1a1a; font-size: 1.1rem; margin-top: 5px; }
        .evidence-img { width: 100%; max-height: 500px; object-fit: contain; border-radius: 10px; border: 1px solid #ddd; margin-top: 10px; }
        .btn-group { 
            display: flex; 
            gap: 15px; 
            margin-top: 30px; 
            padding-top: 20px;
            border-top: 1px dashed #ddd; 
        }

        .btn { 
            padding: 14px 25px; 
            border-radius: 12px; 
            border: none; 
            font-weight: bold; 
            cursor: pointer; 
            flex: 1; 
            font-family: 'Cinzel', serif; 
            font-size: 0.9rem;
            letter-spacing: 1px;
            transition: all 0.3s ease; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-group form {
            flex: 1; 
            display: flex;
        }

        .btn-group .btn {
            width: 100%; 
            justify-content: center;
        }

        .status-banner {
            margin-top: 30px;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .banner-approved { background: #e8f5e9; color: #2e7d32; border: 1px solid #2e7d32; }
        .banner-rejected { background: #ffebee; color: #c62828; border: 1px solid #c62828; }

        .btn-approved:hover { 
            background: #1b5e20; 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.3);
        }

        .btn-rejected:hover { 
            background: #c62828; 
            color: white;
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
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

<div class="evidence-card">
    <h2>Refund Request: Order #<?= formatOrderNumber($refund['order_id']) ?></h2>
    
    <div class="info-grid">
        <div>
            <div class="label">Customer Name</div>
            <div class="value"><?= htmlspecialchars($refund['customer_name']) ?></div>
        </div>
        <div>
            <div class="label">Reason Category</div>
            <div class="value" style="color: #d32f2f;"><?= htmlspecialchars($refund['refund_reason']) ?></div>
        </div>
    </div>

    <div class="label">Customer Notes:</div>
    <div class="value" style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
        <?= nl2br(htmlspecialchars($refund['refund_notes'])) ?>
    </div>

    <div class="label" style="margin-top: 20px;">Evidence Photo:</div>
    <img src="../uploads/refunds/<?= $refund['refund_evidence_image'] ?>" class="evidence-img" alt="Refund Evidence">

    <?php if ($can_decide): ?>
        <div class="btn-group">
            <form action="process_refund_decision.php" method="POST" style="flex: 1;">
                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                <input type="hidden" name="decision" value="Approved">
                <button type="submit" class="btn btn-approve">Confirm Refund</button>
            </form>

            <form action="process_refund_decision.php" method="POST" style="flex: 1;">
                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                <input type="hidden" name="decision" value="Rejected">
                <button type="submit" class="btn btn-reject">Decline Refund</button>
            </form>
        </div>
    <?php else: ?>
        <?php if ($current_status === 'refunded'): ?>
            <div class="status-banner banner-approved">
                <i class="fas fa-check-circle"></i> THIS REFUND HAS BEEN APPROVED
            </div>
        <?php elseif ($current_status === 'rejected'): ?>
            <div class="status-banner banner-rejected">
                <i class="fas fa-times-circle"></i> THIS REFUND REQUEST WAS DECLINED
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <p style="text-align: center; margin-top: 20px;">
        <a href="<?= htmlspecialchars($back_url) ?>" style="color: #666; text-decoration: none;">← Back</a>
    </p>
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