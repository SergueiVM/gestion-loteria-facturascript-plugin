<?php
/**
 * GestionLoteria plugin Init
 */
namespace FacturaScripts\Plugins\GestionLoteria;

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Migrations;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;

final class Init extends InitClass
{
    public function init(): void
    {
        // No usamos migraciones: las tablas se definen con XML en /Table
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        // Init models and run migrations
        new Model\Sorteo();
        // Ensure table structure from Table/sorteos.xml is applied
        $this->updateTableData('sorteos');

        // Ensure talonarios table and model are available
        new Model\Talonario();
        $this->updateTableData('talonarios');

        // Ensure premios tables and models are available
        new Model\PremioLote();
        $this->updateTableData('premio_lotes');

        new Model\PremioParticipacion();
        $this->updateTableData('premio_participaciones');

    //     // If an old 'codigo' column still exists in DB, remove it to avoid NOT NULL errors
    //     try {
    //         $db = new DataBase();
    //         $db->connect();
    //         $cols = $db->getColumns('sorteos');
    //         if (array_key_exists('codigo', $cols)) {
    //             $sql = 'ALTER TABLE sorteos DROP COLUMN codigo';
    //             $db->exec($sql);
    //             Tools::log()->info('Removed legacy column codigo from table sorteos');
    //         }
    //     } catch (\Throwable $e) {
    //         Tools::log()->warning('Could not remove legacy column codigo: ' . $e->getMessage());
    //     }
    }
}
