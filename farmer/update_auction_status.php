<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $auction_id = $_GET['id'];
    $new_status = $_GET['status']; 
    $farmer_id = $_SESSION['farmer_id'];

    try {
        $pdo->beginTransaction();

        $queryAuction = "UPDATE auction 
                         SET status = :status 
                         WHERE auction_id = :aid 
                         AND livestock_id IN (SELECT livestock_id FROM livestock WHERE farmer_id = :fid)";
        
        $stmtAuction = $pdo->prepare($queryAuction);
        $stmtAuction->execute([
            ':status' => $new_status,
            ':aid'    => $auction_id,
            ':fid'    => $farmer_id
        ]);

        if ($stmtAuction->rowCount() > 0) {
            if ($new_status === 'active') {
                $queryLivestock = "UPDATE livestock 
                                   SET availability_status = 'Available' 
                                   WHERE livestock_id = (SELECT livestock_id FROM auction WHERE auction_id = :aid)";
                $stmtLivestock = $pdo->prepare($queryLivestock);
                $stmtLivestock->execute([':aid' => $auction_id]);
                $_SESSION['msg'] = "Auction is now LIVE and livestock is Available!";
            } 
            elseif ($new_status === 'closed') {
                $queryLivestock = "UPDATE livestock 
                                   SET availability_status = 'Unavailable' 
                                   WHERE livestock_id = (SELECT livestock_id FROM auction WHERE auction_id = :aid)";
                $stmtLivestock = $pdo->prepare($queryLivestock);
                $stmtLivestock->execute([':aid' => $auction_id]);
                $_SESSION['msg'] = "Auction CLOSED and livestock is now Unavailable.";
            } else {
                $_SESSION['msg'] = "Auction status updated to " . htmlspecialchars($new_status);
            }
        } else {
            $_SESSION['msg'] = "Update failed: Auction not found or access denied.";
        }

        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['msg'] = "Database Error: " . $e->getMessage();
    }
} else {
    $_SESSION['msg'] = "Invalid Request.";
}

header("Location: farmer_manage_auction.php"); 
exit();