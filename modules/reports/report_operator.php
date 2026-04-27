<?php
// modules/reports/report_operator.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../turni/TurniRepository.php';
require_once __DIR__ . '/ReportRepository.php';
require_once __DIR__ . '/report_share_helper.php';

require_login();
require_permission('reports.view');

$pageTitle    = 'Report operatore';
$pageSubtitle = 'Analisi turni e ore nette per singolo operatore';
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
function formatPausaPranzoOperatore($value): string
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

function reportOperatoreBadgeClass(array $row): string
{
    $countsAsWork = isset($row['counts_as_work']) ? (int)$row['counts_as_work'] : 0;
    $countsAsAbsence = isset($row['counts_as_absence']) ? (int)$row['counts_as_absence'] : 0;
    $isSpecial = isset($row['is_special']) ? (int)$row['is_special'] : 0;

    if ($countsAsAbsence === 1) {
        return 'report-destination-absence';
    }

    if ($countsAsWork === 1) {
        return 'report-destination-work';
    }

    if ($isSpecial === 1) {
        return 'report-destination-special';
    }

    return 'report-destination-normal';
}

function reportOperatoreBadgeLabel(array $row): string
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
// DATI BASE FILTRI
// --------------------------------------------------
$operatori = $turniRepo->getOperatori();

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
// MAPPA OPERATORI
// --------------------------------------------------
$operatoriMap = [];
foreach ($operatori as $op) {
    $operatoriMap[(int)($op['id'] ?? 0)] = $op;
}

// --------------------------------------------------
// LABEL FILTRO
// --------------------------------------------------
$operatoreLabel = 'Nessun operatore selezionato';
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
// PAUSE USATE NEL REPORT
// --------------------------------------------------
$pauseUsate = [];
foreach ($rows as $row) {
    $pauseUsate[] = formatPausaPranzoOperatore($row['pausa_pranzo'] ?? 0);
}
$pauseUsate = array_values(array_unique($pauseUsate));

$pausaLabel = 'Nessun dato';
if (!empty($pauseUsate)) {
    if (count($pauseUsate) === 1) {
        $pausaLabel = $pauseUsate[0];
    } else {
        $pausaLabel = 'Variabile in base al cantiere del turno';
    }
}

$pausaNote = 'Le ore nette di questo report sono calcolate usando la pausa pranzo impostata nel cantiere associato a ciascun turno. La pausa viene scalata solo quando il turno supera la soglia minima necessaria per ottenere 8 ore effettive di lavoro più la pausa prevista. Inoltre il conteggio finale dipende dai flag della destinazione: counts_as_work include le ore nel monte ore lavorato, counts_as_absence le marca come assenza, mentre le destinazioni speciali senza nessuno dei due flag restano neutre.';

// --------------------------------------------------
// URL EXPORT / STAMPA
// --------------------------------------------------
$queryArgs = [
    'data_da'      => $dataDa,
    'data_a'       => $dataA,
    'operatore_id' => $operatoreId,
];

$csvUrl   = app_url('modules/reports/export_operator_csv.php') . '?' . http_build_query($queryArgs);
$printUrl = app_url('modules/reports/report_operator_print.php') . '?' . http_build_query($queryArgs);

// --------------------------------------------------
// SHARE
// --------------------------------------------------
$shareTitle = 'Report operatore Turnar';
$shareText  = 'Report operatore: ' . $operatoreLabel
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

.report-destination-work{
    background:rgba(34,197,94,.14);
    border-color:rgba(34,197,94,.24);
    color:#dcfce7;
}

.report-destination-absence{
    background:rgba(239,68,68,.14);
    border-color:rgba(239,68,68,.24);
    color:#ffe4e6;
}

.report-destination-special{
    background:rgba(139,92,246,.14);
    border-color:rgba(139,92,246,.24);
    color:#eadcff;
}

.report-destination-normal{
    background:rgba(255,255,255,.04);
    border-color:rgba(255,255,255,.10);
    color:var(--text);
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
    .report-share-group .report-btn{
        width:100%;
        justify-content:center;
    }

    .report-table-head{
        flex-direction:column;
        align-items:flex-start;
    }
}
</style>

<div class="report-shell">

    <div class="report-grid-top">
        <div class="card report-panel">
            <h2>Filtro report operatore</h2>
            <div class="report-subtitle">
                Seleziona l’operatore e l’intervallo date per generare il riepilogo delle ore nette e dei turni.
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
                                <option value="0">Seleziona operatore</option>
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
                </div>

                <div class="report-actions">
                    <button type="submit" class="report-btn report-btn-primary">Genera report</button>
                    <a href="<?php echo h(app_url('modules/reports/report_operator.php')); ?>" class="report-btn report-btn-ghost">Reset filtri</a>
                    <a href="<?php echo h(app_url('modules/reports/index.php')); ?>" class="report-btn report-btn-secondary">Centro report</a>
                    <?php if ($operatoreId > 0): ?>
                        <a href="<?php echo h($csvUrl); ?>" class="report-btn report-btn-secondary">Export CSV</a>
                        <a href="<?php echo h($printUrl); ?>" class="report-btn report-btn-secondary">Stampa / PDF</a>
                    <?php endif; ?>
                </div>

                <?php if ($operatoreId > 0): ?>
                    <div class="report-share-group">
                        <button type="button" class="report-btn report-btn-secondary" id="shareNativeBtn">Condividi</button>
                        <a href="<?php echo h($share['whatsapp_url']); ?>" target="_blank" rel="noopener" class="report-btn report-btn-secondary">WhatsApp</a>
                        <a href="<?php echo h($share['email_url']); ?>" class="report-btn report-btn-secondary">Email</a>
                        <button type="button" class="report-btn report-btn-secondary" id="copyLinkBtn" data-link="<?php echo h($share['absolute_url']); ?>">Copia link</button>
                    </div>
                <?php endif; ?>
            </form>

            <?php if ($operatoreId <= 0): ?>
                <div class="report-warning">
                    Seleziona un operatore per visualizzare il report.
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
                    <div class="report-summary-label">Operatore</div>
                    <div class="report-summary-value"><?php echo h($operatoreLabel); ?></div>
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

    <?php if ($operatoreId > 0 && !empty($rows)): ?>
        <div class="report-table-wrap">
            <div class="report-table-head">
                <div>
                    <h2>Dettaglio turni operatore</h2>
                    <div class="report-subtitle">
                        Elenco completo dei turni trovati per l’operatore selezionato nel periodo indicato.
                    </div>
                </div>

                <div class="report-chip-wrap">
                    <span class="report-chip">Operatore: <?php echo h($operatoreLabel); ?></span>
                    <span class="report-chip">Turni: <?php echo (int)($totali['turni'] ?? 0); ?></span>
                    <span class="report-chip">Giorni: <?php echo (int)($totali['giorni'] ?? 0); ?></span>
                    <span class="report-chip">Ore nette: <?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?></span>
                </div>
            </div>

            <div class="report-table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Data</th>
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
                                <td colspan="8"><?php echo h(format_date_it($dataIso)); ?></td>
                            </tr>

                            <?php foreach ($dayRows as $row): ?>
                                <?php
                                $destName = trim((string)($row['destinazione'] ?? ''));
                                $badgeClass = reportOperatoreBadgeClass($row);
                                $badgeLabel = reportOperatoreBadgeLabel($row);
                                ?>
                                <tr>
                                    <td><?php echo h(format_date_it((string)$row['data'])); ?></td>
                                    <td>
                                        <span class="report-pill <?php echo h($badgeClass); ?>">
                                            <?php echo h($destName !== '' ? $destName : '-'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo h($badgeLabel); ?></td>
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
                Chiarimento sul modo in cui viene applicata la pausa pranzo nei conteggi dell’operatore.
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
                Riepilogo conclusivo del report operatore.
            </div>

            <div class="report-total-grid">
                <div class="report-total-card">
                    <div class="report-total-label">Turni trovati</div>
                    <div class="report-total-value"><?php echo (int)($totali['turni'] ?? 0); ?></div>
                    <div class="report-total-note">Turni associati all’operatore nel periodo selezionato</div>
                </div>

                <div class="report-total-card">
                    <div class="report-total-label">Giorni coinvolti</div>
                    <div class="report-total-value"><?php echo (int)($totali['giorni'] ?? 0); ?></div>
                    <div class="report-total-note">Date con almeno un turno presente</div>
                </div>

                <div class="report-total-card">
                    <div class="report-total-label">Ore nette totali</div>
                    <div class="report-total-value"><?php echo h(number_format((float)($totali['ore_totali'] ?? 0), 2, ',', '.')); ?></div>
                    <div class="report-total-note">Ore finali dopo logica pausa pranzo e flag destinazione</div>
                </div>
            </div>
        </div>
    <?php elseif ($operatoreId > 0): ?>
        <div class="card report-empty">
            <h3>Nessun risultato</h3>
            <p>
                Non sono stati trovati turni per l’operatore selezionato nel periodo indicato.
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