<?php

namespace FacturaScripts\Plugins\GestionLoteria\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class PremioLote extends ModelClass
{
    use ModelTrait;

    public $idpremiolote;
    public $idsorteo;
    public $fecha_pago;
    public $created_at;
    public $cantidad_participaciones;
    public $importe_total;
    public $numeros;

    public function clear(): void
    {
        parent::clear();
        $this->fecha_pago = Tools::date();
        $this->created_at = Tools::dateTime();
        $this->cantidad_participaciones = 0;
        $this->importe_total = 0.00;
        $this->numeros = '';
    }

    public static function primaryColumn(): string
    {
        return 'idpremiolote';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'idpremiolote';
    }

    public static function tableName(): string
    {
        return 'premio_lotes';
    }

    public function test(): bool
    {
        $this->idsorteo = (int)($this->idsorteo ?? 0);
        $this->cantidad_participaciones = (int)($this->cantidad_participaciones ?? 0);
        $this->importe_total = (float)($this->importe_total ?? 0);
        $this->numeros = Tools::noHtml(trim((string)$this->numeros));

        if ($this->idsorteo <= 0 || $this->cantidad_participaciones <= 0 || $this->importe_total <= 0) {
            Tools::log()->warning('record-save-error');
            return false;
        }

        return parent::test();
    }
}