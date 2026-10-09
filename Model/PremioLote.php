<?php

namespace FacturaScripts\Plugins\GestionLoteria\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;

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

    public function delete(): bool
    {
        $db = self::db();
        $newTransaction = false === $db->inTransaction() && $db->beginTransaction();

        foreach ($this->getParticipaciones() as $participacion) {
            if (false === $participacion->delete()) {
                if ($newTransaction) {
                    $db->rollback();
                }
                return false;
            }
        }

        if (false === parent::delete()) {
            if ($newTransaction) {
                $db->rollback();
            }
            return false;
        }

        return false === $newTransaction || $db->commit();
    }

    /**
     * @return PremioParticipacion[]
     */
    public function getParticipaciones(): array
    {
        if (empty($this->idpremiolote)) {
            return [];
        }

        return PremioParticipacion::all(
            [Where::eq('idpremiolote', $this->idpremiolote)],
            ['numero' => 'ASC'],
            0,
            0
        );
    }

    public function getSorteo(): Sorteo
    {
        $sorteo = new Sorteo();
        $sorteo->load($this->idsorteo);
        return $sorteo;
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
        $this->importe_total = round((float)($this->importe_total ?? 0), 2);
        $this->numeros = Tools::noHtml(trim((string)$this->numeros));

        if ($this->idsorteo <= 0) {
            Tools::log()->warning('sorteo-not-found');
            return false;
        }

        if ($this->cantidad_participaciones < 0 || $this->importe_total < 0) {
            Tools::log()->warning('record-save-error');
            return false;
        }

        return parent::test();
    }

    /**
     * Recalcula número de papeletas, importe total y lista de números a partir de las líneas.
     */
    public function updateTotals(): bool
    {
        $numeros = [];
        $total = 0.0;
        foreach ($this->getParticipaciones() as $participacion) {
            $numeros[] = (int)$participacion->numero;
            $total += (float)$participacion->importe;
        }

        $this->cantidad_participaciones = count($numeros);
        $this->importe_total = round($total, 2);
        $this->numeros = implode(', ', $numeros);
        return $this->save();
    }
}
