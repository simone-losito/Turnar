<?php
// modules/reports/export_gantt_destination_csv.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../turni/TurniRepository.php';
require_once __DIR__ . '/ReportRepository.php';

require_login();

$db         = db_connect();
$turniRepo  = new TurniRepository($db);
$reportRepo = new ReportRepository($db);

// --------------------------------------------------
// HELPERS LOCALI
// --------------------------------------------------
function formatPausaPranzoExportGantt($value): string
{
    $pausa = (float)$value;

    if ($pausa <= 0) {
        return 'Nessuna pausa';
    }

    if (abs($pausa - 0.50) < 0.001) {
        return '30 minuti';
    }

    if (abs($pausa - 1.00) < 0.001) {
        return '1 ora';
    }

    $minuti = (int)round($pausa * 60);
    return $minuti . ' minuti';
}

function time_to_minutes_gantt_csv(?string $time): int
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

function calculate_duration_hours_gantt_csv(?string $inizio, ?string $fine): float
{
    $start = time_to_minutes_gantt_csv($inizio);
    $end   = time_to_minutes_gantt_csv($fine);

    if ($start <= 0 && $end <= 0) {
        return 0.0;
    }

    if ($end <= $start) {
        $end += 1440;
    }

    $minutes = max(0, $end - $start);
    return round($minutes / 60, 2);
}

function classify_row_type_gantt_csv(array $row): string
{
    $countsAsWork    = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 1;
    $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
    $isSpecial       = !empty($row['is_special']) ? 1 : 0;

    if ($countsAsAbsence === 1) {
        return 'ASSENZA';
    }

    if ($countsAsWork === 1) {
        return 'LAVORO';
    }

    if ($isSpecial === 1) {
        return 'NEUTRO';
    }

    return 'LAVORO';
}

// --------------------------------------------------
// FILTRI
// --------------------------------------------------
$inizioMeseIso = date('Y-m-01');
$fineMeseIso   = date('Y-m-t');

$dataDa = trim((string)($_GET['data_da'] ?? $inizioMeseIso));
$dataA  = trim((string)($_GET['data_a'] ?? $fineMeseIso));

$dataDa = normalize_date_iso($dataDa) ?: $inizioMeseIso;
$dataA  = normalize_date_iso($dataA) ?: $fineMeseIso;

if ($dataDa > $dataA) {
    [$dataDa, $dataA] = [$dataA, $dataDa];
}

$destinazioneId = (int)($_GET['destinazione_id'] ?? 0);

// --------------------------------------------------
// DATI SUPPORTO
// --------------------------------------------------
$destinazioni = $turniRepo->getDestinazioni();

$destinazioniMap = [];
foreach ($destinazioni as $dest) {
    $destinazioniMap[(int)($dest['id'] ?? 0)] = $dest;
}

$destinazioneLabel = 'Destinazione non selezionata';
$destComuneLabel = '-';
$destTipologiaLabel = '-';
$pausaLabel = 'Nessun dato';

if ($destinazioneId > 0 && isset($destinazioniMap[$destinazioneId])) {
    $dest = $destinazioniMap[$destinazioneId];

    $destinazioneLabel = trim((string)($dest['commessa'] ?? ''));
    if ($destinazioneLabel === '') {
        $destinazioneLabel = 'Destinazione #' . $destinazioneId;
    }

    $destComuneLabel    = trim((string)($dest['comune'] ?? '')) ?: '-';
    $destTipologiaLabel = trim((string)($dest['tipologia'] ?? '')) ?: '-';
    $pausaLabel         = formatPausaPranzoExportGantt($dest['pausa_pranzo'] ?? 0);
}

// --------------------------------------------------
// LETTURA REPORT GANTT
// --------------------------------------------------
$rows = [];
if ($destinazioneId > 0 && method_exists($reportRepo, 'getReportGanttDestinazione')) {
    $rows = $reportRepo->getReportGanttDestinazione($destinazioneId, $dataDa, $dataA);
}

$totali = $reportRepo->calcolaTotali($rows);

// --------------------------------------------------
// OPERATORI COINVOLTI / TOTALI EXTRA
// --------------------------------------------------
$operatoriCoinvoltiMap = [];
$conflittiTotali = 0;
$oreLavoro = 0.0;
$oreAssenza = 0.0;
$oreNeutre = 0.0;

foreach ($rows as $row) {
    $nome = trim((string)($row['operatore'] ?? ''));
    if ($nome !== '') {
        $operatoriCoinvoltiMap[$nome] = true;
    }

    if (!empty($row['has_conflict'])) {
        $conflittiTotali++;
    }

    $ore = (float)($row['ore_nette'] ?? 0);
    $tipo = classify_row_type_gantt_csv($row);

    if ($tipo === 'LAVORO') {
        $oreLavoro += $ore;
    } elseif ($tipo === 'ASSENZA') {
        $oreAssenza += abs($ore);
    } else {
        $oreNeutre += $ore;
    }
}

$operatoriCoinvolti = array_keys($operatoriCoinvoltiMap);
sort($operatoriCoinvolti, SORT_NATURAL | SORT_FLAG_CASE);

// --------------------------------------------------
// ORDINAMENTO RIGHE
// --------------------------------------------------
usort($rows, static function (array $a, array $b): int {
    $aData = (string)($a['data'] ?? '');
    $bData = (string)($b['data'] ?? '');

    if ($aData !== $bData) {
        return $aData <=> $bData;
    }

    $aLane = (int)($a['lane'] ?? 0);
    $bLane = (int)($b['lane'] ?? 0);

    if ($aLane !== $bLane) {
        return $aLane <=> $bLane;
    }

    $aStart = (int)($a['minuti_inizio'] ?? 0);
    $bStart = (int)($b['minuti_inizio'] ?? 0);

    if ($aStart !== $bStart) {
        return $aStart <=> $bStart;
    }

    return strcasecmp((string)($a['operatore'] ?? ''), (string)($b['operatore'] ?? ''));
});

// --------------------------------------------------
// OUTPUT CSV
// --------------------------------------------------
$filenameSafe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $destinazioneLabel);
$filename = 'report_gantt_destinazione_' . $filenameSafe . '_' . $dataDa . '_a_' . $dataA . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
if ($output === false) {
    exit('Impossibile generare il file CSV.');
}

fwrite($output, "\xEF\xBB\xBF");

// --------------------------------------------------
// TESTATA
// --------------------------------------------------
fputcsv($output, ['REPORT GANTT DESTINAZIONE TURNAR'], ';');
fputcsv($output, ['Destinazione', $destinazioneLabel], ';');
fputcsv($output, ['Comune', $destComuneLabel], ';');
fputcsv($output, ['Tipologia', $destTipologiaLabel], ';');
fputcsv($output, ['Periodo dal', format_date_it($dataDa)], ';');
fputcsv($output, ['Periodo al', format_date_it($dataA)], ';');
fputcsv($output, ['Pausa pranzo', $pausaLabel], ';');
fputcsv($output, ['Operatori coinvolti', count($operatoriCoinvolti)], ';');
fputcsv($output, ['Turni totali', (int)($totali['turni'] ?? 0)], ';');
fputcsv($output, ['Giorni coinvolti', (int)($totali['giorni'] ?? 0)], ';');
fputcsv($output, ['Conflitti visivi', (int)$conflittiTotali], ';');
fputcsv($output, ['Ore nette lavoro', number_format((float)$oreLavoro, 2, ',', '')], ';');
fputcsv($output, ['Ore nette assenza', number_format((float)$oreAssenza, 2, ',', '')], ';');
fputcsv($output, ['Ore nette neutre', number_format((float)$oreNeutre, 2, ',', '')], ';');
fputcsv($output, ['Ore nette totali', number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '')], ';');
fputcsv($output, [], ';');

// --------------------------------------------------
// DETTAGLIO TURNI TIMELINE
// --------------------------------------------------
fputcsv($output, ['DETTAGLIO TURNI TIMELINE'], ';');
fputcsv($output, [
    'Data',
    'Operatore',
    'Destinazione',
    'Comune',
    'Tipologia',
    'Ora inizio',
    'Ora fine',
    'Minuti inizio',
    'Minuti fine',
    'Durata lorda ore',
    'Pausa cantiere',
    'Ore nette',
    'Tipo riga',
    'Responsabile',
    'Lane',
    'Conflitto'
], ';');

foreach ($rows as $row) {
    $durataLorda = calculate_duration_hours_gantt_csv(
        (string)($row['ora_inizio'] ?? ''),
        (string)($row['ora_fine'] ?? '')
    );

    $tipoRiga = classify_row_type_gantt_csv($row);

    fputcsv($output, [
        format_date_it((string)($row['data'] ?? '')),
        (string)($row['operatore'] ?? ''),
        (string)($row['destinazione'] ?? ''),
        (string)($row['comune'] ?? ''),
        (string)($row['tipologia'] ?? ''),
        (string)($row['ora_inizio'] ?? ''),
        (string)($row['ora_fine'] ?? ''),
        (int)($row['minuti_inizio'] ?? 0),
        (int)($row['minuti_fine'] ?? 0),
        number_format((float)$durataLorda, 2, ',', ''),
        formatPausaPranzoExportGantt($row['pausa_pranzo'] ?? 0),
        number_format((float)($row['ore_nette'] ?? 0), 2, ',', ''),
        $tipoRiga,
        !empty($row['is_capocantiere']) ? 'SI' : 'NO',
        (int)($row['lane'] ?? 0),
        !empty($row['has_conflict']) ? 'SI' : 'NO'
    ], ';');
}

// --------------------------------------------------
// RIEPILOGO PER OPERATORE
// --------------------------------------------------
$totaliOperatori = [];
foreach ($rows as $row) {
    $nomeOp = trim((string)($row['operatore'] ?? '-'));
    if ($nomeOp === '') {
        $nomeOp = '-';
    }

    if (!isset($totaliOperatori[$nomeOp])) {
        $totaliOperatori[$nomeOp] = [
            'ore' => 0.0,
            'turni' => 0,
            'conflitti' => 0,
        ];
    }

    $totaliOperatori[$nomeOp]['ore'] += (float)($row['ore_nette'] ?? 0);
    $totaliOperatori[$nomeOp]['turni']++;
    if (!empty($row['has_conflict'])) {
        $totaliOperatori[$nomeOp]['conflitti']++;
    }
}
ksort($totaliOperatori);

fputcsv($output, [], ';');
fputcsv($output, ['RIEPILOGO PER OPERATORE'], ';');
fputcsv($output, ['Operatore', 'Turni', 'Ore nette', 'Conflitti'], ';');

foreach ($totaliOperatori as $nomeOp => $stats) {
    fputcsv($output, [
        $nomeOp,
        (int)$stats['turni'],
        number_format((float)$stats['ore'], 2, ',', ''),
        (int)$stats['conflitti']
    ], ';');
}

// --------------------------------------------------
// TOTALI FINALI
// --------------------------------------------------
fputcsv($output, [], ';');
fputcsv($output, ['RIEPILOGO FINALE'], ';');
fputcsv($output, ['Destinazione', $destinazioneLabel], ';');
fputcsv($output, ['Operatori coinvolti', count($operatoriCoinvolti)], ';');
fputcsv($output, ['Turni totali', (int)($totali['turni'] ?? 0)], ';');
fputcsv($output, ['Giorni coinvolti', (int)($totali['giorni'] ?? 0)], ';');
fputcsv($output, ['Conflitti visivi', (int)$conflittiTotali], ';');
fputcsv($output, ['Ore nette lavoro', number_format((float)$oreLavoro, 2, ',', '')], ';');
fputcsv($output, ['Ore nette assenza', number_format((float)$oreAssenza, 2, ',', '')], ';');
fputcsv($output, ['Ore nette neutre', number_format((float)$oreNeutre, 2, ',', '')], ';');
fputcsv($output, ['Ore nette totali', number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '')], ';');

fclose($output);
exit;