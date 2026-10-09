<?php

namespace FacturaScripts\Plugins\GestionLoteria\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Core\Tools;

class EditTalonario extends EditController
{
    public function getModelClassName(): string
    {
        return 'Talonario';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'loteria';
        $data['title'] = 'talonario';
        $data['icon'] = 'fa-solid fa-book';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();
        AssetManager::addJs(FS_ROUTE . '/Plugins/GestionLoteria/Assets/Js/edit-talonario.js?v=' . Tools::date());
    }
}
