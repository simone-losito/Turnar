<?php
// modules/users/edit.php

require_once __DIR__ . '/../../core/helpers.php';

require_login();

$id = (int)get('id', 0);
$isEdit = $id > 0;

if ($isEdit) {
    require_permission('users.edit');
} else {
    require_permission('users.create');
}

$pageTitle    = $isEdit ? 'Gestione Utente' : 'Nuovo utente';
$pageSubtitle = 'Gestione accesso, ruolo, scope e permessi';
$activeModule = 'users';

$db = db_connect();
$errors = [];

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function users_safe_redirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    exit;
}

function users_checkbox(string $key): int
{
    return isset($_POST[$key]) ? 1 : 0;
}

function users_username_exists(mysqli $db, string $username, int $excludeId = 0): bool
{
    $username = trim($username);
    if ($username === '') {
        return false;
    }

    if ($excludeId > 0) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $username, $excludeId);
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $username);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->fetch_assoc();
    $stmt->close();

    return (bool)$exists;
}

function users_email_exists(mysqli $db, string $email, int $excludeId = 0): bool
{
    $email = trim($email);
    if ($email === '') {
        return false;
    }

    if ($excludeId > 0) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $email, $excludeId);
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $email);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->fetch_assoc();
    $stmt->close();

    return (bool)$exists;
}

function users_dipendente_link_exists(mysqli $db, int $dipendenteId, int $excludeUserId = 0): bool
{
    if ($dipendenteId <= 0) {
        return false;
    }

    if ($excludeUserId > 0) {
        $stmt = $db->prepare("SELECT id FROM users WHERE dipendente_id = ? AND id <> ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $dipendenteId, $excludeUserId);
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE dipendente_id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $dipendenteId);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->fetch_assoc();
    $stmt->close();

    return (bool)$exists;
}

function permission_action_label(string $code): string
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

function permission_group_label(string $group): string
{
    return match ($group) {
        'dashboard'      => 'Dashboard',
        'operators'      => 'Personale',
        'destinations'   => 'Destinazioni',
        'assignments'    => 'Turni',
        'calendar'       => 'Calendario',
        'reports'        => 'Report',
        'settings'       => 'Impostazioni',
        'users'          => 'Gestione Utenti',
        'communications' => 'Comunicazioni',
        'mobile'         => 'Mobile',
        default          => ucfirst(str_replace('_', ' ', $group)),
    };
}

// --------------------------------------------------
// PERSONALE
// --------------------------------------------------
$operatori = [];
$resDip = $db->query("SELECT id, nome, cognome, email, tipologia, attivo FROM dipendenti ORDER BY cognome ASC, nome ASC");
if ($resDip) {
    while ($row = $resDip->fetch_assoc()) {
        $operatori[] = $row;
    }
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
// DEFAULT FORM
// --------------------------------------------------
$form = [
    'id'                   => 0,
    'dipendente_id'        => 0,
    'username'             => '',
    'email'                => '',
    'password'             => '',
    'role'                 => ROLE_USER,
    'scope'                => SCOPE_SELF,
    'is_active'            => 1,
    'can_login_web'        => 1,
    'can_login_app'        => 1,
    'must_change_password' => 1,
    'is_administrative'    => 0,
];

$permissionStates = [];

foreach ($permissions as $perm) {
    $permissionStates[(int)$perm['id']] = 'inherit';
}

// --------------------------------------------------
// CARICAMENTO MODIFICA
// --------------------------------------------------
if ($isEdit && !is_post()) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $existing = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    } else {
        $existing = null;
    }

    if (!$existing) {
        users_safe_redirect(app_url('modules/users/index.php'));
    }

    foreach ($form as $key => $default) {
        if (array_key_exists($key, $existing)) {
            $form[$key] = $existing[$key] ?? $default;
        }
    }

    $form['id'] = (int)$existing['id'];
    $form['dipendente_id'] = (int)($existing['dipendente_id'] ?? 0);
    $form['password'] = '';

    $stmtPerm = $db->prepare("SELECT permission_id, is_allowed FROM user_permissions WHERE user_id = ?");
    if ($stmtPerm) {
        $stmtPerm->bind_param('i', $id);
        $stmtPerm->execute();
        $resPermUser = $stmtPerm->get_result();

        while ($row = $resPermUser ? $resPermUser->fetch_assoc() : null) {
            if (!$row) {
                break;
            }
            $permissionStates[(int)$row['permission_id']] = !empty($row['is_allowed']) ? 'allow' : 'deny';
        }

        $stmtPerm->close();
    }
}

// --------------------------------------------------
// SALVATAGGIO
// --------------------------------------------------
if (is_post()) {
    $form['id']                   = (int)post('id', 0);
    $form['dipendente_id']        = (int)post('dipendente_id', 0);
    $form['username']             = trim((string)post('username', ''));
    $form['email']                = trim((string)post('email', ''));
    $form['password']             = trim((string)post('password', ''));
    $form['role']                 = normalize_role((string)post('role', ROLE_USER));
    $form['scope']                = normalize_scope((string)post('scope', SCOPE_SELF));
    $form['is_active']            = users_checkbox('is_active');
    $form['can_login_web']        = users_checkbox('can_login_web');
    $form['can_login_app']        = users_checkbox('can_login_app');
    $form['must_change_password'] = users_checkbox('must_change_password');
    $form['is_administrative']    = users_checkbox('is_administrative');

    foreach ($permissions as $perm) {
        $permId = (int)$perm['id'];
        $state = trim((string)post('perm_' . $permId, 'inherit'));
        if (!in_array($state, ['inherit', 'allow', 'deny'], true)) {
            $state = 'inherit';
        }
        $permissionStates[$permId] = $state;
    }

    if ($form['username'] === '') {
        $errors[] = 'Lo username è obbligatorio.';
    }

    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida.';
    }

    if (!$isEdit && $form['password'] === '') {
        $errors[] = 'Per il nuovo utente devi indicare una password iniziale.';
    }

    if ($form['username'] !== '' && users_username_exists($db, $form['username'], $isEdit ? $id : 0)) {
        $errors[] = 'Lo username esiste già.';
    }

    if ($form['email'] !== '' && users_email_exists($db, $form['email'], $isEdit ? $id : 0)) {
        $errors[] = 'L\'email esiste già.';
    }

    if ($form['dipendente_id'] > 0 && users_dipendente_link_exists($db, $form['dipendente_id'], $isEdit ? $id : 0)) {
        $errors[] = 'Questa persona del personale è già collegata a un altro utente.';
    }

    if (!$form['can_login_web'] && !$form['can_login_app']) {
        $errors[] = 'L\'utente deve poter accedere almeno al software web o all\'app.';
    }

    if (empty($errors)) {
        try {
            $db->begin_transaction();

            if ($isEdit) {
                $stmtRead = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
                if (!$stmtRead) {
                    throw new RuntimeException('Errore lettura password attuale.');
                }
                $stmtRead->bind_param('i', $id);
                $stmtRead->execute();
                $resRead = $stmtRead->get_result();
                $rowRead = $resRead ? $resRead->fetch_assoc() : null;
                $stmtRead->close();

                if (!$rowRead) {
                    throw new RuntimeException('Utente non trovato.');
                }

                $passwordHash = (string)($rowRead['password_hash'] ?? '');
                if ($form['password'] !== '') {
                    $passwordHash = password_hash($form['password'], PASSWORD_DEFAULT);
                }

                $dipendenteId = $form['dipendente_id'] > 0 ? $form['dipendente_id'] : null;
                $email = $form['email'] !== '' ? $form['email'] : null;

                $sql = "
                    UPDATE users SET
                        dipendente_id = ?,
                        role = ?,
                        scope = ?,
                        username = ?,
                        password_hash = ?,
                        email = ?,
                        is_active = ?,
                        can_login_web = ?,
                        can_login_app = ?,
                        must_change_password = ?,
                        is_administrative = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ";
                $stmt = $db->prepare($sql);
                if (!$stmt) {
                    throw new RuntimeException('Errore preparazione aggiornamento utente.');
                }

                $stmt->bind_param(
                    'isssssiiiiii',
                    $dipendenteId,
                    $form['role'],
                    $form['scope'],
                    $form['username'],
                    $passwordHash,
                    $email,
                    $form['is_active'],
                    $form['can_login_web'],
                    $form['can_login_app'],
                    $form['must_change_password'],
                    $form['is_administrative'],
                    $id
                );

                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new RuntimeException('Errore salvataggio utente.');
                }
                $stmt->close();

                $savedUserId = $id;
            } else {
                $passwordHash = password_hash($form['password'], PASSWORD_DEFAULT);
                $dipendenteId = $form['dipendente_id'] > 0 ? $form['dipendente_id'] : null;
                $email = $form['email'] !== '' ? $form['email'] : null;

                $sql = "
                    INSERT INTO users (
                        dipendente_id,
                        role,
                        scope,
                        username,
                        password_hash,
                        email,
                        is_active,
                        can_login_web,
                        can_login_app,
                        must_change_password,
                        is_administrative,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ";
                $stmt = $db->prepare($sql);
                if (!$stmt) {
                    throw new RuntimeException('Errore preparazione creazione utente.');
                }

                $stmt->bind_param(
                    'isssssiiiii',
                    $dipendenteId,
                    $form['role'],
                    $form['scope'],
                    $form['username'],
                    $passwordHash,
                    $email,
                    $form['is_active'],
                    $form['can_login_web'],
                    $form['can_login_app'],
                    $form['must_change_password'],
                    $form['is_administrative']
                );

                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new RuntimeException('Errore creazione utente.');
                }

                $savedUserId = (int)$stmt->insert_id;
                $stmt->close();
            }

            $stmtDel = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
            if (!$stmtDel) {
                throw new RuntimeException('Errore reset permessi utente.');
            }
            $stmtDel->bind_param('i', $savedUserId);
            $stmtDel->execute();
            $stmtDel->close();

            $stmtIns = $db->prepare("
                INSERT INTO user_permissions (user_id, permission_id, is_allowed)
                VALUES (?, ?, ?)
            ");
            if (!$stmtIns) {
                throw new RuntimeException('Errore preparazione salvataggio permessi.');
            }

            foreach ($permissionStates as $permId => $state) {
                if ($state === 'inherit') {
                    continue;
                }

                $allowed = $state === 'allow' ? 1 : 0;
                $permId = (int)$permId;
                $stmtIns->bind_param('iii', $savedUserId, $permId, $allowed);
                $stmtIns->execute();
            }

            $stmtIns->close();

            $db->commit();

            users_safe_redirect(
                app_url('modules/users/edit.php?id=' . $savedUserId . '&saved=1')
            );
        } catch (Throwable $e) {
            $db->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$saved = (int)get('saved', 0) === 1;
$isSelfEdit = $isEdit && auth_id() === (int)$form['id'];

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<style>
.user-edit-grid{
    display:grid;
    grid-template-columns:minmax(320px, 420px) minmax(0, 1fr);
    gap:18px;
}

.user-side-box{
    display:flex;
    flex-direction:column;
    gap:14px;
}

.user-avatar{
    width:180px;
    height:180px;
    border-radius:24px;
    background:linear-gradient(135deg, color-mix(in srgb, var(--primary) 20%, transparent), color-mix(in srgb, var(--primary-2) 16%, transparent));
    border:1px solid var(--line);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:54px;
    font-weight:800;
    color:#fff;
    margin:0 auto;
    box-shadow:0 14px 30px rgba(0,0,0,.12);
}

.user-title{
    text-align:center;
    font-size:22px;
    font-weight:800;
    color:var(--text);
}

.user-note{
    text-align:center;
    color:var(--muted);
    font-size:12px;
    line-height:1.6;
}

.user-status-strip{
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    gap:8px;
}

.status-chip{
    display:inline-flex;
    align-items:center;
    padding:7px 11px;
    border-radius:999px;
    border:1px solid var(--line);
    font-size:11px;
    font-weight:700;
    line-height:1;
}

.status-chip.role{
    color:color-mix(in srgb, var(--primary) 72%, var(--text));
    background:color-mix(in srgb, var(--primary) 16%, transparent);
    border-color:color-mix(in srgb, var(--primary) 28%, transparent);
}

.status-chip.scope{
    color:color-mix(in srgb, var(--primary-2) 74%, var(--text));
    background:color-mix(in srgb, var(--primary-2) 16%, transparent);
    border-color:color-mix(in srgb, var(--primary-2) 28%, transparent);
}

.status-chip.ok{
    color:#059669;
    background:rgba(52,211,153,.14);
    border-color:rgba(52,211,153,.28);
}

.status-chip.no{
    color:#dc2626;
    background:rgba(248,113,113,.12);
    border-color:rgba(248,113,113,.28);
}

.user-form-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:14px;
}

.field.full{
    grid-column:1 / -1;
}

.section-title-local{
    margin:18px 0 10px;
    font-size:15px;
    font-weight:800;
    color:var(--text);
}

.check-row{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}

.check-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:999px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
}

.permissions-wrap{
    display:grid;
    gap:16px;
    margin-top:8px;
}

.permission-group{
    border:1px solid var(--line);
    border-radius:18px;
    padding:14px;
    background:color-mix(in srgb, var(--bg-3) 82%, transparent);
}

.permission-group-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:10px;
}

.permission-group-title{
    font-size:14px;
    font-weight:800;
    text-transform:capitalize;
    color:var(--text);
}

.permission-grid{
    display:grid;
    gap:10px;
}

.permission-row{
    display:grid;
    grid-template-columns:minmax(200px, 1fr) 220px;
    gap:12px;
    align-items:center;
    padding:10px 12px;
    border-radius:14px;
    background:color-mix(in srgb, var(--bg-3) 78%, transparent);
    border:1px solid color-mix(in srgb, var(--line) 82%, transparent);
}

.permission-code{
    font-size:13px;
    color:var(--text);
    word-break:break-word;
}

.permission-help{
    margin-top:4px;
    color:var(--muted);
    font-size:11px;
}

.preset-bar{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin:12px 0 14px;
}

.preset-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:10px 14px;
    border-radius:999px;
    border:1px solid var(--line);
    background:color-mix(in srgb, var(--bg-3) 88%, transparent);
    color:var(--text);
    cursor:pointer;
    font-size:12px;
    font-weight:700;
    transition:transform .18s ease, border-color .18s ease, background .18s ease;
}

.preset-btn:hover{
    transform:translateY(-1px);
    background:color-mix(in srgb, var(--bg-3) 94%, transparent);
    border-color:color-mix(in srgb, var(--primary) 35%, transparent);
}

.info-box{
    padding:14px 16px;
    border-radius:18px;
    border:1px solid color-mix(in srgb, var(--primary) 24%, transparent);
    background:color-mix(in srgb, var(--primary) 8%, transparent);
    color:var(--text);
    font-size:13px;
    line-height:1.6;
}

.actions-row{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:18px;
}

.alert-success,
.alert-info,
.alert-error{
    margin-bottom:16px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--line);
}

.alert-success{
    border-color:rgba(52,211,153,.28);
    background:rgba(52,211,153,.12);
    color:#166534;
}

.alert-info{
    border-color:color-mix(in srgb, var(--primary) 28%, transparent);
    background:color-mix(in srgb, var(--primary) 10%, transparent);
    color:var(--text);
}

.alert-error{
    border-color:rgba(248,113,113,.30);
    background:rgba(248,113,113,.12);
    color:#991b1b;
}

@media (max-width: 980px){
    .user-edit-grid{
        grid-template-columns:1fr;
    }

    .user-form-grid{
        grid-template-columns:1fr;
    }

    .permission-row{
        grid-template-columns:1fr;
    }
}
</style>

<?php if ($saved): ?>
    <div class="alert-success">Utente salvato correttamente.</div>
<?php endif; ?>

<?php if ($isSelfEdit): ?>
    <div class="alert-info">
        Stai modificando il tuo stesso utente. Fai attenzione a ruolo, accessi e permessi.
    </div>
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

<?php
$previewName = $form['username'] !== '' ? $form['username'] : 'Nuovo utente';
$initials = mb_strtoupper(mb_substr($previewName, 0, 2, 'UTF-8'), 'UTF-8');
if ($initials === '') {
    $initials = 'US';
}
?>

<div class="user-edit-grid">
    <div class="card">
        <div class="user-side-box">
            <div class="user-avatar"><?php echo h($initials); ?></div>
            <div class="user-title"><?php echo h($previewName); ?></div>

            <div class="user-status-strip">
                <span class="status-chip role"><?php echo h(role_label($form['role'])); ?></span>
                <span class="status-chip scope"><?php echo h(scope_label($form['scope'])); ?></span>
                <span class="status-chip <?php echo !empty($form['is_active']) ? 'ok' : 'no'; ?>">
                    <?php echo !empty($form['is_active']) ? 'Attivo' : 'Disattivo'; ?>
                </span>
                <span class="status-chip <?php echo !empty($form['can_login_web']) ? 'ok' : 'no'; ?>">
                    <?php echo !empty($form['can_login_web']) ? 'Web ON' : 'Web OFF'; ?>
                </span>
                <span class="status-chip <?php echo !empty($form['can_login_app']) ? 'ok' : 'no'; ?>">
                    <?php echo !empty($form['can_login_app']) ? 'App ON' : 'App OFF'; ?>
                </span>
            </div>

            <div class="user-note">
                Qui gestisci accesso al software, app, ruolo, scope e permessi specifici.<br>
                Il collegamento al <strong>Personale</strong> è opzionale ma consigliato.
            </div>
        </div>
    </div>

    <div class="card">
        <form method="post" id="userEditForm">
            <input type="hidden" name="id" value="<?php echo (int)$form['id']; ?>">

            <div class="user-form-grid">
                <div class="field">
                    <label>Personale collegato</label>
                    <select name="dipendente_id">
                        <option value="0">Nessun collegamento</option>
                        <?php foreach ($operatori as $op): ?>
                            <?php
                                $opId = (int)$op['id'];
                                $opLabel = trim((string)$op['cognome'] . ' ' . (string)$op['nome']);
                                if ($opLabel === '') {
                                    $opLabel = 'Persona #' . $opId;
                                }
                                $opTipologia = trim((string)($op['tipologia'] ?? ''));
                                if ($opTipologia !== '') {
                                    $opLabel .= ' · ' . $opTipologia;
                                }
                            ?>
                            <option value="<?php echo $opId; ?>" <?php echo (int)$form['dipendente_id'] === $opId ? 'selected' : ''; ?>>
                                <?php echo h($opLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Username *</label>
                    <input type="text" name="username" value="<?php echo h((string)$form['username']); ?>" required>
                </div>

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo h((string)$form['email']); ?>">
                </div>

                <div class="field">
                    <label><?php echo $isEdit ? 'Nuova password (lascia vuoto per non cambiarla)' : 'Password iniziale *'; ?></label>
                    <input type="password" name="password" value="">
                </div>

                <div class="field">
                    <label>Ruolo</label>
                    <select name="role" id="roleSelect">
                        <option value="<?php echo h(ROLE_USER); ?>" <?php echo $form['role'] === ROLE_USER ? 'selected' : ''; ?>>User</option>
                        <option value="<?php echo h(ROLE_MANAGER); ?>" <?php echo $form['role'] === ROLE_MANAGER ? 'selected' : ''; ?>>Manager</option>
                        <option value="<?php echo h(ROLE_MASTER); ?>" <?php echo $form['role'] === ROLE_MASTER ? 'selected' : ''; ?>>Master</option>
                    </select>
                </div>

                <div class="field">
                    <label>Scope</label>
                    <select name="scope" id="scopeSelect">
                        <option value="<?php echo h(SCOPE_SELF); ?>" <?php echo $form['scope'] === SCOPE_SELF ? 'selected' : ''; ?>>Self</option>
                        <option value="<?php echo h(SCOPE_TEAM); ?>" <?php echo $form['scope'] === SCOPE_TEAM ? 'selected' : ''; ?>>Team</option>
                        <option value="<?php echo h(SCOPE_GLOBAL); ?>" <?php echo $form['scope'] === SCOPE_GLOBAL ? 'selected' : ''; ?>>Global</option>
                    </select>
                </div>

                <div class="field full">
                    <label>Stato e accessi</label>
                    <div class="check-row">
                        <label class="check-pill">
                            <input type="checkbox" name="is_active" value="1" <?php echo !empty($form['is_active']) ? 'checked' : ''; ?> style="width:auto;">
                            <span>Attivo</span>
                        </label>

                        <label class="check-pill">
                            <input type="checkbox" name="can_login_web" value="1" <?php echo !empty($form['can_login_web']) ? 'checked' : ''; ?> style="width:auto;">
                            <span>Accesso Web</span>
                        </label>

                        <label class="check-pill">
                            <input type="checkbox" name="can_login_app" value="1" <?php echo !empty($form['can_login_app']) ? 'checked' : ''; ?> style="width:auto;">
                            <span>Accesso App</span>
                        </label>

                        <label class="check-pill">
                            <input type="checkbox" name="must_change_password" value="1" <?php echo !empty($form['must_change_password']) ? 'checked' : ''; ?> style="width:auto;">
                            <span>Cambio password obbligatorio</span>
                        </label>

                        <label class="check-pill">
                            <input type="checkbox" name="is_administrative" value="1" <?php echo !empty($form['is_administrative']) ? 'checked' : ''; ?> style="width:auto;">
                            <span>Amministrativo</span>
                        </label>
                    </div>
                </div>
            </div>

            <?php if (can('users.permissions')): ?>
                <div class="section-title-local">Permessi specifici utente</div>

                <div class="info-box">
                    Qui puoi decidere se l’utente eredita i permessi dal ruolo oppure se alcuni permessi vengono forzati su <strong>Consenti</strong> o <strong>Nega</strong>.
                </div>

                <div class="preset-bar">
                    <button type="button" class="preset-btn" data-preset="inherit">Eredita tutto</button>
                    <button type="button" class="preset-btn" data-preset="readonly">Solo lettura</button>
                    <button type="button" class="preset-btn" data-preset="manager">Manager operativo</button>
                    <button type="button" class="preset-btn" data-preset="master">Accesso totale</button>
                </div>

                <div class="permissions-wrap">
                    <?php foreach ($permissionGroups as $group => $groupPermissions): ?>
                        <div class="permission-group" data-group="<?php echo h($group); ?>">
                            <div class="permission-group-head">
                                <div class="permission-group-title"><?php echo h(permission_group_label($group)); ?></div>

                                <div class="check-row">
                                    <button type="button" class="preset-btn group-btn" data-group-action="inherit" data-group="<?php echo h($group); ?>">Eredita gruppo</button>
                                    <button type="button" class="preset-btn group-btn" data-group-action="allow" data-group="<?php echo h($group); ?>">Consenti gruppo</button>
                                    <button type="button" class="preset-btn group-btn" data-group-action="deny" data-group="<?php echo h($group); ?>">Nega gruppo</button>
                                </div>
                            </div>

                            <div class="permission-grid">
                                <?php foreach ($groupPermissions as $perm): ?>
                                    <?php
                                    $permId = (int)$perm['id'];
                                    $code = (string)$perm['code'];
                                    $parts = explode('.', $code, 2);
                                    $action = $parts[1] ?? $code;
                                    ?>
                                    <div class="permission-row" data-permission-code="<?php echo h($code); ?>">
                                        <div class="permission-code">
                                            <strong><?php echo h(permission_action_label($code)); ?></strong>
                                            <div class="permission-help"><?php echo h($code); ?></div>
                                        </div>

                                        <div class="field" style="margin:0;">
                                            <select
                                                name="<?php echo h('perm_' . $permId); ?>"
                                                class="permission-select"
                                                data-permission-code="<?php echo h($code); ?>"
                                                data-group="<?php echo h($group); ?>"
                                                data-action="<?php echo h($action); ?>"
                                            >
                                                <option value="inherit" <?php echo ($permissionStates[$permId] ?? 'inherit') === 'inherit' ? 'selected' : ''; ?>>Eredita dal ruolo</option>
                                                <option value="allow" <?php echo ($permissionStates[$permId] ?? 'inherit') === 'allow' ? 'selected' : ''; ?>>Consenti</option>
                                                <option value="deny" <?php echo ($permissionStates[$permId] ?? 'inherit') === 'deny' ? 'selected' : ''; ?>>Nega</option>
                                            </select>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="actions-row">
                <button type="submit" class="btn btn-primary">
                    <?php echo $isEdit ? 'Salva utente' : 'Crea utente'; ?>
                </button>

                <a href="<?php echo h(app_url('modules/users/index.php')); ?>" class="btn btn-ghost">
                    Torna alla lista
                </a>

                <?php if ($isEdit && !empty($form['dipendente_id'])): ?>
                    <a href="<?php echo h(app_url('modules/operators/edit.php?id=' . (int)$form['dipendente_id'])); ?>" class="btn btn-secondary">
                        Apri Personale
                    </a>
                <?php endif; ?>

                <?php if ($isEdit && can('users.create')): ?>
                    <a href="<?php echo h(app_url('modules/users/edit.php')); ?>" class="btn btn-secondary">
                        Nuovo utente
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (can('users.permissions')): ?>
<script>
(function () {
    const selects = Array.from(document.querySelectorAll('.permission-select'));
    const presetButtons = Array.from(document.querySelectorAll('.preset-btn[data-preset]'));
    const groupButtons = Array.from(document.querySelectorAll('.group-btn'));

    if (!selects.length) {
        return;
    }

    function setAll(value) {
        selects.forEach(function (select) {
            select.value = value;
        });
    }

    function applyReadOnlyPreset() {
        selects.forEach(function (select) {
            const action = (select.dataset.action || '').toLowerCase();
            if (action === 'view') {
                select.value = 'allow';
            } else {
                select.value = 'inherit';
            }
        });
    }

    function applyManagerPreset() {
        selects.forEach(function (select) {
            const code = (select.dataset.permissionCode || '').toLowerCase();
            const action = (select.dataset.action || '').toLowerCase();

            if (code.startsWith('dashboard.') ||
                code.startsWith('operators.') ||
                code.startsWith('destinations.') ||
                code.startsWith('assignments.') ||
                code.startsWith('calendar.') ||
                code.startsWith('reports.') ||
                code.startsWith('communications.')) {
                if (action === 'delete') {
                    select.value = 'inherit';
                } else {
                    select.value = 'allow';
                }
                return;
            }

            if (code.startsWith('users.view')) {
                select.value = 'allow';
                return;
            }

            if (code.startsWith('settings.view')) {
                select.value = 'allow';
                return;
            }

            select.value = 'inherit';
        });
    }

    function applyMasterPreset() {
        selects.forEach(function (select) {
            select.value = 'allow';
        });
    }

    presetButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const preset = button.dataset.preset || '';

            if (preset === 'inherit') {
                setAll('inherit');
            } else if (preset === 'readonly') {
                applyReadOnlyPreset();
            } else if (preset === 'manager') {
                applyManagerPreset();
            } else if (preset === 'master') {
                applyMasterPreset();
            }
        });
    });

    groupButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const group = button.dataset.group || '';
            const action = button.dataset.groupAction || 'inherit';

            selects.forEach(function (select) {
                if ((select.dataset.group || '') === group) {
                    select.value = action;
                }
            });
        });
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>