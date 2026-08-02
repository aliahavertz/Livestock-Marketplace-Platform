<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$auction_id = $_GET['auction_id'] ?? null;
$payment_id = $_GET['payment_id'] ?? null;

if (!$auction_id || !$payment_id) {
    die("Error: Missing transaction information.");
}

ob_start();
$_GET['pdf_mode'] = true; 
include 'receipt_deposit.php'; 
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true); 
$options->set('defaultFont', 'DejaVu Serif'); 

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

$filename = "Receipt_Auction_" . htmlspecialchars($auction_id) . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit;