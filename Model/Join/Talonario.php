<?php
namespace FacturaScripts\Plugins\GestionLoteria\Model\Join;

use FacturaScripts\Core\Template\JoinModel;

class Talonario extends JoinModel
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->setMasterModel(new \FacturaScripts\Dinamic\Model\Talonario());
    }

    protected function getFields(): array
    {
        return [
            'idtalonario' => 'talonarios.idtalonario',
            'idsorteo' => 'talonarios.idsorteo',
            'nro_inicio' => 'talonarios.nro_inicio',
            'nro_fin' => 'talonarios.nro_fin',
            'idcontacto' => 'talonarios.idcontacto',
            'vendidas' => 'talonarios.vendidas',
            'no_vendidas' => 'talonarios.no_vendidas',
            'total_participaciones' => 'talonarios.total_participaciones',
            'precio_talonario' => 'talonarios.precio_talonario',
            'pagado' => 'talonarios.pagado',
            'matriz_entregada' => 'talonarios.matriz_entregada',
            'contacto_descripcion' => 'contactos.descripcion',
            'sorteo_descripcion' => 'sorteos.descripcion',
            'fecha_limite_pago' => 'sorteos.fecha_limite_pago'
        ];
    }

    protected function getGroupFields(): string
    {
        return '';
    }

    protected function getSQLFrom(): string
    {
        return 'talonarios'
            . ' LEFT JOIN contactos ON contactos.idcontacto = talonarios.idcontacto'
            . ' LEFT JOIN sorteos ON sorteos.idsorteo = talonarios.idsorteo';
    }

    protected function getTables(): array
    {
        return ['talonarios', 'contactos', 'sorteos'];
    }
}
