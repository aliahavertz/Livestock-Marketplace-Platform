<?php
session_start();
include '../db_connect.php';
include '../inc/numbers.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

$stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();

$sql = "SELECT name, farm_name, email, phone_number, profile_image 
        FROM farmer 
        WHERE farmer_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$farmer_id]);
$farmer = $stmt->fetch(PDO::FETCH_ASSOC);

$name = $farmer['name']; 

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

$auction_id = $_GET['id'] ?? null;

if (!$auction_id) {
    header("Location: farmer_manage_auctions.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT a.*, l.name as livestock_name 
                           FROM auction a 
                           JOIN livestock l ON a.livestock_id = l.livestock_id 
                           WHERE a.auction_id = :aid AND l.farmer_id = :fid");
    $stmt->execute([':aid' => $auction_id, ':fid' => $farmer_id]);
    $auction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$auction) {
        die("Auction record not found or access denied.");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $starting_price = $_POST['starting_price'];

    if (strtotime($end_time) <= strtotime($start_time)) {
        $error = "The closing date must be later than the opening date.";
    } else {
        try {
            $updateSql = "UPDATE auction 
                          SET title = :title, 
                              start_time = :start, 
                              end_time = :end, 
                              starting_price = :price 
                          WHERE auction_id = :aid";
            
            $updateStmt = $pdo->prepare($updateSql);
            $success = $updateStmt->execute([
                ':title' => $title,
                ':start' => $start_time,
                ':end'   => $end_time,
                ':price' => $starting_price,
                ':aid'   => $auction_id
            ]);

            if ($success) {
                $formattedID = formatAuctionID($auction_id);
                
                $_SESSION['msg'] = "Auction #$formattedID updated successfully.";
                header("Location: farmer_manage_auction.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Update Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Auction | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">
    <style>
        .form-card { 
            background: white; max-width: 600px; margin: auto; padding: 40px; 
            border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid rgba(25, 118, 210, 0.2);
        }
        h2 { font-family: 'Cinzel', serif; color: #0d1b2a; text-align: center; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        label { display: block; margin-top: 20px; font-family: 'Cinzel', serif; font-size: 0.8rem; color: #1976d2; font-weight: bold; }
        input { 
            width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #ddd; 
            border-radius: 10px; box-sizing: border-box; font-family: 'PT Serif', serif;
        }
        .btn-save { 
            background: #1976d2; color: white; width: 100%; padding: 15px; 
            border: none; border-radius: 50px; margin-top: 30px; 
            font-family: 'Cinzel', serif; font-weight: bold; cursor: pointer; transition: 0.3s;
        }
        .btn-save:hover { background: #1565c0; transform: translateY(-2px); }
        .error-msg { background: #ffebee; color: #c62828; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #ef9a9a; }
        .cancel-link { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; font-size: 0.85rem; }
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
                    <li><a href="farmer_manage_auction.php"><i class="fas fa-gavel" class="active"></i> Manage Auctions</a></li>
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
                        <li class="active">Edit Auction</li>
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

<div class="form-card">
    <h2>Edit Auction Schedule</h2>
    <p style="text-align: center; color: #666;">Modifying details for: <strong><?= htmlspecialchars($auction['livestock_name']) ?></strong></p>

    <?php if (isset($error)): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Auction Title</label>
        <input type="text" name="title" value="<?= htmlspecialchars($auction['title']) ?>" required>

        <div style="display: flex; gap: 15px;">
            <div style="flex: 1;">
                <label>Opening Time</label>
                <input type="datetime-local" name="start_time" 
                       value="<?= date('Y-m-d\TH:i', strtotime($auction['start_time'])) ?>" required>
            </div>
            <div style="flex: 1;">
                <label>Closing Time</label>
                <input type="datetime-local" name="end_time" 
                       value="<?= date('Y-m-d\TH:i', strtotime($auction['end_time'])) ?>" required>
            </div>
        </div>

        <label>Starting Price (RM)</label>
        <input type="number" step="0.01" name="starting_price" value="<?= $auction['starting_price'] ?>" required>

        <button type="submit" class="btn-save">Update Auction</button>
        <a href="farmer_manage_auction.php" class="cancel-link">Return</a>
    </form>
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
</script>
</body>
</html>