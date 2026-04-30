<?php
// modules/users/delete.php
// Eliminazione utente con conferma e protezioni backend ruoli

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('users.delete');

$db = db_connect();
$id = (int)get('id', 0);

if ($id <= 0) {
    redirect('modules/users/index.php');
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// Nessuno può eliminare sé stesso
if ((int)auth_id() === $id) {
    redirect('modules/users/index.php?self_delete_blocked=1');
}

$stmt = $db->prepare("\n    SELECT\n        u.id,\n        u.username,\n        u.email,\n        u.role,\n        u.scope,\n        u.is_active,\n        u.can_login_web,\n        u.can_login_app,\n        u.is_administrative,\n        u.dipendente_id,\n        d.nome AS dip_nome,\n        d.cognome AS dip_cognome\n    FROM users u\n    LEFT JOIN dipendenti d ON d.id = u.dipendente_id\n    WHERE u.id = ?\n    LIMIT 1\n");

if (!$stmt) {
    redirect('modules/users/index.php?delete_error=1');
}

$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    redirect('modules/users/index.php');
}

$role = normalize_role((string)($user['role'] ?? ROLE_USER));
$isAdministrative = !empty($user['is_administrative']);

// Protezione backend: un Manager non può eliminare Master o utenti amministrativi
if (function_exists('is_manager') && is_manager() && ($role === ROLE_MASTER || $isAdministrative)) {
    http_response_code(403);
    exit('Accesso negato: un manager non può eliminare utenti Master o amministrativi.');
}

// Protezione extra: l'ultimo Master non può essere eliminato
if ($role === ROLE_MASTER) {
    $resMasterCount = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'master'");
    $masterCountRow = $resMasterCount ? $resMasterCount->fetch_assoc() : null;
    $masterCount = (int)($masterCountRow['total'] ?? 0);
    if ($resMasterCount instanceof mysqli_result) {
        $resMasterCount->free();
    }

    if ($masterCount <= 1) {
        redirect('modules/users/index.php?last_master_blocked=1');
    }
}

$username = trim((string)($user['username'] ?? ''));
$email = trim((string)($user['email'] ?? ''));
$scope = normalize_scope((string)($user['scope'] ?? SCOPE_SELF));
$isActive = !empty($user['is_active']);
$canLoginWeb = !empty($user['can_login_web']);
$canLoginApp = !empty($user['can_login_app']);
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

    <div class="content-card" style="max-width:920px;">
        <div class="toolbar">
            <div class="toolbar-left">
                <a class="btn btn-ghost" href="<?php echo h(app_url('modules/users/index.php')); ?>">← Torna agli utenti</a>
            </div>
            <div class="toolbar-right">
                <span class="soft-pill">Conferma eliminazione</span>
            </div>
        </div>

        <div class="alert-error" style="margin-bottom:16px;">
            <strong>Attenzione:</strong> questa operazione è definitiva e non può essere annullata.
            L'anagrafica personale collegata non verrà eliminata.
        </div>

        <div class="entity-card" style="display:grid;gap:14px;">
            <div class="entity-row" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                <div><strong>Utente</strong><br><span class="text-muted"><?php echo h($displayName); ?></span></div>
                <div><strong>Email</strong><br><span class="text-muted"><?php echo h($email !== '' ? $email : '-'); ?></span></div>
                <div><strong>Ruolo</strong><br><span class="text-muted"><?php echo h(role_label($role)); ?></span></div>
                <div><strong>Scope</strong><br><span class="text-muted"><?php echo h(scope_label($scope)); ?></span></div>
                <div><strong>Personale collegato</strong><br><span class="text-muted"><?php echo h($linkedPerson); ?></span></div>
                <div><strong>Stato</strong><br><span class="text-muted"><?php echo h($isActive ? 'Attivo' : 'Disattivo'); ?><?php echo $isAdministrative ? ' · Amministrativo' : ''; ?></span></div>
                <div><strong>Accesso Web</strong><br><span class="text-muted"><?php echo h($canLoginWeb ? 'Sì' : 'No'); ?></span></div>
                <div><strong>Accesso App</strong><br><span class="text-muted"><?php echo h($canLoginApp ? 'Sì' : 'No'); ?></span></div>
            </div>

            <form method="post" class="toolbar" style="margin:0;">
                <div class="toolbar-left">
                    <a class="btn btn-ghost" href="<?php echo h(app_url('modules/users/index.php')); ?>">Annulla</a>
                </div>
                <div class="toolbar-right">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Confermi eliminazione definitiva di <?php echo h(addslashes($displayName)); ?>?');">
                        Elimina definitivamente
                    </button>
                </div>
            </form>
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
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

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

    redirect('modules/users/index.php?deleted=1');
} catch (Throwable $e) {
    $db->rollback();
    redirect('modules/users/index.php?delete_error=1');
}
