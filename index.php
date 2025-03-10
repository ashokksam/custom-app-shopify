<?php

$shop = "goodomensstore.myshopify.com";
$client_id = "d25a74cfbfd326c455697b563d36f2d0";
$scopes = "read_products,write_products, read_shipping, write_shipping";
$redirect_uri = "https://projectsofar.com/shopify-apps-new/callback.php";

$url = "https://$shop/admin/oauth/authorize?client_id=$client_id&scope=$scopes&redirect_uri=$redirect_uri";
header("Location: $url");
exit;

?>
<?php
/*header("Content-Type: application/json");

$shipping_rates = [
    "rates" => [
        [
            "service_name" => "trans global express",
            "service_code" => "EXPRESS",
            "total_price" => 1000, // Price in cents ($10.00)
            "currency" => "USD"
        ]
    ]
];

echo json_encode($shipping_rates);


*/
?>
<?php
// header("Content-Type: application/json");
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// // Log the raw request body
// $input = file_get_contents("php://input");
// file_put_contents('log.txt', $input); // Save for debugging

// $data = json_decode($input, true);

// if (!$data) {
//     echo json_encode(["error" => "Invalid JSON data received."]);
//     exit;
// }

// if (!isset($data['rate'])) {
//     echo json_encode(["error" => "Missing 'rate' data from Shopify."]);
//     exit;
// }

// // Extract shipping details
// $shipping_address = $data['rate']['destination'] ?? [];
// $cart_items = $data['rate']['items'] ?? [];

// // Log extracted data for debugging
// file_put_contents('log.txt', print_r($shipping_address, true), FILE_APPEND);
// file_put_contents('log.txt', print_r($cart_items, true), FILE_APPEND);

// echo json_encode(["success" => true, "message" => "Data received successfully."]);
?>


<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle CORS and preflight requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// if(isset($_REQUEST['link_source']) && $_REQUEST['link_source'] == 'search'){
//     return;
// }
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// Function to log messages
function logMessage($message) {
    $logFile = __DIR__ . '/shipping_debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

logMessage("Script started.");

function getShippingPrice($shipmentData) {
    $url = "https://staging.services3.transglobalexpress.co.uk/Book/V2/BookShipment/";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode("oWF3e8e9mT:7D[eUVv4we")
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($shipmentData));

    $response = curl_exec($ch);
    $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    logMessage("API Request: " . json_encode($shipmentData));
    logMessage("API Response: $response");
    logMessage("HTTP Status Code: $httpStatusCode");

    if ($curlError) {
        logMessage("cURL error: $curlError");
    }

    curl_close($ch);

    if ($httpStatusCode != 200) {
        logMessage("API request failed with status code: $httpStatusCode. Response: $response");
        throw new Exception("API request failed. HTTP Status Code: $httpStatusCode");
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['Status']) && $responseData['Status'] === 'SUCCESS' && isset($responseData['OrderInvoice']['TotalNet'])) {
        return $responseData['OrderInvoice']['TotalNet'];
    } else {
        logMessage("Shipping price (TotalNet) not found in response: " . json_encode($responseData));
        throw new Exception("Shipping price (TotalNet) not found in the response.");
    }
}

try {
    $input = file_get_contents('php://input');
    logMessage("Received Shopify request: $input");

    $cartData = json_decode($input, true);

    if (!$cartData || !isset($cartData['rate']['items'])) {
        logMessage("Invalid request data: $input");
        throw new Exception("Invalid request data.");
    }

    $total_weight = 0;
    $total_price = 0;
    $item_count = count($cartData['rate']['items']);

    foreach ($cartData['rate']['items'] as $item) {
        $total_weight += $item['grams'];
        $total_price += $item['price'];
    }

    logMessage("Total weight: $total_weight, Total price: $total_price, Item count: $item_count");

    $shipmentData = [
        "Credentials" => [
            "APIKey" => "oWF3e8e9mT",
            "Password" => "7D[eUVv4we"
        ],
        "Shipment" => [
            "Consignment" => [
                "ItemType" => "Parcel",
                "ItemsAreStackable" => true,
                "ConsignmentSummary" => "Shopify Cart",
                "ConsignmentValue" => $total_price / 100,
                "ReasonForExport" => "Sale",
                "ConsignmentCurrency" => [
                    "CurrencyCode" => "GBP"
                ],
                "Packages" => [
                    [
                        "Weight" => $total_weight / 1000,
                        "Length" => 20.0,
                        "Width" => 18.0,
                        "Height" => 12.00,
                        "CommodityDetails" => [
                            [
                                "CommodityCode" => "49029000",
                                "CommodityDescription" => "Shopify Cart Items",
                                "CountryOfOrigin" => [
                                    "CountryCode" => "DE"
                                ],
                                "NumberOfUnits" => $item_count,
                                "UnitValue" => ($total_price / 100) / max(1, $item_count),
                                "UnitWeight" => ($total_weight / 1000) / max(1, $item_count),
                                "ProductCode" => " "
                            ]
                        ]
                    ]
                ]
            ],
            "CollectionAddress" => [
                "Forename" => "Shopify",
                "Surname" => "Store",
                "EmailAddress" => "store@example.com",
                "TelephoneNumber" => "07395505038",
                "MobileNumber" => "07395505038",
                "CompanyName" => "Shopify Store",
                "AddressLineOne" => "123 Main St",
                "City" => "Edinburgh",
                "Postcode" => "EH12 7TB",
                "Country" => [
                    "CountryID" => 231
                ],
                "IsAddressResidential" => false
            ],
            "DeliveryAddress" => [
                "Forename" => $cartData['rate']['destination']['first_name'] ?? 'test',
                "Surname" => $cartData['rate']['destination']['last_name'] ?? 'test',
                "EmailAddress" => $cartData['rate']['destination']['email'] ?? 'test@gmail.com',
                "TelephoneNumber" => $cartData['rate']['destination']['phone'] ?? '8787878787',
                "CompanyName" => $cartData['rate']['destination']['company'] ?? 'test',
                "AddressLineOne" => $cartData['rate']['destination']['address1'] ?? '34th Street',
                "City" => $cartData['rate']['destination']['city'] ?? 'Brooklyn',
                "Postcode" => $cartData['rate']['destination']['zip'] ?? '11232',
                "Country" => [
                    "CountryCode" => $cartData['rate']['destination']['country_code'] ?? 'QA'
                ],
                "IsAddressResidential" => true
            ]
        ],
        "BookDetails" => [
            "ServiceID" => 219,
            "YourReference" => "Shopify Cart",
            "ShippingCharges" => 0.00,
            "IOSSNumber" => "",
            "Collection" => [
                "CollectionDate" => date('Y-m-d', strtotime('+1 day')),
                "ReadyFrom" => "09:30",
                "CollectionOptionID" => 5
            ],
            "Insurance" => [
                "CoverValue" => $total_price,
                "ExcessValue" => 0.0,
                "GoodsAreNew" => true,
                "GoodsAreFragile" => false
            ]
        ]
    ];

    logMessage("Shipment data prepared: " . json_encode($shipmentData));

    try {
        $shippingPrice = getShippingPrice($shipmentData);
    } catch (Exception $e) {
        logMessage("Error fetching shipping price: " . $e->getMessage());
        $shippingPrice = 10.00; // Default fallback shipping price
    }

    $response = [
        "rates" => [
            [
                "service_name" => "Express Shipping",
                "service_code" => "EXPRESS",
                "total_price" => round($shippingPrice * 100),
                "currency" => "GBP"
            ]
        ]
    ];

    logMessage("Response sent to Shopify: " . json_encode($response));
    echo json_encode($response);
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// Log any PHP fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error) {
        logMessage("Fatal Error: " . json_encode($error));
    }
});

logMessage("Script finished.");

?>





<?php
/*header("Content-Type: application/json");

// Fetch the request data (Shopify sends shipping request as JSON)
$input = file_get_contents("php://input");
$requestData = json_decode($input, true);

// Default shipping price in cents
$basePrice = 1000; // $10.00

// Check if shipping address exists in the request
if (isset($requestData['rate']['destination'])) {
    $destination = $requestData['rate']['destination'];
    
    $country = $destination['country'] ?? 'US'; // Default: US
    $province = $destination['province'] ?? '';
    $postalCode = $destination['postal_code'] ?? '';

    // Dynamic pricing based on country
    if ($country === 'US') {
        $basePrice = 1000; // $10.00
    } elseif ($country === 'CA') {
        $basePrice = 1500; // $15.00 for Canada
    } elseif ($country === 'UK') {
        $basePrice = 2000; // $20.00 for UK
    } else {
        $basePrice = 2500; // $25.00 for other countries
    }

    // Extra charge based on postal code (Example: Extra $5 for a specific region)
    if ($postalCode === 'K1N 5T2') {
        $basePrice += 500; // Add $5.00
    }
}

// Build the shipping rates response
$shipping_rates = [
    "rates" => [
        [
            "service_name" => "Trans Global Express",
            "service_code" => "EXPRESS",
            "total_price" => $basePrice, // Dynamic price in cents
            "currency" => "USD"
        ]
    ]
];

// Return JSON response to Shopify
echo json_encode($shipping_rates);*/
?>




<?php 
exit;
$shop = "goodomensstore.myshopify.com";
$client_id = "f6487eb7ff062d0783005b5f1f268cfe";
$scopes = "read_products,write_products, read_shipping, write_shipping";
$redirect_uri = "https://projectsofar.com/shopify-apps/callback.php";

$url = "https://$shop/admin/oauth/authorize?client_id=$client_id&scope=$scopes&redirect_uri=$redirect_uri";
header("Location: $url");
exit;

?>