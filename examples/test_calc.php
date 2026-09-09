<?php

require_once __DIR__ . '/vendor/autoload.php';

use AbdullohId\MeasoftDelivery\MeasoftClient;

$params = [
    'townfrom_code' => 272765, // Zangiata (Tashkent Region)
    'townfrom_name' => 'Ташкент',
    'pvz_code'      => 40,
    'weight'        => 0.55,   // Crossed the 0.5kg limit
];

$result = MeasoftClient::calculateCostPvz($params);

if (is_numeric($result)) {
    echo "Calculated Shipping Cost: {$result} UZS\n";
} else {
    echo "Calculation Failed: {$result}\n";
}
