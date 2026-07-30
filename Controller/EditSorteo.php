<?php

namespace FacturaScripts\Plugins\GestionLoteria\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Core\Tools;

class EditSorteo extends EditController
{
    public function getModelClassName(): string
    {
        return 'Sorteo';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'loteria';
        $data['title'] = 'sorteo';
        $data['icon'] = 'fa-solid fa-ticket';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();
        AssetManager::addJs(FS_ROUTE . '/Plugins/GestionLoteria/Assets/Js/edit-sorteo.js?v=' . Tools::date());
    }
}
