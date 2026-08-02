<?php
function formatOrderNumber($id) {
    $salted_id = $id + 10485760; 
    $hash = strtoupper(base_convert($salted_id, 10, 36));
    return $hash;
}

function formatAuctionID($id) {
    return "AUC-" . str_pad($id, 5, '0', STR_PAD_LEFT);
}
?>