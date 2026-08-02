<?php
session_start();
include('../db_connect.php');

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer/farmer_dashboard.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

$stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();

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
    <title>Farmer Profile | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.5">

    <style>
        .sidebar .profile-section {
            margin-bottom: 0px !important;
            padding-bottom: 5px !important;
        }

        .sidebar .farmer-name {
            margin-bottom: 0px !important;
        }

        .sidebar .nav-links {
            margin-top: 5px !important;
            padding-left: 0 !important;
        }

        .sidebar .nav-links li a {
            padding-left: 15px !important; 
        }
        .sidebar .nav-links {
            padding: 0 !important;
            margin-top: 10px !important; 
            list-style: none !important;
            width: 100% !important;
        }

        .sidebar .nav-links li {
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .sidebar .nav-links li a {
            display: flex !important;
            align-items: center !important;
            text-decoration: none !important;
            padding: 12px 24px !important;
            gap: 12px !important; 
            width: 100% !important;
            color: #ffffff !important;
            font-family: 'Cinzel', serif !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            box-sizing: border-box !important;
        }

        .sidebar .nav-links li a span {
            color: #ffffff !important;
            font-family: 'Cinzel', serif !important;
            text-align: left !important;
        }

        .sidebar .nav-links li a i:first-child {
            color: #ffffff !important;
            font-size: 16px !important;
            width: 20px !important;
            text-align: center !important;
            margin: 0 !important;
        }

        .sidebar .nav-links li a.logout-link,
        .sidebar .nav-links li a.logout-link span,
        .sidebar .nav-links li a.logout-link i {
            color: #e74c3c !important;
        }

        .sidebar .nav-links li .submenu {
            display: none;
            list-style: none !important;
            padding-left: 20px !important;
            margin: 0 !important;
            background: rgba(0, 0, 0, 0.1) !important;
        }

        .sidebar .nav-links li.open .submenu {
            display: block !important;
        }
        .sidebar .nav-links li a:hover,
        .sidebar .nav-links li a:hover span,
        .sidebar .nav-links li a:hover i {
            color: #1976d2 !important; 
        }

        .sidebar .nav-links li a.logout-link:hover,
        .sidebar .nav-links li a.logout-link:hover span,
        .sidebar .nav-links li a.logout-link:hover i {
            color: #ff6b6b !important; 
        }

        .main-content {
            margin-left: 280px !important;
            width: calc(100% - 280px) !important;
            position: relative;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 75px !important;
            width: calc(100% - 75px) !important;
        }

        .page-wrapper {
            width: 100% !important;
            max-width: 1000px !important; /* Expanded wrapper alignment to support horizontal split view */
            margin: 0 auto !important;
        }

        .breadcrumb-wrapper {
            max-width: 1000px;
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

        /* --- Updated Layout Grid for Horizontal Card View --- */
        .inside-profile-box {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(15px);
            padding: 40px;
            border-radius: 30px;
            border: 1px solid rgba(144, 202, 249, 0.4);
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
            position: relative;
        }

        .inside-profile-box h3 {
            font-family: 'Cinzel', serif;
            text-align: center;
            font-weight: 700;
            color: #0d1b2a;
            font-size: 28px;
            margin-bottom: 40px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        /* Container partitioning image row and layout details side by side */
        .horizontal-profile-container {
            display: flex;
            flex-direction: row;
            gap: 40px;
            align-items: flex-start;
        }

        .left-img-pane {
            flex: 0 0 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: sticky;
            top: 20px;
        }

        .right-data-pane {
            flex: 1;
        }

        .inside-profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #90caf9;
            padding: 4px;
            background: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: 0.3s ease;
        }

        .inside-profile-img:hover {
            transform: scale(1.03);
            box-shadow: 0 8px 20px rgba(144,202,249,0.4);
        }

        .info-table {
            width: 100%;
            margin-top: 0px; /* Aligns smoothly with image header */
        }

        .info-row {
            display: flex;
            padding: 16px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            align-items: center;
        }

        .info-row:last-child { border-bottom: none; }

        .info-label {
            flex: 0 0 180px; /* Gives structural alignment spacing to custom label tags */
            font-family: 'Cinzel', serif;
            font-weight: 700;
            color: #1976d2;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-value {
            flex: 1;
            color: #3b332a;
            font-size: 15px;
            padding-left: 10px;
            font-family: 'PT Serif', serif;
        }

        .edit-btn {
            display: inline-block;
            background: linear-gradient(135deg, #90caf9, #64b5f6);
            color: #0d1b2a;
            padding: 14px;
            text-align: center;
            text-decoration: none;
            font-family: 'PT Serif', serif;
            font-weight: bold;
            border-radius: 50px;
            transition: 0.3s;
            box-shadow: 0 5px 15px rgba(144, 202, 249, 0.4);
            margin-top: 30px;
            border: none;
            width: 60%;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(144, 202, 249, 0.6);
            color: #0d1b2a;
        }
        
        .msg {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
            font-size: 14px;
        }

        .label-icon { width: 18px; text-align: center; color: #1976d2; font-size: 14px; }

        @media (max-width: 768px) {
            .horizontal-profile-container {
                flex-direction: column;
                align-items: center;
            }
            .left-img-pane {
                position: static;
                flex: 1 1 auto;
                margin-bottom: 25px;
            }
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .info-value {
                padding-left: 0;
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
                <a href="../Models/logout.php" class="logout-link" style="margin-top: 30px;">
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
                        <li class="active">My Profile</li>
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
            <div class="inside-profile-box">
                <h3>Farmer Profile</h3>

                <?php if(isset($_GET['status'])): ?>
                    <div class="msg"><i class="fas fa-check-circle me-2"></i> Profile Updated Successfully</div>
                <?php endif; ?>
                
                <!-- Flex container wrapper split horizontally -->
                <div class="horizontal-profile-container">
                    
                    <!-- Left Column: Display Image Presentation -->
                    <div class="left-img-pane">
                        <img src="<?php echo $imagePath; ?>" class="inside-profile-img" alt="Profile Picture">
                        <div class="d-flex align-items: center justify-content: center gap-1 mt-3">
                            <p style="font-size: 0.75rem; margin: 0; color: #666; font-family: 'Cinzel', serif; font-weight: 700;">Farm Logo</p>
                        </div>
                    </div>

                    <!-- Right Column: Profile Data Details & Action Flow -->
                    <div class="right-data-pane">
                        <div class="info-table">
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-warehouse label-icon"></i> Farm Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['farm_name']); ?></span>
                            </div>

                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-user-tie label-icon"></i> Owner Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['name']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-envelope label-icon"></i> Email Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['email']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-phone label-icon"></i> Phone Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['phone_number']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-map-marked-alt label-icon"></i> Farm Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['address'] ?: 'Not provided'); ?></span>
                            </div>

                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-file-alt label-icon"></i> Farm Description</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['farm_description']); ?></span>
                            </div>

                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-id-card label-icon"></i> Reg. Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['registration_number']); ?></span>
                            </div>
                        </div>

                        <a href="farmer_edit_profile.php" class="edit-btn">
                            <i class="fas fa-user-edit me-2"></i> Edit Profile Information
                        </a>
                    </div>

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