<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['customer_id'])) {
    die('Invalid request');
}

$customer_id = $_SESSION['customer_id'];
$livestock_id = (int)$_POST['livestock_id'];
$auction_id = !empty($_POST['auction_id']) ? (int)$_POST['auction_id'] : null;

$final_price = (float)$_POST['total_with_services'];
$remarks = $_POST['harvest_remarks'] ?? '';
$services_selected = isset($_POST['services']) ? implode(',', $_POST['services']) : '';

$stmt = $pdo->prepare("SELECT name, breed FROM livestock WHERE livestock_id = ?");
$stmt->execute([$livestock_id]);
$livestock = $stmt->fetch();

\Stripe\Stripe::setApiKey('sk_test_51SipzdEhjpQ4R31fUn7iS5Ld3K4vigl5Hzx05UWBokwZ1dypneBTDXsSG0yAq4NiR4Bbag336ykhYseXJw5CHDJZ00Pi7SPtFt');

try {
    $checkout_session = \Stripe\Checkout\Session::create([
        'mode' => 'payment',
        'success_url' => 'http://localhost/LivestockMarketplace/payment/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => 'http://localhost/LivestockMarketplace/Models/customer_dashboard.php',
        'payment_method_types' => ['card', 'fpx'],
        
        'shipping_address_collection' => ['allowed_countries' => ['MY']],
        'phone_number_collection' => ['enabled' => true],

        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => 'myr',
                'unit_amount' => (int)round($final_price * 100),
                'product_data' => [
                    'name' => ($auction_id ? "Auction Settlement: " : "Purchase: ") . $livestock['name'],
                    'description' => "Breed: " . $livestock['breed'],
                ],
            ],
        ]],
        'metadata' => [
            'livestock_id' => $livestock_id,
            'customer_id' => $customer_id,
            'remarks' => $remarks,
            'services' => $services_selected
        ]
    ]);

    $sql = "INSERT INTO orders (customer_id, livestock_id, total_price, status, payment_status, stripe_payment_id, harvest_remarks, selected_services) 
            VALUES (?, ?, ?, 'Processing', 'Pending', ?, ?, ?)";
    $stmtOrder = $pdo->prepare($sql);
    $stmtOrder->execute([
        $customer_id, 
        $livestock_id, 
        $final_price, 
        $checkout_session->id, 
        $remarks, 
        $services_selected
    ]);

    header("Location: " . $checkout_session->url);
    exit;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}