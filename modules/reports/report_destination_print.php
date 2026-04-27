<?php
// modules/reports/report_destination_print.php

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
function formatPausaPranzoDestinationPrint($value): string
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

function getDestinazioneWorkRuleLabelPrint(array $dest): string
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

function getDestinazioneWorkRuleNotePrint(array $dest): string
{
    $isSpecial = !empty($dest['is_special']) ? 1 : 0;

    $countsAsWork = array_key_exists('counts_as_work', $dest) && $dest['counts_as_work'] !== null
        ? (int)$dest['counts_as_work']
        : null;

    $countsAsAbsence = array_key_exists('counts_as_absence', $dest) && $dest['counts_as_absence'] !== null
        ? (int)$dest['counts_as_absence']
        : null;

    if ($countsAsWork === 1) {
        return 'Le ore di questa destinazione vengono sommate nel report come ore lavorate.';
    }

    if ($countsAsAbsence === 1) {
        return 'Le ore di questa destinazione sono marcate come assenza e non vengono sommate alle ore lavorate della destinazione.';
    }

    if ($isSpecial === 1) {
        return 'Questa destinazione è speciale e, non avendo il flag lavoro, le sue ore restano escluse dal monte ore lavorato.';
    }

    return 'Questa destinazione viene trattata come destinazione operativa standard e le sue ore entrano nel monte ore lavorato.';
}

// --------------------------------------------------
// FILTRI
// --------------------------------------------------
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

$destinazioneId = (int)($_GET['destinazione_id'] ?? 0);

// --------------------------------------------------
// MAPPE
// --------------------------------------------------
$destinazioniMap = [];
foreach ($destinazioni as $dest) {
    $destinazioniMap[(int)($dest['id'] ?? 0)] = $dest;
}

// --------------------------------------------------
// LABEL FILTRO
// --------------------------------------------------
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
    $pausaLabel         = formatPausaPranzoDestinationPrint($dest['pausa_pranzo'] ?? 0);
    $destIsSpecialLabel = !empty($dest['is_special']) ? 'Sì' : 'No';
    $destWorkRuleLabel  = getDestinazioneWorkRuleLabelPrint($dest);
    $destWorkRuleNote   = getDestinazioneWorkRuleNotePrint($dest);
}

// --------------------------------------------------
// DATI REPORT
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
// NOTE PAUSA
// --------------------------------------------------
$pausaNote = 'La pausa pranzo del cantiere selezionato è impostata a ' . $pausaLabel . '. La pausa viene scalata solo quando il turno supera la soglia minima necessaria per ottenere 8 ore effettive di lavoro più la pausa prevista.';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Report destinazione stampabile</title>
    <style>
        :root{
            --line:#2c3446;
            --text:#141821;
            --muted:#5f6b7f;
            --panel:#ffffff;
            --bg:#eef1f6;
            --soft:#f7f9fc;
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
            grid-template-columns:repeat(8, minmax(0,1fr));
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
    <a class="btn" href="<?php echo h(app_url('modules/reports/report_destination.php') . '?' . http_build_query($_GET)); ?>">Torna al report</a>
</div>

<div class="page">
    <div class="head">
        <div>
            <div class="brand">Turnar</div>
            <div class="subtitle">Report destinazione stampabile</div>
        </div>
        <div class="period">
            Intervallo <?php echo h(format_date_it($dataDa)); ?> → <?php echo h(format_date_it($dataA)); ?>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Report ore destinazione</div>
        <div class="section-subtitle">Riepilogo delle ore nette e dettaglio giornaliero della singola destinazione.</div>

        <div class="note">
            Le ore riportate in questo report rappresentano il risultato finale della logica attiva sulla destinazione selezionata. La presenza o meno nel monte ore dipende dai flag configurati sulla destinazione stessa.
        </div>

        <div class="pause-box">
            <div class="pause-title">Regole di calcolo applicate</div>
            <div class="pause-value">Pausa pranzo: <?php echo h($pausaLabel); ?></div>
            <div class="pause-text"><?php echo h($pausaNote); ?></div>
            <div class="pause-text" style="margin-top:8px;"><?php echo h($destWorkRuleNote); ?></div>
        </div>

        <div class="cards">
            <div class="card">
                <div class="card-label">Destinazione</div>
                <div class="card-value"><?php echo h($destinazioneLabel); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Comune</div>
                <div class="card-value"><?php echo h($destComuneLabel); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Tipologia</div>
                <div class="card-value"><?php echo h($destTipologiaLabel); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Speciale</div>
                <div class="card-value"><?php echo h($destIsSpecialLabel); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Logica ore</div>
                <div class="card-value"><?php echo h($destWorkRuleLabel); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Operatori</div>
                <div class="card-value"><?php echo count($operatoriCoinvolti); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Turni</div>
                <div class="card-value"><?php echo (int)$totali['turni']; ?></div>
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
                    <th>Comune</th>
                    <th>Tipologia</th>
                    <th>Orario</th>
                    <th class="num">Ore nette</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo h(format_date_it((string)$row['data'])); ?></td>
                            <td><?php echo h((string)$row['operatore']); ?></td>
                            <td><?php echo h((string)($row['comune'] ?? '-')); ?></td>
                            <td><?php echo h((string)($row['tipologia'] ?? '-')); ?></td>
                            <td><?php echo h((string)($row['ora_inizio'] ?? '-')); ?> - <?php echo h((string)($row['ora_fine'] ?? '-')); ?></td>
                            <td class="num"><?php echo h(number_format((float)($row['ore_nette'] ?? 0), 2, ',', '.')); ?> h</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Nessun risultato trovato con i filtri attuali.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>