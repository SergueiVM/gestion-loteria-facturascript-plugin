<?php

namespace FacturaScripts\Plugins\GestionLoteria\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\GestionLoteria\Model\PremioLote;
use FacturaScripts\Plugins\GestionLoteria\Model\PremioParticipacion;
use FacturaScripts\Plugins\GestionLoteria\Model\Sorteo;

class Premios extends Controller
{
    public array $batchList = [];
    public array $dailySummary = [];
    public string $filterDesde = '';
    public string $filterHasta = '';
    public string $filterSearch = '';
    public array $paidParticipations = [];
    public array $processResult = [];
    public string $participacionesInput = '';
    public ?Sorteo $selectedSorteo = null;
    public int $selectedSorteoId = 0;
    public array $sorteos = [];
    public array $totals = [
        'count' => 0,
        'amount' => 0.0,
    ];

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'loteria';
        $data['title'] = 'premios';
        $data['icon'] = 'fa-solid fa-trophy';
        $data['ordernum'] = 230;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->selectedSorteoId = max(0, (int)$this->request->queryOrInput('idsorteo', 0));
        $this->participacionesInput = trim((string)$this->request->request->get('participaciones', ''));
        $this->loadFilters();

        if ('register-premios' === (string)$this->request->request->get('action', '')) {
            $this->registerAction();
        }

        $this->loadSorteos();
        $this->loadSelectedSorteo();
        $this->loadReports();
    }

    private function getDataBase(): DataBase
    {
        $db = new DataBase();
        $db->connect();
        return $db;
    }

    private function escapeLikeValue(DataBase $db, string $value): string
    {
        $escaped = $db->escapeString($value);
        return str_replace(['%', '_'], ['\\%', '\\_'], $escaped);
    }

    private function getBatchFiltersSql(DataBase $db): string
    {
        $where = ['idsorteo = ' . $this->selectedSorteoId];

        if ($this->filterDesde !== '') {
            $where[] = "fecha_pago >= '" . $db->escapeString($this->filterDesde) . "'";
        }

        if ($this->filterHasta !== '') {
            $where[] = "fecha_pago <= '" . $db->escapeString($this->filterHasta) . "'";
        }

        if ($this->filterSearch !== '') {
            $searchEscaped = $this->escapeLikeValue($db, $this->filterSearch);
            $searchInt = ctype_digit($this->filterSearch) ? (int)$this->filterSearch : null;
            $searchWhere = [
                "numeros LIKE '%" . $searchEscaped . "%' ESCAPE '\\\\'",
            ];

            if ($searchInt !== null) {
                $searchWhere[] = 'idpremiolote = ' . $searchInt;
            }

            $where[] = '(' . implode(' OR ', $searchWhere) . ')';
        }

        return implode(' AND ', $where);
    }

    private function getParticipationFiltersSql(DataBase $db): string
    {
        $where = ['p.idsorteo = ' . $this->selectedSorteoId];

        if ($this->filterDesde !== '') {
            $where[] = "p.fecha_pago >= '" . $db->escapeString($this->filterDesde) . "'";
        }

        if ($this->filterHasta !== '') {
            $where[] = "p.fecha_pago <= '" . $db->escapeString($this->filterHasta) . "'";
        }

        if ($this->filterSearch !== '') {
            $searchEscaped = $this->escapeLikeValue($db, $this->filterSearch);
            $searchInt = ctype_digit($this->filterSearch) ? (int)$this->filterSearch : null;
            $searchWhere = [
                "CAST(p.numero AS CHAR) LIKE '%" . $searchEscaped . "%' ESCAPE '\\\\'",
                "CAST(p.idtalonario AS CHAR) LIKE '%" . $searchEscaped . "%' ESCAPE '\\\\'",
                "CAST(p.idpremiolote AS CHAR) LIKE '%" . $searchEscaped . "%' ESCAPE '\\\\'",
            ];

            if ($searchInt !== null) {
                $searchWhere[] = 'p.numero = ' . $searchInt;
                $searchWhere[] = 'p.idtalonario = ' . $searchInt;
                $searchWhere[] = 'p.idpremiolote = ' . $searchInt;
            }

            $where[] = '(' . implode(' OR ', $searchWhere) . ')';
        }

        return implode(' AND ', $where);
    }

    private function loadFilters(): void
    {
        $defaultHasta = Tools::date();
        $defaultDesde = date('Y-m-d', strtotime($defaultHasta . ' -3 months'));

        $this->filterDesde = trim((string)$this->request->queryOrInput('desde', $defaultDesde));
        $this->filterHasta = trim((string)$this->request->queryOrInput('hasta', $defaultHasta));
        $this->filterSearch = trim((string)$this->request->queryOrInput('buscar', ''));

        if (!$this->isValidDate($this->filterDesde)) {
            $this->filterDesde = $defaultDesde;
        }

        if (!$this->isValidDate($this->filterHasta)) {
            $this->filterHasta = $defaultHasta;
        }

        if ($this->filterDesde > $this->filterHasta) {
            [$this->filterDesde, $this->filterHasta] = [$this->filterHasta, $this->filterDesde];
        }
    }

    private function loadBatchList(DataBase $db): void
    {
        $this->batchList = [];
        if ($this->selectedSorteoId <= 0) {
            return;
        }

        $sql = 'SELECT idpremiolote, fecha_pago, created_at, cantidad_participaciones, importe_total, numeros'
            . ' FROM premio_lotes'
            . ' WHERE ' . $this->getBatchFiltersSql($db)
            . ' ORDER BY fecha_pago DESC, idpremiolote DESC';

        foreach ($db->select($sql) as $row) {
            $this->batchList[] = [
                'idpremiolote' => (int)$row['idpremiolote'],
                'fecha_pago' => (string)$row['fecha_pago'],
                'created_at' => (string)($row['created_at'] ?? ''),
                'cantidad_participaciones' => (int)$row['cantidad_participaciones'],
                'importe_total' => (float)$row['importe_total'],
                'numeros' => (string)($row['numeros'] ?? ''),
            ];
        }
    }

    private function loadDailySummary(DataBase $db): void
    {
        $this->dailySummary = [];
        $this->totals = ['count' => 0, 'amount' => 0.0];
        if ($this->selectedSorteoId <= 0) {
            return;
        }

        $sql = 'SELECT fecha_pago, COUNT(*) AS cantidad, COALESCE(SUM(importe), 0) AS importe_total'
            . ' FROM premio_participaciones'
            . ' WHERE ' . str_replace('p.', '', $this->getParticipationFiltersSql($db))
            . ' GROUP BY fecha_pago'
            . ' ORDER BY fecha_pago DESC';

        foreach ($db->select($sql) as $row) {
            $cantidad = (int)$row['cantidad'];
            $importeTotal = (float)$row['importe_total'];
            $this->dailySummary[] = [
                'fecha_pago' => (string)$row['fecha_pago'],
                'cantidad' => $cantidad,
                'importe_total' => $importeTotal,
            ];
            $this->totals['count'] += $cantidad;
            $this->totals['amount'] += $importeTotal;
        }
    }

    private function loadPaidParticipations(DataBase $db): void
    {
        $this->paidParticipations = [];
        if ($this->selectedSorteoId <= 0) {
            return;
        }

        $sql = 'SELECT p.numero, p.fecha_pago, p.importe, p.idpremiolote, p.idtalonario, t.nro_inicio, t.nro_fin'
            . ' FROM premio_participaciones p'
            . ' LEFT JOIN talonarios t ON t.idtalonario = p.idtalonario'
            . ' WHERE ' . $this->getParticipationFiltersSql($db)
            . ' ORDER BY p.fecha_pago DESC, p.idpremioparticipacion DESC'
            . ' LIMIT 500';

        foreach ($db->select($sql) as $row) {
            $this->paidParticipations[] = [
                'numero' => (int)$row['numero'],
                'fecha_pago' => (string)$row['fecha_pago'],
                'importe' => (float)$row['importe'],
                'idpremiolote' => (int)$row['idpremiolote'],
                'idtalonario' => (int)$row['idtalonario'],
                'nro_inicio' => isset($row['nro_inicio']) ? (int)$row['nro_inicio'] : null,
                'nro_fin' => isset($row['nro_fin']) ? (int)$row['nro_fin'] : null,
            ];
        }
    }

    private function loadReports(): void
    {
        if ($this->selectedSorteoId <= 0) {
            return;
        }

        $db = $this->getDataBase();
        $this->loadDailySummary($db);
        $this->loadBatchList($db);
        $this->loadPaidParticipations($db);
    }

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function loadSelectedSorteo(): void
    {
        $this->selectedSorteo = null;
        if ($this->selectedSorteoId <= 0) {
            return;
        }

        $sorteo = new Sorteo();
        if ($sorteo->load($this->selectedSorteoId)) {
            $this->selectedSorteo = $sorteo;
        }
    }

    private function loadSorteos(): void
    {
        $this->sorteos = [];

        foreach (Sorteo::all([], ['fecha' => 'DESC', 'idsorteo' => 'DESC'], 0, 0) as $sorteo) {
            $this->sorteos[] = [
                'idsorteo' => (int)$sorteo->idsorteo,
                'label' => (string)$sorteo->descripcion . ' (#' . (string)$sorteo->idsorteo . ')',
                'fecha' => (string)$sorteo->fecha,
                'activo' => (bool)$sorteo->activo,
                'importe_premio' => (float)($sorteo->importe_premio ?? 0),
            ];
        }
    }

    /**
     * @return array{numbers: array<int, int>, invalidTokens: array<int, string>, duplicateInput: array<int, int>}
     */
    private function parseParticipaciones(string $input): array
    {
        $parts = preg_split('/[\s,;]+/', $input) ?: [];
        $numbers = [];
        $invalidTokens = [];
        $duplicateInput = [];
        $seen = [];

        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '') {
                continue;
            }

            if (!ctype_digit($part)) {
                $invalidTokens[] = $part;
                continue;
            }

            $number = (int)$part;
            if (isset($seen[$number])) {
                $duplicateInput[] = $number;
                continue;
            }

            $seen[$number] = true;
            $numbers[] = $number;
        }

        return [
            'numbers' => $numbers,
            'invalidTokens' => array_values(array_unique($invalidTokens)),
            'duplicateInput' => array_values(array_unique($duplicateInput)),
        ];
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function findPaidTalonariosForNumber(DataBase $db, int $idsorteo, int $numero): array
    {
        $sql = 'SELECT idtalonario, nro_inicio, nro_fin'
            . ' FROM talonarios'
            . ' WHERE idsorteo = ' . $idsorteo
            . ' AND pagado = 1'
            . ' AND nro_inicio <= ' . $numero
            . ' AND nro_fin >= ' . $numero
            . ' ORDER BY idtalonario ASC';

        $rows = [];
        foreach ($db->select($sql) as $row) {
            $rows[] = [
                'idtalonario' => (int)$row['idtalonario'],
                'nro_inicio' => (int)$row['nro_inicio'],
                'nro_fin' => (int)$row['nro_fin'],
            ];
        }

        return $rows;
    }

    private function findExistingPayment(DataBase $db, int $idsorteo, int $numero): ?array
    {
        $sql = 'SELECT idpremiolote, fecha_pago'
            . ' FROM premio_participaciones'
            . ' WHERE idsorteo = ' . $idsorteo
            . ' AND numero = ' . $numero
            . ' LIMIT 1';

        $rows = $db->select($sql);
        if (empty($rows)) {
            return null;
        }

        return [
            'idpremiolote' => (int)$rows[0]['idpremiolote'],
            'fecha_pago' => (string)$rows[0]['fecha_pago'],
        ];
    }

    private function registerAction(): void
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return;
        }

        if (false === $this->validateFormToken()) {
            return;
        }

        $this->processResult = [
            'saved' => [],
            'invalidTokens' => [],
            'duplicateInput' => [],
            'notFound' => [],
            'alreadyPaid' => [],
            'ambiguous' => [],
            'batchId' => 0,
            'savedCount' => 0,
            'totalAmount' => 0.0,
        ];

        if ($this->selectedSorteoId <= 0) {
            Tools::log()->warning('sorteo-not-found');
            return;
        }

        $sorteo = new Sorteo();
        if (false === $sorteo->load($this->selectedSorteoId)) {
            Tools::log()->warning('sorteo-not-found');
            return;
        }

        $this->selectedSorteo = $sorteo;
        $importePremio = (float)($sorteo->importe_premio ?? 0);
        if ($importePremio <= 0) {
            Tools::log()->warning('premio-amount-not-defined');
            return;
        }

        $parsed = $this->parseParticipaciones($this->participacionesInput);
        $this->processResult['invalidTokens'] = $parsed['invalidTokens'];
        $this->processResult['duplicateInput'] = $parsed['duplicateInput'];

        if (empty($parsed['numbers'])) {
            Tools::log()->warning('premios-empty-input');
            return;
        }

        $db = $this->getDataBase();
        $validEntries = [];
        foreach ($parsed['numbers'] as $numero) {
            $alreadyPaid = $this->findExistingPayment($db, $this->selectedSorteoId, $numero);
            if (!empty($alreadyPaid)) {
                $this->processResult['alreadyPaid'][] = [
                    'numero' => $numero,
                    'idpremiolote' => $alreadyPaid['idpremiolote'],
                    'fecha_pago' => $alreadyPaid['fecha_pago'],
                ];
                continue;
            }

            $talonarios = $this->findPaidTalonariosForNumber($db, $this->selectedSorteoId, $numero);
            if (empty($talonarios)) {
                $this->processResult['notFound'][] = $numero;
                continue;
            }

            if (count($talonarios) > 1) {
                $this->processResult['ambiguous'][] = $numero;
                continue;
            }

            $validEntries[] = [
                'numero' => $numero,
                'idtalonario' => $talonarios[0]['idtalonario'],
                'nro_inicio' => $talonarios[0]['nro_inicio'],
                'nro_fin' => $talonarios[0]['nro_fin'],
                'importe' => $importePremio,
            ];
        }

        if (empty($validEntries)) {
            Tools::log()->warning('premios-no-valid-numbers');
            return;
        }

        $fechaPago = Tools::date();
        $importeTotal = count($validEntries) * $importePremio;
        $db->beginTransaction();

        try {
            $lote = new PremioLote();
            $lote->idsorteo = $this->selectedSorteoId;
            $lote->fecha_pago = $fechaPago;
            $lote->created_at = Tools::dateTime();
            $lote->cantidad_participaciones = count($validEntries);
            $lote->importe_total = $importeTotal;
            $lote->numeros = implode(', ', array_map(static function ($entry) {
                return (string)$entry['numero'];
            }, $validEntries));

            if (false === $lote->save()) {
                $db->rollback();
                Tools::log()->error('record-save-error');
                return;
            }

            foreach ($validEntries as $entry) {
                $participacion = new PremioParticipacion();
                $participacion->idpremiolote = (int)$lote->idpremiolote;
                $participacion->idsorteo = $this->selectedSorteoId;
                $participacion->idtalonario = $entry['idtalonario'];
                $participacion->numero = $entry['numero'];
                $participacion->fecha_pago = $fechaPago;
                $participacion->importe = $entry['importe'];

                if (false === $participacion->save()) {
                    $db->rollback();
                    Tools::log()->error('record-save-error');
                    return;
                }

                $this->processResult['saved'][] = $entry;
            }

            if (false === $db->commit()) {
                $db->rollback();
                Tools::log()->error('record-save-error');
                return;
            }
        } catch (\Throwable $exception) {
            $db->rollback();
            Tools::log()->error($exception->getMessage());
            return;
        }

        $this->processResult['batchId'] = (int)$lote->idpremiolote;
        $this->processResult['savedCount'] = count($validEntries);
        $this->processResult['totalAmount'] = $importeTotal;

        $skippedCount = count($this->processResult['invalidTokens'])
            + count($this->processResult['duplicateInput'])
            + count($this->processResult['notFound'])
            + count($this->processResult['alreadyPaid'])
            + count($this->processResult['ambiguous']);

        if ($skippedCount > 0) {
            Tools::log()->warning('premio-batch-partial', [
                '%saved%' => $this->processResult['savedCount'],
                '%skipped%' => $skippedCount,
            ]);
            return;
        }

        Tools::log()->notice('premio-batch-created', [
            '%count%' => $this->processResult['savedCount'],
            '%amount%' => number_format($importeTotal, 2, ',', '.'),
        ]);
    }
}