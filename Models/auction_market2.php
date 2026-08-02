<?php
session_start();
include_once '../db_connect.php';
include '../inc/header.php';

$customer_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;

$limit = 8; 
$page = isset($_GET['page']) ? (int)$GET['page'] : 1;
$offset = ($page - 1) * $limit;

$countQuery = $pdo->query("SELECT COUNT(*) FROM auction WHERE status ILIKE 'active'");
$totalAuctions = $countQuery->fetchColumn();
$totalPages = ceil($totalAuctions / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auction Market | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6); 
            font-family: 'PT Serif', serif; 
            color: #1a1a1a; 
        }

        .hero-section { 
            height: 80px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-bottom: 25px; 
            max-width: 950px; 
            width: 100%; 
            padding: 0 20px; 
            margin-top: 20px;
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box; 
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

        .list-wrapper { max-width: 950px; margin: auto; padding: 0 20px; }

        .breadcrumb-vintage { list-style: none; display: flex; gap: 10px; margin-bottom: 25px; font-size: 14px; padding: 0; }
        .breadcrumb-vintage a { color: #1976d2; text-decoration: none; }
        .breadcrumb-vintage .current { color: #666; }
        .breadcrumb-vintage li:not(:last-child)::after { content: '>'; margin-left: 10px; color: #ccc; }

        .auction-grid { 
            display: flex;
            flex-direction: column;
            gap: 18px; 
        }

        .auction-card {
            display: flex; 
            flex-direction: row; 
            align-items: center;
            background: #fff;
            border-radius: 15px;
            padding: 15px 25px;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            transition: 0.3s ease;
        }
        .auction-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.05); }

        .card-img-container { 
            width: 150px; 
            height: 110px; 
            flex-shrink: 0; 
            border-radius: 12px; 
            overflow: hidden; 
        }
        .card-img-container img { width: 100%; height: 100%; object-fit: cover; }

        .card-content { 
            flex-grow: 1; 
            padding: 0 30px; 
            display: flex;
            flex-direction: column;
        }

        .animal-title { 
            font-family: 'Cinzel', serif; 
            font-size: 1.2rem; 
            color: #0d1b2a; 
            margin: 0; 
            text-transform: uppercase;
        }
        
        .subtitle {
            color: #718096;
            font-size: 13px;
            margin: 4px 0 8px 0;
        }

        .farm-info {
            font-size: 12px;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-tag {
            position: absolute;
            top: 12px;
            right: 15px;
            z-index: 20; 
        }

        .live-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 800;
            color: #2f855a;
            text-transform: uppercase;
            background: #f0fff4; 
            padding: 3px 8px;
            border-radius: 12px;
            border: 1px solid #c6f6d5;
        }

        .bid-footer { 
            text-align: right; 
            min-width: 200px;
            border-left: 1px solid #eee;
            padding-left: 20px;
            padding-top: 25px; 
            display: flex;
            flex-direction: column;
            justify-content: center; 
            align-items: flex-end;
        }

        .price-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #a0aec0;
            font-weight: bold;
            margin-bottom: 2px;
            line-height: 1; 
        }

        .pulse-dot {
            width: 6px;
            height: 6px;
            background-color: #48bb78;
            border-radius: 50%;
            box-shadow: 0 0 0 rgba(72, 187, 120, 0.4);
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(72, 187, 120, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(72, 187, 120, 0); }
            100% { box-shadow: 0 0 0 0 rgba(72, 187, 120, 0); }
        }

        .price-text { 
            font-family: 'Cinzel', serif; 
            font-size: 1.4rem; 
            color: #1976d2; 
            font-weight: bold; 
            margin: 2px 0 12px 0;
        }

        .button-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .action-btn { 
            display: inline-block;
            background: #0d1b2a; 
            color: #fff; 
            padding: 10px 16px; 
            border-radius: 8px; 
            font-family: 'Cinzel', serif; 
            font-size: 11px;
            font-weight: bold;
            text-decoration: none; 
            transition: 0.2s; 
            text-align: center;
        }

        .action-btn.guest-login {
            background: #1976d2; 
        }
        .action-btn i {
            margin-right: 5px;
        }
        .action-btn:hover { background: #1976d2; }
        .action-btn.locked { background: #e53e3e; }

        .browse-btn { background: linear-gradient(135deg, #90caf9, #64b5f6); color: #0d1b2a; padding: 14px 30px; border-radius: 30px; font-family: 'Cinzel', serif; font-weight: bold; text-decoration: none; transition: 0.3s; }

        .details-btn {
            display: inline-block;
            background: transparent;
            color: #4a5568;
            padding: 8px 16px;
            border-radius: 8px;
            font-family: 'Cinzel', serif;
            font-size: 10px;
            font-weight: bold;
            text-decoration: none;
            border: 1px solid #e2e8f0;
            transition: 0.2s;
            text-align: center;
        }
        .details-btn:hover { background: #f7fafc; border-color: #cbd5e0; color: #0d1b2a; }

        .stats-row { display: flex; gap: 15px; margin-top: 10px; border-top: 1px dashed #eee; padding-top: 10px; }
        .stat-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #718096; }
        .stat-item i { color: #1976d2; font-size: 12px; }
        .deposit-badge { font-size: 10px; background: #fff8e1; color: #b7791f; padding: 2px 6px; border-radius: 4px; border: 1px solid #fef3c7; font-weight: bold; margin-top: 5px; align-self: flex-start; }

        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 25px; }
        .pagination a { padding: 6px 12px; border-radius: 4px; background: #fff; color: #0d1b2a; text-decoration: none; border: 1px solid #90caf9; font-size: 12px; font-family: 'Cinzel', serif; }
        .pagination a.active { background: #0d1b2a; color: #fff; border-color: #0d1b2a; }
    </style>
</head>
<body>

    <div class="list-wrapper">
        <div class="hero-section">
        <h1>Live Auctions</h1>
    </div>
       <ul class="breadcrumb-vintage">
        <li><a href="customer_dashboard.php"><i class="fas fa-home"></i> Marketplace</a></li>
        <li class="current">Live Auctions</li>
    </ul>

    <div class="auction-grid">
        <?php
        $sql = "SELECT l.*, a.auction_id, a.current_bid, a.status as auction_status, 
        f.farm_name, ad.amount as deposit_amount,
        (SELECT COUNT(*) FROM bidding b WHERE b.livestock_id = a.livestock_id) as total_bids,
        (SELECT COUNT(*) FROM auction_deposits_paid adp WHERE adp.auction_id = a.auction_id AND adp.status = 'paid') as participant_count
        FROM livestock l 
        JOIN auction a ON l.livestock_id = a.livestock_id 
        JOIN farmer f ON l.farmer_id = f.farmer_id
        LEFT JOIN auction_deposits ad ON a.auction_id = ad.auction_id
        WHERE a.status ILIKE 'active'
        ORDER BY a.auction_id DESC
        LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($auctions)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: rgba(255,255,255,0.5); border-radius: 25px; border: 2px dashed #90caf9;">
                <i class="fas fa-gavel" style="font-size: 4rem; color: #cbd5e0; margin-bottom: 20px;"></i>
                <h2 style="font-family: 'Cinzel', serif; color: #0d1b2a;">No Active Auctions</h2>
                <p style="color: #666; font-size: 1.1rem;">There are currently no livestock auctions available. Please check back later or visit the Marketplace.</p>
                <a href="customer_dashboard.php" class="browse-btn" style="display: inline-block; margin-top: 20px;">
                    <i class="fas fa-shopping-cart"></i> Browse Marketplace
                </a>
            </div>
        <?php else: 
            foreach ($auctions as $row): 
                $images = !empty($row['image']) ? explode(',', $row['image']) : ['../assets/no-image.png'];
                $imgSrc = (strpos(trim($images[0]), '../') === false) ? "../farmer/uploads/" . trim($images[0]) : trim($images[0]);
                $l_id = $row['livestock_id'];
                $a_id = $row['auction_id'];

                $hasPaid = false;
                $isLoggedIn = ($customer_id !== null);
                $hasPaid = false;

                if ($isLoggedIn) {
                    $p_stmt = $pdo->prepare("SELECT 1 FROM auction_deposits_paid WHERE customer_id = ? AND auction_id = ? AND status = 'paid'");
                    $p_stmt->execute([$customer_id, $a_id]);
                    $hasPaid = (bool)$p_stmt->fetch();
                }
                ?>
                <div class="auction-card">
                    <div class="card-img-container">
                        <img src="<?= $imgSrc ?>" alt="Livestock">
                    </div>

                    <div class="card-content">
                        <h3 class="animal-title"><?= htmlspecialchars($row['name']) ?></h3>
                        <div style="font-size: 12px; color: #718096;"><?= $row['breed'] ?> • <?= $row['farm_name'] ?></div>
                        
                        <div class="deposit-badge">
                            <i class="fas fa-shield-alt"></i> Required Deposit: RM <?= number_format($row['deposit_amount'], 2) ?>
                        </div>

                        <div class="stats-row">
                            <div class="stat-item">
                                <p style="font-weight: bold; color: #24C778;">Now:</p><br>
                                <i class="fas fa-gavel"></i>
                                <strong><?= $row['total_bids'] ?></strong> Bids
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-users"></i>
                                <strong><?= $row['participant_count'] ?></strong> Participants
                            </div>
                        </div>
                    </div>

                    <div class="bid-footer">
                        <div class="price-label">Current Bid</div>
                        <div class="price-text">RM <?= number_format($row['current_bid'], 2) ?></div>
                        
                        <div class="button-group">
                            <?php if (!$isLoggedIn): ?>
                                <a href="customer_login.php" class="action-btn" style="background: #1976d2;">
                                    <i class="fas fa-sign-in-alt"></i> LOGIN TO JOIN
                                </a>
                            <?php else: ?>
                                <a href="<?= $hasPaid ? "Join_Auction.php?livestock_id=$l_id" : "pay_deposit.php?auction_id=$a_id&livestock_id=$l_id" ?>" 
                                   class="action-btn <?= !$hasPaid ? 'locked' : '' ?>">
                                   <i class="fas <?= $hasPaid ? 'fa-gavel' : 'fa-lock' ?>"></i>
                                   <?= $hasPaid ? 'JOIN BID' : 'PAY DEPOSIT' ?>
                               </a>
                           <?php endif; ?>

                           <a href="livestock_view.php?livestock_id=<?= $l_id ?>" class="details-btn">
                            VIEW DETAILS
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>" class="<?= ($page == $i) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div> -->
</div>

<!-- <?php include '../inc/footer.php'; ?> -->
</body>
</html>