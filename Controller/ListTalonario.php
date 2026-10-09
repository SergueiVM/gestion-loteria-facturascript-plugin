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
        $this->addView('ListTalonario', 'Join\\Talonario', 'talonarios', 'fa-solid fa-book')
            ->addSearchFields([
                'idtalonario',
                'contactos.descripcion',
                'CAST(talonarios.nro_inicio AS CHAR(50))',
                'CAST(talonarios.nro_fin AS CHAR(50))',
            ])
            ->addOrderBy(['idtalonario'], 'number')
            ->addOrderBy(['contacto_descripcion'], 'contact')
            ->addOrderBy(['nro_inicio'], 'nro_inicio', 1);

        $i18n = Tools::lang();
        $this->listView('ListTalonario')
            ->addFilterSelectWhere('sorteo_estado', [
                ['label' => $i18n->trans('all'), 'where' => []],
                ['label' => '------', 'where' => []],
                ['label' => $i18n->trans('active'), 'where' => [new DataBaseWhere('talonarios.idsorteo', 'select idsorteo from sorteos where activo = true', 'IN')]],
                ['label' => $i18n->trans('inactive'), 'where' => [new DataBaseWhere('talonarios.idsorteo', 'select idsorteo from sorteos where activo = false', 'IN')]],
            ]);

        // Pagado filter: All / Pagado / No pagado
        $this->listView('ListTalonario')->addFilterSelectWhere('pagado_status', [
            ['label' => $i18n->trans('all'), 'where' => []],
            ['label' => $i18n->trans('paid'), 'where' => [new DataBaseWhere('pagado', true)]],
            ['label' => $i18n->trans('unpaid'), 'where' => [new DataBaseWhere('pagado', false)]],
        ]);

        // Add contact autocomplete filter
        $this->addFilterAutocomplete('ListTalonario', 'idcontacto', 'contact', 'talonarios.idcontacto', 'contactos', 'idcontacto', 'descripcion');

        // action button: mark paid for selected talonarios (single icon button)
        $this->addButton('ListTalonario', [
            'action' => "listViewSetAction('ListTalonario','mark-paid'); return false;",
            'type' => 'js',
            'icon' => 'fa-solid fa-file-invoice-dollar',
            'label' => '',
            'title' => 'mark-paid',
            'color' => 'success'
        ]);

        $this->addButton('ListTalonario', [
            'action' => "listViewSetAction('ListTalonario','mark-matrix-delivered'); return false;",
            'type' => 'js',
            'icon' => 'fa-solid fa-file-circle-check',
            'label' => '',
            'title' => 'mark-matrix-delivered',
            'color' => 'primary'
        ]);

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
                    $view->where[] = new DataBaseWhere('talonarios.idsorteo', 'select idsorteo from sorteos where activo = true', 'IN');
                    $view->filters['sorteo_estado']->setValue(2);
                }
            } else {
                $view->where[] = new DataBaseWhere('talonarios.idsorteo', 'select idsorteo from sorteos where activo = true', 'IN');
            }
        }

        parent::loadData($viewName, $view);

        if ($viewName !== 'ListTalonario') {
            return;
        }

        if (empty($this->request->request->get('loadfilter')) && $this->hasOnlyDefaultTalonarioFilter($view)) {
            $view->showFilters = false;
        }

        $statusRow = $view->getRow('status');
        if ($statusRow !== null) {
            $statusRow->options = [];
        }

        $today = date('Y-m-d');
        foreach ($view->cursor as $talonario) {
            if ($talonario->pagado) {
                $this->addColor($viewName, 'idtalonario', $talonario->idtalonario, 'success');
            } elseif (!empty($talonario->fecha_limite_pago)) {
                if ($talonario->fecha_limite_pago < $today) {
                    $this->addColor($viewName, 'idtalonario', $talonario->idtalonario, 'danger');
                } elseif ($talonario->fecha_limite_pago === $today) {
                    $this->addColor($viewName, 'idtalonario', $talonario->idtalonario, 'warning');
                }
            }
        }
    }

    /**
     * Returns true when the only active filter is the default "active" status.
     */
    private function hasOnlyDefaultTalonarioFilter($view): bool
    {
        foreach ($view->filters as $key => $filter) {
            $value = $filter->getValue();
            if ($value === null || $value === '' || $value === 0 || $value === '0') {
                continue;
            }

            if ($key === 'sorteo_estado' && (string)$value === '2') {
                continue;
            }

            return false;
        }

        return isset($view->filters['sorteo_estado']);
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'mark-paid':
                $this->markPaidAction();
                return true;
            case 'mark-matrix-delivered':
                $this->markMatrixDeliveredAction();
                return true;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Parse selected codes from request in the common formats used by the list UI
     * @return array<int,string>
     */
    private function getSelectedCodesFromRequest(): array
    {
        $postData = $this->request->request->all();
        $selectedCodes = [];

        if (isset($postData['codes']) && is_array($postData['codes']) && !empty($postData['codes'])) {
            $selectedCodes = $postData['codes'];
        } elseif (isset($postData['codes[]']) && is_array($postData['codes[]']) && !empty($postData['codes[]'])) {
            $selectedCodes = $postData['codes[]'];
        } else {
            $codes = $postData['code'] ?? $this->request->request->get('code');
            if (is_string($codes) && $codes !== '') {
                $selectedCodes = explode(',', $codes);
            } elseif (is_array($codes)) {
                $selectedCodes = $codes;
            }
        }

        $selectedCodes = array_values(array_unique(array_filter(array_map(static function ($item) {
            return trim((string)$item);
        }, $selectedCodes))));

        return array_values(array_filter($selectedCodes, static function ($item) {
            return ctype_digit((string)$item);
        }));
    }

    private function markPaidAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $selectedCodes = $this->getSelectedCodesFromRequest();
        if (empty($selectedCodes)) {
            Tools::log()->warning('no-record-selected');
            return true;
        }

        $success = 0;
        $fail = 0;
        foreach ($selectedCodes as $code) {
            $tal = new \FacturaScripts\Plugins\GestionLoteria\Model\Talonario();
            if (false === $tal->load($code)) {
                $fail++;
                continue;
            }

            if ($tal->pagado) {
                continue;
            }

            $tal->pagado = true;
            if ($tal->save()) {
                $success++;
            } else {
                $fail++;
            }
        }

        if ($success > 0) {
            Tools::log()->notice('records-updated-correctly', ['%count%' => $success]);
        }
        if ($fail > 0) {
            Tools::log()->warning('records-updated-error', ['%count%' => $fail]);
        }

        return true;
    }

    private function markMatrixDeliveredAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        }

        $selectedCodes = $this->getSelectedCodesFromRequest();
        if (empty($selectedCodes)) {
            Tools::log()->warning('no-record-selected');
            return true;
        }

        $success = 0;
        $fail = 0;
        foreach ($selectedCodes as $code) {
            $tal = new \FacturaScripts\Plugins\GestionLoteria\Model\Talonario();
            if (false === $tal->load($code)) {
                $fail++;
                continue;
            }

            if ($tal->matriz_entregada) {
                continue;
            }

            $tal->matriz_entregada = true;
            if ($tal->save()) {
                $success++;
            } else {
                $fail++;
            }
        }

        if ($success > 0) {
            Tools::log()->notice('records-updated-correctly', ['%count%' => $success]);
        }
        if ($fail > 0) {
            Tools::log()->warning('records-updated-error', ['%count%' => $fail]);
        }

        return true;
    }
}
