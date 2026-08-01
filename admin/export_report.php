<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    exit("Unauthorized access");
}

$query = "SELECT o.order_date, o.order_id, c.name as customer_name, l.name as livestock_name, o.total_price, o.status 
          FROM orders o 
          JOIN customer c ON o.customer_id = c.customer_id 
          JOIN livestock l ON o.livestock_id = l.livestock_id 
          ORDER BY o.order_date DESC";
$stmt = $pdo->query($query);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "Business_Report_" . date('Ymd') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

echo "<table border='1'>";
echo "<tr>
        <th style='background-color: #b89b5e; color: white;'>Date</th>
        <th style='background-color: #b89b5e; color: white;'>Order ID</th>
        <th style='background-color: #b89b5e; color: white;'>Customer</th>
        <th style='background-color: #b89b5e; color: white;'>Livestock</th>
        <th style='background-color: #b89b5e; color: white;'>Total Price (RM)</th>
        <th style='background-color: #b89b5e; color: white;'>Status</th>
      </tr>";

foreach ($transactions as $t) {
    echo "<tr>";
    echo "<td>" . date('d/m/Y', strtotime($t['order_date'])) . "</td>";
    echo "<td>#ORD-" . $t['order_id'] . "</td>";
    echo "<td>" . htmlspecialchars($t['customer_name']) . "</td>";
    echo "<td>" . htmlspecialchars($t['livestock_name']) . "</td>";
    echo "<td>" . number_format($t['total_price'], 2) . "</td>";
    echo "<td>" . $t['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";
exit();