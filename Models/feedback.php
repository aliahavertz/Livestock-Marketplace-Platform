<?php
session_start();
require_once '../db_connect.php';
include '../inc/numbers.php';

if (!isset($_SESSION['customer_id']) || !isset($_GET['order_id'])) {
    header("Location: my_orders.php");
    exit();
}

$order_id = (int)$_GET['order_id'];
$customer_id = $_SESSION['customer_id'];

$stmt = $pdo->prepare("SELECT o.order_id, l.name, l.image, l.livestock_id, l.farmer_id 
 FROM orders o 
 JOIN livestock l ON o.livestock_id = l.livestock_id 
 WHERE o.order_id = ? AND o.customer_id = ?");
$stmt->execute([$order_id, $customer_id]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: my_orders.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    $rating = $_POST['rating'];
    $feedback_message = $_POST['feedback_message'];
    
    try {
        $sql = "INSERT INTO feedback (customer_id, farmer_id, admin_id, feedback_message, rating, feedback_date, status, livestock_id, order_id) 
        VALUES (?, ?, NULL, ?, ?, CURRENT_DATE, 'Pending', ?, ?)";
        
        $ins = $pdo->prepare($sql);
        
        if ($ins->execute([
            $customer_id, 
            $order['farmer_id'], 
            $feedback_message, 
            $rating, 
            $order['livestock_id'], 
            $order_id
        ])) {
            echo "<script>alert('Thank you! Your feedback has been submitted.'); window.location.href='my_orders.php';</script>";
            exit();
        }
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}

include '../inc/header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rate Purchase | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #1976d2;
            --dark-navy: #0d1b2a;
            --accent-gold: #ffca08;
            --glass-bg: rgba(255, 255, 255, 0.85);
        }

        body { 
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6);
            font-family: 'PT Serif', serif; 
            color: #1a1a1a; 
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 140px 20px 60px; 
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .hero-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .hero-section h2 { 
            font-family: 'Cinzel', serif; 
            font-size: clamp(1.5rem, 5vw, 2.5rem); 
            color: var(--dark-navy);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .hero-section .subtitle {
            font-size: 1rem;
            color: #777;
            margin-top: 8px;
        }

        .breadcrumb {
            width: 100%;
            max-width: 550px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            text-align: left;
        }
        
        .breadcrumb a { color: var(--primary-blue); text-decoration: none; }
        .breadcrumb span { margin: 0 10px; color: #ccc; font-size: 0.7rem; }

        .review-card { 
            max-width: 700px; 
            width: 100%;
            padding: 45px 40px; 
            background: var(--glass-bg); 
            backdrop-filter: blur(15px);
            border-radius: 30px; 
            border: 1px solid rgba(144, 202, 249, 0.4);
            box-shadow: 0 20px 40px rgba(0,0,0,0.06);
            box-sizing: border-box;
        }

        .animal-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .animal-preview {
            width: 130px; 
            height: 130px; 
            object-fit: cover; 
            border-radius: 25px;
            border: 4px solid white;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }

        h2 {
            font-family: 'Cinzel', serif;
            text-transform: uppercase;
            color: var(--dark-navy);
            letter-spacing: 1px;
            margin: 10px 0 5px;
            font-size: 1.3rem;
        }

        /* Star Rating */
        .star-rating { 
            display: flex; 
            flex-direction: row-reverse; 
            justify-content: center; 
            gap: 12px; 
            margin: 25px 0; 
        }
        
        .star-rating input { display: none; }
        .star-rating label { 
            font-size: 42px; 
            color: #ddd; 
            cursor: pointer; 
            transition: 0.2s ease;
        }
        
        .star-rating input:checked ~ label { color: var(--accent-gold); }
        .star-rating label:hover, 
        .star-rating label:hover ~ label { 
            color: var(--accent-gold); 
            transform: scale(1.1);
        }

        textarea { 
            width: 100%; 
            padding: 20px; 
            border: 1px solid rgba(0,0,0,0.1); 
            border-radius: 15px;
            height: 140px; 
            box-sizing: border-box; 
            font-family: 'PT Serif', serif;
            font-size: 1rem;
            outline: none;
            transition: 0.3s;
            background: rgba(255, 255, 255, 0.9);
            resize: none;
        }

        textarea:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 15px rgba(25, 118, 210, 0.1);
            background: #fff;
        }

        .btn-submit { 
            width: 100%; 
            padding: 18px; 
            background: var(--primary-blue); 
            color: #fff; 
            border: none; 
            border-radius: 15px;
            cursor: pointer; 
            font-family: 'Cinzel', serif;
            font-weight: bold; 
            font-size: 1rem;
            margin-top: 25px; 
            transition: 0.3s;
            box-shadow: 0 10px 20px rgba(25, 118, 210, 0.2);
        }

        .btn-submit:hover {
            background: var(--dark-navy);
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }

        .back-link {
            display: inline-block;
            margin-top: 25px;
            text-decoration: none;
            color: #888;
            font-family: 'Cinzel', serif;
            font-size: 0.75rem;
            font-weight: bold;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .back-link:hover { color: var(--primary-blue); }

        @media (max-width: 600px) {
            .page-container { padding-top: 100px; }
            .review-card { padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <div class="page-container">
        

        <nav class="breadcrumb"> 
            <a href="customer_dashboard.php"><i class="fas fa-home"></i> Marketplace</a> 
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="my_orders.php">My Orders</a>
            <span><i class="fas fa-chevron-right"></i></span>
            Submit Review
        </nav>

        <div class="review-card">
    <div class="hero-section">
        <h2>Customer Feedback</h2>
        <div class="subtitle">Share your experience with the RanchLink community</div>
    </div> <div class="animal-header">
        <?php 
        $rawImg = $order['image'];
        $displayImg = '';
        if (!empty($rawImg)) {
            $decoded = json_decode($rawImg, true);
            if (is_array($decoded)) { $displayImg = $decoded[0]; } 
            else { $imgParts = explode(',', $rawImg); $displayImg = trim($imgParts[0]); }
        }
        $imgPath = !empty($displayImg) ? '../farmer/uploads/' . $displayImg : '../assets/no-image.png'; 
        ?>
        <img src="<?= htmlspecialchars($imgPath) ?>" class="animal-preview" alt="Livestock">
        <h2>Rate <?= htmlspecialchars($order['name']) ?></h2>
        <p style="color: #888; font-size: 0.85rem; font-style: italic;">
            Order Number: <?= formatOrderNumber($order['order_id']) ?>
        </p>
    </div>

    <form method="POST" action=""> 
        <div class="star-rating">
            <input type="radio" id="5-stars" name="rating" value="5" required /><label for="5-stars">★</label>
            <input type="radio" id="4-stars" name="rating" value="4" /><label for="4-stars">★</label>
            <input type="radio" id="3-stars" name="rating" value="3" /><label for="3-stars">★</label>
            <input type="radio" id="2-stars" name="rating" value="2" /><label for="2-stars">★</label>
            <input type="radio" id="1-star" name="rating" value="1" /><label for="1-star">★</label>
        </div>

        <textarea name="feedback_message" placeholder="How was the quality of the livestock? Was the farmer professional?" required></textarea>
        
        <button type="submit" name="submit_review" class="btn-submit">SUBMIT REVIEW</button>
        
        <div style="text-align: center;">
            <a href="my_orders.php" class="back-link"><i class="fas fa-arrow-left"></i> RETURN TO ORDERS</a>
        </div>
    </form>
</div>
    </div>

</body>
</html>