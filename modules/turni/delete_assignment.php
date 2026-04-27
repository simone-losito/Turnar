<?php
// modules/turni/delete_assignment.php

require_once __DIR__ . '/TurniRepository.php';

require_login();
require_module('assignments');

if (!can_manage_assignments()) {
    redirect('modules/turni/planning.php');
}

$db = db_connect();

$id   = (int)get('id', post('id', 0));
$data = normalize_date_iso((string)get('data', post('data', today_date()))) ?: today_date();

if ($id <= 0) {
    redirect(app_url('modules/turni/planning.php?data=' . urlencode($data)));
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function load_turno_for_delete(mysqli $db, int $id): ?array
{
    $sql = "
        SELECT
            e.id,
            e.data,
            e.ora_inizio,
            e.ora_fine,
            e.is_capocantiere,
            d.nome,
            d.cognome,
            c.commessa,
            c.comune,
            c.tipologia
        FROM eventi_turni e
        LEFT JOIN dipendenti d ON d.id = e.id_dipendente
        LEFT JOIN cantieri c ON c.id = e.id_cantiere
        WHERE e.id = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? $row : null;
}

$turno = load_turno_for_delete($db, $id);

if (!$turno) {
    redirect(app_url('modules/turni/planning.php?data=' . urlencode($data) . '&delete_error=1'));
}

$turnoData = normalize_date_iso((string)($turno['data'] ?? '')) ?: $data;
$planningUrl = app_url('modules/turni/planning.php?data=' . urlencode($turnoData));

$operatoreNome = trim((string)($turno['cognome'] ?? '') . ' ' . (string)($turno['nome'] ?? ''));
if ($operatoreNome === '') {
    $operatoreNome = 'Operatore non disponibile';
}

$destinazioneNome = trim((string)($turno['commessa'] ?? ''));
if ($destinazioneNome === '') {
    $destinazioneNome = 'Destinazione non disponibile';
}

$destExtraParts = [];
if (!empty($turno['comune'])) {
    $destExtraParts[] = (string)$turno['comune'];
}
if (!empty($turno['tipologia'])) {
    $destExtraParts[] = (string)$turno['tipologia'];
}
$destExtra = !empty($destExtraParts) ? implode(' • ', $destExtraParts) : '';

$oraInizio = substr((string)($turno['ora_inizio'] ?? ''), 0, 5);
$oraFine   = substr((string)($turno['ora_fine'] ?? ''), 0, 5);
$isResponsabile = !empty($turno['is_capocantiere']);

// --------------------------------------------------
// PAGINA CONFERMA
// --------------------------------------------------
if (!is_post()) {
    $pageTitle    = 'Elimina assegnazione';
    $pageSubtitle = 'Conferma eliminazione turno';
    $activeModule = 'assignments';

    require_once __DIR__ . '/../../templates/layout_top.php';
    ?>

    <style>
    .delete-page{
        display:grid;
        gap:18px;
    }

    .delete-hero{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:14px;
        flex-wrap:wrap;
    }

    .delete-hero-title{
        margin:0 0 8px;
        font-size:24px;
        font-weight:900;
        color:var(--text);
    }

    .delete-hero-sub{
        margin:0;
        color:var(--muted);
        line-height:1.6;
        font-size:14px;
    }

    .delete-wrap{
        display:grid;
        grid-template-columns:minmax(320px, 860px);
        gap:18px;
    }

    .delete-card{
        background:var(--content-card-bg);
        border:1px solid var(--line);
        border-radius:24px;
        box-shadow:var(--shadow);
        padding:20px;
    }

    .delete-card h2{
        margin:0 0 10px;
        font-size:22px;
        font-weight:900;
        color:var(--text);
    }

    .delete-sub{
        margin:0 0 18px;
        color:var(--muted);
        line-height:1.6;
        font-size:14px;
    }

    .delete-info-grid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
        margin-bottom:18px;
    }

    .delete-info-item{
        padding:14px;
        border-radius:18px;
        border:1px solid var(--line);
        background:color-mix(in srgb, var(--bg-3) 86%, transparent);
    }

    .delete-info-label{
        font-size:11px;
        color:var(--muted);
        text-transform:uppercase;
        letter-spacing:.05em;
        margin-bottom:6px;
        font-weight:700;
    }

    .delete-info-value{
        font-size:15px;
        font-weight:800;
        color:var(--text);
        word-break:break-word;
    }

    .delete-warning{
        margin-bottom:18px;
        padding:16px 18px;
        border-radius:18px;
        border:1px solid rgba(248,113,113,.24);
        background:rgba(248,113,113,.10);
        color:#b91c1c;
        line-height:1.7;
        font-size:14px;
    }

    .delete-warning strong{
        color:#991b1b;
    }

    .delete-helper{
        margin-top:18px;
        padding:14px 16px;
        border-radius:18px;
        border:1px solid rgba(251,191,36,.20);
        background:rgba(251,191,36,.08);
        color:#92400e;
        line-height:1.6;
        font-size:13px;
    }

    .delete-actions{
        display:flex;
        gap:10px;
        flex-wrap:wrap;
        align-items:center;
        margin-top:6px;
    }

    @media (max-width: 900px){
        .delete-info-grid{
            grid-template-columns:1fr;
        }
    }
    </style>

    <div class="delete-page">

        <div class="card">
            <div class="delete-hero">
                <div>
                    <h1 class="delete-hero-title">Elimina turno</h1>
                    <p class="delete-hero-sub">
                        Conferma finale prima della rimozione del turno dal planning Turnar.
                    </p>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a class="btn btn-ghost" href="<?php echo h($planningUrl); ?>">
                        ← Torna al planning
                    </a>
                </div>
            </div>
        </div>

        <div class="delete-wrap">
            <section class="delete-card">
                <h2>Conferma eliminazione turno</h2>
                <p class="delete-sub">
                    Stai per eliminare un’assegnazione esistente. L’operazione rimuoverà il turno dalla giornata selezionata.
                </p>

                <div class="delete-info-grid">
                    <div class="delete-info-item">
                        <div class="delete-info-label">ID turno</div>
                        <div class="delete-info-value"><?php echo (int)$id; ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Data</div>
                        <div class="delete-info-value"><?php echo h(format_date_it($turnoData)); ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Operatore</div>
                        <div class="delete-info-value"><?php echo h($operatoreNome); ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Orario</div>
                        <div class="delete-info-value"><?php echo h($oraInizio . ' → ' . $oraFine); ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Destinazione</div>
                        <div class="delete-info-value"><?php echo h($destinazioneNome); ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Dettagli</div>
                        <div class="delete-info-value"><?php echo h($destExtra !== '' ? $destExtra : '-'); ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Ruolo turno</div>
                        <div class="delete-info-value"><?php echo $isResponsabile ? 'Responsabile' : 'Operatore'; ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Ritorno</div>
                        <div class="delete-info-value">Planning del <?php echo h(format_date_it($turnoData)); ?></div>
                    </div>
                </div>

                <div class="delete-warning">
                    <strong>Attenzione:</strong><br>
                    questa operazione elimina definitivamente il turno selezionato dalla pianificazione del giorno.<br>
                    Dopo la conferma non sarà possibile recuperarlo automaticamente.
                </div>

                <form method="post">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="data" value="<?php echo h($turnoData); ?>">

                    <div class="delete-actions">
                        <a class="btn btn-ghost" href="<?php echo h($planningUrl); ?>">
                            Annulla
                        </a>

                        <button
                            type="submit"
                            class="btn btn-danger"
                            onclick="return confirm('Confermi l’eliminazione definitiva di questo turno?');"
                        >
                            Elimina definitivamente
                        </button>
                    </div>
                </form>

                <div class="delete-helper">
                    Questo file ora è allineato al nuovo stile Turnar e non cancella più il turno direttamente senza pagina di conferma.
                </div>
            </section>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/../../templates/layout_bottom.php';
    exit;
}

// --------------------------------------------------
// ELIMINAZIONE REALE
// --------------------------------------------------
$stmt = $db->prepare("DELETE FROM eventi_turni WHERE id = ? LIMIT 1");
if (!$stmt) {
    redirect(app_url('modules/turni/planning.php?data=' . urlencode($turnoData) . '&delete_error=1'));
}

$stmt->bind_param('i', $id);
$stmt->execute();
$deletedRows = (int)$stmt->affected_rows;
$stmt->close();

if ($deletedRows < 1) {
    redirect(app_url('modules/turni/planning.php?data=' . urlencode($turnoData) . '&delete_error=1'));
}

redirect(app_url('modules/turni/planning.php?data=' . urlencode($turnoData) . '&deleted=1'));