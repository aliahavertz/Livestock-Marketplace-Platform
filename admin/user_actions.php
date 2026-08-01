<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) exit();

$type = $_GET['type'] ?? ''; 
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($type && $action && $id) {
    $table = ($type === 'customer') ? 'customer' : 'farmer';
    $pk = ($type === 'customer') ? 'customer_id' : 'farmer_id';
    
    $status_col = ($type === 'farmer') ? 'verified_status' : 'status';

    try {
        if ($action === 'delete') {
            $sql = "DELETE FROM $table WHERE $pk = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
        } 
        elseif ($action === 'approve') {
            $sql = "UPDATE $table SET $status_col = 'approved' WHERE $pk = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
        }
        elseif ($action === 'reject') {
            $sql = "UPDATE $table SET $status_col = 'rejected' WHERE $pk = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
        }
        elseif ($action === 'suspend') {
            $sql = "UPDATE $table SET $status_col = 'suspended' WHERE $pk = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
        }
        
        header("Location: manage_users.php?msg=updated");
        exit();

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: manage_users.php");
    exit();
}