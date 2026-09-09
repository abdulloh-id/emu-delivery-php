<?php

namespace AbdullohId\MeasoftDelivery;

use AbdullohId\MeasoftDelivery\Contracts\MeasoftStorageInterface;
use AbdullohId\MeasoftDelivery\Exceptions\MeasoftException;
use AbdullohId\MeasoftDelivery\Exceptions\MeasoftRequestException;
use SimpleXMLElement;

class MeasoftSync
{
    private ?MeasoftStorageInterface $storage;

    public function __construct(?MeasoftStorageInterface $storage = null)
    {
        $this->storage = $storage;
    }

    /**
     * Retrieves a list of towns for the configured country.
     *
     * @throws MeasoftException
     * @throws MeasoftRequestException
     */
    public static function getTownList(): array
    {
        $authData = MeasoftClient::getAuthParams();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><townlist/>');
        $auth = $xml->addChild('auth');
        $auth->addAttribute('extra', (string)$authData['extra']);
        $auth->addAttribute('login', $authData['login']);
        $auth->addAttribute('pass',  $authData['pass']);

        $conditions = $xml->addChild('conditions');
        $conditions->addChild('country', (string)MeasoftClient::getCountryCode());

        $limit = $xml->addChild('limit');
        $limit->addChild('countall', 'YES');

        $response = MeasoftClient::sendRequest($xml->asXML());
        $res = simplexml_load_string($response);

        if (!$res) {
            throw new MeasoftRequestException("Invalid XML response received from EMU town list endpoint.");
        }

        $townList = [];
        if (isset($res->town)) {
            foreach ($res->town as $town) {
                $townList[] = [
                    'id'          => (int)$town->code,
                    'name'        => (string)$town->name,
                    'region_code' => (int)$town->city->code,
                    'region_name' => (string)$town->city->name,
                    'latitude'    => (float)($town->coords['lat'] ?? 0),
                    'longitude'   => (float)($town->coords['lon'] ?? 0),
                ];
            }
        }

        return $townList;
    }

    /**
     * Retrieves PVZ list from EMU database.
     *
     * @throws MeasoftException
     * @throws MeasoftRequestException
     */
    public static function getPvzList(?string $clientCode = null): array
    {
        $authData = MeasoftClient::getAuthParams();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><pvzlist/>');
        $auth = $xml->addChild('auth');
        $auth->addAttribute('extra', (string)$authData['extra']);
        $auth->addAttribute('login', $authData['login']);
        $auth->addAttribute('pass',  $authData['pass']);

        if ($clientCode !== null) {
            $xml->addChild('client_code', $clientCode);
        }

        $response = MeasoftClient::sendRequest($xml->asXML());
        $res = simplexml_load_string($response);

        if (!$res) {
            throw new MeasoftRequestException("Invalid XML response received from EMU PVZ list endpoint.");
        }

        $pvzList = [];
        if (isset($res->pvz)) {
            foreach ($res->pvz as $pvz) {
                $townAttrs = $pvz->town->attributes();
                $pvzList[] = [
                    'code'              => (int)$pvz->code,
                    'clientcode'        => (string)$pvz->clientcode,
                    'name'              => (string)$pvz->name,
                    'parentcode'        => (int)$pvz->parentcode,
                    'parentname'        => (string)$pvz->parentname,
                    'town'              => (string)$pvz->town,
                    'town_code'         => (string)($townAttrs['code'] ?? ''),
                    'town_regioncode'   => (int)($townAttrs['regioncode'] ?? 0),
                    'town_regionname'   => (string)($townAttrs['regionname'] ?? ''),
                    'address'           => (string)$pvz->address,
                    'phone'             => (string)$pvz->phone,
                    'comment'           => (string)$pvz->comment,
                    'worktime'          => (string)$pvz->worktime,
                    'traveldescription' => (string)$pvz->traveldescription,
                    'maxweight'         => (int)$pvz->maxweight,
                    'acceptcash'        => (string)$pvz->acceptcash === 'YES' ? 1 : 0,
                    'acceptcard'        => (string)$pvz->acceptcard === 'YES' ? 1 : 0,
                    'acceptfitting'     => (string)$pvz->acceptfitting === 'YES' ? 1 : 0,
                    'acceptindividuals' => (string)$pvz->acceptindividuals === 'YES' ? 1 : 0,
                    'latitude'          => (float)$pvz->latitude,
                    'longitude'         => (float)$pvz->longitude,
                ];
            }
        }

        return $pvzList;
    }

    /**
     * Updates regions in storage.
     *
     * @throws MeasoftException
     */
    public function updateRegionList(array $townList): bool
    {
        $this->ensureStorageConfigured();

        $regions = [];
        foreach ($townList as $town) {
            if (isset($town['region_code'], $town['region_name'])) {
                $regions[$town['region_code']] = $town['region_name'];
            }
        }

        return $this->storage->saveRegions($regions);
    }

    /**
     * Updates towns in storage.
     *
     * @throws MeasoftException
     */
    public function updateTownList(array $townList): bool
    {
        $this->ensureStorageConfigured();
        return $this->storage->saveTowns($townList);
    }

    /**
     * Updates PVZ list in storage.
     *
     * @throws MeasoftException
     */
    public function updatePvzList(array $pvzList): bool
    {
        $this->ensureStorageConfigured();
        return $this->storage->savePvzList($pvzList);
    }

    /**
     * @throws MeasoftException
     */
    private function ensureStorageConfigured(): void
    {
        if ($this->storage === null) {
            throw new MeasoftException("No storage repository provided. Pass an MeasoftStorageInterface implementation to MeasoftSync.");
        }
    }
}