<?php
// modules/reports/index.php

require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../turni/TurniRepository.php';

require_login();
require_permission('reports.view');

$pageTitle    = 'Report';
$pageSubtitle = 'Centro report di Turnar';
$activeModule = 'reports';

$db   = db_connect();
$repo = new TurniRepository($db);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// --------------------------------------------------
// DATI BASE
// --------------------------------------------------
$operatori    = $repo->getOperatori();
$destinazioni = $repo->getDestinazioni();

$totOperatori    = count($operatori);
$totDestinazioni = count($destinazioni);
$totSpeciali     = 0;

foreach ($destinazioni as $dest) {
    $isSpecialFlag = array_key_exists('is_special', $dest) ? (int)$dest['is_special'] : null;
    $commessa = mb_strtolower(trim((string)($dest['commessa'] ?? '')), 'UTF-8');

    if ($isSpecialFlag === 1) {
        $totSpeciali++;
        continue;
    }

    // fallback compatibilità dati vecchi
    if ($isSpecialFlag === null && in_array($commessa, ['ferie', 'permessi', 'corsi di formazione', 'malattia'], true)) {
        $totSpeciali++;
    }
}

$inizioMeseIso = date('Y-m-01');
$fineMeseIso   = date('Y-m-t');

$reportPeriodoUrl = app_url(
    'modules/reports/report_period.php?data_da=' . urlencode($inizioMeseIso) . '&data_a=' . urlencode($fineMeseIso)
);

$reportOperatoreUrl = app_url(
    'modules/reports/report_operator.php?data_da=' . urlencode($inizioMeseIso) . '&data_a=' . urlencode($fineMeseIso)
);

$reportDestinazioneUrl = app_url(
    'modules/reports/report_destination.php?data_da=' . urlencode($inizioMeseIso) . '&data_a=' . urlencode($fineMeseIso)
);

$reportGanttDestinazioneUrl = app_url(
    'modules/reports/report_gantt_destination.php?data_da=' . urlencode($inizioMeseIso) . '&data_a=' . urlencode($fineMeseIso)
);

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.reports-home{
    display:grid;
    gap:18px;
}

.reports-hero{
    display:grid;
    grid-template-columns:1.15fr .85fr;
    gap:16px;
}

.reports-panel,
.report-card,
.reports-kpi-card,
.reports-footer-card{
    background:var(--content-card-bg);
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:var(--shadow);
}

.reports-panel,
.report-card,
.reports-footer-card{
    padding:20px;
}

.reports-panel h2,
.report-card h3,
.reports-footer-card h3{
    margin:0;
    color:var(--text);
}

.reports-subtitle{
    margin-top:6px;
    color:var(--muted);
    font-size:13px;
    line-height:1.6;
}

.reports-hero-box{
    display:grid;
    gap:14px;
    margin-top:18px;
}

.reports-hero-item{
    display:flex;
    gap:12px;
    align-items:flex-start;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
}

.reports-hero-icon{
    width:44px;
    height:44px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex:0 0 auto;
    font-size:18px;
    font-weight:800;
    color:#fff;
    background:linear-gradient(135deg, rgba(110,168,255,.88), rgba(139,92,246,.88));
}

.reports-hero-text strong{
    display:block;
    font-size:14px;
    margin-bottom:4px;
    color:var(--text);
}

.reports-hero-text span{
    color:var(--muted);
    font-size:13px;
    line-height:1.55;
}

.reports-kpi-grid{
    display:grid;
    grid-template-columns:1fr;
    gap:14px;
}

.reports-kpi-card{
    padding:18px;
}

.reports-kpi-label{
    color:var(--muted);
    font-size:12px;
    margin-bottom:8px;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.reports-kpi-value{
    font-size:30px;
    font-weight:800;
    line-height:1;
    color:var(--text);
}

.reports-kpi-note{
    margin-top:8px;
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
}

.reports-cards{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:16px;
}

.report-card{
    display:flex;
    flex-direction:column;
    gap:14px;
    min-height:290px;
}

.report-card-badge{
    display:inline-flex;
    align-items:center;
    align-self:flex-start;
    padding:7px 11px;
    border-radius:999px;
    border:1px solid rgba(110,168,255,.30);
    background:rgba(110,168,255,.10);
    color:#dbeafe;
    font-size:12px;
    font-weight:700;
}

.report-card-badge.gantt{
    border-color:rgba(139,92,246,.30);
    background:rgba(139,92,246,.12);
    color:#eadcff;
}

.report-card-title{
    font-size:21px;
    font-weight:800;
    line-height:1.2;
    color:var(--text);
}

.report-card-text{
    color:var(--muted);
    font-size:13px;
    line-height:1.65;
}

.report-card-list{
    display:grid;
    gap:8px;
    color:var(--text);
    font-size:13px;
}

.report-card-list div{
    padding:10px 12px;
    border-radius:14px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 86%, transparent);
    line-height:1.5;
}

.report-card-footer{
    margin-top:auto;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.reports-footer-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:16px;
}

.reports-footer-list{
    display:grid;
    gap:10px;
    margin-top:14px;
}

.reports-footer-item{
    padding:11px 12px;
    border-radius:14px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 86%, transparent);
    color:var(--text);
    font-size:13px;
    line-height:1.55;
}

.reports-footer-item strong{
    display:block;
    margin-bottom:3px;
    color:var(--text);
}

.reports-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:auto;
}

@media (max-width: 1300px){
    .reports-cards{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 1100px){
    .reports-hero,
    .reports-footer-grid{
        grid-template-columns:1fr;
    }

    .reports-kpi-grid{
        grid-template-columns:repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 760px){
    .reports-kpi-grid,
    .reports-cards{
        grid-template-columns:1fr;
    }
}
</style>

<div class="reports-home">

    <div class="reports-hero">
        <div class="card reports-panel">
            <h2>Centro report</h2>
            <div class="reports-subtitle">
                Da qui accedi ai report operativi del gestionale.
                La home resta leggera e ordinata, mentre filtri, tabelle, stampa ed export si usano dentro ogni report dedicato.
            </div>

            <div class="reports-hero-box">
                <div class="reports-hero-item">
                    <div class="reports-hero-icon">⏱</div>
                    <div class="reports-hero-text">
                        <strong>Ore nette corrette</strong>
                        <span>
                            I report usano la pausa pranzo del cantiere (<strong>cantieri.pausa_pranzo</strong>) con la regola giusta:
                            la pausa viene scalata solo quando il turno supera la soglia necessaria per ottenere 8 ore effettive più la pausa prevista.
                        </span>
                    </div>
                </div>

                <div class="reports-hero-item">
                    <div class="reports-hero-icon">📄</div>
                    <div class="reports-hero-text">
                        <strong>Report separati e chiari</strong>
                        <span>
                            Ogni report vive in una pagina dedicata: periodo, operatore, destinazione e gantt destinazione.
                            In questo modo evitiamo duplicazioni, schermate troppo pesanti e filtri ripetuti.
                        </span>
                    </div>
                </div>

                <div class="reports-hero-item">
                    <div class="reports-hero-icon">🖨</div>
                    <div class="reports-hero-text">
                        <strong>Export e stampa</strong>
                        <span>
                            I report possono essere esportati in CSV e stampati in formato PDF tramite la vista stampabile,
                            così ottieni un risultato più ordinato e professionale.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="reports-kpi-grid">
            <div class="card reports-kpi-card">
                <div class="reports-kpi-label">Operatori</div>
                <div class="reports-kpi-value"><?php echo (int)$totOperatori; ?></div>
                <div class="reports-kpi-note">Anagrafica letta dalla tabella dipendenti.</div>
            </div>

            <div class="card reports-kpi-card">
                <div class="reports-kpi-label">Destinazioni</div>
                <div class="reports-kpi-value"><?php echo (int)$totDestinazioni; ?></div>
                <div class="reports-kpi-note">Archivio destinazioni letto dalla tabella cantieri.</div>
            </div>

            <div class="card reports-kpi-card">
                <div class="reports-kpi-label">Destinazioni speciali</div>
                <div class="reports-kpi-value"><?php echo (int)$totSpeciali; ?></div>
                <div class="reports-kpi-note">Conteggiate dal flag is_special oppure dai nomi speciali storici.</div>
            </div>
        </div>
    </div>

    <div class="reports-cards">

        <div class="card report-card">
            <span class="report-card-badge">Disponibile</span>

            <div class="report-card-title">Report periodo</div>

            <div class="report-card-text">
                Report generale per intervallo date con filtro per operatore, destinazione singola e destinazioni multiple.
                È il punto giusto per avere subito una vista completa delle ore e dei turni.
            </div>

            <div class="report-card-list">
                <div>Vista a schermo con dettaglio turni e raggruppamento per data</div>
                <div>Totali ore nette, giorni coinvolti e numero turni</div>
                <div>Export CSV e vista stampabile per PDF</div>
            </div>

            <div class="reports-actions">
                <a href="<?php echo h($reportPeriodoUrl); ?>" class="btn btn-primary">Apri report periodo</a>
            </div>
        </div>

        <div class="card report-card">
            <span class="report-card-badge">Disponibile</span>

            <div class="report-card-title">Report operatore</div>

            <div class="report-card-text">
                Report dedicato al singolo operatore, utile per vedere giornate lavorate, destinazioni servite
                e totale ore nette nel periodo selezionato.
            </div>

            <div class="report-card-list">
                <div>Filtro per singolo operatore</div>
                <div>Dettaglio giornaliero ordinato e riepilogo dedicato</div>
                <div>Export CSV e vista stampabile per PDF</div>
            </div>

            <div class="reports-actions">
                <a href="<?php echo h($reportOperatoreUrl); ?>" class="btn btn-primary">Apri report operatore</a>
            </div>
        </div>

        <div class="card report-card">
            <span class="report-card-badge">Disponibile</span>

            <div class="report-card-title">Report destinazione</div>

            <div class="report-card-text">
                Report della singola destinazione/cantiere per vedere operatori coinvolti, turni registrati,
                ore nette e riepilogo finale nel periodo scelto.
            </div>

            <div class="report-card-list">
                <div>Filtro per destinazione/cantiere</div>
                <div>Elenco operatori coinvolti e dettaglio operativo</div>
                <div>Totali finali, export CSV e stampa/PDF</div>
            </div>

            <div class="reports-actions">
                <a href="<?php echo h($reportDestinazioneUrl); ?>" class="btn btn-primary">Apri report destinazione</a>
            </div>
        </div>

        <div class="card report-card">
            <span class="report-card-badge gantt">Nuovo</span>

            <div class="report-card-title">Report Gantt destinazione</div>

            <div class="report-card-text">
                Vista grafica oraria su 24 ore per una singola destinazione nel periodo selezionato.
                Serve per vedere a colpo d’occhio turni, sovrapposizioni e distribuzione giornaliera degli operatori.
            </div>

            <div class="report-card-list">
                <div>Timeline giornaliera stile gantt su 24 ore</div>
                <div>Evidenza immediata dei conflitti e delle sovrapposizioni</div>
                <div>Export CSV dedicato e stampa/PDF della vista grafica</div>
            </div>

            <div class="reports-actions">
                <a href="<?php echo h($reportGanttDestinazioneUrl); ?>" class="btn btn-primary">Apri report gantt</a>
            </div>
        </div>

    </div>

    <div class="reports-footer-grid">
        <div class="card reports-footer-card">
            <h3>Stato attuale del modulo</h3>
            <div class="reports-subtitle">
                Situazione reale del lavoro già completato nella sezione report.
            </div>

            <div class="reports-footer-list">
                <div class="reports-footer-item">
                    <strong>Già operativi</strong>
                    Report periodo, report operatore, report destinazione e report gantt destinazione con filtri, riepilogo, dettaglio turni e calcolo ore nette corretto.
                </div>

                <div class="reports-footer-item">
                    <strong>Calcolo ore già corretto</strong>
                    Le ore nette usano la pausa pranzo del cantiere con la regola richiesta:
                    nessuna pausa, 30 minuti oppure 1 ora, solo quando il turno supera la soglia necessaria.
                </div>

                <div class="reports-footer-item">
                    <strong>Struttura coerente</strong>
                    Il modulo è ora organizzato in report separati, ognuno con la propria logica chiara e senza duplicazioni inutili.
                </div>
            </div>
        </div>

        <div class="card reports-footer-card">
            <h3>Prossimi completamenti</h3>
            <div class="reports-subtitle">
                I prossimi sviluppi naturali per rendere il modulo report ancora più completo.
            </div>

            <div class="reports-footer-list">
                <div class="reports-footer-item">
                    <strong>Logiche flag nei report</strong>
                    Applicare in modo uniforme i flag delle destinazioni speciali e delle future regole di conteggio ore su periodo, operatore, destinazione e gantt.
                </div>

                <div class="reports-footer-item">
                    <strong>Rifinitura stampa</strong>
                    Possibile ulteriore miglioramento dell’impaginazione stampabile per un effetto ancora più “documento ufficiale”.
                </div>

                <div class="reports-footer-item">
                    <strong>Condivisione</strong>
                    In un secondo momento si potrà aggiungere invio o condivisione dei report tramite email o altri canali.
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>