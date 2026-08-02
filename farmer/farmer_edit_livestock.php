<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();
include '../db_connect.php';

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];
$livestock_id = $_GET['livestock_id'] ?? null;

if (!$livestock_id) {
    header("Location: view_livestock.php");
    exit();
}

$sql = "SELECT name, farm_name, email, phone_number, profile_image 
        FROM farmer 
        WHERE farmer_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$farmer_id]);
$farmer = $stmt->fetch(PDO::FETCH_ASSOC);

$name = $farmer['farm_name']; 

$imageFolder = "uploads/";
if (!empty($farmer['profile_image'])) {
    $imagePath = $imageFolder . $farmer['profile_image'];
} else {
    $imagePath = $imageFolder . "default.png";
}

if (!file_exists($imagePath)) {
    $imagePath = $imageFolder . "default.png";
}

$sql = "SELECT l.*, 
        a.start_time, a.end_time, a.auction_id, ad.amount as current_deposit,
        h.vaccination, h.medicine, h.vitamin
        FROM livestock l
        LEFT JOIN auction a ON l.livestock_id = a.livestock_id
        LEFT JOIN auction_deposits ad ON a.auction_id = ad.auction_id
        LEFT JOIN health h ON l.livestock_id = h.livestockid
        WHERE l.livestock_id = ? AND l.farmer_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$livestock_id, $farmer_id]);
$livestock = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$livestock) {
    die("Error: Record not found or unauthorized access.");
}

$stmtSvc = $pdo->prepare("SELECT * FROM harvestservice WHERE livestockid = ?");
$stmtSvc->execute([$livestock_id]);
$services = $stmtSvc->fetchAll(PDO::FETCH_ASSOC); 
$hasService = (count($services) > 0);
$start_val = $livestock['start_time'] ? date('Y-m-d\TH:i', strtotime($livestock['start_time'])) : '';
$end_val = $livestock['end_time'] ? date('Y-m-d\TH:i', strtotime($livestock['end_time'])) : '';

$stmtDeliv = $pdo->prepare("SELECT * FROM livestock_delivery_options WHERE livestock_id = ?");
$stmtDeliv->execute([$livestock_id]);
$deliveryOptions = $stmtDeliv->fetchAll(PDO::FETCH_ASSOC);

$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND user_type = 'farmer' AND is_read = FALSE");
$stmtUnread->execute(['uid' => $farmer_id]);
$unreadCount = $stmtUnread->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Livestock | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">
    <style>
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
        
        .form-container { 
            max-width: 850px; margin: 0 auto 40px; background: rgba(255, 255, 255, 0.6); 
            backdrop-filter: blur(15px); padding: 45px; border-radius: 30px; 
            border: 1px solid rgba(144, 202, 249, 0.4); box-shadow: 0 15px 35px rgba(0,0,0,0.05); 
        }

        .main-title { text-align: center; text-transform: uppercase; color: #0d1b2a; font-family: 'Cinzel', serif; font-size: 28px; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 15px; margin-bottom: 40px; }
        .section-title { margin-top: 30px; font-size: 1.1em; font-weight: bold; color: #1976d2; font-family: 'Cinzel', serif; border-left: 4px solid #90caf9; padding-left: 12px; margin-bottom: 20px; }
        
        .form-row-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 10px; }
        .field-group label {
            display: block;
            font-family: 'Cinzel', serif;
            font-size: 0.8rem;
            margin-bottom: 5px;
            color: var(--navy);
        }

        .row-inputs {
            display: grid;
            grid-template-columns: 1fr 120px 120px 50px;
            gap: 15px;
            align-items: flex-end;
        }
        
        label { display: block; font-size: 0.9em; margin-top: 15px; margin-bottom: 8px; color: #333; font-weight: bold; }
        
        input, select, textarea { width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.8); border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; outline: none; transition: 0.3s; }
        input:focus { border-color: #90caf9; box-shadow: 0 0 8px rgba(144, 202, 249, 0.3); }
        input[type="checkbox"] {
            width: auto !important;
            margin: 0;
        }
        #serviceSection {
            text-align: left;
        }

        .gallery-wrapper { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px; margin-top: 10px; }
        .gallery-item { position: relative; aspect-ratio: 1/1; border-radius: 15px; overflow: hidden; border: 1px solid rgba(0,0,0,0.1); background: #fff; }
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; }
        .existing-tag { position: absolute; bottom: 0; width: 100%; background: rgba(25, 118, 210, 0.8); color: white; font-size: 9px; text-align: center; padding: 2px 0; }
        
        .add-image-btn { aspect-ratio: 1/1; border: 2px dashed #90caf9; border-radius: 15px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(144, 202, 249, 0.05); cursor: pointer; color: #1976d2; }

        .btn-vintage { background: linear-gradient(135deg, #1976d2, #1565c0); color: white; padding: 16px; border: none; border-radius: 50px; width: 60%; font-size: 1.1rem; cursor: pointer; display: block; margin: 40px auto 0; font-family: 'Cinzel', serif; font-weight: bold; text-transform: uppercase; }
        #auctionDetails, #serviceSection { background: rgba(144, 202, 249, 0.05); padding: 25px; border: 2px dashed #90caf9; margin-top: 15px; border-radius: 15px; }
        .button-group {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 40px;
        }

        .btn-vintage {
            width: 250px; 
            margin: 0; 
        }

        .btn-cancel {
            background: #e0e0e0;
            color: #333;
            padding: 16px;
            border: none;
            border-radius: 50px;
            width: 250px;
            font-size: 1.1rem;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            text-transform: uppercase;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-cancel:hover {
            background: #d5d5d5;
            color: #000;
        }

        #deliverySection {
            background: rgba(144, 202, 249, 0.05);
            padding: 25px;
            border: 2px dashed #90caf9;
            margin-top: 15px;
            border-radius: 15px;
        }

        .delivery-row {
            margin-bottom: 15px;
        }

        .btn-add-option {
            width: 100%;
            padding: 12px;
            background: white;
            border: 2px dashed #90caf9;
            color: #1976d2;
            border-radius: 10px;
            font-family: 'Cinzel', serif;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .btn-add-option:hover {
            background: rgba(144, 202, 249, 0.1);
            border-color: #1565c0;
            color: #1565c0;
        }

        .btn-remove-row {
            background: #fff1f0;
            color: #ff4d4f;
            border: 1px solid #ffccc7;
            border-radius: 10px;
            height: 48px; 
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-remove-row:hover {
            background: #ff4d4f;
            color: white;
            border-color: #ff4d4f;
        }

        .delivery-grid {
            display: grid; 
            grid-template-columns: 1fr 150px 50px; 
            gap: 15px; 
            align-items: center;
        }

        .gallery-item { position: relative; }
        .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 77, 79, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            z-index: 10;
            transition: 0.3s;
        }
        .remove-btn:hover { background: #ff4d4f; transform: scale(1.1); }
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
                    <li><a href="view_livestock.php"><i class="fas fa-list" class="active"></i> View All Livestock</a></li>
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


        <div class="form-container">
            <form action="process_edit_livestock.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="livestock_id" value="<?= $livestock_id ?>">
                
                <h2 class="main-title">Edit Livestock Details</h2>

                <div class="section-title"><i class="fas fa-paw"></i> Primary Details</div>
                <label>Livestock Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($livestock['name']) ?>" required>

                <div class="form-row-grid">
                    <div>
                        <label>Category</label>
                        <select name="category" required>
                            <option value="Cattle" <?= $livestock['category'] == 'Cattle' ? 'selected' : '' ?>>Cattle</option>
                            <option value="Goat" <?= $livestock['category'] == 'Goat' ? 'selected' : '' ?>>Goat</option>
                            <option value="Sheep" <?= $livestock['category'] == 'Sheep' ? 'selected' : '' ?>>Sheep</option>
                        </select>
                    </div>
                    <div>
                        <label>Breed</label>
                        <input type="text" name="breed" value="<?= htmlspecialchars($livestock['breed']) ?>" required>
                    </div>
                </div>

                <div class="section-title"><i class="fas fa-tags"></i> Pricing & Sale Type</div>
                <div class="form-row-grid">
                    <div>
                        <label>Sale Type</label>
                        <select name="sale_type" id="saleTypeSelect">
                            <option value="Fixed" <?= $livestock['sale_type'] == 'Fixed' ? 'selected' : '' ?>>Direct Purchase</option>
                            <option value="Auction" <?= $livestock['sale_type'] == 'Auction' ? 'selected' : '' ?>>Auction</option>
                        </select>
                    </div>
                    <div>
                        <label id="priceLabel"><?= $livestock['sale_type'] == 'Auction' ? 'Starting Bid (RM)' : 'Price (RM)' ?></label>
                        <input type="number" step="0.01" name="price" value="<?= $livestock['price'] ?>" required>
                    </div>
                </div>

                <div id="auctionDetails" style="display: <?= $livestock['sale_type'] == 'Auction' ? 'block' : 'none' ?>;">
                    <div class="form-row-grid">
                        <div>
                            <label>Start Date & Time</label>
                            <input type="datetime-local" name="auction_start_time" value="<?= $start_val ?>">
                        </div>
                        <div>
                            <label>End Date & Time</label>
                            <input type="datetime-local" name="auction_end_time" value="<?= $end_val ?>">
                        </div>
                    </div>
                    <label>Required Deposit (RM)</label>
                    <input type="number" step="0.01" name="deposit_amount" value="<?= htmlspecialchars($livestock['current_deposit'] ?? '0') ?>">
                </div>

                <div class="section-title"><i class="fas fa-chart-bar"></i> Physical Data</div>
                <div class="form-row-grid">
                    <div>
                        <label>Age (months)</label>
                        <input type="number" name="age" value="<?= htmlspecialchars($livestock['age'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Gender</label>
                        <select name="gender">
                            <option value="Male" <?= ($livestock['gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($livestock['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div>
                        <label>Weight (KG)</label>
                        <input type="number" step="0.01" name="weight" value="<?= htmlspecialchars($livestock['weight'] ?? '') ?>">
                    </div>
                </div>

                <label>Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($livestock['description']) ?></textarea>

                <div class="section-title"><i class="fas fa-images"></i> Media Gallery</div>
                <div class="gallery-wrapper" id="imageGallery">
                    <?php 
                    $images = !empty($livestock['image']) ? explode(',', $livestock['image']) : [];
                    foreach ($images as $index => $img): 
                        $imgTrim = trim($img);
                        if (empty($imgTrim)) continue;
                        $imgSrc = (strpos($imgTrim, '../') === false) ? 'uploads/' . $imgTrim : $imgTrim;
                        ?>
                        <div class="gallery-item" id="img-container-<?= $index ?>">
                            <img src="<?= $imgSrc ?>">
                            <div class="existing-tag">CURRENT</div>
                            <button type="button" class="remove-btn" onclick="removeExistingImage('<?= $imgTrim ?>', 'img-container-<?= $index ?>')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>

                    <div class="add-image-btn" onclick="document.getElementById('hidden-input').click();">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add More</span>
                    </div>
                </div>

                <input type="hidden" name="removed_images" id="removed_images" value="">

                <input type="file" name="images[]" id="hidden-input" multiple accept="image/*" style="display:none;">

                <div class="section-title"><i class="fas fa-notes-medical"></i> Health Records</div>
                <div class="form-row-grid">
                    <input type="text" name="vaccination" value="<?= htmlspecialchars($livestock['vaccination'] ?? '') ?>" placeholder="Vaccination">
                    <input type="text" name="medicine" value="<?= htmlspecialchars($livestock['medicine'] ?? '') ?>" placeholder="Medicine">
                    <input type="text" name="vitamin" value="<?= htmlspecialchars($livestock['vitamin'] ?? '') ?>" placeholder="Vitamin">
                </div>

                <div class="section-title"><i class="fas fa-plus-circle"></i> Services</div>
                <label for="toggleService" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="toggleService" name="provideService" <?= $hasService ? 'checked' : '' ?>> 
                    <span>I PROVIDE SERVICES</span>
                </label>

                <div id="serviceSection" style="display: <?= $hasService ? 'block' : 'none' ?>; background: rgba(144, 202, 249, 0.05); padding: 25px; border: 2px dashed #90caf9; margin-top: 15px; border-radius: 15px;">
                    <div id="service-repeater">
                        <?php if ($hasService): ?>
                            <?php foreach ($services as $svc): ?>
                                <div class="service-row" style="margin-bottom: 15px;">
                                    <div class="form-row-grid" style="grid-template-columns: 1fr 150px 50px; align-items: center;">
                                        <div class="field-group">
                                        <label>Service Type</label>
                                        <input type="text" name="serviceType[]" value="<?= htmlspecialchars($svc['servicetype']) ?>" placeholder="e.g. Slaughtering" required>
                                    </div>
                                    <div class="field-group">
                                        <label>Fee (RM)</label>
                                        <input type="number" step="0.01" name="serviceFee[]" value="<?= $svc['servicefee'] ?>" placeholder="Fee (RM)" required>
                                    </div>
                                        <button type="button" onclick="this.closest('.service-row').remove()" style="background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; border-radius: 10px; height: 45px; cursor: pointer;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="service-row" style="margin-bottom: 15px;">
                                <div class="form-row-grid" style="grid-template-columns: 1fr 150px 50px; align-items: center;">
                                    <input type="text" name="serviceType[]" placeholder="e.g. Slaughtering">
                                    <input type="number" step="0.01" name="serviceFee[]" placeholder="0.00">
                                    <button type="button" onclick="this.closest('.service-row').remove()" style="background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; border-radius: 10px; height: 45px; cursor: pointer;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="button" onclick="addServiceRow()" style="width: 100%; padding: 12px; background: white; border: 2px dashed #90caf9; color: #1976d2; border-radius: 10px; font-family: 'Cinzel'; cursor: pointer; font-weight: bold; transition: 0.3s;">
                        <i class="fas fa-plus-circle"></i> Add Another Service
                    </button>
                </div>

                <div class="section-title"><i class="fas fa-truck"></i> Delivery & Transport Options</div>
                <div id="deliverySection" style="background: rgba(144, 202, 249, 0.05); padding: 25px; border: 2px dashed #90caf9; margin-top: 15px; border-radius: 15px;">
                    <p style="font-family: 'PT Serif'; font-size: 0.85rem; color: #666; margin-bottom: 15px;">
                        Define transport methods available for this animal (e.g., Self-Pickup, Lorry Transport).
                    </p>

                    <div id="delivery-repeater">
                        <?php if (!empty($deliveryOptions)): ?>
                            <?php foreach ($deliveryOptions as $option): ?>
                                <div class="delivery-row" style="margin-bottom: 15px;">
                                    <div class="form-row-grid" style="grid-template-columns: 1fr 120px 100px 45px; align-items: center;">
                                        <div class="field-group">
                                        <label>Method Name</label>
                                        <input type="text" name="delivery_type[]" value="<?= htmlspecialchars($option['method_name']) ?>" placeholder="Method Name" required>
                                    </div>
                                    <div class="field-group">
                                        <label>Max Weight (KG)</label>
                                        <input type="number" step="0.01" name="delivery_max_capacity[]" value="<?= $option['max_capacity'] ?? '' ?>" placeholder="Max KG" required title="Max weight this vehicle can carry">
                                    </div>
                                    <div class="field-group">
                                        <label>Fee (RM)</label>
                                        <input type="number" step="0.01" name="delivery_fee[]" value="<?= $option['delivery_fee'] ?>" placeholder="Fee (RM)" required>
                                    </div>
                                        <button type="button" onclick="this.closest('.delivery-row').remove()" style="background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; border-radius: 10px; height: 45px; cursor: pointer;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="delivery-row" style="margin-bottom: 15px;">
                                <div class="form-row-grid" style="grid-template-columns: 1fr 150px 50px; align-items: center;">
                                    <div class="field-group">
                                        <input type="text" name="delivery_type[]" placeholder="e.g. Self-Pickup" required>
                                    </div>
                                    <div class="field-group">
                                        <input type="number" step="0.01" name="delivery_max_capacity[]" placeholder="Max KG" required>
                                    </div>
                                    <div class="field-group">
                                        <input type="number" step="0.01" name="delivery_fee[]" placeholder="0.00" required>
                                    </div>
                                    <button type="button" onclick="this.closest('.delivery-row').remove()" style="background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; border-radius: 10px; height: 45px; cursor: pointer;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="button" onclick="addDeliveryRow()" style="width: 100%; padding: 12px; background: white; border: 2px dashed #90caf9; color: #1976d2; border-radius: 10px; font-family: 'Cinzel'; cursor: pointer; font-weight: bold; transition: 0.3s;">
                        <i class="fas fa-plus-circle"></i> Add Another Option
                    </button>
                </div>

                <div class="section-title">Inventory Status</div>
                <select name="availability_status">
                    <option value="Available" <?= $livestock['availability_status'] == 'Available' ? 'selected' : '' ?>>Available</option>
                    <option value="Unavailable" <?= $livestock['availability_status'] == 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                    <option value="Sold" <?= $livestock['availability_status'] == 'Sold' ? 'selected' : '' ?>>Sold</option>
                </select>

                <div class="button-group">
                    <a href="view_livestock.php" class="btn-cancel">
                        <i class="fas fa-times" style="margin-right: 8px;"></i> Cancel
                    </a>
                    <button type="submit" class="btn-vintage">
                        <i class="fas fa-save" style="margin-right: 8px;"></i> Update Livestock
                    </button>
                </div>
            </form>
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

    document.getElementById("toggleService").addEventListener("change", function() {
        document.getElementById("serviceSection").style.display = this.checked ? "block" : "none";
    });

    document.getElementById("saleTypeSelect").addEventListener("change", function() {
        const isAuction = this.value === "Auction";
        document.getElementById("auctionDetails").style.display = isAuction ? "block" : "none";
        document.getElementById("priceLabel").innerText = isAuction ? "Starting Bid (RM)" : "Price (RM)";
    });

    const hiddenInput = document.getElementById('hidden-input');
    const gallery = document.getElementById('imageGallery');
    let allFiles = new DataTransfer(); 
    let removedImages = [];

    function removeExistingImage(fileName, containerId) {
        if (confirm("Remove this image? Changes will be saved once you update.")) {
            removedImages.push(fileName);
            document.getElementById('removed_images').value = removedImages.join(',');

            const container = document.getElementById(containerId);
            container.style.transition = "0.3s";
            container.style.opacity = "0.3";
            container.style.pointerEvents = "none"; 
            container.querySelector('.existing-tag').innerText = "REMOVING";
            container.querySelector('.existing-tag').style.background = "#ff4d4f";
        }
    }

    hiddenInput.addEventListener('change', function() {
        Array.from(this.files).forEach((file, index) => {
            const fileId = `new-img-${Date.now()}-${index}`;
            allFiles.items.add(file); 

            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'gallery-item';
                div.id = fileId;
                div.innerHTML = `
                    <img src="${e.target.result}">
                    <div class="existing-tag" style="background:#4caf50;">NEW</div>
                    <button type="button" class="remove-btn" onclick="removeNewImage('${fileId}', '${file.name}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                gallery.insertBefore(div, gallery.querySelector('.add-image-btn'));
            }
            reader.readAsDataURL(file);
        });
        this.files = allFiles.files;
    });

    function removeNewImage(containerId, fileName) {
        document.getElementById(containerId).remove();

        const newFiles = new DataTransfer();
        Array.from(allFiles.files).forEach(file => {
            if (file.name !== fileName) newFiles.items.add(file);
        });
        allFiles = newFiles;
        hiddenInput.files = allFiles.files;
    }

    function addDeliveryRow() {
        const container = document.getElementById('delivery-repeater');
        const newRow = document.createElement('div');
        newRow.className = 'delivery-row';
        newRow.style.marginBottom = "15px";
        newRow.innerHTML = `
            <div class="row-inputs">
            <div class="field-group">
                <input type="text" name="delivery_type[]" placeholder="e.g. Lorry Transport" required>
            </div>
            <div class="field-group">
                <input type="number" step="0.1" name="delivery_max_capacity[]" placeholder="Max KG" required>
            </div>
            <div class="field-group">
                <input type="number" step="0.01" name="delivery_fee[]" placeholder="0.00" required>
            </div>
                <button type="button" onclick="this.closest('.delivery-row').remove()" class="btn-remove-row">
                    <i class="fas fa-trash"></i>
                </button>
        </div>`;
        container.appendChild(newRow);
    }

    function addServiceRow() {
        const container = document.getElementById('service-repeater');
        const newRow = document.createElement('div');
        newRow.className = 'service-row';
        newRow.style.marginBottom = "15px";
        newRow.innerHTML = `
            <div class="form-row-grid" style="grid-template-columns: 1fr 150px 50px; align-items: center;">
            <div class="field-group">
                <input type="text" name="serviceType[]" placeholder="Service Type" required>
            </div>
            <div class="field-group">
                <input type="number" step="0.01" name="serviceFee[]" placeholder="0.00" required>
            </div>
                <button type="button" onclick="this.closest('.service-row').remove()" class="btn-remove-row">
                    <i class="fas fa-trash"></i>
                </button>
            </div>`;
        container.appendChild(newRow);
    }
</script>
</body>
</html>