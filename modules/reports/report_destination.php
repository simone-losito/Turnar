<?php
// modules/reports/report_destination.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../turni/TurniRepository.php';
require_once __DIR__ . '/ReportRepository.php';
require_once __DIR__ . '/report_share_helper.php';

require_login();
require_permission('reports.view');

$pageTitle    = 'Report destinazione';
$pageSubtitle = 'Analisi turni e ore nette per singola destinazione';
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
function formatPausaPranzoDestinazione($value): string
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

function getDestinazioneWorkRuleLabel(array $dest): string
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

function getDestinazioneWorkRuleNote(array $dest): string
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
// DATI BASE FILTRI
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
$destWorkRuleLabel = '-';
$destWorkRuleNote = '';
$destIsSpecialLabel = 'No';

if ($destinazioneId > 0 && isset($destinazioniMap[$destinazioneId])) {
    $dest = $destinazioniMap[$destinazioneId];

    $destinazioneLabel = trim((string)($dest['commessa'] ?? ''));
    if ($destinazioneLabel === '') {
        $destinazioneLabel = 'Destinazione #' . $destinazioneId;
    }

    $destComuneLabel    = trim((string)($dest['comune'] ?? '')) ?: '-';
    $destTipologiaLabel = trim((string)($dest['tipologia'] ?? '')) ?: '-';
    $pausaLabel         = formatPausaPranzoDestinazione($dest['pausa_pranzo'] ?? 0);
    $destWorkRuleLabel  = getDestinazioneWorkRuleLabel($dest);
    $destWorkRuleNote   = getDestinazioneWorkRuleNote($dest);
    $destIsSpecialLabel = !empty($dest['is_special']) ? 'Sì' : 'No';
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
// NOTE REPORT
// --------------------------------------------------
$pausaNote = 'Le ore nette di questo report sono già calcolate al netto della pausa pranzo del cantiere selezionato: ' . $pausaLabel . '. La pausa viene scalata solo quando il turno supera la soglia minima necessaria per ottenere 8 ore effettive di lavoro più la pausa prevista.';

$flagNote = 'Questo report somma solo le ore che la destinazione rende conteggiabili come lavoro. Se la destinazione è marcata come assenza oppure è speciale neutra senza flag lavoro, le sue ore non entrano nel monte ore lavorato.';

// --------------------------------------------------
// URL EXPORT / STAMPA
// --------------------------------------------------
$queryArgs = [
    'data_da'         => $dataDa,
    'data_a'          => $dataA,
    'destinazione_id' => $destinazioneId,
];

$csvUrl   = app_url('modules/reports/export_destination_csv.php') . '?' . http_build_query($queryArgs);
$printUrl = app_url('modules/reports/report_destination_print.php') . '?' . http_build_query($queryArgs);

// --------------------------------------------------
// SHARE
// --------------------------------------------------
$shareTitle = 'Report destinazione Turnar';
$shareText  = 'Report destinazione: ' . $destinazioneLabel
    . ' · Periodo ' . format_date_it($dataDa) . ' - ' . format_date_it($dataA)
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
    grid-template-columns:1.15fr .85fr;
    gap:16px;
}

.report-panel,
.report-summary-card,
.report-table-wrap,
.report-empty,
.report-total-box,
.report-note-box,
.report-operator-box{
    background:var(--content-card-bg);
    border:1px solid var(--line);
    border-radius:22px;
    box-shadow:var(--shadow);
}

.report-panel,
.report-summary-card,
.report-empty,
.report-total-box,
.report-note-box,
.report-operator-box{
    padding:18px;
}

.report-panel h2,
.report-summary-card h3,
.report-empty h3,
.report-total-box h3,
.report-note-box h3,
.report-operator-box h3{
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
}

.report-summary-value{
    text-align:right;
    font-size:13px;
    font-weight:700;
    word-break:break-word;
    color:var(--text);
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
    min-width:980px;
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
    grid-template-columns:repeat(4, minmax(0, 1fr));
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
    margin-right:8px;
}

.report-warning{
    margin-top:16px;
    padding:14px 16px;
    border-radius:16px;
    border:1px dashed rgba(251,191,36,.24);
    background:rgba(251,191,36,.08);
    color:#fde68a;
    font-size:13px;
    line-height:1.6;
}

.report-mini-table{
    width:100%;
    border-collapse:collapse;
    margin-top:14px;
}

.report-mini-table th,
.report-mini-table td{
    padding:10px 10px;
    border-bottom:1px solid rgba(255,255,255,.08);
    font-size:13px;
    text-align:left;
    color:var(--text);
}

.report-mini-table th{
    color:var(--muted);
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.03em;
}

.report-mini-table td:last-child,
.report-mini-table th:last-child{
    text-align:right;
}

.report-operators-list{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:14px;
}

.report-operator-pill{
    display:inline-flex;
    align-items:center;
    padding:8px 11px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.04);
    font-size:12px;
    font-weight:700;
    color:var(--text);
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
        grid-template-columns:1fr 1fr;
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
    .report-share-group .report-btn{
        width:100%;
        justify-content:center;
    }

    .report-table-head{
        flex-direction:column;
        align-items:flex-start;
    }

    .report-total-grid{
        grid-template-columns:1fr;
    }
}
</style>

<div class="report-shell">

    <div class="report-grid-top">
        <div class="card report-panel">
            <h2>Filtro report destinazione</h2>
            <div class="report-subtitle">
                Seleziona la destinazione e l’intervallo date per generare il riepilogo delle ore nette e dei turni registrati.
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
                            <label for="destinazioneId">Destinazione</label>
                            <select id="destinazioneId" name="destinazione_id" class="report-select">
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

                <div class="report-actions">
                    <button type="submit" class="report-btn report-btn-primary">Genera report</button>
                    <a href="<?php echo h(app_url('modules/reports/report_destination.php')); ?>" class="report-btn report-btn-ghost">Reset filtri</a>
                    <a href="<?php echo h(app_url('modules/reports/index.php')); ?>" class="report-btn report-btn-secondary">Centro report</a>
                    <?php if ($destinazioneId > 0): ?>
                        <a href="<?php echo h($csvUrl); ?>" class="report-btn report-btn-secondary">Export CSV</a>
                        <a href="<?php echo h($printUrl); ?>" class="report-btn report-btn-secondary">Stampa / PDF</a>
                    <?php endif; ?>
                </div>

                <?php if ($destinazioneId > 0): ?>
                    <div class="report-share-group">
                        <button type="button" class="report-btn report-btn-secondary" id="shareNativeBtn">Condividi</button>
                        <a href="<?php echo h($share['whatsapp_url']); ?>" target="_blank" rel="noopener" class="report-btn report-btn-secondary">WhatsApp</a>
                        <a href="<?php echo h($share['email_url']); ?>" class="report-btn report-btn-secondary">Email</a>
                        <button type="button" class="report-btn report-btn-secondary" id="copyLinkBtn" data-link="<?php echo h($share['absolute_url']); ?>">Copia link</button>
                    </div>
                <?php endif; ?>
            </form>

            <?php if ($destinazioneId <= 0): ?>
                <div class="report-warning">
                    Seleziona una destinazione per visualizzare il report.
                </div>
            <?php endif; ?>
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
                    <div class="report-summary-label">Destinazione</div>
                    <div class="report-summary-value"><?php echo h($destinazioneLabel); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Comune</div>
                    <div class="report-summary-value"><?php echo h($destComuneLabel); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Tipologia</div>
                    <div class="report-summary-value"><?php echo h($destTipologiaLabel); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Speciale</div>
                    <div class="report-summary-value"><?php echo h($destIsSpecialLabel); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Logica conteggio ore</div>
                    <div class="report-summary-value"><?php echo h($destWorkRuleLabel); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Pausa pranzo</div>
                    <div class="report-summary-value"><?php echo h($pausaLabel); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Operatori coinvolti</div>
                    <div class="report-summary-value"><?php echo count($operatoriCoinvolti); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Turni trovati</div>
                    <div class="report-summary-value"><?php echo (int)($totali['turni'] ?? 0); ?></div>
                </div>

                <div class="report-summary-row">
                    <div class="report-summary-label">Ore nette totali</div>
                    <div class="report-summary-value"><?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($destinazioneId > 0 && !empty($rows)): ?>
        <div class="card report-operator-box">
            <h3>Operatori coinvolti</h3>
            <div class="report-subtitle">
                Elenco operatori presenti sulla destinazione nel periodo selezionato.
            </div>

            <div class="report-operators-list">
                <?php foreach ($operatoriCoinvolti as $nomeOperatore): ?>
                    <span class="report-operator-pill"><?php echo h($nomeOperatore); ?></span>
                <?php endforeach; ?>
            </div>

            <table class="report-mini-table">
                <thead>
                    <tr>
                        <th>Dipendente</th>
                        <th>Ore totali (nette)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($totaliOperatori as $nomeOp => $oreOp): ?>
                        <tr>
                            <td><?php echo h($nomeOp); ?></td>
                            <td><?php echo h(number_format((float)$oreOp, 2, ',', '.')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="report-table-wrap">
            <div class="report-table-head">
                <div>
                    <h2>Dettaglio turni destinazione</h2>
                    <div class="report-subtitle">
                        Elenco completo dei turni registrati sulla destinazione selezionata nel periodo indicato e conteggiati come ore lavorate.
                    </div>
                </div>

                <div class="report-chip-wrap">
                    <span class="report-chip">Destinazione: <?php echo h($destinazioneLabel); ?></span>
                    <span class="report-chip">Logica: <?php echo h($destWorkRuleLabel); ?></span>
                    <span class="report-chip">Operatori: <?php echo count($operatoriCoinvolti); ?></span>
                    <span class="report-chip">Turni: <?php echo (int)($totali['turni'] ?? 0); ?></span>
                    <span class="report-chip">Ore nette: <?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?></span>
                </div>
            </div>

            <div class="report-table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Operatore</th>
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
                                <td colspan="7"><?php echo h(format_date_it($dataIso)); ?></td>
                            </tr>

                            <?php foreach ($dayRows as $row): ?>
                                <tr>
                                    <td><?php echo h(format_date_it((string)$row['data'])); ?></td>
                                    <td><?php echo h((string)($row['operatore'] ?? '-')); ?></td>
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
                Chiarimento sul modo in cui vengono applicati pausa pranzo e flag della destinazione.
            </div>

            <div class="report-note-badge">
                Pausa pranzo: <?php echo h($pausaLabel); ?>
            </div>

            <div class="report-note-badge">
                Regola destinazione: <?php echo h($destWorkRuleLabel); ?>
            </div>

            <div class="report-note-text">
                <?php echo h($pausaNote); ?>
            </div>

            <div class="report-note-text">
                <?php echo h($flagNote); ?>
            </div>

            <div class="report-note-text">
                <?php echo h($destWorkRuleNote); ?>
            </div>
        </div>

        <div class="card report-total-box">
            <h3>Totali finali</h3>
            <div class="report-subtitle">
                Riepilogo conclusivo del report destinazione.
            </div>

            <div class="report-total-grid">
                <div class="report-total-card">
                    <div class="report-total-label">Operatori coinvolti</div>
                    <div class="report-total-value"><?php echo count($operatoriCoinvolti); ?></div>
                    <div class="report-total-note">Operatori presenti sulla destinazione nel periodo selezionato</div>
                </div>

                <div class="report-total-card">
                    <div class="report-total-label">Turni trovati</div>
                    <div class="report-total-value"><?php echo (int)($totali['turni'] ?? 0); ?></div>
                    <div class="report-total-note">Turni conteggiati in base ai flag della destinazione</div>
                </div>

                <div class="report-total-card">
                    <div class="report-total-label">Giorni coinvolti</div>
                    <div class="report-total-value"><?php echo (int)($totali['giorni'] ?? 0); ?></div>
                    <div class="report-total-note">Date con almeno un turno conteggiato</div>
                </div>

                <div class="report-total-card">
                    <div class="report-total-label">Ore nette totali</div>
                    <div class="report-total-value"><?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?></div>
                    <div class="report-total-note">Ore valide come lavoro, già al netto della pausa quando applicabile</div>
                </div>
            </div>
        </div>
    <?php elseif ($destinazioneId > 0): ?>
        <div class="card report-empty">
            <h3>Nessun risultato</h3>
            <p>
                Non sono stati trovati turni conteggiabili come lavoro per la destinazione selezionata nel periodo indicato.
            </p>
        </div>
    <?php endif; ?>

</div>

<script>
(function () {
    const shareNativeBtn = document.getElementById('shareNativeBtn');
    const copyLinkBtn = document.getElementById('copyLinkBtn');

    const shareData = {
        title: <?php echo json_encode($share['title'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        text: <?php echo json_encode($share['text'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        url: <?php echo json_encode($share['absolute_url'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    };

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
})();
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>