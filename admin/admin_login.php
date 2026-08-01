<?php
session_start();
include '../db_connect.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = "SELECT * FROM administrator WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ($password === $row['password']) {
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['admin_name'] = $row['full_name'];
            // $_SESSION['role'] = $row['role'];
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error = "Invalid credentials.";
        }
    } else {
        $error = "No administrator account found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Portal | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Raleway:wght@300;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gold: #b89b5e;
            --dark-charcoal: #2c2c2c;
            --vintage-cream: #f9f7f2;
            --border-color: #453c34;
        }

        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: var(--vintage-cream);
            font-family: 'Raleway', sans-serif;
            background-image: url('https://www.transparenttextures.com/patterns/paper.png');
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background: #fff;
            padding: 40px;
            border: 2px solid var(--border-color);
            box-shadow: 10px 10px 0px var(--primary-gold);
            text-align: center;
        }

        .login-card h2 {
            font-family: 'Cinzel', serif;
            color: var(--dark-charcoal);
            font-size: 2rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .login-card p {
            color: #888;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-family: 'Cinzel', serif;
            font-weight: 700;
            font-size: 0.8rem;
            margin-bottom: 8px;
            color: var(--border-color);
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            box-sizing: border-box;
            font-family: 'Raleway', sans-serif;
            transition: 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-gold);
            background-color: #fffdf9;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: var(--dark-charcoal);
            color: white;
            border: none;
            font-family: 'Cinzel', serif;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 1px;
            transition: 0.3s;
            margin-top: 10px;
        }

        .btn-login:hover {
            background: var(--primary-gold);
        }

        .error-msg {
            color: #d32f2f;
            background: #ffebee;
            padding: 10px;
            font-size: 0.8rem;
            margin-bottom: 20px;
            border-left: 4px solid #d32f2f;
        }

        .footer-link {
            margin-top: 25px;
            font-size: 0.8rem;
        }

        .footer-link a {
            color: var(--primary-gold);
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="login-card">
    <i class="fas fa-user-shield" style="font-size: 3rem; color: var(--primary-gold); margin-bottom: 20px;"></i>
    <h2>Admin Portal</h2>
    <p>Secure Administrator Access Only</p>

    <?php if ($error): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>

    <form action="admin_login.php" method="POST">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" class="btn-login">Authorize Access</button>
    </form>

    <div class="footer-link">
        <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Main Site</a>
    </div>
</div>

</body>
</html>