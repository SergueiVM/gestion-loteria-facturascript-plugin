<?php

namespace FacturaScripts\Plugins\GestionLoteria\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\GestionLoteria\Lib\AsientoTalonario;

class Talonario extends ModelClass
{
    use ModelTrait;

    public $idtalonario;
    public $idsorteo;
    public $nro_inicio;
    public $nro_fin;
    public $idcontacto;
    public $vendidas;
    public $no_vendidas;
    public $total_participaciones;
    public $precio_talonario;
    public $pagado;

    public function clear(): void
    {
        parent::clear();
        $this->nro_inicio = 0;
        $this->nro_fin = 0;
        $this->vendidas = 0;
        $this->no_vendidas = 0;
        $this->total_participaciones = 0;
        $this->precio_talonario = 0.00;
        $this->pagado = false;
    }

    public static function primaryColumn(): string
    {
        return 'idtalonario';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'idtalonario';
    }

    public static function tableName(): string
    {
        return 'talonarios';
    }

    public function totalParticipaciones(): int
    {
        $inicio = (int)($this->nro_inicio ?? 0);
        $fin = (int)($this->nro_fin ?? 0);
        if ($fin < $inicio) {
            return 0;
        }
        return $fin - $inicio + 1;
    }

    /**
     * Ensure sensible defaults for `vendidas` and `no_vendidas`.
     * Rules:
     * - If both are empty -> vendidas = total, no_vendidas = 0
     * - If one is provided and the other is empty -> fill the other so their sum == total
     */
    protected function ensureDefaults(): void
    {
        $total = $this->totalParticipaciones();

        $hasVendidas = isset($this->vendidas) && $this->vendidas !== null;
        $hasNoVendidas = isset($this->no_vendidas) && $this->no_vendidas !== null;

        if (!$hasVendidas && !$hasNoVendidas) {
            $this->vendidas = $total;
            $this->no_vendidas = 0;
            return;
        }

        if ($hasVendidas && !$hasNoVendidas) {
            $this->no_vendidas = max(0, $total - (int)$this->vendidas);
            return;
        }

        if (!$hasVendidas && $hasNoVendidas) {
            $this->vendidas = max(0, $total - (int)$this->no_vendidas);
            return;
        }

        // both provided: keep as-is (validation will check sum)
    }

    public function precioTalonario(): float
    {
        $sorteo = new Sorteo();
        if (isset($this->idsorteo)) {
            $sorteo->load($this->idsorteo);
        }

        // ensure transient defaults when computing price
        $this->ensureDefaults();

        $vendidas = (int)($this->vendidas ?? 0);
        $no_vendidas = (int)($this->no_vendidas ?? 0);

        $precio_part = (float)($sorteo->precio_participacion ?? 0.0);
        $importe_don = (float)($sorteo->importe_donativo ?? 0.0);

        return (float)($precio_part * $vendidas + $importe_don * $no_vendidas);
    }

    public function __get($name)
    {
        if ($name === 'total_participaciones') {
            return $this->totalParticipaciones();
        }
        if ($name === 'precio_talonario') {
            return $this->precioTalonario();
        }
        if ($name === 'sorteo_descripcion') {
            $sorteo = new Sorteo();
            if (isset($this->idsorteo) && $sorteo->load($this->idsorteo)) {
                return $sorteo->descripcion;
            }
            return '';
        }
        if ($name === 'contacto_descripcion') {
            // Use SDPatronato Contacto model if available
            $class = '\\FacturaScripts\\Plugins\\SDPatronato\\Model\\Contacto';
            if (class_exists($class)) {
                $contact = new $class();
                if (isset($this->idcontacto) && $contact->load($this->idcontacto)) {
                    // Prefer descripcion (full name) when available
                    return $contact->descripcion ?? trim(($contact->nombre ?? '') . ' ' . ($contact->apellidos ?? ''));
                }
            }
            return '';
        }

        return parent::__get($name);
    }

    public function test(): bool
    {
        $this->nro_inicio = (int)($this->nro_inicio ?? 0);
        $this->nro_fin = (int)($this->nro_fin ?? 0);
        // Apply defaults for vendidas/no_vendidas before validation
        $this->ensureDefaults();

        // Now normalize types
        $this->vendidas = isset($this->vendidas) ? (int)$this->vendidas : null;
        $this->no_vendidas = isset($this->no_vendidas) ? (int)$this->no_vendidas : 0;

        if ($this->nro_fin < $this->nro_inicio) {
            Tools::log()->error('invalid-range-numbers');
            return false;
        }

        $total = $this->totalParticipaciones();

        if ($this->vendidas !== null && ($this->vendidas < 0 || $this->vendidas > $total)) {
            Tools::log()->error('invalid-vendidas');
            return false;
        }

        if ($this->no_vendidas < 0 || $this->no_vendidas > $total) {
            Tools::log()->error('invalid-no_vendidas');
            return false;
        }

        if ($this->vendidas !== null && ($this->vendidas + $this->no_vendidas) !== $total) {
            Tools::log()->error('invalid-sum-vendidas-no_vendidas');
            return false;
        }

        // Normalize pagado to boolean
        $this->pagado = isset($this->pagado) ? (bool)$this->pagado : false;

        return parent::test();
    }

    public function save(): bool
    {
        // If both counters are explicitly zero, treat as not-provided: default vendidas=total
        if (((int)($this->vendidas ?? 0)) === 0 && ((int)($this->no_vendidas ?? 0)) === 0) {
            $this->vendidas = $this->totalParticipaciones();
            $this->no_vendidas = 0;
        }

        // Ensure vendidas/no_vendidas defaults so totals and price are consistent
        $this->ensureDefaults();

        // Compute and persist total_participaciones and precio_talonario
        $this->total_participaciones = $this->totalParticipaciones();
        $this->precio_talonario = $this->precioTalonario();

        // Ensure values are stored (use parent::__set to avoid dynamic props issues)
        parent::__set('total_participaciones', $this->total_participaciones);
        parent::__set('precio_talonario', $this->precio_talonario);
        // Ensure pagado is persisted and defaults to false
        $this->pagado = isset($this->pagado) ? (bool)$this->pagado : false;
        parent::__set('pagado', $this->pagado);

        // If pagado is being set now, validate that we can create the asiento
        $prevPagado = $this->getOriginal('pagado') ? true : false;
        if (!$prevPagado && $this->pagado) {
            $valid = AsientoTalonario::validar($this);
            if ($valid === false) {
                return false;
            }
        }

        // If unmarking pagado, ensure asiento can be deleted (not blocked)
        if ($prevPagado && !$this->pagado) {
            if (!AsientoTalonario::puedeEliminar($this)) {
                Tools::log()->warning('talonario-asiento-locked');
                return false;
            }
        }

        $saved = parent::save();

        // After saving, if pagado changed false->true, create the asiento
        if ($saved && !$prevPagado && $this->pagado) {
            AsientoTalonario::crear($this);
        }

        // After saving, if pagado changed true->false, delete the asiento(s)
        if ($saved && $prevPagado && !$this->pagado) {
            AsientoTalonario::eliminarByTalonarioId($this->idtalonario);
        }

        return $saved;
    }
}
