<?php
// modules/reports/export_destination_csv.php

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
function formatPausaPranzoExportDestinazione($value): string
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

function getDestinazioneWorkRuleLabelExport(array $dest): string
{
    $isSpecial = !empty($dest['is_special']) ? 1 : 0;

    $countsAsWork = array_key_exists('counts_as_work', $dest) && $dest['counts_as_work'] !== null
        ? (int)$dest['counts_as_work']
        : null;

    $countsAsAbsence = array_key_exists('counts_as_absence', $dest) && $dest['counts_as_absence'] !== null
        ? (int)$dest['counts_as_absence']
        : null;

    if ($countsAsWork === 1) {
        return 'Conteggiata come lavoro';
    }

    if ($countsAsAbsence === 1) {
        return 'Conteggiata come assenza';
    }

    if ($isSpecial === 1) {
        return 'Speciale neutra / esclusa dal monte ore';
    }

    return 'Operativa standard';
}

function getDestinazioneWorkRuleNoteExport(array $dest): string
{
    $isSpecial = !empty($dest['is_special']) ? 1 : 0;

    $countsAsWork = array_key_exists('counts_as_work', $dest) && $dest['counts_as_work'] !== null
        ? (int)$dest['counts_as_work']
        : null;

    $countsAsAbsence = array_key_exists('counts_as_absence', $dest) && $dest['counts_as_absence'] !== null
        ? (int)$dest['counts_as_absence']
        : null;

    if ($countsAsWork === 1) {
        return 'Le ore di questa destinazione entrano nel report come ore lavorate.';
    }

    if ($countsAsAbsence === 1) {
        return 'Le ore di questa destinazione sono marcate come assenza e non vengono sommate alle ore lavorate.';
    }

    if ($isSpecial === 1) {
        return 'Questa destinazione è speciale e, non avendo il flag lavoro, le sue ore restano escluse dal monte ore lavorato.';
    }

    return 'Questa destinazione viene trattata come destinazione operativa standard e le sue ore entrano nel monte ore lavorato.';
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
$destIsSpecialLabel = '-';
$destWorkRuleLabel = '-';
$destWorkRuleNote = '';

if ($destinazioneId > 0 && isset($destinazioniMap[$destinazioneId])) {
    $dest = $destinazioniMap[$destinazioneId];

    $destinazioneLabel = trim((string)($dest['commessa'] ?? ''));
    if ($destinazioneLabel === '') {
        $destinazioneLabel = 'Destinazione #' . $destinazioneId;
    }

    $destComuneLabel    = trim((string)($dest['comune'] ?? '')) ?: '-';
    $destTipologiaLabel = trim((string)($dest['tipologia'] ?? '')) ?: '-';
    $pausaLabel         = formatPausaPranzoExportDestinazione($dest['pausa_pranzo'] ?? 0);
    $destIsSpecialLabel = !empty($dest['is_special']) ? 'Sì' : 'No';
    $destWorkRuleLabel  = getDestinazioneWorkRuleLabelExport($dest);
    $destWorkRuleNote   = getDestinazioneWorkRuleNoteExport($dest);
}

// --------------------------------------------------
// LETTURA REPORT
// --------------------------------------------------
$rows = [];
if ($destinazioneId > 0) {
    $rows = $reportRepo->getReportDestinazione($destinazioneId, $dataDa, $dataA);
}

$totali = $reportRepo->calcolaTotali($rows);

// --------------------------------------------------
// OPERATORI COINVOLTI
// --------------------------------------------------
$operatoriCoinvoltiMap = [];
foreach ($rows as $row) {
    $nome = trim((string)($row['operatore'] ?? ''));
    if ($nome !== '') {
        $operatoriCoinvoltiMap[$nome] = true;
    }
}
$operatoriCoinvolti = array_keys($operatoriCoinvoltiMap);
sort($operatoriCoinvolti, SORT_NATURAL | SORT_FLAG_CASE);

// --------------------------------------------------
// TOTALI PER OPERATORE
// --------------------------------------------------
$totaliOperatori = [];
foreach ($rows as $row) {
    $nomeOp = (string)($row['operatore'] ?? '-');
    if (!isset($totaliOperatori[$nomeOp])) {
        $totaliOperatori[$nomeOp] = 0.0;
    }
    $totaliOperatori[$nomeOp] += (float)($row['ore_nette'] ?? 0);
}
ksort($totaliOperatori);

// --------------------------------------------------
// OUTPUT CSV
// --------------------------------------------------
$filenameSafe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $destinazioneLabel);
$filename = 'report_destinazione_' . $filenameSafe . '_' . $dataDa . '_a_' . $dataA . '.csv';

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
fputcsv($output, ['REPORT DESTINAZIONE TURNAR'], ';');
fputcsv($output, ['Destinazione', $destinazioneLabel], ';');
fputcsv($output, ['Comune', $destComuneLabel], ';');
fputcsv($output, ['Tipologia', $destTipologiaLabel], ';');
fputcsv($output, ['Speciale', $destIsSpecialLabel], ';');
fputcsv($output, ['Logica conteggio ore', $destWorkRuleLabel], ';');
fputcsv($output, ['Periodo dal', format_date_it($dataDa)], ';');
fputcsv($output, ['Periodo al', format_date_it($dataA)], ';');
fputcsv($output, ['Pausa pranzo', $pausaLabel], ';');
fputcsv($output, ['Operatori coinvolti', count($operatoriCoinvolti)], ';');
fputcsv($output, [], ';');

// --------------------------------------------------
// NOTE LOGICA REPORT
// --------------------------------------------------
fputcsv($output, ['NOTE REPORT'], ';');
fputcsv($output, ['Regola destinazione', $destWorkRuleNote], ';');
fputcsv($output, ['Pausa pranzo', 'La pausa viene scalata solo quando il turno supera la soglia minima necessaria per ottenere 8 ore effettive di lavoro più la pausa prevista.'], ';');
fputcsv($output, [], ';');

// --------------------------------------------------
// RIEPILOGO ORE PER OPERATORE
// --------------------------------------------------
fputcsv($output, ['ORE PER OPERATORE'], ';');
fputcsv($output, ['Operatore', 'Ore totali nette'], ';');

if (!empty($totaliOperatori)) {
    foreach ($totaliOperatori as $nomeOp => $oreOp) {
        fputcsv($output, [
            $nomeOp,
            number_format((float)$oreOp, 2, ',', '')
        ], ';');
    }
} else {
    fputcsv($output, ['Nessun dato disponibile', ''], ';');
}

fputcsv($output, [], ';');

// --------------------------------------------------
// DETTAGLIO TURNI
// --------------------------------------------------
fputcsv($output, ['DETTAGLIO TURNI'], ';');
fputcsv($output, [
    'Data',
    'Operatore',
    'Destinazione',
    'Comune',
    'Tipologia',
    'Ora inizio',
    'Ora fine',
    'Pausa cantiere',
    'Ore nette'
], ';');

foreach ($rows as $row) {
    fputcsv($output, [
        format_date_it((string)($row['data'] ?? '')),
        (string)($row['operatore'] ?? ''),
        (string)($row['destinazione'] ?? ''),
        (string)($row['comune'] ?? ''),
        (string)($row['tipologia'] ?? ''),
        (string)($row['ora_inizio'] ?? ''),
        (string)($row['ora_fine'] ?? ''),
        formatPausaPranzoExportDestinazione($row['pausa_pranzo'] ?? 0),
        number_format((float)($row['ore_nette'] ?? 0), 2, ',', '')
    ], ';');
}

// --------------------------------------------------
// TOTALI FINALI
// --------------------------------------------------
fputcsv($output, [], ';');
fputcsv($output, ['RIEPILOGO FINALE'], ';');
fputcsv($output, ['Destinazione', $destinazioneLabel], ';');
fputcsv($output, ['Speciale', $destIsSpecialLabel], ';');
fputcsv($output, ['Logica conteggio ore', $destWorkRuleLabel], ';');
fputcsv($output, ['Operatori coinvolti', count($operatoriCoinvolti)], ';');
fputcsv($output, ['Turni totali', (int)($totali['turni'] ?? 0)], ';');
fputcsv($output, ['Giorni coinvolti', (int)($totali['giorni'] ?? 0)], ';');
fputcsv($output, ['Ore nette totali', number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '')], ';');

fclose($output);
exit;