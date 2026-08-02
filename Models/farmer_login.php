<?php
session_start();
include '../db_connect.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM farmer WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user['password'])) {
            
            $email_status = $user['verify_status'] ?? 'unverified';
            if ($email_status !== 'verified') {
                $_SESSION['pop_title'] = "Email Unverified";
                $_SESSION['pop_msg'] = "Please check your inbox and verify your email address before logging in.";
                $_SESSION['pop_type'] = "warning";
                header("Location: farmer_login.php");
                exit();
            }

            $admin_status = strtolower(trim($user['verified_status'] ?? 'pending'));

            if ($admin_status === 'pending') {
                $_SESSION['pop_title'] = "Approval Pending";
                $_SESSION['pop_msg'] = "Email verified! However, your account is still awaiting Admin approval for your farm certificate.";
                $_SESSION['pop_type'] = "info";
                header("Location: farmer_login.php");
                exit();
            } elseif ($admin_status === 'rejected') {
                $_SESSION['pop_title'] = "Account Rejected";
                $_SESSION['pop_msg'] = "Your registration has been rejected. Please contact support.";
                $_SESSION['pop_type'] = "error";
                header("Location: farmer_login.php");
                exit();
            } elseif ($admin_status === 'suspended') {
                $_SESSION['pop_title'] = "Suspended";
                $_SESSION['pop_msg'] = "Your account has been suspended. Please contact the administrator.";
                $_SESSION['pop_type'] = "error";
                header("Location: farmer_login.php");
                exit();
            }

            // Login Success
            // unset($_SESSION['customer_id']);
            $_SESSION['farmer_id'] = $user['farmer_id'];
            $_SESSION['farmer_name'] = $user['name'];

            header("Location: ../farmer/farmer_dashboard.php");
            exit();
        } else {
           $_SESSION['pop_title'] = "Login Failed";
            $_SESSION['pop_msg'] = "Invalid password. Please try again.";
            $_SESSION['pop_type'] = "error";
            header("Location: farmer_login.php");
            exit();
        } 
    } else {
        $_SESSION['pop_title'] = "Not Found";
        $_SESSION['pop_msg'] = "Farmer account not found with this email.";
        $_SESSION['pop_type'] = "error";
        header("Location: farmer_login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Farmer Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 50px 0;
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6);
            font-family: 'Cinzel', serif;
            color: #3b332a;
            display: flex; justify-content: center; align-items: center; min-height: 100vh;
        }
        .login-container {
            max-width: 1000px; display: flex;
            box-shadow: 0 0 25px rgba(144,202,249,0.4);
            border-radius: 12px; overflow: hidden; width: 1000px;
        }
        .login-left {
            background: url('../assets/login-wall4.jpg') center/cover no-repeat;
            width: 50%; min-height: 500px; filter: brightness(1.0) sepia(0.2);
        }
        .login-right {
            padding: 40px; width: 50%; background: #fdf6ec;
        }
        .back-btn {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 16px; text-decoration: none; color: #453c34;
            margin-bottom: 20px; font-weight: bold;
        }
        .back-btn:hover { color: #90caf9; }
        
        h3 { font-size: 30px; text-align: center; color: #453c34; text-transform: uppercase; margin: 0; }
        .text-muted { text-align: center; color: #5c4d3b; margin-bottom: 25px; }
        .form-group-vintage {
            position: relative; 
        }
        .form-group-vintage label {
            display: block; margin-top: 15px; margin-bottom: 5px;
            font-weight: bold; font-size: 1.1em; color: #453c34;
        }
        .form-control-vintage {
            width: 100%; padding: 10px; border: 2px solid #90caf9;
            border-radius: 8px; background: #E7F3FE; font-family: 'PT Serif', serif;
        }
        .btn-login-vintage {
            display: block; width: 100%; padding: 12px; margin-top: 20px;
            background: #90caf9; border: none; border-radius: 8px;
            color: white; font-family: 'Cinzel', serif; font-weight: bold;
            cursor: pointer; transition: 0.3s;
        }
        .btn-login-vintage:hover { background: #7ab8e8; }
        .create-account-link { color: #0a7100; font-weight: bold; text-decoration: none; border-bottom: 1px dashed #0a7100; }
        .form-link-small {
            color: #8b0000 !important;
            text-decoration: none;
            font-weight: bold;
        }
        .form-link-small:hover a {
            color: black !important;
        }
        
        .swal-title-vintage { font-family: 'Cinzel', serif !important; color: #453c34 !important; }
        .swal-popup-vintage { font-family: 'PT Serif', serif !important; border-radius: 15px !important; }
        .toggle-password {
            position: absolute; right: 15px; top: 40px; cursor: pointer; color: #aaa; z-index: 10;
        }
        .toggle-password:hover { color: var(--farm-blue); }
    </style>
</head>

<body>
<div class="login-container">
    <div class="login-left"></div>

    <div class="login-right">
        <a href="../index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <h3>Farmer Login</h3>
        <p class="text-muted">Welcome back! Sign in to continue</p>

        <form action="farmer_login.php" method="POST">
            <div class="form-group-vintage">
                <label>Email</label>
                <input type="email" name="email" class="form-control-vintage" required>
            </div>

            <div class="form-group-vintage">
                <label>Password</label>
                <input type="password" name="password" id="passwordInput" class="form-control-vintage" required>
                <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            </div>

             <div style="display: flex; justify-content: space-between; margin-top: 15px; margin-bottom: 15px;">
                <!-- <div>
                    <input type="checkbox" id="rememberMe" name="remember"> 
                    <label for="rememberMe" style="font-family:'PT Serif'; font-size:14px;">Keep me logged in</label>
                </div>
               -->                
               <a href="farmer_forgot_password.php" class="form-link-small" style="font-family:'PT Serif'; font-size:14px; text-decoration: underline;">Forgot password?</a>
           </div>

            <button type="submit" class="btn-login-vintage">Log in now</button>
        </form>

        <div style="text-align: center; margin-top: 25px;">
            <a href="Farmer.php" class="create-account-link">Create new farmer account</a>
        </div>
        <div style="text-align: center; margin-top: 10px;">
    <p style="font-family: 'PT Serif'; font-size: 13px;">
        Didn't get the email? 
        <a href="resend-email-verification.php?page=farmer" style="color: #8b0000; font-weight: bold; text-decoration: none;">Resend Link</a>
    </p>
</div>
    </div>
</div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#passwordInput');

    if (togglePassword) {
        togglePassword.addEventListener('click', function (e) {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            this.classList.toggle('fa-eye-slash');
        });
    }
</script>

<?php if(isset($_SESSION['pop_msg'])): ?>
<script>
    Swal.fire({
        title: "<?= $_SESSION['pop_title']; ?>",
        text: "<?= $_SESSION['pop_msg']; ?>",
        icon: "<?= $_SESSION['pop_type']; ?>",
        confirmButtonColor: '#90caf9',
        customClass: {
            title: 'swal-title-vintage',
            popup: 'swal-popup-vintage'
        }
    });
</script>

<?php 
    unset($_SESSION['pop_title']);
    unset($_SESSION['pop_msg']);
    unset($_SESSION['pop_type']);
endif; 
?>
</body>
</html>