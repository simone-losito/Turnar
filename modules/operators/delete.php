<?php
// modules/operators/delete.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('operators.delete');

$db = db_connect();

$id = (int)get('id', 0);

if ($id <= 0) {
    redirect(app_url('modules/operators/index.php'));
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function delete_operator_photo_file_on_delete(?string $photoPath): void
{
    $photoPath = trim((string)$photoPath);
    if ($photoPath === '') {
        return;
    }

    $parsedPath = parse_url($photoPath, PHP_URL_PATH);
    $normalized = trim((string)($parsedPath ?: $photoPath));
    $normalized = str_replace('\\', '/', $normalized);
    $normalized = ltrim($normalized, '/');

    $uploadsPos = strpos($normalized, 'uploads/operators/');
    if ($uploadsPos === false) {
        return;
    }

    $relativePath = substr($normalized, $uploadsPos);
    $fullPath = dirname(__DIR__, 2) . '/' . $relativePath;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

// --------------------------------------------------
// RECUPERO RECORD
// --------------------------------------------------
$stmt = $db->prepare("
    SELECT
        d.id,
        d.nome,
        d.cognome,
        d.foto,
        u.id AS user_id
    FROM dipendenti d
    LEFT JOIN users u ON u.dipendente_id = d.id
    WHERE d.id = ?
    LIMIT 1
");
if (!$stmt) {
    redirect(app_url('modules/operators/index.php?delete_error=1'));
}

$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$operatore = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$operatore) {
    redirect(app_url('modules/operators/index.php'));
}

$userId = (int)($operatore['user_id'] ?? 0);

$operatoreNome = trim((string)($operatore['nome'] ?? '') . ' ' . (string)($operatore['cognome'] ?? ''));
if ($operatoreNome === '') {
    $operatoreNome = 'Persona #' . $id;
}

// --------------------------------------------------
// PAGINA CONFERMA
// --------------------------------------------------
if (!is_post()) {
    $pageTitle    = 'Elimina persona';
    $pageSubtitle = 'Conferma eliminazione anagrafica personale';
    $activeModule = 'operators';

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
                    <h1 class="delete-hero-title">Elimina persona</h1>
                    <p class="delete-hero-sub">
                        Conferma finale prima della rimozione definitiva dell’anagrafica personale dal sistema Turnar.
                    </p>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a class="btn btn-ghost" href="<?php echo h(app_url('modules/operators/index.php')); ?>">
                        ← Torna al personale
                    </a>
                </div>
            </div>
        </div>

        <div class="delete-wrap">
            <section class="delete-card">
                <h2>Conferma eliminazione</h2>
                <p class="delete-sub">
                    Stai per eliminare una persona del personale. Se esiste un account collegato, verrà eliminato automaticamente insieme ai suoi permessi specifici.
                </p>

                <div class="delete-info-grid">
                    <div class="delete-info-item">
                        <div class="delete-info-label">ID</div>
                        <div class="delete-info-value"><?php echo (int)$id; ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Persona</div>
                        <div class="delete-info-value"><?php echo h($operatoreNome); ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Utente collegato</div>
                        <div class="delete-info-value"><?php echo $userId > 0 ? 'Sì' : 'No'; ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Foto</div>
                        <div class="delete-info-value"><?php echo !empty($operatore['foto']) ? 'Presente' : 'Assente'; ?></div>
                    </div>
                </div>

                <div class="delete-warning">
                    <strong>Attenzione:</strong><br>
                    questa operazione è definitiva e non può essere annullata.<br>
                    Verranno eliminati:
                    <br>• anagrafica personale
                    <?php if ($userId > 0): ?>
                        <br>• account utente collegato
                        <br>• permessi specifici dell’utente collegato
                    <?php endif; ?>
                    <?php if (!empty($operatore['foto'])): ?>
                        <br>• foto caricata dell’operatore
                    <?php endif; ?>
                </div>

                <form method="post">
                    <div class="delete-actions">
                        <a class="btn btn-ghost" href="<?php echo h(app_url('modules/operators/index.php')); ?>">
                            Annulla
                        </a>

                        <button
                            type="submit"
                            class="btn btn-danger"
                            onclick="return confirm('Confermi l’eliminazione definitiva di <?php echo h(addslashes($operatoreNome)); ?>?');"
                        >
                            Elimina definitivamente
                        </button>
                    </div>
                </form>

                <div class="delete-helper">
                    Questo file ora è allineato al nuovo stile Turnar. La logica di eliminazione resta coerente con il tuo flusso attuale.
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
try {
    $db->begin_transaction();

    if ($userId > 0) {
        $stmtDelUserPerm = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        if (!$stmtDelUserPerm) {
            throw new RuntimeException('Errore eliminazione permessi utente collegato.');
        }
        $stmtDelUserPerm->bind_param("i", $userId);
        $stmtDelUserPerm->execute();
        $stmtDelUserPerm->close();

        $stmtDelUser = $db->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
        if (!$stmtDelUser) {
            throw new RuntimeException('Errore eliminazione utente collegato.');
        }
        $stmtDelUser->bind_param("i", $userId);
        $stmtDelUser->execute();
        $stmtDelUser->close();
    }

    $stmtDelete = $db->prepare("DELETE FROM dipendenti WHERE id = ? LIMIT 1");
    if (!$stmtDelete) {
        throw new RuntimeException('Errore eliminazione persona.');
    }
    $stmtDelete->bind_param("i", $id);
    $stmtDelete->execute();

    if ((int)$stmtDelete->affected_rows < 1) {
        $stmtDelete->close();
        throw new RuntimeException('Nessuna persona eliminata.');
    }

    $stmtDelete->close();

    $db->commit();

    delete_operator_photo_file_on_delete((string)($operatore['foto'] ?? ''));

    redirect(app_url('modules/operators/index.php?deleted=1'));
} catch (Throwable $e) {
    $db->rollback();
    redirect(app_url('modules/operators/index.php?delete_error=1'));
}