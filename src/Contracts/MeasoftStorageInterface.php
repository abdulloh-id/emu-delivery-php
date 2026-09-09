<?php

namespace AbdullohId\MeasoftDelivery\Contracts;

interface MeasoftStorageInterface
{
    /**
     * Store or replace regions.
     * $regions format: [ 'region_code' => 'Region Name', ... ]
     */
    public function saveRegions(array $regions): bool;

    /**
     * Store or replace towns.
     * $towns format: [ ['id' => 1, 'name' => '...', 'region_code' => 10, ...], ... ]
     */
    public function saveTowns(array $towns): bool;

    /**
     * Store or replace PVZ pickup points.
     */
    public function savePvzList(array $pvzList): bool;
}