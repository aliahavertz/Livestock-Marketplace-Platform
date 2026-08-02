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

$sqlFarmer = "SELECT * FROM farmer WHERE farmer_id = :id";
$stmtFarmer = $pdo->prepare($sqlFarmer);
$stmtFarmer->execute(['id' => $farmer_id]);
$farmer = $stmtFarmer->fetch(PDO::FETCH_ASSOC);

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
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'] ?? null;
    $livestock_id = $_POST['livestock_id'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $starting_price = $_POST['starting_price'] ?? null;
    $deposit_amount = $_POST['deposit_amount'] ?? 0;

    if (strtotime($end_time) <= strtotime($start_time)) {
        $error = "End date must be later than the start date.";
    } else {
        try {
            $pdo->beginTransaction();

            $sqlAuction = "INSERT INTO auction (title, livestock_id, current_bid, starting_price, start_time, end_time, status) 
                           VALUES (?, ?, ?, ?, ?, ?, 'active')";
            $stmtAuction = $pdo->prepare($sqlAuction);
            $stmtAuction->execute([$title, $livestock_id, $starting_price, $starting_price, $start_time, $end_time]);
            
            $new_auction_id = $pdo->lastInsertId();
            
            $sqlDeposit = "INSERT INTO auction_deposits (auction_id, amount) VALUES (?, ?)";
            $stmtDeposit = $pdo->prepare($sqlDeposit);
            $stmtDeposit->execute([$new_auction_id, $deposit_amount]);

            $sqlLivestock = "UPDATE livestock 
            SET availability_status = 'In Auction', 
            sale_type = 'Auction',
            price = ? 
            WHERE livestock_id = ?";
            $stmtLivestock = $pdo->prepare($sqlLivestock);
            $stmtLivestock->execute([$starting_price, $livestock_id]);

            $pdo->commit();

            $_SESSION['msg'] = "Auction and Deposit Settings Created Successfully!";
            header("Location: farmer_manage_auction.php");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

$query = "SELECT livestock_id, name, breed, gender, farmer_livestock_no, availability_status 
          FROM livestock 
          WHERE farmer_id = ? 
          AND (sale_type IS NULL OR sale_type != 'Auction')
          AND availability_status = 'Available'
          ORDER BY name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute([$farmer_id]);
$livestock_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Auction | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">
    <style>
        :root {
            --primary-gold: #c5a059;
            --dark-navy: #0d1b2a;
            --glass-bg: rgba(255, 255, 255, 0.6);
        }

        .page-wrapper { 
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 80vh;
            padding: 20px; 
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


        .glass-card { 
            background: var(--glass-bg); backdrop-filter: blur(15px);
            padding: 40px; border-radius: 30px; border: 1px solid rgba(144, 202, 249, 0.4);
            box-shadow: 0 15px 35px rgba(0,0,0,0.05); max-width: 600px;
        }

        .form-group { margin-bottom: 20px; }
        
        label { 
            display: block; font-family: 'Cinzel', serif; font-weight: 700; 
            font-size: 0.85rem; color: #444; margin-bottom: 8px; letter-spacing: 0.5px;
        }

        input, select {
            width: 100%; padding: 12px 15px; border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.1); background: rgba(255,255,255,0.8);
            font-family: 'PT Serif', serif; font-size: 1rem; transition: 0.3s;
        }

        input:focus, select:focus {
            outline: none; border-color: #1976d2; box-shadow: 0 0 0 4px rgba(25, 118, 210, 0.1);
        }

        .btn-submit {
            background: #1976d2; color: white; border: none;
            padding: 15px 30px; border-radius: 12px; font-family: 'Cinzel', serif;
            font-weight: 700; cursor: pointer; width: 100%; font-size: 1rem;
            letter-spacing: 1px; transition: 0.3s; margin-top: 20px; max-width: 100%;
        }

        .btn-submit:hover { background: var(--dark-navy); transform: translateY(-2px); }

        .error-msg {
            background: #ffebee; color: #c62828; padding: 15px;
            border-radius: 12px; margin-bottom: 20px; border-left: 5px solid #c62828;
            font-family: 'PT Serif', serif;
        }

        @media (max-width: 480px) {
            .glass-card { padding: 25px; }
            div[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important; 
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
                <a onclick="toggleSubmenu(this)"class="active">
                    <i class="fas fa-gavel"></i> <span>Livestock Auctions</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="submenu">
                    <li><a href="farmer_manage_auction.php"><i class="fas fa-gavel"></i> Manage Auctions</a></li>
                    <li><a href="create_auction.php" class="active"><i class="fas fa-plus-circle"></i> Start New Auction</a></li>
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
                    <a href="farmer_manage_auction.php" class="back-btn">
                        <i class="bi bi-arrow-left-circle-fill"></i> Back
                    </a>
                <h2 class="main-title">Create New Auction</h2>
            </div>

                <?php if (isset($error)): ?>
                    <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-signature"></i> Auction Title</label>
                        <input type="text" name="title" placeholder="e.g. Premium Brahman Bull Sale" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-cow"></i> Select Livestock</label>
                        <select name="livestock_id" required>
                            <option value="">-- Choose from your inventory --</option>
                            <?php if (empty($livestock_list)): ?>
                                <option value="" disabled>No available livestock for auction found in your records.</option>
                            <?php else: ?>
                                <?php foreach ($livestock_list as $row): ?>
                                    <option value="<?= $row['livestock_id'] ?>">
                                        <?= htmlspecialchars($row['name']) ?> 
                                        (<?= htmlspecialchars($row['availability_status']) ?>) 
                                        - ID: <?= htmlspecialchars($row['farmer_livestock_no']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                       <!--  <small style="display: block; margin-top: 5px; color: #666; font-family: 'PT Serif', serif;">
                            Only livestock marked as "Available" are listed here.
                        </small> -->
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label><i class="far fa-calendar-check"></i> Start Date & Time</label>
                            <input type="datetime-local" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label><i class="far fa-calendar-times"></i> End Date & Time</label>
                            <input type="datetime-local" name="end_time" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Starting Price (RM)</label>
                        <input type="number" step="0.01" name="starting_price" placeholder="0.00" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-hand-holding-usd"></i> Required Deposit (RM)</label>
                        <input type="number" step="0.01" name="deposit_amount" placeholder="0.00" required>
                        <small style="color: #666; font-size: 0.75rem;">Amount bidders must pay to join.</small>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-plus-circle"></i> Create Auction
                    </button>
                    
                    <a href="farmer_manage_auction.php" style="display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; font-family: 'Cinzel', serif; font-size: 0.8rem;">
                        Cancel and Return
                    </a>
                </form>
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