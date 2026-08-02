<?php
session_start();
include('../db_connect.php'); 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/phpmailer/phpmailer/src/Exception.php';
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $sql = "SELECT customer_id, name FROM customer WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(16));
        $token_hash = hash("sha256", $token);
        
        date_default_timezone_set('Asia/Kuala_Lumpur');
        $expiry = date("Y-m-d H:i:s"); 

        $updateSql = "UPDATE customer 
                      SET reset_token_hash = :hash, 
                          reset_token_expires_at = CAST(:expiry AS TIMESTAMP) + INTERVAL '30 minutes'
                      WHERE customer_id = :id";
                      
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':hash' => $token_hash,
            ':expiry' => $expiry,
            ':id' => $user['customer_id']
        ]);

        $resetLink = "http://localhost/LivestockMarketplace/Models/reset-password.php?token=" . $token;

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';                    
            $mail->SMTPAuth   = true;
            $mail->Username   = 'syzawuu@gmail.com';   
            $mail->Password   = 'iiay irur zqmw usen';                 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('syzawuu@gmail.com', 'Livestock Marketplace');
            $mail->addAddress($email, $user['name']);

            $mail->isHTML(true);
            $mail->Subject = 'Reset Your RanchLink Website Password';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                    <h2 style='color: #453c34;'>Password Recovery Request</h2>
                    <p>Hi " . htmlspecialchars($user['name']) . ",</p>
                    <p>We received a request to change your account password for our livestock marketplace platform.</p>
                    <p>Click the secure link below to set up a brand new login credential:</p>
                    <p><a href='" . $resetLink . "' style='display:inline-block; padding: 10px 20px; background-color: #90caf9; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reset Website Password</a></p>
                    <br>
                    <small style='color: #888;'>If you did not make this request, you can safely ignore this email.</small>
                </div>
            ";

            $mail->send();
            
            $_SESSION['status'] = "A secure verification email has been dispatched to your inbox!";
            $_SESSION['status_type'] = "success";

        } catch (Exception $e) {
            $_SESSION['status'] = "Mailer Error: System could not transmit message. " . $mail->ErrorInfo;
            $_SESSION['status_type'] = "error";
        }

        header("Location: forgot_password.php");
        exit();
    } else {
        $_SESSION['status'] = "No account found with that email address.";
        $_SESSION['status_type'] = "error";
        header("Location: forgot_password.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password</title>
<link href="https://fonts.googleapis.com/css2?family=Special+Elite&family=PT+Serif:wght@400;700&family=Cinzel:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 50px 0;
        background: radial-gradient(circle at top, #fdf6ec, #f4efe6);
        font-family: 'Cinzel', serif;
        color: #3b332a;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;        
        min-width: 850px; 
        overflow-x: auto; 
    }
    .login-container {
        max-width: 1000px;
        margin: auto;
        display: flex;
        box-shadow: 0 0 25px rgba(144,202,249,0.6);
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        z-index: 1;        
        width: 1000px; 
    }
    
    .login-container::after {
        content: "";
        position: absolute;
        inset: 0;
        background: url("../assets/grunge-overlay.png"); 
        pointer-events: none;
        opacity: 0.3;
        z-index: 2;
    }

    .login-left {
        background: url('../assets/login-wall4.jpg') center/cover no-repeat;
        min-height: 500px;
        width: 50%;
        position: relative;
        filter: brightness(1.0) sepia(0.3);        
        flex: 1 0 50%; 
    }
    .login-left::before {
        content: "";
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.3);
    }

    .login-right {
        padding: 40px;
        width: 50%;
        background: #fdf6ec;
        position: relative;
        z-index: 3; 
        overflow: hidden;
        flex: 1 0 50%;
    }
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 16px;
        text-decoration: none;
        color: #453c34;
        margin-bottom: 20px;
        font-family: 'Cinzel', cursive;
        font-weight: bold;
    }
    .back-btn:hover { color: #90caf9; }

    h3 {
        font-family: 'Cinzel', cursive;
        font-size: 30px;
        text-align: center;
        color: #453c34;
        text-transform: uppercase;
        margin-bottom: 5px !important;
    }
    .text-muted {
        text-align: center;
        color: #5c4d3b !important;
        margin-bottom: 25px;
    }

    .form-group-vintage {
        position: relative; 
    }

    .form-group-vintage label {
        display: block;
        margin-top: 15px;
        margin-bottom: 5px;
        font-weight: bold;
        font-size: 1.1em;
        font-family: 'Cinzel', cursive;
        letter-spacing: 0.5px;
        color: #453c34;
    }

    .form-control-vintage {
        width: 100%;
        padding: 10px;
        border: 2px solid #90caf9;
        border-radius: 8px;
        background: #E7F3FE;
        font-family: 'PT Serif', serif;
        font-size: 16px;
        color: #2d2b29;
        box-shadow: inset 1px 1px 2px rgba(0,0,0,0.2);
    }

    .btn-login-vintage {
        display: block;
        width: 100%;
        padding: 12px;
        margin-top: 30px;
        background: #90caf9;
        border: 1px solid #90caf9;
        border-radius: 8px;
        color: white;
        font-family: 'Cinzel', cursive;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-login-vintage:hover {
        background: black;
        border: 1px solid #E7F3FE;
        color: white;
    }

    .swal2-popup {
        font-family: 'PT Serif', serif !important;
    }
    .swal2-title {
        font-family: 'Cinzel', serif !important;
        color: #453c34 !important;
    }
</style>
</head>
<body>

<?php if(isset($_SESSION['status'])): ?>
<script>
    Swal.fire({
        title: 'Reset Status',
        text: "<?= $_SESSION['status']; ?>",
        icon: "<?= isset($_SESSION['status_type']) ? $_SESSION['status_type'] : 'info'; ?>",
        confirmButtonColor: '#90caf9',
        background: '#fdf6ec'
    });
</script>
<?php 
    unset($_SESSION['status']); 
    unset($_SESSION['status_type']); 
endif; 
?>

<div class="login-container">
    <div class="login-left"></div>

    <div class="login-right">
        <a href="customer_login.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>

        <h3>Recover Password</h3>
        <p class="text-muted" style="font-family: 'PT Serif'; font-size: 14px;">Enter your email address below and we will send you a secure link to reset your credentials.</p>

        <form action="forgot_password.php" method="POST">
            <div class="form-group-vintage">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control-vintage" required placeholder="example@email.com">
            </div>

            <button type="submit" class="btn-login-vintage">Send Reset Link</button>
        </form>
    </div>
</div>

</body>
</html>