<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';
include '../inc/header.php';
include '../inc/numbers.php';

\Stripe\Stripe::setApiKey('sk_test_51SipzdEhjpQ4R31fUn7iS5Ld3K4vigl5Hzx05UWBokwZ1dypneBTDXsSG0yAq4NiR4Bbag336ykhYseXJw5CHDJZ00Pi7SPtFt');

if (isset($_GET['auction_id']) && isset($_SESSION['customer_id'])) {
    $auction_id = (int)$_GET['auction_id'];
    $customer_id = $_SESSION['customer_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT 
            a.auction_id, 
            ad.amount, 
            l.livestock_id, 
            l.name, 
            l.image 
            FROM auction_deposits ad
            JOIN auction a ON ad.auction_id = a.auction_id
            JOIN livestock l ON a.livestock_id = l.livestock_id
            WHERE ad.auction_id = ?
            ");
        $stmt->execute([$auction_id]);
        $item = $stmt->fetch();

        if (!$item) die("Error: Auction details not found.");

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => (int)round($item['amount'] * 100),
            'currency' => 'myr',
            'payment_method_types' => ['card', 'fpx'],
            'metadata' => [
                'auction_id' => $auction_id,
                'customer_id' => $customer_id,
                'payment_type' => 'auction_deposit'
            ]
        ]);
        $clientSecret = $paymentIntent->client_secret;

    } catch (Exception $e) {
        die("Stripe Error: " . $e->getMessage());
    }
} else {
    header("Location: Join_Auction.php");
    exit();
}

$images = !empty($item['image']) ? explode(',', $item['image']) : ['../assets/no-image.png'];
$imgSrc = (strpos(trim($images[0]), '../') === false) ? "../farmer/uploads/" . trim($images[0]) : trim($images[0]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Join Auction | RanchLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --vintage-blue: #1976d2; --navy: #0d1b2a; --cream: #fdf6ec; --soft-blue: #90caf9; }
        body { background: #fdf6ec; font-family: 'PT Serif', serif; padding: 100px 20px; color: #453c34; }
        .wrapper { max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: 1fr 350px; gap: 30px; }
        .card { background: white; padding: 30px; border-radius: 12px; border: 1px solid rgba(144, 202, 249, 0.3); box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h2 { font-family: 'Cinzel', serif; font-size: 1.1rem; color: var(--navy); border-bottom: 2px double var(--soft-blue); padding-bottom: 10px; margin-bottom: 20px; }
        .custom-input { width: 100%; padding: 12px; border: 1px solid var(--soft-blue); border-radius: 8px; box-sizing: border-box; margin-bottom: 10px; }
        .btn-pay { width: 100%; background: linear-gradient(135deg, var(--vintage-blue), #64b5f6); color: white; padding: 15px; border: none; font-family: 'Cinzel'; font-weight: bold; border-radius: 50px; cursor: pointer; margin-top: 20px; }
        .item-mini { display: flex; gap: 15px; align-items: center; }
        .item-mini img { border: 2px solid var(--soft-blue); border-radius: 8px; }
        .btn-marketplace {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%; 
            margin-top: 8px;
            padding: 12px;
            color: #718096; 
            text-decoration: none;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            font-size: 0.75rem;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .btn-marketplace:hover {
            color: #e53e3e;
            transform: translateY(-1px);
        }

        .btn-marketplace i {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="main-content">
        <div class="card">
            <h2><i class="fas fa-user"></i> Contact Information</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <input type="text" id="full_name" placeholder="Full Name" class="custom-input" style="grid-column: span 2;" required>
                <input type="email" id="email" placeholder="Email Address" class="custom-input">
                <input type="tel" id="phone" placeholder="Phone Number" class="custom-input">
            </div>
        </div>

        <div class="card">
            <h2><i class="fas fa-credit-card"></i> Secure Payment</h2>
            <div id="payment-element">
                </div>
            <button id="submit-button" class="btn-pay">Pay Deposit RM <?= number_format($item['amount'], 2) ?></button>
            <div id="payment-message" style="margin-top:15px; color:red; font-size:0.9rem; display:none;"></div>
        </div>
    </div>

    <div class="sidebar">
        <div class="card" style="position: sticky; top: 20px; border-top: 5px solid var(--vintage-blue);">
            <h2>Auction Summary</h2>
            <div class="item-mini">
                <img src="<?= $imgSrc ?>" width="60" height="60">
                <div>
                    <strong style="display:block; font-family:'Cinzel'; font-size:0.8rem;"><?= htmlspecialchars($item['name']) ?></strong>
                    <span style="font-size:0.7rem; color:#777;">Auction ID: <?= formatAuctionID($auction_id) ?></span>
                </div>
            </div>
            <hr style="margin:20px 0; opacity:0.2;">
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span>Entry Deposit</span>
                <strong>RM <?= number_format($item['amount'], 2) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:1.2rem; color:var(--vintage-blue);">
                <span style="font-family:'Cinzel';">Total</span>
                <strong>RM <?= number_format($item['amount'], 2) ?></strong>
            </div>
            <p style="font-size:0.7rem; color:#888; margin-top:20px;">
                <i class="fas fa-info-circle"></i> This amount is held as a guarantee for your bid participation.
            </p>
            <a href="customer_dashboard.php" class="btn-marketplace"><i class="fas fa-close"></i>Cancel & Return</a>
        </div>
    </div>
</div>

<script>
    const stripe = Stripe('pk_test_51SipzdEhjpQ4R31f2AB6H0Q57SvDtJk7OcTJezCOfEVNqGxBNBQAArWU2Cj1RdcGKxLB3yPyAy2rEQDR610TQhSZ00UAHM7moN');
    const options = { clientSecret: '<?= $clientSecret ?>' };
    const elements = stripe.elements(options);
    const paymentElement = elements.create('payment');
    paymentElement.mount('#payment-element');

    const form = document.getElementById('submit-button');
    const messageContainer = document.querySelector('#payment-message');

    form.addEventListener('click', async (event) => {
        event.preventDefault();

        const fullName = document.getElementById('full_name').value;
        if (!fullName) {
            alert("Please provide your full name.");
            return;
        }

        form.disabled = true;
        form.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: "http://localhost/LivestockMarketplace/Models/deposit_success.php",
                payment_method_data: {
                    billing_details: {
                        name: fullName,
                        email: document.getElementById('email').value,
                        phone: document.getElementById('phone').value
                    }
                }
            },
        });

        if (error) {
            messageContainer.style.display = 'block';
            messageContainer.textContent = error.message;
            form.disabled = false;
            form.innerText = "Pay Deposit RM <?= number_format($item['amount'], 2) ?>";
        }
    });
</script>
</body>
</html>