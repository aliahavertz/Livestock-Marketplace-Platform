<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = $_POST['ids'];
    
    $ids = array_map('intval', $ids);

    if (!empty($ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $sql = "DELETE FROM livestock WHERE livestock_id IN ($placeholders) AND farmer_id = ?";
            
            $stmt = $pdo->prepare($sql);
            
            $params = array_merge($ids, [$farmer_id]);
            
            if ($stmt->execute($params)) {
                $_SESSION['msg'] = count($ids) . " items deleted successfully.";
            } else {
                $_SESSION['msg'] = "Error deleting items.";
            }
        } catch (Exception $e) {
            $_SESSION['msg'] = "Database error: " . $e->getMessage();
        }
    }
} else {
    $_SESSION['msg'] = "No items selected for deletion.";
}

header("Location: view_livestock.php");
exit();