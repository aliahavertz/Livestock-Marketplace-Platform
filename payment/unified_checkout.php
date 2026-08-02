<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';

if (isset($_GET['livestock_id'])) {
    $livestock_id = (int)$_GET['livestock_id'];
    $_SESSION['checkout_livestock_id'] = $livestock_id;
} elseif (!empty($_SESSION['cart'])) {
    $livestock_id = (int)reset($_SESSION['cart']); 
    $_SESSION['checkout_livestock_id'] = $livestock_id;
} else {
    $livestock_id = $_SESSION['checkout_livestock_id'] ?? 0;
}

$customer_id = $_SESSION['customer_id'] ?? 0;

$selected_ids = [];

if (isset($_GET['items'])) {
    $selected_ids = array_map('intval', explode(',', $_GET['items']));
} elseif (isset($_GET['livestock_id'])) {
    $selected_ids = [(int)$_GET['livestock_id']];
}

if (empty($selected_ids) || !$customer_id) {
    die("<h3>Checkout Session Error</h3><p>No items selected or you are not logged in.</p>");
}

\Stripe\Stripe::setApiKey('sk_test_51SipzdEhjpQ4R31fUn7iS5Ld3K4vigl5Hzx05UWBokwZ1dypneBTDXsSG0yAq4NiR4Bbag336ykhYseXJw5CHDJZ00Pi7SPtFt');

$placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM livestock WHERE livestock_id IN ($placeholders)");
$stmt->execute($selected_ids);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) die("Livestock items not found.");

$auction_id = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;
$deposit_paid = 0; 

if ($auction_id > 0) {
    $stmtDeposit = $pdo->prepare("
        SELECT amount 
        FROM auction_deposits_paid 
        WHERE auction_id = ? AND customer_id = ? AND status = 'paid'
    ");
    $stmtDeposit->execute([$auction_id, $customer_id]);
    $deposit_paid = (float)$stmtDeposit->fetchColumn();
}

$passed_price = isset($_GET['price']) ? (float)$_GET['price'] : null;

if ($auction_id > 0 && $passed_price !== null) {
    $winning_bid = $passed_price; 
    $base_price = $winning_bid;
} else {
    $base_price = 0;
    foreach ($items as $item) { 
        $base_price += (float)$item['price']; 
    }
    $winning_bid = $base_price; 
}

$first_farmer_id = $items[0]['farmer_id'];
$total_order_weight = 0;

foreach ($items as $item) {
    if ($item['farmer_id'] !== $first_farmer_id) {
        die("<h3>Logistics Error</h3><p>Please checkout items from one farm at a time.</p>");
    }
    $category = strtolower($item['category'] ?? '');
    $total_order_weight += (float)($item['weight'] ?? 0);
    // $total_required_units += (strpos($category, 'cow') !== false || strpos($category, 'cattle') !== false) ? 4 : 1;
}


// $base_price = 0;
// foreach ($items as $item) { $base_price += (float)$item['price']; }

$farmer_id = $items[0]['farmer_id'];

$stmtDelivery = $pdo->prepare("
    SELECT DISTINCT ON (LOWER(REGEXP_REPLACE(method_name, '\s+', '', 'g')), delivery_fee) 
    method_name, delivery_fee, max_capacity, option_id
    FROM livestock_delivery_options 
    WHERE livestock_id IN ($placeholders)
    ORDER BY LOWER(REGEXP_REPLACE(method_name, '\s+', '', 'g')), delivery_fee, option_id ASC
    ");

$stmtDelivery->execute($selected_ids);
$deliveryOptions = $stmtDelivery->fetchAll(PDO::FETCH_ASSOC);


try {
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => (int)round($base_price * 100), 
        'currency' => 'myr',
        'payment_method_types' => ['card', 'fpx'],
        'metadata' => [
            'livestock_ids' => implode(',', $selected_ids), 
            'customer_id' => $customer_id,
            'auction_id' => $auction_id
        ]
    ]);
    $clientSecret = $paymentIntent->client_secret;
} catch (Exception $e) { die("Stripe Error: " . $e->getMessage()); }

require_once __DIR__ . '/../inc/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --vintage-blue: #1976d2; --soft-blue: #90caf9; --navy: #0d1b2a; --cream: #fdf6ec; --border: rgba(144, 202, 249, 0.3); }
        body { background: radial-gradient(circle at top, #fdf6ec, #f4efe6); font-family: 'PT Serif', serif; padding: 100px 20px; color: #453c34; }
        .wrapper { max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: 1fr 380px; gap: 30px; }
        .card { background: white; padding: 30px; border: 1px solid var(--border); margin-bottom: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        h2 { font-family: 'Cinzel', serif; font-size: 1.2rem; color: var(--navy); border-bottom: 2px double var(--soft-blue); padding-bottom: 10px; margin-bottom: 20px; }
        .item-preview { display: flex; gap: 20px; align-items: center; margin-bottom: 15px; }
        .item-preview img { border-radius: 8px; border: 2px solid var(--soft-blue); object-fit: cover; }
        .service-label { background: #fff; border: 1px solid #eee; padding: 12px 15px; border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; width: 100%; box-sizing: border-box; margin-bottom: 8px; }
        .service-label:hover:not(.disabled) { border-color: var(--vintage-blue); background: #f0f7ff; }
        .service-label.disabled { opacity: 0.5; cursor: not-allowed; background: #f9f9f9; }
        .service-label.disabled .fee-text {
            color: #d32f2f;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.7rem;
        }
        #delivery-details-section, 
        #payment-card-section { 
            display: none; 
        }
        .price-row { display: flex; justify-content: space-between; margin: 15px 0; align-items: center; }
        .btn-pay { width: 100%; background: linear-gradient(135deg, var(--vintage-blue), #64b5f6); color: white; padding: 15px; border: none; font-family: 'Cinzel'; font-weight: bold; border-radius: 50px; cursor: pointer; }
        .custom-input { width: 100%; padding: 12px; border: 1px solid var(--soft-blue); border-radius: 8px; box-sizing: border-box; }
        .summary-card { position: sticky; top: 20px; border-top: 5px solid var(--vintage-blue); }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="main-content">
        <?php foreach ($items as $livestock): 
            $id = $livestock['livestock_id'];
            $stmtS = $pdo->prepare("SELECT DISTINCT ON (servicetype) * FROM harvestservice WHERE livestockid = ?");
            $stmtS->execute([$livestock['livestock_id']]);
            $itemServices = $stmtS->fetchAll(PDO::FETCH_ASSOC);
            $images = !empty($livestock['image']) ? explode(',', $livestock['image']) : ['../assets/no-image.png'];
            $imgSrc = (strpos(trim($images[0]), '../') === false) ? "../farmer/uploads/" . trim($images[0]) : trim($images[0]);
        ?>
        <div class="card">
            <div class="item-preview">
                <img src="<?= $imgSrc ?>" width="80" height="80">
                <div>
                    <h3 style="margin:0; font-family:'Cinzel';"><?= htmlspecialchars($livestock['name']) ?></h3>

                    <?php if ($auction_id > 0): ?>
                        <h4 style="margin: 10px 0 0 0; font-family:'Cinzel'; font-size: 0.8rem; color: #666;">Balance to pay:</h4>
                        <p style="color:var(--vintage-blue); font-weight: bold; margin: 0;">
                            RM <?= number_format($passed_price, 2) ?>
                        </p>
                    <?php else: ?>
                        <p style="color:var(--vintage-blue); font-weight: bold;">
                            RM <?= number_format($livestock['price'], 2) ?>
                        </p>
                    <?php endif; ?>

                </div>
            </div>

                <?php if ($itemServices): ?>
                    <p style="font-size: 0.8rem; font-weight: bold; margin-bottom: 10px;">ADD OPTIONAL SERVICES FOR THIS ITEM:</p>
                    <?php foreach ($itemServices as $s): ?>
                        <label class="service-label">
                            <span>
                                <input type="checkbox" 
                                name="services[]" 
                                value="<?= $s['serviceid'] ?>" 
                                class="service-cb" 
                                data-price="<?= $s['servicefee'] ?>"
                                data-name="<?= htmlspecialchars($s['servicetype']) ?>">
                                <span style="margin-left: 10px; font-weight: 500;">
                                    <?= htmlspecialchars($s['servicetype']) ?>
                                </span>
                            </span>
                            <strong>+ RM <?= number_format($s['servicefee'], 2) ?></strong>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="background: #f9f9f9; border: 1px dashed #ccc; padding: 15px; border-radius: 8px; text-align: center;">
                        <p style="font-size: 0.85rem; color: #888; margin: 0;">
                            <i class="fas fa-info-circle"></i> No optional services available for this item.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="card">
            <h2><i class="fas fa-truck"></i> Shipping</h2>
            <p style="font-size: 0.8rem; color: #666; margin-bottom: 15px;">
                Total Order Weight: <strong><?= number_format($total_order_weight, 2) ?> KG</strong>
            </p>
            
            <?php if ($deliveryOptions): ?>
                <?php foreach ($deliveryOptions as $opt): ?>
                    <label class="service-label shipping-row" id="label-<?= $opt['option_id'] ?>">
                        <span>
                            <input type="radio" name="global_shipping" class="shipping-radio" 
                                   data-price="<?= $opt['delivery_fee'] ?>" 
                                   data-capacity="<?= $opt['max_capacity'] ?>" 
                                   data-id="<?= $opt['option_id'] ?>">
                            <?= htmlspecialchars($opt['method_name']) ?>
                            <small style="display:block; color:#777;">
                                Max Vehicle Capacity: <?= number_format($opt['max_capacity'], 2) ?> KG
                            </small>
                        </span>
                        <strong class="fee-text"><?= $opt['delivery_fee'] == 0 ? 'FREE' : '+ RM ' . number_format($opt['delivery_fee'], 2) ?></strong>
                    </label>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No shipping options available. Please contact farmer.</p>
            <?php endif; ?>
        </div>

        <div class="card" id="delivery-details-section">
            <h2><i class="fas fa-address-card"></i> Delivery Details</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <input type="text" id="first_name" placeholder="First Name" class="custom-input" required>
                <input type="text" id="last_name" placeholder="Last Name" class="custom-input" required>
                <input type="email" id="email" placeholder="Email Address" class="custom-input">
                <input type="tel" id="phone_number" placeholder="Phone Number" class="custom-input" required>
                <input type="text" id="delivery_street" placeholder="Street Address" class="custom-input" style="grid-column: span 2;" required>
                <input type="text" id="delivery_city" placeholder="City" class="custom-input" required>
                <input type="text" id="delivery_postcode" placeholder="Postcode" class="custom-input" required>
                <input type="text" id="delivery_state" placeholder="State" class="custom-input" required>
            </div>
        </div>

        <div class="card" id="payment-card-section" style="display: none;">
            <h2><i class="fas fa-shield-alt"></i> Secure Payment</h2>
            <div id="payment-element"></div>
            <button id="submit-button" class="btn-pay" style="margin-top: 20px;">Confirm & Pay</button>
        </div>
    </div>

    <div class="sidebar">
        <div class="card summary-card">
            <h2>Order Summary</h2>
            <div class="price-row"><span>Items Total</span><span>RM <?= number_format($winning_bid, 2) ?></span></div>
            <?php if ($auction_id > 0 && $deposit_paid > 0): ?>
                <div class="price-row" style="color: #d32f2f; font-weight: 500;">
                    <span><i class="fas fa-check-circle"></i> Deposit Paid</span>
                    <span>- RM <?= number_format($deposit_paid, 2) ?></span>
                </div>
            <?php endif; ?>
            <div class="price-row"><span>Services</span><span id="service-total-display">RM 0.00</span></div>
            <div class="price-row"><span>Shipping</span><span id="shipping-total-display">RM 0.00</span></div>
            <hr>
            <div class="price-row">
                <span style="font-weight:bold;">Total Price</span>
                <span id="final-total-display" style="font-size:1.5rem; color:var(--vintage-blue);">RM <?= number_format($base_price, 2) ?></span>
            </div>
            <!-- <div class="price-row"><span style="font-weight:bold;">Total</span><span id="final-total-display" style="font-size:1.5rem; color:var(--vintage-blue);">RM <?= number_format($base_price, 2) ?></span></div> -->
            <button id="proceed-button" class="btn-pay" style="margin-top:20px;">Proceed to Payment</button>
        </div>
    </div>
</div>

<script>
    const stripe = Stripe('pk_test_51SipzdEhjpQ4R31f2AB6H0Q57SvDtJk7OcTJezCOfEVNqGxBNBQAArWU2Cj1RdcGKxLB3yPyAy2rEQDR610TQhSZ00UAHM7moN');
    const basePrice = <?= $base_price ?>;
    const depositPaid = <?= $deposit_paid ?>;
    const totalOrderWeight = <?= $total_order_weight ?>;

    function getSelectedShippingName() {
        const selected = document.querySelector('.shipping-radio:checked');
        if (!selected) return 'Self-Pickup';
        const labelText = selected.closest('.shipping-row').childNodes[1].textContent.trim();
        return labelText || 'Standard Delivery';
    }

    function validateCapacity() {
        const radios = document.querySelectorAll('.shipping-radio');
        let firstValidFound = false;

        radios.forEach(radio => {
            const maxCapacity = parseFloat(radio.dataset.capacity);
            const label = document.getElementById('label-' + radio.dataset.id);

            if (maxCapacity > 0 && totalOrderWeight > maxCapacity) {
                radio.disabled = true;
                label.classList.add('disabled');
                label.querySelector('.fee-text').innerHTML = "<span style='color:red; font-size:0.7rem;'>OVERWEIGHT</span>";
            } else {
                radio.disabled = false;
                label.classList.remove('disabled');

                const price = parseFloat(radio.dataset.price);
                label.querySelector('.fee-text').innerHTML = price === 0 ? 'FREE' : '+ RM ' + price.toFixed(2);

                if (!firstValidFound) {
                    radio.checked = true;
                    firstValidFound = true;
                }
            }
        });
    }

async function updateTotals() {
    console.log("updateTotals triggered!"); 

    let serviceExtra = 0;
    document.querySelectorAll('.service-cb:checked').forEach(cb => {
        serviceExtra += parseFloat(cb.dataset.price || 0);
    });

    let shippingExtra = 0;
    const selectedShipping = document.querySelector('.shipping-radio:checked');

    if (selectedShipping) {
        shippingExtra = parseFloat(selectedShipping.dataset.price || 0);
    }

    const newTotal = (typeof basePrice !== 'undefined' ? basePrice : 0) + serviceExtra + shippingExtra;

    const serviceDiv = document.getElementById('service-total-display');
    const shippingDiv = document.getElementById('shipping-total-display');
    const finalDiv = document.getElementById('final-total-display');

    if (serviceDiv) serviceDiv.innerText = 'RM ' + serviceExtra.toFixed(2);
    if (shippingDiv) shippingDiv.innerText = 'RM ' + shippingExtra.toFixed(2);
    if (finalDiv) finalDiv.innerText = 'RM ' + newTotal.toFixed(2);

    try {
        const amountInSen = Math.round(newTotal * 100);
        const response = await fetch('update_payment_intent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                paymentIntentId: '<?= $paymentIntent->id ?>',
                newAmount: amountInSen,
                customer_id: <?= (int)$customer_id ?>,
                livestock_ids: '<?= implode(",", $selected_ids) ?>',
                auction_id: <?= (int)$auction_id ?>,
                deposit_amount: depositPaid,
                shipping_method: getSelectedShippingName(),
                harvestFee: serviceExtra,
                shippingFee: shippingExtra
            })
        });
        console.log("Server responded with status:", response.status);
    } catch (e) {
        console.error("Server sync error:", e);
    }
}

document.getElementById('proceed-button').addEventListener('click', async () => {
    const proceedBtn = document.getElementById('proceed-button');
    
    document.getElementById('delivery-details-section').style.display = 'block';
    document.getElementById('payment-card-section').style.display = 'block';

    proceedBtn.style.opacity = '0.5';
    proceedBtn.innerText = 'Processing...';
    proceedBtn.disabled = true;

    let selectedServiceIDs = [];
    let selectedServiceNames = [];
    let serviceExtra = 0;
    document.querySelectorAll('.service-cb:checked').forEach(cb => {
        selectedServiceIDs.push(cb.value);
        selectedServiceNames.push(cb.dataset.name);
        serviceExtra += parseFloat(cb.dataset.price) || 0;
    });
    
    const selectedShipping = document.querySelector('.shipping-radio:checked');
    const shippingExtra = selectedShipping ? parseFloat(selectedShipping.dataset.price) : 0;
    const currentBasePrice = typeof basePrice !== 'undefined' ? basePrice : 0;
    const amountInSen = Math.round((currentBasePrice + serviceExtra + shippingExtra) * 100);

    try {
        const response = await fetch('update_payment_intent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                paymentIntentId: '<?php echo $paymentIntent->id; ?>', 
                newAmount: amountInSen,
                customer_id: <?= (int)$customer_id ?>, 
                livestock_ids: '<?= implode(",", $selected_ids) ?>',
                selected_services: selectedServiceIDs.join(','),
                service_names: selectedServiceNames.join(', '),
                shipping_option_id: selectedShipping ? selectedShipping.dataset.id : 0,
                shipping_method: getSelectedShippingName(),
                harvestFee: serviceExtra,
                shippingFee: shippingExtra
            })
        });

        const result = await response.json();
        console.log("Metadata sync result:", result);

        if (!result.success) {
            throw new Error("Metadata update failed on server");
        }

        window.scrollTo({ 
            top: document.getElementById('delivery-details-section').offsetTop - 20, 
            behavior: 'smooth' 
        });

        if (!window.stripeElements) {
            window.stripeElements = stripe.elements({ clientSecret: '<?= $clientSecret ?>' });
            const paymentElement = window.stripeElements.create('payment');
            paymentElement.mount('#payment-element');
        }

    } catch (e) {
        console.error("CRITICAL ERROR:", e);
        alert("Failed to sync order details. Please refresh and try again.");
        proceedBtn.disabled = false;
        proceedBtn.style.opacity = '1';
        proceedBtn.innerText = 'Proceed to Checkout';
    }
});

document.querySelectorAll('.service-cb, .shipping-radio').forEach(el => {
    el.addEventListener('change', updateTotals);
});

window.addEventListener('DOMContentLoaded', () => {
    validateCapacity();
    updateTotals();
});


document.getElementById('submit-button').addEventListener('click', async (event) => {
    event.preventDefault();

    const requiredFields = ['first_name', 'last_name', 'phone_number', 'delivery_street', 'delivery_city', 'delivery_postcode', 'delivery_state'];
    for (let fieldId of requiredFields) {
        const field = document.getElementById(fieldId);
        if (!field.value.trim()) {
            alert("Please fill in your delivery details before making payment.");
            field.focus();
            field.style.borderColor = "red"; 
            return; 
        } else {
            field.style.borderColor = "var(--soft-blue)";
        }
    }

    if (!window.stripeElements) return;

    const submitBtn = document.getElementById('submit-button');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    try {
        const fullName = document.getElementById('first_name').value + ' ' + document.getElementById('last_name').value;
        
        await fetch('update_payment_intent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                paymentIntentId: '<?php echo $paymentIntent->id; ?>',
                delivery_details: {
                    recipient_name: fullName,
                    phone: document.getElementById('phone_number').value,
                    email: document.getElementById('email').value,
                    address: document.getElementById('delivery_street').value,
                    city: document.getElementById('delivery_city').value,
                    postcode: document.getElementById('delivery_postcode').value,
                    state: document.getElementById('delivery_state').value
                }
            })
        });
    } catch (e) {
        console.error("Metadata sync failed, but proceeding with payment...", e);
    }

    const { error } = await stripe.confirmPayment({
        elements: window.stripeElements,
        confirmParams: {
            return_url: new URL("success.php", window.location.href).href, 
            payment_method_data: {
                billing_details: {
                    name: document.getElementById('first_name').value + ' ' + document.getElementById('last_name').value,
                    email: document.getElementById('email').value,
                    phone: document.getElementById('phone_number').value,
                    address: {
                        line1: document.getElementById('delivery_street').value,
                        city: document.getElementById('delivery_city').value,
                        postal_code: document.getElementById('delivery_postcode').value,
                        state: document.getElementById('delivery_state').value,
                        country: 'MY' 
                    }
                }
            }
        },
    });

    if (error) {
        alert(error.message);
        submitBtn.disabled = false;
        submitBtn.innerText = "Confirm & Pay";
    }
});
</script>
</body>
</html>