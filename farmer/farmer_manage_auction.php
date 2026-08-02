<?php
session_start();
if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

date_default_timezone_set('Asia/Kuala_Lumpur');
include '../db_connect.php';
include '../inc/numbers.php';
$farmer_id = $_SESSION['farmer_id'];
$now = date('Y-m-d H:i:s');

$expired_query = "UPDATE auction SET status = 'closed' WHERE status = 'active' AND end_time <= ?";
$stmt_expire = $pdo->prepare($expired_query);
$stmt_expire->execute([$now]);

$update_livestock_query = "UPDATE livestock SET availability_status = 'Unavailable' 
WHERE livestock_id IN (SELECT livestock_id FROM auction WHERE status = 'closed')";
$stmt_ls_expire = $pdo->prepare($update_livestock_query);
$stmt_ls_expire->execute();

// Fetch Farmer Name
$stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();

$query = "SELECT a.*, l.name as livestock_name, l.farmer_livestock_no, l.image, 
          COUNT(b.bid_id) as total_bids
          FROM auction a 
          JOIN livestock l ON a.livestock_id = l.livestock_id 
          LEFT JOIN bidding b ON a.livestock_id = b.livestock_id
          WHERE l.farmer_id = :fid 
          AND a.status NOT IN ('closed', 'completed') 
          GROUP BY a.auction_id, l.name, l.farmer_livestock_no, l.image 
          ORDER BY a.start_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([':fid' => $farmer_id]);
$auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Livestock Auctions Management | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">
    <style>
        .page-wrapper { max-width: 1300px; margin: 40px auto; padding: 0 20px; }

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
            background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(15px);
            padding: 40px; border-radius: 30px; border: 1px solid rgba(144, 202, 249, 0.4);
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
        }

            /*h2 { font-family: 'Cinzel', serif; border-bottom: 2px solid #1976d2; padding-bottom: 10px; margin-bottom: 20px; }*/
            
        /*.auction-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .auction-table th { text-align: left; padding: 15px; background: #f8f9fa; font-family: 'Cinzel', serif; font-size: 0.8rem; }
        .auction-table td { padding: 15px; border-bottom: 1px solid #eee; }*/
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .active { background: #e8f5e9; color: #2e7d32; }
        .closed { background: #ffebee; color: #c62828; }
        .pending { background: #fff3e0; color: #ef6c00; }
        
        .btn-action { padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 0.8rem; font-weight: bold; transition: 0.3s; display: inline-flex; align-items: center; gap: 5px; border: none; }
        .btn-start { background: #2d5a27; color: white; }
        .btn-end { background: #d32f2f; color: white; } 
        .btn-edit { background: #1976d2; color: white; }
        .button-container {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 12px;           
            margin-bottom: 20px; 
        }

        .btn-history {
            box-shadow: 0 4px 15px rgba(45, 90, 39, 0.2);
            border-radius: 50px;
            background: #607d8b;
            color: white;
            padding: 10px 20px; 
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: bold;
            display: inline-flex;  
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }
        .btn-history:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        .btn-new {
            box-shadow: 0 4px 15px rgba(45, 90, 39, 0.2);
            border-radius: 50px;
            background: #2d5a27;
            color: white;
            padding: 10px 20px; 
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: bold;
            display: inline-flex;  
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }
        .btn-new:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: bold; }
        .modern-table th { font-family: 'Cinzel', serif; color: #1976d2; font-size: 0.75rem; text-transform: uppercase; padding: 10px; text-align: center; }
        .auction-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; }
        .auction-table th { font-family: 'Cinzel', serif; color: #1976d2; font-size: 0.75rem; text-transform: uppercase; padding: 10px; text-align: center; }
        .auction-table td { background: white; padding: 15px; text-align: center; border-top: 1px solid rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.02); }
        .auction-table tr td:first-child { border-left: 1px solid rgba(0,0,0,0.02); border-radius: 15px 0 0 15px; }
        .auction-table tr td:last-child { border-right: 1px solid rgba(0,0,0,0.02); border-radius: 0 15px 15px 0; }
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
                <a onclick="toggleSubmenu(this)"class="active">
                    <i class="fas fa-gavel"></i> <span>Livestock Auctions</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="submenu">
                    <li><a href="farmer_manage_auction.php" class="active"><i class="fas fa-gavel"></i> Manage Auctions</a></li>
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
                        <li class="active">Livestock Auctions Management</li>
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
                    <h2 class="main-title">Livestock Auctions</h2>
                    <div style="width: 85px;"></div> 
                </div>
                <div class="button-container">
                    <a href="farmer_auction_history.php" class="btn-history">
                        <i class="fas fa-history"></i> View Auction History
                    </a>
                    <a href="create_auction.php" class="btn-new">
                        <i class="fas fa-plus"></i> Create New Auction
                    </a>
                </div>

                <?php if(isset($_SESSION['msg'])): ?>
                    <div class="alert" style="background: #e8f5e9; color: #2e7d32;"><?= $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
                <?php endif; ?>
                <div id="auctionTableContainer">

                    <table class="auction-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Auction ID</th>
                                <th>Livestock Info</th>
                                <th>Schedule</th>
                                <th>Price</th>
                                <th>Bids</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($auctions)): ?>
                                <tr>
                                    <td colspan="8" style="padding: 80px 20px; text-align: center; background: rgba(255,255,255,0.4);">
                                        <div style="max-width: 400px; margin: 0 auto;">
                                            <div style="position: relative; display: inline-block; margin-bottom: 20px;">
                                                <i class="fas fa-gavel" style="font-size: 4rem; color: #d1d5db;"></i>
                                                <i class="fas fa-search" style="position: absolute; bottom: 0; right: -5px; font-size: 1.5rem; color: #1976d2; background: white; border-radius: 50%; padding: 5px;"></i>
                                            </div>
                                            
                                            <h3 style="font-family: 'Cinzel', serif; color: #4b5563; margin-bottom: 10px; letter-spacing: 1px;">
                                                No Auctions Found
                                            </h3>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php 
                            $i = 1;
                            foreach ($auctions as $row): 
                                $isExpired = strtotime($row['end_time']) <= strtotime($now); 
                                ?>
                                <tr>
                                    <td>
                                        <span style="color: #888; font-size: 15px; font-weight: bold;"><?= $i++ ?>.</span>
                                    </td>
                                    <td>
                                        <span style="font-weight: bold; color: #777;">
                                            <?= formatAuctionID($row['auction_id']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 15px; text-align: left;">
                                            <?php 
                                            $rawImageValue = !empty($row['image']) ? $row['image'] : "";
                                            $imageArray = explode(',', $rawImageValue);
                                            $firstImage = trim($imageArray[0]);

                                            if (!empty($firstImage) && file_exists("uploads/" . $firstImage)) {
                                                $lsImage = "uploads/" . $firstImage;
                                            } else {
                                                $lsImage = "uploads/default_livestock.png";
                                            }
                                            ?>
                                            <img src="<?= $lsImage ?>" 
                                            style="width: 60px; height: 60px; object-fit: cover; border-radius: 10px; border: 1px solid #eee; flex-shrink: 0;">
                                            <div>
                                                <strong style="display: block; font-size: 14px;"><?= htmlspecialchars($row['title']) ?></strong>
                                                <small style="color: #666;">Livestock: <?= htmlspecialchars($row['livestock_name']) ?></small><br>

                                                <?php if (!empty($row['farmer_livestock_no'])): ?>
                                                    <small style="background: #fff3e0; color: #e65100; padding: 2px 8px; border-radius: 4px; font-size: 11px; border: 1px solid #ffe0b2; display: inline-block; margin-top: 5px; font-weight: 600;">
                                                        <i class="fas fa-tag"></i> No: <?= htmlspecialchars($row['farmer_livestock_no']) ?>
                                                    </small>
                                                <?php else: ?>
                                                    <small style="color: #999; font-style: italic; font-size: 10px;">No tag assigned</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small>Start: <?= date('d M, H:i', strtotime($row['start_time'])) ?></small><br>
                                        <small style="<?= $isExpired ? 'color: #d32f2f; font-weight: bold;' : '' ?>">
                                            End: <?= date('d M, H:i', strtotime($row['end_time'])) ?>
                                            <?php if($isExpired && $row['status'] !== 'active'): ?>
                                                <br>(Expired - Edit Required)
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        Start: RM <?= number_format($row['starting_price'], 2) ?><br>
                                        <strong>Bid: RM <?= number_format($row['current_bid'] ?? 0, 2) ?></strong>
                                    </td>
                                    <td>
                                        <div style="background: #f1f5f9; padding: 5px 10px; border-radius: 8px; display: inline-block;">
                                            <i class="fas fa-gavel" style="color: #64748b; font-size: 12px;"></i>
                                            <span style="font-weight: 700; color: #1e293b;"><?= $row['total_bids'] ?></span>
                                            <small style="display: block; font-size: 10px; color: #94a3b8; text-transform: uppercase;">Total Bids</small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'closed'): ?>
                                            <span class="status-badge closed">CLOSED</span>
                                        <?php else: ?>
                                            <span class="status-badge <?= strtolower($row['status']) ?>">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <?php 
                                            $isExpired = strtotime($row['end_time']) <= strtotime($now); 
                                            ?>

                                            <?php if ($row['status'] !== 'active'): ?>
                                                <?php if ($isExpired): ?>
                                                    <button class="btn-action" style="background: #ccc; color: #666; cursor: not-allowed;" 
                                                    title="End time has passed. Please edit the schedule first.">
                                                    <i class="fas fa-lock"></i> Start
                                                </button>
                                            <?php else: ?>
                                                <a href="update_auction_status.php?id=<?= $row['auction_id'] ?>&status=active" 
                                                 class="btn-action btn-start" 
                                                 onclick="return confirm('Start this auction now?')">
                                                 <i class="fas fa-play"></i> Start
                                             </a>
                                         <?php endif; ?>
                                     <?php endif; ?>

                                     <?php if ($row['status'] === 'active'): ?>
                                        <a href="farmer_close_auction.php?auction_id=<?= $row['auction_id'] ?>&status=closed" 
                                         class="btn-action btn-end" onclick="return confirm('Are you sure you want to CLOSE this auction? Bidding will stop immediately.')">
                                         <i class="fas fa-times-circle"></i> Close
                                     </a>
                                 <?php endif; ?>

                                 <a href="farmer_edit_auction.php?id=<?= $row['auction_id'] ?>" class="btn-action btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="view_auction_bids.php?id=<?= $row['auction_id']; ?>" class="btn-action"  style="background: #607d8b; color: white;">
                                    <i class="fas fa-list-ul"></i> Bids
                                </a>
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

    function loadAuctionData() {
        fetch('fetch_auctions.php')
        .then(response => {
            if (!response.ok) throw new Error('File not found');
            return response.text();
        })
        .then(data => {
            document.getElementById('auctionTableBody').innerHTML = data;
        })
        .catch(error => console.error('Error fetching auctions:', error));
    }

    setInterval(loadAuctionData, 5000);
</script>
</body>
</html>