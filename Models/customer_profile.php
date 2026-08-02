<?php
session_start();
include('../db_connect.php');

if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit();
}

$id = $_SESSION['customer_id'];

$stmt = $pdo->prepare("SELECT name, email, phone_number, address, profile_image FROM customer WHERE customer_id = ?");
$stmt->execute([$id]);
$custData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$custData) {
    die("User not found.");
}

$imageFolder = "uploads/";
$imagePath = (!empty($custData['profile_image']) && file_exists($imageFolder . $custData['profile_image'])) 
             ? $imageFolder . $custData['profile_image'] 
             : $imageFolder . "default.png";

include '../inc/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: radial-gradient(circle at top, #fdf6ec, #f4efe6); font-family: 'PT Serif', serif; color: #1a1a1a; min-height: 100vh; padding: 60px 20px; }
        .profile-box { max-width: 600px; margin: 40px auto; background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(15px); padding: 40px; border-radius: 30px; border: 1px solid rgba(144, 202, 249, 0.4); box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .profile-box h3 { font-family: 'Cinzel', serif; text-align: center; font-weight: 700; color: #0d1b2a; font-size: 32px; margin-bottom: 30px; }
        .profile-img { width: 160px; height: 160px; border-radius: 50%; object-fit: cover; border: 4px solid #90caf9; padding: 5px; background: #fff; box-shadow: 0 8px 20px rgba(144, 202, 249, 0.3); }
        .info-row { display: flex; padding: 15px 0; border-bottom: 1px solid rgba(0,0,0,0.05); align-items: center; }
        .info-label { flex: 0 0 130px; font-family: 'Cinzel', serif; font-weight: 700; color: #1976d2; font-size: 14px; }
        .info-value { flex: 1; color: #3b332a; font-size: 16px; padding-left: 10px; }
        .edit-btn { display: inline-block; background: linear-gradient(135deg, #90caf9, #64b5f6); color: #0d1b2a; padding: 14px 40px; text-decoration: none; border-radius: 50px; margin-top: 35px; font-weight: bold; box-shadow: 0 5px 15px rgba(144, 202, 249, 0.4); }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #1976d2; margin-bottom: 25px; font-weight: bold; }
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
    </style>
</head>
<body>

<div class="container">
    <div class="profile-box">
        <a href="customer_dashboard.php" class="back-btn">
            <i class="bi bi-arrow-left-circle-fill"></i> Back
        </a>
        
        <h3>Your Profile</h3>
        <?php if(isset($_GET['status'])): ?>
            <div class="msg"><i class="fas fa-check-circle me-2"></i> Profile Updated Successfully</div>
        <?php endif; ?>
        
        <div class="text-center mb-4">
            <img src="<?php echo $imagePath; ?>" class="profile-img" alt="Profile Picture">
        </div>

        <div class="info-table">
            <div class="info-row">
                <span class="info-label"><i class="fas fa-user"></i> Name</span>
                <span class="info-value"><?php echo htmlspecialchars($custData['name'] ?? 'N/A'); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                <span class="info-value"><?php echo htmlspecialchars($custData['email'] ?? 'N/A'); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                <span class="info-value"><?php echo htmlspecialchars($custData['phone_number'] ?? 'N/A'); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address</span>
                <span class="info-value"><?php echo htmlspecialchars($custData['address'] ?? 'N/A'); ?></span>
            </div>
        </div>

        <div class="text-center">
            <a href="customer_edit_profile.php" class="edit-btn">
                <i class="fas fa-user-edit me-2"></i> Edit Profile Information
            </a>
        </div>
    </div>
</div>

</body>
</html>