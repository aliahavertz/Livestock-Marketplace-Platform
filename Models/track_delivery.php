<?php
session_start();
include('../db_connect.php');

if (!isset($_SESSION['customer_id'])) {
    header("Location: ../customer_login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$stmt = $pdo->prepare("SELECT name, profile_image FROM customer WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT d.deliverystatus, d.deliverydate, d.ridername, d.trackingnumber 
          FROM delivery d
          JOIN orders o ON d.order_id = o.order_id
          WHERE d.order_id = :oid AND o.customer_id = :cid
          ORDER BY d.deliverydate DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([':oid' => $order_id, ':cid' => $customer_id]);
$travel_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$imagePath = !empty($customer['profile_image']) ? "uploads/" . $customer['profile_image'] : "uploads/default.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Track My Package | Ranch Outlet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/sidebar.css"> 
    <style>
    .tracking-container {
        max-width: 800px;
        margin: 50px auto;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(15px);
        padding: 40px;
        border-radius: 30px;
        border: 1px solid rgba(144, 202, 249, 0.4);
    }
    .timeline {
        position: relative;
        padding-left: 40px;
        list-style: none;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 4px;
        height: 100%;
        background: #e3f2fd;
        border-radius: 2px;
    }
    .milestone {
        position: relative;
        margin-bottom: 30px;
    }
    .milestone::after {
        content: '';
        position: absolute;
        left: -46px;
        top: 5px;
        width: 16px;
        height: 16px;
        background: #1976d2;
        border: 4px solid #fff;
        border-radius: 50%;
        box-shadow: 0 0 10px rgba(25, 118, 210, 0.3);
    }
    .milestone.current::after {
        background: #4caf50;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(76, 175, 80, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); }
    }
</style>
</head>
<body>

    <div class="tracking-container">
    <h2 style="font-family: 'Cinzel'; margin-bottom: 20px;">Order #<?= $order_id ?> Tracking</h2>
    
    <div class="timeline">
        <?php foreach($milestones as $index => $m): ?>
            <div class="milestone <?= $index === 0 ? 'current' : '' ?>">
                <div style="font-weight: bold; font-family: 'Cinzel'; color: #0d1b2a;">
                    <?= htmlspecialchars($m['status_message']) ?>
                </div>
                <div style="font-size: 0.9rem; color: #666;">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($m['location']) ?>
                </div>
                <div style="font-size: 0.8rem; color: #999;">
                    <?= date('d M Y, h:i A', strtotime($m['updated_at'])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>