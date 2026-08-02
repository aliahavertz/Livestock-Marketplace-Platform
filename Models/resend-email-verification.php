<?php
session_start();
include('../db_connect.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$page_type = isset($_GET['page']) ? $_GET['page'] : 'customer';
$return_page = ($page_type == 'farmer') ? 'farmer_login.php' : 'customer_login.php';

function send_resend_email($name, $email, $verify_token) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'syzawuu@gmail.com'; 
        $mail->Password   = 'iiay irur zqmw usen'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('syzawuu@gmail.com', 'Livestock Marketplace');
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'New Verification Link';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; border: 1px solid #ddd; padding: 20px;'>
                <h2>Hello $name,</h2>
                <p>You requested a new verification link for your Livestock Marketplace account.</p>
                <p>Please click the button below to verify your email address:</p>
                <a href='http://localhost/LivestockMarketplace/Models/verify-email.php?token=$verify_token' 
                   style='background: #90caf9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                   Verify My Email
                </a>
                <p>If you did not request this, please ignore this email.</p>
            </div>";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if (isset($_POST['resend_btn'])) {
    $email = trim($_POST['email']);
    $current_page_type = $_POST['page_type']; 

    $check_user = "SELECT name, email, verify_token, verify_status FROM customer WHERE email = :email
                   UNION
                   SELECT name, email, verify_token, verify_status FROM farmer WHERE email = :email
                   LIMIT 1";
    
    $stmt = $pdo->prepare($check_user);
    $stmt->execute(['email' => $email]);

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row['verify_status'] == 'unverified') {
            $name = $row['name'];
            $token = $row['verify_token'];

            if (send_resend_email($name, $email, $token)) {
                $_SESSION['status'] = "A new verification link has been sent to $email.";
                $_SESSION['status_type'] = "success";
                header("Location: resend-email-verification.php?page=" . $current_page_type);
                exit();
            } else {
                $_SESSION['status'] = "Email could not be sent. Please check your settings.";
                $_SESSION['status_type'] = "error";
            }
        } else {
            $_SESSION['status'] = "This account is already verified. You can log in.";
            $_SESSION['status_type'] = "info";
        }
    } else {
        $_SESSION['status'] = "No account found with that email address.";
        $_SESSION['status_type'] = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resend Verification</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            margin: 0;
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6);
            font-family: 'Cinzel', serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .resend-container {
            background: #fdf6ec;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 0 25px rgba(144,202,249,0.4);
            width: 100%;
            max-width: 450px;
            text-align: center;
            border: 1px solid rgba(144,202,249,0.2);
        }
        h3 { color: #453c34; text-transform: uppercase; margin-bottom: 10px; }
        p { font-family: 'PT Serif', serif; color: #5c4d3b; font-size: 14px; }
        
        .form-control-vintage {
            width: 100%;
            padding: 12px;
            margin: 20px 0;
            border: 2px solid #90caf9;
            border-radius: 8px;
            background: #E7F3FE;
            font-family: 'PT Serif', serif;
            font-size: 16px;
            outline: none;
        }
        .btn-resend {
            width: 100%;
            padding: 12px;
            background: #90caf9;
            border: none;
            border-radius: 8px;
            color: white;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-resend:hover { background: #7ab8e8; transform: translateY(-1px); }
        .back-link {
            display: block;
            margin-top: 20px;
            color: #8b0000;
            text-decoration: none;
            font-family: 'PT Serif', serif;
            font-weight: bold;
            font-size: 14px;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="resend-container">
    <i class="fas fa-envelope-open-text" style="font-size: 40px; color: #90caf9; margin-bottom: 15px;"></i>
    <h3>Resend Verification</h3>
    <p>Lost your link? Enter your email address below to receive a new one for your <strong><?= ucfirst($page_type) ?></strong> account.</p>

    <form action="resend-email-verification.php?page=<?= $page_type ?>" method="POST">
        <input type="hidden" name="page_type" value="<?= $page_type ?>">
        
        <input type="email" name="email" class="form-control-vintage" placeholder="Enter your registered email" required>
        <button type="submit" name="resend_btn" class="btn-resend">Send Verification Link</button>
    </form>

    <a href="<?= $return_page ?>" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to <?= ucfirst($page_type) ?> Login
    </a>
</div>

<?php if(isset($_SESSION['status'])): ?>
<script>
    Swal.fire({
        title: 'Status',
        text: "<?= $_SESSION['status']; ?>",
        icon: "<?= $_SESSION['status_type'] ?? 'info'; ?>",
        confirmButtonColor: '#90caf9',
        customClass: { popup: 'swal-font-fix' }
    });
</script>
<style>.swal-font-fix { font-family: 'PT Serif', serif !important; }</style>
<?php unset($_SESSION['status']); unset($_SESSION['status_type']); endif; ?>

</body>
</html>