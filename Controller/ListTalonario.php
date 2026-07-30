<?php

namespace FacturaScripts\Plugins\GestionLoteria\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;

class ListTalonario extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'loteria';
        $data['title'] = 'talonarios';
        $data['icon'] = 'fa-solid fa-book';
        return $data;
    }

    protected function createViews()
    {
        $this->addView('ListTalonario', 'Talonario', 'talonarios', 'fa-solid fa-book')
            ->addSearchFields(['idtalonario'])
            ->addOrderBy(['idtalonario'], 'number', 1);

        $i18n = Tools::lang();
        $this->listView('ListTalonario')
            ->addFilterSelectWhere('sorteo_estado', [
                ['label' => $i18n->trans('all'), 'where' => []],
                ['label' => '------', 'where' => []],
                ['label' => $i18n->trans('active'), 'where' => [new DataBaseWhere('idsorteo', 'select idsorteo from sorteos where activo = true', 'IN')]],
                ['label' => $i18n->trans('inactive'), 'where' => [new DataBaseWhere('idsorteo', 'select idsorteo from sorteos where activo = false', 'IN')]],
            ]);

        // Add contact autocomplete filter
        $this->addFilterAutocomplete('ListTalonario', 'idcontacto', 'contact', 'idcontacto', 'contactos', 'idcontacto', 'descripcion');

        // Default to 'active' on first load
        if (isset($this->listView('ListTalonario')->filters['sorteo_estado'])) {
            $this->listView('ListTalonario')->filters['sorteo_estado']->setValue(2);
        }
    }

    protected function loadData($viewName, $view)
    {
        if ($viewName === 'ListTalonario') {
            if (isset($view->filters['sorteo_estado'])) {
                $val = $view->filters['sorteo_estado']->getValue();
                if ($val === null || $val === '' || $val === 0) {
                    $view->where[] = new DataBaseWhere('idsorteo', 'select idsorteo from sorteos where activo = true', 'IN');
                    $view->filters['sorteo_estado']->setValue(2);
                }
            } else {
                $view->where[] = new DataBaseWhere('idsorteo', 'select idsorteo from sorteos where activo = true', 'IN');
            }
        }

        parent::loadData($viewName, $view);
    }
}
