<?php
session_start();
include('../db_connect.php'); 

if (isset($_GET['redirect'])) {
    $_SESSION['redirect_url'] = $_GET['redirect'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM customer WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user['password'])) {

            if (isset($user['verify_status']) && $user['verify_status'] !== 'verified') {
                $_SESSION['status'] = "Please verify your email address!";
                $_SESSION['status_type'] = "warning";
                header("Location: customer_login.php");
                exit();
            }

            $_SESSION['customer_id'] = $user['customer_id'];
            $_SESSION['customer_name'] = $user['name'];

            $c_id = $user['customer_id'];

            $cookie_cart = isset($_COOKIE['persistent_cart']) ? json_decode($_COOKIE['persistent_cart'], true) : [];

            if (!empty($cookie_cart)) {
                foreach ($cookie_cart as $l_id) {
                    $syncSql = "INSERT INTO cart (customer_id, livestock_id) 
                    VALUES (?, ?) 
                    ON CONFLICT (customer_id, livestock_id) DO NOTHING";
                    $pdo->prepare($syncSql)->execute([$c_id, (int)$l_id]);
                }
            }

            $fetchSql = "SELECT livestock_id FROM cart WHERE customer_id = ?";
            $stmtCart = $pdo->prepare($fetchSql);
            $stmtCart->execute([$c_id]);
            $_SESSION['cart'] = $stmtCart->fetchAll(PDO::FETCH_COLUMN);

            setcookie('persistent_cart', json_encode($_SESSION['cart']), time() + (86400 * 30), "/");

            if (isset($_SESSION['redirect_url'])) {
                $url = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']); 
                header("Location: " . $url);
            } else {
                header("Location: customer_dashboard.php");
            }
            exit();
        } 
        else {
            $_SESSION['status'] = "Invalid password!";
            $_SESSION['status_type'] = "error";
            header("Location: customer_login.php");
            exit();
        }
    } 
    else {
        $_SESSION['status'] = "Customer account not found!";
        $_SESSION['status_type'] = "error";
        header("Location: customer_login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Login</title>
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

    .form-link-small {
        color: #8b0000 !important;
        text-decoration: none;
        font-weight: bold;
    }
    .form-link-small:hover a {
        color: black !important;
    }

    .btn-login-vintage {
        display: block;
        width: 100%;
        padding: 12px;
        margin-top: 20px;
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
        color:white;
    }

    .create-account-link {
        color: #0a7100 !important;
        font-weight: bold;
        text-decoration: none;
        border-bottom: 1px dashed #0a7100;
    }

    .swal2-popup {
        font-family: 'PT Serif', serif !important;
    }
    .swal2-title {
        font-family: 'Cinzel', serif !important;
        color: #453c34 !important;
    }
    .toggle-password {
            position: absolute; right: 15px; top: 45px; cursor: pointer; color: #aaa; z-index: 10;
        }
    .toggle-password:hover { color: var(--farm-blue); }
</style>
</head>

<body>

<?php if(isset($_SESSION['status'])): ?>
<script>
    Swal.fire({
        title: 'Login Status',
        text: <?= json_encode($_SESSION['status']); ?>,
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
        <a href="../index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <h3>Customer Login</h3>
        <p class="text-muted">Welcome back! Sign in to continue</p>

        <form action="customer_login.php" method="POST">
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
               <a href="forgot_password.php" class="form-link-small" style="font-family:'PT Serif'; font-size:14px; text-decoration: underline;">Forgot password?</a>
           </div>

            <button type="submit" class="btn-login-vintage">Log in now</button>
        </form>
        <br>
        <div style="text-align: center; margin-top: 15px;">
            <a href="Customer.php" class="create-account-link">Create new account</a>
        </div>
        <div style="text-align: center; margin-top: 10px;">
    <p style="font-family: 'PT Serif'; font-size: 13px;">
        Didn't get the email? 
        <a href="resend-email-verification.php?page=customer" style="color: #8b0000; font-weight: bold; text-decoration: none;">Resend Link</a>
    </p>
</div>
    </div>
</div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#passwordInput');

    togglePassword.addEventListener('click', function (e) {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
</script>

</body>
</html>