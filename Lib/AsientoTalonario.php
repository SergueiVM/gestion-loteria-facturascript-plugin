<?php
/**
 * Helper to create accounting entry when a talonario is marked as paid
 */
namespace FacturaScripts\Plugins\GestionLoteria\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Plugins\GestionLoteria\Model\Sorteo;

class AsientoTalonario
{
    /**
     * Validate necessary subaccounts exist to create the asiento.
     * Return null if ok, false to abort save.
     */
    public static function validar(object $talonario)
    {
        // Only validate if being marked as paid now
        $prev = $talonario->getOriginal('pagado');
        $prev = $prev ? true : false;
        if ($prev || empty($talonario->pagado)) {
            return null;
        }

        if (empty($talonario->idsorteo)) {
            Tools::log()->error('sorteo-not-found');
            return false;
        }

        $sorteo = new Sorteo();
        if (false === $sorteo->load($talonario->idsorteo)) {
            Tools::log()->error('sorteo-not-found');
            return false;
        }

        $idempresa = (int)Tools::settings('default', 'idempresa');
        $fecha = Tools::date();

        // Check bank subaccount via forma de pago
        if (empty($sorteo->codpago)) {
            Tools::log()->error('payment-method-not-defined');
            return false;
        }

        $forma = new FormaPago();
        if (false === $forma->load($sorteo->codpago)) {
            Tools::log()->error('payment-method-not-found');
            return false;
        }

        $ejercicio = new Ejercicio();
        $ejercicio->idempresa = $idempresa;
        if (false === $ejercicio->loadFromDate($fecha, true, false)) {
            Tools::log()->error('talonario-no-ejercicio', ['%fecha%' => $fecha]);
            return false;
        }

        $subBanco = $forma->getSubcuenta($ejercicio->codejercicio, true);
        if (empty($subBanco->codsubcuenta)) {
            Tools::log()->error('talonario-no-subcuenta-banco', ['%codpago%' => $sorteo->codpago]);
            return false;
        }

        // Check venta and donativo subcuentas
        if (empty($sorteo->codsubcuentaven_loteria) || empty($sorteo->codsubcuentadon_loteria)) {
            Tools::log()->error('talonario-missing-loteria-subaccounts');
            return false;
        }

        $sub = new Subcuenta();
        $where = ['codejercicio' => $ejercicio->codejercicio, 'codsubcuenta' => $sorteo->codsubcuentaven_loteria];
        $loaded = $sub->loadWhere([new \FacturaScripts\Core\Where('codejercicio', $ejercicio->codejercicio, '='), new \FacturaScripts\Core\Where('codsubcuenta', $sorteo->codsubcuentaven_loteria, '=')]);
        if (empty($sub->codsubcuenta)) {
            Tools::log()->error('talonario-no-subcuenta-venta');
            return false;
        }

        $sub2 = new Subcuenta();
        $sub2->loadWhere([new \FacturaScripts\Core\Where('codejercicio', $ejercicio->codejercicio, '='), new \FacturaScripts\Core\Where('codsubcuenta', $sorteo->codsubcuentadon_loteria, '=')]);
        if (empty($sub2->codsubcuenta)) {
            Tools::log()->error('talonario-no-subcuenta-donativo');
            return false;
        }

        return null;
    }

    /**
     * Create the accounting entry for a talonario just marked as paid.
     */
    public static function crear(object $talonario): void
    {
        if (empty($talonario->idtalonario) || empty($talonario->idsorteo) || empty($talonario->pagado)) {
            return;
        }

        // Reload from DB to ensure persisted values
        $modelClass = get_class($talonario);
        $reload = new $modelClass();
        if (false === $reload->load($talonario->idtalonario)) {
            return;
        }
        $talonario = $reload;

        $sorteo = new Sorteo();
        if (false === $sorteo->load($talonario->idsorteo)) {
            return;
        }

        $idempresa = (int)Tools::settings('default', 'idempresa');
        $fecha = Tools::date();

        $importe_debe = (float)$talonario->precio_talonario;
        if ($importe_debe <= 0) {
            return;
        }

        $importe_venta = (float)$sorteo->importe_jugado * (int)$talonario->vendidas;
        $importe_don = (float)$sorteo->importe_donativo * (int)$talonario->total_participaciones;

        // Ejercicio
        $ejercicio = new Ejercicio();
        $ejercicio->idempresa = $idempresa;
        if (false === $ejercicio->loadFromDate($fecha, true, false)) {
            return;
        }

        // Subcuentas
        $forma = new FormaPago();
        if (false === $forma->load($sorteo->codpago)) {
            return;
        }
        $subBanco = $forma->getSubcuenta($ejercicio->codejercicio, true);
        if (empty($subBanco->codsubcuenta)) {
            return;
        }

        $subVenta = new Subcuenta();
        $subVenta->loadWhere([new \FacturaScripts\Core\Where('codejercicio', $ejercicio->codejercicio, '='), new \FacturaScripts\Core\Where('codsubcuenta', $sorteo->codsubcuentaven_loteria, '=')]);
        if (empty($subVenta->codsubcuenta)) {
            return;
        }

        $subDon = new Subcuenta();
        $subDon->loadWhere([new \FacturaScripts\Core\Where('codejercicio', $ejercicio->codejercicio, '='), new \FacturaScripts\Core\Where('codsubcuenta', $sorteo->codsubcuentadon_loteria, '=')]);
        if (empty($subDon->codsubcuenta)) {
            return;
        }

        // Create asiento
        $asiento = new Asiento();
        $asiento->fecha = $fecha;
        $asiento->codejercicio = $ejercicio->codejercicio;
        // Description: Pago talonario {nro_inicio}-{nro_fin} {descripcion del sorteo} {contacto}
        $descripcionSorteo = trim((string)$sorteo->descripcion);
        $concepto = Tools::lang()->trans('pago-talonario') . ' ' . $talonario->nro_inicio . '-' . $talonario->nro_fin . ' ' . $descripcionSorteo;

        // Append contact name if available
        if (!empty($talonario->idcontacto)) {
            $contact = new \FacturaScripts\Dinamic\Model\Contacto();
            if (false !== $contact->load($talonario->idcontacto)) {
                $contactName = trim((string)$contact->descripcion ?: trim((string)($contact->nombre . ' ' . $contact->apellidos)));
                if ($contactName !== '') {
                    $concepto .= ' - ' . $contactName;
                }
            }
        }

        $asiento->concepto = $concepto;
        $asiento->idempresa = $idempresa;
        $asiento->importe = $importe_debe;
        $asiento->documento = 'TALON-' . $talonario->idtalonario;
        $asiento->editable = true;

        if (false === $asiento->save()) {
            Tools::log()->error('talonario-asiento-save-error');
            return;
        }

        // Debit: bank
        $lineaDebe = $asiento->getNewLine($subBanco);
        $lineaDebe->debe = $importe_debe;
        $lineaDebe->haber = 0;
        // counterpart to first haber (venta) for clarity
        $lineaDebe->setCounterpart($subVenta);
        $lineaDebe->save();

        // Haber: venta
        if ($importe_venta > 0) {
            $lineaHaberV = $asiento->getNewLine($subVenta);
            $lineaHaberV->debe = 0;
            $lineaHaberV->haber = $importe_venta;
            $lineaHaberV->setCounterpart($subBanco);
            $lineaHaberV->save();
        }

        // Haber: donativo
        if ($importe_don > 0) {
            $lineaHaberD = $asiento->getNewLine($subDon);
            $lineaHaberD->debe = 0;
            $lineaHaberD->haber = $importe_don;
            $lineaHaberD->setCounterpart($subBanco);
            $lineaHaberD->save();
        }
    }

    /**
     * Return true if the asiento for this talonario can be deleted (editable)
     */
    public static function puedeEliminar(object $talonario): bool
    {
        if (empty($talonario->idtalonario)) {
            return true;
        }

        $doc = 'TALON-' . $talonario->idtalonario;
        $asiento = new Asiento();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('documento', $doc)];
        foreach ($asiento->all($where) as $a) {
            if (false === $a->editable()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Delete the asiento(s) linked to the talonario document.
     */
    public static function eliminarByTalonarioId(int $idtalonario): void
    {
        if (empty($idtalonario)) {
            return;
        }

        $doc = 'TALON-' . $idtalonario;
        $asiento = new Asiento();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('documento', $doc)];
        foreach ($asiento->all($where) as $a) {
            $a->delete();
        }
    }
}
