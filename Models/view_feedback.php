<?php
session_start();
require_once '../db_connect.php';
include '../inc/header.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

try {
    $sql = "SELECT f.*, l.name as livestock_name, farm.farm_name 
            FROM feedback f
            JOIN livestock l ON f.livestock_id = l.livestock_id
            JOIN farmer farm ON l.farmer_id = farm.farmer_id
            WHERE f.customer_id = :cid
            ORDER BY f.feedback_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cid' => $customer_id]);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching feedback: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Feedback History | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6);
            font-family: 'PT Serif', serif; 
            color: #1a1a1a;
            min-height: 100vh;
        }

        .hero-section { 
            height: 80px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-bottom: 25px; 
            max-width: 1000px; 
            width: 100%; 
            padding: 0 20px; 
            margin-top: 20px;
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box; 
            background: #E6F0FA; 
            color: #1976d2; 
            border-radius: 12px; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .hero-section h1 {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 1px;
            margin: 0;
            text-transform: uppercase;
            font-family: 'Cinzel', serif;
        }

        .feedback-container { 
            max-width: 1000px; 
            margin: 0 auto 60px; 
            padding: 0 20px; 
        }

        .breadcrumb-vintage {
            list-style: none;
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            font-size: 14px;
            align-items: center;
        }
        .breadcrumb-vintage a { color: #1976d2; text-decoration: none; }
        .breadcrumb-vintage .current { color: #666; }

        /* Table Styling */
        .feedback-table-wrapper {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(14px);
            border-radius: 18px;
            border: 1px solid rgba(144, 202, 249, 0.4);
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .feedback-table th, .feedback-table td {
            padding: 18px 20px;
            vertical-align: top;
            border-bottom: 1px solid rgba(144, 202, 249, 0.2);
        }

        .feedback-table th {
            background: rgba(144, 202, 249, 0.5);
            font-family: 'Cinzel', serif;
            color: #0d1b2a;
            font-weight: bold;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .feedback-table tr:last-child td {
            border-bottom: none;
        }

        .feedback-table td label {
            display: block;
            font-family: 'Cinzel', serif;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1976d2;
            margin-bottom: 2px;
            font-weight: 700;
            opacity: 0.85;
        }

        .feedback-table td .livestock-name {
            margin-bottom: 12px; 
        }

        .feedback-table td .date-text {
            color: #453c34;
            font-size: 0.9rem;
        }

        .livestock-name {
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            color: #0d1b2a;
            display: block;
            margin-bottom: 5px;
        }

        .date-text { 
            color: #666; 
            font-size: 0.8rem; 
            display: block;
        }

        .star-active { color: #ffca08; text-shadow: 0 0 5px rgba(255,202,8,0.3); }
        .star-inactive { color: #ccc; }

        .status-badge {
            display: inline-block;
            font-size: 10px; 
            padding: 4px 10px; 
            border-radius: 20px;
            text-transform: uppercase; 
            font-weight: bold;
            text-align: center;
        }
        .status-pending { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        .status-approved { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

        .message-text { 
            font-style: italic; 
            color: #453c34; 
            line-height: 1.5;
        }

        .farmer-reply-box {
            padding: 10px 12px;
            background: #f1f8e9; 
            border-radius: 8px;
            border-left: 3px solid #81c784;
            font-size: 0.9rem;
        }

        .reply-header {
            display: block;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            font-size: 0.75rem;
            color: #2e7d32;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .btn-back {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #0d1b2a;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            font-size: 14px;
            transition: 0.3s;
        }
        .btn-back:hover { color: #64b5f6; transform: translateX(-5px); }

        /* Responsive Design for Mobile Views */
        @media (max-width: 768px) {
            .feedback-table, .feedback-table thead, .feedback-table tbody, .feedback-table th, .feedback-table td, .feedback-table tr { 
                display: block; 
            }
            .feedback-table thead { display: none; }
            .feedback-table tr { border-bottom: 2px solid rgba(144, 202, 249, 0.3); padding: 15px 0; }
            .feedback-table td { border-bottom: none; padding: 8px 20px; position: relative; }
        }
    </style>
</head>
<body>

<div class="feedback-container">
    <div class="hero-section">
        <h1>Feedback History</h1>
    </div>
    
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb-vintage">
            <li><a href="customer_dashboard.php"><i class="fas fa-home"></i> Marketplace</a></li>
            <li><i class="fas fa-chevron-right" style="font-size: 10px; color: #ccc;"></i></li>
            <li class="current">My Feedback</li>
        </ul>
    </nav>

    <?php if ($feedbacks): ?>
        <div class="feedback-table-wrapper">
            <table class="feedback-table">
                <thead>
                    <tr>
                        <th style="width: 7%; text-align: center;">No.</th>
                        <th style="width: 25%;">Livestock Details</th>
                        <th style="width: 15%;">Rating</th>
                        <th style="width: 30%;">My Message</th>
                        <th style="width: 30%;">Farmer's Response</th>
                        <th style="width: 15%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1; 
                    foreach ($feedbacks as $fb): ?>
                        <tr>
                            <td style="text-align: center;">
                                <span class="index-number"><?= $i++ ?>.</span>
                            </td>
                            
                            <!-- Livestock Info -->
                            <td>
                                <label>Order's Item:</label>
                                <strong class="livestock-name"><?= htmlspecialchars($fb['livestock_name']) ?></strong>
                                <label>Date:</label>
                                <span class="date-text">Submitted: <?= date('d M Y', strtotime($fb['feedback_date'])) ?></span>
                            </td>
                            
                            <!-- Star Rating -->
                            <td>
                                <div style="font-size: 1rem; white-space: nowrap;">
                                    <?php for ($star = 1; $star <= 5; $star++): ?>
                                        <i class="fas fa-star <?= $star <= $fb['rating'] ? 'star-active' : 'star-inactive' ?>"></i>
                                        <?php endfor; ?>
                                </div>
                            </td>
                            
                            <!-- Customer Feedback message -->
                            <td>
                                <div class="message-text">
                                    <i class="fas fa-quote-left" style="color: #90caf9; margin-right: 5px; opacity: 0.5; font-size: 0.85rem;"></i>
                                    <?= htmlspecialchars($fb['feedback_message']) ?>
                                </div>
                            </td>
                            
                            <!-- Farmer's Reply Response Area -->
                            <td>
                                <?php if (!empty($fb['farmer_reply'])): ?>
                                    <div class="farmer-reply-box">
                                        <span class="reply-header">
                                            <i class="fas fa-store"></i> 
                                            <?= htmlspecialchars($fb['farm_name'] ?? 'Farmer') ?>'s Response
                                        </span>
                                        <p style="color: #2e7d32; font-style: italic; margin: 0;">
                                            <?= htmlspecialchars($fb['farmer_reply']) ?>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <p style="font-size: 0.8rem; color: #999; margin: 0; font-style: italic;">
                                        <i class="fas fa-hourglass-half"></i> Waiting for farmer's response...
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td>
                                    <span class="status-badge status-<?= strtolower($fb['status']) ?>">
                                        <?= htmlspecialchars($fb['status']) ?>
                                    </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 60px; background: rgba(255,255,255,0.5); border-radius: 24px; border: 1px solid rgba(144, 202, 249, 0.3);">
            <i class="fas fa-comment-slash fa-4x" style="color: #90caf9; margin-bottom: 20px; opacity: 0.5;"></i>
            <p style="font-size: 1.2rem; color: #453c34; margin-bottom: 15px;">You haven't submitted any feedback yet.</p>
            <a href="my_orders.php" style="color: #64b5f6; font-weight: bold; text-decoration: none;">View my orders to leave a review &rarr;</a>
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 40px;">
        <a href="customer_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Marketplace
        </a>
    </div>
</div>

</body>
</html>