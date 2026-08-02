<?php
session_start();
include "../db_connect.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
function sendemail_verify($name, $email, $verify_token)
{
    try {
        $mail = new PHPMailer(true);
        // $mail->SMTPDebug = 2;
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
        $mail->Subject = 'Email Verification';

        $email_template = "
            <h2>Hello $name</h2>
            <p>Please verify your email address by clicking the link below:</p>
            <a href='http://localhost/LivestockMarketplace/Models/verify-email.php?token=$verify_token'>
                Verify Email
            </a>
        ";

        $mail->Body = $email_template;
        $mail->AltBody = "Verify your email using this link: http://localhost/LivestockMarketplace/Models/verify-email.php?token=$verify_token";

        $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone_number = $_POST['phone_number'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $postal_code = $_POST['postal_code'];
    // $profile_image = $_POST['profile_image'];
    // $preferred_livestock_type = $_POST['preferred_livestock_type'];
    $verify_token = bin2hex(random_bytes(32));

    // sendemail_verify("$name", "$email", "$verify_token");
    // echo "sent or not ?";


    // Email Exists or not
    $check_sql = "SELECT email FROM customer WHERE email = ? LIMIT 1";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$email]);

    if ($check_stmt->rowCount() > 0) {

        $_SESSION['status'] = "Email already exists";
        header("Location: Customer.php");
        exit();

    } else {

        $sql = "INSERT INTO customer (
            name, email, password, phone_number, address, city, state,
            postal_code, verify_token, verify_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name,
                $email,
                $password,
                $phone_number,
                $address,
                $city,
                $state,
                $postal_code,
                $verify_token,
                'unverified'
            ]);

            sendemail_verify($name, $email, $verify_token);

            $_SESSION['status'] = "Registration successful! Please verify your email.";
            header("Location: customer_login.php");
            exit();

        } catch (PDOException $e) {

            $_SESSION['status'] = "Registration failed";
            header("Location: Customer.php");
            exit();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registration | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --farm-blue: #0077b6; 
            --sky-light: #caf0f8; 
            --white: #ffffff;
            --bg-accent: #fdf6ec;
        }

        * { box-sizing: border-box; font-family: 'Cinzel', serif; transition: 0.3s; }

        body {
            margin: 0;
            background: url('https://cdn.pixabay.com/photo/2022/02/17/17/21/cows-7019167_1280.jpg') no-repeat center center;
            /*background-image: radial-gradient(#caf0f8 1px, transparent 1px);*/
            background-size: cover;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .registration-container {
            width: 100%;
            max-width: 600px;
            background: radial-gradient(circle at top, rgba(253, 246, 236, 0.7), rgba(244, 239, 230, 0.7));
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 15px 35px rgba(0, 119, 182, 0.1);
            border: 3px solid var(--sky-light);
            position: relative;
            backdrop-filter: blur(5px);
        }

        .home-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            text-decoration: none;
            color: var(--farm-blue);
            background: var(--sky-light);
            padding: 8px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .home-btn:hover {
            background: var(--farm-blue);
            color: white;
        }

        h2 { color: var(--farm-blue); margin: 15px 0 5px 0; font-size: 1.8rem; text-align: center; }
        .subtitle { color: #666; text-align: center; margin-bottom: 25px; font-size: 0.95rem; }

        .progress-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            position: relative;
            max-width: 180px;
            margin-left: auto;
            margin-right: auto;
        }
        .progress-container::before {
            content: ""; background: var(--sky-light);
            height: 4px; width: 100%; position: absolute; top: 50%; transform: translateY(-50%); z-index: 1;
        }
        .step-circle {
            width: 30px; height: 30px; background: white; border: 3px solid var(--sky-light);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            z-index: 2; font-weight: bold; color: var(--farm-blue); font-size: 0.8rem;
        }
        .step-circle.active { border-color: var(--farm-blue); background: var(--farm-blue); color: white; }

        .form-step { display: none; }
        .form-step.active { display: block; animation: fadeIn 0.4s ease; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .form-group { margin-bottom: 12px; display: flex; flex-direction: column; position: relative; }
        label { font-weight: 600; font-size: 0.85rem; margin-bottom: 5px; color: #444; }
        
        input, textarea {
            padding: 12px 14px; border: 2px solid #eee; border-radius: 10px; font-size: 0.95rem; background: #fdfdfd;
        }
        input:focus, textarea:focus { border-color: var(--farm-blue); outline: none; background: #fff; }

        .password-wrapper { position: relative; display: flex; flex-direction: column; }
        .toggle-password {
            position: absolute; right: 15px; top: 38px; cursor: pointer; color: #aaa; z-index: 10;
        }
        .toggle-password:hover { color: var(--farm-blue); }

        .btn-row { display: flex; gap: 10px; margin-top: 20px; }
        button {
            flex: 1; padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer; border: none; font-size: 1rem;
        }
        .btn-next { background: var(--farm-blue); color: white; }
        .btn-prev { background: #eee; color: #666; }
        button:hover { opacity: 0.9; transform: translateY(-1px); }

        .login-footer { text-align: center; margin-top: 20px; font-size: 0.85rem; border-top: 1px solid #eee; padding-top: 15px; }
        .login-footer a { color: var(--farm-blue); font-weight: bold; text-decoration: none; }
    </style>
</head>
<body>

<div class="registration-container">
    <a href="../index.php" class="home-btn">
        <i class="fas fa-home"></i> Home
    </a>

    <div class="progress-container">
        <div class="step-circle active" id="circle1">1</div>
        <div class="step-circle" id="circle2">2</div>
    </div>

    <h2>Create an account</h2>
    <p class="subtitle" id="step-text">Your Account Details</p>
    
    <form method="POST" id="regForm" onsubmit="return showVerificationMessage()">
        
        <div class="form-step active" id="step1">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Enter your full name" required>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="example@email.com" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group password-wrapper">
                    <label>Password</label>
                    <input type="password" name="password" id="passwordInput" placeholder="••••••••" required>
                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" placeholder="01x-xxxxxxx">
                </div>
            </div>

            <div class="btn-row">
                <button type="button" class="btn-next" onclick="nextStep()">Next: Address <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>

        <div class="form-step" id="step2">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" placeholder="e.g. Shah Alam">
                </div>
                <div class="form-group">
                    <label>State</label>
                    <input type="text" name="state" placeholder="e.g. Selangor">
                </div>
            </div>

            <div class="form-group">
                <label>Postal Code</label>
                <input type="text" name="postal_code" placeholder="Zip code">
            </div>

            <div class="form-group">
                <label>Full Address</label>
                <textarea name="address" placeholder="Home or delivery address" rows="3"></textarea>
            </div>
            
            <div class="btn-row">
                <button type="button" class="btn-prev" onclick="prevStep()"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="submit" class="btn-next">Register Account</button>
            </div>
        </div>
    </form>
    
    <div class="login-footer">
        Already have an account? <a href="customer_login.php">Login here</a>
    </div>
</div>

<script>
    function nextStep() {
        // Validate step 1 fields before moving to step 2
        const name = document.querySelector('input[name="name"]').value;
        const email = document.querySelector('input[name="email"]').value;
        const password = document.querySelector('input[name="password"]').value;

        if(!name || !email || !password) {
            alert("Please fill in your name, email, and password details first.");
            return;
        }

        document.getElementById('step1').classList.remove('active');
        document.getElementById('step2').classList.add('active');
        document.getElementById('circle2').classList.add('active');
        document.getElementById('step-text').innerText = "Delivery Information";
    }

    function prevStep() {
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step1').classList.add('active');
        document.getElementById('circle2').classList.remove('active');
        document.getElementById('step-text').innerText = "Your Account Details";
    }

    function showVerificationMessage() {
        alert("Registration submitted! We are sending a verification link to your email. Please wait a few seconds while we redirect you...");
        return true; 
    }

    const togglePassword = document.querySelector('#togglePassword');
    const passwordField = document.querySelector('#passwordInput');

    togglePassword.addEventListener('click', function (e) {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
</script>

</body>
</html>
