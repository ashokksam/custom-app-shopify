<?php 
$shop = "goodomensstore.myshopify.com";
$access_token = "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";
//shpua_b3f3cc6d7a68465ecef638d472496ecb
$url = "https://$shop/admin/api/2024-01/carrier_services.json";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-Shopify-Access-Token: $access_token"
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
