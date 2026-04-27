<?php
// modules/reports/export_operator_csv.php

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
function formatPausaPranzoExportOperatore($value): string
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

function exportOperatoreTipoConteggio(array $row): string
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

$operatoreId = (int)($_GET['operatore_id'] ?? 0);

// --------------------------------------------------
// DATI SUPPORTO
// --------------------------------------------------
$operatori = $turniRepo->getOperatori();

$operatoriMap = [];
foreach ($operatori as $op) {
    $operatoriMap[(int)($op['id'] ?? 0)] = $op;
}

$operatoreLabel = 'Operatore non selezionato';
if ($operatoreId > 0 && isset($operatoriMap[$operatoreId])) {
    $op = $operatoriMap[$operatoreId];
    $operatoreLabel = trim((string)($op['cognome'] ?? '') . ' ' . (string)($op['nome'] ?? ''));
    if ($operatoreLabel === '') {
        $operatoreLabel = 'Operatore #' . $operatoreId;
    }
}

// --------------------------------------------------
// LETTURA REPORT
// --------------------------------------------------
$rows = [];
if ($operatoreId > 0) {
    $rows = $reportRepo->getReportOperatore($operatoreId, $dataDa, $dataA);
}

$totali = $reportRepo->calcolaTotali($rows);

// --------------------------------------------------
// PAUSE USATE
// --------------------------------------------------
$pauseUsate = [];
foreach ($rows as $row) {
    $pauseUsate[] = formatPausaPranzoExportOperatore($row['pausa_pranzo'] ?? 0);
}
$pauseUsate = array_values(array_unique($pauseUsate));

$pausaLabel = 'Nessun dato';
if (!empty($pauseUsate)) {
    $pausaLabel = count($pauseUsate) === 1
        ? $pauseUsate[0]
        : 'Variabile in base al cantiere del turno';
}

// --------------------------------------------------
// OUTPUT CSV
// --------------------------------------------------
$filenameSafe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $operatoreLabel);
$filename = 'report_operatore_' . $filenameSafe . '_' . $dataDa . '_a_' . $dataA . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
if ($output === false) {
    exit('Impossibile generare il file CSV.');
}

// BOM UTF-8 per Excel
fwrite($output, "\xEF\xBB\xBF");

// --------------------------------------------------
// TESTATA
// --------------------------------------------------
fputcsv($output, ['REPORT OPERATORE TURNAR'], ';');
fputcsv($output, ['Operatore', $operatoreLabel], ';');
fputcsv($output, ['Periodo dal', format_date_it($dataDa)], ';');
fputcsv($output, ['Periodo al', format_date_it($dataA)], ';');
fputcsv($output, ['Pausa pranzo', $pausaLabel], ';');
fputcsv($output, ['Logica conteggio', 'Le ore dipendono dai flag della destinazione: counts_as_work = Lavoro, counts_as_absence = Assenza, speciale senza flag = Speciale neutra'], ';');
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
        exportOperatoreTipoConteggio($row),
        (string)($row['comune'] ?? ''),
        (string)($row['tipologia'] ?? ''),
        (string)($row['ora_inizio'] ?? ''),
        (string)($row['ora_fine'] ?? ''),
        formatPausaPranzoExportOperatore($row['pausa_pranzo'] ?? 0),
        number_format((float)($row['ore_nette'] ?? 0), 2, ',', '')
    ], ';');
}

// --------------------------------------------------
// TOTALI FINALI
// --------------------------------------------------
fputcsv($output, [], ';');
fputcsv($output, ['RIEPILOGO FINALE'], ';');
fputcsv($output, ['Operatore', $operatoreLabel], ';');
fputcsv($output, ['Turni totali', (int)($totali['turni'] ?? 0)], ';');
fputcsv($output, ['Giorni coinvolti', (int)($totali['giorni'] ?? 0)], ';');
fputcsv($output, ['Ore nette totali', number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '')], ';');

fclose($output);
exit;