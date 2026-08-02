<?php
session_start();
include('../db_connect.php'); 

$token_valid = false;
$user = null;

if (isset($_GET['token']) && !empty(trim($_GET['token']))) {
    $incoming_token = trim($_GET['token']);
    $token_hash = hash("sha256", $incoming_token);
    
    date_default_timezone_set('Asia/Kuala_Lumpur');
    $current_time = date("Y-m-d H:i:s");

    $sql = "SELECT * FROM customer 
            WHERE TRIM(reset_token_hash) = :hash 
            LIMIT 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':hash', $token_hash);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $expiry_time = strtotime($user['reset_token_expires_at']);
        $now_time = strtotime($current_time);

        if ($expiry_time > $now_time) {
            $token_valid = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $_SESSION['status'] = "Passwords do not match!";
        $_SESSION['status_type'] = "error";
        header("Location: reset-password.php?token=" . urlencode($_GET['token']));
        exit();
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    $sql = "UPDATE customer 
            SET password = :password, reset_token_hash = NULL, reset_token_expires_at = NULL 
            WHERE customer_id = :id";
    $stmt = $pdo->prepare($sql);
    $execution = $stmt->execute([
        ':password' => $hashed_password,
        ':id' => $user['customer_id']
    ]);
    
    if ($execution) {
        $_SESSION['status'] = "Password updated successfully!";
        $_SESSION['status_type'] = "success";
        header("Location: customer_login.php");
        exit();
    } else {
        $_SESSION['status'] = "Database processing error.";
        $_SESSION['status_type'] = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Website Password</title>
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

    .swal2-popup { font-family: 'PT Serif', serif !important; }
    .swal2-title { font-family: 'Cinzel', serif !important; color: #453c34 !important; }
    
    .toggle-password {
        position: absolute; right: 15px; top: 45px; cursor: pointer; color: #aaa; z-index: 10;
    }
    .error-box {
        text-align: center;
        padding: 30px 10px;
        font-family: 'PT Serif', serif;
    }
</style>
</head>
<body>

<?php if(isset($_SESSION['status'])): ?>
<script>
    Swal.fire({
        title: 'Recovery Status',
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
        <?php if ($token_valid): ?>
            <h3 style="margin-top: 20px;">New Password</h3>
            <p class="text-muted">Set up your brand new website authorization key.</p>

            <form action="reset-password.php?token=<?= htmlspecialchars($incoming_token) ?>" method="POST" id="resetForm">
                
                <div class="form-group-vintage">
                    <label>New Password</label>
                    <input type="password" name="password" id="passwordInput" class="form-control-vintage" required minlength="6">
                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                </div>

                <div class="form-group-vintage" style="margin-top: 10px;">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirmPasswordInput" class="form-control-vintage" required>
                    <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                </div>

                <button type="submit" class="btn-login-vintage">Change Password</button>
            </form>
        <?php else: ?>
            <div class="error-box">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #d32f2f; margin-bottom: 15px;"></i>
                <h3>Invalid Link</h3>
                <p style="color: #666;">This recovery link has expired or the validation token configuration context is invalid.</p>
                <a href="forgot_password.php" class="btn-login-vintage" style="text-decoration: none; text-align: center; display: block; margin-top: 25px;">Request a New Link</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#passwordInput');
    if(togglePassword && password) {
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    }

    const toggleConfirm = document.querySelector('#toggleConfirmPassword');
    const confirmPass = document.querySelector('#confirmPasswordInput');
    if(toggleConfirm && confirmPass) {
        toggleConfirm.addEventListener('click', function () {
            const type = confirmPass.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPass.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    }

    const form = document.getElementById('resetForm');
    if(form) {
        form.addEventListener('submit', function(e) {
            if(password.value !== confirmPass.value) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error',
                    text: 'Passwords do not match inside configuration inputs!',
                    icon: 'error',
                    confirmButtonColor: '#90caf9',
                    background: '#fdf6ec'
                });
            }
        });
    }
</script>

</body>
</html>