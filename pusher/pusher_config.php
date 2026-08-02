<?php
require_once __DIR__ . '/../vendor/autoload.php'; 

$pusher_app_id = "2098880";         
$pusher_app_key = "c86d192b04d14e240a9f";        
$pusher_app_secret = "cd29579ada27e840ec97";  
$pusher_cluster = "ap1";                 

$options = [
    'cluster' => $pusher_cluster,
    'useTLS' => true,
    'curl_options' => [
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0
    ]
];

$pusher = new Pusher\Pusher(
    $pusher_app_key,
    $pusher_app_secret,
    $pusher_app_id,
    $options
);
?>
