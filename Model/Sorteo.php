<?php

namespace FacturaScripts\Plugins\GestionLoteria\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class Sorteo extends ModelClass
{
    use ModelTrait;

    public $idsorteo;
    public $descripcion;
    public $fecha;
    public $fecha_limite_pago;
    public $activo;
    public $importe_jugado;
    public $importe_donativo;
    public $premiado;
    public $importe_premio;
    public $precio_participacion;
    public $codsubcuentaven_loteria;
    public $codsubcuentadon_loteria;
    public $codpago;

    public function clear(): void
    {
        parent::clear();
        $this->fecha = Tools::date();
        $this->fecha_limite_pago = null;
        $this->activo = true;
        $this->importe_jugado = 0.00;
        $this->importe_donativo = 0.00;
        $this->premiado = false;
        $this->importe_premio = null;
        $this->codsubcuentaven_loteria = null;
        $this->codsubcuentadon_loteria = null;
        $this->codpago = null;
    }

    public static function primaryColumn(): string
    {
        return 'idsorteo';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'descripcion';
    }

    public static function tableName(): string
    {
        return 'sorteos';
    }

    /**
     * Precio de la participación = importe_jugado + importe_donativo
     */
    public function precioParticipacion(): float
    {
        return (float)($this->importe_jugado ?? 0) + (float)($this->importe_donativo ?? 0);
    }

    public function __get($name)
    {
        if ($name === 'precio_participacion') {
            // Prefer stored value when present, otherwise compute on the fly
            if (isset($this->precio_participacion) && $this->precio_participacion !== null) {
                return (float)$this->precio_participacion;
            }
            return $this->precioParticipacion();
        }

        return parent::__get($name);
    }

    public function __set(string $key, $value): void
    {
        // Delegate to parent to store values in the attributes array and avoid
        // creating dynamic public properties (deprecated in newer PHP versions).
        parent::__set($key, $value);
    }

    public function save(): bool
    {
        // Calculate precio_participacion on server before saving
        $this->precio_participacion = $this->precioParticipacion();
        // If the legacy 'codigo' column still exists and is NOT NULL in DB,
        // ensure we provide a non-null temporary value before insert.
        $hadCodigoColumn = $this->hasColumn('codigo');
        if ($hadCodigoColumn) {
            $currentCodigo = $this->codigo ?? null;
            if ($currentCodigo === null) {
                parent::__set('codigo', '');
            }
        }

        $saved = parent::save();

        // After saving, if we had to set a temporary codigo, update it to the id
        if ($saved && $hadCodigoColumn) {
            $currentCodigo = $this->codigo ?? null;
            if ($currentCodigo === '') {
                // set codigo to the primary id for clarity
                parent::__set('codigo', (string)$this->id());
                // persist the change
                parent::save();
            }
        }

        return $saved;
    }

    public function hasPaidPrizes(): bool
    {
        if (empty($this->idsorteo) || false === self::db()->tableExists('premio_lotes')) {
            return false;
        }

        $sql = 'SELECT 1 FROM premio_lotes WHERE idsorteo = ' . (int)$this->idsorteo . ' LIMIT 1';
        return !empty(self::db()->select($sql));
    }

    public function test(): bool
    {
        // sanitize
        $this->descripcion = Tools::noHtml(trim((string)$this->descripcion));

        // optional accounting/payment fields: sanitize strings
        $this->codsubcuentaven_loteria = isset($this->codsubcuentaven_loteria) ? trim((string)$this->codsubcuentaven_loteria) : null;
        $this->codsubcuentadon_loteria = isset($this->codsubcuentadon_loteria) ? trim((string)$this->codsubcuentadon_loteria) : null;
        $this->codpago = isset($this->codpago) ? trim((string)$this->codpago) : null;

        // validations
        if (empty($this->descripcion)) {
            Tools::log()->error('invalid-descripcion');
            return false;
        }

        if (isset($this->importe_jugado) && $this->importe_jugado < 0) {
            Tools::log()->error('invalid-importe_jugado');
            return false;
        }

        if (isset($this->importe_donativo) && $this->importe_donativo < 0) {
            Tools::log()->error('invalid-importe_donativo');
            return false;
        }

        $this->premiado = (bool)$this->premiado;
        if ($this->premiado && (float)($this->importe_premio ?? 0) <= 0) {
            Tools::log()->error('premio-amount-required');
            return false;
        }

        if (false === $this->premiado && $this->hasPaidPrizes()) {
            Tools::log()->error('sorteo-has-paid-prizes');
            return false;
        }

        return parent::test();
    }
}
