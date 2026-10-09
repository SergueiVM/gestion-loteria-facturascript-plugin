<?php

namespace FacturaScripts\Plugins\GestionLoteria\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\GestionLoteria\Model\ParticipacionAnulada;
use FacturaScripts\Plugins\GestionLoteria\Model\PremioParticipacion;
use FacturaScripts\Plugins\GestionLoteria\Model\Sorteo;

class EditParticipacionAnulada extends Controller
{
    public const MAX_RANGO = 1000;

    public string $fecha = '';

    public string $hasta = '';

    public string $modo = 'una';

    public string $motivo = 'no-vendida';

    public string $desde = '';

    public string $observaciones = '';

    /** @var ParticipacionAnulada[] anulaciones registradas en este envío */
    public array $registradas = [];

    public int $selectedSorteoId = 0;

    /** @var Sorteo[] */
    public array $sorteos = [];

    public function getMotivos(): array
    {
        return ParticipacionAnulada::MOTIVOS;
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'loteria';
        $data['submenu'] = 'participaciones';
        $data['title'] = 'anular-participaciones';
        $data['icon'] = 'fa-solid fa-ban';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->sorteos = Sorteo::all([Where::eq('activo', true)], ['fecha' => 'ASC', 'idsorteo' => 'ASC'], 0, 0);
        $this->selectedSorteoId = (int)$this->request->request->get('idsorteo', 0);
        if ($this->selectedSorteoId === 0 && !empty($this->sorteos)) {
            $this->selectedSorteoId = (int)$this->sorteos[0]->idsorteo;
        }

        $this->fecha = date('Y-m-d');
        if ($this->request->request->get('action', '') === 'anular') {
            $this->anularAction();
        }
    }

    private function anularAction(): void
    {
        $req = $this->request->request;
        $this->modo = $req->get('modo', 'una') === 'rango' ? 'rango' : 'una';
        $this->desde = trim((string)$req->get('desde', ''));
        $this->hasta = trim((string)$req->get('hasta', ''));
        $this->motivo = (string)$req->get('motivo', 'no-vendida');
        $this->observaciones = trim((string)$req->get('observaciones', ''));
        $this->fecha = (string)$req->get('fecha', '');

        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return;
        } elseif (false === $this->validateFormToken()) {
            return;
        }

        $sorteo = null;
        foreach ($this->sorteos as $item) {
            if ((int)$item->idsorteo === $this->selectedSorteoId) {
                $sorteo = $item;
                break;
            }
        }
        if (null === $sorteo) {
            Tools::log()->warning('select-sorteo');
            return;
        }

        if (false === in_array($this->motivo, ParticipacionAnulada::MOTIVOS, true)) {
            Tools::log()->warning('anulacion-motivo-invalid');
            return;
        }

        $fecha = \DateTime::createFromFormat('!Y-m-d', $this->fecha);
        if (false === $fecha || $fecha->format('Y-m-d') !== $this->fecha) {
            Tools::log()->warning('fecha-invalid');
            return;
        }

        if ($this->modo === 'una') {
            $this->hasta = $this->desde;
        }
        if (false === ctype_digit($this->desde) || false === ctype_digit($this->hasta)) {
            Tools::log()->warning('anulacion-numero-invalid');
            return;
        }

        $desde = (int)$this->desde;
        $hasta = (int)$this->hasta;
        if ($hasta < $desde) {
            Tools::log()->warning('anulacion-rango-invalid');
            return;
        }
        if ($hasta - $desde + 1 > self::MAX_RANGO) {
            Tools::log()->warning('anulacion-rango-too-big', ['%max%' => self::MAX_RANGO]);
            return;
        }

        $errores = 0;
        for ($numero = $desde; $numero <= $hasta; $numero++) {
            $anulada = $this->anular($sorteo, $numero, $fecha->format('d-m-Y'));
            if (null === $anulada) {
                $errores++;
                continue;
            }
            $this->registradas[] = $anulada;
        }

        if (count($this->registradas) > 0) {
            Tools::log()->notice('participaciones-anuladas-ok', ['%count%' => count($this->registradas)]);
            // dejamos el formulario listo para la siguiente anulación
            $this->desde = '';
            $this->hasta = '';
            $this->observaciones = '';
        }
        if ($errores > 0) {
            Tools::log()->warning('participaciones-anuladas-error', ['%count%' => $errores]);
        }
    }

    /**
     * Anula una papeleta concreta. Informa del motivo si no se puede anular.
     */
    private function anular(Sorteo $sorteo, int $numero, string $fecha): ?ParticipacionAnulada
    {
        $where = [Where::eq('idsorteo', $sorteo->idsorteo), Where::eq('numero', $numero)];

        $previa = ParticipacionAnulada::all($where, [], 0, 1);
        if (!empty($previa)) {
            Tools::log()->warning('papeleta-already-anulada', ['%numero%' => $numero, '%date%' => $previa[0]->fecha]);
            return null;
        }

        $pagada = PremioParticipacion::all($where, [], 0, 1);
        if (!empty($pagada)) {
            Tools::log()->warning('papeleta-anular-already-paid', ['%numero%' => $numero, '%batch%' => $pagada[0]->idpremiolote]);
            return null;
        }

        $talonarios = $this->dataBase->select('SELECT idtalonario FROM talonarios'
            . ' WHERE idsorteo = ' . (int)$sorteo->idsorteo
            . ' AND nro_inicio <= ' . $numero . ' AND nro_fin >= ' . $numero);
        if (empty($talonarios)) {
            Tools::log()->warning('papeleta-not-in-booklet', ['%numero%' => $numero]);
            return null;
        }
        if (count($talonarios) > 1) {
            Tools::log()->warning('papeleta-anular-several-booklets', ['%numero%' => $numero]);
            return null;
        }

        $anulada = new ParticipacionAnulada();
        $anulada->idsorteo = $sorteo->idsorteo;
        $anulada->idtalonario = (int)$talonarios[0]['idtalonario'];
        $anulada->numero = $numero;
        $anulada->motivo = $this->motivo;
        $anulada->fecha = $fecha;
        $anulada->observaciones = $this->observaciones;
        $anulada->nick = $this->user->nick ?? null;
        if (false === $anulada->save()) {
            Tools::log()->error('papeleta-save-error', ['%numero%' => $numero]);
            return null;
        }

        return $anulada;
    }
}
