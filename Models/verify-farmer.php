<?php
session_start();
include "../db_connect.php";

if(isset($_GET['token'])) {
    $token = $_GET['token'];
    $query = "SELECT verify_token, verify_status FROM farmer WHERE verify_token = ? LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$token]);

    if($stmt->rowCount() > 0) {
        $row = $stmt->fetch();
        if($row['verify_status'] == "unverified") {
            $update_query = "UPDATE farmer SET verify_status = 'verified' WHERE verify_token = ?";            
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([$token]);

            $_SESSION['pop_title'] = "Verification Successful!";
            $_SESSION['pop_msg'] = "Your email has been verified successfully. Admin approval is now pending for your farm certificate.";
            $_SESSION['pop_type'] = "success";
        } else {
            $_SESSION['pop_title'] = "Already Verified";
            $_SESSION['pop_msg'] = "Your email is already verified. Please wait for admin approval or log in.";
            $_SESSION['pop_type'] = "info";
        }
        header("Location: farmer_login.php");
        exit(); 

    } else {
        $_SESSION['pop_title'] = "Invalid Link";
        $_SESSION['pop_msg'] = "This verification token does not exist or has expired.";
        $_SESSION['pop_type'] = "error";
        header("Location: farmer_login.php");
        exit();
    }
} else {
    header("Location: farmer_login.php");
    exit();
}
?>