<?php
session_start();
include '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = $_POST['rating'];
    $review_text = $_POST['review_text'];
    
    $user_name = $_SESSION['customer_name'] ?? $_SESSION['farmer_name'] ?? 'Anonymous Buyer';

    $sql = "INSERT INTO feedback (user_name, rating, review_text) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$user_name, $rating, $review_text])) {
        header("Location: feedback.php?success=1");
    } else {
        echo "Error recording entry in ledger.";
    }
}