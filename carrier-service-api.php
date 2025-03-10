<?php 
$shop = "goodomensstore.myshopify.com";
$access_token = "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";
$data = [
    "carrier_service" => [
        "name" => "Custom Shipping Rate",
        "callback_url" => "https://projectsofar.info/shopify-apps/index.php", // Update with your actual app URL
        "service_discovery" => true,
        "format" => "json"
    ]
]; 
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://$shop/admin/api/2024-01/carrier_services.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-Shopify-Access-Token: $access_token"
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
