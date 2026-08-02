<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

if (isset($_GET['livestock_id'])) {
    $livestock_id = $_GET['livestock_id'];
    $farmer_id = $_SESSION['farmer_id'];

    try {
        $imgQuery = "SELECT image FROM livestock WHERE livestock_id = :lid AND farmer_id = :fid";
        $imgStmt = $pdo->prepare($imgQuery);
        $imgStmt->execute([':lid' => $livestock_id, ':fid' => $farmer_id]);
        $livestock = $imgStmt->fetch();

        if ($livestock) {
            // Delete physical files if they exist
            if (!empty($livestock['image'])) {
                $images = explode(',', $livestock['image']);
                foreach ($images as $img) {
                    $file_path = "uploads/" . trim($img);
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }

            $deleteQuery = "DELETE FROM livestock WHERE livestock_id = :lid AND farmer_id = :fid";
            $deleteStmt = $pdo->prepare($deleteQuery);
            $deleteStmt->execute([':lid' => $livestock_id, ':fid' => $farmer_id]);

            $_SESSION['msg'] = "Livestock record deleted successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting record: " . $e->getMessage();
    }
}

header("Location: view_livestock.php");
exit();