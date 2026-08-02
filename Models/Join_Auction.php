<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

$projectFolder = "/LivestockMarketplace";
$db_path = $_SERVER['DOCUMENT_ROOT'] . $projectFolder . "/db_connect.php";
if (file_exists($db_path)) { 
    include_once($db_path); 
} else {
    include_once '../db_connect.php'; 
}

require_once '../pusher/pusher_config.php';

if (!isset($_SESSION['customer_id'])) {
    $current_url = $_SERVER['REQUEST_URI'];
    header("Location: customer_login.php?redirect=" . urlencode($current_url));
    exit();
}

$customer_id = $_SESSION['customer_id'];
$livestock_id = isset($_GET['livestock_id']) ? (int)$_GET['livestock_id'] : 0;
$auction_id_param = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;

// echo "<pre>";
// $debug = $pdo->query("SELECT * FROM auction LIMIT 1")->fetch(PDO::FETCH_ASSOC);
// if ($debug) {
//     print_r(array_keys($debug)); 
// } else {
//     echo "The auction table is empty.";
// }
// exit;

if ($livestock_id === 0 && $auction_id_param !== 0) {
    $stmtId = $pdo->prepare('SELECT "livestock_id" FROM "auction" WHERE "auction_id" = ?');
    $stmtId->execute([$auction_id_param]);
    $res = $stmtId->fetch();
    if ($res) {
        $livestock_id = $res['livestock_id'];
    }
}

if ($livestock_id === 0) {
    die("Error: No Livestock ID provided.");
}

$stmt = $pdo->prepare("SELECT l.*, f.farm_name, f.name as owner_name, f.phone_number, f.email, f.address 
                       FROM livestock l 
                       JOIN farmer f ON l.farmer_id = f.farmer_id 
                       WHERE l.livestock_id = ?");
$stmt->execute([$livestock_id]);
$livestock = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$livestock) die("Error: Livestock not found.");

$stmtAuction = $pdo->prepare('
    SELECT * FROM "auction" 
    WHERE "livestock_id" = ? 
    AND ("status" ILIKE \'active\' OR "status" ILIKE \'closed\') 
    ORDER BY "created_at" DESC LIMIT 1
');
$stmtAuction->execute([$livestock_id]);
$auctionData = $stmtAuction->fetch(PDO::FETCH_ASSOC);

if (!$auctionData) die("Error: No active auction found.");

$auction_id = $auctionData['auction_id'];
$end_time = $auctionData['end_time'];
$current_bid = !empty($auctionData['current_bid']) ? $auctionData['current_bid'] : $livestock['price'];
$images = explode(',', $livestock['image']);

$current_time_unix = time();
$end_time_unix = strtotime($end_time);
$auction_closed = ($auctionData['status'] === 'closed' || $current_time_unix >= $end_time_unix);


$is_winner = false;
$is_approved = false;

if ($auction_closed) {
    $stmtCheckWin = $pdo->prepare("
        SELECT * FROM bidding 
        WHERE livestock_id = ? 
        ORDER BY current_bid DESC LIMIT 1
    ");
    $stmtCheckWin->execute([$livestock_id]); 
    $highestBid = $stmtCheckWin->fetch();

    if ($highestBid && (int)$highestBid['customer_id'] === (int)$customer_id) {
        $is_winner = true; 
        $win = $highestBid; 

        if (!empty($highestBid['winner_id'])) {
            $is_approved = true;
        }
    }
}


$h_stmt = $pdo->prepare("SELECT * FROM health WHERE livestockid = ? ORDER BY healthdate DESC");
$h_stmt->execute([$livestock_id]);
$healthRecords = $h_stmt->fetchAll(PDO::FETCH_ASSOC);

$s_stmt = $pdo->prepare("SELECT servicetype, servicefee FROM harvestservice WHERE livestockid = ?");
$s_stmt->execute([$livestock_id]);
$services = $s_stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtHasWinner = $pdo->prepare("SELECT COUNT(*) FROM bidding WHERE livestock_id = ? AND winner_id IS NOT NULL");
$stmtHasWinner->execute([$livestock_id]);
$winnerMarked = ($stmtHasWinner->fetchColumn() > 0);

// Fetch Bidding History
$b_stmt = $pdo->prepare("SELECT b.*, c.name 
                         FROM bidding b 
                         JOIN customer c ON b.customer_id = c.customer_id 
                         WHERE b.livestock_id = ? 
                         ORDER BY b.current_bid DESC LIMIT 5");
$b_stmt->execute([$livestock_id]);
$bidHistory = $b_stmt->fetchAll(PDO::FETCH_ASSOC);

$current_bid = !empty($auctionData['current_bid']) ? $auctionData['current_bid'] : $livestock['price'];
$end_time = $auctionData['end_time'];
$images = explode(',', $livestock['image']);

include '../inc/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Auction | <?= htmlspecialchars($livestock['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
    <style>
        :root { 
            --accent: #1976d2; 
            --gold: #c5a059; 
            --dark: #0d1b2a; 
            --bg: #f8f9fa;
            --glass: rgba(255, 255, 255, 0.9);
        }

        body { 
            background: linear-gradient(135deg, #fdf6ec 0%, #f4efe6 100%); 
            font-family: 'PT Serif', serif; 
            color: #2d3436; 
            padding-top: 90px;
            line-height: 1.6;
        }

        .breadcrumb-container {
            grid-column: 1 / 4; 
            grid-row: 1;
            margin-bottom: 10px;
            z-index: 10;
            position: relative;
        }
        .breadcrumb { 
            list-style: none; 
            display: flex; 
            gap: 10px; 
            margin: 0; 
            padding: 0;
            font-size: 14px; 
        }
        .breadcrumb a { 
            color: #1976d2; 
            text-decoration: none; 
            transition: 0.3s;
        }
        .breadcrumb a:hover { color: black; }
        .breadcrumb li:not(:last-child)::after { 
            content: '>'; 
            margin-left: 10px; 
            color: #cbd5e0; 
        }
        .breadcrumb .current { 
            color: #666; 
            font-weight: 600;
        }

        .dashboard-container { 
            max-width: 1300px; 
            margin: 20px auto; 
            display: grid; 
            grid-template-columns: 70px 1fr 380px;
            grid-template-rows: auto 1fr; 
            gap: 20px; 
            padding: 0 20px; 
        }

        .thumb-rail { grid-row: 2; grid-column: 1; display: flex; flex-direction: column; gap: 15px; padding-top: 20px; }
        .main-content { grid-row: 2; grid-column: 2; }
        .bid-sidebar { grid-row: 2; grid-column: 3; position: sticky; top: 100px; height: fit-content; }

        .thumb-box { 
            width: 60px; height: 60px; border-radius: 10px; cursor: pointer; 
            border: 2px solid transparent; transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .thumb-box.active { border-color: var(--accent); transform: translateX(5px); }
        .thumb-box img { width: 100%; height: 100%; object-fit: cover; }

        .hero-section {
            background: var(--glass); border-radius: 20px; padding: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid rgba(255,255,255,0.8);
        }
        .hero-display { 
            height: 250px; display: flex; align-items: center; justify-content: center;
            background: #fff; border-radius: 15px; overflow: hidden; margin-bottom: 55px;
        }
        #mainHeroImg { max-width: 100%; max-height: 100%; object-fit: contain; transition: 0.5s; }

        .specs-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .spec-card { 
            background: #fff; padding: 8px 5px; border-radius: 15px; 
            text-align: center; border: 1px solid #edf2f7; transition: 0.3s;
        }
        .spec-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .spec-card label { display: block; font-size: 10px; font-weight: bold; color: #a0aec0; text-transform: uppercase; letter-spacing: 1px; }
        .spec-card span { font-family: 'Cinzel', serif; font-size: 14px; color: var(--dark); font-weight: 700; }

        .details-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; }
        .info-panel { background: var(--glass); border-radius: 20px; padding: 25px; border: 1px solid rgba(0,0,0,0.05); }
        .info-panel h4 { font-family: 'Cinzel', serif; font-size: 1.1rem; margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px; }
        .info-panel h4 i { color: var(--accent); }
        .full-width-panel { grid-column: span 2; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; font-size: 11px; color: #718096; text-transform: uppercase; padding: 12px 0; border-bottom: 2px solid #f7fafc; }
        .data-table td { padding: 14px 0; border-bottom: 1px solid #f7fafc; font-size: 14px; }

        .sticky-card { background: #fff; border-radius: 28px; padding: 30px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); border: 1px solid #fff; }
        .timer-box { background: #fff5f5; border: 1.5px solid #feb2b2; border-radius: 20px; padding: 15px; text-align: center; margin-bottom: 25px; }
        .timer-val { font-family: 'Inter', sans-serif; font-size: 28px; color: #c53030; font-weight: 800; letter-spacing: -1px; }

        .price-display { text-align: center; margin-bottom: 30px; padding: 20px; background: #f0f7ff; border-radius: 20px; }
        .current-price { font-family: 'Cinzel', serif; font-size: 42px; color: var(--accent); font-weight: 700; margin-top: 5px; }

        .bid-input-wrapper { position: relative; margin-bottom: 20px; }
        .bid-input-wrapper span { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); font-weight: bold; color: #4a5568; }
        .bid-input { width: 100%; padding: 18px 20px 18px 50px; border-radius: 18px; border: 2px solid #e2e8f0; font-size: 22px; font-weight: 600; box-sizing: border-box; transition: 0.3s; }
        .bid-input:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 4px rgba(25, 118, 210, 0.1); }

        .btn-bid { background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 100%); color: #fff; width: 80%; padding: 10px; border-radius: 18px; font-family: 'Cinzel', serif; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: 0.4s; display: flex; align-items: center; justify-content: center; gap: 12px; margin: 0 auto;}
        .btn-bid:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(13,27,42,0.3); }

        .farm-badge { background: #e3f2fd; color: #1976d2; padding: 8px 16px; border-radius: 30px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 15px; }
        .history-tag { padding: 4px 10px; border-radius: 6px; background: #ebf8ff; color: #2b6cb0; font-weight: 800; font-size: 10px; text-transform: uppercase; }

        @media (max-width: 1100px) {
            .dashboard-container { grid-template-columns: 1fr; }
            .breadcrumb-container { grid-column: 1; }
            .thumb-rail { flex-direction: row; order: 2; grid-column: 1; }
            .main-content { grid-column: 1; order: 3; }
            .bid-sidebar { order: 1; position: static; grid-column: 1; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="breadcrumb-container">
        <ul class="breadcrumb">
            <li><a href="customer_dashboard.php"><i class="fas fa-home"></i> Marketplace</a></li>
            <li><a href="auction_market2.php">Auctions Market</a></li>
            <li class="current"><?= htmlspecialchars($livestock['name']) ?></li>
        </ul>
    </div>

    <div class="thumb-rail">
        <?php foreach ($images as $index => $img): $imgSrc = "../farmer/uploads/" . trim($img); ?>
            <div class="thumb-box <?= $index === 0 ? 'active' : '' ?>" onclick="swapImage('<?= $imgSrc ?>', this)">
                <img src="<?= $imgSrc ?>" alt="Livestock View">
            </div>
        <?php endforeach; ?>
    </div>

    <div class="main-content">
        <div class="hero-section">
            <div class="farm-badge">
                <i class="fas fa-award"></i> Verified Farm: <?= htmlspecialchars($livestock['farm_name']) ?>
            </div>
            <h2 style="font-family:'Cinzel'; margin: 0; color: var(--dark);"><?= htmlspecialchars($livestock['name']) ?></h2>
<!--             <p style="color: #718096; margin: 8px 0 25px 0; font-size: 15px;">
                <i class="fas fa-map-marker-alt" style="color: #e53e3e;"></i> <?= htmlspecialchars($livestock['address']) ?>
            </p> -->

            <div class="hero-display">
                <img id="mainHeroImg" src="../farmer/uploads/<?= trim($images[0]) ?>">
            </div>

            <div class="specs-grid">
                <div class="spec-card"><label>Weight</label><span><?= $livestock['weight'] ?> KG</span></div>
                <div class="spec-card"><label>Breed</label><span><?= $livestock['breed'] ?></span></div>
                <div class="spec-card"><label>Age</label><span><?= $livestock['age'] ?> Mo</span></div>
                <div class="spec-card"><label>Gender</label><span><?= $livestock['gender'] ?></span></div>
            </div>
        </div>

        <div class="details-layout">
            <!-- Health Records -->
            <div class="info-panel">
                <h4><i class="fas fa-notes-medical"></i> Health Records</h4>
                <table class="data-table">
                    <thead><tr><th>Metric</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if($healthRecords): foreach($healthRecords as $h): ?>
                            <tr><td>Vaccination</td><td style="font-weight:600;"><?= $h['vaccination'] ?: 'N/A' ?></td></tr>
                            <tr><td>Medication</td><td style="font-weight:600;"><?= $h['medicine'] ?: 'None Reported' ?></td></tr>
                            <tr><td>Vitamin</td><td style="font-weight:600;"><?= $h['vitamin'] ?: 'N/A' ?></td></tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="2" style="text-align:center; color:#a0aec0;">No records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Services -->
            <div class="info-panel">
                <h4><i class="fas fa-truck-loading"></i> Available Services</h4>
                <table class="data-table">
                    <thead><tr><th>Service</th><th>Fee</th></tr></thead>
                    <tbody>
                        <?php if($services): foreach($services as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['servicetype']) ?></td>
                                <td style="color: var(--accent); font-weight:bold;">RM <?= number_format($s['servicefee'], 2) ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="2" style="text-align:center; color:#a0aec0;">No additional services.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bidding History -->
            <div class="info-panel full-width-panel">
                <h4><i class="fas fa-history"></i> Recent Bidding Activity</h4>
                <table class="data-table">
                    <thead>
                        <tr><th>Bidder Name</th><th>Amount</th><th>Time</th><th>Status</th></tr>
                    </thead>
                    <tbody id="bidTableBody">
                        <?php if($bidHistory): foreach($bidHistory as $index => $bid): ?>
                            <tr class="bid-row" data-bid-id="<?= $bid['bid_id'] ?>">
                                <td style="font-weight: 600;"><?= htmlspecialchars($bid['name']) ?></td>
                                <td class="bid-amount" style="font-weight: 700; color: var(--accent); font-size: 16px;">
                                    RM <?= number_format($bid['current_bid'], 2) ?>
                                </td>
                                <td style="color: #718096;"><?= date('h:i:s A', strtotime($bid['created_at'])) ?></td>
                                <td class="bid-status"> 
                                    <?= $index === 0 ? '<span class="history-tag">Highest Bidder</span>' : '<span class="outbid-tag" style="color:#cbd5e0; font-size:10px;">OUTBID</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                        <tr id="emptyBidRow"><td colspan="4" style="text-align:center; padding: 30px; color:#a0aec0;">Be the first to place a bid!</td></tr>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bid Sidebar -->
    <div class="bid-sidebar">
        <div class="sticky-card">
            <div class="timer-box">
                <label style="font-size: 11px; font-weight: 800; color: #e53e3e; text-transform: uppercase; letter-spacing: 1px;">Auction Ends In</label>
                <div class="timer-val" id="countdown">00:00:00</div>
            </div>

            <div class="price-display">
                <div style="font-size: 12px; font-weight: 700; color: #718096; text-transform: uppercase;">Current Bid</div>
                <div class="current-price">RM <span id="currentPrice"><?= number_format($current_bid, 2) ?></span></div>
            </div>

            <div class="bid-action-box">
                <?php if ($auction_closed): ?>
                    <?php if ($is_winner): ?>
                        <div style="background: #fff9eb; border: 2px solid #fbd38d; padding: 20px; border-radius: 18px; text-align: center;">
                            <p style="color: #975a16; font-weight: 700; font-size: 1.1rem; margin-bottom: 5px;">
                                🏆 Congratulations! You are the winner!
                            </p>

                            <?php if (!$is_approved): ?>
                                <div style="padding: 10px; background: rgba(251, 211, 141, 0.3); border-radius: 10px;">
                                    <i class="fas fa-hourglass-half"></i> 
                                    <span style="font-size: 14px; color: #744210;">Waiting for farmer's approval to proceed with payment.</span>
                                </div>
                            <?php else: ?>
                                <p style="color: #2f855a; font-size: 13px; margin-bottom: 15px;">Farmer has approved your bid. You may now proceed to checkout.</p>
                                <a href="pay_balance.php?auction_id=<?= $auction_id ?>" class="btn-bid" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); text-decoration: none;">
                                    PROCEED TO PAYMENT <i class="fas fa-credit-card"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <button type="button" class="btn-bid" disabled style="opacity: 0.5; cursor: not-allowed; background: #666;">
                            AUCTION CLOSED
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bid-input-wrapper">
                        <span>RM</span>
                        <input type="number" id="bid_amount" class="bid-input" value="<?= $current_bid + 50 ?>" step="10">
                    </div>
                    <button type="button" id="placeBidBtn" class="btn-bid" data-auction-id="<?= $auction_id ?>">
                        PLACE BID <i class="fas fa-gavel"></i>
                    </button>
                <?php endif; ?>
            </div>

            <div class="mini-history-container" style="margin-top: 25px; border-top: 1px solid #edf2f7; padding-top: 20px;">
                <h5 style="font-family:'Cinzel'; font-size: 0.8rem; margin: 0 0 15px 0; color: var(--dark); display: flex; justify-content: space-between;">
                    <span>Live Activity</span>
                    <span style="color: #48bb78; font-size: 9px; text-transform: uppercase;"><i class="fas fa-circle" style="font-size: 7px; vertical-align: middle;"></i> Live</span>
                </h5>
                <ul class="mini-bid-list" id="miniBidList" style="list-style: none; padding: 0; margin: 0;">
                    <?php if($bidHistory): foreach(array_slice($bidHistory, 0, 3) as $index => $bid): ?>
                        <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f8fafc;">
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: #2d3436;"><?= htmlspecialchars($bid['name']) ?></div>
                                <div style="font-size: 10px; color: #a0aec0;"><?= date('h:i A', strtotime($bid['created_at'])) ?></div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 13px; font-weight: 700; color: <?= $index === 0 ? '#1976d2' : '#718096' ?>;">
                                    RM <?= number_format($bid['current_bid'], 0) ?>
                                </div>
                                <?php if($index === 0): ?>
                                    <span style="font-size: 8px; background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 4px; font-weight: 800;">TOP</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; else: ?>
                        <li id="emptyMiniBid" style="font-size: 12px; color: #a0aec0; text-align: center; padding: 10px;">No bids yet.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    console.log("Auction Script Initializing...");

    if (typeof pusher === 'undefined') {
        window.pusher = new Pusher('<?= $pusher_app_key ?>', { 
            cluster: '<?= $pusher_cluster ?>',
            forceTLS: true 
        });
    }

    if (typeof window.channel !== 'undefined') {
        pusher.unsubscribe(window.channel.name); 
    }
    window.channel = pusher.subscribe('auction-<?= $auction_id ?>');

    window.channel.bind('new-bid', function(data) {
        console.log("Real-time bid received:", data);

        const amount = parseFloat(data.current_bid);
        const formatted = amount.toLocaleString('en-MY', { minimumFractionDigits: 2 });
        const formattedShort = amount.toLocaleString('en-MY', { minimumFractionDigits: 0 });

        const priceDisplay = document.getElementById('currentPrice');
        if (priceDisplay) priceDisplay.innerText = formatted;

        const inputField = document.getElementById('bid_amount');
        if (inputField) inputField.value = (amount + 50).toFixed(2);

        document.querySelectorAll('#bidTableBody .bid-status').forEach(statusCell => {
            statusCell.innerHTML = '<span class="outbid-tag" style="color:#cbd5e0; font-size:10px;">OUTBID</span>';
        });

        const tableBody = document.getElementById('bidTableBody');
        if (document.getElementById('emptyBidRow')) document.getElementById('emptyBidRow').remove();

        const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const timeNowFull = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        const newRow = `
        <tr class="bid-row">
            <td style="font-weight: 600;">${data.bidder_name}</td>
            <td style="font-weight: 700; color: #1976d2; font-size: 16px;">RM ${formatted}</td>
            <td style="color: #718096;">${timeNowFull}</td>
            <td class="bid-status"><span class="history-tag">Highest Bidder</span></td>
        </tr>`;
        tableBody.insertAdjacentHTML('afterbegin', newRow);

        const miniList = document.getElementById('miniBidList');
        if (document.getElementById('emptyMiniBid')) document.getElementById('emptyMiniBid').remove();

        miniList.querySelectorAll('span').forEach(span => {
            if(span.innerText === 'TOP') span.remove();
        });

        const newMiniItem = `
        <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f8fafc;">
            <div>
                <div style="font-size: 13px; font-weight: 600; color: #2d3436;">${data.bidder_name}</div>
                <div style="font-size: 10px; color: #a0aec0;">${timeNow}</div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 13px; font-weight: 700; color: #1976d2;">RM ${formattedShort}</div>
                <span style="font-size: 8px; background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 4px; font-weight: 800;">TOP</span>
            </div>
        </li>`;

        miniList.insertAdjacentHTML('afterbegin', newMiniItem);

        while (miniList.children.length > 5) {
            miniList.lastElementChild.remove();
        }
    });

    const bidBtn = document.getElementById('placeBidBtn');
    if (bidBtn) {
        bidBtn.addEventListener('click', function(e) {
            e.preventDefault(); 
            
            const bidInput = document.getElementById('bid_amount');
            const bidVal = bidInput.value;
            const auctionId = this.getAttribute('data-auction-id');

            const currentPriceText = document.getElementById('currentPrice').innerText;
            const currentPrice = parseFloat(currentPriceText.replace(/[^\d.-]/g, ''));

            if (parseFloat(bidVal) <= currentPrice) {
                alert("Your bid must be higher than RM " + currentPrice.toLocaleString());
                return;
            }

            bidBtn.disabled = true;
            bidBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SENDING...';

            fetch('place_bid.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `auction_id=${auctionId}&bid_amount=${bidVal}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success') {
                    alert("Bid Failed: " + data.message);
                }
            })
            .catch(err => {
                console.error("Fetch error:", err);
                alert("Could not connect to server.");
            })
            .finally(() => {
                bidBtn.disabled = false;
                bidBtn.innerHTML = 'PLACE BID <i class="fas fa-gavel"></i>';
            });
        });
    }

    // Countdown Timer logic
    const endTime = <?= strtotime($end_time) ?> * 1000;
    const timer = setInterval(function() {
        const now = new Date().getTime();
        const diff = endTime - now;

        const countdownEl = document.getElementById("countdown");
        const bidBtn = document.getElementById("placeBidBtn");

        if (diff <= 0) {
            clearInterval(timer);
            if (countdownEl) countdownEl.innerHTML = "CLOSED";

            fetch('finalize_auction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `auction_id=<?= $auction_id ?>`
            })
            .then(res => res.json())
            .then(data => {
                setTimeout(() => {
                    window.location.reload(); 
                }, 1500); 
            })
            .catch(err => console.error("Error finalizing auction:", err));
            
            if (bidBtn) {
                bidBtn.disabled = true;
                bidBtn.innerHTML = "AUCTION CLOSED";
                bidBtn.style.opacity = "0.5";
                bidBtn.style.cursor = "not-allowed";
                bidBtn.style.background = "#666";
            }
            return;
        }

        const h = Math.floor(diff / 3600000).toString().padStart(2, '0');
        const m = Math.floor((diff % 3600000) / 60000).toString().padStart(2, '0');
        const s = Math.floor((diff % 60000) / 1000).toString().padStart(2, '0');

        if (countdownEl) {
            countdownEl.innerHTML = `${h}h : ${m}m : ${s}s`;
        }
    }, 1000);

    function swapImage(src, el) {
        document.getElementById('mainHeroImg').src = src;
        document.querySelectorAll('.thumb-box').forEach(b => b.classList.remove('active'));
        el.classList.add('active');
    }
</script>

<?php include '../inc/footer.php'; ?>
</body>
</html>
