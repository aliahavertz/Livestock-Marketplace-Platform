<?php
require_once 'vendor/autoload.php';
require_once 'db_connect.php';

\Stripe\Stripe::setApiKey('sk_test_51SipzdEhjpQ4R31fUn7iS5Ld3K4vigl5Hzx05UWBokwZ1dypneBTDXsSG0yAq4NiR4Bbag336ykhYseXJw5CHDJZ00Pi7SPtFt');
$endpoint_secret = 'whsec_YOUR_WEBHOOK_SECRET';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object;
        $stripe_id = $paymentIntent->id;

        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'Paid' WHERE stripe_payment_id = ?");
        $stmt->execute([$stripe_id]);
        break;

    case 'payment_intent.payment_failed':
        $paymentIntent = $event->data->object;
        $stripe_id = $paymentIntent->id;

        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'Failed' WHERE stripe_payment_id = ?");
        $stmt->execute([$stripe_id]);
        break;
}

http_response_code(200);