<?php
session_start();
include('../db_connect.php');

if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_dashboard.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

$stmt = $pdo->prepare("SELECT * FROM customer WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone_number'] ?? '';
    $address = $_POST['address'] ?? '';
    $img = $customer['profile_image'];

    if (!empty($_FILES['profile_image']['name'])) {
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $img = "cust_" . time() . "." . $ext;
        move_uploaded_file($_FILES['profile_image']['tmp_name'], "uploads/" . $img);
    }

    $colName = array_key_exists('phone_number', $customer) ? 'phone_number' : 'phone';
    
    $sql = "UPDATE customer SET name = ?, $colName = ?, address = ?, profile_image = ? WHERE customer_id = ?";
    $pdo->prepare($sql)->execute([$name, $phone, $address, $img, $customer_id]);

    header("Location: customer_profile.php?status=updated");
    exit();
}

$displayName = $customer['name'] ?? '';
$displayAddress = $customer['address'] ?? '';
$displayPhone = $customer['phone_number'] ?? $customer['phone'] ?? '';
$displayImg = !empty($customer['profile_image']) ? "uploads/".$customer['profile_image'] : "uploads/default.png";

include'../inc/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile | Ranch Outlet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6);
            font-family: 'PT Serif', serif; 
            color: #1a1a1a;
            min-height: 100vh;
            padding: 60px 20px;
        }

        .profile-box {
            max-width: 600px;
            margin: 40px auto;
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
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .img-preview-container {
            text-align: center;
            margin-bottom: 25px;
        }
        .img-preview {
            width: 130px;
            height: 130px;
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
            display: block;
        }

        input[type="text"], 
        input[type="file"], 
        textarea {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.1);
            background: rgba(255, 255, 255, 0.8);
            font-family: 'PT Serif', serif;
            transition: 0.3s;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #90caf9;
            background: #fff;
            box-shadow: 0 0 10px rgba(144, 202, 249, 0.2);
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
    </style>
</head>
<body>

<div class="profile-box">
    <h3>Edit Profile</h3>
    
    <?php if(isset($_GET['status'])): ?>
        <div class="msg"><i class="fas fa-check-circle me-2"></i> Profile Updated Successfully</div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="img-preview-container">
            <img src="<?php echo $displayImg; ?>" class="img-preview" alt="Current Profile">
            <div class="mt-3">
                <label for="file-upload" style="cursor: pointer; color: #1976d2; text-decoration: underline;">
                    Change Profile Picture
                </label>
                <input id="file-upload" type="file" name="profile_image" style="font-size: 12px;" onchange="previewProfileImage(this)">
            </div>
        </div>

        <label>Full Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($displayName); ?>" required placeholder="Enter your full name">

        <label>Phone Number</label>
        <input type="text" name="phone_number" value="<?php echo htmlspecialchars($displayPhone); ?>" required placeholder="Enter your phone number">

        <label>Residential Address</label>
        <textarea name="address" rows="3" required placeholder="Enter your full home address"><?php echo htmlspecialchars($displayAddress); ?></textarea>

        <button type="submit" class="btn-save">Save Changes</button>
        
        <a href="customer_profile.php" class="cancel-link">
            <i class="fas fa-times me-1"></i> Cancel and Return
        </a>
    </form>
</div>

<script>
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