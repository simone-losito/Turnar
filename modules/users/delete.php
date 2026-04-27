<?php
// modules/users/delete.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('users.delete');

$db = db_connect();
$id = (int)get('id', 0);

if ($id <= 0) {
    redirect(app_url('modules/users/index.php'));
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (auth_id() === $id) {
    redirect(app_url('modules/users/index.php?self_delete_blocked=1'));
}

$stmt = $db->prepare("
    SELECT
        u.id,
        u.username,
        u.email,
        u.role,
        u.scope,
        u.is_active,
        u.can_login_web,
        u.can_login_app,
        u.is_administrative,
        u.dipendente_id,
        d.nome AS dip_nome,
        d.cognome AS dip_cognome
    FROM users u
    LEFT JOIN dipendenti d ON d.id = u.dipendente_id
    WHERE u.id = ?
    LIMIT 1
");
if (!$stmt) {
    redirect(app_url('modules/users/index.php?delete_error=1'));
}

$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    redirect(app_url('modules/users/index.php'));
}

$username = trim((string)($user['username'] ?? ''));
$email = trim((string)($user['email'] ?? ''));
$role = trim((string)($user['role'] ?? ''));
$scope = trim((string)($user['scope'] ?? ''));
$isActive = !empty($user['is_active']);
$canLoginWeb = !empty($user['can_login_web']);
$canLoginApp = !empty($user['can_login_app']);
$isAdministrative = !empty($user['is_administrative']);
$dipendenteId = (int)($user['dipendente_id'] ?? 0);

$linkedPerson = trim((string)($user['dip_cognome'] ?? '') . ' ' . (string)($user['dip_nome'] ?? ''));
if ($linkedPerson === '') {
    $linkedPerson = 'Nessun collegamento';
}

$displayName = $username !== '' ? '@' . $username : ('Utente #' . $id);

// --------------------------------------------------
// PAGINA CONFERMA
// --------------------------------------------------
if (!is_post()) {
    $pageTitle    = 'Elimina utente';
    $pageSubtitle = 'Conferma eliminazione account utente';
    $activeModule = 'users';

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
                    <h1 class="delete-hero-title">Elimina utente</h1>
                    <p class="delete-hero-sub">
                        Conferma finale prima della rimozione definitiva dell’account utente dal sistema Turnar.
                    </p>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a class="btn btn-ghost" href="<?php echo h(app_url('modules/users/index.php')); ?>">
                        ← Torna agli utenti
                    </a>
                </div>
            </div>
        </div>

        <div class="delete-wrap">
            <section class="delete-card">
                <h2>Conferma eliminazione</h2>
                <p class="delete-sub">
                    Stai per eliminare un account utente. L’eventuale collegamento al personale non verrà eliminato, ma resterà senza account collegato.
                </p>

                <div class="delete-info-grid">
                    <div class="delete-info-item">
                        <div class="delete-info-label">ID</div>
                        <div class="delete-info-value"><?php echo (int)$id; ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Username</div>
                        <div class="delete-info-value"><?php echo h($username !== '' ? '@' . $username : '-'); ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Ruolo</div>
                        <div class="delete-info-value">
                            <?php echo function_exists('role_label') ? h(role_label($role)) : h($role !== '' ? ucfirst($role) : '-'); ?>
                        </div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Scope</div>
                        <div class="delete-info-value">
                            <?php echo function_exists('scope_label') ? h(scope_label($scope)) : h($scope !== '' ? ucfirst($scope) : '-'); ?>
                        </div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Email</div>
                        <div class="delete-info-value"><?php echo h($email !== '' ? $email : '-'); ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Personale collegato</div>
                        <div class="delete-info-value"><?php echo h($linkedPerson); ?></div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Accessi</div>
                        <div class="delete-info-value">
                            <?php
                            $accessParts = [];
                            if ($canLoginWeb) {
                                $accessParts[] = 'Web';
                            }
                            if ($canLoginApp) {
                                $accessParts[] = 'App';
                            }
                            echo h(!empty($accessParts) ? implode(' • ', $accessParts) : 'Nessuno');
                            ?>
                        </div>
                    </div>

                    <div class="delete-info-item">
                        <div class="delete-info-label">Stato</div>
                        <div class="delete-info-value">
                            <?php
                            $statusParts = [];
                            $statusParts[] = $isActive ? 'Attivo' : 'Disattivo';
                            if ($isAdministrative) {
                                $statusParts[] = 'Amministrativo';
                            }
                            echo h(implode(' • ', $statusParts));
                            ?>
                        </div>
                    </div>
                </div>

                <div class="delete-warning">
                    <strong>Attenzione:</strong><br>
                    questa operazione è definitiva e non può essere annullata.<br>
                    Verranno eliminati:
                    <br>• account utente
                    <br>• permessi specifici dell’utente
                    <br>• preferiti destinazioni dell’utente
                    <br><br>
                    <strong>Non</strong> verrà eliminata l’anagrafica personale collegata.
                </div>

                <form method="post">
                    <div class="delete-actions">
                        <a class="btn btn-ghost" href="<?php echo h(app_url('modules/users/index.php')); ?>">
                            Annulla
                        </a>

                        <button
                            type="submit"
                            class="btn btn-danger"
                            onclick="return confirm('Confermi l’eliminazione definitiva di <?php echo h(addslashes($displayName)); ?>?');"
                        >
                            Elimina definitivamente
                        </button>
                    </div>
                </form>

                <div class="delete-helper">
                    Questo file ora è allineato al nuovo stile Turnar e alla logica pulita di conferma prima della cancellazione.
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

    $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    if (!$stmt) {
        throw new RuntimeException('Errore eliminazione permessi utente.');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("DELETE FROM user_favorite_destinations WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('Errore eliminazione utente.');
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ((int)$stmt->affected_rows < 1) {
        $stmt->close();
        throw new RuntimeException('Nessun utente eliminato.');
    }

    $stmt->close();

    $db->commit();
    redirect(app_url('modules/users/index.php?deleted=1'));
} catch (Throwable $e) {
    $db->rollback();
    redirect(app_url('modules/users/index.php?delete_error=1'));
}