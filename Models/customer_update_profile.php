<?php
session_start();
include('../db_connect.php');

if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$newFileName = null;

if (!empty($_FILES['profile_image']['name'])) {
    $file = $_FILES['profile_image'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = "customer_" . time() . "." . $ext;
    $allowed = ['jpg', 'jpeg', 'png'];

    if (in_array(strtolower($ext), $allowed)) {
        if (!is_dir("uploads/")) {
            mkdir("uploads/", 0777, true);
        }
        move_uploaded_file($file['tmp_name'], "uploads/" . $newFileName);
    } 
}

$sql = "UPDATE customer 
        SET name = :name, 
            phone_number = :phone, 
            address = :address,
            email = :email" . ($newFileName ? ", profile_image = :img" : "") . " 
        WHERE customer_id = :id";

$stmt = $pdo->prepare($sql);

$stmt->bindParam(':name', $_POST['name']);
$stmt->bindParam(':phone', $_POST['phone_number']);
$stmt->bindParam(':address', $_POST['address']);
$stmt->bindParam(':email', $_POST['email']); 
$stmt->bindParam(':id', $customer_id);

if ($newFileName) {
    $stmt->bindParam(':img', $newFileName);
}

try {
    $stmt->execute();
    header("Location: customer_profile.php?updated=1");
} catch (PDOException $e) {
    die("Error updating profile: " . $e->getMessage());
}
exit();