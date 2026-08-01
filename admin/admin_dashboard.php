<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND user_type = 'admin' AND is_read = FALSE");
$stmt->execute(['uid' => $_SESSION['admin_id']]);
$unread_count = $stmt->fetchColumn();

$total_pending = $pdo->query("SELECT COUNT(*) FROM livestock WHERE availability_status = 'Pending'")->fetchColumn();
$total_customers = $pdo->query("SELECT COUNT(*) FROM customer")->fetchColumn();
$total_farmers = $pdo->query("SELECT COUNT(*) FROM farmer")->fetchColumn();
$total_users = $total_customers + $total_farmers;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Administrator Dashboard | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Raleway:wght@300;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --gold: #b89b5e;
            --charcoal: #2c2c2c;
            --cream: #f9f7f2;
            --border: #453c34;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
        }
        
        body { 
            margin: 0; 
            font-family: 'Raleway', sans-serif; 
            background: var(--cream); 
            display: flex; 
            transition: all 0.3s ease;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: var(--charcoal);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 3px solid var(--gold);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-x: hidden;
            z-index: 1000;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #444;
            font-family: 'Cinzel', serif;
            white-space: nowrap;
        }

        #sidebarCollapse {
            position: absolute;
            top: 15px;
            right: -15px;
            background: var(--gold);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid var(--charcoal);
            z-index: 1001;
            transition: 0.3s;
        }

        .sidebar.collapsed #sidebarCollapse {
            right: 20px; 
        }

        .nav-links { list-style: none; padding: 0; margin-top: 20px; }
        .nav-links li a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: #ccc;
            text-decoration: none;
            transition: 0.3s;
            font-family: 'Cinzel', serif;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .nav-links li a i { 
            margin-right: 20px; 
            font-size: 1.2rem;
            min-width: 25px;
            text-align: center;
        }

        .sidebar.collapsed .link-text {
            display: none;
        }

        .nav-links li a:hover, .nav-links li a.active {
            background: var(--gold);
            color: white;
        }

        .main-content { 
            margin-left: var(--sidebar-width); 
            flex: 1; 
            padding: 30px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.collapsed-active .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            border-bottom: 2px solid var(--border);
            padding-bottom: 10px;
        }
        .top-bar h1 { font-family: 'Cinzel', serif; margin: 0; color: var(--charcoal); }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card {
            background: white;
            padding: 20px;
            border: 1px solid var(--border);
            box-shadow: 5px 5px 0px var(--gold);
            text-align: center;
        }
        .stat-card h3 { font-family: 'Cinzel', serif; font-size: 0.8rem; color: #666; margin: 0; }
        .stat-card .value { font-size: 2rem; font-weight: bold; color: var(--charcoal); margin: 10px 0; }

        .dashboard-section {
            background: white;
            padding: 25px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }
        .section-header {
            font-family: 'Cinzel', serif;
            border-left: 5px solid var(--gold);
            padding-left: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body id="bodyTag">

<div class="sidebar" id="sidebar">
    <div id="sidebarCollapse">
        <i class="fas fa-chevron-left" id="toggleIcon"></i>
    </div>

    <div class="sidebar-header">
        <h3 id="logoText">Admin Portal</h3>
    </div>
    <ul class="nav-links">
        <li><a href="#" class="active"><i class="fas fa-th-large"></i> <span class="link-text">Overview</span></a></li>
        <li><a href="manage_users.php"><i class="fas fa-users"></i> <span class="link-text">Manage Users</span></a></li>
        <li><a href="verify_listings.php"><i class="fas fa-check-circle"></i> <span class="link-text">Verify Listings</span></a></li>
        <li><a href="manage_auctions.php"><i class="fas fa-gavel"></i> <span class="link-text">Manage Auctions</span></a></li>
        <!-- <li><a href="manage_reports.php"><i class="fas fa-file-invoice-dollar"></i> <span class="link-text">Manage Reports</span></a></li> -->
        <li><a href="send_notifications.php"><i class="fas fa-bullhorn"></i> <span class="link-text">Send Notifications</span></a></li>
        <li><a href="logout.php" style="margin-top: 50px; color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="top-bar">
        <h1>Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?></h1>
        <div class="date"><i class="fas fa-calendar"></i> <?= date('D, d M Y') ?></div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Pending Listings</h3>
            <div class="value"><?= $total_pending ?></div>
            <a href="verify_listings.php" style="color: var(--gold); font-size: 0.8rem; text-decoration: none;">View All</a>
        </div>
        <div class="stat-card">
            <h3>Total Users</h3>
            <div class="value"><?= $total_users ?></div>
            <a href="manage_users.php" style="color: var(--gold); font-size: 0.8rem; text-decoration: none;">Manage</a>
        </div>
        <div class="stat-card">
            <h3>Active Auctions</h3>
            <div class="value">0</div> 
            <a href="manage_auctions.php" style="color: var(--gold); font-size: 0.8rem; text-decoration: none;">Monitor</a>
        </div>
    </div>

    <?php
        $pending_listings = $pdo->query("SELECT l.*, f.name as farmer_name 
                                     FROM livestock l 
                                     JOIN farmer f ON l.farmer_id = f.farmer_id 
                                     WHERE l.availability_status = 'Pending' 
                                     LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="dashboard-section">
        <div class="section-header">
            <h2>Listing Requests</h2>
            <a href="verify_listings.php" style="color: var(--gold); text-decoration: none; font-size: 0.8rem;">View All</a>
        </div>
        
        <table width="100%" style="border-collapse: collapse; margin-top: 15px; text-align: left;">
            <thead>
                <tr style="background: var(--gold); font-family: 'Cinzel', serif; font-size: 0.8rem; color:white;">
                    <th style="padding: 10px; border: 1px solid #ddd;">Livestock Name</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Farmer</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Price</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pending_listings)): ?>
                    <?php foreach ($pending_listings as $listing): ?>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($listing['name']) ?></td>
                            <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($listing['farmer_name']) ?></td>
                            <td style="padding: 10px; border: 1px solid #ddd;">RM <?= number_format($listing['price'], 2) ?></td>
                            <td style="padding: 10px; border: 1px solid #ddd;">
                                <a href="verify_listings.php?id=<?= $listing['livestock_id'] ?>" 
                                   style="background: var(--gold); color: white; padding: 5px 10px; text-decoration: none; font-size: 0.7rem; font-family: 'Cinzel', serif;">
                                   Review
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding: 20px; text-align: center; color: #999;">No pending verifications.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const bodyTag = document.getElementById('bodyTag');
    const toggleBtn = document.getElementById('sidebarCollapse');
    const toggleIcon = document.getElementById('toggleIcon');
    const logoText = document.getElementById('logoText');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        bodyTag.classList.toggle('collapsed-active');

        if (sidebar.classList.contains('collapsed')) {
            toggleIcon.classList.replace('fa-chevron-left', 'fa-chevron-right');
            logoText.style.opacity = '0';
        } else {
            toggleIcon.classList.replace('fa-chevron-right', 'fa-chevron-left');
            logoText.style.opacity = '1';
        }
    });
</script>

</body>
</html>