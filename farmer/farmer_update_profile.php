<?php
session_start();
include('../db_connect.php');

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer/farmer_dashboard.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

// Upload Image
$newFileName = null;

// Fetch Farmer Name
$stmt = $pdo->prepare("SELECT name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();

if (!empty($_FILES['profile_image']['name'])) {

    $file = $_FILES['profile_image'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = "farmer_" . time() . "." . $ext;

    $allowed = ['jpg', 'jpeg', 'png'];

    if (in_array(strtolower($ext), $allowed)) {
        move_uploaded_file($file['tmp_name'], "uploads/" . $newFileName);
    } 
}

$sql = "UPDATE farmer 
        SET name = :name, farm_name = :farmName, phone_number = :phone, address = :address, farm_description :farmDesc"
        . ($newFileName ? ", profile_image = :img" : "")
        . " WHERE farmer_id = :id";

$stmt = $pdo->prepare($sql);

$stmt->bindParam(':farmName', $_POST['farm_name']);
$stmt->bindParam(':name', $_POST['name']);
$stmt->bindParam(':phone', $_POST['phone_number']);
$stmt->bindParam(':address', $_POST['address']);
$stmt->bindParam(':farmDesc', $_POST['farm_description']);
$stmt->bindParam(':id', $farmer_id);

if ($newFileName) {
    $stmt->bindParam(':img', $newFileName);
}

$stmt->execute();

header("Location: ../farmer/farmer_profile.php?updated=1");
exit();
