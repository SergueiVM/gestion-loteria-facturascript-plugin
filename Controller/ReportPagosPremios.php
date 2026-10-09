<?php

namespace FacturaScripts\Plugins\GestionLoteria\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\GestionLoteria\Model\Sorteo;

class ReportPagosPremios extends Controller
{
    public string $desde = '';

    public string $hasta = '';

    /** @var array filas: fecha, papeletas, importe, acumulado_papeletas, acumulado_importe */
    public array $rows = [];

    /** @var Sorteo|null */
    public $sorteo = null;

    /** @var Sorteo[] */
    public array $sorteos = [];

    public int $totalPapeletas = 0;

    public float $totalImporte = 0.0;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['submenu'] = 'loteria';
        $data['title'] = 'pagos-premios';
        $data['icon'] = 'fa-solid fa-trophy';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->sorteos = Sorteo::all([Where::eq('premiado', true)], ['fecha' => 'ASC', 'idsorteo' => 'ASC'], 0, 0);
        $this->desde = $this->validDate((string)$this->request->queryOrInput('desde', ''));
        $this->hasta = $this->validDate((string)$this->request->queryOrInput('hasta', ''));

        $this->sorteo = $this->selectSorteo((int)$this->request->queryOrInput('idsorteo', 0));
        if ($this->sorteo) {
            $this->loadRows();
        }
    }

    private function loadRows(): void
    {
        $sql = 'SELECT fecha_pago, COUNT(*) AS papeletas, SUM(importe) AS importe'
            . ' FROM premio_participaciones'
            . ' WHERE idsorteo = ' . (int)$this->sorteo->idsorteo;
        if ($this->desde !== '') {
            $sql .= ' AND fecha_pago >= ' . $this->dataBase->var2str($this->desde);
        }
        if ($this->hasta !== '') {
            $sql .= ' AND fecha_pago <= ' . $this->dataBase->var2str($this->hasta);
        }
        $sql .= ' GROUP BY fecha_pago ORDER BY fecha_pago ASC';

        foreach ($this->dataBase->select($sql) as $row) {
            $this->totalPapeletas += (int)$row['papeletas'];
            $this->totalImporte += (float)$row['importe'];
            $this->rows[] = [
                'fecha' => $row['fecha_pago'],
                'papeletas' => (int)$row['papeletas'],
                'importe' => (float)$row['importe'],
                'acumulado_papeletas' => $this->totalPapeletas,
                'acumulado_importe' => $this->totalImporte,
            ];
        }
    }

    /**
     * Devuelve el sorteo solicitado o, por defecto, el sorteo activo premiado más antiguo.
     */
    private function selectSorteo(int $idsorteo): ?Sorteo
    {
        foreach ($this->sorteos as $sorteo) {
            if ($idsorteo > 0 && (int)$sorteo->idsorteo === $idsorteo) {
                return $sorteo;
            }
        }

        if ($idsorteo > 0) {
            Tools::log()->warning('record-not-found');
            return null;
        }

        foreach ($this->sorteos as $sorteo) {
            if ($sorteo->activo) {
                return $sorteo;
            }
        }

        return $this->sorteos[0] ?? null;
    }

    private function validDate(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
