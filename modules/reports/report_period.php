<?php
// modules/reports/report_period.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../turni/TurniRepository.php';
require_once __DIR__ . '/ReportRepository.php';
require_once __DIR__ . '/report_share_helper.php';

require_login();
require_permission('reports.view');

$pageTitle    = 'Report periodo';
$pageSubtitle = 'Analisi turni per intervallo date, operatori e destinazioni';
$activeModule = 'reports';

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
function formatPausaPranzoReport($value): string
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

function reportPeriodTipoConteggio(array $row): string
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

function reportPeriodTipoConteggioClass(array $row): string
{
    $countsAsWork = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 0;
    $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
    $isSpecial = isset($row['is_special']) ? (int)$row['is_special'] : 0;

    if ($countsAsAbsence === 1) {
        return 'report-tipo-assenza';
    }

    if ($countsAsWork === 1) {
        return 'report-tipo-lavoro';
    }

    if ($isSpecial === 1) {
        return 'report-tipo-speciale';
    }

    return 'report-tipo-operativa';
}

// --------------------------------------------------
// DATI BASE FILTRI
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
// MAPPE RAPIDE
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
// LETTURA REPORT
// --------------------------------------------------
$rows = $reportRepo->getReportPeriodo($dataDa, $dataA, [
    'operatore_id'          => $operatoreId,
    'destinazione_id'       => $destinazioneId,
    'destinazioni_multiple' => $destinazioniMultiple,
]);

$totali = $reportRepo->calcolaTotali($rows);

// --------------------------------------------------
// RAGGRUPPAMENTO PER DATA
// --------------------------------------------------
$rowsByDate = [];
foreach ($rows as $row) {
    $data = (string)($row['data'] ?? '');
    if ($data === '') {
        $data = '0000-00-00';
    }

    if (!isset($rowsByDate[$data])) {
        $rowsByDate[$data] = [];
    }

    $rowsByDate[$data][] = $row;
}

// --------------------------------------------------
// INFO PAUSA PRANZO DA MOSTRARE
// --------------------------------------------------
$pausaLabel = 'Variabile in base al cantiere del turno';

if ($destinazioneId > 0 && isset($destinazioniMap[$destinazioneId])) {
    $pausaLabel = formatPausaPranzoReport($destinazioniMap[$destinazioneId]['pausa_pranzo'] ?? 0);
} elseif ($destinazioneId === 0 && count($destinazioniMultiple) === 1) {
    $onlyId = (int)reset($destinazioniMultiple);
    if ($onlyId > 0 && isset($destinazioniMap[$onlyId])) {
        $pausaLabel = formatPausaPranzoReport($destinazioniMap[$onlyId]['pausa_pranzo'] ?? 0);
    }
}

if ($destinazioneId > 0 || count($destinazioniMultiple) === 1) {
    $pausaNote = 'Le ore nette di questo report sono già calcolate al netto della pausa pranzo del cantiere selezionato: ' . $pausaLabel . '. Inoltre il tipo di conteggio dipende dai flag della destinazione: counts_as_work = Lavoro, counts_as_absence = Assenza, destinazione speciale senza flag = Speciale neutra. La pausa viene scalata solo quando il turno supera la soglia minima necessaria per ottenere 8 ore effettive di lavoro più la pausa prevista.';
} else {
    $pausaNote = 'Le ore nette di questo report sono già calcolate al netto della pausa pranzo prevista nel singolo cantiere di ogni turno. Inoltre il tipo di conteggio dipende dai flag della destinazione: counts_as_work = Lavoro, counts_as_absence = Assenza, destinazione speciale senza flag = Speciale neutra. La pausa può essere assente, di 30 minuti o di 1 ora, in base a quanto impostato nel cantiere. Viene scalata solo quando il turno supera la soglia minima necessaria.';
}

// --------------------------------------------------
// URL
// --------------------------------------------------
$queryArgs = [
    'data_da'               => $dataDa,
    'data_a'                => $dataA,
    'operatore_id'          => $operatoreId,
    'destinazione_id'       => $destinazioneId,
    'destinazioni_multiple' => $destinazioniMultiple,
];

$csvUrl   = app_url('modules/reports/export_period_csv.php') . '?' . http_build_query($queryArgs);
$printUrl = app_url('modules/reports/report_period_print.php') . '?' . http_build_query($queryArgs);

// --------------------------------------------------
// SHARE
// --------------------------------------------------
$shareTitle = 'Report periodo Turnar';
$shareText  = 'Report periodo dal ' . format_date_it($dataDa) . ' al ' . format_date_it($dataA)
    . ' · Turni: ' . (int)($totali['turni'] ?? 0)
    . ' · Ore nette: ' . number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.');

$share = build_report_share_data($printUrl, $shareTitle, $shareText);

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.report-shell{
    display:grid;
    gap:18px;
}

.report-grid-top{
    display:grid;
    grid-template-columns:1.2fr .8fr;
    gap:16px;
}

.report-panel,
.report-summary-card,
.report-table-wrap,
.report-empty,
.report-total-box,
.report-note-box{
    background:var(--content-card-bg);
    border:1px solid var(--line);
    border-radius:22px;
    box-shadow:var(--shadow);
}

.report-panel,
.report-summary-card,
.report-empty,
.report-total-box,
.report-note-box{
    padding:18px;
}

.report-panel h2,
.report-summary-card h3,
.report-empty h3,
.report-total-box h3,
.report-note-box h3{
    margin:0;
    color:var(--text);
}

.report-subtitle{
    margin-top:6px;
    color:var(--muted);
    font-size:13px;
    line-height:1.55;
}

.report-form-grid{
    display:grid;
    grid-template-columns:repeat(12, minmax(0, 1fr));
    gap:14px;
    margin-top:16px;
}

.col-3{grid-column:span 3;}
.col-4{grid-column:span 4;}
.col-6{grid-column:span 6;}
.col-12{grid-column:span 12;}

.report-field{
    display:flex;
    flex-direction:column;
    gap:8px;
    min-width:0;
}

.report-field label{
    font-size:12px;
    font-weight:700;
    color:var(--muted);
}

.report-input,
.report-select{
    width:100%;
    min-height:46px;
    padding:12px 14px;
    border-radius:16px;
    border:1px solid var(--line);
    background:var(--bg-3);
    color:var(--text);
    outline:none;
}

.report-input:focus,
.report-select:focus{
    border-color:rgba(110,168,255,.45);
    box-shadow:0 0 0 3px rgba(110,168,255,.12);
}

.report-actions,
.report-share-group{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}

.report-actions{
    margin-top:18px;
}

.report-share-group{
    margin-top:12px;
}

.report-btn{
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

.report-btn:hover{
    transform:translateY(-1px);
    filter:brightness(1.05);
}

.report-btn-primary{
    background:linear-gradient(135deg, var(--primary), var(--primary-2));
    color:#fff;
    border-color:transparent;
}

.report-btn-secondary{
    background:rgba(255,255,255,.05);
    color:var(--text);
    border-color:var(--line);
}

.report-btn-ghost{
    background:transparent;
    color:var(--text);
    border-color:var(--line);
}

.report-summary-list{
    display:grid;
    gap:10px;
    margin-top:14px;
}

.report-summary-row{
    display:flex;
    justify-content:space-between;
    gap:14px;
    padding:10px 0;
    border-bottom:1px solid rgba(255,255,255,.07);
}

.report-summary-row:last-child{
    border-bottom:none;
    padding-bottom:0;
}

.report-summary-label{
    color:var(--muted);
    font-size:13px;
    flex:0 0 170px;
}

.report-summary-value{
    text-align:right;
    font-size:13px;
    font-weight:700;
    color:var(--text);
    flex:1 1 auto;
    min-width:0;
    word-break:normal;
    overflow-wrap:anywhere;
    white-space:normal;
}

.report-multi-box{
    border:1px solid rgba(110,168,255,.18);
    background:linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.02));
    border-radius:18px;
    padding:14px;
    min-height:260px;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.03);
    overflow:hidden;
}

.report-multi-toolbar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:12px;
}

.report-multi-search{
    flex:1 1 260px;
    min-height:42px;
    padding:10px 14px;
    border-radius:999px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.03);
    color:var(--text);
    outline:none;
    min-width:0;
}

.report-multi-search:focus{
    border-color:rgba(110,168,255,.45);
    box-shadow:0 0 0 3px rgba(110,168,255,.12);
}

.report-mini-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:10px 14px;
    border-radius:999px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.04);
    color:var(--text);
    font-size:12px;
    font-weight:700;
    cursor:pointer;
    transition:.18s ease;
    white-space:nowrap;
}

.report-mini-btn:hover{
    background:rgba(255,255,255,.08);
}

.report-check-grid{
    display:flex;
    flex-direction:column;
    gap:10px;
    min-height:170px;
    max-height:340px;
    overflow-y:auto;
    overflow-x:hidden;
    padding-right:6px;
    width:100%;
}

.report-check-item{
    display:flex;
    align-items:flex-start;
    gap:12px;
    width:100%;
    min-width:0;
    padding:14px 16px;
    border-radius:16px;
    border:1px solid rgba(255,255,255,.08);
    background:rgba(255,255,255,.04);
}

.report-check-item.hidden-by-filter{
    display:none !important;
}

.report-check-item input[type="checkbox"]{
    appearance:auto !important;
    -webkit-appearance:checkbox !important;
    width:18px !important;
    height:18px !important;
    min-width:18px !important;
    min-height:18px !important;
    max-width:18px !important;
    max-height:18px !important;
    flex:0 0 18px !important;
    margin:3px 0 0 0 !important;
    padding:0 !important;
    border-radius:4px !important;
    cursor:pointer;
}

.report-check-main{
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    min-width:0;
    width:100%;
    flex:1 1 auto;
}

.report-check-title{
    display:block;
    width:100%;
    font-size:14px;
    font-weight:700;
    line-height:1.35;
    color:var(--text);
    white-space:normal;
    word-break:normal;
    overflow-wrap:anywhere;
}

.report-check-meta{
    display:block;
    width:100%;
    margin-top:5px;
    color:var(--muted);
    font-size:12px;
    line-height:1.45;
    white-space:normal;
    word-break:normal;
    overflow-wrap:anywhere;
}

.report-check-special{
    display:inline-flex;
    align-items:center;
    margin-top:8px;
    padding:4px 8px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
    background:rgba(139,92,246,.16);
    color:#e9d5ff;
    border:1px solid rgba(139,92,246,.24);
}

.report-chip-wrap{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.report-chip{
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

.report-table-wrap{
    overflow:hidden;
}

.report-table-head{
    padding:18px 18px 0;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
    flex-wrap:wrap;
}

.report-table-scroll{
    overflow:auto;
    padding:18px;
}

.report-table{
    width:100%;
    min-width:1120px;
    border-collapse:collapse;
}

.report-table th{
    text-align:left;
    padding:12px 14px;
    font-size:12px;
    letter-spacing:.03em;
    color:var(--muted);
    border-bottom:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.02);
    position:sticky;
    top:0;
    z-index:2;
}

.report-table td{
    padding:13px 14px;
    border-bottom:1px solid rgba(255,255,255,.06);
    font-size:13px;
    vertical-align:top;
    color:var(--text);
}

.report-table tr:hover td{
    background:rgba(255,255,255,.025);
}

.report-date-row td{
    background:rgba(110,168,255,.08);
    color:#dbeafe;
    font-weight:800;
    border-bottom:1px solid rgba(110,168,255,.18);
}

.report-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.04);
    font-size:12px;
    font-weight:700;
}

.report-hours{
    font-weight:800;
    color:#dcfce7;
}

.report-empty{
    text-align:center;
}

.report-empty p{
    margin:10px 0 0;
    color:var(--muted);
    line-height:1.6;
}

.report-total-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:14px;
    margin-top:16px;
}

.report-total-card{
    padding:16px;
    border-radius:18px;
    border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.03);
}

.report-total-label{
    color:var(--muted);
    font-size:12px;
    margin-bottom:8px;
}

.report-total-value{
    font-size:28px;
    font-weight:800;
    line-height:1;
    color:var(--text);
}

.report-total-note{
    margin-top:8px;
    color:var(--muted);
    font-size:12px;
}

.report-note-text{
    margin-top:12px;
    color:var(--text);
    font-size:13px;
    line-height:1.7;
}

.report-note-badge{
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

.report-tipo-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    border:1px solid transparent;
}

.report-tipo-lavoro{
    color:#dcfce7;
    background:rgba(34,197,94,.14);
    border-color:rgba(34,197,94,.28);
}

.report-tipo-assenza{
    color:#fee2e2;
    background:rgba(239,68,68,.14);
    border-color:rgba(239,68,68,.28);
}

.report-tipo-speciale{
    color:#f3e8ff;
    background:rgba(139,92,246,.16);
    border-color:rgba(139,92,246,.28);
}

.report-tipo-operativa{
    color:#dbeafe;
    background:rgba(59,130,246,.14);
    border-color:rgba(59,130,246,.28);
}

@media (max-width: 1100px){
    .report-grid-top{
        grid-template-columns:1fr;
    }

    .col-3,
    .col-4,
    .col-6{
        grid-column:span 6;
    }

    .report-total-grid{
        grid-template-columns:1fr;
    }
}

@media (max-width: 760px){
    .col-3,
    .col-4,
    .col-6,
    .col-12{
        grid-column:span 12;
    }

    .report-actions .report-btn,
    .report-actions .report-mini-btn,
    .report-share-group .report-btn,
    .report-share-group .report-mini-btn{
        width:100%;
        justify-content:center;
    }

    .report-table-head{
        flex-direction:column;
        align-items:flex-start;
    }

    .report-summary-row{
        flex-direction:column;
        align-items:flex-start;
    }

    .report-summary-label{
        flex:none;
        margin-bottom:4px;
    }

    .report-summary-value{
        text-align:left;
        width:100%;
    }

    .report-multi-toolbar{
        flex-direction:column;
        align-items:stretch;
    }

    .report-multi-search,
    .report-mini-btn{
        width:100%;
    }
}
</style>

<div class="report-shell">

    <div class="report-grid-top">
        <div class="card report-panel">
            <h2>Filtro report periodo</h2>
            <div class="report-subtitle">
                Seleziona intervallo date, operatore e destinazioni per generare il report.
            </div>

            <form method="get" action="">
                <div class="report-form-grid">
                    <div class="col-3">
                        <div class="report-field">
                            <label for="dataDa">Data da</label>
                            <input type="date" id="dataDa" name="data_da" class="report-input" value="<?php echo h($dataDa); ?>">
                        </div>
                    </div>

                    <div class="col-3">
                        <div class="report-field">
                            <label for="dataA">Data a</label>
                            <input type="date" id="dataA" name="data_a" class="report-input" value="<?php echo h($dataA); ?>">
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="report-field">
                            <label for="operatoreId">Operatore</label>
                            <select id="operatoreId" name="operatore_id" class="report-select">
                                <option value="0">Tutti gli operatori</option>
                                <?php foreach ($operatori as $op): ?>
                                    <?php
                                    $opId = (int)($op['id'] ?? 0);
                                    $opLabel = trim((string)($op['cognome'] ?? '') . ' ' . (string)($op['nome'] ?? ''));
                                    if ($opLabel === '') {
                                        $opLabel = 'Operatore #' . $opId;
                                    }
                                    ?>
                                    <option value="<?php echo $opId; ?>" <?php echo $operatoreId === $opId ? 'selected' : ''; ?>>
                                        <?php echo h($opLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="report-field">
                            <label for="destinazioneId">Destinazione singola</label>
                            <select id="destinazioneId" name="destinazione_id" class="report-select">
                                <option value="0">Tutte le destinazioni</option>
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

                    <div class="col-12">
                        <div class="report-field">
                            <label>Destinazioni multiple</label>

                            <div class="report-multi-box">
                                <div class="report-multi-toolbar">
                                    <input
                                        type="text"
                                        id="multiDestinationSearch"
                                        class="report-multi-search"
                                        placeholder="Cerca tra le destinazioni..."
                                        autocomplete="off"
                                    >
                                    <button type="button" id="selectAllDestBtn" class="report-mini-btn">Seleziona tutte</button>
                                    <button type="button" id="clearAllDestBtn" class="report-mini-btn">Deseleziona tutte</button>
                                </div>

                                <div class="report-check-grid" id="multiDestinationGrid">
                                    <?php foreach ($destinazioni as $dest): ?>
                                        <?php
                                        $destId = (int)($dest['id'] ?? 0);
                                        $commessa = trim((string)($dest['commessa'] ?? ''));
                                        $comune = trim((string)($dest['comune'] ?? ''));
                                        $tipologia = trim((string)($dest['tipologia'] ?? ''));
                                        $searchBlob = mb_strtolower(trim($commessa . ' ' . $comune . ' ' . $tipologia), 'UTF-8');
                                        $isSpecial = !empty($dest['is_special']) ? 1 : 0;
                                        $checked = in_array($destId, $destinazioniMultiple, true);
                                        ?>
                                        <label class="report-check-item" data-search="<?php echo h($searchBlob); ?>">
                                            <input
                                                type="checkbox"
                                                name="destinazioni_multiple[]"
                                                value="<?php echo $destId; ?>"
                                                <?php echo $checked ? 'checked' : ''; ?>
                                            >

                                            <span class="report-check-main">
                                                <span class="report-check-title">
                                                    <?php echo h($commessa !== '' ? $commessa : ('Destinazione #' . $destId)); ?>
                                                </span>

                                                <span class="report-check-meta">
                                                    <?php
                                                    $meta = [];
                                                    if ($comune !== '') $meta[] = $comune;
                                                    if ($tipologia !== '') $meta[] = $tipologia;
                                                    echo h(!empty($meta) ? implode(' · ', $meta) : 'Nessun dettaglio aggiuntivo');
                                                    ?>
                                                </span>

                                                <?php if ($isSpecial === 1): ?>
                                                    <span class="report-check-special">Speciale</span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="report-actions">
                    <button type="submit" class="report-btn report-btn-primary">Genera report</button>
                    <a href="<?php echo h(app_url('modules/reports/report_period.php')); ?>" class="report-btn report-btn-ghost">Reset filtri</a>
                    <a href="<?php echo h(app_url('modules/reports/index.php')); ?>" class="report-btn report-btn-secondary">Centro report</a>
                    <a href="<?php echo h($csvUrl); ?>" class="report-btn report-btn-secondary">Export CSV</a>
                    <a href="<?php echo h($printUrl); ?>" class="report-btn report-btn-secondary">Stampa / PDF</a>
                </div>

                <div class="report-share-group">
                    <button type="button" class="report-btn report-btn-secondary" id="shareNativeBtn">Condividi</button>
                    <a href="<?php echo h($share['whatsapp_url']); ?>" target="_blank" rel="noopener" class="report-btn report-btn-secondary">WhatsApp</a>
                    <a href="<?php echo h($share['email_url']); ?>" class="report-btn report-btn-secondary">Email</a>
                    <button type="button" class="report-btn report-btn-secondary" id="copyLinkBtn" data-link="<?php echo h($share['absolute_url']); ?>">Copia link</button>
                </div>
            </form>
        </div>

        <div class="card report-summary-card">
            <h3>Riepilogo</h3>
            <div class="report-subtitle">
                Sintesi veloce dei filtri attivi e del risultato ottenuto.
            </div>

            <div class="report-summary-list">
                <div class="report-summary-row">
                    <div class="report-summary-label">Periodo</div>
                    <div class="report-summary-value"><?php echo h(format_date_it($dataDa) . ' - ' . format_date_it($dataA)); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Operatore</div>
                    <div class="report-summary-value"><?php echo h($operatoreLabel); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Destinazione singola</div>
                    <div class="report-summary-value"><?php echo h($destinazioneLabel); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Destinazioni multiple</div>
                    <div class="report-summary-value">
                        <?php echo !empty($multiLabels) ? h(implode(', ', $multiLabels)) : 'Nessuna'; ?>
                    </div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Pausa pranzo</div>
                    <div class="report-summary-value"><?php echo h($pausaLabel); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Turni trovati</div>
                    <div class="report-summary-value"><?php echo (int)($totali['turni'] ?? 0); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Giorni coinvolti</div>
                    <div class="report-summary-value"><?php echo (int)($totali['giorni'] ?? 0); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Ore nette totali</div>
                    <div class="report-summary-value"><?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($rows)): ?>
        <div class="report-table-wrap">
            <div class="report-table-head">
                <div>
                    <h2>Dettaglio turni</h2>
                    <div class="report-subtitle">
                        Report dettagliato delle assegnazioni trovate nel periodo selezionato.
                    </div>
                </div>

                <div class="report-chip-wrap">
                    <span class="report-chip">Turni: <?php echo (int)($totali['turni'] ?? 0); ?></span>
                    <span class="report-chip">Giorni: <?php echo (int)($totali['giorni'] ?? 0); ?></span>
                    <span class="report-chip">Ore nette: <?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?></span>
                    <span class="report-chip">Pausa: <?php echo h($pausaLabel); ?></span>
                </div>
            </div>

            <div class="report-table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Operatore</th>
                            <th>Destinazione</th>
                            <th>Tipo conteggio</th>
                            <th>Comune</th>
                            <th>Tipologia</th>
                            <th>Inizio</th>
                            <th>Fine</th>
                            <th>Ore nette</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rowsByDate as $dataIso => $dayRows): ?>
                            <tr class="report-date-row">
                                <td colspan="9"><?php echo h(format_date_it($dataIso)); ?></td>
                            </tr>

                            <?php foreach ($dayRows as $row): ?>
                                <?php
                                $destName = trim((string)($row['destinazione'] ?? ''));
                                $tipoConteggio = reportPeriodTipoConteggio($row);
                                $tipoConteggioClass = reportPeriodTipoConteggioClass($row);
                                ?>
                                <tr>
                                    <td><?php echo h(format_date_it((string)$row['data'])); ?></td>
                                    <td><?php echo h((string)($row['operatore'] ?? '-')); ?></td>
                                    <td>
                                        <span class="report-pill">
                                            <?php echo h($destName !== '' ? $destName : '-'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="report-tipo-pill <?php echo h($tipoConteggioClass); ?>">
                                            <?php echo h($tipoConteggio); ?>
                                        </span>
                                    </td>
                                    <td><?php echo h((string)($row['comune'] ?? '-')); ?></td>
                                    <td><?php echo h((string)($row['tipologia'] ?? '-')); ?></td>
                                    <td><?php echo h((string)($row['ora_inizio'] ?? '-')); ?></td>
                                    <td><?php echo h((string)($row['ora_fine'] ?? '-')); ?></td>
                                    <td class="report-hours"><?php echo h(number_format((float)($row['ore_nette'] ?? 0), 2, ',', '.')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card report-note-box">
            <h3>Nota calcolo ore</h3>
            <div class="report-subtitle">
                Chiarimento sul modo in cui viene applicata la pausa pranzo nei conteggi.
            </div>

            <div class="report-note-badge">
                Pausa pranzo: <?php echo h($pausaLabel); ?>
            </div>

            <div class="report-note-text">
                <?php echo h($pausaNote); ?>
            </div>
        </div>

        <div class="card report-total-box">
            <h3>Totali finali</h3>
            <div class="report-subtitle">
                Riepilogo conclusivo del report generato.
            </div>

            <div class="report-total-grid">
                <div class="report-total-card">
                    <div class="report-total-label">Turni trovati</div>
                    <div class="report-total-value"><?php echo (int)($totali['turni'] ?? 0); ?></div>
                    <div class="report-total-note">Turni letti nel periodo selezionato</div>
                </div>

                <div class="report-total-card">
                    <div class="report-total-label">Giorni coinvolti</div>
                    <div class="report-total-value"><?php echo (int)($totali['giorni'] ?? 0); ?></div>
                    <div class="report-total-note">Date con almeno un turno presente</div>
                </div>

                <div class="report-total-card">
                    <div class="report-total-label">Ore nette totali</div>
                    <div class="report-total-value"><?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?></div>
                    <div class="report-total-note">Ore già al netto della pausa pranzo quando applicabile</div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card report-empty">
            <h3>Nessun risultato</h3>
            <p>
                Non sono stati trovati turni con i filtri attuali.
                Prova ad allargare il periodo oppure a rimuovere qualche filtro.
            </p>
        </div>
    <?php endif; ?>

</div>

<script>
(function () {
    const multiDestinationSearch = document.getElementById('multiDestinationSearch');
    const multiDestinationGrid = document.getElementById('multiDestinationGrid');
    const selectAllDestBtn = document.getElementById('selectAllDestBtn');
    const clearAllDestBtn = document.getElementById('clearAllDestBtn');
    const shareNativeBtn = document.getElementById('shareNativeBtn');
    const copyLinkBtn = document.getElementById('copyLinkBtn');

    const shareData = {
        title: <?php echo json_encode($share['title'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        text: <?php echo json_encode($share['text'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        url: <?php echo json_encode($share['absolute_url'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    };

    function normalizeText(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function applyMultiDestinationFilter() {
        if (!multiDestinationGrid || !multiDestinationSearch) return;

        const query = normalizeText(multiDestinationSearch.value);
        const tokens = query === '' ? [] : query.split(' ').filter(Boolean);
        const items = Array.from(multiDestinationGrid.querySelectorAll('.report-check-item'));

        items.forEach(function (item) {
            const searchText = normalizeText(item.getAttribute('data-search') || '');

            const visible = tokens.length === 0 || tokens.every(function (token) {
                return searchText.includes(token);
            });

            item.classList.toggle('hidden-by-filter', !visible);
        });
    }

    async function copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            alert('Link copiato negli appunti.');
        } catch (err) {
            const temp = document.createElement('textarea');
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            try {
                document.execCommand('copy');
                alert('Link copiato negli appunti.');
            } catch (e) {
                alert('Impossibile copiare il link automaticamente.');
            }
            document.body.removeChild(temp);
        }
    }

    if (multiDestinationSearch) {
        multiDestinationSearch.addEventListener('input', applyMultiDestinationFilter);
    }

    if (selectAllDestBtn && multiDestinationGrid) {
        selectAllDestBtn.addEventListener('click', function () {
            const visibleCheckboxes = Array.from(
                multiDestinationGrid.querySelectorAll('.report-check-item:not(.hidden-by-filter) input[type="checkbox"]')
            );
            visibleCheckboxes.forEach(function (checkbox) {
                checkbox.checked = true;
            });
        });
    }

    if (clearAllDestBtn && multiDestinationGrid) {
        clearAllDestBtn.addEventListener('click', function () {
            const allCheckboxes = Array.from(
                multiDestinationGrid.querySelectorAll('input[type="checkbox"]')
            );
            allCheckboxes.forEach(function (checkbox) {
                checkbox.checked = false;
            });
        });
    }

    if (shareNativeBtn) {
        shareNativeBtn.addEventListener('click', async function () {
            if (navigator.share) {
                try {
                    await navigator.share(shareData);
                } catch (err) {
                }
            } else {
                await copyToClipboard(shareData.url);
            }
        });
    }

    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', async function () {
            const link = copyLinkBtn.getAttribute('data-link') || shareData.url;
            await copyToClipboard(link);
        });
    }

    applyMultiDestinationFilter();
})();
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>