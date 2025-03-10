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
            ]
        ]
    ];

    // Fetch dynamic shipping price
    $shippingPrice = getShippingPrice($shipmentData);

    // Shopify expects price in cents
    $shipping_rates = [
        "rates" => [
            [
                "service_name" => "Trans Global Express",
                "service_code" => "EXPRESS",
                "total_price" => $shippingPrice * 100, // Convert to cents
                "currency" => "USD"
            ]
        ]
    ];

    echo json_encode($shipping_rates);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
