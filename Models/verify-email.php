<?php
session_start();
include "../db_connect.php";

if (!isset($_GET['token'])) {
    $_SESSION['status'] = "Not Allowed";
    header("Location: customer_login.php");
    exit();
}

$token = $_GET['token'];

// Check token
$sql = "SELECT verify_token, verify_status FROM customer WHERE verify_token = :token LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['token' => $token]);

if ($stmt->rowCount() == 0) {
    $_SESSION['status'] = "This token does not exist";
    header("Location: customer_login.php");
    exit();
}

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row['verify_status'] === 'verified') {
    $_SESSION['status'] = "Email already verified. Please login.";
    header("Location: customer_login.php");
    exit();
}

$update_sql = "UPDATE customer 
               SET verify_status = 'verified' 
               WHERE verify_token = :token";

$update_stmt = $pdo->prepare($update_sql);
$update_stmt->execute(['token' => $token]);

if ($update_stmt->rowCount() > 0) {
    $_SESSION['status'] = "Your account has been verified successfully!";
} else {
    $_SESSION['status'] = "Verification failed.";
}

header("Location: customer_login.php");
exit();
?>
