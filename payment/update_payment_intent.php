<?php
require_once __DIR__ . '/../vendor/autoload.php';

$stripe = new \Stripe\StripeClient('sk_test_51SipzdEhjpQ4R31fUn7iS5Ld3K4vigl5Hzx05UWBokwZ1dypneBTDXsSG0yAq4NiR4Bbag336ykhYseXJw5CHDJZ00Pi7SPtFt');

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['paymentIntentId'])) {
    $updatePayload = [];
    $meta = []; 

    if (isset($data['newAmount'])) {
        $updatePayload['amount'] = (int)$data['newAmount'];
        
        $totalAmount = (float)$data['newAmount'] / 100;
        $harvestFee = (float)($data['harvestFee'] ?? 0);
        $shippingFee = (float)($data['shippingFee'] ?? 0);
        $animalAmount = $totalAmount - $harvestFee - $shippingFee;

        $meta = array_merge($meta, [
            'customer_id'        => $data['customer_id'] ?? 0,
            'livestock_ids'      => $data['livestock_ids'] ?? '',
            'selected_services'  => $data['selected_services'] ?? '',
            'service_names'     => (string)($data['service_names'] ?? 'None'),
            'animal_amount'      => number_format($animalAmount, 2, '.', ''), 
            'harvest_amount'     => number_format($harvestFee, 2, '.', ''),
            'shipping_fee'       => number_format($shippingFee, 2, '.', ''),
            'shipping_method'    => $data['shipping_method'] ?? 'Self-Pickup',
            'shipping_option_id' => $data['shipping_option_id'] ?? 0
        ]);
    }

    if (isset($data['delivery_details'])) {
        $details = $data['delivery_details'];
        $nameParts = explode(' ', trim($details['recipient_name']));
        
        $meta = array_merge($meta, [
            'first_name' => $nameParts[0] ?? '',
            'last_name'  => (count($nameParts) > 1) ? implode(' ', array_slice($nameParts, 1)) : '',
            'phone'      => $details['phone'] ?? '',
            'email'      => $details['email'] ?? '',
            'address'    => $details['address'] ?? '',
            'city'       => $details['city'] ?? '',
            'postcode'   => $details['postcode'] ?? '',
            'state'      => $details['state'] ?? ''
        ]);
    }

    if (!empty($meta)) {
        $updatePayload['metadata'] = $meta;
    }

    try {
        $stripe->paymentIntents->update($data['paymentIntentId'], $updatePayload);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>