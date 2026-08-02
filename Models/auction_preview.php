<?php
session_start();
include '../db_connect.php';

$isLoggedIn = isset($_SESSION['customer_id']);
$customer_id = $isLoggedIn ? $_SESSION['customer_id'] : null;

$current_url = "";
if (!$isLoggedIn) {
    $current_url = "Models/auction_preview.php" . (isset($_GET['livestock_id']) ? "?livestock_id=" . $_GET['livestock_id'] : "");
}

include '../inc/header.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

$target_livestock = $_GET['livestock_id'] ?? null;

$query = "SELECT 
            a.auction_id, a.current_bid, a.last_bidder_id, a.status, a.end_time,
            EXTRACT(EPOCH FROM a.end_time) as end_time_unix,
            l.livestock_id, l.name, l.breed, l.image, l.price as starting_price,
            COALESCE(ad.amount, 0) as deposit_fee,
            (SELECT COUNT(*) FROM auction_deposits_paid 
             WHERE auction_id = a.auction_id AND customer_id = :cid AND status = 'paid') as has_paid
          FROM auction a
          JOIN livestock l ON a.livestock_id = l.livestock_id 
          LEFT JOIN auction_deposits ad ON a.auction_id = ad.auction_id
          WHERE a.status ILIKE 'active'";

if ($target_livestock) {
    $query .= " AND l.livestock_id = :lid";
}

$query .= " GROUP BY a.auction_id, l.livestock_id, ad.amount ORDER BY a.end_time ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':cid', $customer_id, PDO::PARAM_INT);
    if ($target_livestock) {
        $stmt->bindValue(':lid', $target_livestock, PDO::PARAM_INT);
    }
    $stmt->execute();
    $auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bid_histories = [];
    foreach ($auctions as $auc) {
        $h_stmt = $pdo->prepare("SELECT b.current_bid, c.name 
                                 FROM bidding b 
                                 JOIN customer c ON b.customer_id = c.customer_id 
                                 WHERE b.livestock_id = ? 
                                 ORDER BY b.bid_id DESC LIMIT 10");
        $h_stmt->execute([$auc['livestock_id']]);
        $bid_histories[$auc['auction_id']] = $h_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Query Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Auction Floor | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --primary-gradient: linear-gradient(135deg, #90caf9, #64b5f6);
            --vintage-bg: radial-gradient(circle at top, #fdf6ec, #f4efe6);
            --dark-blue: #0d1b2a;
            --auction-orange: #ff9800;
        }

        body { background: var(--vintage-bg); font-family: 'PT Serif', serif; color: #1a1a1a; margin: 0; }
        .container { max-width: 1100px; margin: auto; padding: 40px 20px; min-height: 80vh; }
        
        .preview-banner {
            background: var(--dark-blue); color: #90caf9; padding: 15px;
            text-align: center; border-radius: 12px; margin-bottom: 30px;
            font-family: 'Cinzel', serif; border: 1px solid #90caf9;
        }

        .auction-card { 
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
            border-radius: 24px; display: flex; border: 1px solid rgba(144, 202, 249, 0.4);
            overflow: hidden; margin-bottom: 40px; height: 620px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
        }

        .card-main { flex: 1.4; padding: 30px; border-right: 1px solid rgba(0,0,0,0.05); overflow-y: auto; }
        .card-sidebar { flex: 1; background: rgba(144, 202, 249, 0.05); display: flex; flex-direction: column; }
        
        .timer { background: var(--dark-blue); color: #90caf9; padding: 8px 18px; border-radius: 50px; font-family: 'Cinzel'; font-size: 0.9rem; font-weight: bold; }
        .price-val { font-family: 'Cinzel'; font-size: 2.8rem; color: #1976d2; margin: 5px 0 20px 0; font-weight: 700; }
        
        .img-box { width: 100%; height: 280px; border-radius: 15px; overflow: hidden; margin-bottom: 20px; background: #eee; }
        .img-box img { width: 100%; height: 100%; object-fit: cover; }

        .btn-action { 
            width: 100%; padding: 18px; border-radius: 40px; border: none; 
            font-family: 'Cinzel'; font-weight: bold; cursor: pointer; 
            text-decoration: none; display: inline-block; text-align: center; transition: 0.3s;
        }
        
        .btn-bid { background: var(--primary-gradient); color: var(--dark-blue); }
        .btn-login { background: var(--auction-orange); color: white; }
        .btn-deposit { background: #4caf50; color: white; }

        .empty-state { text-align: center; padding: 100px 20px; color: #666; }
        .empty-state i { font-size: 60px; color: #cbd5e0; margin-bottom: 20px; }

        @keyframes highlight { 0% { background: #fff; } 50% { background: #e3f2fd; } 100% { background: #fff; } }
    </style>
</head>
<body>

<div class="container">
    <?php if (!$isLoggedIn): ?>
        <div class="preview-banner">
            <i class="fas fa-gavel"></i> LIVE PREVIEW MODE: Bids update in real-time. Please login to participate.
        </div>
    <?php endif; ?>

    <h1 style="text-align:center; font-family:'Cinzel'; margin-bottom:40px;">Live Auction Floor</h1>

    <?php if (empty($auctions)): ?>
        <div class="empty-state">
            <i class="fas fa-search"></i>
            <h3>No Active Auctions Found</h3>
            <p>We couldn't find an active auction for this animal. It may have ended or hasn't started yet.</p>
            <a href="index.php" style="color: #1976d2; font-weight:bold;">Go Back to Marketplace</a>
        </div>
    <?php else: ?>
        <?php foreach ($auctions as $auction): 
            $aid = $auction['auction_id'];
            $displayPrice = ($auction['current_bid'] > 0) ? $auction['current_bid'] : $auction['starting_price'];
            $hasPaid = ($auction['has_paid'] > 0);
            $img = !empty($auction['image']) ? explode(',', $auction['image'])[0] : 'no-image.png';
        ?>
        
        <div class="auction-card">
            <div class="card-main">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h2 style="font-family:'Cinzel';"><?= htmlspecialchars($auction['name']) ?></h2>
                    <span class="timer" id="timer-<?= $aid ?>">LOADING...</span>
                </div>
                
                <div class="img-box">
                    <img src="../farmer/uploads/<?= htmlspecialchars($img) ?>" onerror="this.src='../assets/no-image.png'">
                </div>

                <p style="font-size: 0.8rem; color: #666; text-transform: uppercase;">Current Bid</p>
                <div class="price-val">RM <span id="price-<?= $aid ?>"><?= number_format($displayPrice, 2) ?></span></div>

                <?php if (!$isLoggedIn): ?>
                    <a href="customer_login.php" class="btn-action btn-login">Login to Place Bid</a>
                <?php elseif (!$hasPaid): ?>
                    <a href="pay_deposit.php?auction_id=<?= $aid ?>" class="btn-action btn-deposit">
                        Pay RM <?= number_format($auction['deposit_fee'], 2) ?> Deposit
                    </a>
                <?php else: ?>
                    <form id="form-<?= $aid ?>">
                        <input type="number" name="bid_amount" id="input-<?= $aid ?>" 
                               style="width:100%; padding:15px; margin-bottom:10px; border-radius:10px; border:1px solid #ddd;" 
                               value="<?= $displayPrice + 10 ?>">
                        <button type="submit" class="btn-action btn-bid">Place High Bid</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card-sidebar">
                <div style="padding:20px; border-bottom:1px solid #ddd; font-family:'Cinzel';">Live Activity</div>
                <div class="feed-container" id="feed-<?= $aid ?>">
                    <?php foreach ($bid_histories[$aid] as $history): ?>
                        <div class="feed-item">
                            <span class="feed-price">RM <?= number_format($history['current_bid'], 2) ?></span>
                            <strong><?= htmlspecialchars($history['name']) ?></strong><br>
                            <small style="color: #999;">Verified Bid</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
<script>
    const pusherObj = new Pusher('YOUR_PUSHER_KEY', { cluster: 'ap1' });

    <?php foreach ($auctions as $auction): ?>
    (function(aid) {
        const endTime = <?= $auction['end_time_unix'] ?> * 1000;
        setInterval(() => {
            const diff = endTime - new Date().getTime();
            const timerElem = document.getElementById("timer-" + aid);
            if (diff <= 0) { timerElem.innerText = "ENDED"; return; }
            const h = Math.floor(diff/3600000);
            const m = Math.floor((diff%3600000)/60000);
            const s = Math.floor((diff%60000)/1000);
            timerElem.innerText = `${h}:${m}:${s}`;
        }, 1000);

        const channel = pusherObj.subscribe('auction-' + aid);
        channel.bind('new-bid', function(data) {
            document.getElementById('price-' + aid).innerText = parseFloat(data.current_bid).toFixed(2);
            const feed = document.getElementById('feed-' + aid);
            const html = `<div class="feed-item" style="background:#e3f2fd;">
                            <span class="feed-price">RM ${parseFloat(data.current_bid).toFixed(2)}</span>
                            <strong>${data.bidder_name}</strong><br><small>Just now</small>
                          </div>`;
            feed.insertAdjacentHTML('afterbegin', html);
        });
    })(<?= $auction['auction_id'] ?>);
    <?php endforeach; ?>
</script>
</body>
</html>