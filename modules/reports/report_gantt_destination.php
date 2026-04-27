<?php
// modules/reports/report_gantt_destination.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../turni/TurniRepository.php';
require_once __DIR__ . '/ReportRepository.php';

require_login();
require_permission('reports.view');

$pageTitle    = 'Report Gantt destinazione';
$pageSubtitle = 'Vista grafica turni su 24 ore per singola destinazione';
$activeModule = 'reports_gantt';

$db         = db_connect();
$turniRepo  = new TurniRepository($db);
$reportRepo = new ReportRepository($db);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// --------------------------------------------------
// HELPERS LOCALI
// --------------------------------------------------
function formatPausaPranzoGantt($value): string
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

function ganttPercentFromMinutes(int $minutes): float
{
    return max(0, min(100, ($minutes / 1440) * 100));
}

function ganttHourLabel(int $hour): string
{
    return str_pad((string)$hour, 2, '0', STR_PAD_LEFT) . ':00';
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
// MAPPA DESTINAZIONI
// --------------------------------------------------
$destinazioniMap = [];
foreach ($destinazioni as $dest) {
    $destinazioniMap[(int)($dest['id'] ?? 0)] = $dest;
}

// --------------------------------------------------
// LABEL FILTRO
// --------------------------------------------------
$destinazioneLabel = 'Nessuna destinazione selezionata';
$pausaLabel = 'Nessun dato';
$destComuneLabel = '-';
$destTipologiaLabel = '-';

if ($destinazioneId > 0 && isset($destinazioniMap[$destinazioneId])) {
    $dest = $destinazioniMap[$destinazioneId];

    $destinazioneLabel = trim((string)($dest['commessa'] ?? ''));
    if ($destinazioneLabel === '') {
        $destinazioneLabel = 'Destinazione #' . $destinazioneId;
    }

    $destComuneLabel    = trim((string)($dest['comune'] ?? '')) ?: '-';
    $destTipologiaLabel = trim((string)($dest['tipologia'] ?? '')) ?: '-';
    $pausaLabel         = formatPausaPranzoGantt($dest['pausa_pranzo'] ?? 0);
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
// RAGGRUPPAMENTO PER DATA
// --------------------------------------------------
$rowsByDate = [];
foreach ($rows as $row) {
    $data = (string)($row['data'] ?? '');
    if ($data === '') {
        continue;
    }

    if (!isset($rowsByDate[$data])) {
        $rowsByDate[$data] = [];
    }

    $rowsByDate[$data][] = $row;
}

ksort($rowsByDate);

// --------------------------------------------------
// STATISTICHE
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

    $countsAsWork    = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 1;
    $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
    $isSpecial       = !empty($row['is_special']) ? 1 : 0;

    if ($countsAsWork === 1) {
        $oreLavoro += $ore;
    } elseif ($countsAsAbsence === 1) {
        $oreAssenza += abs($ore);
    } elseif ($isSpecial === 1) {
        $oreNeutre += $ore;
    }
}

$operatoriCoinvolti = array_keys($operatoriCoinvoltiMap);
sort($operatoriCoinvolti, SORT_NATURAL | SORT_FLAG_CASE);

// --------------------------------------------------
// URL EXPORT / STAMPA
// --------------------------------------------------
$queryArgs = [
    'data_da'         => $dataDa,
    'data_a'          => $dataA,
    'destinazione_id' => $destinazioneId,
];

$csvUrl   = app_url('modules/reports/export_gantt_destination_csv.php') . '?' . http_build_query($queryArgs);
$printUrl = app_url('modules/reports/report_gantt_destination_print.php') . '?' . http_build_query($queryArgs);

// --------------------------------------------------
// NOTE
// --------------------------------------------------
$pausaNote = 'Nel grafico la barra rappresenta la posizione oraria del turno sulla giornata 00:00 → 24:00. I totali numerici invece seguono le stesse regole dei report: pausa pranzo del cantiere e flag destinazione (lavoro, assenza o neutro).';

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.gantt-shell{
    display:grid;
    gap:18px;
}

.gantt-grid-top{
    display:grid;
    grid-template-columns:1.15fr .85fr;
    gap:16px;
}

.gantt-panel,
.gantt-summary-card,
.gantt-empty,
.gantt-total-box,
.gantt-note-box,
.gantt-timeline-box{
    background:var(--content-card-bg);
    border:1px solid var(--line);
    border-radius:22px;
    box-shadow:var(--shadow);
}

.gantt-panel,
.gantt-summary-card,
.gantt-empty,
.gantt-total-box,
.gantt-note-box{
    padding:18px;
}

.gantt-timeline-box{
    overflow:hidden;
}

.gantt-panel h2,
.gantt-summary-card h3,
.gantt-empty h3,
.gantt-total-box h3,
.gantt-note-box h3{
    margin:0;
    color:var(--text);
}

.gantt-subtitle{
    margin-top:6px;
    color:var(--muted);
    font-size:13px;
    line-height:1.55;
}

.gantt-form-grid{
    display:grid;
    grid-template-columns:repeat(12, minmax(0, 1fr));
    gap:14px;
    margin-top:16px;
}

.col-3{grid-column:span 3;}
.col-4{grid-column:span 4;}
.col-6{grid-column:span 6;}
.col-12{grid-column:span 12;}

.gantt-field{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.gantt-field label{
    font-size:12px;
    font-weight:700;
    color:var(--muted);
}

.gantt-input,
.gantt-select{
    width:100%;
    min-height:46px;
    padding:12px 14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:var(--bg-3);
    color:var(--text);
    outline:none;
}

.gantt-input:focus,
.gantt-select:focus{
    border-color:rgba(110,168,255,.45);
    box-shadow:0 0 0 3px rgba(110,168,255,.12);
}

.gantt-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    margin-top:18px;
}

.gantt-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:44px;
    padding:10px 16px;
    border-radius:999px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.05);
    color:var(--text);
    font-size:13px;
    font-weight:700;
    text-decoration:none;
    cursor:pointer;
    transition:.18s ease;
    white-space:nowrap;
}

.gantt-btn:hover{
    transform:translateY(-1px);
    filter:brightness(1.05);
}

.gantt-btn-primary{
    background:linear-gradient(135deg, var(--primary), var(--primary-2));
    color:#fff;
    border-color:transparent;
}

.gantt-btn-secondary{
    background:rgba(255,255,255,.05);
    color:var(--text);
    border-color:var(--line);
}

.gantt-btn-ghost{
    background:transparent;
    color:var(--text);
    border-color:var(--line);
}

.gantt-summary-list{
    display:grid;
    gap:10px;
    margin-top:14px;
}

.gantt-summary-row{
    display:flex;
    justify-content:space-between;
    gap:14px;
    padding:10px 0;
    border-bottom:1px solid rgba(255,255,255,.07);
}

.gantt-summary-row:last-child{
    border-bottom:none;
    padding-bottom:0;
}

.gantt-summary-label{
    color:var(--muted);
    font-size:13px;
}

.gantt-summary-value{
    text-align:right;
    font-size:13px;
    font-weight:700;
    word-break:break-word;
    color:var(--text);
}

.gantt-top-head{
    padding:18px 18px 0;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
    flex-wrap:wrap;
}

.gantt-chip-wrap{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.gantt-chip{
    display:inline-flex;
    align-items:center;
    padding:7px 11px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.04);
    font-size:12px;
    font-weight:700;
    color:var(--text);
}

.gantt-legend{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    padding:0 18px;
    margin-top:14px;
}

.gantt-legend-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 11px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.04);
    color:var(--text);
}

.gantt-dot{
    width:10px;
    height:10px;
    border-radius:999px;
    flex:0 0 auto;
}

.gantt-dot.work{ background:#22c55e; }
.gantt-dot.absence{ background:#ef4444; }
.gantt-dot.neutral{ background:#8b5cf6; }
.gantt-dot.conflict{ background:#f59e0b; }

.gantt-scroll{
    overflow:auto;
    padding:18px;
}

.gantt-board{
    min-width:1280px;
    display:grid;
    gap:18px;
}

.gantt-day{
    border:1px solid rgba(255,255,255,.08);
    border-radius:18px;
    background:rgba(255,255,255,.02);
    overflow:hidden;
}

.gantt-day-head{
    padding:14px 16px;
    border-bottom:1px solid rgba(255,255,255,.08);
    background:rgba(255,255,255,.03);
    display:flex;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    align-items:center;
}

.gantt-day-title{
    font-size:16px;
    font-weight:800;
    color:var(--text);
}

.gantt-day-sub{
    color:var(--muted);
    font-size:12px;
    margin-top:4px;
}

.gantt-day-badge{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.04);
    color:var(--text);
}

.gantt-day-badge.conflict{
    color:#fecaca;
    border-color:rgba(239,68,68,.28);
    background:rgba(239,68,68,.12);
}

.gantt-axis-wrap{
    padding:14px 16px 16px;
}

.gantt-axis{
    position:relative;
}

.gantt-hour-row{
    display:grid;
    grid-template-columns:220px repeat(24, minmax(36px, 1fr));
    gap:0;
    margin-bottom:8px;
    align-items:center;
}

.gantt-hour-label-spacer{
    font-size:11px;
    color:transparent;
    user-select:none;
}

.gantt-hour{
    font-size:11px;
    color:var(--muted);
    text-align:left;
    padding-left:4px;
}

.gantt-lanes{
    position:relative;
    border:1px solid rgba(255,255,255,.08);
    border-radius:14px;
    overflow:hidden;
}

.gantt-lane{
    position:relative;
    display:grid;
    grid-template-columns:220px 1fr;
    min-height:56px;
    border-bottom:1px solid rgba(255,255,255,.06);
    background:
        linear-gradient(to right, rgba(255,255,255,.025), rgba(255,255,255,.025)) left top / 220px 100% no-repeat,
        repeating-linear-gradient(
            to right,
            rgba(255,255,255,.05) 0,
            rgba(255,255,255,.05) calc(100% / 24),
            rgba(255,255,255,.02) calc(100% / 24),
            rgba(255,255,255,.02) calc((100% / 24) * 2)
        );
}

.gantt-lane:last-child{
    border-bottom:none;
}

.gantt-lane-label{
    padding:10px 12px;
    border-right:1px solid rgba(255,255,255,.06);
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:4px;
    min-width:0;
    background:rgba(255,255,255,.025);
}

.gantt-lane-name{
    font-size:13px;
    font-weight:800;
    color:var(--text);
    line-height:1.25;
    word-break:break-word;
}

.gantt-lane-sub{
    font-size:11px;
    color:var(--muted);
    line-height:1.35;
}

.gantt-lane-track{
    position:relative;
    min-height:56px;
}

.gantt-bar{
    position:absolute;
    top:10px;
    height:36px;
    min-width:28px;
    border-radius:12px;
    border:1px solid rgba(255,255,255,.16);
    padding:6px 10px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:2px;
    box-shadow:0 8px 16px rgba(0,0,0,.18);
}

.gantt-bar.work{
    background:linear-gradient(135deg, rgba(34,197,94,.88), rgba(16,185,129,.82));
    color:#ecfdf5;
}

.gantt-bar.absence{
    background:linear-gradient(135deg, rgba(239,68,68,.88), rgba(248,113,113,.82));
    color:#fff1f2;
}

.gantt-bar.neutral{
    background:linear-gradient(135deg, rgba(139,92,246,.88), rgba(168,85,247,.82));
    color:#f5f3ff;
}

.gantt-bar.conflict{
    box-shadow:0 0 0 2px rgba(239,68,68,.30), 0 8px 16px rgba(0,0,0,.18);
    border-color:rgba(255,235,235,.55);
}

.gantt-bar-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
}

.gantt-bar-name{
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.gantt-bar-time{
    font-size:11px;
    opacity:.95;
    white-space:nowrap;
}

.gantt-bar-meta{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}

.gantt-mini{
    display:inline-flex;
    align-items:center;
    padding:2px 7px;
    border-radius:999px;
    font-size:10px;
    font-weight:800;
    background:rgba(255,255,255,.18);
    border:1px solid rgba(255,255,255,.16);
}

.gantt-empty{
    text-align:center;
    padding:18px;
}

.gantt-empty p{
    margin:10px 0 0;
    color:var(--muted);
    line-height:1.6;
}

.gantt-total-grid{
    display:grid;
    grid-template-columns:repeat(6, minmax(0, 1fr));
    gap:14px;
    margin-top:16px;
}

.gantt-total-card{
    padding:16px;
    border-radius:18px;
    border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.03);
}

.gantt-total-label{
    color:var(--muted);
    font-size:12px;
    margin-bottom:8px;
}

.gantt-total-value{
    font-size:28px;
    font-weight:800;
    line-height:1;
    color:var(--text);
}

.gantt-total-note{
    margin-top:8px;
    color:var(--muted);
    font-size:12px;
}

.gantt-note-text{
    margin-top:12px;
    color:var(--text);
    font-size:13px;
    line-height:1.7;
}

.gantt-note-badge{
    display:inline-flex;
    align-items:center;
    padding:7px 11px;
    border-radius:999px;
    border:1px solid rgba(110,168,255,.30);
    background:rgba(110,168,255,.10);
    color:#dbeafe;
    font-size:12px;
    font-weight:700;
    margin-top:12px;
}

@media (max-width: 1200px){
    .gantt-grid-top{
        grid-template-columns:1fr;
    }

    .col-3,
    .col-4,
    .col-6{
        grid-column:span 6;
    }

    .gantt-total-grid{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px){
    .col-3,
    .col-4,
    .col-6,
    .col-12{
        grid-column:span 12;
    }

    .gantt-actions .gantt-btn{
        width:100%;
        justify-content:center;
    }

    .gantt-total-grid{
        grid-template-columns:1fr;
    }
}
</style>

<div class="gantt-shell">

    <div class="gantt-grid-top">
        <div class="card gantt-panel">
            <h2>Filtro report Gantt destinazione</h2>
            <div class="gantt-subtitle">
                Seleziona la destinazione e il periodo. Il grafico mostra ogni giornata su scala 24 ore con sovrapposizioni e conflitti visibili a colpo d’occhio.
            </div>

            <form method="get" action="">
                <div class="gantt-form-grid">
                    <div class="col-3">
                        <div class="gantt-field">
                            <label for="dataDa">Data da</label>
                            <input type="date" id="dataDa" name="data_da" class="gantt-input" value="<?php echo h($dataDa); ?>">
                        </div>
                    </div>

                    <div class="col-3">
                        <div class="gantt-field">
                            <label for="dataA">Data a</label>
                            <input type="date" id="dataA" name="data_a" class="gantt-input" value="<?php echo h($dataA); ?>">
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="gantt-field">
                            <label for="destinazioneId">Destinazione</label>
                            <select id="destinazioneId" name="destinazione_id" class="gantt-select">
                                <option value="0">Seleziona destinazione</option>
                                <?php foreach ($destinazioni as $dest): ?>
                                    <?php
                                    $destId = (int)($dest['id'] ?? 0);
                                    $destLabel = trim((string)($dest['commessa'] ?? ''));
                                    $destComune = trim((string)($dest['comune'] ?? ''));
                                    if ($destComune !== '') {
                                        $destLabel .= ' · ' . $destComune;
                                    }
                                    if ($destLabel === '') {
                                        $destLabel = 'Destinazione #' . $destId;
                                    }
                                    ?>
                                    <option value="<?php echo $destId; ?>" <?php echo $destinazioneId === $destId ? 'selected' : ''; ?>>
                                        <?php echo h($destLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="gantt-actions">
                    <button type="submit" class="gantt-btn gantt-btn-primary">Genera Gantt</button>
                    <a href="<?php echo h(app_url('modules/reports/report_gantt_destination.php')); ?>" class="gantt-btn gantt-btn-ghost">Reset filtri</a>
                    <a href="<?php echo h(app_url('modules/reports/index.php')); ?>" class="gantt-btn gantt-btn-secondary">Centro report</a>

                    <?php if ($destinazioneId > 0): ?>
                        <a href="<?php echo h($csvUrl); ?>" class="gantt-btn gantt-btn-secondary">Export CSV</a>
                        <a href="<?php echo h($printUrl); ?>" class="gantt-btn gantt-btn-secondary">Stampa / PDF</a>
                        <a href="<?php echo h(app_url('modules/reports/report_destination.php?data_da=' . urlencode($dataDa) . '&data_a=' . urlencode($dataA) . '&destinazione_id=' . $destinazioneId)); ?>" class="gantt-btn gantt-btn-secondary">Report classico</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card gantt-summary-card">
            <h3>Riepilogo</h3>
            <div class="gantt-subtitle">
                Sintesi veloce del report grafico.
            </div>

            <div class="gantt-summary-list">
                <div class="gantt-summary-row">
                    <div class="gantt-summary-label">Periodo</div>
                    <div class="gantt-summary-value"><?php echo h(format_date_it($dataDa) . ' - ' . format_date_it($dataA)); ?></div>
                </div>

                <div class="gantt-summary-row">
                    <div class="gantt-summary-label">Destinazione</div>
                    <div class="gantt-summary-value"><?php echo h($destinazioneLabel); ?></div>
                </div>

                <div class="gantt-summary-row">
                    <div class="gantt-summary-label">Comune</div>
                    <div class="gantt-summary-value"><?php echo h($destComuneLabel); ?></div>
                </div>

                <div class="gantt-summary-row">
                    <div class="gantt-summary-label">Tipologia</div>
                    <div class="gantt-summary-value"><?php echo h($destTipologiaLabel); ?></div>
                </div>

                <div class="gantt-summary-row">
                    <div class="gantt-summary-label">Pausa cantiere</div>
                    <div class="gantt-summary-value"><?php echo h($pausaLabel); ?></div>
                </div>

                <div class="gantt-summary-row">
                    <div class="gantt-summary-label">Operatori coinvolti</div>
                    <div class="gantt-summary-value"><?php echo count($operatoriCoinvolti); ?></div>
                </div>

                <div class="gantt-summary-row">
                    <div class="gantt-summary-label">Turni trovati</div>
                    <div class="gantt-summary-value"><?php echo (int)($totali['turni'] ?? 0); ?></div>
                </div>

                <div class="gantt-summary-row">
                    <div class="gantt-summary-label">Conflitti visivi</div>
                    <div class="gantt-summary-value"><?php echo (int)$conflittiTotali; ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($destinazioneId > 0 && !empty($rowsByDate)): ?>
        <div class="gantt-timeline-box">
            <div class="gantt-top-head">
                <div>
                    <h2>Timeline giornaliera 24 ore</h2>
                    <div class="gantt-subtitle">
                        Una riga per turno. La colonna sinistra ti fa leggere subito l’operatore, mentre la barra ti fa vedere l’occupazione oraria reale nella giornata.
                    </div>
                </div>

                <div class="gantt-chip-wrap">
                    <span class="gantt-chip">Giorni: <?php echo (int)($totali['giorni'] ?? 0); ?></span>
                    <span class="gantt-chip">Turni: <?php echo (int)($totali['turni'] ?? 0); ?></span>
                    <span class="gantt-chip">Operatori: <?php echo count($operatoriCoinvolti); ?></span>
                    <span class="gantt-chip">Ore nette: <?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?></span>
                </div>
            </div>

            <div class="gantt-legend">
                <span class="gantt-legend-pill"><span class="gantt-dot work"></span>Lavoro</span>
                <span class="gantt-legend-pill"><span class="gantt-dot absence"></span>Assenza</span>
                <span class="gantt-legend-pill"><span class="gantt-dot neutral"></span>Neutro / speciale</span>
                <span class="gantt-legend-pill"><span class="gantt-dot conflict"></span>Conflitto</span>
            </div>

            <div class="gantt-scroll">
                <div class="gantt-board">
                    <?php foreach ($rowsByDate as $dataIso => $dayRows): ?>
                        <?php
                        usort($dayRows, static function (array $a, array $b): int {
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

                        $conflictsInDay = 0;
                        foreach ($dayRows as $r) {
                            if (!empty($r['has_conflict'])) {
                                $conflictsInDay++;
                            }
                        }
                        ?>
                        <div class="gantt-day">
                            <div class="gantt-day-head">
                                <div>
                                    <div class="gantt-day-title"><?php echo h(format_date_it($dataIso)); ?></div>
                                    <div class="gantt-day-sub">
                                        <?php echo count($dayRows); ?> turni presenti nella giornata
                                    </div>
                                </div>

                                <?php if ($conflictsInDay > 0): ?>
                                    <span class="gantt-day-badge conflict"><?php echo $conflictsInDay; ?> conflitti</span>
                                <?php else: ?>
                                    <span class="gantt-day-badge">Nessun conflitto</span>
                                <?php endif; ?>
                            </div>

                            <div class="gantt-axis-wrap">
                                <div class="gantt-axis">
                                    <div class="gantt-hour-row">
                                        <div class="gantt-hour-label-spacer">.</div>
                                        <?php for ($h = 0; $h < 24; $h++): ?>
                                            <div class="gantt-hour"><?php echo h(ganttHourLabel($h)); ?></div>
                                        <?php endfor; ?>
                                    </div>

                                    <div class="gantt-lanes">
                                        <?php foreach ($dayRows as $row): ?>
                                            <?php
                                            $startMinutes = (int)($row['minuti_inizio'] ?? 0);
                                            $endMinutes   = (int)($row['minuti_fine'] ?? 0);

                                            if ($endMinutes <= $startMinutes) {
                                                $endMinutes = $startMinutes + 1;
                                            }

                                            $displayEnd = min($endMinutes, 1440);
                                            $left  = ganttPercentFromMinutes($startMinutes);
                                            $width = ganttPercentFromMinutes(max(1, $displayEnd - $startMinutes));

                                            $countsAsWork    = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 1;
                                            $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
                                            $isSpecial       = !empty($row['is_special']) ? 1 : 0;

                                            $barClass = 'work';
                                            if ($countsAsAbsence === 1) {
                                                $barClass = 'absence';
                                            } elseif ($countsAsWork !== 1 && $isSpecial === 1) {
                                                $barClass = 'neutral';
                                            }

                                            $hasConflict = !empty($row['has_conflict']);
                                            $nomeOperatore = trim((string)($row['operatore'] ?? '-'));
                                            $oraInizioShort = substr((string)($row['ora_inizio'] ?? ''), 0, 5);
                                            $oraFineShort   = substr((string)($row['ora_fine'] ?? ''), 0, 5);
                                            ?>
                                            <div class="gantt-lane">
                                                <div class="gantt-lane-label">
                                                    <div class="gantt-lane-name"><?php echo h($nomeOperatore); ?></div>
                                                    <div class="gantt-lane-sub">
                                                        <?php echo h($oraInizioShort . ' → ' . $oraFineShort); ?>
                                                        ·
                                                        <?php echo h(number_format((float)($row['ore_nette'] ?? 0), 2, ',', '.')); ?> h
                                                    </div>
                                                </div>

                                                <div class="gantt-lane-track">
                                                    <div
                                                        class="gantt-bar <?php echo h($barClass); ?> <?php echo $hasConflict ? 'conflict' : ''; ?>"
                                                        style="left: <?php echo h(number_format($left, 4, '.', '')); ?>%; width: <?php echo h(number_format($width, 4, '.', '')); ?>%;"
                                                        title="<?php echo h($nomeOperatore . ' · ' . $oraInizioShort . ' - ' . $oraFineShort); ?>"
                                                    >
                                                        <div class="gantt-bar-top">
                                                            <div class="gantt-bar-name"><?php echo h($nomeOperatore); ?></div>
                                                            <div class="gantt-bar-time"><?php echo h($oraInizioShort . ' → ' . $oraFineShort); ?></div>
                                                        </div>

                                                        <div class="gantt-bar-meta">
                                                            <?php if (!empty($row['is_capocantiere'])): ?>
                                                                <span class="gantt-mini">Responsabile</span>
                                                            <?php endif; ?>

                                                            <?php if ($hasConflict): ?>
                                                                <span class="gantt-mini">Conflitto</span>
                                                            <?php endif; ?>

                                                            <span class="gantt-mini">
                                                                <?php echo h(number_format((float)($row['ore_nette'] ?? 0), 2, ',', '.')); ?> h
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card gantt-note-box">
            <h3>Nota calcolo ore</h3>
            <div class="gantt-subtitle">
                Differenza tra visualizzazione grafica e totali numerici.
            </div>

            <div class="gantt-note-badge">
                Pausa pranzo: <?php echo h($pausaLabel); ?>
            </div>

            <div class="gantt-note-text">
                <?php echo h($pausaNote); ?>
            </div>
        </div>

        <div class="card gantt-total-box">
            <h3>Totali finali</h3>
            <div class="gantt-subtitle">
                Riepilogo conclusivo del report gantt destinazione.
            </div>

            <div class="gantt-total-grid">
                <div class="gantt-total-card">
                    <div class="gantt-total-label">Operatori coinvolti</div>
                    <div class="gantt-total-value"><?php echo count($operatoriCoinvolti); ?></div>
                    <div class="gantt-total-note">Operatori presenti nel periodo</div>
                </div>

                <div class="gantt-total-card">
                    <div class="gantt-total-label">Turni trovati</div>
                    <div class="gantt-total-value"><?php echo (int)($totali['turni'] ?? 0); ?></div>
                    <div class="gantt-total-note">Turni sulla destinazione selezionata</div>
                </div>

                <div class="gantt-total-card">
                    <div class="gantt-total-label">Giorni coinvolti</div>
                    <div class="gantt-total-value"><?php echo (int)($totali['giorni'] ?? 0); ?></div>
                    <div class="gantt-total-note">Date con almeno un turno</div>
                </div>

                <div class="gantt-total-card">
                    <div class="gantt-total-label">Ore lavoro</div>
                    <div class="gantt-total-value"><?php echo h(number_format($oreLavoro, 2, ',', '.')); ?></div>
                    <div class="gantt-total-note">Somma ore con flag lavoro</div>
                </div>

                <div class="gantt-total-card">
                    <div class="gantt-total-label">Ore assenza</div>
                    <div class="gantt-total-value"><?php echo h(number_format($oreAssenza, 2, ',', '.')); ?></div>
                    <div class="gantt-total-note">Somma ore con flag assenza</div>
                </div>

                <div class="gantt-total-card">
                    <div class="gantt-total-label">Ore neutre</div>
                    <div class="gantt-total-value"><?php echo h(number_format($oreNeutre, 2, ',', '.')); ?></div>
                    <div class="gantt-total-note">Somma ore speciali neutre</div>
                </div>
            </div>
        </div>

    <?php elseif ($destinazioneId > 0): ?>
        <div class="card gantt-empty">
            <h3>Nessun risultato</h3>
            <p>
                Non sono stati trovati turni per la destinazione selezionata nel periodo indicato.
            </p>
        </div>
    <?php else: ?>
        <div class="card gantt-empty">
            <h3>Seleziona una destinazione</h3>
            <p>
                Scegli periodo e cantiere per generare il report gantt su 24 ore.
            </p>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>