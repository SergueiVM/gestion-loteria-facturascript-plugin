<?php

namespace FacturaScripts\Plugins\GestionLoteria\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class PremioParticipacion extends ModelClass
{
    use ModelTrait;

    public $idpremioparticipacion;
    public $idpremiolote;
    public $idsorteo;
    public $idtalonario;
    public $numero;
    public $fecha_pago;
    public $importe;

    public function clear(): void
    {
        parent::clear();
        $this->fecha_pago = Tools::date();
        $this->importe = 0.00;
    }

    public static function primaryColumn(): string
    {
        return 'idpremioparticipacion';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'numero';
    }

    public static function tableName(): string
    {
        return 'premio_participaciones';
    }

    public function test(): bool
    {
        $this->idpremiolote = (int)($this->idpremiolote ?? 0);
        $this->idsorteo = (int)($this->idsorteo ?? 0);
        $this->idtalonario = (int)($this->idtalonario ?? 0);
        $this->numero = (int)($this->numero ?? 0);
        $this->importe = (float)($this->importe ?? 0);

        if ($this->idpremiolote <= 0 || $this->idsorteo <= 0 || $this->idtalonario <= 0 || $this->numero < 0 || $this->importe <= 0) {
            Tools::log()->warning('record-save-error');
            return false;
        }

        return parent::test();
    }
}