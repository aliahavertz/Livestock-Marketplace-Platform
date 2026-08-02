<?php
session_start();
include '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = $_POST['livestock_name'] ?? '';
    $breed          = $_POST['breed'] ?? '';
    $category       = $_POST['category'] ?? '';
    $age            = $_POST['age'] ?? 0;
    $gender         = $_POST['gender'] ?? '';
    $weight         = $_POST['weight'] ?? 0;
    $price          = $_POST['price'] ?? 0;
    $sale_type      = $_POST['sale_type'] ?? 'Direct';
    $status         = $_POST['availability_status'] ?? 'Pending';
    $description    = $_POST['description'] ?? '';
    $farmerID       = $_POST['farmer_id'] ?? 0;

    $start_time     = ($sale_type === 'Auction') ? $_POST['auction_start_time'] : null;
    $end_time       = ($sale_type === 'Auction') ? $_POST['auction_end_time'] : null;
    $deposit_amount = ($sale_type === 'Auction') ? ($_POST['deposit_amount'] ?? 0) : 0;

    try {
        $pdo->beginTransaction();

        $stmtSeq = $pdo->prepare("SELECT MAX(farmer_livestock_no) FROM livestock WHERE farmer_id = ?");
        $stmtSeq->execute([$farmerID]);
        $last_no = $stmtSeq->fetchColumn();

        $new_display_id = ($last_no) ? $last_no + 1 : 1; 

        $sql = "INSERT INTO livestock 
                (name, breed, category, age, gender, weight, price, availability_status, description, farmer_id, sale_type, farmer_livestock_no) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $name, $breed, $category, $age, $gender, $weight, $price, 
            $status, $description, $farmerID, $sale_type, $new_display_id
        ]);
        
        $livestockID = $pdo->lastInsertId();

        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $uploadDir = __DIR__ . "/uploads/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $files = $_FILES['images'];
            $uploadedNames = []; 

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$i];
                    $originalName = basename($files['name'][$i]);
                    $newImgName = time() . "_" . $i . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "_", $originalName);

                    if (move_uploaded_file($tmpName, $uploadDir . $newImgName)) {
                        $imgSql = "INSERT INTO livestock_images (livestock_id, image_name) VALUES (?, ?)";
                        $pdo->prepare($imgSql)->execute([$livestockID, $newImgName]);

                        $uploadedNames[] = $newImgName;
                    }
                }
            }

            if (!empty($uploadedNames)) {
                $commaString = implode(',', $uploadedNames);
                $updateMainImg = "UPDATE livestock SET image = ? WHERE livestock_id = ?";
                $pdo->prepare($updateMainImg)->execute([$commaString, $livestockID]);
            }
        }

        if ($sale_type === 'Auction') {
            $auctionSQL = "INSERT INTO auction 
                           (livestock_id, start_time, end_time, starting_price, current_bid, status, title) 
                           VALUES (?, ?, ?, ?, ?, 'active', ?)";
            
            $auction_title = "Auction for " . $name;
            $stmtAuction = $pdo->prepare($auctionSQL);
            $stmtAuction->execute([
                $livestockID, 
                $start_time, 
                $end_time, 
                $price, 
                $price, 
                $auction_title
            ]);

            $auctionID = $pdo->lastInsertId();
            $depositSQL = "INSERT INTO auction_deposits (auction_id, amount) VALUES (?, ?)";
            $pdo->prepare($depositSQL)->execute([$auctionID, $deposit_amount]);
        }

        if (!empty($_POST['vaccination']) || !empty($_POST['medicine']) || !empty($_POST['vitamin'])) {
            $healthSQL = "INSERT INTO health (livestockid, vaccination, medicine, vitamin) VALUES (?, ?, ?, ?)";
            $pdo->prepare($healthSQL)->execute([$livestockID, $_POST['vaccination'], $_POST['medicine'], $_POST['vitamin']]);
        }

        if (isset($_POST['provideService']) && isset($_POST['serviceType']) && is_array($_POST['serviceType'])) {

            $serviceSQL = "INSERT INTO harvestservice (farmerid, livestockid, servicetype, servicefee) VALUES (?, ?, ?, ?)";
            $stmtService = $pdo->prepare($serviceSQL);

            foreach ($_POST['serviceType'] as $index => $type) {
                $fee = $_POST['serviceFee'][$index] ?? 0;

                if (!empty(trim($type))) {
                    $stmtService->execute([
                        $farmerID, 
                        $livestockID, 
                        htmlspecialchars($type), 
                        (float)$fee
                    ]);
                }
            }
        }

        if (isset($_POST['delivery_type']) && is_array($_POST['delivery_type'])) {
            $pdo->prepare("DELETE FROM livestock_delivery_options WHERE livestock_id = ?")->execute([$livestockID]);

            $stmtDeliv = $pdo->prepare("INSERT INTO livestock_delivery_options (livestock_id, method_name, delivery_fee, max_capacity) VALUES (?, ?, ?, ?)");

            foreach ($_POST['delivery_type'] as $index => $methodName) {
                $methodFee = $_POST['delivery_fee'][$index] ?? 0;
                $maxCapacity = $_POST['max_weight'][$index]; 

                if (!empty(trim($methodName))) {
                    $stmtDeliv->execute([
                        $livestockID,
                        htmlspecialchars($methodName),
                        (float)$methodFee,
                        (float)$maxCapacity
                    ]);
                }
            }
        }

        if ($status === 'Pending') {
            $notifSQL = "INSERT INTO notifications (user_id, user_type, title, message, is_read) VALUES (1, 'admin', 'Verification Required', ?, FALSE)";
            $notifMsg = "New listing '$name' from Farmer #$farmerID requires approval before publishing.";
            $pdo->prepare($notifSQL)->execute([$notifMsg]);
            
            $redirect_msg = "pending_approval";
        } else {
            $notifSQL = "INSERT INTO notifications (user_id, user_type, title, message, is_read) VALUES (1, 'admin', 'New Livestock Listed', ?, FALSE)";
            $notifMsg = "Farmer #$farmerID has listed a new animal: '$name' (Auto-Approved).";
            $pdo->prepare($notifSQL)->execute([$notifMsg]);

            $redirect_msg = "success";
        }

        $pdo->commit();
        
        header("Location: view_livestock.php?msg=" . $redirect_msg);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Database Error: " . $e->getMessage());
    }
}
?>