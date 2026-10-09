<?php

namespace FacturaScripts\Plugins\GestionLoteria\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

class ListPremioLote extends ListController
{
    private const VIEW_NAME = 'ListPremioLote';
    private const SORTEOS_ACTIVOS = 'SELECT idsorteo FROM sorteos WHERE premiado = true AND activo = true';
    private const SORTEOS_INACTIVOS = 'SELECT idsorteo FROM sorteos WHERE premiado = true AND activo = false';
    private const SORTEOS_PREMIADOS = 'SELECT idsorteo FROM sorteos WHERE premiado = true';

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'loteria';
        $data['title'] = 'premios';
        $data['icon'] = 'fa-solid fa-trophy';
        $data['ordernum'] = 230;
        return $data;
    }

    protected function createViews()
    {
        $this->addView(self::VIEW_NAME, 'PremioLote', 'premios', 'fa-solid fa-trophy')
            ->addSearchFields(['CAST(idpremiolote AS CHAR(20))', 'numeros'])
            ->addOrderBy(['created_at'], 'fecha', 2)
            ->addOrderBy(['idpremiolote'], 'batch')
            ->addOrderBy(['cantidad_participaciones'], 'papeletas')
            ->addOrderBy(['importe_total'], 'importe-premio-total');

        $i18n = Tools::lang();
        $this->listView(self::VIEW_NAME)
            ->addFilterSelectWhere('sorteo_estado', [
                ['label' => $i18n->trans('sorteos-activos'), 'where' => [new DataBaseWhere('idsorteo', self::SORTEOS_ACTIVOS, 'IN')]],
                ['label' => $i18n->trans('sorteos-inactivos'), 'where' => [new DataBaseWhere('idsorteo', self::SORTEOS_INACTIVOS, 'IN')]],
                ['label' => $i18n->trans('all'), 'where' => [new DataBaseWhere('idsorteo', self::SORTEOS_PREMIADOS, 'IN')]],
            ])
            ->addFilterSelect('idsorteo', 'sorteo', 'idsorteo', $this->getSorteosPremiados())
            ->addFilterPeriod('created_at', 'fecha', 'created_at', true);
    }

    private function getSorteosPremiados(): array
    {
        $values = [['code' => '', 'description' => '------']];
        $sql = 'SELECT idsorteo, descripcion FROM sorteos WHERE premiado = true ORDER BY fecha DESC, idsorteo DESC';
        foreach ($this->dataBase->select($sql) as $row) {
            $values[] = ['code' => $row['idsorteo'], 'description' => $row['descripcion']];
        }
        return $values;
    }
}
