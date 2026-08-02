<?php
session_start();
require_once '../db_connect.php';
include '../inc/numbers.php';

$order_id = $_GET['order_id'] ?? null;

if (!$order_id || !isset($_SESSION['customer_id'])) {
    header("Location: my_orders.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

try {
    $stmt = $pdo->prepare("SELECT order_id FROM orders WHERE order_id = ? AND customer_id = ?");
    $stmt->execute([$order_id, $customer_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found or access denied.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

include '../inc/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Refund | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cinzel', serif; background: radial-gradient(circle at top, #fdf6ec, #f4efe6); padding: 20px; }
        .form-container { 
            max-width: 500px; margin: 120px auto; background: white; 
            padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
        }
        h2 { font-family: 'Cinzel', serif; text-align: center; color: #2c3e50; }
        .input-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        select, textarea, input[type="file"] { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box;
        }
        .btn-submit { 
            background: #1976d2; color: white; border: none; padding: 12px; 
            width: 100%; border-radius: 25px; font-family: 'Cinzel'; font-weight: bold; cursor: pointer; 
        }
        .btn-submit:hover {
            color: white;
            background-color: black;
        }
        .btn-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%; 
            margin-top: 8px;
            padding: 12px;
            color: #718096; 
            text-decoration: none;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            font-size: 0.75rem;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        .btn-cancel:hover {
            color: red;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Refund Request #<?= formatOrderNumber($order['order_id']) ?></h2>
    <p style="font-size: 0.8rem; color: #7f8c8d; text-align: center;">Please provide clear evidence of the issue.</p>

    <form action="../farmer/process_refund_request.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">

        <div class="input-group">
            <label>Reason for Refund</label>
            <select name="reason" required>
                <option value="">-- Select Reason --</option>
                <option value="Livestock Injured">Livestock Injured</option>
                <option value="Health Issues">Health Issues/Sick</option>
                <option value="Wrong Breed/Animal">Wrong Breed/Animal Delivered</option>
                <option value="Deceased on Arrival">Deceased on Arrival</option>
            </select>
        </div>

        <div class="input-group">
            <label>Additional Notes</label>
            <textarea name="notes" rows="4" placeholder="Describe the situation in detail..." required></textarea>
        </div>

        <div class="input-group">
            <label>Upload Photo Evidence</label>
            <input type="file" name="evidence" accept="image/*" required>
        </div>

        <button type="submit" class="btn-submit">Submit Request to Farmer</button>
        <a href="my_orders.php" class="btn-cancel"><i class="fas fa-close"></i> Cancel & Return</a>
    </form>
</div>

</body>
</html>