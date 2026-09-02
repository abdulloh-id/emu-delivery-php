<?php

namespace AbdullohId\EmuDelivery;

use SimpleXMLElement;
use db; // Your global database class

class EmuSync
{
    private static function escape($value)
    {
        if ($value === null) return '';

        $search  = ["\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a", "`"];
        $replace = ["\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z", "\\`"];

        return str_replace($search, $replace, $value);
    }

    /**
     * Retrieves a list of towns in Uzbekistan
     */
    public static function getTownList()
    {
        if (!EmuClient::loadCredentials()) {
            return "Error: EMU credentials not found in configuration.";
        }

        $authData = EmuClient::getAuthParams();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><townlist/>');
        $auth = $xml->addChild('auth');
        $auth->addAttribute('extra', $authData['extra']);
        $auth->addAttribute('login', $authData['login']);
        $auth->addAttribute('pass',  $authData['pass']);

        $conditions = $xml->addChild('conditions');
        $conditions->addChild('country', '1219');

        $limit = $xml->addChild('limit');
        $limit->addChild('countall', 'YES');

        // Delegate HTTP request to EmuClient
        $response = EmuClient::sendRequest($xml->asXML());

        if (strpos($response, 'Error:') === 0 || strpos($response, 'Curl error') === 0) {
            return $response;
        }

        $res = simplexml_load_string($response);
        if (!$res) return "Error: Invalid XML response.";

        $town_list = [];
        if (isset($res->town)) {
            foreach ($res->town as $town) {
                $town_list[] = [
                    'id'          => (int)$town->code,
                    'name'        => (string)$town->name,
                    'region_code' => (int)$town->city->code,
                    'region_name' => (string)$town->city->name,
                    'latitude'    => (float)($town->coords['lat'] ?? 0),
                    'longitude'   => (float)($town->coords['lon'] ?? 0),
                ];
            }
        }
        return $town_list;
    }

    /**
     * Retrieves PVZ list from EMU database
     */
    public static function getPvzList($client_code = null)
    {
        if (!EmuClient::loadCredentials()) {
            return "Error: EMU credentials not found in configuration.";
        }

        $authData = EmuClient::getAuthParams();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><pvzlist/>');
        $auth = $xml->addChild('auth');
        $auth->addAttribute('extra', $authData['extra']);
        $auth->addAttribute('login', $authData['login']);
        $auth->addAttribute('pass',  $authData['pass']);

        if ($client_code) {
            $xml->addChild('client_code', $client_code);
        }

        // Delegate HTTP request to EmuClient
        $response = EmuClient::sendRequest($xml->asXML());

        if (strpos($response, 'Error:') === 0 || strpos($response, 'Curl error') === 0) {
            return $response;
        }

        $res = simplexml_load_string($response);
        if (!$res) return "Error: Invalid XML response.";

        $pvz_list = [];
        if (isset($res->pvz)) {
            foreach ($res->pvz as $pvz) {
                $town_attrs = $pvz->town->attributes();
                $pvz_list[] = [
                    'code'              => (int)$pvz->code,
                    'clientcode'        => (string)$pvz->clientcode,
                    'name'              => (string)$pvz->name,
                    'parentcode'        => (int)$pvz->parentcode,
                    'parentname'        => (string)$pvz->parentname,
                    'town'              => (string)$pvz->town,
                    'town_code'         => (string)($town_attrs['code'] ?? ''),
                    'town_regioncode'   => (int)($town_attrs['regioncode'] ?? 0),
                    'town_regionname'   => (string)($town_attrs['regionname'] ?? ''),
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
        return $pvz_list;
    }

    /**
     * Updates EMU region list in local database
     */
    public static function updateRegionList(array $town_list)
    {
        if (empty($town_list)) {
            return [
                'success' => false,
                'message' => 'EMU town list is required',
            ];
        }

        $regions = [];
        foreach ($town_list as $town) {
            $regions[$town['region_code']] = $town['region_name'];
        }

        db::query("TRUNCATE TABLE `emu_region_list`");

        $count = 0;
        foreach ($regions as $id => $name) {
            $name = self::escape($name);

            $ins = db::query("INSERT INTO `emu_region_list` (
            `ID`,
            `NAME`
            ) VALUES (
            '$id',
            '$name'
            )");

            $count++;
        }

        if ($ins['stat'] !== 'success') {
            return [
                'success' => false,
                'message' => 'An error occurred during data entry',
                'debug' => $ins
            ];
        }

        return [
            'success' => true,
            'message' => "Successfully updated EMU region list. Inserted {$count} entries.",
        ];
    }

    /**
     * Updates EMU town list in local database
     */
    public static function updateTownList(array $town_list)
    {
        if (empty($town_list)) {
            return [
                'success' => false,
                'message' => 'EMU town list is required',
            ];
        }

        db::query("TRUNCATE TABLE `emu_town_list`");

        $count = 0;
        foreach ($town_list as $town) {
            $name        = self::escape($town['name']);
            $region_name = self::escape($town['region_name']);

            $ins = db::query("INSERT INTO `emu_town_list` (
            `ID`,
            `NAME`,
            `REGION_CODE`,
            `REGION_NAME`,
            `LATITUDE`,
            `LONGITUDE`
            ) VALUES (
            '{$town['id']}',
            '$name',
            '{$town['region_code']}',
            '$region_name',
            '{$town['latitude']}',
            '{$town['longitude']}'
        )");

            $count++;
        }

        if ($ins['stat'] !== 'success') {
            return [
                'success' => false,
                'message' => 'An error occurred during data entry',
                'debug' => $ins
            ];
        }

        return [
            'success' => true,
            'message' => "Successfully updated EMU town list. Inserted {$count} entries.",
        ];
    }

    /**
     * Updates EMU PVZ list in local database
     */
    public static function updatePvzList(array $pvz_list)
    {
        if (empty($pvz_list)) {
            return [
                'success' => false,
                'message' => 'PVZ list is required',
            ];
        }

        $raw_regions = db::arr("SELECT ID FROM `emu_region_list`") ?? [];
        $uzbekistan_regions = array_column($raw_regions, 'ID');

        db::query("TRUNCATE TABLE `emu_pvz_list`");

        $count_inside = 0;
        $count_outside = 0;
        $ins = ['stat' => 'success'];

        foreach ($pvz_list as $pvz) {
            if (!in_array($pvz['town_regioncode'], $uzbekistan_regions)) {
                $count_outside++;
            }

            if (in_array($pvz['town_regioncode'], $uzbekistan_regions)) {
                $name            = self::escape($pvz['name']);
                $parentname      = self::escape($pvz['parentname']);
                $address         = self::escape($pvz['address']);
                $town            = self::escape($pvz['town']);
                $town_regionname = self::escape($pvz['town_regionname']);
                $worktime        = self::escape($pvz['worktime']);
                $travel          = self::escape($pvz['traveldescription']);
                $comment         = self::escape($pvz['comment']);

                $sql = "INSERT INTO emu_pvz_list (
                `ID`,
                `CLIENT_CODE`,
                `NAME`,
                `PARENTCODE`,
                `PARENTNAME`,
                `TOWN`,
                `TOWN_CODE`,
                `TOWN_REGIONCODE`,
                `TOWN_REGIONNAME`,
                `ADDRESS`,
                `PHONE`,
                `COMMENT`,
                `WORKTIME`,
                `TRAVELDESCRIPTION`,
                `MAXWEIGHT`,
                `ACCEPTCASH`,
                `ACCEPTCARD`,
                `ACCEPTFITTING`,
                `ACCEPTINDIVIDUALS`,
                `LATITUDE`,
                `LONGITUDE`,
                `ACTIVE`
            ) VALUES (
                '{$pvz['code']}',
                '{$pvz['clientcode']}',
                '{$name}',
                '{$pvz['parentcode']}',
                '{$parentname}',
                '{$town}',
                '{$pvz['town_code']}',
                '{$pvz['town_regioncode']}',
                '{$town_regionname}',
                '{$address}',
                '{$pvz['phone']}',
                '{$comment}',
                '{$worktime}',
                '{$travel}',
                '{$pvz['maxweight']}',
                '{$pvz['acceptcash']}',
                '{$pvz['acceptcard']}',
                '{$pvz['acceptfitting']}',
                '{$pvz['acceptindividuals']}',
                '{$pvz['latitude']}',
                '{$pvz['longitude']}',
                1
            )";

                $ins = db::query($sql);
                $count_inside++;
            }
        }

        if ($ins['stat'] !== 'success') {
            return [
                'success' => false,
                'message' => 'An error occurred during data entry',
                'debug' => $ins
            ];
        }

        return [
            'success' => true,
            'message' => "Successfully updated EMU PVZ list. Inserted {$count_inside} entries.",
            'debug' => [
                'counted in Uzbekistan' => $count_inside,
                'counted outside Uzbekistan' => $count_outside,
            ]
        ];
    }
}
