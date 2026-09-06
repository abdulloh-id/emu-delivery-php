<?php

namespace AbdullohId\EmuDelivery;

use AbdullohId\EmuDelivery\Contracts\EmuStorageInterface;
use AbdullohId\EmuDelivery\Exceptions\EmuException;
use AbdullohId\EmuDelivery\Exceptions\EmuRequestException;
use SimpleXMLElement;

class EmuSync
{
    private ?EmuStorageInterface $storage;

    public function __construct(?EmuStorageInterface $storage = null)
    {
        $this->storage = $storage;
    }

    /**
     * Retrieves a list of towns in Uzbekistan.
     *
     * @throws EmuException
     * @throws EmuRequestException
     */
    public static function getTownList(): array
    {
        $authData = EmuClient::getAuthParams();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><townlist/>');
        $auth = $xml->addChild('auth');
        $auth->addAttribute('extra', (string)$authData['extra']);
        $auth->addAttribute('login', $authData['login']);
        $auth->addAttribute('pass',  $authData['pass']);

        $conditions = $xml->addChild('conditions');
        $conditions->addChild('country', '1219');

        $limit = $xml->addChild('limit');
        $limit->addChild('countall', 'YES');

        $response = EmuClient::sendRequest($xml->asXML());
        $res = simplexml_load_string($response);

        if (!$res) {
            throw new EmuRequestException("Invalid XML response received from EMU town list endpoint.");
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
     * @throws EmuException
     * @throws EmuRequestException
     */
    public static function getPvzList(?string $clientCode = null): array
    {
        $authData = EmuClient::getAuthParams();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><pvzlist/>');
        $auth = $xml->addChild('auth');
        $auth->addAttribute('extra', (string)$authData['extra']);
        $auth->addAttribute('login', $authData['login']);
        $auth->addAttribute('pass',  $authData['pass']);

        if ($clientCode !== null) {
            $xml->addChild('client_code', $clientCode);
        }

        $response = EmuClient::sendRequest($xml->asXML());
        $res = simplexml_load_string($response);

        if (!$res) {
            throw new EmuRequestException("Invalid XML response received from EMU PVZ list endpoint.");
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
     * @throws EmuException
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
     * @throws EmuException
     */
    public function updateTownList(array $townList): bool
    {
        $this->ensureStorageConfigured();
        return $this->storage->saveTowns($townList);
    }

    /**
     * Updates PVZ list in storage.
     *
     * @throws EmuException
     */
    public function updatePvzList(array $pvzList): bool
    {
        $this->ensureStorageConfigured();
        return $this->storage->savePvzList($pvzList);
    }

    /**
     * @throws EmuException
     */
    private function ensureStorageConfigured(): void
    {
        if ($this->storage === null) {
            throw new EmuException("No storage repository provided. Pass an EmuStorageInterface implementation to EmuSync.");
        }
    }
}