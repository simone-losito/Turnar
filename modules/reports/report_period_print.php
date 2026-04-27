<?php
// modules/reports/report_period_print.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../turni/TurniRepository.php';
require_once __DIR__ . '/ReportRepository.php';

require_login();

$db          = db_connect();
$turniRepo   = new TurniRepository($db);
$reportRepo  = new ReportRepository($db);

// --------------------------------------------------
// HELPERS LOCALI
// --------------------------------------------------
function formatPausaPranzoReportPrint($value): string
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

function reportPrintTipoConteggio(array $row): string
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

function reportPrintTipoConteggioClass(array $row): string
{
    $countsAsWork = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 0;
    $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
    $isSpecial = isset($row['is_special']) ? (int)$row['is_special'] : 0;

    if ($countsAsAbsence === 1) {
        return 'tipo-assenza';
    }

    if ($countsAsWork === 1) {
        return 'tipo-lavoro';
    }

    if ($isSpecial === 1) {
        return 'tipo-speciale-neutra';
    }

    return 'tipo-operativa';
}

// --------------------------------------------------
// FILTRI
// --------------------------------------------------
$operatori    = $turniRepo->getOperatori();
$destinazioni = $turniRepo->getDestinazioni();

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
// MAPPE
// --------------------------------------------------
$operatoriMap = [];
foreach ($operatori as $op) {
    $operatoriMap[(int)($op['id'] ?? 0)] = $op;
}

$destinazioniMap = [];
foreach ($destinazioni as $dest) {
    $destinazioniMap[(int)($dest['id'] ?? 0)] = $dest;
}

// --------------------------------------------------
// LABEL FILTRI
// --------------------------------------------------
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
// DATI REPORT
// --------------------------------------------------
$rows = $reportRepo->getReportPeriodo($dataDa, $dataA, [
    'operatore_id'          => $operatoreId,
    'destinazione_id'       => $destinazioneId,
    'destinazioni_multiple' => $destinazioniMultiple,
]);

$totali = $reportRepo->calcolaTotali($rows);

// --------------------------------------------------
// INFO PAUSA PRANZO DA MOSTRARE
// --------------------------------------------------
$pausaLabel = 'Variabile in base al cantiere del turno';

if ($destinazioneId > 0 && isset($destinazioniMap[$destinazioneId])) {
    $pausaLabel = formatPausaPranzoReportPrint($destinazioniMap[$destinazioneId]['pausa_pranzo'] ?? 0);
} elseif ($destinazioneId === 0 && count($destinazioniMultiple) === 1) {
    $onlyId = (int)reset($destinazioniMultiple);
    if ($onlyId > 0 && isset($destinazioniMap[$onlyId])) {
        $pausaLabel = formatPausaPranzoReportPrint($destinazioniMap[$onlyId]['pausa_pranzo'] ?? 0);
    }
}

$pausaNote = 'Le ore nette di questo report sono già calcolate al netto della pausa pranzo prevista nel singolo cantiere di ogni turno. La pausa può essere assente, di 30 minuti o di 1 ora, in base a quanto impostato nel cantiere. Viene scalata solo quando il turno supera la soglia minima necessaria.';

if ($destinazioneId > 0 || count($destinazioniMultiple) === 1) {
    $pausaNote = 'Le ore nette di questo report sono già calcolate al netto della pausa pranzo del cantiere selezionato: ' . $pausaLabel . '. La pausa viene scalata solo quando il turno supera la soglia minima necessaria per ottenere 8 ore effettive di lavoro più la pausa prevista.';
}

$flagNote = 'Tipo conteggio turni: counts_as_work = Lavoro, counts_as_absence = Assenza, is_special senza altri flag = Speciale neutra. Tutti gli altri casi restano Operativa.';

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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Report periodo stampabile</title>
    <style>
        :root{
            --line:#2c3446;
            --text:#141821;
            --muted:#5f6b7f;
            --panel:#ffffff;
            --bg:#eef1f6;
            --blue:#1f3b8f;
            --soft:#f7f9fc;
            --green:#166534;
            --green-bg:#dcfce7;
            --red:#991b1b;
            --red-bg:#fee2e2;
            --violet:#5b21b6;
            --violet-bg:#ede9fe;
            --gray:#374151;
            --gray-bg:#f3f4f6;
        }

        *{box-sizing:border-box}

        body{
            margin:0;
            font-family:Arial, Helvetica, sans-serif;
            background:var(--bg);
            color:var(--text);
        }

        .toolbar{
            position:sticky;
            top:0;
            z-index:10;
            display:flex;
            justify-content:flex-end;
            gap:10px;
            padding:16px 20px;
            background:#e7ebf3;
            border-bottom:1px solid #d4d9e4;
        }

        .btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:10px 16px;
            border-radius:999px;
            border:1px solid #bcc5d6;
            background:#fff;
            color:#1a2130;
            font-size:13px;
            font-weight:700;
            text-decoration:none;
            cursor:pointer;
        }

        .page{
            width:1120px;
            margin:24px auto;
            background:var(--panel);
            border:1px solid #d9dfe9;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
            padding:28px;
        }

        .head{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:20px;
            margin-bottom:20px;
        }

        .brand{
            font-size:28px;
            font-weight:800;
            color:#111827;
            line-height:1.1;
        }

        .subtitle{
            margin-top:6px;
            color:var(--muted);
            font-size:14px;
        }

        .period{
            text-align:right;
            font-size:14px;
            color:#111827;
            font-weight:700;
        }

        .section{
            border:1px solid var(--line);
            border-radius:16px;
            padding:16px;
            margin-bottom:14px;
        }

        .section-title{
            font-size:22px;
            font-weight:800;
            margin-bottom:4px;
        }

        .section-subtitle{
            font-size:13px;
            color:var(--muted);
            margin-bottom:14px;
        }

        .note{
            border:1px solid var(--line);
            border-radius:12px;
            padding:12px 14px;
            font-size:13px;
            background:#fafbfe;
            margin-bottom:14px;
            line-height:1.6;
        }

        .pause-box{
            border:1px solid var(--line);
            border-radius:12px;
            padding:12px 14px;
            background:var(--soft);
            margin-bottom:14px;
        }

        .pause-title{
            font-size:12px;
            text-transform:uppercase;
            color:var(--muted);
            margin-bottom:6px;
            font-weight:700;
        }

        .pause-value{
            font-size:16px;
            font-weight:800;
            margin-bottom:8px;
        }

        .pause-text{
            font-size:13px;
            line-height:1.65;
            color:var(--text);
        }

        .cards{
            display:grid;
            grid-template-columns:repeat(5, minmax(0,1fr));
            gap:10px;
            margin-bottom:14px;
        }

        .card{
            border:1px solid var(--line);
            border-radius:12px;
            padding:10px 12px;
            min-height:72px;
        }

        .card-label{
            font-size:11px;
            color:var(--muted);
            text-transform:uppercase;
            margin-bottom:6px;
        }

        .card-value{
            font-size:14px;
            font-weight:800;
            line-height:1.3;
        }

        .block-title{
            font-size:15px;
            font-weight:800;
            margin:14px 0 8px;
        }

        table{
            width:100%;
            border-collapse:collapse;
        }

        th, td{
            padding:8px 6px;
            border-bottom:1px solid #d8deea;
            text-align:left;
            font-size:13px;
            vertical-align:top;
        }

        th{
            font-size:12px;
            text-transform:uppercase;
            color:#2a3650;
            letter-spacing:.02em;
        }

        .num{
            text-align:right;
            white-space:nowrap;
        }

        .tipo-pill{
            display:inline-block;
            padding:4px 8px;
            border-radius:999px;
            font-size:11px;
            font-weight:700;
            border:1px solid transparent;
        }

        .tipo-lavoro{
            color:var(--green);
            background:var(--green-bg);
            border-color:#86efac;
        }

        .tipo-assenza{
            color:var(--red);
            background:var(--red-bg);
            border-color:#fca5a5;
        }

        .tipo-speciale-neutra{
            color:var(--violet);
            background:var(--violet-bg);
            border-color:#c4b5fd;
        }

        .tipo-operativa{
            color:var(--gray);
            background:var(--gray-bg);
            border-color:#d1d5db;
        }

        @media print{
            body{
                background:#fff;
            }

            .toolbar{
                display:none !important;
            }

            .page{
                width:auto;
                margin:0;
                border:none;
                box-shadow:none;
                padding:0;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn" onclick="window.print()">Stampa / Salva PDF</button>
    <a class="btn" href="<?php echo h(app_url('modules/reports/report_period.php') . '?' . http_build_query($_GET)); ?>">Torna al report</a>
</div>

<div class="page">
    <div class="head">
        <div>
            <div class="brand">Turnar</div>
            <div class="subtitle">Report periodo stampabile</div>
        </div>
        <div class="period">
            Intervallo <?php echo h(format_date_it($dataDa)); ?> → <?php echo h(format_date_it($dataA)); ?>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Report ore periodo</div>
        <div class="section-subtitle">Riepilogo delle ore nette e dettaglio giornaliero.</div>

        <div class="note">
            Le ore riportate in questo report sono <strong>ore effettive di lavoro</strong>, già calcolate al netto della pausa pranzo del cantiere quando applicabile.
        </div>

        <div class="pause-box">
            <div class="pause-title">Pausa pranzo applicata</div>
            <div class="pause-value"><?php echo h($pausaLabel); ?></div>
            <div class="pause-text"><?php echo h($pausaNote); ?></div>
            <div class="pause-text" style="margin-top:8px;"><strong>Logica flag:</strong> <?php echo h($flagNote); ?></div>
        </div>

        <div class="cards">
            <div class="card">
                <div class="card-label">Operatore</div>
                <div class="card-value"><?php echo h($operatoreLabel); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Destinazione</div>
                <div class="card-value"><?php echo h($destinazioneLabel); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Destinazioni multiple</div>
                <div class="card-value"><?php echo !empty($multiLabels) ? h(implode(', ', $multiLabels)) : 'Nessuna'; ?></div>
            </div>

            <div class="card">
                <div class="card-label">Turni / Giorni</div>
                <div class="card-value"><?php echo (int)$totali['turni']; ?> / <?php echo (int)$totali['giorni']; ?></div>
            </div>

            <div class="card">
                <div class="card-label">Totale ore nette</div>
                <div class="card-value"><?php echo h(number_format((float)$totali['ore_totali'], 2, ',', '.')); ?> h</div>
            </div>
        </div>

        <div class="block-title">Ore per dipendente</div>
        <table>
            <thead>
                <tr>
                    <th>Dipendente</th>
                    <th class="num">Ore totali (nette)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($totaliOperatori)): ?>
                    <?php foreach ($totaliOperatori as $nomeOp => $oreOp): ?>
                        <tr>
                            <td><?php echo h($nomeOp); ?></td>
                            <td class="num"><?php echo h(number_format((float)$oreOp, 2, ',', '.')); ?> h</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">Nessun dato disponibile.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="block-title">Dettaglio giornaliero</div>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Dipendente</th>
                    <th>Destinazione</th>
                    <th>Tipo conteggio</th>
                    <th>Comune</th>
                    <th>Orario</th>
                    <th class="num">Ore nette</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $destName = trim((string)($row['destinazione'] ?? ''));
                        $tipoConteggio = reportPrintTipoConteggio($row);
                        $tipoClass = reportPrintTipoConteggioClass($row);
                        ?>
                        <tr>
                            <td><?php echo h(format_date_it((string)$row['data'])); ?></td>
                            <td><?php echo h((string)$row['operatore']); ?></td>
                            <td><?php echo h($destName !== '' ? $destName : '-'); ?></td>
                            <td>
                                <span class="tipo-pill <?php echo h($tipoClass); ?>">
                                    <?php echo h($tipoConteggio); ?>
                                </span>
                            </td>
                            <td><?php echo h((string)($row['comune'] ?? '-')); ?></td>
                            <td><?php echo h((string)($row['ora_inizio'] ?? '-')); ?> - <?php echo h((string)($row['ora_fine'] ?? '-')); ?></td>
                            <td class="num"><?php echo h(number_format((float)($row['ore_nette'] ?? 0), 2, ',', '.')); ?> h</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">Nessun risultato trovato con i filtri attuali.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>