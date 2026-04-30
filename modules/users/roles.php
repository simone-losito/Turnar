<?php
// modules/users/roles.php
// Gestione permessi ruoli - accesso SOLO Master

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('users.permissions');

// 🔒 SOLO MASTER
if (!function_exists('is_master') || !is_master()) {
    http_response_code(403);
    exit('Accesso negato: solo un utente Master può gestire i permessi ruoli.');
}

$pageTitle    = 'Permessi ruoli';
$pageSubtitle = 'Gestione dei permessi base per ruolo';
$activeModule = 'users';

$db = db_connect();
$errors = [];
$saved = false;

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function roles_safe_redirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    exit;
}

function permission_action_label_role_page(string $code): string
{
    $parts = explode('.', $code, 2);
    $action = $parts[1] ?? $code;

    return match ($action) {
        'view'         => 'Visualizzazione',
        'create'       => 'Creazione',
        'edit'         => 'Modifica',
        'delete'       => 'Eliminazione',
        'manage'       => 'Gestione',
        'export'       => 'Export',
        'permissions'  => 'Permessi',
        default        => ucfirst(str_replace('_', ' ', $action)),
    };
}

function permission_group_label_role_page(string $group): string
{
    return match ($group) {
        'dashboard'      => 'Dashboard',
        'operators'      => 'Personale',
        'destinations'   => 'Destinazioni',
        'assignments'    => 'Turni',
        'calendar'       => 'Calendario',
        'reports'        => 'Report',
        'settings'       => 'Impostazioni',
        'users'          => 'Utenti',
        'communications' => 'Comunicazioni',
        'mobile'         => 'Mobile',
        default          => ucfirst(str_replace('_', ' ', $group)),
    };
}

// --------------------------------------------------
// PERMESSI DISPONIBILI
// --------------------------------------------------
$permissions = [];
$permissionGroups = [];

$resPerm = $db->query("SELECT id, code FROM permissions ORDER BY code ASC");
if ($resPerm) {
    while ($row = $resPerm->fetch_assoc()) {
        $permissions[] = $row;
    }
}

foreach ($permissions as $perm) {
    $code = (string)($perm['code'] ?? '');
    $parts = explode('.', $code, 2);
    $group = $parts[0] ?? 'extra';
    $permissionGroups[$group][] = $perm;
}

ksort($permissionGroups);

// --------------------------------------------------
// RUOLI
// --------------------------------------------------
$roles = [
    ROLE_USER,
    ROLE_MANAGER,
    ROLE_MASTER,
];

// --------------------------------------------------
// DEFAULT STATI
// --------------------------------------------------
$rolePermissionStates = [];
foreach ($roles as $role) {
    foreach ($permissions as $perm) {
        $rolePermissionStates[$role][(int)$perm['id']] = 0;
    }
}

// --------------------------------------------------
// CARICAMENTO ATTUALE DA DB
// --------------------------------------------------
$resRolePerm = $db->query("SELECT role, permission_id, is_allowed FROM role_permissions");
if ($resRolePerm) {
    while ($row = $resRolePerm->fetch_assoc()) {
        $role = normalize_role((string)($row['role'] ?? ROLE_USER));
        $permId = (int)($row['permission_id'] ?? 0);
        $allowed = !empty($row['is_allowed']) ? 1 : 0;

        if (isset($rolePermissionStates[$role][$permId])) {
            $rolePermissionStates[$role][$permId] = $allowed;
        }
    }
}

// --------------------------------------------------
// SALVATAGGIO
// --------------------------------------------------
if (is_post()) {
    foreach ($roles as $role) {
        foreach ($permissions as $perm) {
            $permId = (int)$perm['id'];
            $field = 'perm_' . $role . '_' . $permId;
            $rolePermissionStates[$role][$permId] = isset($_POST[$field]) ? 1 : 0;
        }
    }

    try {
        $db->begin_transaction();

        $del = $db->prepare("DELETE FROM role_permissions");
        if (!$del) {
            throw new RuntimeException('Errore pulizia permessi ruoli.');
        }
        $del->execute();
        $del->close();

        $ins = $db->prepare("\n            INSERT INTO role_permissions (role, permission_id, is_allowed)\n            VALUES (?, ?, ?)\n        ");
        if (!$ins) {
            throw new RuntimeException('Errore preparazione salvataggio permessi ruoli.');
        }

        foreach ($roles as $role) {
            foreach ($permissions as $perm) {
                $permId = (int)$perm['id'];
                $allowed = !empty($rolePermissionStates[$role][$permId]) ? 1 : 0;

                $ins->bind_param('sii', $role, $permId, $allowed);
                $ins->execute();
            }
        }

        $ins->close();

        $db->commit();
        roles_safe_redirect(app_url('modules/users/roles.php?saved=1'));
    } catch (Throwable $e) {
        $db->rollback();
        $errors[] = $e->getMessage();
    }
}

$saved = (int)get('saved', 0) === 1;

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<div class="content-card" style="max-width:1100px;">

    <?php if ($saved): ?>
        <div class="alert-success">Permessi ruoli salvati correttamente.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $error): ?>
                <div>• <?php echo h($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="info-box" style="margin-bottom:14px;">
        Configura i permessi base dei ruoli. Il ruolo Master ha sempre accesso totale.
    </div>

    <form method="post">
        <?php foreach ($roles as $role): ?>
            <div class="entity-card" style="margin-bottom:16px;">
                <h3><?php echo h(role_label($role)); ?></h3>

                <?php foreach ($permissionGroups as $group => $groupPermissions): ?>
                    <div style="margin-top:10px;">
                        <strong><?php echo h(permission_group_label_role_page($group)); ?></strong>

                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">
                            <?php foreach ($groupPermissions as $perm): ?>
                                <?php $permId = (int)$perm['id']; ?>
                                <label class="mini-pill">
                                    <input type="checkbox" name="perm_<?php echo h($role . '_' . $permId); ?>" value="1"
                                        <?php echo !empty($rolePermissionStates[$role][$permId]) ? 'checked' : ''; ?>>
                                    <?php echo h(permission_action_label_role_page($perm['code'])); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <button class="btn btn-primary">Salva permessi</button>
        <a href="<?php echo h(app_url('modules/users/index.php')); ?>" class="btn btn-ghost">Torna</a>
    </form>

</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
