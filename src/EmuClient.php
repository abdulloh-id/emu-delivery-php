<?php

namespace AbdullohId\EmuDelivery;

use AbdullohId\EmuDelivery\Exceptions\EmuException;
use AbdullohId\EmuDelivery\Exceptions\EmuRequestException;
use InvalidArgumentException;
use SimpleXMLElement;

class EmuClient
{
    private static string $api_url = "https://home.courierexe.ru/api/";

    private static ?string $login = null;
    private static ?string $password = null;
    private static ?int $extra = null;

    /**
     * Dynamically configure EMU credentials.
     */
    public static function configure(string $login, string $password, int $extra, ?string $apiUrl = null): void
    {
        self::$login = $login;
        self::$password = $password;
        self::$extra = $extra;

        if ($apiUrl !== null) {
            self::$api_url = $apiUrl;
        }
    }

    /**
     * Checks if API credentials have been configured.
     */
    public static function loadCredentials(): bool
    {
        return (bool) (self::$login && self::$password && self::$extra);
    }

    /**
     * @throws EmuException
     */
    public static function getAuthParams(): array
    {
        if (!self::loadCredentials()) {
            throw new EmuException("EMU credentials are not configured. Call EmuClient::configure() or ensure config.php exists.");
        }

        return [
            'login' => self::$login,
            'pass'  => self::$password,
            'extra' => self::$extra,
        ];
    }

    /**
     * Common cURL communication helper with SSL and timeout hardening.
     *
     * @throws EmuRequestException
     */
    public static function sendRequest(string $xmlString, string $contentType = 'application/xml'): string
    {
        if (!self::loadCredentials()) {
            throw new EmuRequestException("EMU API credentials are not configured.");
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::$api_url,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ["Content-Type: {$contentType}; charset=utf-8"],
            CURLOPT_POSTFIELDS     => $xmlString,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new EmuRequestException("cURL connection error: {$error}");
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new EmuRequestException("EMU API request failed with HTTP status code {$httpCode}.");
        }

        return (string)$response;
    }

    /**
     * @throws EmuException
     * @throws EmuRequestException
     * @throws InvalidArgumentException
     */
    public static function calculateCost(array $deliveryParams): float
    {
        if (empty($deliveryParams['townto']) || empty($deliveryParams['townfrom'])) {
            throw new InvalidArgumentException("Origin and destination cities are required.");
        }
        if (!isset($deliveryParams['weight']) || (float)$deliveryParams['weight'] <= 0) {
            throw new InvalidArgumentException("Valid positive weight is required.");
        }

        $authParams = self::getAuthParams();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><calculator/>');
        $auth = $xml->addChild('auth');
        $auth->addAttribute('login', $authParams['login']);
        $auth->addAttribute('pass', $authParams['pass']);
        $auth->addAttribute('extra', (string)$authParams['extra']);

        $order = $xml->addChild('order');
        $order->addChild('pricetype', $deliveryParams['pricetype'] ?? 'CUSTOMER');

        $sender = $order->addChild('sender');
        $senderTown = $sender->addChild('town', htmlspecialchars($deliveryParams['townfrom_name'] ?? ''));
        $senderTown->addAttribute('code', (string)$deliveryParams['townfrom']);

        $receiver = $order->addChild('receiver');
        $receiverTown = $receiver->addChild('town', htmlspecialchars($deliveryParams['townto_name'] ?? ''));
        $receiverTown->addAttribute('code', (string)$deliveryParams['townto']);

        $order->addChild('weight', (string)(float)$deliveryParams['weight']);

        $response = self::sendRequest($xml->asXML());
        $responseXml = simplexml_load_string($response);

        if (!$responseXml) {
            throw new EmuRequestException("Invalid XML response received from EMU API.");
        }

        if (isset($responseXml->attributes()['error']) && (int)$responseXml->attributes()['error']) {
            $errorMsg = (string)($responseXml->attributes()['errormsg'] ?? 'Unknown API error');
            throw new EmuRequestException("EMU API Error: {$errorMsg}");
        }

        $showPrice = $deliveryParams['show_price'] ?? 'to_home';
        $targetService = ($showPrice === 'to_office') ? 'ДО ОФИСА' : 'НА ДОМ';

        foreach ($responseXml->calc as $calc) {
            if ((string)$calc->service->attributes()['name'] === $targetService) {
                return (float)$calc->attributes()['price'];
            }
        }

        throw new EmuException("Service '$targetService' not found in calculation response.");
    }

    /**
     * Calculates delivery fee from seller's address to an EMU PVZ.
     *
     * @throws EmuException
     * @throws EmuRequestException
     * @throws InvalidArgumentException
     */
    public static function calculateCostPvz(array $params): float
    {
        if (empty($params['pvz_code'])) {
            throw new InvalidArgumentException("PVZ code is required.");
        }
        if (empty($params['townfrom_code'])) {
            throw new InvalidArgumentException("Sender town code is required.");
        }
        if (!isset($params['weight']) || (float)$params['weight'] <= 0) {
            throw new InvalidArgumentException("Valid positive weight is required.");
        }

        $authParams = self::getAuthParams();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><calculator/>');
        $auth = $xml->addChild('auth');
        $auth->addAttribute('login', $authParams['login']);
        $auth->addAttribute('pass', $authParams['pass']);
        $auth->addAttribute('extra', (string)$authParams['extra']);

        $order = $xml->addChild('order');
        $order->addChild('pricetype', 'CUSTOMER');

        $sender = $order->addChild('sender');
        $senderTown = $sender->addChild('town', htmlspecialchars($params['townfrom_name'] ?? ''));
        $senderTown->addAttribute('code', (string)$params['townfrom_code']);

        $receiver = $order->addChild('receiver');
        if (!empty($params['townto_code'])) {
            $receiverTown = $receiver->addChild('town', htmlspecialchars($params['townto_name'] ?? ''));
            $receiverTown->addAttribute('code', (string)$params['townto_code']);
        }
        $receiver->addChild('pvz', (string)(int)$params['pvz_code']);

        $order->addChild('service', '1');

        $packages = $order->addChild('packages');
        $package = $packages->addChild('package');
        $package->addAttribute('mass', (string)(float)$params['weight']);

        $response = self::sendRequest($xml->asXML());
        $responseXml = simplexml_load_string($response);

        if (!$responseXml) {
            throw new EmuRequestException("Invalid XML response received from EMU API.");
        }

        if (isset($responseXml->attributes()['error']) && (int)$responseXml->attributes()['error']) {
            $errorMsg = (string)($responseXml->attributes()['errormsg'] ?? 'Unknown API error');
            throw new EmuRequestException("EMU API Error: {$errorMsg}");
        }

        foreach ($responseXml->calc as $calc) {
            if ((string)$calc->service->attributes()['name'] === 'ДО ОФИСА') {
                return (float)$calc->attributes()['price'];
            }
        }

        throw new EmuException("PVZ service 'ДО ОФИСА' not found in calculation response.");
    }

    /**
     * @throws EmuException
     * @throws EmuRequestException
     * @throws InvalidArgumentException
     */
    public static function createOrder(array $orderData): array
    {
        if (empty($orderData['orderno'])) {
            throw new InvalidArgumentException("Order number (orderno) is required.");
        }

        $authParams = self::getAuthParams();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><neworder></neworder>');
        $xml->addAttribute('newfolder', 'NO');

        $auth = $xml->addChild('auth');
        $auth->addAttribute('login', $authParams['login']);
        $auth->addAttribute('pass', $authParams['pass']);
        $auth->addAttribute('extra', (string)$authParams['extra']);

        $order = $xml->addChild('order');
        $order->addAttribute('orderno', (string)$orderData['orderno']);

        $sections = ['sender', 'receiver'];
        foreach ($sections as $key) {
            $node = $order->addChild($key);
            $node->addChild('person', htmlspecialchars($orderData[$key]['person'] ?? ''));
            $node->addChild('phone', htmlspecialchars($orderData[$key]['phone'] ?? ''));
            $town = $node->addChild('town', htmlspecialchars($orderData[$key]['town'] ?? ''));

            if ($key === 'receiver') {
                if (isset($orderData['receiver']['town_regioncode'])) {
                    $town->addAttribute('regioncode', htmlspecialchars($orderData['receiver']['town_regioncode']));
                }
                if (isset($orderData['receiver']['country'])) {
                    $town->addAttribute('country', htmlspecialchars($orderData['receiver']['country']));
                }
            }

            $node->addChild('company', htmlspecialchars($orderData[$key]['company'] ?? ''));
            $node->addChild('address', htmlspecialchars($orderData[$key]['address'] ?? ''));
            if (isset($orderData[$key]['date'])) {
                $node->addChild('date', htmlspecialchars($orderData[$key]['date']));
            }
        }

        $fields = ['weight', 'quantity', 'paytype', 'service', 'price', 'enclosure', 'instruction'];
        foreach ($fields as $field) {
            $order->addChild($field, htmlspecialchars($orderData[$field] ?? ''));
        }

        $response = self::sendRequest($xml->asXML(), 'application/xml');
        $xmlResponse = simplexml_load_string($response);

        if ($xmlResponse === false) {
            throw new EmuRequestException("Invalid XML response received from EMU API.");
        }

        if (isset($xmlResponse->createorder)) {
            $co = $xmlResponse->createorder;
            return [
                'orderno'    => (string)$co['orderno'],
                'barcode'    => (string)$co['barcode'],
                'error'      => (int)$co['error'],
                'errormsg'   => (string)$co['errormsg'],
                'errormsgru' => (string)$co['errormsgru'],
                'orderprice' => (float)$co['orderprice'],
            ];
        }

        throw new EmuRequestException("Unexpected response format received from EMU API.");
    }
}
