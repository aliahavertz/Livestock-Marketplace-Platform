<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$query = "SELECT l.*, f.farm_name as farmer_name, h.vaccination, h.medicine, h.vitamin
          FROM livestock l 
          JOIN farmer f ON l.farmer_id = f.farmer_id 
          LEFT JOIN health h ON l.livestock_id = h.livestockID
          WHERE l.availability_status = 'Pending' 
          ORDER BY l.livestock_id DESC";
$stmt = $pdo->query($query);
$pending_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Listings | Admin Portal</title>
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

        body { margin: 0; font-family: 'Raleway', sans-serif; background: var(--cream); display: flex; transition: 0.3s; }

        .breadcrumbs { margin-bottom: 20px; font-size: 0.8rem; font-family: 'Cinzel', serif; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;}
        .breadcrumbs a { color: var(--gold); text-decoration: none; }
        .breadcrumbs i { color: var(--gold);}

        .sidebar {
            width: var(--sidebar-width);
            background: var(--charcoal);
            color: white;
            height: 100vh;
            position: fixed;
            border-right: 3px solid var(--gold);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            overflow: hidden;
        }

        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #444;
            font-family: 'Cinzel', serif;
            white-space: nowrap;
        }

        #sidebarCollapse {
            position: absolute; top: 15px; right: -15px;
            background: var(--gold); color: white;
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; border: 2px solid var(--charcoal); z-index: 1001;
            transition: 0.3s;
        }

        .sidebar.collapsed #sidebarCollapse { right: 20px; }

        .nav-links { list-style: none; padding: 0; margin-top: 20px; }
        .nav-links li a {
            display: flex; align-items: center; padding: 15px 25px;
            color: #ccc; text-decoration: none; transition: 0.3s;
            font-family: 'Cinzel', serif; font-size: 0.9rem;
        }

        .nav-links i { margin-right: 20px; width: 20px; font-size: 1.1rem; text-align: center; }

        .sidebar.collapsed .link-text, .sidebar.collapsed .sidebar-header h3 { display: none; }

        .nav-links li a:hover, .nav-links li a.active {
            background: var(--gold);
            color: white;
        }

        .main-content { 
            margin-left: var(--sidebar-width); 
            flex: 1; padding: 30px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        body.collapsed-active .main-content { margin-left: var(--sidebar-collapsed-width); }

        .top-bar {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 10px;
        }
        .top-bar h1 { font-family: 'Cinzel', serif; margin: 0; color: var(--charcoal); }

        .verify-card { 
            background: #fff; 
            border: 1px solid var(--border); 
            display: flex; 
            margin-bottom: 30px; 
            box-shadow: 6px 6px 0px var(--gold); 
            overflow: hidden; 
        }
        
        .animal-img-container { 
            width: 300px; 
            background: #eee; 
            border-right: 1px solid var(--border); 
            display: flex;
            flex-direction: column;
        }

        .main-preview {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-bottom: 1px solid #ddd;
        }

        .thumbnail-strip {
            display: flex;
            gap: 5px;
            padding: 10px;
            overflow-x: auto;
            background: #f5f5f5;
        }

        .thumb-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            cursor: pointer;
            border: 1px solid #ccc;
            transition: 0.2s;
        }

        .thumb-img:hover {
            border-color: var(--gold);
            transform: scale(1.05);
        }
        .animal-img { width: 100%; height: 100%; object-fit: cover; }
        
        .details { padding: 25px; flex: 1; }
        .details h3 { font-family: 'Cinzel', serif; margin: 0 0 15px 0; color: #8b0000; font-size: 1.5rem; }
        
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
        .info-item { font-size: 0.95em; line-height: 1.6; }
        
        .description-box { background: #fdfae0; padding: 15px; border-left: 4px solid var(--gold); margin: 15px 0; font-size: 0.9em; }
        
        .health-badge-section { background: #e8f5e9; padding: 12px; border-radius: 4px; font-size: 0.85em; border: 1px solid #c8e6c9; }
        
        .action-btns { 
            padding: 20px; 
            width: 180px; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            gap: 12px; 
            background: #fafafa; 
            border-left: 1px solid #ddd; 
        }
        
        .btn { 
            padding: 12px; border: none; font-family: 'Cinzel', serif; font-weight: bold; 
            cursor: pointer; text-decoration: none; text-align: center; 
            font-size: 0.85em; transition: 0.3s; 
        }
        .btn-approve { background: #2e7d32; color: white; }
        .btn-reject { background: #c62828; color: white; }
        .btn:hover { opacity: 0.8; transform: translateY(-2px); }
    </style>
</head>
<body id="bodyTag">

<div class="sidebar" id="sidebar">
    <div id="sidebarCollapse"><i class="fas fa-chevron-left" id="toggleIcon"></i></div>
    <div class="sidebar-header"><h3>Admin Portal</h3></div>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php"><i class="fas fa-th-large"></i> <span class="link-text">Overview</span></a></li>
        <li><a href="manage_users.php"><i class="fas fa-users"></i> <span class="link-text">Manage Users</span></a></li>
        <li><a href="verify_listings.php" class="active"><i class="fas fa-check-circle"></i> <span class="link-text">Verify Listings</span></a></li>
        <li><a href="manage_auctions.php"><i class="fas fa-gavel"></i> <span class="link-text">Manage Auctions</span></a></li>
        <li><a href="send_notifications.php"><i class="fas fa-bullhorn"></i> <span class="link-text">Send Notifications</span></a></li>
        <li><a href="logout.php" style="margin-top: 50px; color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="top-bar">
        <h1>Verify Listings</h1>
        <div class="date" style="font-family: 'Cinzel';"><i class="fas fa-calendar"></i> <?= date('D, d M Y') ?></div>
    </div>
     <div class="breadcrumbs">
            <i class="fas fa-home"></i><a href="admin_dashboard.php"> Dashboard</a><span> > </span> Verify Listings
        </div>


    <?php if ($pending_list): ?>
        <?php foreach ($pending_list as $animal): ?>
            <div class="verify-card">
                <div class="animal-img-container">
                    <?php 
                    $image_list = !empty($animal['image']) ? explode(',', $animal['image']) : [];
                    $first_image = !empty($image_list) ? trim($image_list[0]) : '';
                    $main_path = !empty($first_image) ? '../farmer/uploads/'.$first_image : '../assets/no-image.png';
                    ?>
                    
                    <img src="<?= $main_path ?>" 
                    class="main-preview" 
                    id="main_<?= $animal['livestock_id'] ?>" 
                    alt="Main View">

                    <?php if(count($image_list) > 1): ?>
                        <div class="thumbnail-strip">
                            <?php foreach($image_list as $img): ?>
                                <?php $thumb_path = '../farmer/uploads/'.trim($img); ?>
                                <img src="<?= $thumb_path ?>" 
                                class="thumb-img" 
                                onclick="document.getElementById('main_<?= $animal['livestock_id'] ?>').src='<?= $thumb_path ?>'"
                                alt="Thumbnail">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="details">
                    <h3><?= htmlspecialchars($animal['name']) ?> <small style="color:#666;">(<?= htmlspecialchars($animal['breed']) ?>)</small></h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <p><strong>Category:</strong> <?= htmlspecialchars($animal['category']) ?></p>
                            <p><strong>Gender:</strong> <?= htmlspecialchars($animal['gender']) ?></p>
                            <p><strong>Age:</strong> <?= htmlspecialchars($animal['age']) ?> Months</p>
                        </div>
                        <div class="info-item">
                            <p><strong>Farm:</strong> <?= htmlspecialchars($animal['farmer_name']) ?></p>
                            <p><strong>Price:</strong> RM <?= number_format($animal['price'], 2) ?></p>
                            <p><strong>Sale Type:</strong> <?= htmlspecialchars($animal['sale_type']) ?></p>
                        </div>
                    </div>
                    <div class="description-box"><?= nl2br(htmlspecialchars($animal['description'])) ?></div>
                    <div class="health-badge-section">
                        <strong>Medical Record:</strong><br>
                        Vac: <?= htmlspecialchars($animal['vaccination'] ?: 'N/A') ?> | 
                        Med: <?= htmlspecialchars($animal['medicine'] ?: 'N/A') ?> |
                        Vit: <?= htmlspecialchars($animal['vitamin'] ?: 'N/A') ?>
                    </div>
                </div>

                <div class="action-btns">
                    <a href="process_verification.php?action=approve&id=<?= $animal['livestock_id'] ?>" 
                       class="btn btn-approve" onclick="return confirm('Approve this listing?')">
                       <i class="fas fa-check-circle"></i> Approve
                    </a>
                    
                    <button class="btn btn-reject" onclick="rejectWithReason(<?= $animal['livestock_id'] ?>)">
                        <i class="fas fa-times-circle"></i> Reject
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="verify-card" style="padding: 40px; justify-content: center;">
            <p style="font-family: 'Cinzel'; color: #666;">No pending listings awaiting verification.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const bodyTag = document.getElementById('bodyTag');
    const toggleBtn = document.getElementById('sidebarCollapse');
    const toggleIcon = document.getElementById('toggleIcon');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        bodyTag.classList.toggle('collapsed-active');
        toggleIcon.classList.toggle('fa-chevron-left');
        toggleIcon.classList.toggle('fa-chevron-right');
    });

    function rejectWithReason(id) {
        let reason = prompt("Enter reason for rejection (this will be sent to the farmer):");
        if (reason != null && reason.trim() !== "") {
            window.location.href = "process_verification.php?action=reject&id=" + id + "&reason=" + encodeURIComponent(reason);
        } else if (reason != null) {
            alert("A reason is required to reject a listing.");
        }
    }
</script>
</body>
</html>