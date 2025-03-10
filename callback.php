<?php
/*$client_id = "d25a74cfbfd326c455697b563d36f2d0";
$client_secret = "f9082a1cb76cef9bac1bd9700023e70d";
$code = $_GET['code'];
$shop = $_GET['shop'];

$access_token_url = "https://$shop/admin/oauth/access_token";
$data = [
    "client_id" => $client_id,
    "client_secret" => $client_secret,
    "code" => $code
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $access_token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

$response = curl_exec($ch);
curl_close($ch); 

$token_data = json_decode($response, true);
$access_token = $token_data['access_token'];
 echo "Access Token: " . $access_token;
 echo 'APP installed';*/
//  exit;
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
    if ($postalCode === 'SW7 5JW') {
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
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function getShippingPrice($shipmentData) {
    $url = "https://staging.services3.transglobalexpress.co.uk/Book/V2/BookShipment/";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode("oWF3e8e9mT:7D[eUVv4we")
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($shipmentData));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }

    $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpStatusCode != 200) {
        throw new Exception("API request failed. HTTP Status Code: $httpStatusCode. Response: $response");
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['Status']) && $responseData['Status'] === 'SUCCESS') {
        return $responseData['OrderInvoice']['TotalNet'] ?? 0;
    } else {
        throw new Exception("Shipping price not found.");
    }
}

try {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data || !isset($data['rate'])) {
        throw new Exception("Invalid request data.");
    }

    $shipping_address = $data['rate']['destination'];
    $cart_items = $data['rate']['items'] ?? [];

    // Calculate total weight
    $totalWeight = 0;
    foreach ($cart_items as $item) {
        $totalWeight += ($item['grams'] ?? 0) * ($item['quantity'] ?? 1);
    }

    // Prepare shipment data
    $shipmentData = [
        "Credentials" => [
            "APIKey" => "oWF3e8e9mT",
            "Password" => "7D[eUVv4we"
        ],
        "Shipment" => [
            "DeliveryAddress" => [
                "Forename" => $shipping_address['first_name'] ?? '',
                "Surname" => $shipping_address['last_name'] ?? '',
                "EmailAddress" => $shipping_address['email'] ?? '',
                "TelephoneNumber" => $shipping_address['phone'] ?? '',
                "AddressLineOne" => $shipping_address['address1'] ?? '',
                "City" => $shipping_address['city'] ?? '',
                "Postcode" => $shipping_address['zip'] ?? '',
                "Country" => [
                    "CountryCode" => $shipping_address['country_code'] ?? 'US'
                ]
            ],
            "Weight" => $totalWeight > 0 ? $totalWeight / 1000 : 1 // Convert grams to kg
        ]
    ];

    // Fetch dynamic shipping price
    $shippingPrice = getShippingPrice($shipmentData);

    // Shopify expects price in cents
    if($shippingPrice){
         $shipping_rates = [
        "rates" => [
            [
                "service_name" => "Trans Global Express",
                "service_code" => "EXPRESS",
                "total_price" => $shippingPrice * 1000000, // Convert to cents
                "currency" => "USD"
            ]
        ]
        ];
    }else{
         $shipping_rates = [
        "rates" => [
            [
                "service_name" => "Trans Global Express",
                "service_code" => "EXPRESS",
                "total_price" => 1000000, // Convert to cents
                "currency" => "USD"
            ]
        ]
    ];
    }
   

    echo json_encode($shipping_rates);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
