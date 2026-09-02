<?php

namespace AbdullohId\EmuDelivery;

use SimpleXMLElement;

class EmuClient
{
    private static $api_url = "https://home.courierexe.ru/api/";

    private static ?string $login = null;
    private static ?string $password = null;
    private static ?int $extra = null;

    /**
     * Loads config once and verifies credentials.
     */
    public static function loadCredentials(): bool
    {
        if (self::$login && self::$password && self::$extra) {
            return true;
        }

        $config = require __DIR__ . "/../config.php"; // Adjust path as needed
        self::$login    = $config['emu']['login'] ?? null;
        self::$password = $config['emu']['password'] ?? null;
        self::$extra    = $config['emu']['extra'] ?? null;

        return (bool)(self::$login && self::$password && self::$extra);
    }

    public static function getAuthParams(): array
    {
        self::loadCredentials();
        return [
            'login' => self::$login,
            'pass'  => self::$password,
            'extra' => self::$extra,
        ];
    }

    /**
     * Common cURL communication helper.
     */
    public static function sendRequest(string $xml_string, string $content_type = 'application/xml')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: {$content_type}; charset=utf-8"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_string);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = 'Curl error: ' . curl_error($ch);
            curl_close($ch);
            return $error;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code != 200 || !$response) {
            return "Error: API request failed with HTTP code $http_code.";
        }

        return $response;
    }

    public static function calculateCost(array $delivery_params)
    {
        if (!self::loadCredentials()) {
            return "Error: EMU credentials not found in configuration.";
        }

        // Validation
        if (empty($delivery_params['townto']) || empty($delivery_params['townfrom'])) {
            return "Error: Origin and destination cities are required.";
        }
        if (!isset($delivery_params['weight']) || $delivery_params['weight'] <= 0) {
            return "Error: Valid weight is required.";
        }

        // Build XML
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><calculator/>');
        $auth = $xml->addChild('auth');
        $auth->addAttribute('login', self::$login);
        $auth->addAttribute('pass', self::$password);
        $auth->addAttribute('extra', self::$extra);

        $order = $xml->addChild('order');
        $order->addChild('pricetype', $delivery_params['pricetype'] ?? 'CUSTOMER');

        $sender = $order->addChild('sender');
        $sender_town = $sender->addChild('town', htmlspecialchars($delivery_params['townfrom_name'] ?? ''));
        $sender_town->addAttribute('code', $delivery_params['townfrom']);

        $receiver = $order->addChild('receiver');
        $receiver_town = $receiver->addChild('town', htmlspecialchars($delivery_params['townto_name'] ?? ''));
        $receiver_town->addAttribute('code', $delivery_params['townto']);

        $order->addChild('weight', (float)$delivery_params['weight']);

        // Execute Request
        $response = self::sendRequest($xml->asXML());
        if (strpos($response, 'Error:') === 0 || strpos($response, 'Curl error') === 0) {
            return $response;
        }

        $response_xml = simplexml_load_string($response);
        if (!$response_xml) return "Error: Invalid XML response.";

        // API Level Error handling
        if (isset($response_xml->attributes()['error']) && (int)$response_xml->attributes()['error']) {
            return "Error: " . ($response_xml->attributes()['errormsg'] ?? 'Unknown API error');
        }

        // Logic to pick service
        $show_price = $delivery_params['show_price'] ?? 'to_home';
        $target_service = ($show_price === 'to_office') ? 'ДО ОФИСА' : 'НА ДОМ';

        foreach ($response_xml->calc as $calc) {
            if ((string)$calc->service->attributes()['name'] === $target_service) {
                return (float)$calc->attributes()['price'];
            }
        }

        return "Error: Service '$target_service' not found.";
    }


    /**
     * Calculates delivery fee from seller's address to an EMU PVZ
     */
    public static function calculateCostPvz(array $params)
    {
        if (!self::loadCredentials()) {
            return "Error: EMU credentials not found in configuration.";
        }

        if (empty($params['pvz_code'])) {
            return "Error: PVZ code is required.";
        }
        if (empty($params['townfrom_code'])) {
            return "Error: Sender town code is required.";
        }
        if (!isset($params['weight']) || $params['weight'] <= 0) {
            return "Error: Valid weight is required.";
        }

        $xml   = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><calculator/>');
        $auth  = $xml->addChild('auth');
        $auth->addAttribute('login', self::$login);
        $auth->addAttribute('pass',  self::$password);
        $auth->addAttribute('extra', self::$extra);

        $order = $xml->addChild('order');
        $order->addChild('pricetype', 'CUSTOMER');

        $sender      = $order->addChild('sender');
        $sender_town = $sender->addChild('town', htmlspecialchars($params['townfrom_name'] ?? ''));
        $sender_town->addAttribute('code', $params['townfrom_code']);

        $receiver = $order->addChild('receiver');
        if (!empty($params['townto_code'])) {
            $receiver_town = $receiver->addChild('town', htmlspecialchars($params['townto_name'] ?? ''));
            $receiver_town->addAttribute('code', $params['townto_code']);
        }
        $receiver->addChild('pvz', (int)$params['pvz_code']);

        $order->addChild('service', 1);

        $packages = $order->addChild('packages');
        $package  = $packages->addChild('package');
        $package->addAttribute('mass', (float)$params['weight']);

        $response = self::sendRequest($xml->asXML());

        if (strpos($response, 'Error:') === 0 || strpos($response, 'Curl error') === 0) {
            return $response;
        }

        $response_xml = simplexml_load_string($response);
        if (!$response_xml) return "Error: Invalid XML response.";

        if (isset($response_xml->attributes()['error']) && (int)$response_xml->attributes()['error']) {
            return "Error: " . ($response_xml->attributes()['errormsg'] ?? 'Unknown API error');
        }

        foreach ($response_xml->calc as $calc) {
            if ((string)$calc->service->attributes()['name'] === 'ДО ОФИСА') {
                return (float)$calc->attributes()['price'];
            }
        }

        return "Error: PVZ service 'ДО ОФИСА' not found in response.";
    }

    public static function createOrder(array $orderData)
    {
        if (!self::loadCredentials()) {
            return "Error: EMU credentials not found.";
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><neworder></neworder>');
        $xml->addAttribute('newfolder', 'NO');

        $auth = $xml->addChild('auth');
        $auth->addAttribute('login', self::$login);
        $auth->addAttribute('pass', self::$password);
        $auth->addAttribute('extra', self::$extra);

        $order = $xml->addChild('order');
        $order->addAttribute('orderno', $orderData['orderno']);

        // Build Sender/Receiver (Standardizing logic)
        $sections = ['sender', 'receiver'];
        foreach ($sections as $key) {
            $node = $order->addChild($key);
            $node->addChild('person', htmlspecialchars($orderData[$key]['person'] ?? ''));
            $node->addChild('phone', htmlspecialchars($orderData[$key]['phone'] ?? ''));
            $town = $node->addChild('town', htmlspecialchars($orderData[$key]['town'] ?? ''));

            if ($key === 'receiver') {
                if (isset($orderData['receiver']['town_regioncode']))
                    $town->addAttribute('regioncode', htmlspecialchars($orderData['receiver']['town_regioncode']));
                if (isset($orderData['receiver']['country']))
                    $town->addAttribute('country', htmlspecialchars($orderData['receiver']['country']));
            }

            $node->addChild('company', htmlspecialchars($orderData[$key]['company'] ?? ''));
            $node->addChild('address', htmlspecialchars($orderData[$key]['address'] ?? ''));
            if (isset($orderData[$key]['date'])) $node->addChild('date', htmlspecialchars($orderData[$key]['date']));
        }

        // Common Fields
        $fields = ['weight', 'quantity', 'paytype', 'service', 'price', 'enclosure', 'instruction'];
        foreach ($fields as $field) {
            $order->addChild($field, htmlspecialchars($orderData[$field] ?? ''));
        }

        // Execute Request
        $response = self::sendRequest($xml->asXML(), 'application/xml');
        if (strpos($response, 'Error:') === 0) return $response;

        $xml_response = simplexml_load_string($response);
        if ($xml_response === false) return "Error: Invalid XML response";

        if (isset($xml_response->createorder)) {
            $co = $xml_response->createorder;
            return [
                'orderno'    => (string)$co['orderno'],
                'barcode'    => (string)$co['barcode'],
                'error'      => (int)$co['error'],
                'errormsg'   => (string)$co['errormsg'],
                'errormsgru' => (string)$co['errormsgru'],
                'orderprice' => (float)$co['orderprice']
            ];
        }

        return "Error: Unexpected response format.";
    }
}
