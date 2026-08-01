<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$sales_stmt = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status = 'Paid'");
$total_revenue = $sales_stmt->fetchColumn() ?: 0;

$order_count = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

$category_query = "SELECT l.category, SUM(o.total_price) as revenue 
                   FROM orders o 
                   JOIN livestock l ON o.livestock_id = l.livestock_id 
                   WHERE o.status = 'Paid' 
                   GROUP BY l.category";
$categories = $pdo->query($category_query)->fetchAll(PDO::FETCH_ASSOC);

$transactions = $pdo->query("SELECT o.*, c.name as customer_name, l.name as livestock_name 
                             FROM orders o 
                             JOIN customer c ON o.customer_id = c.customer_id 
                             JOIN livestock l ON o.livestock_id = l.livestock_id 
                             ORDER BY o.order_date DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Reports | Admin Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Raleway:wght@300;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --gold: #b89b5e; --charcoal: #2c2c2c; --cream: #f9f7f2; --border: #453c34; }
        body { font-family: 'Raleway', sans-serif; background: var(--cream); margin: 0; padding: 40px; }
        .container { max-width: 1200px; margin: auto; }
        
        .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        h2 { font-family: 'Cinzel', serif; color: var(--charcoal); text-transform: uppercase; letter-spacing: 2px; }

        .report-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .report-card { background: white; border: 1px solid var(--border); padding: 25px; box-shadow: 6px 6px 0px var(--gold); }
        .report-card h4 { font-family: 'Cinzel', serif; margin: 0; color: #777; font-size: 0.8rem; }
        .report-card .amount { font-size: 2rem; font-weight: bold; color: var(--charcoal); margin: 10px 0; }

        .data-section { background: white; border: 1px solid var(--border); padding: 30px; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th { font-family: 'Cinzel', serif; text-align: left; padding: 15px; border-bottom: 2px solid var(--gold); background: #fafafa; }
        .report-table td { padding: 15px; border-bottom: 1px solid #eee; font-size: 0.9rem; }

        .btn-print { background: var(--charcoal); color: white; padding: 10px 20px; font-family: 'Cinzel', serif; border: none; cursor: pointer; transition: 0.3s; }
        .btn-print:hover { background: var(--gold); }
    </style>
</head>
<body>

<div class="container">
    <div class="report-header">
        <div>
            <a href="admin_dashboard.php" style="color: var(--gold); text-decoration: none;"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <h2>Sales & Business Reports</h2>
        </div>
        <!-- <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Export Report</button> -->
        <a href="export_report.php" class="btn-print" style="text-decoration: none; background: #2e7d32; margin-left: 10px;">
        <i class="fas fa-file-excel"></i> Download Excel
        </a>
    </div>

    <div class="report-grid">
        <div class="report-card">
            <h4>Total Revenue</h4>
            <div class="amount">RM <?= number_format($total_revenue, 2) ?></div>
            <small style="color: green;">+8% from last month</small>
        </div>
        <div class="report-card">
            <h4>Total Orders</h4>
            <div class="amount"><?= $order_count ?></div>
            <small>Completed Transactions</small>
        </div>
        <div class="report-card">
            <h4>Top Category</h4>
            <div class="amount"><?= $categories[0]['category'] ?? 'N/A' ?></div>
            <small>By Revenue Generation</small>
        </div>
    </div>

    <div class="data-section">
        <h3 style="font-family: 'Cinzel', serif; margin-bottom: 20px;">Detailed Transaction Logs</h3>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Livestock</th>
                    <th>Total Price</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($t['order_date'])) ?></td>
                    <td>ORD-<?= $t['order_id'] ?></td>
                    <td><?= htmlspecialchars($t['customer_name']) ?></td>
                    <td><?= htmlspecialchars($t['livestock_name']) ?></td>
                    <td><strong>RM <?= number_format($t['total_price'], 2) ?></strong></td>
                    <td>
                        <span style="color: <?= $t['status'] == 'Paid' ? '#2e7d32' : '#c62828' ?>;">
                            <?= $t['status'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>