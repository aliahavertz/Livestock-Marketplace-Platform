<?php
session_start();
include '../db_connect.php';

$action = $_REQUEST['action'] ?? '';
$customer_id = $_SESSION['customer_id'] ?? null; 

function updateCartCookie($cartArray) {
    setcookie('persistent_cart', json_encode($cartArray), time() + (86400 * 30), "/");
}

if ($action == 'add' && isset($_POST['livestock_id'])) {
    $id = (int)$_POST['livestock_id'];
    
    if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }
    
    if (!in_array($id, $_SESSION['cart'])) {
        $_SESSION['cart'][] = $id;
        
        setcookie('persistent_cart', json_encode($_SESSION['cart']), time() + (86400 * 30), "/");

        if (isset($_SESSION['customer_id'])) {
            $sql = "INSERT INTO cart (customer_id, livestock_id) 
                    VALUES (?, ?) 
                    ON CONFLICT (customer_id, livestock_id) DO NOTHING";
            $pdo->prepare($sql)->execute([$_SESSION['customer_id'], $id]);
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Already in basket']);
    }
    exit;
}

if ($action == 'remove' && isset($_POST['livestock_id'])) {
    $id = (int)$_POST['livestock_id'];
    
    if (isset($_SESSION['cart']) && ($key = array_search($id, $_SESSION['cart'])) !== false) {
        unset($_SESSION['cart'][$key]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        
        if (empty($_SESSION['cart'])) {
            setcookie('persistent_cart', '', time() - 3600, "/");
            unset($_SESSION['cart']); 
        } else {
            updateCartCookie($_SESSION['cart']);
        }

        if ($customer_id) {
            $sql = "DELETE FROM cart WHERE customer_id = ? AND livestock_id = ?";
            $pdo->prepare($sql)->execute([$customer_id, $id]);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action == 'get') {
    if (empty($_SESSION['cart']) && isset($_COOKIE['persistent_cart'])) {
        $_SESSION['cart'] = json_decode($_COOKIE['persistent_cart'], true);
    }

    $items = [];
    $total = 0;
    
    if (!empty($_SESSION['cart'])) {
        $ids = implode(',', array_map('intval', $_SESSION['cart']));
        $stmt = $pdo->query("SELECT livestock_id, name, price, image FROM livestock 
                             WHERE livestock_id IN ($ids) AND availability_status = 'Available'");
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $imgs = explode(',', $row['image']);
            $items[] = [
                'id' => $row['livestock_id'],
                'name' => $row['name'],
                'price' => $row['price'],
                'image' => trim($imgs[0])
            ];
            $total += $row['price'];
        }
    }
    echo json_encode(['items' => $items, 'total' => $total]);
    exit;
}
?>