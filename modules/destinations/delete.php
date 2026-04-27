<?php
// modules/destinations/delete.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('destinations.delete');

$db = db_connect();

$id = (int)get('id', 0);

if ($id <= 0) {
    redirect(app_url('modules/destinations/index.php'));
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function delete_destination_photo_file_on_delete(?string $photoPath): void
{
    $photoPath = trim((string)$photoPath);
    if ($photoPath === '') {
        return;
    }

    $prefix = 'uploads/destinations/';
    $normalized = ltrim(str_replace('\\', '/', $photoPath), '/');

    if (strpos($normalized, $prefix) !== 0) {
        return;
    }

    $fullPath = dirname(__DIR__, 2) . '/' . $normalized;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

// --------------------------------------------------
// CARICO DESTINAZIONE
// --------------------------------------------------
$stmt = $db->prepare("
    SELECT id, commessa, foto, is_special
    FROM cantieri
    WHERE id = ?
    LIMIT 1
");
if (!$stmt) {
    redirect(app_url('modules/destinations/index.php?delete_error=1'));
}

$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$destinazione = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$destinazione) {
    redirect(app_url('modules/destinations/index.php'));
}

$destinazioneNome = trim((string)($destinazione['commessa'] ?? ''));
if ($destinazioneNome === '') {
    $destinazioneNome = 'Destinazione #' . $id;
}

// --------------------------------------------------
// BLOCCO ELIMINAZIONE DESTINAZIONI SPECIALI
// --------------------------------------------------
if (!empty($destinazione['is_special'])) {
    redirect(app_url('modules/destinations/index.php?blocked_delete=1'));
}

// --------------------------------------------------
// CONTROLLO UTILIZZO NEI TURNI
// se la destinazione è già presente in eventi_turni
// non la elimino per evitare problemi storici/report
// --------------------------------------------------
$stmtUsage = $db->prepare("
    SELECT COUNT(*) AS totale
    FROM eventi_turni
    WHERE id_cantiere = ?
");
if (!$stmtUsage) {
    redirect(app_url('modules/destinations/index.php?delete_error=1'));
}

$stmtUsage->bind_param('i', $id);
$stmtUsage->execute();
$resUsage = $stmtUsage->get_result();
$usageRow = $resUsage ? $resUsage->fetch_assoc() : null;
$stmtUsage->close();

$turniAssociati = (int)($usageRow['totale'] ?? 0);

if ($turniAssociati > 0) {
    redirect(app_url('modules/destinations/index.php?blocked_in_use=1'));
}

// --------------------------------------------------
// CONFERMA ELIMINAZIONE
// --------------------------------------------------
if (!is_post()) {
    $pageTitle    = 'Elimina destinazione';
    $pageSubtitle = 'Conferma eliminazione destinazione';
    $activeModule = 'destinations';

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
        grid-template-columns:repeat(3,minmax(0,1fr));
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
                    <h1 class="delete-hero-title">Elimina destinazione</h1>
                    <p class="delete-hero-sub">
                        Conferma finale prima della rimozione definitiva della destinazione dal sistema Turnar.
                    </p>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a class="btn btn-ghost" href="<?php echo h(app_url('modules/destinations/index.php')); ?>">
                        ← Torna alle destinazioni
                    </a>
                </div>
            </div>
        </div>

        <div class="delete-wrap">
            <section class="delete-card">
                <h2>Conferma eliminazione</h2>
                <p class="delete-sub">
                    Stai per eliminare una destinazione operativa. L’azione rimuoverà anche eventuali preferiti collegati e, se presente, la foto caricata.
                </p>

                <div class="delete-info-grid">
                    <div class="delete-info-item">
                        <div class="delete-info-label">ID</div>
                        <div class="delete-info-value"><?php echo (int)$id; ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Destinazione</div>
                        <div class="delete-info-value"><?php echo h($destinazioneNome); ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Tipo</div>
                        <div class="delete-info-value">Operativa standard</div>
                    </div>
                </div>

                <div class="delete-warning">
                    <strong>Attenzione:</strong><br>
                    questa operazione è definitiva e non può essere annullata.<br>
                    La destinazione verrà eliminata solo perché:
                    <br>• non è una destinazione speciale
                    <br>• non risulta già usata nei turni
                </div>

                <form method="post">
                    <div class="delete-actions">
                        <a class="btn btn-ghost" href="<?php echo h(app_url('modules/destinations/index.php')); ?>">
                            Annulla
                        </a>

                        <button
                            type="submit"
                            class="btn btn-danger"
                            onclick="return confirm('Confermi l’eliminazione definitiva della destinazione <?php echo h(addslashes($destinazioneNome)); ?>?');"
                        >
                            Elimina definitivamente
                        </button>
                    </div>
                </form>

                <div class="delete-helper">
                    Questo file ora è allineato al nuovo stile Turnar. La logica backend originale di protezione resta invariata.
                </div>
            </section>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/../../templates/layout_bottom.php';
    exit;
}

// --------------------------------------------------
// ELIMINO EVENTUALI PREFERITI UTENTE COLLEGATI
// compatibile con cantiere_id oppure destination_id
// --------------------------------------------------
$favoriteColumn = null;
$resCols = $db->query("SHOW COLUMNS FROM `user_favorite_destinations`");
if ($resCols) {
    $columns = [];
    while ($col = $resCols->fetch_assoc()) {
        $columns[] = (string)($col['Field'] ?? '');
    }
    $resCols->free();

    if (in_array('cantiere_id', $columns, true)) {
        $favoriteColumn = 'cantiere_id';
    } elseif (in_array('destination_id', $columns, true)) {
        $favoriteColumn = 'destination_id';
    }
}

try {
    $db->begin_transaction();

    if ($favoriteColumn !== null) {
        $stmtFavDelete = $db->prepare("
            DELETE FROM `user_favorite_destinations`
            WHERE `{$favoriteColumn}` = ?
        ");

        if (!$stmtFavDelete) {
            throw new RuntimeException('Errore eliminazione preferiti collegati.');
        }

        $stmtFavDelete->bind_param('i', $id);
        $stmtFavDelete->execute();
        $stmtFavDelete->close();
    }

    $stmtDelete = $db->prepare("
        DELETE FROM cantieri
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmtDelete) {
        throw new RuntimeException('Errore eliminazione destinazione.');
    }

    $stmtDelete->bind_param('i', $id);
    $stmtDelete->execute();

    if ((int)$stmtDelete->affected_rows < 1) {
        $stmtDelete->close();
        throw new RuntimeException('Nessuna destinazione eliminata.');
    }

    $stmtDelete->close();

    $db->commit();

    delete_destination_photo_file_on_delete((string)($destinazione['foto'] ?? ''));

    redirect(app_url('modules/destinations/index.php?deleted=1'));
} catch (Throwable $e) {
    $db->rollback();
    redirect(app_url('modules/destinations/index.php?delete_error=1'));
}