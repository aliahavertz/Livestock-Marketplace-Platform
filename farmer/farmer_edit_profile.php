<?php
session_start();
include('../db_connect.php');

if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../index.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

$stmt = $pdo->prepare("SELECT * FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$farmer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$farmer) {
    die("Farmer record not found.");
}

$name = $farmer['farm_name'];
$imageFolder = "uploads/";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmName = $_POST['farm_name'] ?? '';
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone_number'] ?? '';
    $address = $_POST['address'] ?? '';
    $farmDesc = $_POST['farm_description'] ?? '';
    
    $img = $farmer['profile_image']; 

    if (!empty($_FILES['profile_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $img = "farmer_" . $farmer_id . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['profile_image']['tmp_name'], $imageFolder . $img);
            
            if (!empty($farmer['profile_image']) && $farmer['profile_image'] != 'default.png') {
                if (file_exists($imageFolder . $farmer['profile_image'])) {
                    unlink($imageFolder . $farmer['profile_image']);
                }
            }
        }
    }

    $colName = array_key_exists('phone_number', $farmer) ? 'phone_number' : 'phone';
    
    $sql = "UPDATE farmer SET name = ?, farm_name = ?, $colName = ?, address = ?, farm_description = ?, profile_image = ? WHERE farmer_id = ?";
    $pdo->prepare($sql)->execute([$name, $farmName, $phone, $address, $farmDesc, $img, $farmer_id]);

    header("Location: farmer_profile.php?status=updated");
    exit();
}

$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND user_type = 'farmer' AND is_read = FALSE");
$stmtUnread->execute(['uid' => $farmer_id]);
$unreadCount = $stmtUnread->fetchColumn();

$displayFarm = $farmer['farm_name'] ?? '';
$displayName = $farmer['name'] ?? '';
$displayAddress = $farmer['address'] ?? '';
$displayFarmDesc = $farmer['farm_description'] ?? '';
$displayPhone = $farmer['phone_number'] ?? $farmer['phone'] ?? '';

if (!empty($farmer['profile_image']) && file_exists($imageFolder . $farmer['profile_image'])) {
    $imagePath = $imageFolder . $farmer['profile_image'];
} else {
    $imagePath = $imageFolder . "default.png";
}

$displayImg = $imagePath; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">


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
            max-width: 1000px !important; /* Increased width to perfectly fit the horizontal view */
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

        /* --- Updated Horizontal Profile Box Styles --- */
        .profile-box {
            width: 100%;
            margin: 20px auto;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(15px);
            padding: 40px;
            border-radius: 30px;
            border: 1px solid rgba(144, 202, 249, 0.4);
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
        }

        .profile-box h3 {
            font-family: 'Cinzel', serif;
            text-align: center;
            font-weight: 700;
            color: #0d1b2a;
            font-size: 28px;
            margin-bottom: 40px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        /* Flex container mapping out image column left and field column right */
        .horizontal-card-container {
            display: flex;
            flex-direction: row;
            gap: 40px;
            align-items: flex-start;
        }

        .left-image-col {
            flex: 0 0 200px; /* Locked width for image sidebar section */
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: sticky;
            top: 20px;
        }

        .right-fields-col {
            flex: 1; /* Automatically uses remaining card width */
        }

        .img-preview-container {
            margin-bottom: 15px;
        }

        .img-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #90caf9;
            padding: 4px;
            background: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        label {
            font-family: 'Cinzel', serif;
            font-weight: 700;
            font-size: 13px;
            color: #1976d2;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        label i.label-icon {
            font-size: 14px;
            color: #1976d2;
            width: 18px;
            text-align: center;
        }

        .input-group-wrapper {
            position: relative;
            margin-bottom: 25px;
        }

        input[type="text"], 
        input[type="password"],
        input[type="file"], 
        textarea {
            width: 100%;
            padding: 12px 15px;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.1);
            background: rgba(255, 255, 255, 0.8);
            font-family: 'PT Serif', serif;
            transition: 0.3s;
        }

        .has-eye-toggle input {
            padding-right: 45px;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #90caf9;
            background: #fff;
            box-shadow: 0 0 10px rgba(144, 202, 249, 0.2);
        }

        .eye-toggle-btn {
            position: absolute;
            right: 15px;
            top: 43px; /* Realigned position for horizontal spacing */
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
            padding: 0;
            font-size: 16px;
            transition: color 0.2s ease;
            z-index: 5;
        }
        
        .eye-toggle-btn:hover {
            color: #1976d2;
        }

        .btn-save {
            background: linear-gradient(135deg, #90caf9, #64b5f6);
            color: #0d1b2a;
            padding: 14px;
            border: none;
            border-radius: 50px;
            font-weight: bold;
            width: 100%;
            font-family: 'PT Serif', serif;
            transition: 0.3s;
            box-shadow: 0 5px 15px rgba(144, 202, 249, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(144, 202, 249, 0.6);
        }

        .cancel-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: 0.3s;
        }
        .cancel-link:hover { color: #d32f2f; }

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

        /* Fallback breakpoint for mobile devices */
        @media (max-width: 768px) {
            .horizontal-card-container {
                flex-direction: column;
                align-items: center;
            }
            .left-image-col {
                position: static;
                flex: 1 1 auto;
                margin-bottom: 20px;
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
                        <li><a href="farmer_profile.php">My Profile</a></li>
                        <li class="active">Edit Profile</li>
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
        <div class="profile-box">
            <h3>Edit Profile</h3>

            <?php if(isset($_GET['status'])): ?>
                <div class="msg"><i class="fas fa-check-circle me-2"></i> Profile Updated Successfully</div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="horizontal-card-container">
                    
                    <div class="left-image-col">
                        <div class="img-preview-container">
                            <img src="<?php echo $displayImg; ?>" class="img-preview" alt="Current Profile">
                        </div>
                        <label for="file-upload" style="cursor: pointer; color: #1976d2; text-decoration: underline; font-size: 12px; justify-content: center;">
                            <i class="fas fa-camera label-icon"></i> Change Picture
                        </label>
                        <div class="d-flex align-items: center justify-content: center gap-1 mt-1">
                            <span style="color: red; font-size: 0.7rem;">&ast;</span>
                            <p style="font-size: 0.7rem; margin: 0; color: #666;">Use farm logo</p>
                        </div>
                        <input id="file-upload" type="file" name="profile_image" style="display: none;" onchange="previewProfileImage(this)">
                    </div>

                    <div class="right-fields-col">
                        
                        <div class="input-group-wrapper">
                            <label><i class="fas fa-warehouse label-icon"></i> Farm Name</label>
                            <input type="text" id="farm_name" name="farm_name" value="<?php echo htmlspecialchars($displayFarm); ?>" required placeholder="Enter your farm name">
                        </div>

                        <div class="input-group-wrapper">
                            <label><i class="fas fa-user-tie label-icon"></i> Owner Name</label>
                            <input type="text" id="owner_name" name="name" value="<?php echo htmlspecialchars($displayName); ?>" required placeholder="Enter your full name">
                        </div>

                        <div class="input-group-wrapper">
                            <label><i class="fas fa-phone label-icon"></i> Phone Number</label>
                            <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($displayPhone); ?>" required placeholder="Enter your phone number">
                        </div>

                        <div class="input-group-wrapper">
                            <label><i class="fas fa-map-marked-alt label-icon"></i> Farm Address</label>
                            <textarea name="address" rows="3" required placeholder="Enter your full farm address"><?php echo htmlspecialchars($displayAddress); ?></textarea>
                        </div>

                        <div class="input-group-wrapper">
                            <label><i class="fas fa-file-alt label-icon"></i> Farm Description</label>
                            <textarea name="farm_description" rows="3" required placeholder="Enter your farm description"><?php echo htmlspecialchars($displayFarmDesc); ?></textarea>
                        </div>

                        <button type="submit" class="btn-save">Save Changes</button>
                        <a href="farmer_profile.php" class="cancel-link">
                            <i class="fas fa-times me-1"></i> Cancel & Return
                        </a>
                    </div>

                </div>
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

        function toggleFieldView(fieldId, btnElement) {
            const field = document.getElementById(fieldId);
            const icon = btnElement.querySelector('i');

            if (field.type === "password") {
                field.type = "text";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                field.type = "password";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }

        function previewProfileImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    const preview = document.querySelector('.img-preview');
                    if (preview) {
                        preview.src = e.target.result;
                    }
                };

                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

</body>
</html>