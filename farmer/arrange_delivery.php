<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header("Location: manage_orders.php?error=No order selected");
    exit();
}

$stmt = $pdo->prepare("SELECT farm_name, profile_image FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$farmer = $stmt->fetch(PDO::FETCH_ASSOC); 

if (!$farmer) {
    die("Farmer profile not found.");
}

$name = $farmer['farm_name'];

try {
    $sql = "SELECT o.order_id, c.name as customer_name, d.recipient_name, d.deliveryaddress, 
                   d.city, d.postcode, d.state, d.shipping_method, 
                   d.deliverydate, d.delivery_notes
            FROM orders o
            JOIN customer c ON o.customer_id = c.customer_id
            LEFT JOIN delivery d ON o.order_id = d.order_id
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN livestock l ON oi.livestock_id = l.livestock_id
            WHERE o.order_id = :oid AND l.farmer_id = :fid
            LIMIT 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':oid' => $order_id, ':fid' => $farmer_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found or access denied.");
    }

    $stmtLogs = $pdo->prepare("SELECT * FROM delivery WHERE order_id = :oid ORDER BY created_at DESC");
    $stmtLogs->execute([':oid' => $order_id]);
    $delivery_history = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    $existing_date = !empty($delivery_history) ? $delivery_history[0]['deliverydate'] : "";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

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
    <title>Arrange Delivery | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&family=Raleway:wght@300;400;600&display=swap" rel="stylesheet">
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
            align-items: flex-start; 
            gap: 30px;              
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

        .delivery-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(15px);
            flex: 1;                
            max-width: 700px;       
            padding: 40px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .header-section { text-align: center; margin-bottom: 30px; }
        .history-sidebar {
            width: 400px; 
            background: #ffffff; 
            border-radius: 25px; 
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03); 
            border: 1px solid #eef2f6;
            position: sticky; 
            top: 40px;
            max-height: 650px; 
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        .log-header { 
            font-family: 'Cinzel', serif; 
            font-size: 1.1rem; 
            color: var(--text-main); 
            border-bottom: 2px solid #f0f4f8; 
            padding-bottom: 15px; 
            margin-bottom: 15px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .log-list-container {
            overflow-y: auto;
            flex-grow: 1;
            padding-right: 5px;
        }

        .log-list-container::-webkit-scrollbar {
            width: 5px;
        }
        .log-list-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .log-list-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .log-list-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .log-block {
            background: var(--bg-light);
            border-radius: 12px;
            border: 1px solid #eef4fc;
            padding: 12px;
            margin-bottom: 15px;
        }
        .log-block:last-child {
            margin-bottom: 5px;
        }

        .log-simple-index {
            font-size: 0.9rem;
            font-weight: 700;
            color: #a0aec0;
            min-width: 20px;
            padding-top: 1px;
        }

        .log-table-wrapper {
            flex-grow: 1;
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .log-table td {
            padding: 6px 4px;
            vertical-align: top;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .log-table tr:last-child td {
            border-bottom: none;
            padding-bottom: 0;
        }

        .log-table tr:first-child td {
            padding-top: 0;
        }

        .tbl-label {
            font-weight: 700;
            color: #78889b;
            text-transform: uppercase;
            font-size: 0.68rem;
            width: 32%;
            letter-spacing: 0.3px;
            padding-top: 8px !important;
        }

        .tbl-value {
            color: #2c3e50;
            font-weight: 600;
        }

        .tbl-value.note-text {
            font-style: italic;
            color: #555555;
            font-weight: 400;
            line-height: 1.4;
            white-space: pre-line; 
            word-break: break-word; 
        }
        
        .order-summary {
            background: rgba(25, 118, 210, 0.05);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid #1976d2;
        }

        .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 0.9rem; }
        .label { font-weight: bold; color: #555; text-transform: uppercase; font-size: 0.7rem; display: block; }
        .value { color: #333; font-family: 'PT Serif'; font-weight: bold; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #444; font-size: 0.85rem; }
        
        .input-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 10px;
            background: white;
            font-family: inherit;
            box-sizing: border-box;
        }

        .btn-submit {
            width: 100%;
            background: #1976d2;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-family: 'Cinzel';
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }

        .btn-submit:hover { background: #0d47a1; transform: translateY(-2px); }
        .back-link { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 0.8rem; }
        @media (max-width: 1024px) {
            .page-wrapper {
                flex-direction: column;
                align-items: center;
            }
            .history-sidebar {
                width: 100%;
                max-width: 700px;
                position: static; 
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
                        <li><a href="manage_order.php">Customer Order Management</a></li>
                        <li class="active">Delivery Update</li>
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
            <div class="delivery-card">
                <div class="header-section">
                    <div class="card-header-row">
                 <a href="manage_order.php" class="back-btn">
                    <i class="bi bi-arrow-left-circle-fill"></i> Back
                </a>
                    <h2 class="main-title">Delivery Update</h2>
                </div>
                    <p style="color: #666;">Order #<?= base_convert($order_id + 10485760, 10, 36) ?></p>
                </div>

                <div class="order-summary">
                    <div class="summary-grid">
                        <div>
                            <span class="label">Recipient</span>
                            <span class="value"><?= htmlspecialchars($order['recipient_name'] ?: $order['customer_name']) ?></span>
                        </div>
                        <div>
                            <span class="label">Delivery Method</span>
                            <span class="value"><?= htmlspecialchars($order['shipping_method']) ?></span>
                        </div>
                        <div style="grid-column: span 2;">
                            <span class="label">Address</span>
                            <span class="value"><?= htmlspecialchars($order['deliveryaddress']) ?>, <?= $order['postcode'] ?> <?= $order['city'] ?></span>
                        </div>
                    </div>
                </div>

                <form action="delivery_process.php" method="POST">
                    <input type="hidden" name="order_id" value="<?= $order_id ?>">

                    <div class="form-group">
                        <label>Estimated Delivery Date (Optional)</label>
                        <input type="date" name="estimated_date" class="input-control">
                    </div>

                    <div class="form-group">
                        <label>Updates for Customer</label>
                        <textarea name="delivery_notes" class="input-control" rows="4" 
                                  placeholder="e.g. Driving a white truck, will arrive before 5 PM.&#10;*(Shift + Enter) to insert new line)"><?= htmlspecialchars($order['delivery_notes'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-truck-loading"></i> CONFIRM UPDATE
                    </button>
                </form>
                <center><a href="manage_order.php" style="display:block; margin-top:20px; color:#777; font-size:0.8rem; text-decoration: none;">Back to Orders</a></center>
            </div>

           <div class="history-sidebar">
                <div class="log-header">
                    <i class="fas fa-history"></i> 
                    <span>Delivery Update Logs</span>
                </div>

                <div class="log-list-container">
                    <?php if (!empty($delivery_history)): ?>
                        <?php 
                        foreach ($delivery_history as $index => $log): 
                            $displayIndex = $index + 1; 
                            ?>
                            <div class="log-block">
                                <div class="log-index-badge"><?= $displayIndex ?></div>
                                <div class="log-table-wrapper">
                                <table class="log-table">
                                    <tbody>
                                        <tr>
                                            <td class="tbl-label">Date & Time</td>
                                            <td class="tbl-value"><?= date('d M Y • h:i A', strtotime($log['created_at'])) ?></td>
                                        </tr>
                                        
                                        <?php 
                                        $checkDate = !empty($log['deliverydate']) ? trim($log['deliverydate']) : '';
                                        if ($checkDate !== '' && $checkDate !== '0000-00-00' && $checkDate !== '1970-01-01'): 
                                        ?>
                                            <tr>
                                                <td class="tbl-label">Est. Date</td>
                                                <td class="tbl-value">
                                                    <i class="far fa-calendar-check" style="color: var(--primary-color); margin-right: 4px;"></i>
                                                    <?= date('d M Y', strtotime($log['deliverydate'])) ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>

                                        <tr>
                                            <td class="tbl-label">Updates</td>
                                            <td class="tbl-value note-text">
                                                <?= htmlspecialchars($log['delivery_notes'] ?: 'No supplemental tracking instructions specified.') ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; color: #b4c2d3; padding: 40px 10px;">
                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                            <p style="font-size: 0.85rem; margin: 0;">No previous history.</p>
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
    </script>

</body>
</html>