<?php
session_start();
require_once '../db_connect.php';
include '../inc/header.php';
include '../inc/numbers.php';

if (!isset($_SESSION['customer_id'])) { 
    header("Location: customer_login.php"); 
    exit(); 
}

$uid = $_SESSION['customer_id']; 
$type = 'customer'; 
$dashboard_link = "customer_dashboard.php";

$filter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'today';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$order = ($sort === 'oldest') ? 'ASC' : 'DESC';

$where_clause = "user_id = :uid AND user_type = :type";
$params = ['uid' => $uid, 'type' => $type];

switch ($filter) {
    case 'today':
        $where_clause .= " AND created_at >= CURRENT_DATE";
        break;
    case '2_days':
        $where_clause .= " AND created_at >= NOW() - INTERVAL '2 days'";
        break;
    case '7_days':
        $where_clause .= " AND created_at >= NOW() - INTERVAL '7 days'";
        break;
    case 'older':
        $where_clause .= " AND created_at < NOW() - INTERVAL '7 days'";
        break;
    case 'all':
    default:
        break;
}

if (isset($_POST['clear_all'])) {
    $clearStmt = $pdo->prepare("DELETE FROM notifications WHERE $where_clause");
    $clearStmt->execute($params);
    header("Location: notifications.php?time_filter=" . urlencode($filter));
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE $where_clause ORDER BY created_at $order");
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = :uid AND user_type = :type")
    ->execute(['uid' => $uid, 'type' => $type]);

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = ['y' => 'year','m' => 'month','w' => 'week','d' => 'day','h' => 'hour','i' => 'minute','s' => 'second'];
    foreach ($string as $k => &$v) {
        if ($diff->$k) { $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : ''); } 
        else { unset($string[$k]); }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        body { 
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6); 
            font-family: 'PT Serif', serif; 
            color: #1a1a1a; 
        }

        .breadcrumb-area {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px 20px;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .breadcrumb-links a { text-decoration: none; color: #1976d2; }
        .breadcrumb-links span { color: #888; margin: 0 10px; }

        .btn-clear {
            background: transparent;
            border: 1px solid #d32f2f;
            color: #d32f2f;
            padding: 5px 12px;
            border-radius: 20px;
            font-family: 'Cinzel', serif;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-clear:hover { background: #d32f2f; color: #fff; }

        .notif-container { 
            max-width: 1000px; 
            margin: 0 auto 60px; 
            padding: 20px 20px; 
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-left: 5px solid #90caf9;
            padding-left: 15px;
        }

        .section-title { 
            font-family: 'Cinzel', serif; 
            font-size: 28px; 
            margin: 0;
        }

        .notif-card { 
            background: rgba(255, 255, 255, 0.7); 
            backdrop-filter: blur(14px); 
            border-radius: 15px; 
            padding: 20px; 
            border: 1px solid rgba(144, 202, 249, 0.3); 
            margin-bottom: 15px; 
            transition: 0.3s;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .notif-card.unread {
            border-left: 5px solid #1976d2;
            background: rgba(255, 255, 255, 0.9);
        }

        .notif-icon {
            background: #e3f2fd;
            color: #1976d2;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .notif-content { flex: 1; }
        .notif-title { font-family: 'Cinzel', serif; font-weight: bold; font-size: 16px; color: #0d1b2a; margin-bottom: 5px; }
        .notif-message { font-size: 15px; color: #453c34; line-height: 1.5; margin-bottom: 8px; }
        .notif-time { font-size: 12px; color: #888; display: flex; align-items: center; gap: 5px; }

        .empty-state {
            text-align: center;
            padding: 60px;
            background: rgba(255,255,255,0.5);
            border-radius: 20px;
            border: 2px dashed #ccc;
        }
        .date-select {
            appearance: none;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 6px 30px 6px 12px;
            border-radius: 20px;
            font-family: 'PT Serif', serif;
            font-size: 0.85rem;
            color: #444;
            cursor: pointer;
            transition: all 0.3s ease;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23444' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            margin: 0;
        }

        .date-select:hover {
            background-color: #fff;
            border-color: #1976d2;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .date-select:focus {
            outline: none;
            border-color: #1976d2;
        }

        .sort-label {
            margin: 0; 
            font-family: 'Cinzel', serif; 
            font-size: 0.9rem; 
            font-weight: 600; 
            color: #0d1b2a;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

<div class="breadcrumb-area">
    <div class="breadcrumb-links">
        <a href="<?= $dashboard_link ?>"><i class="fas fa-home"></i> Marketplace</a>
        <span><i class="fas fa-chevron-right" style="font-size: 10px;"></i></span>
        <span style="color: #1a1a1a;">Notifications</span>
    </div>
</div>

<div class="notif-container">
    <div class="section-header">
        <h2 class="section-title">Notifications</h2>

        <div style="display: flex; align-items: center; gap: 15px;">
            <form method="GET" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                    <i class="fas fa-filter" style="font-size: 0.8rem; color: #0d1b2a;"></i>
                    <p class="sort-label">SORT BY:</p>
                </div>
                <select name="time_filter" class="date-select" onchange="this.form.submit()">
                    <option value="today" <?= $filter == 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="2_days" <?= $filter == '2_days' ? 'selected' : '' ?>>Past 2 Days</option>
                    <option value="7_days" <?= $filter == '7_days' ? 'selected' : '' ?>>This Week</option>
                    <option value="older" <?= $filter == 'older' ? 'selected' : '' ?>>Older</option>
                    <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </form>

            <!-- <?php if (count($notifications) > 0): ?>
                <form method="POST" onsubmit="return confirm('Delete all notifications permanently?');" style="margin:0;">
                    <button type="submit" name="clear_all" class="btn-clear">
                        <i class="fas fa-trash-alt"></i> CLEAR ALL
                    </button>
                </form>
            <?php endif; ?> -->
        </div>
    </div>

    <?php if (count($notifications) > 0): ?>
        <?php foreach ($notifications as $n): ?>
            <div class="notif-card <?= $n['is_read'] ? '' : 'unread' ?>">
                <div class="notif-icon">
                    <?php 
                        $msg = strtolower($n['message']);
                        $icon = 'fa-bell';
                        if (strpos($msg, 'won') !== false) $icon = 'fa-trophy';
                        if (strpos($msg, 'paid') !== false || strpos($msg, 'payment') !== false) $icon = 'fa-receipt';
                        if (strpos($msg, 'rejected') !== false || strpos($msg, 'not accepted') !== false) $icon = 'fa-times-circle';
                        if (strpos($msg, 'void') !== false || strpos($msg, 'cancel') !== false) $icon = 'fa-exclamation-triangle';
                    ?>
                    <i class="fas <?= $icon ?>"></i>
                </div>
                <div class="notif-content">
                    <div class="notif-title">
                        <?= !empty($n['title']) ? htmlspecialchars($n['title']) : 'System Message' ?>
                    </div>
                    <div class="notif-message"><?php 
                    $cleaned_message = str_replace('&amp;', '&', $n['message']); 
                    echo $cleaned_message; 
                    ?>
                    
                </div>
                    <div class="notif-time">
                        <i class="far fa-clock"></i> 
                        <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>

                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash" style="font-size: 40px; color: #ccc; margin-bottom: 15px;"></i>
            <p style="font-family: 'Cinzel'; color: #888;">No notifications found.</p>
            <a href="<?= $dashboard_link ?>" style="color: #1976d2; text-decoration: none; font-weight: bold;">Back to Dashboard</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>