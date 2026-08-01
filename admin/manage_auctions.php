<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$query = "SELECT a.*, l.name as livestock_name, f.farm_name as farmer_name 
FROM auction a 
JOIN livestock l ON a.livestock_id = l.livestock_id 
JOIN farmer f ON l.farmer_id = f.farmer_id 
ORDER BY a.end_time DESC";
$auctions = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Auctions | Admin Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Raleway:wght@300;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --gold: #b89b5e;
            --charcoal: #2c2c2c;
            --cream: #f9f7f2;
            --border: #453c34;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
        }

        body { margin: 0; font-family: 'Raleway', sans-serif; background: var(--cream); display: flex; transition: 0.3s; }

        .sidebar {
            width: var(--sidebar-width); background: var(--charcoal); color: white;
            height: 100vh; position: fixed; border-right: 3px solid var(--gold);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1000; overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #444; font-family: 'Cinzel', serif; white-space: nowrap; }
        #sidebarCollapse {
            position: absolute; top: 15px; right: -15px; background: var(--gold); color: white;
            width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; border: 2px solid var(--charcoal); z-index: 1001; transition: 0.3s;
        }
        .sidebar.collapsed #sidebarCollapse { right: 20px; }
        .nav-links { list-style: none; padding: 0; margin-top: 20px; }
        .nav-links li a {
            display: flex; align-items: center; padding: 15px 25px; color: #ccc; text-decoration: none; 
            transition: 0.3s; font-family: 'Cinzel', serif; font-size: 0.9rem;
        }
        .nav-links i { margin-right: 20px; width: 20px; font-size: 1.1rem; text-align: center; }
        .sidebar.collapsed .link-text, .sidebar.collapsed .sidebar-header h3 { display: none; }
        .nav-links li a:hover, .nav-links li a.active { background: var(--gold); color: white; }
        .breadcrumbs { margin-bottom: 20px; font-size: 0.8rem; font-family: 'Cinzel', serif; color: #777; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;}
        .breadcrumbs a { color: var(--gold); text-decoration: none; }
        .breadcrumbs i { color: var(--gold);}

        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 30px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        body.collapsed-active .main-content { margin-left: var(--sidebar-collapsed-width); }

        .top-bar {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 10px;
        }
        .top-bar h1 { font-family: 'Cinzel', serif; margin: 0; color: var(--charcoal); }

        .table-container { background: white; border: 1px solid var(--border); box-shadow: 8px 8px 0px var(--gold); padding: 20px; overflow-x: auto; border-radius: 12px;}
        .modern-admin-table { width: 100%; border-collapse: separate; border-spacing: 0; background: white; }
        .modern-admin-table th { 
            background: var(--charcoal); color: white; font-family: 'Cinzel', serif; 
            text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; padding: 15px; text-align: left; border:none;
        }
        .modern-admin-table th:first-child {
            border-top-left-radius: 15px;
            border-bottom-left-radius: 15px;
        }

        .modern-admin-table th:last-child {
            border-top-right-radius: 15px;
            border-bottom-right-radius: 15px;
        }
        .modern-admin-table td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: middle; color: #444; font-size: 0.9rem; }
        .modern-admin-table tr:hover td { background: #fdfaf3; }
        
        .modern-admin-table tr td:first-child { border-left: 3px solid transparent; }
        .modern-admin-table tr:hover td:first-child { border-left: 3px solid var(--gold);}

        .tabs { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 20px; 
            justify-content: center;
            align-items: center;
        }
        .tab-btn { 
            padding: 10px 25px; 
            cursor: pointer; 
            border: 2px solid var(--gold); 
            border-radius: 8px; 
            background: none; 
            font-family: 'Cinzel', serif; 
            font-size: 0.8rem; 
            font-weight: bold; 
            color: var(--gold); 
            transition: 0.3s;
        }

        .tab-btn-live { 
            padding: 10px 25px; 
            cursor: pointer; 
            border: 2px solid #2e7d32; 
            border-radius: 8px; 
            background: none; 
            font-family: 'Cinzel', serif; 
            font-size: 0.8rem; 
            font-weight: bold; 
            color: #2e7d32; 
            transition: 0.3s;
        }
        .tab-btn-closed { 
            padding: 10px 25px; 
            cursor: pointer; 
            border: 2px solid #c62828; 
            border-radius: 8px; 
            background: none; 
            font-family: 'Cinzel', serif; 
            font-size: 0.8rem; 
            font-weight: bold; 
            color: #c62828; 
            transition: 0.3s;
        }

        .tab-btn:hover, .tab-btn.active { 
            background: var(--gold); 
            color: white; 
        }

        .tab-btn-live:hover { 
            background: #2e7d32; 
            color: white; 
        }

        .tab-btn-closed:hover { 
            background: #c62828; 
            color: white; 
        }

        .status-pill { 
            padding: 4px 10px; font-family: 'Cinzel', serif; font-size: 0.65rem; 
            color: white; border-radius: 2px; font-weight: bold; text-transform: uppercase;
        }
        .status-active { background: #2e7d32; }
        .status-closed { background: #c62828; }

        .time-text {
            font-size: 0.75rem !important; 
            color: #666;
            white-space: nowrap; 
        }

        .price-text { font-family: 'Raleway', sans-serif; font-weight: 700; color: var(--charcoal); }
        .action-buttons {
            display: flex;
            gap: 8px; 
            white-space: nowrap; 
        }
        .btn-table { 
            padding: 6px 10px; font-family: 'Cinzel', serif; font-size: 0.65rem; 
            text-decoration: none; font-weight: bold; border: 1px solid var(--charcoal); 
            transition: 0.3s; display: inline-block; margin: 0; flex: 1; text-align: center;
        }
        .btn-view { background: var(--charcoal); color: white; }
        .btn-dispute { background: #fff; color: #c62828; border-color: #c62828; }
        .btn-table:hover { opacity: 0.8; transform: translateY(-1px); }
    </style>
</head>
<body id="bodyTag">

    <div class="sidebar" id="sidebar">
        <div id="sidebarCollapse"><i class="fas fa-chevron-left" id="toggleIcon"></i></div>
        <div class="sidebar-header"><h3>Admin Portal</h3></div>
        <ul class="nav-links">
            <li><a href="admin_dashboard.php"><i class="fas fa-th-large"></i> <span class="link-text">Overview</span></a></li>
            <li><a href="manage_users.php"><i class="fas fa-users"></i> <span class="link-text">Manage Users</span></a></li>
            <li><a href="verify_listings.php"><i class="fas fa-check-circle"></i> <span class="link-text">Verify Listings</span></a></li>
            <li><a href="manage_auctions.php" class="active"><i class="fas fa-gavel"></i> <span class="link-text">Manage Auctions</span></a></li>
            <li><a href="send_notifications.php"><i class="fas fa-bullhorn"></i> <span class="link-text">Send Notifications</span></a></li>
            <li><a href="logout.php" style="margin-top: 50px; color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <h1>Auction Management</h1>
            <div class="date" style="font-family: 'Cinzel';"><i class="fas fa-calendar"></i> <?= date('D, d M Y') ?></div>
        </div>

        <div class="breadcrumbs">
            <i class="fas fa-home"></i><a href="admin_dashboard.php"> Dashboard</a><span> > </span> All Auctions
        </div>

        <div class="table-container">
            <div class="tabs">
                <button class="tab-btn active" onclick="filterAuctions('all', event)">All Auctions</button>
                <button class="tab-btn-live" onclick="filterAuctions('live', event)">Live</button>
                <button class="tab-btn-closed" onclick="filterAuctions('closed', event)">Closed</button>
            </div>
            <table class="modern-admin-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Livestock</th>
                        <th>Farmer</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Current High Bid</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($auctions as $a): 
                        $is_closed = strtotime($a['end_time']) < time();
                        $status_class = $is_closed ? 'status-closed' : 'status-active';
                        $status_text = $is_closed ? 'Closed' : 'Live'; 
                        ?>
                        <tr class="auction-row" data-status="<?= $is_closed ? 'closed' : 'live' ?>">
                            <td style="font-weight: bold; color: var(--gold);"><?= $no++ ?>.</td>
                            <td><strong style="font-family: 'Cinzel';"><?= htmlspecialchars($a['livestock_name']) ?></strong></td>
                            <td><?= htmlspecialchars($a['farmer_name']) ?></td>
                            <td class="time-text">
                                <i class="fas fa-play-circle" style="color: #2e7d32; font-size: 0.65rem;"></i> 
                                <?= date('d M, h:i A', strtotime($a['start_time'])) ?>
                            </td>
                            <td class="time-text">
                                <i class="far fa-clock" style="color: var(--gold); font-size: 0.8rem;"></i> 
                                <?= date('d M, h:i A', strtotime($a['end_time'])) ?>
                            </td>
                            <td class="price-text">RM <?= number_format($a['current_bid'] ?? $a['starting_price'], 2) ?></td>
                            <td>
                                <span class="status-pill <?= $status_class ?>"><?= $status_text ?></span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_auction_details.php?id=<?= $a['auction_id'] ?>" class="btn-table btn-view">
                                        <i class="fas fa-eye"></i> Monitor
                                    </a>
                                    <!-- <a href="resolve_dispute.php?id=<?= $a['auction_id'] ?>" class="btn-table btn-dispute">
                                        <i class="fas fa-exclamation-triangle"></i> Dispute
                                    </a> -->
                                </div>
                            </td>
                        </tr>
                <?php endforeach; ?>

                <?php if (empty($auctions)): ?>
                    <tr id="empty-db-message">
                        <td colspan="8" style="text-align: center; padding: 60px; color: #888; font-family: 'Cinzel';">
                            <i class="fas fa-folder-open" style="display:block; font-size: 2rem; margin-bottom: 10px; color: #ddd;"></i>
                            No active or past auctions found in the database.
                        </td>
                    </tr>
                <?php endif; ?>

                <tr id="no-filter-results" style="display: none;">
                    <td colspan="8" style="text-align: center; padding: 60px; color: #888; font-family: 'Cinzel';">
                        <i class="fas fa-search" style="display:block; font-size: 2rem; margin-bottom: 10px; color: #ddd;"></i>
                        No auctions match the selected filter.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const bodyTag = document.getElementById('bodyTag');
    const toggleBtn = document.getElementById('sidebarCollapse');
    const toggleIcon = document.getElementById('toggleIcon');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        bodyTag.classList.toggle('collapsed-active');
        toggleIcon.classList.toggle('fa-chevron-left');
        toggleIcon.classList.toggle('fa-chevron-right');
    });

    function filterAuctions(status, event) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        event.currentTarget.classList.add('active');

        const rows = document.querySelectorAll('.auction-row');
    const noResultsMsg = document.getElementById('no-filter-results'); // ID FIXED
    let visibleCount = 0;

    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        
        if (status === 'all' || rowStatus === status) {
            row.style.display = "";
            visibleCount++;
        } else {
            row.style.display = "none";
        }
    });

    if (noResultsMsg) {
        noResultsMsg.style.display = (visibleCount === 0 && rows.length > 0) ? "" : "none";
    }
}
</script>
</body>
</html>