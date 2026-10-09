<?php

namespace FacturaScripts\Plugins\GestionLoteria\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\GestionLoteria\Model\ParticipacionAnulada;

class ListParticipacionAnulada extends ListController
{
    private const VIEW_NAME = 'ListParticipacionAnulada';

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'loteria';
        $data['submenu'] = 'participaciones';
        $data['title'] = 'anulacion-participaciones';
        $data['icon'] = 'fa-solid fa-ban';
        $data['ordernum'] = 240;
        return $data;
    }

    protected function createViews()
    {
        $this->addView(self::VIEW_NAME, 'ParticipacionAnulada', 'participaciones-anuladas', 'fa-solid fa-ban')
            ->addSearchFields(['CAST(numero AS CHAR(20))', 'observaciones'])
            ->addOrderBy(['created_at'], 'creation-date', 2)
            ->addOrderBy(['numero'], 'participation-number')
            ->addOrderBy(['idtalonario'], 'talonario')
            ->addOrderBy(['fecha'], 'fecha');

        $i18n = Tools::lang();
        $motivos = [['code' => '', 'description' => '------']];
        foreach (ParticipacionAnulada::MOTIVOS as $motivo) {
            $motivos[] = ['code' => $motivo, 'description' => $i18n->trans('motivo-' . $motivo)];
        }

        $this->listView(self::VIEW_NAME)
            ->addFilterSelectWhere('sorteo_estado', [
                ['label' => $i18n->trans('sorteos-activos'), 'where' => [new DataBaseWhere('idsorteo', 'SELECT idsorteo FROM sorteos WHERE activo = true', 'IN')]],
                ['label' => $i18n->trans('sorteos-inactivos'), 'where' => [new DataBaseWhere('idsorteo', 'SELECT idsorteo FROM sorteos WHERE activo = false', 'IN')]],
                ['label' => $i18n->trans('all'), 'where' => []],
            ])
            ->addFilterSelect('idsorteo', 'sorteo', 'idsorteo', $this->getSorteos())
            ->addFilterSelect('motivo', 'motivo', 'motivo', $motivos)
            ->addFilterNumber('idtalonario', 'talonario', 'idtalonario', '=')
            ->addFilterPeriod('fecha', 'fecha', 'fecha');

        // las anulaciones no se editan: se borran y se vuelven a dar de alta
        if (false === $this->permissions->allowUpdate) {
            $this->setSettings(self::VIEW_NAME, 'btnNew', false);
        }
    }

    private function getSorteos(): array
    {
        $values = [['code' => '', 'description' => '------']];
        $sql = 'SELECT idsorteo, descripcion FROM sorteos ORDER BY fecha DESC, idsorteo DESC';
        foreach ($this->dataBase->select($sql) as $row) {
            $values[] = ['code' => $row['idsorteo'], 'description' => $row['descripcion']];
        }
        return $values;
    }
}
