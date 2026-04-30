<?php
require_once __DIR__ . '/../../core/helpers.php';

require_login();

$db = db_connect();

$id = (int)get('id', 0);
$isEdit = $id > 0;

if ($isEdit) {
    require_permission('users.edit');
} else {
    require_permission('users.create');
}

$pageTitle    = $isEdit ? 'Modifica utente' : 'Nuovo utente';
$pageSubtitle = 'Gestione accesso, ruolo e permessi';
$activeModule = 'users';

$errors = [];

/* =========================
   BLOCCO SICUREZZA MANAGER
   ========================= */
$existingUser = null;

if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $existingUser = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$existingUser) {
        redirect(app_url('modules/users/index.php'));
    }

    if (
        is_manager() &&
        (
            $existingUser['role'] === ROLE_MASTER ||
            !empty($existingUser['is_administrative'])
        )
    ) {
        http_response_code(403);
        exit('Accesso negato: un manager non può modificare utenti sensibili.');
    }
}

/* =========================
   SALVATAGGIO
   ========================= */
if (is_post()) {

    $username = trim((string)post('username'));
    $email    = trim((string)post('email'));
    $password = trim((string)post('password'));

    $role  = normalize_role(post('role', ROLE_USER));
    $scope = normalize_scope(post('scope', SCOPE_SELF));

    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isAdmin  = isset($_POST['is_administrative']) ? 1 : 0;
    $web      = isset($_POST['can_login_web']) ? 1 : 0;
    $app      = isset($_POST['can_login_app']) ? 1 : 0;

    // 🔐 LIMITI MANAGER
    if (is_manager()) {
        if ($role === ROLE_MASTER) {
            $errors[] = 'Un manager non può assegnare ruolo Master.';
        }
        if ($isAdmin) {
            $errors[] = 'Un manager non può rendere amministrativo.';
        }
        if ($scope === SCOPE_GLOBAL) {
            $errors[] = 'Un manager non può usare scope globale.';
        }
    }

    if ($username === '') {
        $errors[] = 'Username obbligatorio.';
    }

    if (empty($errors)) {

        if ($isEdit) {

            $sql = "
                UPDATE users SET
                    username = ?,
                    email = ?,
                    role = ?,
                    scope = ?,
                    is_active = ?,
                    is_administrative = ?,
                    can_login_web = ?,
                    can_login_app = ?
                WHERE id = ?
            ";

            $stmt = $db->prepare($sql);
            $stmt->bind_param(
                'ssssiiiii',
                $username,
                $email,
                $role,
                $scope,
                $isActive,
                $isAdmin,
                $web,
                $app,
                $id
            );

            $stmt->execute();
            $stmt->close();

            // password opzionale
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param('si', $hash, $id);
                $stmt->execute();
                $stmt->close();
            }

        } else {

            if ($password === '') {
                $errors[] = 'Password obbligatoria.';
            } else {

                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("
                    INSERT INTO users
                    (username, email, password, role, scope, is_active, is_administrative, can_login_web, can_login_app)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    'sssssiiii',
                    $username,
                    $email,
                    $hash,
                    $role,
                    $scope,
                    $isActive,
                    $isAdmin,
                    $web,
                    $app
                );

                $stmt->execute();
                $stmt->close();
            }
        }

        if (empty($errors)) {
            redirect(app_url('modules/users/index.php?saved=1'));
        }
    }
}

/* =========================
   DEFAULT FORM
   ========================= */
$user = $existingUser ?? [
    'username' => '',
    'email' => '',
    'role' => ROLE_USER,
    'scope' => SCOPE_SELF,
    'is_active' => 1,
    'is_administrative' => 0,
    'can_login_web' => 1,
    'can_login_app' => 0
];

require_once __DIR__ . '/../../templates/layout_top.php';
?>

<div class="content-card" style="max-width:700px">

    <h2><?php echo $isEdit ? 'Modifica utente' : 'Nuovo utente'; ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $e): ?>
                <div>• <?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="form-grid">

        <label>Username</label>
        <input type="text" name="username" value="<?php echo h($user['username']); ?>">

        <label>Email</label>
        <input type="email" name="email" value="<?php echo h($user['email']); ?>">

        <label>Password <?php echo $isEdit ? '(lascia vuota per non cambiare)' : ''; ?></label>
        <input type="password" name="password">

        <label>Ruolo</label>
        <select name="role">
            <option value="user" <?php if ($user['role'] === ROLE_USER) echo 'selected'; ?>>User</option>
            <option value="manager" <?php if ($user['role'] === ROLE_MANAGER) echo 'selected'; ?>>Manager</option>
            <option value="master" <?php if ($user['role'] === ROLE_MASTER) echo 'selected'; ?>>Master</option>
        </select>

        <label>Scope</label>
        <select name="scope">
            <option value="self" <?php if ($user['scope'] === SCOPE_SELF) echo 'selected'; ?>>Self</option>
            <option value="team" <?php if ($user['scope'] === SCOPE_TEAM) echo 'selected'; ?>>Team</option>
            <option value="global" <?php if ($user['scope'] === SCOPE_GLOBAL) echo 'selected'; ?>>Global</option>
        </select>

        <label><input type="checkbox" name="is_active" <?php if ($user['is_active']) echo 'checked'; ?>> Attivo</label>
        <label><input type="checkbox" name="is_administrative" <?php if ($user['is_administrative']) echo 'checked'; ?>> Amministrativo</label>
        <label><input type="checkbox" name="can_login_web" <?php if ($user['can_login_web']) echo 'checked'; ?>> Accesso Web</label>
        <label><input type="checkbox" name="can_login_app" <?php if ($user['can_login_app']) echo 'checked'; ?>> Accesso App</label>

        <div style="margin-top:15px;">
            <button class="btn btn-primary">Salva</button>
            <a href="<?php echo app_url('modules/users/index.php'); ?>" class="btn btn-ghost">Annulla</a>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../../templates/layout_bottom.php'; ?>
