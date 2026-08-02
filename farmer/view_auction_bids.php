<?php
session_start();
require_once '../db_connect.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$auction_id = $_GET['id'] ?? null;
$farmer_id = $_SESSION['farmer_id'];

if (!$auction_id) { die("Auction ID missing."); }

$stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();

try {
    $stmt = $pdo->prepare("SELECT a.*, l.name as livestock_name, l.farmer_livestock_no 
                           FROM auction a 
                           JOIN livestock l ON a.livestock_id = l.livestock_id 
                           WHERE a.auction_id = ?");
    $stmt->execute([$auction_id]);
    $auction = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Bid History
    $stmt_bids = $pdo->prepare("
        SELECT b.*, 
        c.name as customer_name,
        b.created_at AT TIME ZONE 'Asia/Kuala_Lumpur' as localized_bid_time
        FROM bidding b 
        LEFT JOIN customer c ON b.customer_id = c.customer_id
        WHERE b.livestock_id = ? 
        ORDER BY b.current_bid DESC
        ");
    $stmt_bids->execute([$auction['livestock_id']]);
    $bid_history = $stmt_bids->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
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
    <title>View Bids | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">
    <style>
        
        .page-wrapper { max-width: 1300px; margin: 40px auto; padding: 0 20px; }

        ..breadcrumb-wrapper {
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
            background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(15px);
            padding: 40px; border-radius: 30px; border: 1px solid rgba(144, 202, 249, 0.4);
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
        }

        .auction-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; }
        .auction-table th { 
            font-family: 'Cinzel', serif; color: #1976d2; font-size: 0.75rem; 
            text-transform: uppercase; padding: 10px; text-align: center; 
        }
        .auction-table td { 
            background: white; padding: 20px; text-align: center; 
            border-top: 1px solid rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.02); 
        }
        .auction-table tr td:first-child { border-left: 1px solid rgba(0,0,0,0.02); border-radius: 15px 0 0 15px; }
        .auction-table tr td:last-child { border-right: 1px solid rgba(0,0,0,0.02); border-radius: 0 15px 15px 0; }

        .status-badge { 
            padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; 
            font-weight: bold; text-transform: uppercase; 
        }
        .active { background: #e8f5e9; color: #2e7d32; }
        .bid-price { color: #2e7d32; font-weight: bold; font-size: 1.1rem; }
        
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; color: #1976d2; font-family: 'Cinzel';
            font-weight: bold; margin-bottom: 20px; transition: 0.3s;
        }
        .btn-back:hover { color: #0d1b2a; }
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
                        <li><a href="farmer_manage_auction.php">Auctions Management</a></li>
                        <li class="active">Auction Logs</li>
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
        <h2 class="main-title">Auction Logs</h2>
    </div>
        
        <div style="text-align: center; margin-bottom: 30px;">
            <h3 style="margin: 0; font-family: 'PT Serif'; color: #453c34;">
                <?= htmlspecialchars($auction['title']) ?>
            </h3>
            <small style="background: #fff3e0; color: #e65100; padding: 4px 12px; border-radius: 4px; font-weight: 600; display: inline-block; margin-top: 10px;">
                <i class="fas fa-tag"></i> Livestock No: <?= htmlspecialchars($auction['farmer_livestock_no'] ?? 'N/A') ?>
            </small>
        </div>

        <table class="auction-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Bidder Name</th>
                    <th>Bid Amount</th>
                    <th>Bid Status</th>
                    <th>Time of Bid</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($bid_history)): ?>
                    <?php foreach ($bid_history as $index => $bid): ?>
                    <tr>
                        <td>
                            <span style="color: #888; font-weight: bold;"><?= $index + 1 ?></span>
                        </td>
                        <td>
                            <strong style="color: #0d1b2a;"><?= htmlspecialchars($bid['customer_name'] ?? 'Guest Bidder') ?></strong>
                        </td>
                        <td>
                            <span class="bid-price">RM <?= number_format($bid['current_bid'], 2) ?></span>
                        </td>
                        <td>
                            <span class="status-badge <?= ($index === 0) ? 'active' : '' ?>">
                                <?= ($index === 0) ? 'Highest Bid' : 'Outbid' ?>
                            </span>

                            <?php if ($index === 0 && $auction['status'] === 'closed'): ?>
                                <?php 
                                $stmtCheck = $pdo->prepare("
                                    SELECT winner_id 
                                    FROM bidding 
                                    WHERE livestock_id = ? 
                                    AND customer_id = ? 
                                    AND winner_id IS NOT NULL 
                                    LIMIT 1
                                    ");
                                $stmtCheck->execute([$auction['livestock_id'], $bid['customer_id']]);
                                $isThisWinnerApproved = $stmtCheck->fetch();
                                ?>

                                <?php if (!$isThisWinnerApproved): ?>
                                    <div style="margin-top: 10px; display: flex; gap: 5px; justify-content: center;">
                                        <form action="approve_bidder.php" method="POST">
                                            <input type="hidden" name="auction_id" value="<?= $auction_id ?>">
                                            <input type="hidden" name="winner_id" value="<?= $bid['customer_id'] ?>">
                                            <button type="submit" style="background:#2e7d32; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>

                                        <button onclick="rejectWithReason(<?= $bid['customer_id'] ?>)" style="background:#c62828; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #2e7d32; font-weight: bold; font-size: 0.8rem;">
                                        <i class="fas fa-check-circle"></i> APPROVED
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td style="color: #777; font-size: 0.85rem;">
                            <i class="far fa-clock"></i> 
                            <?php 
                            $rawTime = $bid['localized_bid_time'] ?? $auction['start_time'];

                            if ($rawTime) {
                                echo date('d M Y, h:i A', strtotime($rawTime));
                            } else {
                                echo "N/A";
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding: 50px; color: #999;">
                            <i class="fas fa-info-circle"></i> No bidding activity recorded for this auction yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="farmer_manage_auction.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Return to List
            </a>
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

    function rejectWithReason(customerId) {
        const reason = prompt("Enter the reason for rejection (Required):");

        if (reason === null) return; 

        if (reason.trim() === "") {
            alert("You must provide a reason to reject the bidder.");
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'reject_bidder.php';

        const fields = {
            'auction_id': '<?= $auction_id ?>',
            'customer_id': customerId,
            'reason': reason
        };

        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }
</script>

</body>
</html>