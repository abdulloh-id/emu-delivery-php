<?php

namespace AbdullohId\MeasoftDelivery\Repositories;

use AbdullohId\MeasoftDelivery\Contracts\MeasoftStorageInterface;
use Exception;
use PDO;

class PdoMeasoftRepository implements MeasoftStorageInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function saveRegions(array $regions): bool
    {
        if (empty($regions)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $this->pdo->exec("DELETE FROM `emu_region_list`");

            $stmt = $this->pdo->prepare("
                INSERT INTO `emu_region_list` (`id`, `name`)
                VALUES (:id, :name)
            ");

            foreach ($regions as $id => $name) {
                $stmt->execute([
                    ':id'   => $id,
                    ':name' => $name,
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function saveTowns(array $towns): bool
    {
        if (empty($towns)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $this->pdo->exec("DELETE FROM `emu_town_list`");

            $stmt = $this->pdo->prepare("
                INSERT INTO `emu_town_list` (
                    `id`, `name`, `region_code`, `region_name`, `latitude`, `longitude`
                ) VALUES (
                    :id, :name, :region_code, :region_name, :latitude, :longitude
                )
            ");

            foreach ($towns as $town) {
                $stmt->execute([
                    ':id'          => $town['id'],
                    ':name'        => $town['name'],
                    ':region_code' => $town['region_code'],
                    ':region_name' => $town['region_name'],
                    ':latitude'    => $town['latitude'],
                    ':longitude'   => $town['longitude'],
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function savePvzList(array $pvzList): bool
    {
        if (empty($pvzList)) {
            return false;
        }

        try {
            $stmtRegions = $this->pdo->query("SELECT `id` FROM `emu_region_list`");
            $uzRegions = $stmtRegions ? $stmtRegions->fetchAll(PDO::FETCH_COLUMN) : [];

            $this->pdo->beginTransaction();

            $this->pdo->exec("DELETE FROM `emu_pvz_list`");

            $stmt = $this->pdo->prepare("
            INSERT INTO `emu_pvz_list` (
                `id`, `client_code`, `name`, `parent_code`, `parent_name`,
                `town`, `town_code`, `town_region_code`, `town_region_name`,
                `address`, `phone`, `comment`, `work_time`, `travel_description`,
                `max_weight`, `accept_cash`, `accept_card`, `accept_fitting`,
                `accept_individuals`, `latitude`, `longitude`
            ) VALUES (
                :id, :client_code, :name, :parent_code, :parent_name,
                :town, :town_code, :town_region_code, :town_region_name,
                :address, :phone, :comment, :work_time, :travel_description,
                :max_weight, :accept_cash, :accept_card, :accept_fitting,
                :accept_individuals, :latitude, :longitude
            )
        ");

            foreach ($pvzList as $pvz) {
                if (!empty($uzRegions) && !in_array($pvz['town_regioncode'], $uzRegions, true)) {
                    continue;
                }

                $stmt->execute([
                    ':id'                 => $pvz['code'],
                    ':client_code'        => $pvz['clientcode'],
                    ':name'               => $pvz['name'],
                    ':parent_code'        => $pvz['parentcode'],
                    ':parent_name'        => $pvz['parentname'],
                    ':town'               => $pvz['town'],
                    ':town_code'          => $pvz['town_code'],
                    ':town_region_code'   => $pvz['town_regioncode'],
                    ':town_region_name'   => $pvz['town_regionname'],
                    ':address'            => $pvz['address'],
                    ':phone'              => $pvz['phone'],
                    ':comment'            => $pvz['comment'],
                    ':work_time'          => $pvz['worktime'],
                    ':travel_description' => $pvz['traveldescription'],
                    ':max_weight'          => $pvz['maxweight'],
                    ':accept_cash'         => $pvz['acceptcash'],
                    ':accept_card'         => $pvz['acceptcard'],
                    ':accept_fitting'      => $pvz['acceptfitting'],
                    ':accept_individuals'  => $pvz['acceptindividuals'],
                    ':latitude'           => $pvz['latitude'],
                    ':longitude'          => $pvz['longitude'],
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
