<?php
session_start();
include '../db_connect.php';
include '../inc/numbers.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: ../Models/customer_login.php");
    exit();
}
include '../inc/header.php'; 

$customer_id = $_SESSION['customer_id'];

$filter_status = $_GET['status'] ?? 'All';
$date_range = $_GET['date_range'] ?? 'Today';

try {
    $sql = "SELECT 
    o.order_id, o.order_date, o.rejection_reason,
    oi.order_item_id, oi.item_status, o.order_status,
    oi.price_at_purchase as item_price, 
    p.stripe_payment_id,
    p.amount as amount_paid,
    l.livestock_id, l.name as animal_name, l.image, l.breed, l.age, l.weight, l.gender,
    f.farm_name, d.recipient_name, d.phone_number, d.deliveryaddress, d.city, d.postcode, d.state, d.deliveryfee
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.order_id 
    JOIN livestock l ON oi.livestock_id = l.livestock_id
    JOIN farmer f ON l.farmer_id = f.farmer_id
    LEFT JOIN payments p ON o.order_id = p.order_id 
    LEFT JOIN delivery d ON o.order_id = d.order_id 
    WHERE o.customer_id = :cid";

    $params = [':cid' => $customer_id];

    if ($filter_status !== 'All') {
        $sql .= " AND oi.item_status = :status"; 
        $params[':status'] = $filter_status;
    }

    switch ($date_range) {
        case 'Today':
            $sql .= " AND o.order_date >= CURRENT_DATE";
            break;
        case 'Yesterday':
            $sql .= " AND o.order_date >= CURRENT_DATE - INTERVAL '1 day' AND o.order_date < CURRENT_DATE";
            break;
        case 'Week':
            $sql .= " AND o.order_date >= CURRENT_DATE - INTERVAL '7 days'";
            break;
        case 'Month':
            $sql .= " AND o.order_date >= CURRENT_DATE - INTERVAL '30 days'";
            break;
        case 'Older':
            $sql .= " AND o.order_date < CURRENT_DATE - INTERVAL '30 days'";
            break;
        case 'All':
        default:
            break;
    }

    $sql .= " ORDER BY o.order_date DESC, oi.order_item_id ASC";

    $stmt = $pdo->prepare($sql);
    
    $stmt->execute($params); 
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching orders: " . $e->getMessage());
}

try {
    $countSql = "SELECT oi.item_status, COUNT(*) as total 
                 FROM order_items oi 
                 JOIN orders o ON oi.order_id = o.order_id 
                 WHERE o.customer_id = :cid 
                 GROUP BY oi.item_status";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':cid' => $customer_id]);
    $statusCountsRaw = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR); // Returns [Status => Count]

    $totalOrders = array_sum($statusCountsRaw);
    
    $getCount = function($status) use ($statusCountsRaw) {
        return $statusCountsRaw[$status] ?? 0;
    };
} catch (PDOException $e) {
    $statusCountsRaw = [];
    $totalOrders = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&family=Raleway:wght@600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6);
            font-family: 'PT Serif', serif; color: #1a1a1a; min-height: 100vh;
        }

        .breadcrumb {
            max-width: 1100px;
            margin: 20px auto 0;
            padding: 0;
            font-size: 0.9rem;
            color: #666;
        }
        .breadcrumb a { color: #1976d2; text-decoration: none; }
        .breadcrumb span { margin: 0 8px; color: #ccc; }

        .hero-section, 
        .orders-container, 
        .filter-wrapper, 
        .date-filter-container, 
        .breadcrumb {
            max-width: 1100px; 
            margin-left: auto;
            margin-right: auto;
            width: 100%;
        }

        .hero-section { 
            height: 80px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-top: 20px;
            margin-bottom: 25px; 
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
            font-family: 'Cinzel', serif;;
        }
        
        .orders-container { max-width: 1100px; margin: 0 auto 60px; padding: 0; }

        .dropdown-container {
            max-width: 950px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: flex-end;
            padding: 0 35px;
        }
        
        .date-dropdown {
            padding: 8px 16px;
            font-family: 'Cinzel', serif;
            font-size: 0.8rem;
            color: #0d1b2a;
            background-color: white;
            border: 2px solid #90caf9;
            border-radius: 20px;
            cursor: pointer;
            outline: none;
            transition: 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .date-dropdown:hover, .date-dropdown:focus {
            border-color: #0d1b2a;
            box-shadow: 0 4px 10px rgba(144, 202, 249, 0.2);
        }
        
        .filter-nav {
            display: flex;
            justify-content: space-between;
            max-width: 950px;
            margin: 0 auto 40px;
            padding: 20px 0;
            overflow-x: auto;
            gap: 10px;
        }
        .filter-item {
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            color: #888;
            transition: 0.3s;
            flex: 1;
            min-width: 80px;
            position: relative; 
        }
        .icon-circle {
            display: flex;
            flex-direction: column; 
            align-items: center;
            justify-content: center;
            gap: 2px; 
            width: 60px;  
            height: 60px;
            border-radius: 50%;
            background: white;
            transition: all 0.3s ease;
            border: 2px solid #eee;
            position: relative; 
        }

        .icon-circle i {
            font-size: 1.2rem; 
            margin-top: 2px;
        }

        .count-text {
            font-family: 'Cinzel', serif; 
            font-size: 0.75rem;
            font-weight: 800;
            color: #1976d2; 
        }

        .filter-item.active .count-text {
            color: white; 
        }

        .filter-badge { display: none; }
        .status-count {
            display: inline-block;
            margin-left: 4px;
            font-family: 'Raleway', sans-serif;
            color: #888; 
            font-size: 0.6rem;
            font-weight: 400; 
        }

        .filter-item span {
            font-family: 'Cinzel', sans-serif;
            font-size: 0.6rem;          
            font-weight: 700;           
            text-transform: uppercase;  
            letter-spacing: 0.5px;        
            display: block;
            margin-top: 10px;           
            text-align: center;
            transition: color 0.3s ease;
            color: #888;                
            max-width: 85px;            
            line-height: 1.2;
            white-space: normal;        
        }

        .filter-item.active span {
            color: #0d1b2a;             
            font-weight: 800;           
        }

        .filter-item:hover span {
            color: #1976d2;             
        }
        .filter-item.active .icon-circle {
            background: #0d1b2a;
            color: white;
            transform: translateY(-5px);
            border-color: #0d1b2a;
        }
        .filter-item.active span { color: #0d1b2a; }
        .filter-item:hover .icon-circle { transform: translateY(-3px); box-shadow: 0 6px 15px rgba(144, 202, 249, 0.2); }

        .order-card { 
            position: relative; 
            display: flex; 
            flex-direction: column;
            background: rgba(255, 255, 255, 0.7); 
            backdrop-filter: blur(14px);
            border-radius: 22px; 
            border: 1px solid rgba(144, 202, 249, 0.4);
            margin-bottom: 25px; 
            padding: 25px; 
            transition: 0.35s;
        }

        .status-badge { 
            position: absolute;
            top: 20px;
            right: 25px;
            padding: 6px 20px; 
            border-radius: 20px; 
            font-size: 11px; 
            text-transform: uppercase; 
            font-weight: bold; 
        }

        .card-main-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .actions-group { 
            display: flex; 
            flex-direction: row !important; 
            justify-content: flex-end;     
            align-items: center;           
            gap: 10px;                     
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.05);
            flex-wrap: nowrap;             
        }

        .actions-group a, .actions-group button {
            width: auto; 
            min-width: 120px;
            margin: 0;
        }
        .btn-receipt, .btn-track, .btn-refund, .btn-cancel {
            width: fit-content; 
            min-width: 120px;  
            white-space: nowrap; 
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .actions-group form {
            display: inline-block;  
        }
        .order-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(144,202,249,0.3); }
        .order-info { display: flex; align-items: center; gap: 20px; }
        .order-info img { width: 100px; height: 100px; object-fit: cover; border-radius: 15px; border: 1px solid rgba(0,0,0,0.1); }
        .animal-title { font-family: 'Cinzel', serif; font-size: 1.2rem; color: #0d1b2a; display: block; }
        .shipping-details { font-size: 0.85rem; color: #555; background: rgba(255,255,255,0.5); padding: 10px 15px; border-radius: 12px; border-left: 4px solid #90caf9; line-height: 1.6; }
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 11px; text-transform: uppercase; font-weight: bold; }
        .status-paid { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-processing { background: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb; }
        .status-ready { background: #fff3e0; color: #ef6c00; border: 1px solid #ffe0b2; }
        .status-delivered { background: #f3e5f5; color: #7b1fa2; border: 1px solid #e1bee7; }
        .status-pending { background: #fff9c4; color: #fbc02d; border: 1px solid #fff176; }
        .status-cancelled { 
            background: #ffebee; 
            color: #c62828; 
            border: 1px solid #ffcdd2; 
        }

        .status-refunded { 
            background: #f5f5f5; 
            color: #616161; 
            border: 1px solid #e0e0e0; 
        }
        .price-tag { font-family: 'Cinzel', serif; color: #1976d2; font-size: 1.2rem; font-weight: bold; }
        .actions-group { display: flex; flex-direction: column; gap: 8px; }
        .filter-wrapper {
            position: relative;
            max-width: 950px;
            margin: 0 auto 10px;
            display: flex;
            align-items: flex-start; 
            padding: 0 35px; 
        }

        .filter-nav::-webkit-scrollbar {
            display: none;
        }

        .filter-nav {
            display: flex;
            justify-content: flex-start; 
            width: 100%;
            gap: 15px;
            padding-top: 15px; 
            overflow-x: auto;
        }

        .scroll-btn {
            background: white;
            border: 2px solid #90caf9;
            color: #1976d2;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            position: absolute;
            z-index: 10;    
            top: 50px; 
            transform: translateY(-50%); 
            transition: 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .scroll-btn:hover {
            background: #1976d2;
            color: white;
        }

        .btn-left { left: 0px; }
        .btn-right { right: 0px; }
        .date-filter-container { 
            max-width: 950px; 
            margin: 0 auto 30px; 
            padding: 15px; 
            background: #fff; 
            border-radius: 15px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            border: 1px solid #eee;
        }

        .btn-receipt { text-align: center; text-decoration: none; color: #0d1b2a; background: linear-gradient(135deg, #90caf9, #64b5f6); padding: 8px 15px; font-size: 13px; font-weight: bold; border-radius: 25px; transition: 0.3s; }
        .btn-receipt:hover { background: #0d1b2a; color: #fff; }
        .btn-track { text-align: center; text-decoration: none; color: #1976d2; background: white; padding: 7px 15px; font-size: 13px; font-weight: bold; border-radius: 25px; border: 2px solid #90caf9; transition: 0.3s; }
        .btn-cancel { 
            background: transparent; 
            border: 2px solid #e57373; 
            color: #e57373; 
            padding: 7px 15px; 
            font-size: 13px; 
            font-weight: bold; 
            border-radius: 25px; 
            cursor: pointer; 
            font-family: 'Raleway', sans-serif; 
            transition: 0.3s;
            margin-top: 5px;
        }

        .btn-cancel:hover { 
            background: #e57373; 
            color: white; 
            box-shadow: 0 4px 12px rgba(229, 115, 115, 0.2);
        }
        .btn-refund { 
            text-align: center; 
            text-decoration: none; 
            color: #f44336; 
            background: #fff; 
            padding: 7px 15px; 
            font-size: 13px; 
            font-weight: bold; 
            border-radius: 25px; 
            border: 2px solid #f44336; 
            transition: 0.3s; 
        }
        .btn-refund:hover { background: #f44336; color: #fff; }
        .item-link { text-decoration: none; color: inherit; display: block; cursor: pointer; }
        .rejection-box {
            margin-top: 10px;
            padding: 10px;
            background-color: #fff5f5;
            border: 1px solid #feb2b2;
            border-left: 4px solid #f56565;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #c53030;
            text-align: left;
        }
        .rejection-box strong {
            display: block;
            color: #9b2c2c;
            margin-bottom: 3px;
            text-transform: uppercase;
            font-size: 0.7rem;
        }
        .status-pending { 
            background: #fff3e0; 
            color: #ef6c00; 
            border: 1px solid #ffe0b2; 
        }
    </style>
</head>
<body>

    <div class="hero-section">
        <h1>My Orders</h1>
    </div>
    <div class="breadcrumb">
        <a href="customer_dashboard.php"><i class="fas fa-home"></i> Marketplace</a> <span>&gt;</span> My Orders
    </div><br>
    <div class="orders-container">

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success" style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i>
                <strong>
                    <?php 
                    if ($_GET['msg'] === 'cancellation_requested') {
                        echo "Cancel Order Requested Successfully !";
                    } else {
                        echo htmlspecialchars($_GET['msg']);
                    }
                    ?>
                </strong>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <div class="dropdown-container">
            <form method="GET" id="dateFilterForm">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <select name="date_range" class="date-dropdown" onchange="document.getElementById('dateFilterForm').submit();">
                    <option value="Today" <?= $date_range == 'Today' ? 'selected' : '' ?>>Today</option>
                    <option value="Yesterday" <?= $date_range == 'Yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="Week" <?= $date_range == 'Week' ? 'selected' : '' ?>>Past 7 Days</option>
                    <option value="Month" <?= $date_range == 'Month' ? 'selected' : '' ?>>Past 30 Days</option>
                    <option value="Older" <?= $date_range == 'Older' ? 'selected' : '' ?>>Older than 30 Days</option>
                    <option value="All" <?= $date_range == 'All' ? 'selected' : '' ?>>All</option>
                </select>
            </form>
        </div>

        <div class="filter-wrapper">
            <button class="scroll-btn btn-left" onclick="scrollFilters(-200)">
                <i class="fas fa-chevron-left"></i>
            </button>

            <div class="filter-nav" id="filterNav">
                <a href="?status=All" class="filter-item <?= $filter_status == 'All' ? 'active' : '' ?>">
                    <div class="icon-circle">
                        <i class="fas fa-list-ul"></i>
                        <?php if($totalOrders > 0): ?>
                            <span class="count-text"><?= $totalOrders ?></span>
                        <?php endif; ?>
                    </div>
                    <span>All</span>
                </a>

            <a href="?status=Preparing&date_range=<?= urlencode($date_range) ?>" class="filter-item <?= $filter_status == 'Preparing' ? 'active' : '' ?>">
                <div class="icon-circle"><i class="fas fa-sync"></i>
                    <span class="count-text"><?= $statusCountsRaw['Preparing'] ?? 0 ?></span>
                </div>
                <span>Preparing </span>
            </a>

            <!-- <a href="?status=Health Inspection&date_range=<?= urlencode($date_range) ?>" class="filter-item <?= $filter_status == 'Health Inspection' ? 'active' : '' ?>">
                <div class="icon-circle"><i class="fas fa-file-medical-alt"></i>
                    <span class="count-text"><?= $statusCountsRaw['Health Inspection'] ?? 0 ?></span>
                </div>
                <span>Health Inspection</span>
            </a> -->

             <a href="?status=Ready for Pickup&date_range=<?= urlencode($date_range) ?>" class="filter-item <?= $filter_status == 'Ready for Pickup' ? 'active' : '' ?>">
                <div class="icon-circle"><i class="fas fa-store"></i>
                    <span class="count-text"><?= $statusCountsRaw['Ready for Pickup'] ?? 0 ?></span>
                </div>
                <span>Ready for Pickup</span>
            </a>

            <a href="?status=In Transit&date_range=<?= urlencode($date_range) ?>" class="filter-item <?= $filter_status == 'In Transit' ? 'active' : '' ?>">
                <div class="icon-circle"><i class="fas fa-truck-moving"></i>
                    <span class="count-text"><?= $statusCountsRaw['In Transit'] ?? 0 ?></span>
                </div>
                <span>In Transit</span>
            </a>

            <!-- <a href="?status=Arrived at Transit Hub" class="filter-item <?= $filter_status == 'Arrived at Transit Hub' ? 'active' : '' ?>">
                <div class="icon-circle"><i class="fas fa-sync"></i>
                    <span class="count-text"><?= $statusCountsRaw['Arrived at Transit Hub'] ?? 0 ?></span>
                </div>
                <span>Arrived at Transit Hub </span>
            </a> -->
            <a href="?status=Out for Delivery&date_range=<?= urlencode($date_range) ?>" class="filter-item <?= $filter_status == 'Out for Delivery' ? 'active' : '' ?>">
                <div class="icon-circle"><i class="fas fa-shipping-fast"></i>
                    <span class="count-text"><?= $statusCountsRaw['Out for Delivery'] ?? 0 ?></span>
                </div>
                <span>Out for Delivery</span>
            </a>

            <a href="?status=Delivered&date_range=<?= urlencode($date_range) ?>" class="filter-item <?= $filter_status == 'Delivered' ? 'active' : '' ?>">
                <div class="icon-circle"><i class="fas fa-truck"></i>
                    <span class="count-text"><?= $statusCountsRaw['Delivered'] ?? 0 ?></span>
                </div>
                <span>Delivered</span>
            </a>

            <a href="?status=Cancelled Order&date_range=<?= urlencode($date_range) ?>" class="filter-item <?= $filter_status == 'Cancelled Order' ? 'active' : '' ?>">
                <div class="icon-circle"><i class="fas fa-hourglass-half"></i>
                    <span class="count-text"><?= $statusCountsRaw['Cancelled Order'] ?? 0 ?></span>
                </div>
                <span>Cancelled Order</span>
            </a>

            <a href="?status=Terminated&date_range=<?= urlencode($date_range) ?>" class="filter-item <?= $filter_status == 'Terminated' ? 'active' : '' ?>">
                <div class="icon-circle"><i class="fas fa-times"></i>
                    <span class="count-text"><?= $statusCountsRaw['Terminated'] ?? 0 ?></span>
                </div>
                <span>Terminated</span>
            </a>

            <a href="?status=Refunded&date_range=<?= urlencode($date_range) ?>" class="filter-item <?= $filter_status == 'Refunded' ? 'active' : '' ?>">
                <div class="icon-circle"><i class="fas fa-exchange-alt"></i>
                    <span class="count-text"><?= $statusCountsRaw['Refunded'] ?? 0 ?></span>
                </div>
                <span>Refunded</span>
            </a>
        </div>
        <button class="scroll-btn btn-right" onclick="scrollFilters(200)">
        <i class="fas fa-chevron-right"></i>
    </button>
</div>

<!-- <div class="date-filter-container">
        <form method="GET" style="display: flex; align-items: center; justify-content: center; gap: 15px; flex-wrap: wrap;">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">

            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 0.8rem; font-weight: bold; color: #555;">From:</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" 
                style="padding: 5px 10px; border-radius: 8px; border: 1px solid #ddd; font-family: 'Raleway'; font-size: 0.85rem;">
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 0.8rem; font-weight: bold; color: #555;">To:</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" 
                style="padding: 5px 10px; border-radius: 8px; border: 1px solid #ddd; font-family: 'Raleway'; font-size: 0.85rem;">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" style="background: #1976d2; color: white; border: none; padding: 6px 18px; border-radius: 20px; font-size: 0.8rem; cursor: pointer; font-weight: bold; transition: 0.3s;">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <?php if(!empty($start_date) || !empty($end_date)): ?>
                <a href="?status=<?= urlencode($filter_status) ?>" style="text-decoration: none; background: #f5f5f5; color: #666; padding: 6px 18px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; border: 1px solid #ddd;">
                    Clear
                </a>
            <?php endif; ?>
        </div>
    </form>
</div> -->


    <?php 
    $count = 1;
    if ($orders): foreach ($orders as $order): ?>
        <div class="order-card">
    <!-- 1. The Index Number (Circle) -->
    <div style="position: absolute; top: -10px; left: -10px; background: #0d1b2a; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: bold; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 10;">
        <?= $count++ ?>
    </div>

    <?php 
    $current_status = $order['item_status'] ?? 'Paid';
    $status_class = match($current_status) {
        'Paid' => 'status-paid',
        'Processing' => 'status-processing',
        'Ready for Pickup' => 'status-ready',
        'Delivered' => 'status-delivered',
        'Cancelled Order', 'Cancelled', 'Terminated' => 'status-cancelled', 
        'Refunded' => 'status-refunded',
        default => 'status-paid',
    };

    $display_status = match($current_status) {
        'Cancelled' => 'Terminated',
        'Cancelled Order' => 'Cancelled Order',
        'Terminated' => 'Terminated',
        default => $current_status,
    };
    ?>
    <span class="status-badge <?= $status_class ?>">
        <?= htmlspecialchars($display_status) ?>
    </span>

    <div class="card-main-content">
            <div class="order-info">
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
                <img src="<?= htmlspecialchars($imgPath) ?>" alt="Livestock">
                <div>
                    <strong class="animal-title"><?= htmlspecialchars($order['animal_name'] ?? 'Unknown Livestock') ?></strong>
                    <div style="background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 4px; display: inline-block; font-size: 0.75rem; font-weight: bold; margin: 4px 0;">
                        <i class="fas fa-tractor"></i> <?= htmlspecialchars($order['farm_name']) ?>
                    </div>

                    <div style="font-size: 0.8rem; color: #555; margin-top: 5px;">
                    </div>
                    
                    <div style="font-size: 0.8rem; color: #555; margin-top: 5px;">
                        <div style="display: flex; gap: 15px; margin-bottom: 5px;">
                            <span><i class="fas fa-tag"></i> <strong>Breed:</strong> <?= htmlspecialchars($order['breed']) ?></span>
                            <span><i class="fas fa-venus-mars"></i> <strong>Gender:</strong> <?= htmlspecialchars($order['gender']) ?></span>
                        </div>

                        <div style="display: flex; gap: 15px;">
                            <span><i class="fas fa-birthday-cake"></i> <strong>Age:</strong> <?= htmlspecialchars($order['age']) ?> month</span>
                            <span><i class="fas fa-weight"></i> <strong>Weight:</strong> <?= htmlspecialchars($order['weight']) ?> kg</span>
                        </div>
                    </div>

                    <span class="order-meta" style="display: block; margin-top: 8px; font-size: 0.75rem; color: #777;"> 
                        <i class="far fa-calendar-alt"></i> <strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($order['order_date'])) ?>
                    </span>
                    <span class="order-meta" style="display: block; margin-top: 2px; font-size: 0.75rem; color: #777; font-weight: bold;"> Order number: <?= formatOrderNumber($order['order_id']) ?></span>
                </div>
            </div>

        <div style="text-align: right; padding-right: 20px; padding-top:30px;">
            <?php $finalPaidAmount = (!empty($order['amount_paid'])) ? (float)$order['amount_paid'] : (float)$order['item_price']; ?>
            <span class="price-tag" style="font-size: 1.5rem; display: block;">RM <?= number_format($finalPaidAmount, 2) ?></span>
            <small style="color: #888; font-weight: bold;">Total Paid</small>
        </div>
    </div>

    <div class="actions-group">
        <?php if (!empty($order['stripe_payment_id'])): ?>
            <?php 
            $receipt_url = ($current_status === 'Refunded') 
                ? "../farmer/download_refund_pdf.php?order_id=" . $order['order_id']
                : "../payment/download_receipt.php?order_id=" . $order['order_id'];
            ?>
            <a href="<?= $receipt_url ?>" class="btn-receipt">
                <i class="fas fa-file-invoice"></i> View Receipt
            </a>
        <?php endif; ?>

        <?php if ($current_status !== 'Terminated' && $current_status !== 'Cancelled' && $current_status !== 'Refunded'): ?>
            <a href="order_tracking.php?order_id=<?= $order['order_id'] ?>" class="btn-track">
                <i class="fas fa-truck"></i> Track Order
            </a>
        <?php endif; ?>

        <?php if ($order['item_status'] == 'Delivered'): ?>
            <a href="request_refund.php?order_id=<?= $order['order_id'] ?>" class="btn-refund">
                <i class="fas fa-undo"></i> Request Refund
            </a>
        <?php endif; ?>

        <?php 
        $allowed_to_cancel = ['Paid', 'Preparing', 'Pending', 'Processing']; 
        if (in_array($order['item_status'], $allowed_to_cancel)): 
        ?>
            <form action="cancel_order.php" method="POST" onsubmit="return confirm('Request cancellation?');">
                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                <?php if ($order['item_status'] !== 'Cancelled Order'): ?>
                    <button type="submit" class="btn-cancel">Cancel Order</button>
                <?php else: ?>
                    <span class="status-badge status-cancelled" style="position:static;">Cancelled Order</span>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; else: ?>
<div style="text-align: center; padding: 80px;">
    <p style="color: #666;">No orders found in this category.</p>
</div>
<?php endif; ?>
</div>

<script>
function scrollFilters(distance) {
    const nav = document.getElementById('filterNav');
    nav.scrollBy({
        left: distance,
        behavior: 'smooth'
    });
}

window.addEventListener('load', () => {
    const activeItem = document.querySelector('.filter-item.active');
    if (activeItem) {
        activeItem.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
});
</script>

</body>
</html>