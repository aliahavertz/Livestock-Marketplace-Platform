<?php
session_start();
include '../db_connect.php';

$cartItems = [];
$subtotal = 0;
$dashboard_link = "customer_dashboard.php";

if (!empty($_SESSION['cart'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    
    $sql = "SELECT l.*, f.farm_name 
    FROM livestock l 
    JOIN farmer f ON l.farmer_id = f.farmer_id 
    WHERE l.livestock_id IN ($placeholders)
    AND l.availability_status = 'Available'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($_SESSION['cart']));
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cartItems as $item) {
        $subtotal += $item['price'];
    }
}

include '../inc/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        body { background: radial-gradient(circle at top, #fdf6ec, #f4efe6); font-family: 'PT Serif', serif; color: #1a1a1a; min-height: 100vh; }

        .hero-section { 
            height: 80px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-bottom: 25px; 
            max-width: 1100px; 
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

        .breadcrumb-container {
            max-width: 1100px; 
            margin: 30px auto 0 auto;
            padding: 0 20px; 
        }

        .breadcrumb-links {
            font-size: 14px;
            display: flex;
            align-items: center;
            color: #888;
        }

        .breadcrumb-links a {
            text-decoration: none;
            color: #1976d2;
            transition: 0.3s;
        }

        .breadcrumb-links a:hover {
            color: black;
        }

        .breadcrumb-links i.fa-chevron-right {
            font-size: 10px;
            margin: 0 12px;
            color: #ccc;
        }

        .cart-container { 
            max-width: 1100px; 
            margin: 30px auto 50px auto; 
            padding: 0 20px; 
            font-family: 'PT Serif', serif; 
        }
        .cart-title { font-family: 'Cinzel', serif; border-bottom: 2px solid #1976d2; padding-bottom: 10px; margin-bottom: 30px; }
        
        .cart-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        
        .cart-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .cart-table th { background: rgba(144, 202, 249, 0.5); padding: 15px; text-align: left; font-family: 'Cinzel'; font-size: 14px; color: black; }
        .cart-table td { padding: 20px; border-bottom: 1px solid #eee; }
        
        .item-details { display: flex; gap: 15px; align-items: center; }
        .item-details img { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 1px solid #ddd; }
        .item-info h4 { margin: 0; color: #333; }
        .item-info small { color: #888; }
        .item-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        #select-all {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .summary-card { background: #fff; padding: 25px; border-radius: 15px; border: 1px solid #90caf9; height: fit-content; position: sticky; top: 100px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 16px; }
        .total-row { border-top: 2px solid #eee; padding-top: 15px; font-weight: bold; font-size: 20px; color: #1976d2; }
        .row-disabled {
            opacity: 0.4;
            filter: grayscale(80%);
            pointer-events: none; 
            transition: all 0.3s ease;
        }

        .row-active {
            background-color: #f0f7ff; 
        }
        
        .btn-checkout { width: 300px; background: #1976d2; color: white; padding: 15px; border: none; border-radius: 30px; font-family: 'Cinzel'; font-weight: bold; cursor: pointer; transition: 0.3s; margin-top: 20px; text-decoration: none; display: block; text-align: center; }
        .btn-checkout:hover { background: #0d1b2a; }
        .btn-checkout:disabled {
            background: #ccc !important;
        }
        
        .remove-link { color: #d32f2f; text-decoration: none; font-size: 12px; font-weight: bold; cursor: pointer; }
        .empty-state { text-align: center; padding: 100px; }
    </style>
</head>
<body>

    <div class="hero-section">
        <h1>Livestock Cart</h1>
    </div>

    <div class="breadcrumb-container">
        <div class="breadcrumb-links">
            <a href="<?= $dashboard_link ?>"><i class="fas fa-home"></i> Marketplace</a>
            <span><i class="fas fa-chevron-right" style="font-size: 10px;"></i></span>
            <span style="color: #000000;">Livestock Cart</span>
        </div>
    </div>
    <div class="cart-container">

        <?php if (empty($cartItems)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-basket" style="font-size: 50px; color: #ccc; margin-bottom: 20px;"></i>
                <h2>Your basket is empty</h2>
                <p>Go back to the <a href="customer_dashboard.php">Marketplace</a> to find your next livestock.</p>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-list">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">
                                    <input type="checkbox" id="select-all" onclick="toggleAll(this)">
                                </th>
                                <th style="width: 50px;">No.</th>
                                <th>Livestock Item</th>
                                <th>Farm</th>
                                <th>Price</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            foreach ($cartItems as $item):  
                                $imgs = explode(',', $item['image']);
                                $displayImg = trim($imgs[0]);
                                ?>
                                <tr id="row-<?= $item['livestock_id'] ?>">
                                    <td style="text-align: center;">
                                        <input type="checkbox" class="item-checkbox" 
                                        name="selected_items[]" 
                                        value="<?= $item['livestock_id'] ?>" 
                                        data-price="<?= $item['price'] ?>"
                                        data-farm="<?= $item['farmer_id'] ?>" 
                                        onclick="calculateSelectedTotal()">
                                    </td>
                                    <td style="color: #888;"><?= $i++ ?></td>
                                    <td>
                                        <div class="item-details">
                                            <img src="../farmer/uploads/<?= $displayImg ?>" alt="">
                                            <div class="item-info">
                                                <h4><?= htmlspecialchars($item['name']) ?></h4>
                                                <small><?= htmlspecialchars($item['breed']) ?> | <?= $item['weight'] ?>kg</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($item['farm_name']) ?></td>
                                    <td style="font-weight: bold;">RM <?= number_format($item['price'], 2) ?></td>
                                    <td>
                                        <a href="javascript:void(0)" onclick="removeFromCart(<?= $item['livestock_id'] ?>)" class="remove-link">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cart-summary">
                    <div class="summary-card">
                        <h3 style="font-family: 'Cinzel'; margin-top: 0;">Order Summary</h3>
                        <div class="summary-row">
                            <span>Selected Items</span>
                            <span id="selected-count">0</span>
                        </div>
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="display-subtotal">RM 0.00</span>
                        </div>
                        <div class="total-row summary-row">
                            <span>Total</span>
                            <span id="display-total">RM 0.00</span>
                        </div>

                        <button onclick="proceedToCheckout()" id="checkout-btn" class="btn-checkout" style="opacity: 0.5; cursor: not-allowed;" disabled>
                            Checkout
                        </button>

                        <p style="font-size: 11px; color: #888; text-align: center; margin-top: 15px;">
                            <i class="fas fa-lock"></i> SSL Encrypted Payment
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function removeFromCart(id) {
            let data = new FormData();
            data.append('livestock_id', id);
            data.append('action', 'remove');

            fetch('cart_handler.php', {
                method: 'POST',
                body: data
            })
            .then(res => res.json())
            .then(response => {
                if(response.success) {
                    location.reload(); 
                }
            });
        }

        function calculateSelectedTotal() {
            const allCheckboxes = document.querySelectorAll('.item-checkbox');
            const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            const displaySubtotal = document.getElementById('display-subtotal');
            const displayTotal = document.getElementById('display-total');
            const selectedCount = document.getElementById('selected-count');
            const checkoutBtn = document.getElementById('checkout-btn');

            let total = 0;
            const STRIPE_LIMIT = 30000; 

            if (checkedBoxes.length > 0) {
                const activeFarmId = checkedBoxes[0].getAttribute('data-farm');

                allCheckboxes.forEach(cb => {
                    const row = cb.closest('tr'); 
                    
                    if (cb.getAttribute('data-farm') !== activeFarmId) {
                        cb.disabled = true;
                        row.classList.add('row-disabled');
                        row.classList.remove('row-active');
                    } else {
                        cb.disabled = false;
                        row.classList.remove('row-disabled');
                        row.classList.add('row-active');
                    }
                });

                checkedBoxes.forEach(cb => {
                    total += parseFloat(cb.getAttribute('data-price'));
                });

                if (total > STRIPE_LIMIT) {
                    checkoutBtn.disabled = true;
                    checkoutBtn.style.opacity = "0.5";
                    checkoutBtn.style.cursor = "not-allowed";
                    checkoutBtn.innerHTML = "Limit Exceeded (Max RM 30k)";
                    checkoutBtn.style.background = "#d32f2f"; 
                } else {
                    checkoutBtn.disabled = false;
                    checkoutBtn.style.opacity = "1";
                    checkoutBtn.style.cursor = "pointer";
                    checkoutBtn.innerHTML = "Checkout";
                    checkoutBtn.style.background = "#1976d2";
                }

            } else {
                allCheckboxes.forEach(cb => {
                    const row = cb.closest('tr');
                    cb.disabled = false;
                    row.classList.remove('row-disabled');
                    row.classList.remove('row-active');
                });

                checkoutBtn.disabled = true;
                checkoutBtn.style.opacity = "0.5";
                checkoutBtn.style.cursor = "not-allowed";
                checkoutBtn.innerHTML = "Checkout";
                checkoutBtn.style.background = "#1976d2";
            }

            const formattedTotal = "RM " + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            displaySubtotal.innerText = formattedTotal;
            displayTotal.innerText = formattedTotal;
            selectedCount.innerText = checkedBoxes.length;
        }

        function toggleAll(master) {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            if (checkboxes.length === 0) return;

            if (master.checked) {
                const targetFarmId = checkboxes[0].getAttribute('data-farm');

                checkboxes.forEach(cb => {
                    if (cb.getAttribute('data-farm') === targetFarmId) {
                        cb.checked = true;
                    } else {
                        cb.checked = false; 
                    }
                });
            } else {
                checkboxes.forEach(cb => cb.checked = false);
            }

            calculateSelectedTotal();
        }

        function proceedToCheckout() {
            const selected = Array.from(document.querySelectorAll('.item-checkbox:checked'))
            .map(cb => cb.value);

            if (selected.length === 0) return;

            window.location.href = "../payment/unified_checkout.php?items=" + selected.join(',');
        }

        function removeFromCart(id) {
            if(!confirm('Remove this item from basket?')) return;

            let data = new FormData();
            data.append('livestock_id', id);
            data.append('action', 'remove');

            fetch('cart_handler.php', {
                method: 'POST',
                body: data
            })
            .then(res => res.json())
            .then(response => {
                if(response.success) {
                    location.reload(); 
                }
            });
        }
    </script>

</body>
</html>