<?php
session_start();
include '../db_connect.php';

date_default_timezone_set('Asia/Kuala_Lumpur');
if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    try {
        $pdo->beginTransaction();

        $pdo->prepare("DELETE FROM health WHERE livestockid = ?")->execute([$delete_id]);

        $pdo->prepare("DELETE FROM harvestservice WHERE livestockid = ?")->execute([$delete_id]);

        $stmtSingleDelete = $pdo->prepare("DELETE FROM livestock WHERE livestock_id = ? AND farmer_id = ?");
        $stmtSingleDelete->execute([$delete_id, $farmer_id]);

        if ($stmtSingleDelete->rowCount() > 0) {
            $pdo->commit();
            header("Location: livestock_archive.php?msg=deleted");
            exit();
        } else {
            $pdo->rollBack();
            header("Location: livestock_archive.php?msg=error_not_found");
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        header("Location: livestock_archive.php?msg=db_error");
        exit();
    }
}

$stmt = $pdo->prepare("SELECT * FROM farmer WHERE farmer_id = :id");
$stmt->execute(['id' => $farmer_id]);
$farmer = $stmt->fetch(PDO::FETCH_ASSOC);
$name = $farmer['farm_name'] ?? 'Farmer';

$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$current_time = date('Y-m-d H:i:s');

$where_clauses = ["l.farmer_id = :fid", "l.availability_status IN ('Sold', 'Unavailable')"];
$params = [':fid' => $farmer_id];

if (!empty($search)) {
    $where_clauses[] = "(l.name ILIKE :search OR l.breed ILIKE :search OR CAST(l.livestock_id AS TEXT) ILIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category_filter)) {
    $where_clauses[] = "l.category = :cat";
    $params[':cat'] = $category_filter;
}

if (!empty($start_date)) {
    $where_clauses[] = "l.date_listed >= :start_date";
    $params[':start_date'] = $start_date . " 00:00:00";
}

if (!empty($end_date)) {
    $where_clauses[] = "l.date_listed <= :end_date";
    $params[':end_date'] = $end_date . " 23:59:59";
}

$where_sql = " WHERE " . implode(" AND ", $where_clauses);

$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

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

if (isset($_POST['bulk_delete']) && !empty($_POST['livestock_ids'])) {
    $ids_to_delete = $_POST['livestock_ids'];
    $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
    
    try {
        $pdo->beginTransaction();
        $stmtDelete = $pdo->prepare("DELETE FROM livestock WHERE livestock_id IN ($placeholders) AND farmer_id = ?");
        $stmtDelete->execute(array_merge($ids_to_delete, [$farmer_id]));
        $pdo->commit();
        header("Location: livestock_archive.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting records: " . $e->getMessage();
    }
}

$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND user_type = 'farmer' AND is_read = FALSE");
$stmtUnread->execute(['uid' => $farmer_id]);
$unreadCount = $stmtUnread->fetchColumn();


$imageFolder = "uploads/";
$imagePath = (!empty($farmer['profile_image']) && file_exists($imageFolder . $farmer['profile_image'])) 
? $imageFolder . $farmer['profile_image'] 
: $imageFolder . "default.png";

function keep_query_parameters($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Livestock Archive | RanchLink</title>
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
        .glass-card { background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(15px); padding: 30px; border-radius: 30px; border: 1px solid rgba(144, 202, 249, 0.4); width: 100%; box-sizing: border-box; }
        .toolbar { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; gap: 20px; flex-wrap: wrap; background: rgba(0, 0, 0, 0.03); padding: 20px; border-radius: 20px; }
        .table-container { width: 100%; overflow-x: auto; margin-top: 20px; border-radius: 15px; }
        .modern-table { width: 100%; min-width: 1200px; border-collapse: separate; border-spacing: 0 12px; }
        .modern-table th { font-family: 'Cinzel', serif; color: #1976d2; font-size: 0.8rem; text-transform: uppercase; padding: 10px 20px; text-align: center; }
        .modern-table td { background: white; padding: 15px; text-align: center; border-top: 1px solid rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.02); }
        .modern-table tr td:first-child { border-radius: 15px 0 0 15px; }
        .modern-table tr td:last-child { border-radius: 0 15px 15px 0; }
        .animal-img { width: 65px; height: 65px; object-fit: cover; border-radius: 12px; border: 2px solid #f4efe6; filter: grayscale(30%); }
        .price-text { color: #2d5a27; font-weight: bold; font-family: 'Cinzel', serif; font-size: 1rem; }
        .badge { font-size: 0.7rem; padding: 4px 10px; font-family: 'Cinzel', serif; font-weight: bold; border-radius: 50px; display: inline-block; }
        .sale-type { background: #f0f4f8; color: #1976d2; }
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 25px; padding: 10px; font-family: 'PT Serif', serif; }
        .pagination-info { color: #555; font-size: 0.9rem; }
        .pagination-buttons { display: flex; gap: 8px; }
        .pagination-btn { text-decoration: none; padding: 8px 16px; border-radius: 50px; border: 1px solid #1976d2; color: #1976d2; font-size: 0.85rem; font-weight: bold; transition: all 0.3s; background: white; }
        .pagination-btn:hover { background: #1976d2; color: white; }
        .pagination-btn.active { background: #1976d2; color: white; cursor: default; }
        .pagination-btn.disabled { border-color: #ccc; color: #ccc; pointer-events: none; background: #fafafa; }
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
                    <li><a href="view_livestock.php"><i class="fas fa-list"></i> View All Livestock</a></li>
                    <li><a href="add_livestock.php"><i class="fas fa-plus"></i> Add Livestock</a></li>
                    <li><a href="livestock_archive.php" class="active"><i class="fas fa-archive"></i> Livestock Archive</a></li>
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
                        <li><a href="view_livestock.php">Livestock Inventory Ledger</a></li>
                        <li class="active">Livestock Archive</li>
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
                <a href="view_livestock.php" class="back-btn">
                    <i class="bi bi-arrow-left-circle-fill"></i> Back
                </a>
                <h2 class="main-title">Livestock Archive (Sold/Unavailable)</h2>
            </div>
                
                <form id="archiveForm" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete selected records?');">
                    <div class="toolbar" style="justify-content: space-between;">
                        <div style="display:flex; gap:15px; flex-grow:1;">
                            <!-- <input type="search" 
                            name="search" 
                            id="searchInput"
                            class="input-field" 
                            placeholder="Search archive..." 
                            value="<?= htmlspecialchars($search) ?>" 
                            oninput="checkClear(this)" style="padding: 10px; border-radius: 8px; border: 1px solid #ddd;"> -->

                            <div style="display: flex; align-items: center; gap: 8px; background: white; padding: 5px 12px; border-radius: 8px; border: 1px solid #ddd;">
                                <label style="font-size:0.75rem; font-family:'Cinzel'; color:#777;">From:</label>
                                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" style="border:none; outline:none; font-size:0.85rem;">
                                <label style="font-size:0.75rem; font-family:'Cinzel'; color:#777;">To:</label>
                                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" style="border:none; outline:none; font-size:0.85rem;">
                            </div>

                            <button type="button" onclick="this.form.method='GET'; this.form.submit();" class="btn btn-search" style="background:#1976d2; color:white; border:none; padding:10px 25px; border-radius:50px; cursor:pointer; font-weight:bold; font-family: 'Cinzel', serif;">Filter</button>
                            <button type="button" onclick="window.location.href='livestock_archive.php'" class="btn" style="background:#888; color:white; border:none; padding:10px 15px; border-radius:50px; cursor:pointer; font-size:0.85rem; font-family: 'Cinzel', serif;">Clear All</button>
                        </div>

                        <button type="submit" name="bulk_delete" class="btn" style="background:#e74c3c; color:white; border:none; padding:10px 20px; border-radius:50px; cursor:pointer; font-family:'Cinzel'; font-weight:bold;">
                            <i class="fas fa-trash-alt"></i> Delete All
                        </button>
                    </div>

                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>No.</th><th>ID</th><th>Images</th>
                                <th style="text-align: left;">Livestock Details</th>
                                <th>Health Records</th><th>Harvest Services</th><th>Type</th>
                                <th>Price/Bid</th><th>Deposit</th><th>Availability</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = $offset + 1;
                            if ($total_items > 0): 
                                while($row = $stmt_livestock->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="livestock_ids[]" value="<?= $row['livestock_id'] ?>" class="item-checkbox">
                                    </td>
                                    <td><?= $counter++ ?>.</td>
                                    <td style="color: #1976d2; font-weight: bold;"><?= htmlspecialchars($row['farmer_livestock_no']) ?></td>
                                    <td>
                                        <?php 
                                        $imgs = !empty($row['image']) ? explode(',', $row['image']) : [];
                                        $display = !empty($imgs) ? trim($imgs[0]) : 'placeholder.jpg';
                                        ?>
                                        <img src="uploads/<?= $display ?>" class="animal-img">
                                    </td>
                                    <td style="text-align: left;">
                                        <strong><?= htmlspecialchars($row['name']) ?></strong><br>
                                        <small><?= htmlspecialchars($row['breed']) ?></small>
                                    </td>
                                    <td style="font-size: 0.7rem; text-align: left;">
                                        <?php if($row['vax'] || $row['vit'] || $row['med']): ?>
                                            <div><i class="fas fa-syringe"></i> <?= htmlspecialchars($row['vax'] ?: 'None') ?></div>
                                            <div><i class="fas fa-capsules"></i> <?= htmlspecialchars($row['vit'] ?: 'None') ?></div>
                                        <?php else: ?>
                                            <span style="color:#ccc;">No Records</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.85rem;">
                                        <?php 
                                        if (!empty($row['available_services']) && $row['available_services'] !== 'None') {
                                            $serviceList = explode(',', $row['available_services']);
                                            $feeList = explode(',', $row['individual_service_fees']); 

                                            foreach ($serviceList as $i => $s) {
                                                $serviceName = htmlspecialchars(trim($s));
                                                $fee = isset($feeList[$i]) ? (float)trim($feeList[$i]) : 0.00;

                                                echo "<div style='font-size:0.7rem;'><b>$serviceName</b> (RM " . number_format($fee, 2) . ")</div>";
                                            }
                                        } else { 
                                            echo "—"; 
                                        }
                                        ?>
                                    </td>
                                    <td><span class="badge sale-type"><?= $row['sale_type'] ?></span></td>
                                    <td class="price-text">RM <?= number_format($row['price'], 2) ?></td>
                                    <td>RM <?= number_format($row['deposit_amount'] ?? 0, 2) ?></td>
                                    <td>
                                        <span class="badge" style="background:#ffebee; color:#c62828;">
                                            <?= htmlspecialchars($row['availability_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" 
                                        onclick="confirmDelete(<?= $row['livestock_id'] ?>)" 
                                        style="background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 1.1rem;" 
                                        title="Delete Permanent">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="10">Archive is empty.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing items <b><?= $offset + 1 ?></b> to <b><?= min($offset + $limit, $total_items) ?></b> of <b><?= $total_items ?></b> records
                        </div>
                        <div class="pagination-buttons">
                            <a href="livestock_archive.php?<?= keep_query_parameters($page - 1) ?>" class="pagination-btn <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="livestock_archive.php?<?= keep_query_parameters($i) ?>" class="pagination-btn <?= ($page == $i) ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <a href="livestock_archive.php?<?= keep_query_parameters($page + 1) ?>" class="pagination-btn <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        function confirmDelete(id) {
            if (confirm("Are you sure you want to permanently delete this record? This action cannot be undone.")) {
                window.location.href = "livestock_archive.php?delete_id=" + id;
            }
        }

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
            alert('Records deleted successfully.');
        <?php endif; ?>

        function checkClear(input) {
            if (input.value === "") {
                input.form.method = 'GET';
                input.form.submit();
            }
        }
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