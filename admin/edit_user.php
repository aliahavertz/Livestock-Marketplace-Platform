<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }

$type = $_GET['type'] ?? ''; 
$id = (int)($_GET['id'] ?? 0);

if ($type === 'customer') {
    $stmt = $pdo->prepare("SELECT * FROM customer WHERE customer_id = ?");
} else {
    $stmt = $pdo->prepare("SELECT * FROM farmer WHERE farmer_id = ?");
}
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) { die("User not found."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $phone = $_POST['phone'] ?? null;
    $address = $_POST['address'] ?? null;
    
    if ($type === 'customer') {
        $update = $pdo->prepare("UPDATE customer SET name = ?, phone_number = ?, address = ? WHERE customer_id = ?");
        $update->execute([$name, $phone, $address, $id]);
    } else {
        $farm_name = $_POST['farm_name'] ?? null;
        $reg_num = $_POST['registration_number'] ?? null;

        $update = $pdo->prepare("UPDATE farmer SET name = ?, farm_name = ?, registration_number = ?, phone_number = ?, address = ? WHERE farmer_id = ?");
        $update->execute([$name, $farm_name, $reg_num, $phone, $address, $id]);
    }
    header("Location: manage_users.php?msg=updated");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit User | Admin Portal</title>
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

        .sidebar {
            width: var(--sidebar-width); background: var(--charcoal); color: white;
            height: 100vh; position: fixed; border-right: 3px solid var(--gold);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1000; overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #444; font-family: 'Cinzel', serif; white-space: nowrap; }
        #sidebarCollapse {
            position: absolute; top: 15px; right: -15px; background: var(--gold); color: white;
            width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; border: 2px solid var(--charcoal); z-index: 1001; transition: 0.3s;
        }
        .sidebar.collapsed #sidebarCollapse { right: 20px; }
        .nav-links { list-style: none; padding: 0; margin-top: 20px; }
        .nav-links li a {
            display: flex; align-items: center; padding: 15px 25px; color: #ccc; text-decoration: none; 
            transition: 0.3s; font-family: 'Cinzel', serif; font-size: 0.9rem;
        }
        .nav-links i { margin-right: 20px; width: 20px; font-size: 1.1rem; text-align: center; }
        .sidebar.collapsed .link-text, .sidebar.collapsed .sidebar-header h3 { display: none; }
        .nav-links li a:hover, .nav-links li a.active { background: var(--gold); color: white; }
        .breadcrumbs { margin-bottom: 20px; font-size: 0.8rem; font-family: 'Cinzel', serif; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;}
        .breadcrumbs a { color: var(--gold); text-decoration: none; }
        .breadcrumbs i { color: var(--gold);}

        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 30px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        body.collapsed-active .main-content { margin-left: var(--sidebar-collapsed-width); }
        .top-bar {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 10px;
        }
        .top-bar h1 { font-family: 'Cinzel', serif; margin: 0; color: var(--charcoal); }
        .edit-container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border: 1px solid #453c34; box-shadow: 10px 10px 0px #b89b5e; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-family: 'Cinzel'; font-weight: bold; margin-bottom: 5px; }
        input { width: 100%; padding: 10px; border: 1px solid #ccc; box-sizing: border-box; }
        .btn-save { background: #2c2c2c; color: white; padding: 10px 20px; border: none; cursor: pointer; font-family: 'Cinzel'; 
            border-radius: 5px;}
        .btn-cancel {
            margin-left: 10px; 
            color: #777; 
            text-decoration: none;
        }
        .btn-cancel:hover {
            color: #F54927;
        }
    </style>
</head>
<body id="bodyTag">

<div class="sidebar" id="sidebar">
    <div id="sidebarCollapse"><i class="fas fa-chevron-left" id="toggleIcon"></i></div>
    <div class="sidebar-header"><h3>Admin Portal</h3></div>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php"><i class="fas fa-th-large"></i> <span class="link-text">Overview</span></a></li>
        <li><a href="manage_users.php" class="active"><i class="fas fa-users"></i> <span class="link-text">Manage Users</span></a></li>
        <li><a href="verify_listings.php"><i class="fas fa-check-circle"></i> <span class="link-text">Verify Listings</span></a></li>
        <li><a href="manage_auctions.php"><i class="fas fa-gavel"></i> <span class="link-text">Manage Auctions</span></a></li>
        <li><a href="send_notifications.php"><i class="fas fa-bullhorn"></i> <span class="link-text">Send Notifications</span></a></li>
        <li><a href="logout.php" style="margin-top: 50px; color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="top-bar">
        <h1>Edit User</h1>
        <div class="date" style="font-family: 'Cinzel';"><i class="fas fa-calendar"></i> <?= date('D, d M Y') ?></div>
    </div>

    <div class="breadcrumbs">
        <i class="fas fa-home"></i><a href="admin_dashboard.php"> Dashboard</a><span> > </span> <a href="manage_users.php"> Manage Users</a><span> > </span> Edit Users
    </div>
    <div class="edit-container">
        <h2>Edit <?= ucfirst($type) ?></h2>
        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
            </div>
            
            <?php if ($type === 'farmer'): ?>
            <div class="form-group">
                <label>Farm Name</label>
                <input type="text" name="farm_name" value="<?= htmlspecialchars($user['farm_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Registration Number</label>
                <input type="text" name="registration_number" value="<?= htmlspecialchars($user['registration_number'] ?? '') ?>" required>
            </div>
            <?php endif; ?>

            <!-- <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            </div> -->

            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
            </div>

            <button type="submit" class="btn-save">Save Changes</button>
            <a href="manage_users.php" class="btn-cancel">Cancel</a>
        </form>
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