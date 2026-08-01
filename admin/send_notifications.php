<?php
session_start();
require_once '../db_connect.php';

// Access Control
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$message_sent = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $target = $_POST['target_group']; 
    $title = $_POST['title'];
    $message = $_POST['message'];

    $sql = "";
    if ($target === 'farmer') {
        $sql = "INSERT INTO notifications (user_id, user_type, title, message) 
                SELECT farmer_id, 'farmer', :title, :message FROM farmer";
    } elseif ($target === 'customer') {
        $sql = "INSERT INTO notifications (user_id, user_type, title, message) 
                SELECT customer_id, 'customer', :title, :message FROM customer";
    } else {
        $sql = "INSERT INTO notifications (user_id, user_type, title, message) 
                SELECT farmer_id, 'farmer', :title, :message FROM farmer
                UNION ALL
                SELECT customer_id, 'customer', :title, :message FROM customer";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['title' => $title, 'message' => $message]);
    $message_sent = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Broadcast Center | Admin Portal</title>
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

        .broadcast-container {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding-top: 20px;
        }

        .broadcast-card { 
            width: 100%;
            max-width: 700px; 
            background: white; 
            border: 1px solid var(--border); 
            padding: 40px; 
            box-shadow: 8px 8px 0px var(--gold); 
        }

        h2 { font-family: 'Cinzel', serif; color: var(--charcoal); margin-top: 0; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        .form-group { margin-bottom: 25px; }
        label { display: block; font-family: 'Cinzel', serif; font-weight: bold; margin-bottom: 8px; font-size: 0.85rem; color: var(--charcoal); }
        
        select, input, textarea { 
            width: 100%; padding: 12px; border: 1px solid #ddd; 
            font-family: 'Raleway', sans-serif; box-sizing: border-box;
            background: #fff; transition: 0.3s;
        }
        
        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 5px rgba(184, 155, 94, 0.2);
        }

        .btn-send { 
            width: 100%; padding: 15px; background: var(--charcoal); 
            color: white; border: none; font-family: 'Cinzel', serif; 
            cursor: pointer; transition: 0.3s; font-weight: bold;
            letter-spacing: 1px;
        }
        .btn-send:hover { background: var(--gold); }

        .success-msg { 
            background: #e8f5e9; color: #2e7d32; padding: 15px; 
            margin-bottom: 25px; text-align: center; border: 1px solid #2e7d32;
            font-family: 'Raleway', sans-serif;
        }
    </style>
</head>
<body id="bodyTag">

<div class="sidebar" id="sidebar">
    <div id="sidebarCollapse"><i class="fas fa-chevron-left" id="toggleIcon"></i></div>
    <div class="sidebar-header"><h3>Admin Portal</h3></div>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php"><i class="fas fa-th-large"></i> <span class="link-text">Overview</span></a></li>
        <li><a href="manage_users.php"><i class="fas fa-users"></i> <span class="link-text">Manage Users</span></a></li>
        <li><a href="verify_listings.php"><i class="fas fa-check-circle"></i> <span class="link-text">Verify Listings</span></a></li>
        <li><a href="manage_auctions.php"><i class="fas fa-gavel"></i> <span class="link-text">Manage Auctions</span></a></li>
        <li><a href="broadcast.php" class="active"><i class="fas fa-bullhorn"></i> <span class="link-text">Send Notifications</span></a></li>
        <li><a href="logout.php" style="margin-top: 50px; color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="top-bar">
        <h1>Broadcast Center</h1>
        <div class="date" style="font-family: 'Cinzel';"><i class="fas fa-calendar"></i> <?= date('D, d M Y') ?></div>
    </div>

     <div class="breadcrumbs">
            <i class="fas fa-home"></i><a href="admin_dashboard.php"> Dashboard</a><span> > </span> Send Notifications
        </div>

    <div class="broadcast-container">
        <div class="broadcast-card">
            <h2><i class="fas fa-paper-plane"></i> Send Announcement</h2>

            <?php if ($message_sent): ?>
                <div class="success-msg">
                    <i class="fas fa-check-circle"></i> Message broadcasted successfully to all selected users!
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_group" required>
                        <option value="all">Everyone (Farmers & Customers)</option>
                        <option value="farmer">Farmers Only</option>
                        <option value="customer">Customers Only</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Subject / Title</label>
                    <input type="text" name="title" placeholder="e.g., System Maintenance Alert" required>
                </div>

                <div class="form-group">
                    <label>Message Content</label>
                    <textarea name="message" rows="6" placeholder="Write your announcement here..." required></textarea>
                </div>

                <button type="submit" class="btn-send">Send Notification</button>
            </form>
        </div>
    </div>
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
</script>
</body>
</html>