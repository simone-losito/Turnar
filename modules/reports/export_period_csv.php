<?php
// modules/reports/export_period_csv.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/ReportRepository.php';
require_once __DIR__ . '/../turni/TurniRepository.php';

require_login();

$db         = db_connect();
$reportRepo = new ReportRepository($db);
$turniRepo  = new TurniRepository($db);

// --------------------------------------------------
// HELPERS LOCALI
// --------------------------------------------------
function formatPausaPranzoExportPeriodo($value): string
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

function exportPeriodoTipoConteggio(array $row): string
{
    $countsAsWork = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 0;
    $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
    $isSpecial = isset($row['is_special']) ? (int)$row['is_special'] : 0;

    if ($countsAsAbsence === 1) {
        return 'Assenza';
    }

    if ($countsAsWork === 1) {
        return 'Lavoro';
    }

    if ($isSpecial === 1) {
        return 'Speciale neutra';
    }

    return 'Operativa';
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

$operatoreId    = (int)($_GET['operatore_id'] ?? 0);
$destinazioneId = (int)($_GET['destinazione_id'] ?? 0);

$destinazioniMultiple = $_GET['destinazioni_multiple'] ?? [];
if (!is_array($destinazioniMultiple)) {
    $destinazioniMultiple = [];
}
$destinazioniMultiple = array_values(array_unique(array_map('intval', $destinazioniMultiple)));
$destinazioniMultiple = array_filter($destinazioniMultiple, static function ($id) {
    return $id > 0;
});

// --------------------------------------------------
// DATI SUPPORTO
// --------------------------------------------------
$operatori    = $turniRepo->getOperatori();
$destinazioni = $turniRepo->getDestinazioni();

$operatoriMap = [];
foreach ($operatori as $op) {
    $operatoriMap[(int)($op['id'] ?? 0)] = $op;
}

$destinazioniMap = [];
foreach ($destinazioni as $dest) {
    $destinazioniMap[(int)($dest['id'] ?? 0)] = $dest;
}

$operatoreLabel = 'Tutti gli operatori';
if ($operatoreId > 0 && isset($operatoriMap[$operatoreId])) {
    $op = $operatoriMap[$operatoreId];
    $operatoreLabel = trim((string)($op['cognome'] ?? '') . ' ' . (string)($op['nome'] ?? ''));
    if ($operatoreLabel === '') {
        $operatoreLabel = 'Operatore #' . $operatoreId;
    }
}

$destinazioneLabel = 'Tutte le destinazioni';
if ($destinazioneId > 0 && isset($destinazioniMap[$destinazioneId])) {
    $dest = $destinazioniMap[$destinazioneId];
    $destinazioneLabel = trim((string)($dest['commessa'] ?? ''));
    if ($destinazioneLabel === '') {
        $destinazioneLabel = 'Destinazione #' . $destinazioneId;
    }
}

$multiLabels = [];
foreach ($destinazioniMultiple as $multiId) {
    if (!isset($destinazioniMap[$multiId])) {
        continue;
    }

    $nome = trim((string)($destinazioniMap[$multiId]['commessa'] ?? ''));
    if ($nome !== '') {
        $multiLabels[] = $nome;
    }
}

// --------------------------------------------------
// INFO PAUSA
// --------------------------------------------------
$pausaLabel = 'Variabile in base al cantiere del turno';

if ($destinazioneId > 0 && isset($destinazioniMap[$destinazioneId])) {
    $pausaLabel = formatPausaPranzoExportPeriodo($destinazioniMap[$destinazioneId]['pausa_pranzo'] ?? 0);
} elseif ($destinazioneId === 0 && count($destinazioniMultiple) === 1) {
    $onlyId = (int)reset($destinazioniMultiple);
    if ($onlyId > 0 && isset($destinazioniMap[$onlyId])) {
        $pausaLabel = formatPausaPranzoExportPeriodo($destinazioniMap[$onlyId]['pausa_pranzo'] ?? 0);
    }
}

// --------------------------------------------------
// LETTURA REPORT
// --------------------------------------------------
$rows = $reportRepo->getReportPeriodo($dataDa, $dataA, [
    'operatore_id'          => $operatoreId,
    'destinazione_id'       => $destinazioneId,
    'destinazioni_multiple' => $destinazioniMultiple,
]);

$totali = $reportRepo->calcolaTotali($rows);

// --------------------------------------------------
// OUTPUT CSV
// --------------------------------------------------
$filename = 'report_periodo_' . $dataDa . '_a_' . $dataA . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
if ($output === false) {
    exit('Impossibile generare il file CSV.');
}

// BOM per Excel / compatibilità UTF-8
fwrite($output, "\xEF\xBB\xBF");

// --------------------------------------------------
// TESTATA REPORT
// --------------------------------------------------
fputcsv($output, ['REPORT PERIODO TURNAR'], ';');
fputcsv($output, ['Periodo dal', format_date_it($dataDa)], ';');
fputcsv($output, ['Periodo al', format_date_it($dataA)], ';');
fputcsv($output, ['Operatore', $operatoreLabel], ';');
fputcsv($output, ['Destinazione singola', $destinazioneLabel], ';');
fputcsv($output, ['Destinazioni multiple', !empty($multiLabels) ? implode(', ', $multiLabels) : 'Nessuna'], ';');
fputcsv($output, ['Pausa pranzo', $pausaLabel], ';');
fputcsv($output, ['Logica conteggio', 'counts_as_work = Lavoro / counts_as_absence = Assenza / is_special senza flag = Speciale neutra'], ';');
fputcsv($output, [], ';');

// --------------------------------------------------
// INTESTAZIONI DETTAGLIO
// --------------------------------------------------
fputcsv($output, [
    'Data',
    'Operatore',
    'Destinazione',
    'Tipo conteggio',
    'Comune',
    'Tipologia',
    'Ora inizio',
    'Ora fine',
    'Pausa cantiere',
    'Is special',
    'Counts as work',
    'Counts as absence',
    'Ore nette'
], ';');

// --------------------------------------------------
// RIGHE DETTAGLIO
// --------------------------------------------------
foreach ($rows as $row) {
    fputcsv($output, [
        format_date_it((string)($row['data'] ?? '')),
        (string)($row['operatore'] ?? ''),
        (string)($row['destinazione'] ?? ''),
        exportPeriodoTipoConteggio($row),
        (string)($row['comune'] ?? ''),
        (string)($row['tipologia'] ?? ''),
        (string)($row['ora_inizio'] ?? ''),
        (string)($row['ora_fine'] ?? ''),
        formatPausaPranzoExportPeriodo($row['pausa_pranzo'] ?? 0),
        isset($row['is_special']) ? (int)$row['is_special'] : 0,
        isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 0,
        isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0,
        number_format((float)($row['ore_nette'] ?? 0), 2, ',', '')
    ], ';');
}

// --------------------------------------------------
// TOTALI FINALI
// --------------------------------------------------
fputcsv($output, [], ';');
fputcsv($output, ['RIEPILOGO FINALE'], ';');
fputcsv($output, ['Turni totali', (int)($totali['turni'] ?? 0)], ';');
fputcsv($output, ['Giorni coinvolti', (int)($totali['giorni'] ?? 0)], ';');
fputcsv($output, ['Ore nette totali', number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '')], ';');

fclose($output);
exit;