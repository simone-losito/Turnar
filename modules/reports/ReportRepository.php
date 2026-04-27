<?php
// modules/reports/ReportRepository.php

require_once __DIR__ . '/../../core/helpers.php';

class ReportRepository
{
    private mysqli $db;

    public function __construct(?mysqli $db = null)
    {
        $this->db = $db ?: db_connect();
    }

    private function tableExists(string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        $safe = $this->db->real_escape_string($tableName);
        $sql  = "SHOW TABLES LIKE '{$safe}'";
        $res  = $this->db->query($sql);

        if ($res instanceof mysqli_result) {
            $exists = $res->num_rows > 0;
            $res->free();
            return $exists;
        }

        return false;
    }

    private function baseQuery(): string
    {
        return "
            SELECT
                e.id,
                e.data,
                e.id_cantiere,
                e.id_dipendente,
                e.ora_inizio,
                e.ora_fine,
                e.is_capocantiere,
                c.commessa,
                c.comune,
                c.tipologia,
                c.pausa_pranzo,
                c.is_special,
                c.counts_as_work,
                c.counts_as_absence,
                d.nome,
                d.cognome
            FROM eventi_turni e
            LEFT JOIN cantieri c   ON c.id = e.id_cantiere
            LEFT JOIN dipendenti d ON d.id = e.id_dipendente
        ";
    }

    private function calcolaMinutiLordi(?string $inizio, ?string $fine): int
    {
        $inizio = trim((string)$inizio);
        $fine   = trim((string)$fine);

        if ($inizio === '' || $fine === '') {
            return 0;
        }

        $start = strtotime('2000-01-01 ' . $inizio);
        $end   = strtotime('2000-01-01 ' . $fine);

        if ($start === false || $end === false) {
            return 0;
        }

        if ($end < $start) {
            $end = strtotime('2000-01-02 ' . $fine);
        }

        if ($end <= $start) {
            return 0;
        }

        $minuti = (int)round(($end - $start) / 60);

        if ($minuti < 0 || $minuti > (24 * 60)) {
            return 0;
        }

        return $minuti;
    }

    private function calcolaOreNette(?string $inizio, ?string $fine, float $pausaPranzo): float
    {
        $minutiLordi = $this->calcolaMinutiLordi($inizio, $fine);
        if ($minutiLordi <= 0) {
            return 0.0;
        }

        $oreLorde = $minutiLordi / 60;
        $pausa = (float)$pausaPranzo;

        if ($pausa < 0) {
            $pausa = 0.0;
        }

        // Regola progetto:
        // la pausa si scala solo se il turno raggiunge 8 ore effettive + pausa
        if ($pausa > 0 && $oreLorde >= (8 + $pausa)) {
            $oreLorde -= $pausa;
        }

        if ($oreLorde < 0) {
            $oreLorde = 0.0;
        }

        return round($oreLorde, 2);
    }

    private function calcolaOreReportConFlag(array $row): float
    {
        $oreNetteBase = $this->calcolaOreNette(
            $row['ora_inizio'] ?? '',
            $row['ora_fine'] ?? '',
            (float)($row['pausa_pranzo'] ?? 0)
        );

        if ($oreNetteBase <= 0) {
            return 0.0;
        }

        $countsAsWork    = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 1;
        $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
        $isSpecial       = isset($row['is_special']) ? (int)$row['is_special'] : 0;

        // Regola:
        // - counts_as_work = 1    => ore positive
        // - counts_as_absence = 1 => ore negative
        // - speciale neutro       => 0
        // - normale senza flags   => default lavoro
        if ($countsAsAbsence === 1) {
            return round($oreNetteBase * -1, 2);
        }

        if ($countsAsWork === 1) {
            return round($oreNetteBase, 2);
        }

        if ($isSpecial === 1) {
            return 0.0;
        }

        return round($oreNetteBase, 2);
    }

    private function normalize(array $row): array
    {
        $oreNette = $this->calcolaOreReportConFlag($row);

        return [
            'id'                => (int)($row['id'] ?? 0),
            'data'              => (string)($row['data'] ?? ''),
            'id_cantiere'       => (int)($row['id_cantiere'] ?? 0),
            'id_dipendente'     => (int)($row['id_dipendente'] ?? 0),
            'operatore'         => trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')) ?: '-',
            'destinazione'      => trim((string)($row['commessa'] ?? '')) ?: '-',
            'comune'            => trim((string)($row['comune'] ?? '')),
            'tipologia'         => trim((string)($row['tipologia'] ?? '')),
            'ora_inizio'        => trim((string)($row['ora_inizio'] ?? '')),
            'ora_fine'          => trim((string)($row['ora_fine'] ?? '')),
            'pausa_pranzo'      => (float)($row['pausa_pranzo'] ?? 0),
            'is_capocantiere'   => (int)($row['is_capocantiere'] ?? 0),
            'is_special'        => (int)($row['is_special'] ?? 0),
            'counts_as_work'    => (int)($row['counts_as_work'] ?? 0),
            'counts_as_absence' => (int)($row['counts_as_absence'] ?? 0),
            'ore_nette'         => $oreNette,
        ];
    }

    public function getReportPeriodo(string $from, string $to, array $filters = []): array
    {
        if (!$this->tableExists('eventi_turni')) {
            return [];
        }

        $from = normalize_date_iso($from);
        $to   = normalize_date_iso($to);

        if (!$from || !$to) {
            return [];
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $sql = $this->baseQuery() . " WHERE e.data BETWEEN ? AND ?";

        $types  = 'ss';
        $params = [$from, $to];

        if (!empty($filters['operatore_id'])) {
            $sql .= " AND e.id_dipendente = ?";
            $types .= 'i';
            $params[] = (int)$filters['operatore_id'];
        }

        if (!empty($filters['destinazione_id'])) {
            $sql .= " AND e.id_cantiere = ?";
            $types .= 'i';
            $params[] = (int)$filters['destinazione_id'];
        }

        if (!empty($filters['destinazioni_multiple']) && is_array($filters['destinazioni_multiple'])) {
            $ids = array_values(array_filter(array_map('intval', $filters['destinazioni_multiple']), static function ($id) {
                return $id > 0;
            }));

            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND e.id_cantiere IN ($placeholders)";
                $types .= str_repeat('i', count($ids));
                $params = array_merge($params, $ids);
            }
        }

        $sql .= " ORDER BY e.data ASC, e.ora_inizio ASC, d.cognome ASC, d.nome ASC";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $res = $stmt->get_result();
        $rows = [];
        $seenIds = [];

        while ($row = $res->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);

            if ($id > 0) {
                if (isset($seenIds[$id])) {
                    continue;
                }
                $seenIds[$id] = true;
            }

            $rows[] = $this->normalize($row);
        }

        $res->free();
        $stmt->close();

        return $rows;
    }

    public function getReportOperatore(int $operatoreId, string $from, string $to): array
    {
        return $this->getReportPeriodo($from, $to, [
            'operatore_id' => $operatoreId,
        ]);
    }

    public function getReportDestinazione(int $destinazioneId, string $from, string $to): array
    {
        return $this->getReportPeriodo($from, $to, [
            'destinazione_id' => $destinazioneId,
        ]);
    }

    public function getReportGanttDestinazione(int $destinazioneId, string $from, string $to): array
    {
        if (!$this->tableExists('eventi_turni')) {
            return [];
        }

        $from = normalize_date_iso($from);
        $to   = normalize_date_iso($to);

        if ($destinazioneId <= 0 || !$from || !$to) {
            return [];
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $sql = "
            SELECT
                e.id,
                e.data,
                e.id_cantiere,
                e.id_dipendente,
                e.ora_inizio,
                e.ora_fine,
                e.is_capocantiere,
                c.commessa,
                c.comune,
                c.tipologia,
                c.pausa_pranzo,
                c.is_special,
                c.counts_as_work,
                c.counts_as_absence,
                d.nome,
                d.cognome
            FROM eventi_turni e
            INNER JOIN cantieri c ON c.id = e.id_cantiere
            INNER JOIN dipendenti d ON d.id = e.id_dipendente
            WHERE e.id_cantiere = ?
              AND e.data BETWEEN ? AND ?
            ORDER BY e.data ASC, e.ora_inizio ASC, e.ora_fine ASC, d.cognome ASC, d.nome ASC
        ";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('iss', $destinazioneId, $from, $to);

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $res = $stmt->get_result();
        $rows = [];

        while ($row = $res->fetch_assoc()) {
            $oraInizio = trim((string)($row['ora_inizio'] ?? ''));
            $oraFine   = trim((string)($row['ora_fine'] ?? ''));

            $minutiDa = $this->timeToMinutes($oraInizio);
            $minutiA  = $this->timeToMinutes($oraFine);

            if ($minutiA <= $minutiDa) {
                $minutiA += 1440;
            }

            $durataMinuti = max(0, $minutiA - $minutiDa);

            $rows[] = [
                'id'                => (int)($row['id'] ?? 0),
                'data'              => (string)($row['data'] ?? ''),
                'id_cantiere'       => (int)($row['id_cantiere'] ?? 0),
                'id_dipendente'     => (int)($row['id_dipendente'] ?? 0),
                'operatore'         => trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')) ?: '-',
                'destinazione'      => trim((string)($row['commessa'] ?? '')) ?: '-',
                'comune'            => trim((string)($row['comune'] ?? '')),
                'tipologia'         => trim((string)($row['tipologia'] ?? '')),
                'ora_inizio'        => $oraInizio,
                'ora_fine'          => $oraFine,
                'minuti_inizio'     => $minutiDa,
                'minuti_fine'       => $minutiA,
                'durata_minuti'     => $durataMinuti,
                'is_capocantiere'   => (int)($row['is_capocantiere'] ?? 0),
                'is_special'        => (int)($row['is_special'] ?? 0),
                'counts_as_work'    => (int)($row['counts_as_work'] ?? 0),
                'counts_as_absence' => (int)($row['counts_as_absence'] ?? 0),
                'pausa_pranzo'      => (float)($row['pausa_pranzo'] ?? 0),
                'ore_nette'         => $this->calcolaOreReportConFlag($row),
                'lane'              => 0,
                'has_conflict'      => 0,
            ];
        }

        $res->free();
        $stmt->close();

        return $this->buildGanttLayout($rows);
    }

    private function timeToMinutes(?string $time): int
    {
        $time = trim((string)$time);
        if ($time === '') {
            return 0;
        }

        $parts = explode(':', $time);
        $h = isset($parts[0]) ? (int)$parts[0] : 0;
        $m = isset($parts[1]) ? (int)$parts[1] : 0;

        return ($h * 60) + $m;
    }

    private function buildGanttLayout(array $rows): array
    {
        $rowsByDate = [];

        foreach ($rows as $row) {
            $date = (string)($row['data'] ?? '');
            if ($date === '') {
                continue;
            }

            if (!isset($rowsByDate[$date])) {
                $rowsByDate[$date] = [];
            }

            $rowsByDate[$date][] = $row;
        }

        $finalRows = [];

        foreach ($rowsByDate as $date => $dayRows) {
            usort($dayRows, function (array $a, array $b): int {
                if ((int)$a['minuti_inizio'] !== (int)$b['minuti_inizio']) {
                    return (int)$a['minuti_inizio'] <=> (int)$b['minuti_inizio'];
                }

                if ((int)$a['minuti_fine'] !== (int)$b['minuti_fine']) {
                    return (int)$a['minuti_fine'] <=> (int)$b['minuti_fine'];
                }

                return strcasecmp((string)$a['operatore'], (string)$b['operatore']);
            });

            // Lane solo per impilare graficamente i turni quando necessario.
            $laneEndTimes = [];

            foreach ($dayRows as $i => $row) {
                $start = (int)$row['minuti_inizio'];
                $end   = (int)$row['minuti_fine'];

                $assignedLane = null;

                foreach ($laneEndTimes as $laneIndex => $laneEnd) {
                    if ($start >= $laneEnd) {
                        $assignedLane = $laneIndex;
                        break;
                    }
                }

                if ($assignedLane === null) {
                    $assignedLane = count($laneEndTimes);
                    $laneEndTimes[] = $end;
                } else {
                    $laneEndTimes[$assignedLane] = $end;
                }

                $dayRows[$i]['lane'] = $assignedLane;
            }

            // Conflitto SOLO se stesso operatore ha turni sovrapposti nello stesso giorno.
            $countDayRows = count($dayRows);
            for ($i = 0; $i < $countDayRows; $i++) {
                for ($j = $i + 1; $j < $countDayRows; $j++) {
                    $sameOperator = (int)($dayRows[$i]['id_dipendente'] ?? 0) > 0
                        && (int)($dayRows[$i]['id_dipendente'] ?? 0) === (int)($dayRows[$j]['id_dipendente'] ?? 0);

                    if (!$sameOperator) {
                        continue;
                    }

                    $aStart = (int)$dayRows[$i]['minuti_inizio'];
                    $aEnd   = (int)$dayRows[$i]['minuti_fine'];
                    $bStart = (int)$dayRows[$j]['minuti_inizio'];
                    $bEnd   = (int)$dayRows[$j]['minuti_fine'];

                    if (!($aEnd <= $bStart || $aStart >= $bEnd)) {
                        $dayRows[$i]['has_conflict'] = 1;
                        $dayRows[$j]['has_conflict'] = 1;
                    }
                }
            }

            foreach ($dayRows as $row) {
                $finalRows[] = $row;
            }
        }

        usort($finalRows, function (array $a, array $b): int {
            if ((string)$a['data'] !== (string)$b['data']) {
                return strcmp((string)$a['data'], (string)$b['data']);
            }

            if ((int)$a['lane'] !== (int)$b['lane']) {
                return (int)$a['lane'] <=> (int)$b['lane'];
            }

            if ((int)$a['minuti_inizio'] !== (int)$b['minuti_inizio']) {
                return (int)$a['minuti_inizio'] <=> (int)$b['minuti_inizio'];
            }

            return strcasecmp((string)$a['operatore'], (string)$b['operatore']);
        });

        return $finalRows;
    }

    public function calcolaTotali(array $rows): array
    {
        $turni = count($rows);
        $giorni = [];
        $oreTotali = 0.0;

        foreach ($rows as $row) {
            $data = (string)($row['data'] ?? '');
            if ($data !== '') {
                $giorni[$data] = true;
            }

            $ore = (float)($row['ore_nette'] ?? 0);
            $oreTotali += $ore;
        }

        return [
            'turni'      => $turni,
            'giorni'     => count($giorni),
            'ore_totali' => round($oreTotali, 2),
        ];
    }
}