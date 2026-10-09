<?php

namespace FacturaScripts\Plugins\GestionLoteria\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;

class ListSorteo extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'loteria';
        $data['title'] = 'sorteos';
        $data['icon'] = 'fa-solid fa-ticket';
        return $data;
    }

    protected function createViews()
    {
        $this->addView('ListSorteo', 'Sorteo', 'sorteos', 'fa-solid fa-ticket')
            ->addSearchFields(['descripcion'])
            ->addOrderBy(['fecha'], 'date', 2);

        // Add a select filter to allow switching to all/active/inactive
        $i18n = Tools::lang();
        $this->listView('ListSorteo')
            ->addFilterSelectWhere('activo', [
                ['label' => $i18n->trans('all'), 'where' => []],
                ['label' => '------', 'where' => []],
                ['label' => $i18n->trans('active'), 'where' => [new DataBaseWhere('activo', true)]],
                ['label' => $i18n->trans('inactive'), 'where' => [new DataBaseWhere('activo', false)]],
            ]);

        $this->listView('ListSorteo')->addFilterCheckbox('premiado', 'premiado', 'premiado');

        // Default the select to 'active' so the list shows active sorteos by default
        if (isset($this->listView('ListSorteo')->filters['activo'])) {
            $this->listView('ListSorteo')->filters['activo']->setValue(2);
        }
    }

    protected function loadData($viewName, $view)
    {
        if ($viewName === 'ListSorteo') {
            // If the user hasn't selected any value in the 'activo' filter,
            // default to showing only active sorteos.
            if (isset($view->filters['activo'])) {
                $val = $view->filters['activo']->getValue();
                if ($val === null || $val === '' || $val === 0) {
                    $view->where[] = new DataBaseWhere('activo', true);
                    // Reflect the default selection in the filter UI
                    $view->filters['activo']->setValue(2);
                }
            } else {
                $view->where[] = new DataBaseWhere('activo', true);
                if (isset($view->filters['activo'])) {
                    $view->filters['activo']->setValue(2);
                }
            }
        }

        parent::loadData($viewName, $view);
    }
}
