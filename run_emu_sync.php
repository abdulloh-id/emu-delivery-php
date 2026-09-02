<?php

require_once __DIR__ . '/vendor/autoload.php';

use AbdullohId\EmuDelivery\EmuSync;

// 1. Sync Towns & Regions
$townList = EmuSync::getTownList();

if (!is_array($townList)) {
    exit("Town Sync Failed: {$townList}\n");
}

$updReg  = EmuSync::updateRegionList($townList);
$updTown = EmuSync::updateTownList($townList);

print_r($updReg);
print_r($updTown);

// 2. Sync PVZ Pickup Points
$pvzList = EmuSync::getPvzList();

if (!is_array($pvzList)) {
    exit("PVZ Sync Failed: {$pvzList}\n");
}

$updPvz = EmuSync::updatePvzList($pvzList);
print_r($updPvz);