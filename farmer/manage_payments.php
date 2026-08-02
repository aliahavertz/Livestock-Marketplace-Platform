<?php
session_start();
require_once '../vendor/autoload.php'; 
require_once '../db_connect.php';
require_once '../inc/functions.php';
require_once '../inc/numbers.php';
require_once '../pusher/pusher_config.php'; 

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['farmer_id'])) {
    header("Location: farmer_login.php");
    exit();
}

$farmer_id = $_SESSION['farmer_id'];

$stmt = $pdo->prepare("SELECT farm_name FROM farmer WHERE farmer_id = ?");
$stmt->execute([$farmer_id]);
$name = $stmt->fetchColumn();


$stripe = new \Stripe\StripeClient('sk_test_51SipzdEhjpQ4R31fUn7iS5Ld3K4vigl5Hzx05UWBokwZ1dypneBTDXsSG0yAq4NiR4Bbag336ykhYseXJw5CHDJZ00Pi7SPtFt');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? null;

    if (!$order_id) {
        die("Error: Missing Order ID.");
    }

    if (isset($_POST['verify_payment'])) {
        $new_status = $_POST['payment_status'];
        $update = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE order_id = ?");
        $update->execute([$new_status, $order_id]);
        header("Location: manage_payments.php?msg=updated");
        exit();
    }

   if ((isset($_POST['action']) && $_POST['action'] === 'refund') || (isset($_POST['decision']) && $_POST['decision'] === 'Approved')) {
    try {
        $order_id = $_POST['order_id'] ?? '';
        $payment_label = $_POST['payment_label'] ?? 'Full Payment';

        if ($payment_label === 'Deposit') {
           $stmt = $pdo->prepare("
                    SELECT p.stripe_payment_id, a.livestock_id, adp.payment_id, adp.customer_id, l.name as animal_name
                    FROM auction_deposits_paid adp
                    JOIN auction a ON adp.auction_id = a.auction_id
                    JOIN livestock l ON a.livestock_id = l.livestock_id
                    JOIN payments p ON adp.payment_id = p.payment_id
                    WHERE p.transaction_id = ?
                ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if ($order && !empty($order['stripe_payment_id'])) {
                $clean_stripe_id = preg_replace('/-\d+$/', '', trim($order['stripe_payment_id']));

                $stripe->refunds->create(['payment_intent' => $clean_stripe_id]);

                $pdo->beginTransaction();
                $pdo->prepare("UPDATE auction_deposits_paid SET status = 'refunded' WHERE payment_id = ?")->execute([$order['payment_id']]);
                $pdo->prepare("UPDATE payments SET payment_status = 'refunded' WHERE payment_id = ?")->execute([$order['payment_id']]);
                $pdo->prepare("UPDATE livestock SET availability_status = 'Available' WHERE livestock_id = ?")->execute([$order['livestock_id']]);

                $receipt_link = "<a href='/LivestockMarketplace/farmer/generate_receipt_refund.php?order_id=" . urlencode($order_id) . "&type=deposit' target='_blank' style='color: #1976d2; font-weight: bold; text-decoration: underline;'>Click Here</a>";
                $notif_title = "Deposit Refunded";
                    $notif_msg = "Your auction entry deposit refund for " . $order['animal_name'] . " has been successful. View receipt here: " . $receipt_link;
                    
                    notify($pdo, $order['customer_id'], 'customer', $notif_title, $notif_msg);
                    
                    $pdo->commit();

                header("Location: manage_payments.php?msg=" . urlencode("Deposit Refunded Successfully"));
                exit();
            } else {
                die("Error: This deposit payment reference could not be processed via Stripe.");
            }
        } else {
            $stmt = $pdo->prepare("
                    SELECT o.stripe_payment_id, oi.livestock_id, o.customer_id, l.name as animal_name 
                    FROM orders o
                    JOIN order_items oi ON o.order_id = oi.order_id
                    JOIN livestock l ON oi.livestock_id = l.livestock_id
                    WHERE o.order_id = ?
                    LIMIT 1
                ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if ($order && !empty($order['stripe_payment_id'])) {
                $clean_stripe_id = preg_replace('/-\d+$/', '', trim($order['stripe_payment_id']));

                $stripe->refunds->create(['payment_intent' => $clean_stripe_id]);

                $pdo->beginTransaction();
                $pdo->prepare("UPDATE payments SET payment_status = 'refunded' WHERE order_id = ?")->execute([$order_id]);
                $pdo->prepare("UPDATE orders SET status = 'Refunded', refund_completed_at = NOW() WHERE order_id = ?")->execute([$order_id]);
                $pdo->prepare("UPDATE order_items SET item_status = 'Refunded' WHERE order_id = ?")->execute([$order_id]);
                $pdo->prepare("UPDATE livestock SET availability_status = 'Available' WHERE livestock_id = ?")->execute([$order['livestock_id']]);

                $formatted_order_id = formatOrderNumber($order_id);
                    $receipt_link = "<a href='/LivestockMarketplace/farmer/generate_receipt_refund.php?order_id=" . urlencode($order_id) . "&type=full' target='_blank' style='color: #1976d2; font-weight: bold; text-decoration: underline;'>Click Here</a>";
                    $notif_title = "Order Refunded";
                    $notif_msg = "Your full payment refund for " . $order['animal_name'] . " (Order #" . $formatted_order_id . ") has been processed successfully. View receipt here: " . $receipt_link;
                    
                    notify($pdo, $order['customer_id'], 'customer', $notif_title, $notif_msg);

                $pdo->commit();

                $msg = isset($_POST['decision']) ? "Refund Approved" : "Manual Refund Processed";
                header("Location: manage_payments.php?msg=" . urlencode($msg));
                exit();
            } else {
                die("Error: This order was not paid via Stripe or ID is missing.");
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Refund Error: " . $e->getMessage());
    }
}

if (isset($_POST['decision']) && $_POST['decision'] === 'Rejected') {
    $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : 'No reason provided';
    $pdo->prepare("UPDATE orders SET status = 'Paid', rejection_reason = ? WHERE order_id = ?")
    ->execute([$rejection_reason, $order_id]);
    header("Location: manage_payments.php?msg=Refund Rejected");
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'flag') {
    $pdo->beginTransaction(); 
    try {
        $update = $pdo->prepare("UPDATE orders SET is_suspicious = TRUE, status = 'Suspended' WHERE order_id = ?");
        $update->execute([$order_id]);

        $pdo->commit();
        header("Location: manage_payments.php?msg=flagged");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error flagging order: " . $e->getMessage());
    }
}
}

$filter = isset($_GET['status']) ? $_GET['status'] : 'All';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$date_filter = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';

try {
    $params = [':fid' => $farmer_id];
    $status_condition = "";
    $search_condition = "";
    $date_condition = ""; 

    // 1. STATUS FILTER
    if ($filter !== 'All') {
        if (strtolower($filter) === 'unpaid') {
            $status_condition = " AND payment_status IS NULL ";
        } else {
            $status_condition = " AND (LOWER(item_status) = :status OR LOWER(payment_status) = :status OR LOWER(order_status) = :status) ";
            $params[':status'] = strtolower($filter);
        }
    }

    if (!empty($search)) {
        $search_condition = " AND (customer_name ILIKE :search OR order_id ILIKE :search OR livestock_names ILIKE :search) ";
        $params[':search'] = "%$search%";
    }

    switch ($date_filter) {
        case 'today':
            $date_condition = " AND trans_date::date = CURRENT_DATE ";
            break;
        case '2_days':
            $date_condition = " AND trans_date >= (CURRENT_TIMESTAMP - INTERVAL '2 days') ";
            break;
        case '7_days':
            $date_condition = " AND trans_date >= (CURRENT_TIMESTAMP - INTERVAL '7 days') ";
            break;
        case '30_days':
            $date_condition = " AND trans_date >= (CURRENT_TIMESTAMP - INTERVAL '30 days') ";
            break;
        default:
            $date_condition = "";
            break;
    }

    $search_clause = "";
    $decoded_search_id = null;

    if (!empty($search)) {
        if (preg_match('/^[A-Z0-9]{4,7}$/i', $search)) {
            try {
                $dec_val = base_convert(strtolower($search), 36, 10);
                if ($dec_val > 10485760) {
                    $decoded_search_id = (string)($dec_val - 10485760);
                }
            } catch (Exception $e) {
                $decoded_search_id = null;
            }
        }

        if (!empty($decoded_search_id)) {
            $search_clause = " AND (customer_name ILIKE :search OR order_id = :decoded_id OR order_id ILIKE :search OR livestock_names ILIKE :search) ";
        } else {
            $search_clause = " AND (customer_name ILIKE :search OR order_id ILIKE :search OR livestock_names ILIKE :search) ";
        }
    }

    $count_sql = "
    SELECT COUNT(*) FROM (
        SELECT * FROM (
            (SELECT 
                CAST(o.order_id AS TEXT) as order_id, 
                NULL as deposit_receipt_id,
                NULL as auction_id,
                o.order_date as trans_date, 
                oi.item_status, 
                o.status as order_status,
                c.name as customer_name, 
                pay.payment_status,
                STRING_AGG(l.name, ', ') as livestock_names
                FROM orders o
                JOIN order_items oi ON o.order_id = oi.order_id
                JOIN livestock l ON oi.livestock_id = l.livestock_id
                JOIN customer c ON o.customer_id = c.customer_id
                LEFT JOIN payments pay ON o.order_id = pay.order_id 
                WHERE l.farmer_id = :fid 
                GROUP BY o.order_id, o.order_date, oi.item_status, o.status, c.name, pay.payment_status)

            UNION ALL

            (SELECT 
                COALESCE(p.transaction_id, CAST(adp.payment_id AS TEXT)) as order_id, 
                CAST(adp.payment_id AS TEXT) as deposit_receipt_id,
                CAST(a.auction_id AS TEXT) as auction_id,
                adp.created_at as trans_date, 
                'Paid' as item_status, 
                'Deposit Paid' as order_status,
                c.name as customer_name, 
                adp.status as payment_status, 
                l.name as livestock_names
                FROM auction_deposits_paid adp
                JOIN customer c ON adp.customer_id = c.customer_id
                JOIN auction a ON adp.auction_id = a.auction_id 
                JOIN livestock l ON a.livestock_id = l.livestock_id
                LEFT JOIN payments p ON adp.payment_id = p.payment_id
                WHERE l.farmer_id = :fid)
        ) as inner_counts
    ) as combined_counts
    WHERE 1=1 $status_condition $search_clause $date_condition";

    $count_stmt = $pdo->prepare($count_sql);
    
    $count_params = [':fid' => $farmer_id];
    if (isset($params[':status'])) $count_params[':status'] = $params[':status'];
    if (!empty($search)) $count_params[':search'] = "%$search%";
    if (!empty($decoded_search_id)) $count_params[':decoded_id'] = $decoded_search_id;
    
    $count_stmt->execute($count_params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_rows / $limit));


    $sql = "
    SELECT * FROM (
        SELECT * FROM (
            -- Full Payments
            (SELECT 
                CAST(o.order_id AS TEXT) as order_id, 
                pay.payment_id,
                NULL as deposit_receipt_id,
                NULL as auction_id,
                o.customer_id,
                o.order_date as trans_date, 
                o.status as order_status,
                oi.item_status, 
                c.name as customer_name, 
                COALESCE(pay.amount, 0) as paid_amount, 
                pay.payment_status,
                o.is_suspicious, 
                o.stripe_payment_id, 
                pay.payment_method,
                'Full Payment' as payment_label,
                STRING_AGG(l.name, ', ') as livestock_names,
                (ARRAY_AGG(l.image))[1] as livestock_image,
                COUNT(oi.order_item_id) as item_count,
                o.refund_reason
                FROM orders o
                JOIN order_items oi ON o.order_id = oi.order_id
                JOIN livestock l ON oi.livestock_id = l.livestock_id
                JOIN customer c ON o.customer_id = c.customer_id
                LEFT JOIN payments pay ON o.order_id = pay.order_id 
                WHERE l.farmer_id = :fid 
                GROUP BY o.order_id, pay.payment_id, o.order_date, o.status, oi.item_status, c.name, pay.amount, pay.payment_status, o.is_suspicious, o.stripe_payment_id, pay.payment_method, o.refund_reason)

            UNION ALL

            -- Deposits
            (SELECT 
                COALESCE(p.transaction_id, CAST(adp.payment_id AS TEXT)) as order_id, 
                adp.payment_id as payment_id, 
                CAST(adp.payment_id AS TEXT) as deposit_receipt_id,
                CAST(a.auction_id AS TEXT) as auction_id,
                adp.customer_id,
                adp.created_at as trans_date, 
                'Deposit Paid' as order_status,
                'Paid' as item_status, 
                c.name as customer_name, 
                adp.amount as paid_amount, 
                adp.status as payment_status, 
                FALSE as is_suspicious, 
                p.stripe_payment_id, 
                p.payment_method,   
                'Deposit' as payment_label,
                l.name as livestock_names,
                l.image as livestock_image,
                1 as item_count,
                NULL as refund_reason 
                FROM auction_deposits_paid adp
                JOIN customer c ON adp.customer_id = c.customer_id
                JOIN auction a ON adp.auction_id = a.auction_id 
                JOIN livestock l ON a.livestock_id = l.livestock_id
                LEFT JOIN payments p ON adp.payment_id = p.payment_id 
                WHERE l.farmer_id = :fid)
        ) as inner_payments
    ) as combined_payments
    WHERE 1=1 $status_condition $search_clause $date_condition
    ORDER BY trans_date DESC
    LIMIT $limit OFFSET $offset";

    $final_params = [':fid' => $farmer_id];
    if (isset($params[':status'])) $final_params[':status'] = $params[':status'];
    if (!empty($search)) $final_params[':search'] = "%$search%";
    if (!empty($decoded_search_id)) $final_params[':decoded_id'] = $decoded_search_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($final_params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$base_params = [
    'status' => $filter,
    'search' => $search,
    'date_range' => $date_filter
];
$query_string = http_build_query($base_params);
$base_url = "?" . $query_string;

$sql = "SELECT * FROM farmer WHERE farmer_id = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id', $farmer_id, PDO::PARAM_INT);
$stmt->execute();
$farmer = $stmt->fetch(PDO::FETCH_ASSOC);

$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND user_type = 'farmer' AND is_read = FALSE");
$stmtUnread->execute(['uid' => $farmer_id]);
$unreadCount = $stmtUnread->fetchColumn();

$imageFolder = "uploads/";
if (!empty($farmer['profile_image'])) {
    $imagePath = $imageFolder . $farmer['profile_image'];
} else {
    $imagePath = $imageFolder . "default.png";
}

if (!file_exists($imagePath)) {
    $imagePath = $imageFolder . "default.png";
}

function syncStatus($pdo, $order_id, $payment_status, $order_label) {
    try {
        $pdo->beginTransaction();

        $stmt1 = $pdo->prepare("UPDATE payments SET payment_status = ? WHERE order_id = ?");
        $stmt1->execute([strtolower($payment_status), $order_id]);

        $stmt2 = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt2->execute([$order_label, $order_id]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Payment Management | RanchLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../inc/css/sidebar.css?v=1.4">

    <style>

        .page-wrapper { max-width: 1100px; margin: 40px auto; padding: 0 20px; }

        .breadcrumb-wrapper {
            max-width: 850px;
            margin: 40px auto 10px; 
            padding: 0 10px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            list-style: none;
            font-family: 'Cinzel', serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .breadcrumb li {
            display: flex;
            align-items: center;
            color: #777;
        }

        .breadcrumb li a {
            text-decoration: none;
            color: #1976d2;
            transition: 0.3s;
        }

        .breadcrumb li a:hover {
            color: #0d1b2a;
        }

        .breadcrumb li::after {
            content: "\f105"; 
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            margin: 0 15px;
            color: #ccc;
            font-size: 12px;
        }

        .breadcrumb li:last-child::after {
            display: none;
        }

        .breadcrumb li.active {
            color: #453c34;
        }

        .back-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #1976d2; margin-bottom: 0; font-weight: bold; border: 1px solid #1976d2; border-radius: 30px; padding: 8px; font-size: 0.85rem;}
        .back-btn:hover {
            color: white;
            background-color: #1976d2;
        }

        .btn-receipt-refund {
            background-color: #546e7a; 
            color: #ffffff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 11px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-receipt-refund:hover {
            background-color: #37474f;
        }
        
        .card-header-row {
            display: flex;
            align-items: center;       
            justify-content: space-between; 
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding-bottom: 15px;
        }

        .main-title { 
            font-family: 'Cinzel', serif; 
            text-align: center; 
            text-transform: uppercase; 
            color: #0d1b2a; 
            margin: 0;                
            padding: 0;               
            letter-spacing: 1px;
            flex-grow: 1;             
        }

        .glass-card { 
            background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(15px);
            padding: 30px; border-radius: 30px; border: 1px solid rgba(144, 202, 249, 0.4);
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
            margin-top: 10px;
        }

        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; table-layout: auto; }
        .modern-table th { 
            font-family: 'Cinzel', serif; color: #1976d2; font-size: 0.8rem; 
            text-transform: uppercase; padding: 10px 20px; text-align: left;
        }
        .modern-table td { 
            background: white; padding: 20px; 
            border-top: 1px solid rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.02);
        }
        .modern-table tr td:first-child { border-left: 1px solid rgba(0,0,0,0.02); border-radius: 15px 0 0 15px; }
        .modern-table tr td:last-child { border-right: 1px solid rgba(0,0,0,0.02); border-radius: 0 15px 15px 0; }
        .modern-table td:nth-child(2) {
            max-width: 200px;       
            word-break: break-all;  
            font-family: monospace; 
            font-size: 0.8rem;      
            line-height: 1.4;       
            vertical-align: middle;
            padding-right: 10px;
        }
        .modern-table th:nth-child(3), 
        .modern-table td:nth-child(3) {
            max-width: 150px; 
        }

        .suspicious-row td { background: #fff5f5 !important; border-color: rgba(198, 40, 40, 0.1); }
        .suspicious-badge { color: #c62828; font-family: 'Cinzel', serif; font-weight: bold; font-size: 0.65rem; display: block; margin-top: 5px; }

        .status-badge { 
            padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; 
            font-family: 'Cinzel', serif; font-weight: bold; text-transform: uppercase;
        }
        .status-paid { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-failed { background: #ffebee; color: #c62828; }
        .status-terminated { background: #ffebee; color: #c62828; }
        .status-refunded { background: #f5f5f5; color: #616161; }
        .status-unpaid { 
            background: #f5f5f5; 
            color: #616161; 
            border: 1px solid #e0e0e0; 
        }
        .status-suspended { 
            background: red; 
            color: white; 
        }

        /*.suspicious-row td { 
            background: #fdf2f2 !important; 
            border-left: 5px solid #c62828; 
        }*/

        .action-btn { 
            border: none; padding: 8px 15px; border-radius: 50px; cursor: pointer; 
            font-family: 'Cinzel', serif; font-size: 0.75rem; color: white; transition: 0.3s;
            display: inline-flex; align-items: center; gap: 5px; font-weight: bold;
        }
        .verify-btn { background: #1976d2; }
        .btn-refund { background: #ef6c00; }
        .btn-flag { background: #c62828; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        select.status-select { 
            padding: 8px; border-radius: 10px; border: 1px solid #ddd; 
            font-family: 'PT Serif', serif; font-size: 0.85rem; outline: none;
        }

        .success-msg {
            background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 15px;
            text-align: center; margin-bottom: 20px; font-family: 'Cinzel', serif; font-weight: bold;
        }
        .filter-tabs {
            display: flex;
            gap: 10px;
            justify-content: center;
            border-bottom: none;
            margin-bottom: 10px;
            padding-bottom: 10px;
        }

        .tab-link {
            text-decoration: none;
            font-family: 'Cinzel', serif;
            font-size: 0.75rem;
            font-weight: bold;
            color: #1976d2;
            padding: 8px 20px;
            border: 1px solid #1976d2;
            border-radius: 50px;
            transition: 0.3s;
        }

        .tab-link.active {
            background: #1976d2;
            color: white;
        }

        .tab-link:hover:not(.active) {
            background: rgba(25, 118, 210, 0.1);
            color: #1976d2;
        }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 30px; }
        .pagination a { 
            text-decoration: none; color: #1976d2; padding: 8px 16px; border-radius: 8px; 
            background: white; border: 1px solid rgba(25, 118, 210, 0.2); 
            font-family: 'Cinzel', serif; font-weight: bold; font-size: 0.8rem; transition: 0.3s;
        }
        .pagination a.active { background: #1976d2; color: white; border-color: #1976d2; }
        .pagination a:hover:not(.active) { background: rgba(25, 118, 210, 0.05); }
        .pagination span { font-family: 'Cinzel', serif; font-size: 0.8rem; color: #888; }

        .btn-receipt { 
            background: #546e7a; 
            text-decoration:none;
        }

        .btn-receipt:hover {
            background-color: #1976d2;
        }

        .btn-receipt-deposit {
            background-color: #135AA1; 
            color: #ffffff;
            text-decoration: none;
        }
        .btn-receipt-deposit:hover {
            background-color: #1976d2;
        }

        .method-tag {
            font-size: 0.7rem;
            color: #777;
            display: block;
            margin-top: 2px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .toolbar { 
            display: flex; justify-content: flex-end; align-items: center;
            margin-bottom: 25px; background: rgba(25, 118, 210, 0.05); padding: 20px; border-radius: 20px;
        }

        .toolbar p {
            margin: 0;
            font-family: 'Cinzel', serif;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark-navy);
            letter-spacing: 0.5px;
        }
        
        .date-filter-box {
            display: flex;         
            align-items: center;   
            gap: 10px;           
        }

        .date-filter-box p {
            margin: 0;            
            white-space: nowrap;  
        }

        .date-select {
            appearance: none; 
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 35px 8px 15px;
            border-radius: 20px;
            font-family: 'PT Serif', serif;
            font-size: 0.85rem;
            color: #444;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23444' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }

        .date-select:hover {
            background-color: #fff;
            border-color: var(--primary-blue);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .date-select:focus {
            outline: none;
            border-color: var(--primary-blue);
        }

        .search-box { position: relative; display: flex; align-items: center;}
        .search-box input { padding: 10px 65px 10px 15px; width: 340px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.1); font-family: 'PT Serif'; }
        .search-icons { position: absolute; right: 15px; display: flex; gap: 10px; align-items: center; }
        .fa-times { color: #ccc; cursor: pointer; display: <?= !empty($search_query) ? 'block' : 'none' ?>; }
        .fa-search { color: #999; }
        .fa-times { 
            color: #999; 
            cursor: pointer; 
            transition: color 0.2s;
        }

        .fa-times:hover { 
            color: #c62828; 
        }
    </style>
</head>
<body>
<nav class="sidebar" id="sidebar">
        <div class="sidebar-toggle-arrow" onclick="toggleSidebar()">
            <i class="fas fa-chevron-left"></i>
        </div>

        <div class="brand" style="display: flex; align-items: center; gap: 10px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="30" height="28" style="flex-shrink: 0; width: 28px; height: 28px; display: inline-block; fill: currentColor; transform: translateY(-1px);">
                <path d="M22 15 C 32 15, 38 32, 42 45 C 45 42, 47 40, 50 40 C 53 40, 55 42, 58 45 C 62 32, 68 15, 78 15 C 72 30, 66 45, 62 50 C 62 68, 58 80, 50 80 C 42 80, 38 68, 38 50 C 34 45, 28 30, 22 15 Z M30 52 C 26 52, 22 48, 25 42 C 30 46, 34 48, 37 49 C 35 51, 32 52, 30 52 Z M70 52 C 68 52, 65 51, 63 49 C 66 48, 70 46, 75 42 C 78 48, 74 52, 70 52 Z"/>
            </svg>
            <span>RanchLink</span>
        </div>

        <div class="profile-section">
            <button class="profile-trigger" onclick="toggleProfileDropdown()">
                <div class="profile-img">
                    <img src="<?php echo $imagePath; ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                </div>
                <p class="welcome-text">Welcome,</p>
                <h4 class="farmer-name"><?php echo htmlspecialchars($name); ?></h4>
            </button>
            <div id="dropdownMenu" class="dropdown-menu">
                <a href="farmer_profile.php">My Profile</a>
                <hr style="border:0; border-top:1px solid #eee; margin: 10px 0;">
                <a href="../Models/logout.php" style="color: #e74c3c;">Logout</a>
            </div>
        </div>

        <ul class="nav-links">
            <li>
                <a href="farmer_dashboard.php">
                    <i class="fas fa-th-large"></i> <span>Dashboard</span>
                </a>
            </li>
            
            <li>
                <a onclick="toggleSubmenu(this)">
                    <i class="fa-solid fa-cow"></i> <span>Livestock Inventory</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="submenu">
                    <li><a href="view_livestock.php"><i class="fas fa-list"></i> View All Livestock</a></li>
                    <li><a href="add_livestock.php"><i class="fas fa-plus"></i> Add Livestock</a></li>
                    <li><a href="livestock_archive.php"><i class="fas fa-archive"></i> Livestock Archive</a></li>
                </ul>
            </li>
            
            <li>
                <a href="manage_order.php">
                    <i class="fas fa-shopping-basket"></i> <span>Customer Orders</span>
                </a>
            </li>
            
            <li>
                <a onclick="toggleSubmenu(this)">
                    <i class="fas fa-gavel"></i> <span>Livestock Auctions</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="submenu">
                    <li><a href="farmer_manage_auction.php"><i class="fas fa-gavel"></i> Manage Auctions</a></li>
                    <li><a href="create_auction.php"><i class="fas fa-plus-circle"></i> Start New Auction</a></li>
                </ul>
            </li>
            
            <li>
                <a href="manage_payments.php"class="active">
                    <i class="fas fa-receipt"></i> <span>Customer Payments</span>
                </a>
            </li>
            <li>
                <a href="manage_feedback.php">
                    <i class="fas fa-comment"></i> <span>Customer Feedbacks</span>
                </a>
            </li>
            <li>
                <a href="farmer_manage_reports.php">
                    <i class="fas fa-chart-bar"></i> <span>Sales & Report Business</span>
                </a>
            </li>
            <li>
                <a href="../Models/logout.php" style="margin-top: 30px; color: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <main class="main-content" id="mainContent">
        <header>
            <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
                <nav class="breadcrumb-wrapper" style="margin: 0; padding: 0;">
                    <ul class="breadcrumb" style="margin: 0; padding: 0; display: flex; list-style: none;">
                        <li><a href="farmer_dashboard.php"><i class="fas fa-home"></i> My Dashboard</a></li>
                        <li class="active">Customer Payments</li>
                    </ul>
                </nav>
            </div>
            <div class="top-actions">
                <span style="font-size: 14px; color: #888; margin-right: 15px;">
                    <i class="far fa-calendar"></i> <?= date('d M Y') ?>
                </span>

                <a href="notifications.php" class="icon-link">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

<div class="page-wrapper">

    <div class="glass-card">
        <div class="card-header-row">
           <a href="farmer_dashboard.php" class="back-btn">
            <i class="bi bi-arrow-left-circle-fill"></i> Back
        </a>
        <h2 class="main-title">Customer Payment</h2>
    </div>

        <div class="filter-tabs">
            <a href="?status=All" class="tab-link <?= (strtolower($filter) == 'all') ? 'active' : '' ?>">All Transactions</a>
            <a href="?status=paid" class="tab-link <?= (strtolower($filter) == 'paid') ? 'active' : '' ?>">Paid</a>
            <!-- <a href="?status=pending" class="tab-link <?= (strtolower($filter) == 'pending') ? 'active' : '' ?>">Pending</a> -->
            <a href="?status=refunded" class="tab-link <?= (strtolower($filter) == 'refunded') ? 'active' : '' ?>">Refunded</a>
            <a href="?status=terminated" class="tab-link <?= (strtolower($filter) == 'terminated') ? 'active' : '' ?>">Terminated</a>
            <!-- <a href="?status=unpaid" class="tab-link <?= (strtolower($filter) == 'unpaid') ? 'active' : '' ?>">Unpaid</a> -->
        </div>

        <div class="toolbar" style="display: flex; justify-content: flex-end; padding: 10px 0;">
            <form method="GET" action="manage_payments.php" id="searchForm" 
            style="display: flex; align-items: center; gap: 10px;">

            <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">

            <div class="date-filter-box">
                <p><i class="fas fa-filter" style="margin-right: 5px; font-size: 0.8rem;"></i> Sort by:</p>
                <select name="date_range" class="date-select" onchange="this.form.submit()" 
                style="border: 1px solid #ddd; border-radius: 20px; padding: 8px 15px; outline: none;">
                <option value="all" <?= $date_filter == 'all' ? 'selected' : '' ?>>All</option>
                <option value="today" <?= $date_filter == 'today' ? 'selected' : '' ?>>Today</option>
                <option value="2_days" <?= $date_filter == '2_days' ? 'selected' : '' ?>>Past 2 Days</option>
                <option value="7_days" <?= $date_filter == '7_days' ? 'selected' : '' ?>>Past 7 Days</option>
                <option value="30_days" <?= $date_filter == '30_days' ? 'selected' : '' ?>>Past 30 Days</option>
            </select>
        </div>

        <div class="search-box" style="position: relative; display: flex; align-items: center;">
            <input type="text" name="search" id="tableSearch" 
            placeholder="Search Customer name or Ref number..." 
            value="<?= htmlspecialchars($search) ?>" 
            oninput="toggleClearBtn()"
            style="border: 1px solid #ddd; border-radius: 20px; padding: 8px 40px 8px 15px; width: 300px; outline: none;">

            <div class="search-icons" style="position: absolute; right: 15px; display: flex; align-items: center; gap: 8px; color: #888;">
                <?php if(!empty($search)): ?>
                    <i class="fas fa-times" id="clearBtn" onclick="clearSearch()" style="cursor: pointer;"></i>
                <?php endif; ?>
                <i class="fas fa-search" style="cursor: default;"></i>
            </div>
        </div>
    </form>
</div>
        
        <?php if (isset($_GET['msg'])): ?>
            <div class="success-msg">
                <i class="fas fa-check-circle"></i> Transaction history updated: <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Date</th>
                    <th>Transaction ID</th>
                    <th>Ref Number</th>
                    <th>Customer</th>
                    <th>Livestock</th>
                    <th>Price & Method</th> 
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $idx = $offset + 1;
                if (count($payments) > 0): ?>
                    <?php foreach ($payments as $p): 
                        $is_paid = (strtolower($p['payment_status'] ?? '') === 'paid');
                        $is_already_refunded = (
                            strtolower($p['item_status'] ?? '') === 'refunded' || 
                            strtolower($p['order_status'] ?? '') === 'refunded' ||
                            strtolower($p['payment_status'] ?? '') === 'refunded'
                        );
                        $is_requesting = (strtolower($p['order_status'] ?? '') === 'refund requested');
                        ?>
                        <?php
                        $display_method = htmlspecialchars($p['payment_method'] ?? 'Stripe');

                        if (!empty($p['stripe_payment_id'])) {
                            try {
                                if (strpos($p['stripe_payment_id'], 'pi_') === 0) {
                                    $intent = $stripe->paymentIntents->retrieve($p['stripe_payment_id'], ['expand' => ['payment_method']]);
                                    $method_data = $intent->payment_method;
                                } elseif (strpos($p['stripe_payment_id'], 'ch_') === 0) {
                                    $charge = $stripe->charges->retrieve($p['stripe_payment_id'], ['expand' => ['payment_method']]);
                                    $method_data = $charge->payment_method;
                                }
                                
                                if (isset($intent->payment_method) && !is_string($intent->payment_method)) {
                                    $method = $intent->payment_method;
                                    if ($method->type === 'card') {
                                        $display_method = "Card (" . ucfirst($method->card->brand) . " ****" . $method->card->last4 . ")";
                                    } elseif ($method->type === 'fpx') {
                                        $bank = ucwords(str_replace('_', ' ', $method->fpx->bank));
                                        $display_method = "Online Banking ($bank)";
                                    }
                                }
                            } catch (Exception $e) {
                            }
                        }
                        ?>
                    <tr class="<?= $p['is_suspicious'] ? 'suspicious-row' : '' ?>">
                        <td style="font-weight: bold; color: #777;">
                            <?= $idx++ ?>.
                        </td>
                        <td style="vertical-align: middle; white-space: nowrap; min-width: 120px;">
                            <div style="display: flex; flex-direction: column; gap: 2px; font-family: 'PT Serif', serif; color: #777;">
                                
                                <div style="font-size: 0.7rem;">
                                    <?= date('d M Y', strtotime($p['trans_date'])) ?>
                                </div>

                                <div style="display: flex; align-items: center; gap: 5px; font-size: 0.75rem;">
                                    <i class="far fa-clock"></i>
                                    <span><?= date('h:i A', strtotime($p['trans_date'])) ?></span>
                                </div>

                            </div>
                        </td>
                        <td style="position: relative; min-width: 180px;">
                            <?php if (!empty($p['stripe_payment_id'])): ?>
                                <div style="display: flex; align-items: flex-start; gap: 8px;">
                                    <code style="word-break: break-all; font-family: monospace; font-size: 0.8rem; color: #333; line-height: 1.4;">
                                        <?= htmlspecialchars($p['stripe_payment_id']) ?>
                                    </code>
                                    <button type="button" 
                                    onclick="copyToClipboard('<?= $p['stripe_payment_id'] ?>', this)" 
                                    style="background: none; border: none; cursor: pointer; color: #1976d2; padding: 0; font-size: 0.9rem;" 
                                    title="Copy ID">
                                    <i class="far fa-copy"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <span style="color: #ccc; font-size: 0.8rem;">N/A</span>
                        <?php endif; ?>
                    </td>
                        <td>
                            <?php if ($p['payment_label'] === 'Deposit'): ?>
                                <strong style="color: #0d1b2a; font-size: 0.75rem;">
                                    <?= htmlspecialchars($p['order_id'] ?? 'N/A') ?>
                                </strong>
                            <?php else: ?>
                                <strong style="color: #0d1b2a; font-size: 0.75rem;">
                                    <?= formatOrderNumber($p['order_id']) ?>
                                </strong>
                            <?php endif; ?>
                            <?php if($p['is_suspicious']): ?>
                                <span class="suspicious-badge"><i class="fas fa-exclamation-triangle"></i> SUSPICIOUS</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: bold;"><?= htmlspecialchars($p['customer_name']) ?></td>
                        <td style="font-style: italic; vertical-align: middle;">
                            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; text-align: center;">
                                <?php 
                                $rawImg = !empty($p['livestock_image']) ? $p['livestock_image'] : "default_livestock.png";        
                                $imgArray = explode(',', $rawImg);
                                $firstImg = trim($imgArray[0]); 

                                $fullPath = "../farmer/uploads/" . $firstImg; 

                                if (!file_exists($fullPath) || empty($firstImg)) {
                                    $displayImg = "../farmer/uploads/default_livestock.png"; 
                                } else {
                                    $displayImg = $fullPath;
                                }
                                ?>
                                
                                <img src="<?= $displayImg ?>" 
                                alt="Livestock" 
                                style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; flex-shrink: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                                
                                <div>
                                    <span style="display: block; font-weight: 600; color: #333; font-size: 0.9rem; line-height: 1.2;">
                                        <?= htmlspecialchars($p['livestock_names']) ?>
                                    </span>
                                    <small style="color: #888; font-style: normal; display: block; margin-top: 2px;">
                                        (<?= $p['item_count'] ?> items)
                                    </small>
                                </div>
                            </div>
                        </td>

                        <td>
                            <strong style="color: #2d5a27;">RM <?= number_format($p['paid_amount'], 2) ?></strong>
                            <?php if ($p['payment_label'] === 'Deposit'): ?>
                                <span style="display: block; font-size: 0.7rem; color: #d35400; font-weight: bold; font-family: 'Cinzel';">
                                    <i class="fas fa-gavel"></i> (DEPOSIT)
                                </span>
                            <?php else: ?>
                                <span style="display: block; font-size: 0.65rem; color: #777; font-family: 'Cinzel';">
                                    FULL PAYMENT
                                </span>
                            <?php endif; ?>
                            <span class="method-tag">
                                <i class="fas <?= (strpos(strtolower($display_method), 'banking') !== false) ? 'fa-university' : 'fa-credit-card' ?>"></i> 
                                <?= $display_method ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $item_s = strtolower($p['item_status'] ?? '');
                            $pay_s = strtolower($p['payment_status'] ?? '');
                            $order_s = strtolower($p['order_status'] ?? ''); 

                            if ($order_s === 'refunded' || $item_s === 'refunded' || $pay_s === 'refunded') {
                                $display_status = 'Refunded';
                            } 
                            elseif ($p['is_suspicious'] || $order_s === 'suspended') {
                                $display_status = 'Suspended';
                            } 
                            elseif ($item_s === 'terminated' || $pay_s === 'failed') {
                                $display_status = 'Terminated';
                            } 
                            elseif ($pay_s === 'paid' || $item_s === 'paid' || $item_s === 'completed' || $item_s === 'processing') {
                                $display_status = 'Paid';
                            } else {
                                $display_status = 'Unpaid';
                            }

                            $status_slug = strtolower($display_status);
                            ?>
                            <div style="padding-bottom:10px;">
                                <span class="status-badge status-<?= $status_slug ?>">
                                    <?= $display_status ?>
                                </span>
                            </div>
                            <?php if ($is_already_refunded && !empty($p['refund_reason'])): ?>
                                <a href="view_refund_evidence.php?order_id=<?= $p['order_id'] ?>&from=payments" 
                                   class="action-btn" 
                                   style="background: #607d8b; color: white; padding: 5px 10px; border-radius: 5px; font-size: 11px; text-decoration: none;">
                                   <i class="fas fa-comment-dots"></i> View Reason
                               </a>
                           <?php endif; ?>
                       </td>
                       <td>
                        <div style="display:flex; gap: 5px; flex-wrap: wrap; align-items: center;">

                            <?php 
                            $is_deposit = ($p['payment_label'] === 'Deposit');

                            $is_refunded_status = (
                                strtolower($p['order_status'] ?? '') === 'refunded' || 
                                strtolower($p['item_status'] ?? '') === 'refunded' || 
                                strtolower($p['payment_status'] ?? '') === 'refunded' ||
                                strtolower($p['order_status'] ?? '') === 'deposit refunded'
                            );

                            if ($is_refunded_status) {
                                $receipt_url = "../farmer/generate_receipt_refund.php?order_id=" . urlencode($p['order_id']) . "&type=" . ($is_deposit ? 'deposit' : 'full');
                            } elseif ($is_deposit) {
                                $clean_payment_id = !empty($p['deposit_receipt_id']) ? $p['deposit_receipt_id'] : $p['payment_id'];
                                $receipt_url = "../Models/generate_receipt_deposit.php?payment_id=" . urlencode($clean_payment_id) . "&auction_id=" . urlencode($p['auction_id'] ?? '') . "&customer_id=" . urlencode($p['customer_id']);
                            } else {
                                $receipt_url = "../payment/download_receipt.php?order_id=" . urlencode($p['order_id']);
                            }
                            ?>

                            <?php if ($is_refunded_status): ?>
                                <a href="<?= $receipt_url ?>" class="action-btn btn-receipt-refund" title="View Refund Receipt" target="_blank">
                                    <i class="fas fa-file-invoice" style="color: #fff;"></i> Refund Receipt
                                </a>
                            <?php else: ?>
                                <a href="<?= $receipt_url ?>" 
                                 class="action-btn <?= $is_deposit ? 'btn-receipt-deposit' : 'btn-receipt' ?>" 
                                 title="View Receipt" 
                                 target="_blank">
                                 <i class="<?= $is_deposit ? 'fas fa-gavel' : 'fas fa-file-invoice-dollar' ?>"></i> 
                                 <?= $is_deposit ? 'Deposit Receipt' : 'Receipt' ?>
                             </a>
                         <?php endif; ?>

                         <?php if ($p['order_status'] === 'Refund Requested'): ?>
                            <div style="display: flex; gap: 5px; align-items: center; background: #fff8e1; padding: 5px; border-radius: 8px; border: 1px solid #ffe082;">
                                <a href="view_refund_evidence.php?order_id=<?= urlencode($p['order_id']) ?>" class="action-btn" style="background: #1976d2; color: white; padding: 5px 10px; border-radius: 5px; font-size: 11px; text-decoration: none;">
                                    <i class="fas fa-eye"></i> Evidence
                                </a>

                                <form action="process_refund_decision.php" method="POST" style="display:inline;" onsubmit="return confirm('Approve this refund?');">
                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($p['order_id']) ?>">
                                    <input type="hidden" name="payment_label" value="<?= htmlspecialchars($p['payment_label']) ?>">
                                    <input type="hidden" name="from_page" value="payments">
                                    <input type="hidden" name="decision" value="Approved">
                                    <button type="submit" class="action-btn" style="background: #2e7d32; color: white; border: none; padding: 5px 10px; border-radius: 5px; font-size: 11px; cursor: pointer;">Approve</button>
                                </form>

                                <form action="process_refund_decision.php" method="POST" style="display:inline;" id="rejectForm_<?= htmlspecialchars($p['order_id']) ?>">
                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($p['order_id']) ?>">
                                    <input type="hidden" name="payment_label" value="<?= htmlspecialchars($p['payment_label']) ?>">
                                    <input type="hidden" name="from_page" value="payments">
                                    <input type="hidden" name="decision" value="Rejected">
                                    <input type="hidden" name="rejection_reason" id="reason_input_<?= htmlspecialchars($p['order_id']) ?>">
                                    <button type="button" class="action-btn" style="background: #c62828; color: white; border: none; padding: 5px 10px; border-radius: 5px; font-size: 11px; cursor: pointer;" onclick="handleReject('<?= htmlspecialchars($p['order_id']) ?>')">Reject</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $is_already_refunded = (
                            strtolower($p['item_status'] ?? '') === 'refunded' || 
                            strtolower($p['payment_status'] ?? '') === 'refunded' || 
                            strtolower($p['order_status'] ?? '') === 'refunded' ||
                            strtolower($p['order_status'] ?? '') === 'deposit refunded'
                        );

                        $is_paid = (isset($p['payment_status']) && strtolower($p['payment_status']) === 'paid') || 
                        (in_array(strtolower($p['item_status'] ?? ''), ['paid', 'processing', 'ready for pickup', 'delivered']));

                        $is_requesting = (($p['order_status'] ?? '') === 'Refund Requested');

                        if ($is_paid && !$is_already_refunded && !$is_requesting): 
                            ?>
                            <form method="POST" style="display:contents;">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($p['order_id']) ?>">
                                <input type="hidden" name="payment_label" value="<?= htmlspecialchars($p['payment_label']) ?>">
                                <button type="submit" name="action" value="refund" class="action-btn btn-refund" 
                                onclick="return confirm('Refund for <?= $p['payment_label'] === 'Deposit' ? 'Deposit' : 'Order' ?> #<?= $p['payment_label'] === 'Deposit' ? htmlspecialchars($p['order_id'] ?? 'N/A') : formatOrderNumber($p['order_id']) ?>?')">
                                <i class="fas fa-undo"></i> Refund
                            </button>
                        </form>
                    <?php endif; ?>

                </div>
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="7" style="text-align:center; padding: 40px; color: #999;">No payment records found.</td></tr>
<?php endif; ?>
</tbody>
</table>
    </div>
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= $base_url ?>&page=<?= $page - 1 ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php 
                $start_loop = max(1, $page - 2);
                $end_loop = min($total_pages, $page + 2);

                for ($i = $start_loop; $i <= $end_loop; $i++): ?>
                    <a href="<?= $base_url ?>&page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= $base_url ?>&page=<?= $page + 1 ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>

            <div style="text-align: center; margin-top: 10px; font-family: 'Cinzel', serif; font-size: 0.7rem; color: #888;">
                Page <?= $page ?> of <?= $total_pages ?> (<?= $total_rows ?> total records)
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('closed');
            mainContent.classList.toggle('expanded');
            
            if(window.innerWidth <= 768) {
                sidebar.classList.toggle('open');
            }
        }

        function toggleSubmenu(element) {
            const parentLi = element.parentElement;
            
            if(document.getElementById('sidebar').classList.contains('closed')) {
                toggleSidebar();
                return;
            }
            
            parentLi.classList.toggle('open');
        }

        function toggleProfileDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        window.onclick = function(event) {
            if (!event.target.closest('.profile-trigger')) {
                const dropdown = document.getElementById('dropdownMenu');
                if (dropdown) dropdown.style.display = 'none';
            }
        }
        function toggleClearBtn() {
            const searchInput = document.getElementById('tableSearch');
            const clearBtn = document.getElementById('clearBtn');
            clearBtn.style.display = searchInput.value.length > 0 ? 'block' : 'none';
        }

        function clearSearch() {
            const searchInput = document.getElementById('tableSearch');
            if (searchInput) {
                searchInput.value = ''; 
            }

            const form = document.getElementById('searchForm');
            if (form) {
                form.submit();
            } else {
                const url = new URL(window.location.href);
                url.searchParams.delete('search');
                window.location.href = url.toString();
            }
        }

        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                const icon = btn.querySelector('i');
                icon.classList.replace('fa-copy', 'fa-check');
        btn.style.color = '#2e7d32'; 
        
        setTimeout(() => {
            icon.classList.replace('fa-check', 'fa-copy');
            btn.style.color = '#1976d2';
        }, 2000);
    });
        }

        function handleReject(orderId) {
            let reason = prompt("Please provide a reason for rejecting this refund:");

            if (reason === null) {
                return; 
            }

            if (reason.trim() === "") {
                alert("You must provide a reason to reject the refund.");
                return;
            }

            document.getElementById('reason_input_' + orderId).value = reason;
            document.getElementById('rejectForm_' + orderId).submit();
        }
    </script>
</body>
</html>