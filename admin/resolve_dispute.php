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

$query = "SELECT a.*, l.name as livestock_name, l.breed, f.farm_name, f.email as farmer_email
          FROM auction a 
          JOIN livestock l ON a.livestock_id = l.livestock_id 
          JOIN farmer f ON l.farmer_id = f.farmer_id 
          WHERE a.auction_id = :aid";
$stmt = $pdo->prepare($query);
$stmt->execute(['aid' => $auction_id]);
$auction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$auction) {
    die("Auction record not found.");
}

$bid_query = "SELECT b.*, c.name as bidder_name, c.email as bidder_email 
              FROM bidding b 
              JOIN customer c ON b.customer_id = c.customer_id 
              WHERE b.livestock_id = :lid 
              ORDER BY b.current_bid DESC LIMIT 1";
$bid_stmt = $pdo->prepare($bid_query);
$bid_stmt->execute(['lid' => $auction['livestock_id']]);
$highest_bid = $bid_stmt->fetch(PDO::FETCH_ASSOC);

// Handle Form Submission
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resolution_action = $_POST['resolution_action'] ?? null;
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    if (empty($resolution_action) || empty($admin_notes)) {
        $message = "Please select a resolution action and provide case notes.";
        $message_type = "error";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Log the dispute case resolution into an audit trail/history or update auction notes
            // Adjust this column update if you have a specialized 'dispute_notes' or 'status' column system
            $update_query = "UPDATE auction SET status = :status WHERE auction_id = :aid";
            
            // Set final status string based on selected action
            $final_status = 'closed';
            if ($resolution_action === 'void_entire_auction') {
                $final_status = 'voided';
            }

            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([
                'status' => $final_status,
                'aid' => $auction_id
            ]);

            // Optional structural note setup: You can insert this into a specialized notifications/logs table if needed.
            // For now, we commit the state change smoothly.
            $pdo->commit();
            
            $message = "Dispute resolved successfully! Case action: " . ucwords(str_replace('_', ' ', $resolution_action));
            $message_type = "success";
            
            // Refresh data context
            $auction['status'] = $final_status;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Database error occurred: " . $e->getMessage();
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resolve Dispute | Admin Portal</title>
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
            --danger: #c62828;
            --success: #2e7d32;
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
            max-width: 900px; 
            margin: auto; 
            background: white; 
            border: 1px solid var(--border); 
            box-shadow: 8px 8px 0px var(--gold); 
            padding: 30px; 
            position: relative;
        }

        .alert-box {
            padding: 15px;
            margin-bottom: 25px;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            font-weight: bold;
            border-left: 5px solid;
        }
        .alert-success { background: #e8f5e9; color: var(--success); border-color: var(--success); }
        .alert-error { background: #ffebee; color: var(--danger); border-color: var(--danger); }

        .dispute-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 20px;
        }
        .info-card h3 { font-family: 'Cinzel', serif; margin-top: 0; color: var(--charcoal); border-bottom: 1px solid #ddd; padding-bottom: 8px;}
        .info-card p { margin: 10px 0; font-size: 0.9rem;}

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            font-size: 0.85rem;
            margin-bottom: 8px;
            color: var(--charcoal);
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            font-family: 'Raleway', sans-serif;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--gold);
            background: #fffdf9;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn-submit {
            background: var(--charcoal);
            color: white;
            border: 1px solid var(--charcoal);
            padding: 12px 25px;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            font-weight: bold;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
        }
        .btn-submit:hover {
            background: var(--gold);
            border-color: var(--gold);
            transform: translateY(-1px);
        }

        .btn-cancel {
            background: transparent;
            color: var(--charcoal);
            border: 1px solid var(--border);
            padding: 12px 25px;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
        }
        .btn-cancel:hover {
            background: #eee;
        }

        .status-pill {
            padding: 3px 8px;
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: bold;
            color: white;
            background: var(--gold);
            border-radius: 2px;
        }
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
            <h1>Dispute Resolution Hub</h1>
            <div class="date" style="font-family: 'Cinzel';"><i class="fas fa-calendar"></i> <?= date('D, d M Y') ?></div>
        </div>

        <div class="breadcrumbs">
            <i class="fas fa-home"></i><a href="admin_dashboard.php"> Dashboard</a><span> > </span> <a href="manage_auctions.php"> Manage Auctions</a><span> > </span> Resolve Dispute
        </div>

        <div class="container">
            <?php if (!empty($message)): ?>
                <div class="alert-box alert-<?= $message_type ?>">
                    <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="dispute-grid">
                <div class="info-card">
                    <h3><i class="fas fa-gavel"></i> Auction Summary</h3>
                    <p><strong>Auction ID:</strong> #<?= htmlspecialchars($auction['auction_id']) ?></p>
                    <p><strong>Livestock Name:</strong> <?= htmlspecialchars($auction['livestock_name']) ?> (<?= htmlspecialchars($auction['breed']) ?>)</p>
                    <p><strong>Farmer / Owner:</strong> <?= htmlspecialchars($auction['farm_name']) ?> (<small><?= htmlspecialchars($auction['farmer_email']) ?></small>)</p>
                    <p><strong>Current Logged Status:</strong> <span class="status-pill"><?= htmlspecialchars($auction['status']) ?></span></p>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-trophy"></i> Leading Bid Metrics</h3>
                    <?php if ($highest_bid): ?>
                        <p><strong>Bidder:</strong> <?= htmlspecialchars($highest_bid['bidder_name']) ?></p>
                        <p><strong>Email Address:</strong> <?= htmlspecialchars($highest_bid['bidder_email']) ?></p>
                        <p><strong>Bid Amount Placed:</strong> <span style="font-weight: bold; color: var(--success);">RM <?= number_format($highest_bid['current_bid'], 2) ?></span></p>
                        <p><strong>Logged Timestamp:</strong> <?= date('d M Y, h:i A', strtotime($highest_bid['created_at'])) ?></p>
                    <?php else: ?>
                        <p style="color: #888; font-style: italic; margin-top: 20px;">No successful bids have been registered for this listing tracking session.</p>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="resolution_action">System Resolution Verdict Action</label>
                    <select name="resolution_action" id="resolution_action" class="form-control">
                        <option value="">-- Choose Resolution Policy Path --</option>
                        <option value="approve_highest_bidder">Validate & Award Listing to Highest Bidder</option>
                        <option value="void_entire_auction">Void Entire Listing (Suspicious Framework Activity / Non-payment)</option>
                        <option value="refund_and_relist">Cancel Session & Authorize Free Re-list for Farmer</option>
                        <option value="manual_override">Apply Custom Database Manual Log Override</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="admin_notes">Official Case Investigation Notes (Public Audit Log)</label>
                    <textarea name="admin_notes" id="admin_notes" rows="6" class="form-control" placeholder="Describe context details, complaints raised, validation checks performed, and full logical explanation behind this choice..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-balance-scale"></i> Execute Resolution</button>
                    <a href="manage_auctions.php" class="btn-cancel">Back to Portal</a>
                </div>
            </form>
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
    </script>
</body>
</html>