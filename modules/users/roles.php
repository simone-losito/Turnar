<?php
// modules/users/roles.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();
require_permission('users.permissions');

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

        $ins = $db->prepare("
            INSERT INTO role_permissions (role, permission_id, is_allowed)
            VALUES (?, ?, ?)
        ");
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

<style>
.role-page-wrap{
    display:grid;
    gap:18px;
}

.role-tabs{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.role-tab-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:10px 16px;
    border-radius:999px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    color:var(--text);
    cursor:pointer;
    font-weight:700;
    font-size:13px;
    transition:transform .18s ease, border-color .18s ease, background .18s ease;
}

.role-tab-btn:hover{
    transform:translateY(-1px);
    border-color:color-mix(in srgb, var(--primary) 35%, transparent);
}

.role-tab-btn.active{
    background:linear-gradient(135deg, var(--primary), var(--primary-2));
    border-color:color-mix(in srgb, var(--primary) 60%, transparent);
    color:#fff;
    box-shadow:0 12px 26px rgba(0,0,0,.16);
}

.role-panel{
    display:none;
    gap:16px;
}

.role-panel.active{
    display:grid;
}

.permission-group{
    border:1px solid var(--line);
    border-radius:20px;
    padding:16px;
    background:color-mix(in srgb, var(--bg-3) 82%, transparent);
    box-shadow:var(--shadow);
}

.permission-group-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:12px;
}

.permission-group-title{
    font-size:15px;
    font-weight:800;
    color:var(--text);
}

.permission-grid{
    display:grid;
    gap:10px;
}

.permission-row{
    display:grid;
    grid-template-columns:minmax(220px, 1fr) auto;
    gap:12px;
    align-items:center;
    padding:10px 12px;
    border-radius:14px;
    background:color-mix(in srgb, var(--bg-3) 78%, transparent);
    border:1px solid color-mix(in srgb, var(--line) 82%, transparent);
}

.permission-main{
    min-width:0;
}

.permission-title{
    font-size:13px;
    font-weight:700;
    color:var(--text);
}

.permission-help{
    margin-top:4px;
    color:var(--muted);
    font-size:11px;
    word-break:break-word;
}

.permission-check{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:999px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    font-size:12px;
    font-weight:700;
    color:var(--text);
}

.permission-check input[type="checkbox"]{
    width:auto;
}

.actions-row{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

@media (max-width: 900px){
    .permission-row{
        grid-template-columns:1fr;
    }
}
</style>

<?php if ($saved): ?>
    <div class="alert-success">Permessi ruoli salvati correttamente.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert-error">
        <strong>Controlla questi punti:</strong>
        <div class="mt-2">
            <?php foreach ($errors as $error): ?>
                <div>• <?php echo h($error); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="role-page-wrap">
    <div class="info-box">
        Qui definisci i permessi <strong>base</strong> dei ruoli <strong>User</strong>, <strong>Manager</strong> e <strong>Master</strong>.<br>
        I permessi specifici impostati dentro il singolo utente restano comunque come override.
    </div>

    <form method="post" id="rolesForm">
        <div class="role-tabs">
            <?php foreach ($roles as $index => $role): ?>
                <button
                    type="button"
                    class="role-tab-btn <?php echo $index === 0 ? 'active' : ''; ?>"
                    data-role-tab="<?php echo h($role); ?>"
                >
                    <?php echo h(role_label($role)); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($roles as $index => $role): ?>
            <div class="role-panel <?php echo $index === 0 ? 'active' : ''; ?>" data-role-panel="<?php echo h($role); ?>">
                <div class="preset-bar">
                    <button type="button" class="preset-btn" data-role-preset="<?php echo h($role); ?>" data-preset="none">Togli tutto</button>
                    <button type="button" class="preset-btn" data-role-preset="<?php echo h($role); ?>" data-preset="readonly">Solo lettura</button>
                    <button type="button" class="preset-btn" data-role-preset="<?php echo h($role); ?>" data-preset="manager">Manager operativo</button>
                    <button type="button" class="preset-btn" data-role-preset="<?php echo h($role); ?>" data-preset="full">Accesso totale</button>
                </div>

                <?php foreach ($permissionGroups as $group => $groupPermissions): ?>
                    <div class="permission-group" data-role="<?php echo h($role); ?>" data-group="<?php echo h($group); ?>">
                        <div class="permission-group-head">
                            <div class="permission-group-title"><?php echo h(permission_group_label_role_page($group)); ?></div>

                            <div class="preset-bar">
                                <button type="button" class="preset-btn group-btn" data-group-role="<?php echo h($role); ?>" data-group="<?php echo h($group); ?>" data-group-action="check">Consenti gruppo</button>
                                <button type="button" class="preset-btn group-btn" data-group-role="<?php echo h($role); ?>" data-group="<?php echo h($group); ?>" data-group-action="uncheck">Nega gruppo</button>
                            </div>
                        </div>

                        <div class="permission-grid">
                            <?php foreach ($groupPermissions as $perm): ?>
                                <?php
                                $permId = (int)$perm['id'];
                                $code = (string)$perm['code'];
                                ?>
                                <div class="permission-row">
                                    <div class="permission-main">
                                        <div class="permission-title"><?php echo h(permission_action_label_role_page($code)); ?></div>
                                        <div class="permission-help"><?php echo h($code); ?></div>
                                    </div>

                                    <label class="permission-check">
                                        <input
                                            type="checkbox"
                                            name="<?php echo h('perm_' . $role . '_' . $permId); ?>"
                                            value="1"
                                            class="role-permission-check"
                                            data-role="<?php echo h($role); ?>"
                                            data-group="<?php echo h($group); ?>"
                                            data-permission-code="<?php echo h($code); ?>"
                                            <?php echo !empty($rolePermissionStates[$role][$permId]) ? 'checked' : ''; ?>
                                        >
                                        <span>Consentito</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="actions-row mt-4">
            <button type="submit" class="btn btn-primary">Salva permessi ruoli</button>
            <a href="<?php echo h(app_url('modules/users/index.php')); ?>" class="btn btn-ghost">Torna agli utenti</a>
        </div>
    </form>
</div>

<script>
(function () {
    const tabButtons = Array.from(document.querySelectorAll('[data-role-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-role-panel]'));
    const presetButtons = Array.from(document.querySelectorAll('[data-role-preset]'));
    const groupButtons = Array.from(document.querySelectorAll('.group-btn'));
    const checks = Array.from(document.querySelectorAll('.role-permission-check'));

    function activateRole(role) {
        tabButtons.forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.roleTab === role);
        });

        panels.forEach(function (panel) {
            panel.classList.toggle('active', panel.dataset.rolePanel === role);
        });
    }

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateRole(btn.dataset.roleTab || '');
        });
    });

    function getRoleChecks(role) {
        return checks.filter(function (check) {
            return (check.dataset.role || '') === role;
        });
    }

    function setRoleChecks(role, fn) {
        getRoleChecks(role).forEach(function (check) {
            check.checked = !!fn(check);
        });
    }

    presetButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const role = btn.dataset.rolePreset || '';
            const preset = btn.dataset.preset || '';

            if (!role || !preset) {
                return;
            }

            if (preset === 'none') {
                setRoleChecks(role, function () { return false; });
                return;
            }

            if (preset === 'full') {
                setRoleChecks(role, function () { return true; });
                return;
            }

            if (preset === 'readonly') {
                setRoleChecks(role, function (check) {
                    const code = (check.dataset.permissionCode || '').toLowerCase();
                    return code.endsWith('.view');
                });
                return;
            }

            if (preset === 'manager') {
                setRoleChecks(role, function (check) {
                    const code = (check.dataset.permissionCode || '').toLowerCase();

                    if (code.startsWith('dashboard.')) return true;
                    if (code.startsWith('operators.') && !code.endsWith('.delete')) return true;
                    if (code.startsWith('destinations.') && !code.endsWith('.delete')) return true;
                    if (code.startsWith('assignments.')) return true;
                    if (code.startsWith('calendar.')) return true;
                    if (code.startsWith('reports.')) return true;
                    if (code.startsWith('communications.')) return true;
                    if (code === 'users.view') return true;
                    if (code === 'settings.view') return true;

                    return false;
                });
            }
        });
    });

    groupButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const role = btn.dataset.groupRole || '';
            const group = btn.dataset.group || '';
            const action = btn.dataset.groupAction || 'check';

            checks.forEach(function (check) {
                if ((check.dataset.role || '') === role && (check.dataset.group || '') === group) {
                    check.checked = action === 'check';
                }
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>