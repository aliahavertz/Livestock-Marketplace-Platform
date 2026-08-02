<?php
session_start();
include '../db_connect.php'; 
$farmer_id = $_SESSION['farmer_id'];

date_default_timezone_set('Asia/Kuala_Lumpur');
$now = date('Y-m-d H:i:s');


$expired_query = "UPDATE auction SET status = 'closed' WHERE status = 'active' AND end_time <= ?";
$pdo->prepare($expired_query)->execute([$now]);

$query = "SELECT a.*, l.name as livestock_name 
          FROM auction a 
          JOIN livestock l ON a.livestock_id = l.livestock_id 
          WHERE l.farmer_id = :fid 
          ORDER BY a.start_time DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([':fid' => $farmer_id]);
$auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($auctions as $row): 
    $isExpired = strtotime($row['end_time']) <= strtotime($now); 
?>
    <tr>
        <td><span style="font-weight: bold; color: #777;"><?= $row['auction_id'] ?></span></td>
        <td>
            <strong><?= htmlspecialchars($row['title']) ?></strong><br>
            <small>Livestock: <?= htmlspecialchars($row['livestock_name']) ?></small>
        </td>
        <td>
            <small>Start: <?= date('d M, H:i', strtotime($row['start_time'])) ?></small><br>
            <small style="<?= ($isExpired && $row['status'] === 'closed') ? 'color: #d32f2f; font-weight: bold;' : '' ?>">
                End: <?= date('d M, H:i', strtotime($row['end_time'])) ?>
                <?php if($isExpired && $row['status'] === 'closed'): ?>
                    <br><span style="font-size: 10px;">(Ended)</span>
                <?php endif; ?>
            </small>
        </td>
        <td>
            Start: RM <?= number_format($row['starting_price'], 2) ?><br>
            <strong>Bid: RM <?= number_format($row['current_bid'] ?? 0, 2) ?></strong>
        </td>
        <td>
            <span class="status-badge <?= strtolower($row['status']) ?>"><?= strtoupper($row['status']) ?></span>
        </td>
        <td>
            <div style="display: flex; gap: 5px;">
                <?php if ($row['status'] === 'pending'): ?>
                    <?php if ($isExpired): ?>
                        <button class="btn-action" style="background: #ccc; color: #666; cursor: not-allowed;" title="Edit schedule first.">
                            <i class="fas fa-lock"></i> Start
                        </button>
                    <?php else: ?>
                        <a href="update_auction_status.php?id=<?= $row['auction_id'] ?>&status=active" class="btn-action btn-start"><i class="fas fa-play"></i> Start</a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($row['status'] === 'active'): ?>
                    <a href="farmer_close_auction.php?auction_id=<?= $row['auction_id'] ?>" class="btn-action btn-end"><i class="fas fa-times-circle"></i> Close</a>
                <?php endif; ?>
                
                <a href="farmer_edit_auction.php?id=<?= $row['auction_id'] ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i> Edit</a>
            </div>
        </td>
    </tr>
<?php endforeach; ?>