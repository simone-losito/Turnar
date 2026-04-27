<?php
// modules/reports/report_gantt_destination_print.php

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
function formatPausaPranzoGanttPrint($value): string
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

function gantt_print_percent_from_minutes(int $minutes): float
{
    return max(0, min(100, ($minutes / 1440) * 100));
}

function gantt_print_build_bar_style(array $row): string
{
    $start = (int)($row['minuti_inizio'] ?? 0);
    $end   = (int)($row['minuti_fine'] ?? 0);

    if ($end <= $start) {
        $end = $start + 1;
    }

    $displayEnd = min($end, 1440);

    $left = gantt_print_percent_from_minutes($start);
    $width = gantt_print_percent_from_minutes(max(1, $displayEnd - $start));

    if ($width < 0.8) {
        $width = 0.8;
    }

    return 'left:' . number_format($left, 4, '.', '') . '%;width:' . number_format($width, 4, '.', '') . '%;';
}

function gantt_print_build_hours(): array
{
    $hours = [];
    for ($h = 0; $h < 24; $h++) {
        $hours[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT);
    }
    return $hours;
}

function gantt_print_classify_row(array $row): string
{
    $countsAsWork    = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 1;
    $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
    $isSpecial       = !empty($row['is_special']) ? 1 : 0;

    if ($countsAsAbsence === 1) {
        return 'absence';
    }

    if ($countsAsWork === 1) {
        return 'work';
    }

    if ($isSpecial === 1) {
        return 'neutral';
    }

    return 'work';
}

function gantt_print_group_rows_by_date(array $rows): array
{
    $grouped = [];

    foreach ($rows as $row) {
        $data = (string)($row['data'] ?? '');
        if ($data === '') {
            $data = '0000-00-00';
        }

        if (!isset($grouped[$data])) {
            $grouped[$data] = [];
        }

        $grouped[$data][] = $row;
    }

    ksort($grouped);

    foreach ($grouped as $data => $items) {
        usort($items, static function (array $a, array $b): int {
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

        $grouped[$data] = $items;
    }

    return $grouped;
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

if ($destinazioneId > 0 && isset($destinazioniMap[$destinazioneId])) {
    $dest = $destinazioniMap[$destinazioneId];

    $destinazioneLabel = trim((string)($dest['commessa'] ?? ''));
    if ($destinazioneLabel === '') {
        $destinazioneLabel = 'Destinazione #' . $destinazioneId;
    }

    $destComuneLabel    = trim((string)($dest['comune'] ?? '')) ?: '-';
    $destTipologiaLabel = trim((string)($dest['tipologia'] ?? '')) ?: '-';
    $pausaLabel         = formatPausaPranzoGanttPrint($dest['pausa_pranzo'] ?? 0);
}

// --------------------------------------------------
// DATI REPORT GANTT
// --------------------------------------------------
$rows = [];
if ($destinazioneId > 0 && method_exists($reportRepo, 'getReportGanttDestinazione')) {
    $rows = $reportRepo->getReportGanttDestinazione($destinazioneId, $dataDa, $dataA);
}

$totali = $reportRepo->calcolaTotali($rows);
$groupedRows = gantt_print_group_rows_by_date($rows);
$hourLabels = gantt_print_build_hours();

// --------------------------------------------------
// OPERATORI / TOTALI EXTRA
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
    $tipo = gantt_print_classify_row($row);

    if ($tipo === 'work') {
        $oreLavoro += $ore;
    } elseif ($tipo === 'absence') {
        $oreAssenza += abs($ore);
    } else {
        $oreNeutre += $ore;
    }
}

$operatoriCoinvolti = array_keys($operatoriCoinvoltiMap);
sort($operatoriCoinvolti, SORT_NATURAL | SORT_FLAG_CASE);

// --------------------------------------------------
// NOTE
// --------------------------------------------------
$pausaNote = 'Nel grafico la barra rappresenta l’occupazione oraria lorda del turno sulla giornata 00:00 → 24:00. Le ore riepilogative invece seguono le regole del report: pausa pranzo del cantiere e flag destinazione (lavoro, assenza o neutro).';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Report Gantt destinazione stampabile</title>
    <style>
        :root{
            --line:#2c3446;
            --text:#141821;
            --muted:#5f6b7f;
            --panel:#ffffff;
            --bg:#eef1f6;
            --soft:#f7f9fc;

            --bar-work-1:#16a34a;
            --bar-work-2:#10b981;

            --bar-absence-1:#ef4444;
            --bar-absence-2:#dc2626;

            --bar-neutral-1:#8b5cf6;
            --bar-neutral-2:#a855f7;
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
            width:1320px;
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
            margin-bottom:16px;
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
            word-break:break-word;
        }

        .block-title{
            font-size:16px;
            font-weight:800;
            margin:16px 0 10px;
        }

        .legend{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:14px;
        }

        .legend-pill{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:7px 11px;
            border-radius:999px;
            border:1px solid #d7e2f2;
            background:#f8fbff;
            font-size:12px;
            font-weight:700;
        }

        .dot{
            width:12px;
            height:12px;
            border-radius:999px;
            display:inline-block;
        }

        .dot.work{
            background:linear-gradient(135deg, var(--bar-work-1), var(--bar-work-2));
        }

        .dot.absence{
            background:linear-gradient(135deg, var(--bar-absence-1), var(--bar-absence-2));
        }

        .dot.neutral{
            background:linear-gradient(135deg, var(--bar-neutral-1), var(--bar-neutral-2));
        }

        .dot.conflict{
            background:linear-gradient(135deg, #ef4444, #991b1b);
        }

        .gantt-day{
            border:1px solid #d8deea;
            border-radius:12px;
            overflow:hidden;
            margin-bottom:16px;
            break-inside:avoid;
            page-break-inside:avoid;
        }

        .gantt-day-head{
            padding:10px 12px;
            background:#edf3ff;
            border-bottom:1px solid #d8deea;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }

        .gantt-day-title{
            font-size:14px;
            font-weight:800;
            color:#16315f;
        }

        .gantt-day-badge{
            display:inline-flex;
            align-items:center;
            padding:5px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:700;
            border:1px solid #d8deea;
            background:#fff;
        }

        .gantt-day-badge.conflict{
            color:#991b1b;
            background:#fee2e2;
            border-color:#fecaca;
        }

        .gantt-hours{
            display:grid;
            grid-template-columns:300px repeat(24, minmax(28px, 1fr));
            border-bottom:1px solid #d8deea;
            background:#f8fbff;
        }

        .gantt-hours .label,
        .gantt-hours .hour{
            padding:8px 4px;
            font-size:11px;
            font-weight:700;
            color:#44506a;
            text-align:center;
            border-right:1px solid #e3e8f2;
        }

        .gantt-hours .label{
            text-align:left;
            padding-left:10px;
        }

        .gantt-row{
            display:grid;
            grid-template-columns:300px 1fr;
            border-bottom:1px solid #edf1f7;
            min-height:56px;
        }

        .gantt-row:last-child{
            border-bottom:none;
        }

        .gantt-name{
            padding:10px;
            border-right:1px solid #e3e8f2;
            background:#fff;
            font-size:13px;
            font-weight:700;
            line-height:1.35;
        }

        .gantt-name small{
            display:block;
            margin-top:4px;
            color:var(--muted);
            font-size:11px;
            font-weight:600;
        }

        .gantt-track{
            position:relative;
            min-height:56px;
            background:
                repeating-linear-gradient(
                    to right,
                    #ffffff 0,
                    #ffffff calc(100% / 24 - 1px),
                    #e9eef7 calc(100% / 24 - 1px),
                    #e9eef7 calc(100% / 24)
                );
        }

        .gantt-bar{
            position:absolute;
            top:10px;
            height:36px;
            border-radius:10px;
            color:#fff;
            font-size:11px;
            font-weight:800;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:0 8px;
            overflow:hidden;
            white-space:nowrap;
            box-shadow:0 4px 10px rgba(0,0,0,.14);
            border:1px solid rgba(255,255,255,.25);
        }

        .gantt-bar.work{
            background:linear-gradient(135deg, var(--bar-work-1), var(--bar-work-2));
        }

        .gantt-bar.absence{
            background:linear-gradient(135deg, var(--bar-absence-1), var(--bar-absence-2));
        }

        .gantt-bar.neutral{
            background:linear-gradient(135deg, var(--bar-neutral-1), var(--bar-neutral-2));
        }

        .gantt-bar.conflict{
            box-shadow:0 0 0 2px rgba(127,29,29,.24), 0 4px 10px rgba(0,0,0,.14);
            border-color:rgba(255,255,255,.55);
        }

        .totals-table{
            width:100%;
            border-collapse:collapse;
            margin-top:10px;
        }

        .totals-table th,
        .totals-table td{
            padding:8px 6px;
            border-bottom:1px solid #d8deea;
            text-align:left;
            font-size:13px;
            vertical-align:top;
        }

        .totals-table th{
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
    <a class="btn" href="<?php echo h(app_url('modules/reports/report_gantt_destination.php') . '?' . http_build_query($_GET)); ?>">Torna al report</a>
</div>

<div class="page">
    <div class="head">
        <div>
            <div class="brand">Turnar</div>
            <div class="subtitle">Report Gantt destinazione stampabile</div>
        </div>
        <div class="period">
            Intervallo <?php echo h(format_date_it($dataDa)); ?> → <?php echo h(format_date_it($dataA)); ?>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Report Gantt destinazione</div>
        <div class="section-subtitle">Vista oraria delle assegnazioni con evidenza immediata delle sovrapposizioni.</div>

        <div class="note">
            Questo report mostra la giornata su scala 24 ore, così puoi vedere subito distribuzione turni e sovrapposizioni. I totali sotto seguono invece la logica ore nette del gestionale.
        </div>

        <div class="pause-box">
            <div class="pause-title">Pausa pranzo applicata</div>
            <div class="pause-value"><?php echo h($pausaLabel); ?></div>
            <div class="pause-text"><?php echo h($pausaNote); ?></div>
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
                <div class="card-label">Operatori</div>
                <div class="card-value"><?php echo count($operatoriCoinvolti); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Turni</div>
                <div class="card-value"><?php echo (int)($totali['turni'] ?? 0); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Giorni</div>
                <div class="card-value"><?php echo (int)($totali['giorni'] ?? 0); ?></div>
            </div>

            <div class="card">
                <div class="card-label">Conflitti</div>
                <div class="card-value"><?php echo (int)$conflittiTotali; ?></div>
            </div>

            <div class="card">
                <div class="card-label">Ore nette totali</div>
                <div class="card-value"><?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?> h</div>
            </div>
        </div>

        <div class="legend">
            <span class="legend-pill"><span class="dot work"></span> Lavoro</span>
            <span class="legend-pill"><span class="dot absence"></span> Assenza</span>
            <span class="legend-pill"><span class="dot neutral"></span> Neutro</span>
            <span class="legend-pill"><span class="dot conflict"></span> Conflitto</span>
        </div>

        <?php if (empty($groupedRows)): ?>
            <div class="note">Nessun risultato trovato con i filtri attuali.</div>
        <?php else: ?>
            <?php foreach ($groupedRows as $dataIso => $dayRows): ?>
                <?php
                $conflictsInDay = 0;
                foreach ($dayRows as $r) {
                    if (!empty($r['has_conflict'])) {
                        $conflictsInDay++;
                    }
                }
                ?>
                <div class="block-title"><?php echo h(format_date_it($dataIso)); ?></div>

                <div class="gantt-day">
                    <div class="gantt-day-head">
                        <div class="gantt-day-title">
                            <?php echo h(format_date_it($dataIso)); ?> · <?php echo count($dayRows); ?> turni
                        </div>

                        <?php if ($conflictsInDay > 0): ?>
                            <span class="gantt-day-badge conflict"><?php echo $conflictsInDay; ?> conflitti</span>
                        <?php else: ?>
                            <span class="gantt-day-badge">Nessun conflitto</span>
                        <?php endif; ?>
                    </div>

                    <div class="gantt-hours">
                        <div class="label">Operatore / turno</div>
                        <?php foreach ($hourLabels as $hour): ?>
                            <div class="hour"><?php echo h($hour); ?></div>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($dayRows as $turno): ?>
                        <?php
                        $typeClass = gantt_print_classify_row($turno);
                        $isConflict = !empty($turno['has_conflict']);
                        $operatore = trim((string)($turno['operatore'] ?? ''));
                        $oraDa = substr((string)($turno['ora_inizio'] ?? ''), 0, 5);
                        $oraA  = substr((string)($turno['ora_fine'] ?? ''), 0, 5);
                        $oreTxt = number_format((float)($turno['ore_nette'] ?? 0), 2, ',', '.');
                        ?>
                        <div class="gantt-row">
                            <div class="gantt-name">
                                <?php echo h($operatore); ?>
                                <small>
                                    <?php echo h($oraDa . ' → ' . $oraA); ?>
                                    · <?php echo h($oreTxt); ?> h
                                    <?php if (!empty($turno['is_capocantiere'])): ?>
                                        · Responsabile
                                    <?php endif; ?>
                                    <?php if ($isConflict): ?>
                                        · Conflitto
                                    <?php endif; ?>
                                </small>
                            </div>

                            <div class="gantt-track">
                                <div
                                    class="gantt-bar <?php echo h($typeClass); ?> <?php echo $isConflict ? 'conflict' : ''; ?>"
                                    style="<?php echo h(gantt_print_build_bar_style($turno)); ?>"
                                    title="<?php echo h($operatore . ' · ' . $oraDa . ' - ' . $oraA . ($isConflict ? ' · CONFLITTO' : '')); ?>"
                                >
                                    <?php echo h($operatore . ' · ' . $oraDa . ' - ' . $oraA); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="block-title">Riepilogo numerico</div>
        <table class="totals-table">
            <thead>
                <tr>
                    <th>Voce</th>
                    <th class="num">Valore</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Operatori coinvolti</td>
                    <td class="num"><?php echo count($operatoriCoinvolti); ?></td>
                </tr>
                <tr>
                    <td>Turni trovati</td>
                    <td class="num"><?php echo (int)($totali['turni'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td>Giorni coinvolti</td>
                    <td class="num"><?php echo (int)($totali['giorni'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td>Conflitti visivi</td>
                    <td class="num"><?php echo (int)$conflittiTotali; ?></td>
                </tr>
                <tr>
                    <td>Ore nette lavoro</td>
                    <td class="num"><?php echo h(number_format((float)$oreLavoro, 2, ',', '.')); ?> h</td>
                </tr>
                <tr>
                    <td>Ore nette assenza</td>
                    <td class="num"><?php echo h(number_format((float)$oreAssenza, 2, ',', '.')); ?> h</td>
                </tr>
                <tr>
                    <td>Ore nette neutre</td>
                    <td class="num"><?php echo h(number_format((float)$oreNeutre, 2, ',', '.')); ?> h</td>
                </tr>
                <tr>
                    <td><strong>Ore nette totali</strong></td>
                    <td class="num"><strong><?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?> h</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>