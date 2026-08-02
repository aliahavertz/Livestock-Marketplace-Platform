<?php
session_start();
require_once '../db_connect.php';
require_once '../inc/numbers.php';

if (!isset($_SESSION['farmer_id'])) exit("Unauthorized");

$farmer_id = $_SESSION['farmer_id'];
$report_type = $_GET['report_type'] ?? 'all';
$start_date = null;
$end_date = date('Y-m-d');

if ($report_type !== 'all') {
    switch ($report_type) {
        case 'daily':   $start_date = date('Y-m-d'); break;
        case 'weekly':  $start_date = date('Y-m-d', strtotime('-7 days')); break;
        case 'monthly': $start_date = date('Y-m-d', strtotime('-30 days')); break;
        case 'annually': $start_date = date('Y-m-d', strtotime('-1 year')); break;
    }
}

$filter_query = "";
$params = [];
if ($start_date) {
    $filter_query = " AND o.order_date BETWEEN ? AND ?";
    $params = [$start_date . " 00:00:00", $end_date . " 23:59:59"];
}

$sql = "
    (SELECT 
        o.order_date as activity_date, 
        o.order_id::text as ref_id, 
        c.name as customer_name, 
        l.name as item_details, 
        o.total_price as amount, 
        o.status as order_status, 
        o.refund_reason,
        p.payment_status,
        COALESCE(d.deliveryfee, 0) as delivery_fee, 
        o.selected_services,
        COALESCE(hs.servicefee, 0) as service_fees,
        'Order' as type_label
    FROM orders o 
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN livestock l ON oi.livestock_id = l.livestock_id 
    JOIN customer c ON o.customer_id = c.customer_id
    LEFT JOIN payments p ON o.order_id = p.order_id
    LEFT JOIN delivery d ON o.order_id = d.order_id
    LEFT JOIN harvestservice hs ON (l.livestock_id = hs.livestockid AND o.selected_services = hs.servicetype)
    WHERE l.farmer_id = ? $filter_query)

    UNION ALL

    (SELECT 
        ad.created_at as activity_date, 
        ad.auction_id::text as ref_id, 
        c.name as customer_name, 
        l.name as item_details, 
        ad.amount as amount, 
        'Completed' as order_status, 
        '' as refund_reason,
        'paid' as payment_status,
        0 as delivery_fee, 
        'Auction Deposit' as selected_services,
        0 as service_fees,
        'Deposit' as type_label
    FROM auction_deposits_paid ad
    JOIN auction a ON ad.auction_id = a.auction_id
    JOIN livestock l ON a.livestock_id = l.livestock_id
    JOIN customer c ON ad.customer_id = c.customer_id
    WHERE l.farmer_id = ? " . str_replace('o.order_date', 'ad.created_at', $filter_query) . ")

    ORDER BY activity_date DESC";

$final_params = array_merge([$farmer_id], $params, [$farmer_id], $params);
$stmt = $pdo->prepare($sql);
$stmt->execute($final_params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "RanchLink_Full_Report_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

echo "<table border='1'>";
echo "<tr style='background-color:#1976d2; color:white;'>
        <th>No.</th>
        <th>Date</th>
        <th>Ref Number</th>
        <th>Customer</th>
        <th>Livestock</th>
        <th>Payment Status</th>
        <th>Order Status</th>
        <th>Refund Reason</th>
        <th>Service Type</th>
        <th>Service Fee (RM)</th>
        <th>Delivery Fee (RM)</th>
        <th>Total (RM)</th>
      </tr>";

$total_rev = 0;
$i = 1;
foreach ($rows as $r) {
    $is_paid = (strtolower($r['payment_status']) === 'paid');
    $not_cancelled = !in_array(strtolower($r['order_status']), ['refunded', 'cancelled', 'terminated']);
    
    if ($is_paid && $not_cancelled) {
        $total_rev += (float)$r['amount'];
    }

    $display_id = ($r['type_label'] === 'Order') ? formatOrderNumber($r['ref_id']) : 'DEP-' . $r['ref_id'];

    echo "<tr>";
    echo "<td>" . $i++ . "</td>";
    echo "<td>" . date('d/m/Y H:i', strtotime($r['activity_date'])) . "</td>";
    echo "<td>" . $display_id . "</td>";
    echo "<td>" . htmlspecialchars($r['customer_name']) . "</td>";
    echo "<td>" . htmlspecialchars($r['item_details']) . "</td>";
    echo "<td>" . ucfirst($r['payment_status']) . "</td>";
    echo "<td>" . ucfirst($r['order_status']) . "</td>";
    echo "<td>" . (!empty($r['refund_reason']) ? htmlspecialchars($r['refund_reason']) : '-') . "</td>";
    echo "<td>" . ($r['selected_services'] ?: 'None') . "</td>";
    echo "<td align='right'>" . number_format($r['service_fees'], 2) . "</td>";
    echo "<td align='right'>" . number_format($r['delivery_fee'], 2) . "</td>"; 
    echo "<td align='right' style='font-weight:bold;'>" . number_format($r['amount'], 2) . "</td>";
    echo "</tr>";
}

echo "<tr style='background-color:#e8f5e9; font-weight:bold;'>
        <td colspan='11' align='right'>TOTAL PAID REVENUE:</td>
        <td align='right'>RM " . number_format($total_rev, 2) . "</td>
      </tr>";
echo "</table>";
exit();