<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$auction_id = $_GET['id'] ?? null;

if (!$auction_id) {
    die("Auction ID missing.");
}

$query = "SELECT a.*, l.name as livestock_name, l.breed, f.farm_name 
FROM auction a 
JOIN livestock l ON a.livestock_id = l.livestock_id 
JOIN farmer f ON l.farmer_id = f.farmer_id 
WHERE a.auction_id = :aid";
$stmt = $pdo->prepare($query);
$stmt->execute(['aid' => $auction_id]);
$auction = $stmt->fetch(PDO::FETCH_ASSOC);

$bid_query = "SELECT b.*, c.name as bidder_name, c.email as bidder_email 
FROM bidding b 
JOIN customer c ON b.customer_id = c.customer_id 
WHERE b.livestock_id = (SELECT livestock_id FROM auction WHERE auction_id = :aid) 
ORDER BY b.current_bid DESC";

$bid_stmt = $pdo->prepare($bid_query);
$bid_stmt->execute(['aid' => $auction_id]);
$bids = $bid_stmt->fetchAll(PDO::FETCH_ASSOC);

$is_auction_closed = in_array(strtolower($auction['status'] ?? ''), ['closed', 'completed']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monitor Bids | Admin Portal</title>
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

        .container { 
            max-width: 1000px; 
            margin: auto; 
            background: white; 
            border: 1px solid var(--border); 
            box-shadow: 8px 8px 0px var(--gold); 
            padding: 30px; 
            position: relative;
        }
        .header-section { display: flex; justify-content: space-between; border-bottom: 2px solid var(--gold); padding-bottom: 15px; margin-bottom: 20px; }
        h2, h3 { font-family: 'Cinzel', serif; color: var(--charcoal); margin: 0; }
        
        .auction-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; background: #fafafa; padding: 20px; border: 1px solid #ddd; }
        
        .bid-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .bid-table th { background: var(--charcoal); color: white; font-family: 'Cinzel', serif; padding: 12px; text-align: left; }
        .bid-table td { padding: 12px; border-bottom: 1px solid #eee; }
        
        .highest-bid { background: #e8f5e9 !important; font-weight: bold; }
        .badge-winner { background: #2e7d32; color: white; padding: 2px 8px; font-size: 0.7rem; border-radius: 4px; }

        #notification-toast {
            display: none;
            position: fixed;
            top: 25px;
            right: 25px;
            background: var(--charcoal);
            color: white;
            padding: 18px 30px;
            border-left: 6px solid var(--gold);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 9999;
            font-family: 'Cinzel', serif;
            font-size: 0.9rem;
            animation: slideIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        .btn-void {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 8px;
            background: transparent;
            color: #c62828;
            border: 1px solid #c62828;
            text-decoration: none;
            font-family: 'Cinzel', serif;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.2s ease;
        }

        .btn-void:hover {
            background: #c62828;
            color: white;
            box-shadow: 4px 4px 0px rgba(198, 40, 40, 0.2);
            transform: translate(-2px, -2px);
        }

        .btn-void i {
            font-size: 0.8rem;
        }

        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
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
            <h1>View Auction Details</h1>
            <div class="date" style="font-family: 'Cinzel';"><i class="fas fa-calendar"></i> <?= date('D, d M Y') ?></div>
        </div>

        <div class="breadcrumbs">
            <i class="fas fa-home"></i><a href="admin_dashboard.php"> Dashboard</a><span> > </span> <a href="manage_auctions.php"> Manage Auctions</a><span> > </span> View Auction Details
        </div>
        <div class="container">
            <div id="notification-toast">
                <i class="fas fa-gavel" style="color: var(--gold); margin-right: 12px;"></i> 
                <span id="toast-message">Bid Voided Successfully</span>
            </div>

            <div class="auction-summary">
                <div>
                    <h3>Livestock Details</h3>
                    <p><strong>Item:</strong> <?= htmlspecialchars($auction['livestock_name']) ?> (<?= htmlspecialchars($auction['breed']) ?>)</p>
                    <p><strong>Farm Name:</strong> <?= htmlspecialchars($auction['farm_name']) ?></p>
                </div>
                <div>
                    <h3>Auction Settings</h3>
                    <p><strong>Starting Bid:</strong> RM <?= number_format($auction['starting_price'], 2) ?></p>
                    <p><strong>Ending:</strong> <?= date('d M Y, h:i A', strtotime($auction['end_time'])) ?></p>
                </div>
            </div>

            <h3>Bid History Log</h3>
            <table class="bid-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Bidder Name</th>
                        <th>Bid Amount</th>
                        <th>Bid Time</th>
                        <?php if (!$is_auction_closed): ?>
                            <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bids): ?>
                        <?php foreach ($bids as $index => $bid): ?>
                            <tr class="<?= $index === 0 ? 'highest-bid' : '' ?>">
                                <td><?= $index + 1 ?>.</td>
                                <td>
                                    <?= htmlspecialchars($bid['bidder_name']) ?> 
                                    <?php if($index === 0) echo '<span class="badge-winner">Highest</span>'; ?>
                                    <br><small><?= htmlspecialchars($bid['bidder_email']) ?></small>
                                </td>
                                <td>RM <?= number_format($bid['current_bid'], 2) ?></td>
                                <td><?= date('d/m/y H:i:s', strtotime($bid['created_at'])) ?></td>
                                <?php if (!$is_auction_closed): ?>
                                    <td>
                                        <a href="void_bid.php?bid_id=<?= $bid['bid_id'] ?>&auction_id=<?= $auction_id ?>" 
                                           class="btn-void" 
                                           onclick="return confirm('Void this bid for suspicious activity?')">
                                           <i class="fas fa-ban"></i> Void Bid
                                        </a>
                                    </td>
                                <?php endif; ?>
                           </tr>
                       <?php endforeach; ?>
                   <?php else: ?>
                    <tr>
                        <td colspan="<?= $is_auction_closed ? 4 : 5 ?>" style="text-align:center; padding: 20px;">
                            No bids have been placed yet.
                        </td>
                    </tr>
                <?php endif; ?>
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
        if (sidebar.classList.contains('collapsed')) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        } else {
            toggleIcon.classList.remove('fa-chevron-right');
            toggleIcon.classList.add('fa-chevron-left');
        }
    });

    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('status') === 'void_success') {
            const toast = document.getElementById('notification-toast');
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.animation = 'fadeOut 0.5s ease-out forwards';
                setTimeout(() => { toast.style.display = 'none'; }, 500);
            }, 4000);
            const newUrl = window.location.origin + window.location.pathname + '?id=' + urlParams.get('id');
            window.history.replaceState({}, document.title, newUrl);
        }
    };
</script>
</body>
</html>