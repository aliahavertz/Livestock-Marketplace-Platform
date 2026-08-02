<?php
session_start();
include_once '../db_connect.php';
include '../inc/header.php';

$customer_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;

$limit = 6; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$countQuery = $pdo->query("SELECT COUNT(*) FROM auction WHERE status ILIKE 'active'");
$totalAuctions = $countQuery->fetchColumn();
$totalPages = ceil($totalAuctions / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: radial-gradient(circle at top, #fdf6ec, #f4efe6); font-family: 'PT Serif', serif; color: #1a1a1a; }
        
        .breadcrumb-vintage { list-style: none; display: flex; gap: 10px; margin-bottom: 25px; font-size: 14px; padding: 0; }
        .breadcrumb-vintage a { color: #1976d2; text-decoration: none; }
        .breadcrumb-vintage .current { color: #666; }
        .breadcrumb-vintage li:not(:last-child)::after { content: '>'; margin-left: 10px; color: #ccc; }

        .hero-section { height: 90px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; margin-bottom: 40px; border-bottom: 2px solid #000; padding-top: 10px; }
        .hero-section h1 { font-family: 'Cinzel', serif; font-size: 2.5rem; margin: 0; }
        
        .list-wrapper { max-width: 1400px; margin: auto; padding: 0 20px 60px; }
        .auction-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(480px, 1fr)); gap: 30px; }

        .auction-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(14px);
            border-radius: 25px;
            border: 1px solid rgba(144, 202, 249, 0.4);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: 0.3s ease;
            position: relative;
        }

        .status-tag {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 11;
            background: #4caf50;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .card-img-container { height: 300px; position: relative; overflow: hidden; background: #000; }
        .slider-wrapper { display: flex; transition: transform 0.4s ease-in-out; height: 100%; }
        .slider-wrapper img { width: 100%; height: 100%; object-fit: cover; flex-shrink: 0; }
        
        .slider-btn { 
            position: absolute; top: 50%; transform: translateY(-50%); 
            background: rgba(0,0,0,0.5); color: white; border: none; 
            padding: 10px; cursor: pointer; z-index: 10; border-radius: 50%; width: 35px; height: 35px;
            display: flex; align-items: center; justify-content: center;
        }
        .slider-btn:hover { background: rgba(0,0,0,0.8); }
        .prev-btn { left: 10px; }
        .next-btn { right: 10px; }

        .card-content { padding: 25px; }
        .animal-title { font-family: 'Cinzel', serif; font-size: 1.8rem; color: #0d1b2a; margin-bottom: 15px; }

        .specs-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
        .spec-box { background: #fff; padding: 10px; border-radius: 12px; text-align: center; border: 1px solid rgba(0,0,0,0.05); }
        .spec-box label { display: block; font-size: 9px; text-transform: uppercase; color: #888; font-weight: bold; }
        .spec-box span { font-size: 13px; font-weight: bold; color: #0d1b2a; }

        .seller-detail-box { background: rgba(144, 202, 249, 0.1); border-left: 4px solid #90caf9; padding: 15px; border-radius: 0 15px 15px 0; margin-bottom: 20px; font-size: 13px; }
        .seller-detail-box strong { font-family: 'Cinzel', serif; display: block; margin-bottom: 5px; color: #0d1b2a; }

        .section-label { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #666; margin-bottom: 8px; display: block; border-bottom: 1px solid #eee; padding-bottom: 4px;}
        .scroll-section { background: rgba(255,255,255,0.4); border-radius: 10px; padding: 10px; margin-bottom: 15px; max-height: 100px; overflow-y: auto; }
        
        .mini-table { width: 100%; font-size: 12px; border-collapse: collapse; }
        .mini-table th { text-align: left; color: #90caf9; font-size: 9px; text-transform: uppercase; padding-bottom: 4px; }
        .mini-table td { padding: 4px 0; border-bottom: 1px solid rgba(0,0,0,0.03); }

        .bid-footer { margin-top: auto; padding-top: 20px; border-top: 1px dashed #ccc; display: flex; justify-content: space-between; align-items: center; }
        .price-text { font-family: 'Cinzel', serif; font-size: 1.6rem; color: #1976d2; font-weight: bold; }
        
        .action-btn { background: linear-gradient(135deg, #90caf9, #64b5f6); color: #0d1b2a; padding: 14px 30px; border-radius: 30px; font-family: 'Cinzel', serif; font-weight: bold; text-decoration: none; transition: 0.3s; }
        .action-btn.locked { background: linear-gradient(135deg, #ffb74d, #f57c00); }

        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 40px; }
        .pagination a { padding: 10px 18px; border-radius: 8px; background: #fff; color: #0d1b2a; text-decoration: none; border: 1px solid #90caf9; font-family: 'Cinzel', serif; font-weight: bold; }
        .pagination a.active { background: #0d1b2a; color: #fff; border-color: #0d1b2a; }
        .pagination a:hover:not(.active) { background: #90caf9; color: #fff; }
    </style>
</head>
<body>

<div class="hero-section">
    <h1>Live Auctions</h1>
</div>

<div class="list-wrapper">
    <ul class="breadcrumb-vintage">
        <li><a href="customer_dashboard.php"><i class="fas fa-home"></i> Marketplace</a></li>
        <li class="current">Live Auctions</li>
    </ul>

    <div class="auction-grid">
        <?php
        $sql = "SELECT l.*, a.auction_id, a.current_bid, a.status as auction_status, f.farm_name, f.name as owner_name, f.phone_number, f.email, f.address 
        FROM livestock l 
        JOIN auction a ON l.livestock_id = a.livestock_id 
        JOIN farmer f ON l.farmer_id = f.farmer_id
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
                <a href="customer_dashboard.php" class="action-btn" style="display: inline-block; margin-top: 20px;">
                    <i class="fas fa-shopping-basket"></i> Browse Marketplace
                </a>
            </div>
        <?php else: 
            foreach ($auctions as $row): 
                $images = !empty($row['image']) ? explode(',', $row['image']) : ['../assets/no-image.png'];
                $l_id = $row['livestock_id'];
                $a_id = $row['auction_id'];

                $h_stmt = $pdo->prepare("SELECT vaccination, medicine, vitamin FROM health WHERE livestockid = ?");
                $h_stmt->execute([$l_id]);
                $healthRecords = $h_stmt->fetchAll(PDO::FETCH_ASSOC);

                $join_stmt = $pdo->prepare("SELECT COUNT(*) FROM auction_deposits_paid WHERE auction_id = ? AND status = 'paid'");
                $join_stmt->execute([$a_id]);
                $participantCount = $join_stmt->fetchColumn();

                $hasPaid = false;
                if ($customer_id) {
                    $p_stmt = $pdo->prepare("SELECT 1 FROM auction_deposits_paid WHERE customer_id = ? AND auction_id = ? AND status = 'paid'");
                    $p_stmt->execute([$customer_id, $a_id]);
                    $hasPaid = (bool)$p_stmt->fetch();
                }
                ?>
            <div class="auction-card">
                <div class="status-tag">
                    <i class="fas fa-circle" style="font-size: 8px; margin-right: 5px; vertical-align: middle;"></i> 
                    <?= strtoupper(htmlspecialchars($row['auction_status'])) ?>
                </div>

                <div class="card-img-container" id="slider-<?= $a_id ?>">
                    <div class="slider-wrapper">
                        <?php foreach ($images as $img): 
                            $imgTrim = trim($img);
                            $imgSrc = (strpos($imgTrim, '../') === false) ? "../farmer/uploads/" . $imgTrim : $imgTrim;
                        ?>
                            <img src="<?= $imgSrc ?>" alt="Livestock">
                        <?php endforeach; ?>
                    </div>
                    <?php if(count($images) > 1): ?>
                        <button class="slider-btn prev-btn" onclick="moveCardSlider(<?= $a_id ?>, -1)"><i class="fas fa-chevron-left"></i></button>
                        <button class="slider-btn next-btn" onclick="moveCardSlider(<?= $a_id ?>, 1)"><i class="fas fa-chevron-right"></i></button>
                    <?php endif; ?>
                </div>
                
                <div class="card-content">
                    <h3 class="animal-title"><?= htmlspecialchars($row['name']) ?></h3>
                    
                    <div class="specs-grid">
                        <div class="spec-box"><label>Breed</label><span><?= $row['breed'] ?></span></div>
                        <div class="spec-box"><label>Weight</label><span><?= $row['weight'] ?>kg</span></div>
                        <div class="spec-box"><label>Age</label><span><?= $row['age'] ?>mo</span></div>
                        <div class="spec-box"><label>Gender</label><span><?= $row['gender'] ?></span></div>
                    </div>

                    <div class="seller-detail-box">
                        <strong><i class="fas fa-certificate"></i> <?= htmlspecialchars($row['farm_name']) ?></strong>
                        <div><i class="fas fa-user-tie"></i> Owner: <?= htmlspecialchars($row['owner_name']) ?></div>
                        <div style="font-size: 11px; margin-top: 4px; color: #666;"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($row['address']) ?></div>
                    </div>

                    <span class="section-label"><i class="fas fa-file-medical"></i> Health Status</span>
                    <div class="scroll-section">
                        <table class="mini-table">
                            <thead><tr><th>Vaccine</th><th>Medicine</th><th>Vitamin</th></tr></thead>
                            <tbody>
                                <?php if($healthRecords): foreach($healthRecords as $h): ?>
                                    <tr><td><?= $h['vaccination'] ?: '-' ?></td><td><?= $h['medicine'] ?: '-' ?></td><td><?= $h['vitamin'] ?: '-' ?></td></tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="3">No records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="bid-footer">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <small style="font-size: 10px; color: #666; text-transform: uppercase;">Current Bid</small>
                                <span style="font-size: 10px; background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 4px; font-weight: bold;">
                                    <i class="fas fa-users"></i> <?= $participantCount ?> Joined
                                </span>
                            </div>
                            <div class="price-text">RM <?= number_format($row['current_bid'], 2) ?></div>
                        </div>
                        <a href="<?= $hasPaid ? "Join_Auction.php?livestock_id=$l_id" : "pay_deposit.php?auction_id=$a_id&livestock_id=$l_id" ?>" 
                           class="action-btn <?= !$hasPaid ? 'locked' : '' ?>">
                            <?= $hasPaid ? '<i class="fas fa-gavel"></i> JOIN BID' : '<i class="fas fa-lock"></i> PAY DEPOSIT' ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>" class="<?= ($page == $i) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>

<script>
const sliderStates = {};
function moveCardSlider(id, direction) {
    if (!sliderStates[id]) sliderStates[id] = 0;
    const container = document.getElementById('slider-' + id);
    const wrapper = container.querySelector('.slider-wrapper');
    const totalImages = wrapper.querySelectorAll('img').length;
    sliderStates[id] += direction;
    if (sliderStates[id] >= totalImages) sliderStates[id] = 0;
    if (sliderStates[id] < 0) sliderStates[id] = totalImages - 1;
    wrapper.style.transform = `translateX(-${sliderStates[id] * 100}%)`;
}
</script>

<?php include '../inc/footer.php'; ?>
</body>
</html>