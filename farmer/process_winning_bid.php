<?php
$stmt = $pdo->prepare("UPDATE bids SET bid_status = :status WHERE bid_id = :id");
$status = ($_GET['action'] == 'accept') ? 'Winning' : 'Rejected';
$stmt->execute(['status' => $status, 'id' => $_GET['id']]);

if ($_GET['action'] == 'accept') {
    $bid = $pdo->prepare("SELECT * FROM bids WHERE bid_id = ?");
    $bid->execute([$_GET['id']]);
    $data = $bid->fetch();

    $insertOrder = $pdo->prepare("INSERT INTO orders (customer_id, livestock_id, total_amount, order_status, date) 
                                 VALUES (?, ?, ?, 'PROCESSING', NOW())");
    $insertOrder->execute([$data['customer_id'], $data['livestock_id'], $data['bid_amount']]);
    
    $_SESSION['msg'] = "Winning bid accepted! Order has been created.";
}

header("Location: view_bids.php?auction_id=" . $data['auction_id']);
?>