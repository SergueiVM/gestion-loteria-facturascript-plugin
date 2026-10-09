<?php

namespace FacturaScripts\Plugins\GestionLoteria\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\GestionLoteria\Model\PremioLote;
use FacturaScripts\Plugins\GestionLoteria\Model\PremioParticipacion;
use FacturaScripts\Plugins\GestionLoteria\Model\Sorteo;

class EditPremioLote extends Controller
{
    /** @var PremioLote|null */
    public $lote = null;

    /** @var PremioParticipacion[] */
    public array $lineas = [];

    public string $papeletasInput = '';

    /** fecha de pago en formato Y-m-d para el input date */
    public string $fechaPago = '';

    /** @var Sorteo|null */
    public $sorteo = null;

    /** @var Sorteo[] sorteos premiados y activos disponibles para un lote nuevo */
    public array $sorteos = [];

    public int $selectedSorteoId = 0;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'loteria';
        $data['title'] = 'batch';
        $data['icon'] = 'fa-solid fa-trophy';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $code = (string)$this->request->queryOrInput('code', '');
        if ($code !== '' && false === $this->loadLote($code)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        if (null === $this->lote) {
            $this->loadSorteos();
            $this->selectedSorteoId = (int)$this->request->request->get('idsorteo', 0);
            if ($this->selectedSorteoId === 0 && !empty($this->sorteos)) {
                // por defecto, el sorteo activo con premio más antiguo
                $this->selectedSorteoId = (int)$this->sorteos[0]->idsorteo;
            }
        }

        switch ((string)$this->request->request->get('action', '')) {
            case 'add-papeletas':
                $this->addPapeletasAction();
                break;

            case 'delete-papeleta':
                $this->deletePapeletaAction();
                break;

            case 'delete-lote':
                if ($this->deleteLoteAction()) {
                    $this->redirect('ListPremioLote');
                    return;
                }
                break;
        }

        if (false === (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->fechaPago)) {
            $this->fechaPago = date('Y-m-d');
        }

        if ($this->lote) {
            $this->lineas = $this->lote->getParticipaciones();
        }
    }

    public function url(): string
    {
        return $this->lote ? $this->lote->url() : parent::url();
    }

    private function addPapeletasAction(): void
    {
        $this->papeletasInput = trim((string)$this->request->request->get('papeletas', ''));
        $this->fechaPago = (string)$this->request->request->get('fecha_pago', '');

        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return;
        }

        if (false === $this->validateFormToken()) {
            return;
        }

        $fecha = \DateTime::createFromFormat('!Y-m-d', $this->fechaPago);
        if (false === $fecha || $fecha->format('Y-m-d') !== $this->fechaPago) {
            Tools::log()->warning('fecha-pago-invalid');
            return;
        }
        if ($fecha > new \DateTime('today')) {
            Tools::log()->warning('fecha-pago-future');
            return;
        }

        $sorteo = $this->lote ? $this->sorteo : $this->getSelectedSorteo();
        if (null === $sorteo) {
            return;
        }

        $importe = round((float)($sorteo->importe_premio ?? 0), 2);
        if (false === (bool)$sorteo->premiado || $importe <= 0) {
            Tools::log()->warning('premio-amount-not-defined');
            return;
        }

        [$valid, $rejected] = $this->validatePapeletas($sorteo, $this->papeletasInput);

        // en el textarea solo dejamos las papeletas con error, para poder corregirlas
        $this->papeletasInput = implode("\n", $rejected);

        if (empty($valid)) {
            Tools::log()->warning('premios-no-valid-numbers');
            return;
        }

        $db = $this->dataBase;
        $db->beginTransaction();

        $lote = $this->lote ?? new PremioLote();
        if (null === $this->lote) {
            $lote->idsorteo = $sorteo->idsorteo;
            $lote->fecha_pago = $fecha->format('d-m-Y');
            if (false === $lote->save()) {
                $db->rollback();
                Tools::log()->error('record-save-error');
                $this->papeletasInput = trim((string)$this->request->request->get('papeletas', ''));
                return;
            }
        }

        foreach ($valid as $entry) {
            $linea = new PremioParticipacion();
            $linea->idpremiolote = $lote->idpremiolote;
            $linea->idsorteo = $sorteo->idsorteo;
            $linea->idtalonario = $entry['idtalonario'];
            $linea->numero = $entry['numero'];
            $linea->fecha_pago = $fecha->format('d-m-Y');
            $linea->importe = $importe;

            if (false === $linea->save()) {
                $db->rollback();
                Tools::log()->error('papeleta-save-error', ['%numero%' => $entry['numero']]);
                $this->papeletasInput = trim((string)$this->request->request->get('papeletas', ''));
                if (null === $this->lote) {
                    $lote->idpremiolote = null;
                }
                return;
            }
        }

        if (false === $lote->updateTotals() || false === $db->commit()) {
            $db->rollback();
            Tools::log()->error('record-save-error');
            $this->papeletasInput = trim((string)$this->request->request->get('papeletas', ''));
            return;
        }

        $this->lote = $lote;
        $this->sorteo = $sorteo;

        Tools::log()->notice('papeletas-registered', [
            '%count%' => count($valid),
            '%amount%' => number_format(count($valid) * $importe, 2, ',', '.'),
        ]);
    }

    private function deleteLoteAction(): bool
    {
        if (false === $this->permissions->allowDelete) {
            Tools::log()->warning('not-allowed-delete');
            return false;
        }

        if (false === $this->validateFormToken() || null === $this->lote) {
            return false;
        }

        if (false === $this->lote->delete()) {
            Tools::log()->error('record-deleted-error');
            return false;
        }

        Tools::log()->notice('record-deleted-correctly');
        return true;
    }

    private function deletePapeletaAction(): void
    {
        if (false === $this->permissions->allowDelete) {
            Tools::log()->warning('not-allowed-delete');
            return;
        }

        if (false === $this->validateFormToken() || null === $this->lote) {
            return;
        }

        $linea = new PremioParticipacion();
        $id = (int)$this->request->request->get('idpremioparticipacion', 0);
        if (false === $linea->load($id) || (int)$linea->idpremiolote !== (int)$this->lote->idpremiolote) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $this->dataBase->beginTransaction();
        if (false === $linea->delete() || false === $this->lote->updateTotals()) {
            $this->dataBase->rollback();
            Tools::log()->error('record-deleted-error');
            return;
        }
        $this->dataBase->commit();

        Tools::log()->notice('papeleta-deleted', ['%numero%' => $linea->numero]);
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function findPaidTalonarios(int $idsorteo, int $numero): array
    {
        $sql = 'SELECT idtalonario FROM talonarios'
            . ' WHERE idsorteo = ' . $idsorteo
            . ' AND pagado = ' . $this->dataBase->var2str(true)
            . ' AND nro_inicio <= ' . $numero
            . ' AND nro_fin >= ' . $numero;

        return $this->dataBase->select($sql);
    }

    private function getSelectedSorteo(): ?Sorteo
    {
        foreach ($this->sorteos as $sorteo) {
            if ((int)$sorteo->idsorteo === $this->selectedSorteoId) {
                return $sorteo;
            }
        }

        Tools::log()->warning('select-premiado-sorteo');
        return null;
    }

    private function loadLote(string $code): bool
    {
        $lote = new PremioLote();
        if (false === $lote->load($code)) {
            return false;
        }

        $this->lote = $lote;
        $this->sorteo = $lote->getSorteo();
        $this->title = Tools::trans('batch') . ' #' . $lote->idpremiolote;
        return true;
    }

    private function loadSorteos(): void
    {
        $this->sorteos = Sorteo::all(
            [Where::eq('premiado', true), Where::eq('activo', true)],
            ['fecha' => 'ASC', 'idsorteo' => 'ASC'],
            0,
            0
        );
    }

    /**
     * Valida cada papeleta introducida. Las incorrectas no se guardan y se informa de cada una.
     *
     * @return array{0: array<int, array<string, int>>, 1: array<int, string>}
     */
    private function validatePapeletas(Sorteo $sorteo, string $input): array
    {
        $valid = [];
        $rejected = [];
        $seen = [];

        foreach (preg_split('/[\s,;]+/', $input) ?: [] as $token) {
            $token = trim((string)$token);
            if ($token === '') {
                continue;
            }

            if (false === ctype_digit($token)) {
                Tools::log()->warning('papeleta-invalid-value', ['%value%' => $token]);
                $rejected[] = $token;
                continue;
            }

            $numero = (int)$token;
            if (isset($seen[$numero])) {
                Tools::log()->warning('papeleta-duplicated-input', ['%numero%' => $numero]);
                continue;
            }
            $seen[$numero] = true;

            $pagada = PremioParticipacion::all([
                Where::eq('idsorteo', $sorteo->idsorteo),
                Where::eq('numero', $numero),
            ], [], 0, 1);
            if (!empty($pagada)) {
                Tools::log()->warning('papeleta-already-paid', [
                    '%numero%' => $numero,
                    '%batch%' => $pagada[0]->idpremiolote,
                    '%date%' => $pagada[0]->fecha_pago,
                ]);
                $rejected[] = $token;
                continue;
            }

            $talonarios = $this->findPaidTalonarios((int)$sorteo->idsorteo, $numero);
            if (empty($talonarios)) {
                Tools::log()->warning('papeleta-not-in-paid-booklet', ['%numero%' => $numero]);
                $rejected[] = $token;
                continue;
            }

            if (count($talonarios) > 1) {
                Tools::log()->warning('papeleta-in-several-booklets', ['%numero%' => $numero]);
                $rejected[] = $token;
                continue;
            }

            $valid[] = ['numero' => $numero, 'idtalonario' => (int)$talonarios[0]['idtalonario']];
        }

        return [$valid, $rejected];
    }
}
