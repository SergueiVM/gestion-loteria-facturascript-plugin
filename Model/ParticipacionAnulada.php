<?php

namespace FacturaScripts\Plugins\GestionLoteria\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class ParticipacionAnulada extends ModelClass
{
    use ModelTrait;

    public const MOTIVOS = ['no-vendida', 'extraviada', 'robada', 'otro'];

    public $idanulacion;
    public $idsorteo;
    public $idtalonario;
    public $numero;
    public $motivo;
    public $fecha;
    public $observaciones;
    public $nick;
    public $created_at;

    public function clear(): void
    {
        parent::clear();
        $this->motivo = 'no-vendida';
        $this->fecha = Tools::date();
        $this->created_at = Tools::dateTime();
    }

    public static function primaryColumn(): string
    {
        return 'idanulacion';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'numero';
    }

    public static function tableName(): string
    {
        return 'participaciones_anuladas';
    }

    public function test(): bool
    {
        $this->idsorteo = (int)($this->idsorteo ?? 0);
        $this->idtalonario = (int)($this->idtalonario ?? 0);
        $this->numero = (int)($this->numero ?? 0);
        $this->observaciones = Tools::noHtml((string)($this->observaciones ?? ''));

        if ($this->idsorteo <= 0 || $this->idtalonario <= 0 || $this->numero < 0) {
            Tools::log()->warning('record-save-error');
            return false;
        }

        if (false === in_array($this->motivo, self::MOTIVOS, true)) {
            Tools::log()->warning('anulacion-motivo-invalid');
            return false;
        }

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        // solo hay pantalla de alta; las anulaciones se consultan en el listado
        return $type === 'new' ? 'EditParticipacionAnulada' : 'ListParticipacionAnulada';
    }
}
