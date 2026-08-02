<?php
session_start();
include '../db_connect.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['farmer_id'])) {
    $farmer_id = $_SESSION['farmer_id'];
    $livestock_id = $_POST['livestock_id'];
    
    $name     = $_POST['name'];
    $category = $_POST['category'];
    $breed    = $_POST['breed'];
    $age      = ($_POST['age'] === '') ? null : (int)$_POST['age'];
    $gender   = $_POST['gender'];
    $weight   = ($_POST['weight'] === '') ? 0 : $_POST['weight'];
    $price    = ($_POST['price'] === '') ? 0 : $_POST['price'];
    
    $status      = $_POST['availability_status'];
    $description = $_POST['description'];
    $sale_type   = $_POST['sale_type'];
    $vaccination = $_POST['vaccination'] ?? null;
    $medicine    = $_POST['medicine'] ?? null;
    $vitamin     = $_POST['vitamin'] ?? null;

    $auction_start  = ($sale_type === 'Auction') ? ($_POST['auction_start_time'] ?? null) : null;
    $auction_end    = ($sale_type === 'Auction') ? ($_POST['auction_end_time'] ?? null) : null;
    $deposit_amount = ($sale_type === 'Auction') ? (($_POST['deposit_amount'] === '') ? 0 : $_POST['deposit_amount']) : 0;
    $auction_title  = "Auction for " . $name;

if ($sale_type === 'Auction') {
    if (empty($auction_start) || empty($auction_end)) {
        die("Error: Auction times are required.");
    }
    if ($price <= 0) {
        die("Error: Auctions must have a starting price greater than RM 0.");
    }
}

    $stmtCheck = $pdo->prepare("SELECT image, availability_status FROM livestock WHERE livestock_id = ? AND farmer_id = ?");
    $stmtCheck->execute([$livestock_id, $farmer_id]);
    $currentRecord = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$currentRecord) {
        die("Error: Livestock record not found or access denied.");
    }

    if ($currentRecord['availability_status'] === 'Pending') {
        $status = 'Pending';
    }

    $currentImages = $currentRecord['image'];
    $imageArray = !empty($currentImages) ? explode(',', $currentImages) : [];

    if (!empty($_POST['removed_images'])) {
        $toRemove = explode(',', $_POST['removed_images']);
        $imageArray = array_filter($imageArray, function($img) use ($toRemove) {
            return !in_array(trim($img), $toRemove);
        });
    }

    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            $file_name = time() . "_" . $key . "_" . basename($_FILES["images"]["name"][$key]);
            $target_file = "uploads/" . $file_name;
            if (move_uploaded_file($tmp_name, $target_file)) {
                $imageArray[] = $file_name;
            }
        }
    }

    $finalImageString = implode(',', array_map('trim', $imageArray));

    try {
        $pdo->beginTransaction();

        $sql = "UPDATE livestock SET 
                name = :name, category = :cat, breed = :breed, age = :age, 
                gender = :gen, weight = :weight, price = :price, 
                availability_status = :status, description = :desc, 
                sale_type = :stype, image = :img
                WHERE livestock_id = :lid AND farmer_id = :fid";
        
        $params = [
            'name' => $name, 'cat' => $category, 'breed' => $breed, 'age' => $age, 
            'gen' => $gender, 'weight' => $weight, 'price' => $price, 
            'status' => $status, 'desc' => $description, 
            'stype' => $sale_type, 'img' => $finalImageString,
            'lid' => $livestock_id, 'fid' => $farmer_id
        ];
        $pdo->prepare($sql)->execute($params);

        $healthCheck = $pdo->prepare("SELECT 1 FROM health WHERE livestockid = ?");
        $healthCheck->execute([$livestock_id]);

        if ($healthCheck->fetch()) {
            $healthStmt = $pdo->prepare("UPDATE health SET vaccination = ?, medicine = ?, vitamin = ? WHERE livestockid = ?");
            $healthStmt->execute([$vaccination, $medicine, $vitamin, $livestock_id]);
        } else {
            $insHealth = $pdo->prepare("INSERT INTO health (livestockid, vaccination, medicine, vitamin) VALUES (?, ?, ?, ?)");
            $insHealth->execute([$livestock_id, $vaccination, $medicine, $vitamin]);
        }


        $provideService = isset($_POST['provideService']);
        
        $pdo->prepare("DELETE FROM harvestservice WHERE livestockid = ?")->execute([$livestock_id]);

        if ($provideService && isset($_POST['serviceType']) && is_array($_POST['serviceType'])) {
            $sqlSvc = "INSERT INTO harvestservice (livestockid, farmerid, servicetype, servicefee, availability) VALUES (?, ?, ?, ?, true)";
            $stmtSvc = $pdo->prepare($sqlSvc);

            foreach ($_POST['serviceType'] as $index => $type) {
                $fee = $_POST['serviceFee'][$index] ?? 0;

                if (!empty(trim($type))) {
                    $stmtSvc->execute([
                        $livestock_id, 
                        $farmer_id, 
                        htmlspecialchars($type), 
                        (float)$fee
                    ]);
                }
            }
        }

        if ($sale_type === 'Auction') {
            $stmt = $pdo->prepare("SELECT auction_id FROM auction WHERE livestock_id = ?");
            $stmt->execute([$livestock_id]);
            $auction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($auction) {
                $auction_id = $auction['auction_id'];
                $current_bid = $auction['current_bid'] ?? null; 

                if ($current_bid !== null && (float)$price > (float)$current_bid) {
                    $_SESSION['error_msg'] = "Error: Starting price (RM $price) cannot be higher than the current bid (RM $current_bid).";
                    header("Location: edit_livestock.php?id=" . $livestock_id);
                    exit();
                }

                $upd = $pdo->prepare("UPDATE auction SET start_time = ?, end_time = ?, starting_price = ?, title = ? WHERE auction_id = ?");
                $upd->execute([$auction_start, $auction_end, $price, $auction_title, $auction_id]);
            } else {
                $ins = $pdo->prepare("INSERT INTO auction (livestock_id, start_time, end_time, starting_price, title, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $ins->execute([$livestock_id, $auction_start, $auction_end, $price, $auction_title]);
                $auction_id = $pdo->lastInsertId();
            }

            $checkDeposit = $pdo->prepare("SELECT 1 FROM auction_deposits WHERE auction_id = ?");
            $checkDeposit->execute([$auction_id]);

            if ($checkDeposit->fetch()) {
                $pdo->prepare("UPDATE auction_deposits SET amount = ? WHERE auction_id = ?")
                ->execute([$deposit_amount, $auction_id]);
            } else {
                $pdo->prepare("INSERT INTO auction_deposits (auction_id, amount) VALUES (?, ?)")
                ->execute([$auction_id, $deposit_amount]);
            }

            $pdo->prepare("UPDATE auction_deposits SET amount = ? WHERE auction_id = ?")->execute([$deposit_amount, $auction_id]);
        } else {
            $pdo->prepare("DELETE FROM auction_deposits WHERE auction_id IN (SELECT auction_id FROM auction WHERE livestock_id = ?)")->execute([$livestock_id]);
            $pdo->prepare("DELETE FROM auction WHERE livestock_id = ?")->execute([$livestock_id]);
        }

        if (isset($_POST['delivery_type']) && is_array($_POST['delivery_type'])) {
            
            $pdo->prepare("DELETE FROM livestock_delivery_options WHERE livestock_id = ?")->execute([$livestock_id]);

            $methods = $_POST['delivery_type']; 
            $capacities = $_POST['delivery_max_capacity'];
            $fees = $_POST['delivery_fee'];     

            $sqlDeliveryOpt = "INSERT INTO livestock_delivery_options (livestock_id, method_name, delivery_fee, max_capacity) VALUES (?, ?, ?, ?)";
            $stmtDelOpt = $pdo->prepare($sqlDeliveryOpt);

            foreach ($methods as $index => $methodName) {
                if (!empty(trim($methodName))) {
                    $fee = !empty($fees[$index]) ? (float)$fees[$index] : 0;
                    $capacity = ($capacities[$index] !== '') ? (float)$capacities[$index] : 9999;
                    $stmtDelOpt->execute([$livestock_id, $methodName, $fee, $capacity]);
                }
            }
        }

        $pdo->commit();
        $_SESSION['success_msg'] = "Livestock details updated successfully!";
        
        $redirectStatus = ($status === 'Pending') ? "pending_approval" : "success";
        header("Location: view_livestock.php?status=" . $redirectStatus);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Critical Database Error: " . $e->getMessage());
    }
}