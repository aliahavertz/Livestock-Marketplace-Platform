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

$stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();

$now = date('Y-m-d H:i:s');

$expired_query = "UPDATE auction 
                  SET status = 'closed' 
                  WHERE status IN ('active', 'expired') 
                  AND end_time <= ?";
$pdo->prepare($expired_query)->execute([$now]);

$start_filter = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_filter = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$where_clauses = ["l.farmer_id = :fid", "a.status IN ('closed', 'completed')"];
$params = [':fid' => $farmer_id];

if (!empty($start_filter)) {
    $where_clauses[] = "a.end_time >= :start_filter";
    $params[':start_filter'] = $start_filter . " 00:00:00";
}
if (!empty($end_filter)) {
    $where_clauses[] = "a.end_time <= :end_filter";
    $params[':end_filter'] = $end_filter . " 23:59:59";
}

$where_sql = implode(" AND ", $where_clauses);

$count_query = "SELECT COUNT(DISTINCT a.auction_id) 
                FROM auction a 
                JOIN livestock l ON a.livestock_id = l.livestock_id 
                WHERE $where_sql";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$query = "SELECT a.*, l.name as livestock_name, l.farmer_livestock_no, l.image, 
          COUNT(b.bid_id) as total_bids
          FROM auction a 
          JOIN livestock l ON a.livestock_id = l.livestock_id 
          LEFT JOIN bidding b ON a.livestock_id = b.livestock_id
          WHERE $where_sql
          GROUP BY a.auction_id, l.name, l.farmer_livestock_no, l.image 
          ORDER BY a.end_time DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT * FROM farmer WHERE farmer_id = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id', $farmer_id, PDO::PARAM_INT);
$stmt->execute();
$farmer = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>Auction History | RanchLink</title>
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
        .glass-card { background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(15px); padding: 40px; border-radius: 30px; border: 1px solid rgba(144, 202, 249, 0.4); }
        .auction-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; }
        .auction-table th { color: #1976d2; font-size: 0.75rem; text-transform: uppercase; padding: 10px; text-align: center; }
        .auction-table td { background: white; padding: 15px; text-align: center; border-radius: 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee; }
        .status-badge.closed { background: #ffebee; color: #c62828; padding: 5px 12px; border-radius: 20px; font-weight: bold; }
        .status-badge.completed { background: #e8f5e9; color: #2e7d32; padding: 5px 12px; border-radius: 20px; font-weight: bold; }

        .filter-container { background: white; padding: 20px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .filter-form { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-family: 'Cinzel', serif; font-size: 11px; font-weight: bold; color: #1976d2; text-transform: uppercase; }
        .form-control { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-family: 'PT Serif', serif; font-size: 14px; color: #333; outline: none; transition: 0.3s; }
        .form-control:focus { border-color: #1976d2; }
        .btn-filter { background: #1976d2; color: white; border: none; padding: 9px 20px; border-radius: 6px; font-family: 'Cinzel', serif; font-weight: bold; font-size: 12px; cursor: pointer; transition: 0.3s; }
        .btn-filter:hover { background: #0d1b2a; }
        .btn-reset { background: #f5f5f5; color: #333; border: 1px solid #ddd; text-decoration: none; padding: 8px 15px; border-radius: 6px; font-family: 'Cinzel', serif; font-weight: bold; font-size: 12px; text-align: center; transition: 0.3s; }
        .btn-reset:hover { background: #e0e0e0; }

        .pagination-container { display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 30px; }
        .page-link { text-decoration: none; color: #1976d2; background: white; border: 1px solid #ddd; padding: 8px 14px; border-radius: 6px; font-weight: bold; font-size: 13px; transition: 0.3s; }
        .page-link:hover, .page-link.active { background: #1976d2; color: white; border-color: #1976d2; }
        .page-link.disabled { color: #ccc; background: #fafafa; border-color: #eee; cursor: not-allowed; }
        .history-footer {
            text-align: center;
            margin-top: 40px; 
            padding-top: 20px;
            border-top: 1px solid rgba(0,0,0,0.05); 
        }

        .btn-return {
            text-decoration: none; 
            color: #1976d2;
            font-family: 'Cinzel', serif;
            font-weight: 700;
            transition: 0.3s;
            display: inline-block;
        }

        .btn-return:hover {
            color: #0d1b2a;
            transform: translateX(-5px);
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
                <a onclick="toggleSubmenu(this)"class="active">
                    <i class="fas fa-gavel"class="active"></i> <span>Livestock Auctions</span>
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
                        <li><a href="farmer_manage_auction.php">Livestock Auctions Management</a></li>
                        <li class="active">Auction History</li>
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
                 <a href="farmer_manage_auction.php" class="back-btn">
                    <i class="bi bi-arrow-left-circle-fill"></i> Back
                </a>
                    <h2 class="main-title" style="margin: 0; display: inline-block;">Auction History</h2>
            </div>

                <div class="filter-container">
                    <form method="GET" action="" class="filter-form">
                        <div class="form-group">
                            <label for="start_date">End Date From</label>
                            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_filter) ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date To</label>
                            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_filter) ?>" class="form-control">
                        </div>
                        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                        <?php if(!empty($start_filter) || !empty($end_filter)): ?>
                            <a href="?" class="btn-reset">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <table class="auction-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Ended Date</th>
                            <th>Livestock Info</th>
                            <th>Total Bids</th>
                            <th>Final Bid</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="6">No completed auctions found.</td></tr>
                        <?php else: ?>
                            <?php 
                            $i = $offset + 1;
                            foreach ($history as $row): ?>
                                <tr>
                                    <td>
                                        <span style="color: #888; font-size: 15px; font-weight: bold;"><?= $i++ ?>.</span>
                                    </td>
                                    <td>
                                        <div style="font-weight: bold; color: #333;">
                                            <?= date('d M Y', strtotime($row['end_time'])) ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #d32f2f; margin-top: 4px; font-family: 'PT Serif', serif;">
                                            <i class="far fa-clock"></i> <?= date('h:i A', strtotime($row['end_time'])) ?>
                                        </div>
                                    </td>
                                    <td style="text-align: left;">
                                        <strong><?= htmlspecialchars($row['livestock_name']) ?></strong><br>
                                        <small class="text-muted">ID: <?= formatAuctionID($row['auction_id']) ?></small>
                                    </td>
                                    <td><span class="badge" style="background: #eee; padding: 4px 10px; border-radius: 10px;"><?= $row['total_bids'] ?> Bids</span></td>
                                    <td>
                                        <strong style="color: #2e7d32;">RM <?= number_format($row['current_bid'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= strtolower($row['status']) ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_auction_bids.php?id=<?= $row['auction_id'] ?>" class="btn-action" style="background: #1976d2; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 12px;">
                                            <i class="fas fa-eye"></i> View Bids
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <?php 
                        $url_params = "";
                        if (!empty($start_filter)) $url_params .= "&start_date=" . urlencode($start_filter);
                        if (!empty($end_filter)) $url_params .= "&end_date=" . urlencode($end_filter);
                        ?>

                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 . $url_params ?>" class="page-link">&laquo; Prev</a>
                        <?php else: ?>
                            <span class="page-link disabled">&laquo; Prev</span>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <a href="?page=<?= $p . $url_params ?>" class="page-link <?= ($page == $p) ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 . $url_params ?>" class="page-link">Next &raquo;</a>
                        <?php else: ?>
                            <span class="page-link disabled">Next &raquo;</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="history-footer">
                    <a href="farmer_manage_auction.php" class="btn-return">
                        <i class="fas fa-arrow-left"></i> Return to List
                    </a>
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
    </script>
</body>
</html>