<?php
// core/auth.php
// Gestione autenticazione e permessi per Turnar

require_once __DIR__ . '/../config/bootstrap.php';

// Evita inclusioni multiple
if (defined('TURNAR_AUTH_LOADED')) {
    return;
}
define('TURNAR_AUTH_LOADED', true);

// --------------------------------------------------
// CHIAVI SESSIONE STANDARD TURNAR
// --------------------------------------------------
if (!defined('AUTH_SESSION_USER')) {
    define('AUTH_SESSION_USER', 'turnar_auth_user');
}
if (!defined('AUTH_SESSION_LAST_LOGIN')) {
    define('AUTH_SESSION_LAST_LOGIN', 'turnar_auth_last_login');
}
if (!defined('AUTH_SESSION_PERMISSIONS')) {
    define('AUTH_SESSION_PERMISSIONS', 'turnar_auth_permissions');
}

// --------------------------------------------------
// LETTURA UTENTE CORRENTE
// --------------------------------------------------
if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = $_SESSION[AUTH_SESSION_USER] ?? null;

        if (!is_array($user) || empty($user['id'])) {
            return null;
        }

        return $user;
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return auth_user() !== null;
    }
}

if (!function_exists('auth_id')) {
    function auth_id(): ?int
    {
        $user = auth_user();
        return $user ? (int)($user['id'] ?? 0) : null;
    }
}

if (!function_exists('auth_dipendente_id')) {
    function auth_dipendente_id(): ?int
    {
        $user = auth_user();
        if (!$user) {
            return null;
        }

        $id = isset($user['dipendente_id']) ? (int)$user['dipendente_id'] : 0;
        return $id > 0 ? $id : null;
    }
}

if (!function_exists('auth_username')) {
    function auth_username(): ?string
    {
        $user = auth_user();
        if (!$user) {
            return null;
        }

        $value = trim((string)($user['username'] ?? ''));
        return $value !== '' ? $value : null;
    }
}

if (!function_exists('auth_display_name')) {
    function auth_display_name(): string
    {
        $user = auth_user();
        if (!$user) {
            return '';
        }

        $nome = trim((string)($user['nome'] ?? ''));
        $cognome = trim((string)($user['cognome'] ?? ''));
        $fullName = trim($nome . ' ' . $cognome);

        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string)($user['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        return 'Utente';
    }
}

if (!function_exists('auth_email')) {
    function auth_email(): ?string
    {
        $user = auth_user();
        if (!$user) {
            return null;
        }

        $value = trim((string)($user['email'] ?? ''));
        return $value !== '' ? $value : null;
    }
}

if (!function_exists('auth_role')) {
    function auth_role(): string
    {
        $user = auth_user();
        if (!$user) {
            return ROLE_USER;
        }

        return normalize_role((string)($user['role'] ?? ROLE_USER));
    }
}

if (!function_exists('auth_scope')) {
    function auth_scope(): string
    {
        $user = auth_user();
        if (!$user) {
            return SCOPE_SELF;
        }

        return normalize_scope((string)($user['scope'] ?? SCOPE_SELF));
    }
}

if (!function_exists('auth_must_change_password')) {
    function auth_must_change_password(): bool
    {
        $user = auth_user();
        return $user ? !empty($user['must_change_password']) : false;
    }
}

// --------------------------------------------------
// RUOLI / SCOPE
// --------------------------------------------------
if (!function_exists('is_user')) {
    function is_user(): bool
    {
        return auth_role() === ROLE_USER;
    }
}

if (!function_exists('is_operator')) {
    function is_operator(): bool
    {
        return is_user();
    }
}

if (!function_exists('is_manager')) {
    function is_manager(): bool
    {
        return auth_role() === ROLE_MANAGER;
    }
}

if (!function_exists('is_director')) {
    function is_director(): bool
    {
        return is_manager();
    }
}

if (!function_exists('is_master')) {
    function is_master(): bool
    {
        return auth_role() === ROLE_MASTER;
    }
}

if (!function_exists('is_manager_or_above')) {
    function is_manager_or_above(): bool
    {
        return in_array(auth_role(), [ROLE_MANAGER, ROLE_MASTER], true);
    }
}

if (!function_exists('is_director_or_above')) {
    function is_director_or_above(): bool
    {
        return is_manager_or_above();
    }
}

if (!function_exists('has_global_scope')) {
    function has_global_scope(): bool
    {
        return auth_scope() === SCOPE_GLOBAL;
    }
}

if (!function_exists('has_team_scope')) {
    function has_team_scope(): bool
    {
        return in_array(auth_scope(), [SCOPE_TEAM, SCOPE_GLOBAL], true);
    }
}

if (!function_exists('has_area_scope')) {
    function has_area_scope(): bool
    {
        return has_team_scope();
    }
}

// --------------------------------------------------
// PERMESSI
// --------------------------------------------------
if (!function_exists('auth_permissions')) {
    function auth_permissions(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $codes = $_SESSION[AUTH_SESSION_PERMISSIONS] ?? [];

        if (!is_array($codes)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('strval', $codes))));
    }
}

if (!function_exists('auth_has_permission')) {
    function auth_has_permission(string $permissionCode): bool
    {
        $permissionCode = trim($permissionCode);
        if ($permissionCode === '') {
            return false;
        }

        if (is_master()) {
            return true;
        }

        return in_array($permissionCode, auth_permissions(), true);
    }
}

if (!function_exists('can')) {
    function can(string $permissionCode): bool
    {
        return auth_has_permission($permissionCode);
    }
}

if (!function_exists('cannot')) {
    function cannot(string $permissionCode): bool
    {
        return !can($permissionCode);
    }
}

if (!function_exists('require_permission')) {
    function require_permission(string $permissionCode): void
    {
        require_login();

        if (!can($permissionCode)) {
            http_response_code(403);
            exit('Accesso negato.');
        }
    }
}

if (!function_exists('auth_load_permissions_from_db')) {
    function auth_load_permissions_from_db(int $userId, string $role): array
    {
        $codes = [];

        try {
            $db = db_connect();

            $sql = "
                SELECT
                    p.code,
                    COALESCE(up.is_allowed, rp.is_allowed, 0) AS allowed_value
                FROM permissions p
                LEFT JOIN role_permissions rp
                    ON rp.permission_id = p.id
                   AND rp.role = ?
                LEFT JOIN user_permissions up
                    ON up.permission_id = p.id
                   AND up.user_id = ?
                ORDER BY p.id ASC
            ";

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('si', $role, $userId);
            $stmt->execute();
            $res = $stmt->get_result();

            while ($row = $res->fetch_assoc()) {
                if (!empty($row['allowed_value'])) {
                    $codes[] = (string)$row['code'];
                }
            }

            $stmt->close();
        } catch (Throwable $e) {
            return [];
        }

        return array_values(array_unique($codes));
    }
}

// --------------------------------------------------
// LOGIN / LOGOUT SESSIONE
// --------------------------------------------------
if (!function_exists('auth_login')) {
    function auth_login(array $user, array $permissions = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $normalized = [
            'id'                   => isset($user['id']) ? (int)$user['id'] : 0,
            'dipendente_id'        => isset($user['dipendente_id']) ? (int)$user['dipendente_id'] : null,
            'username'             => trim((string)($user['username'] ?? '')),
            'nome'                 => trim((string)($user['nome'] ?? '')),
            'cognome'              => trim((string)($user['cognome'] ?? '')),
            'email'                => trim((string)($user['email'] ?? '')),
            'role'                 => normalize_role((string)($user['role'] ?? ROLE_USER)),
            'scope'                => normalize_scope((string)($user['scope'] ?? SCOPE_SELF)),
            'mobile'               => !empty($user['mobile']) ? 1 : 0,
            'active'               => array_key_exists('active', $user) ? (int)!empty($user['active']) : 1,
            'is_active'            => array_key_exists('is_active', $user) ? (int)!empty($user['is_active']) : 1,
            'can_login_web'        => array_key_exists('can_login_web', $user) ? (int)!empty($user['can_login_web']) : 1,
            'can_login_app'        => array_key_exists('can_login_app', $user) ? (int)!empty($user['can_login_app']) : 1,
            'must_change_password' => array_key_exists('must_change_password', $user) ? (int)!empty($user['must_change_password']) : 0,
            'is_administrative'    => array_key_exists('is_administrative', $user) ? (int)!empty($user['is_administrative']) : 0,
            'last_login_at'        => $user['last_login_at'] ?? null,
        ];

        if ($normalized['id'] <= 0) {
            throw new InvalidArgumentException('Utente non valido per login.');
        }

        $_SESSION[AUTH_SESSION_USER] = $normalized;
        $_SESSION[AUTH_SESSION_LAST_LOGIN] = time();
        $_SESSION[AUTH_SESSION_PERMISSIONS] = array_values(array_unique(array_filter(array_map('strval', $permissions))));
        $_SESSION['TURNAR_LAST_ACTIVITY'] = time();
    }
}

if (!function_exists('auth_refresh_session_user')) {
    function auth_refresh_session_user(): void
    {
        $userId = auth_id();
        if (!$userId) {
            return;
        }

        try {
            $db = db_connect();

            $sql = "
                SELECT
                    u.id,
                    u.dipendente_id,
                    u.role,
                    u.scope,
                    u.username,
                    u.email,
                    u.is_active,
                    u.can_login_web,
                    u.can_login_app,
                    u.must_change_password,
                    u.is_administrative,
                    u.last_login_at,
                    d.nome,
                    d.cognome
                FROM users u
                LEFT JOIN dipendenti d ON d.id = u.dipendente_id
                WHERE u.id = ?
                LIMIT 1
            ";

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                return;
            }

            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$row) {
                return;
            }

            $permissions = auth_load_permissions_from_db((int)$row['id'], normalize_role((string)$row['role']));
            auth_login([
                'id'                   => (int)$row['id'],
                'dipendente_id'        => isset($row['dipendente_id']) ? (int)$row['dipendente_id'] : null,
                'username'             => (string)($row['username'] ?? ''),
                'nome'                 => (string)($row['nome'] ?? ''),
                'cognome'              => (string)($row['cognome'] ?? ''),
                'email'                => (string)($row['email'] ?? ''),
                'role'                 => (string)($row['role'] ?? ROLE_USER),
                'scope'                => (string)($row['scope'] ?? SCOPE_SELF),
                'mobile'               => 1,
                'active'               => !empty($row['is_active']) ? 1 : 0,
                'is_active'            => !empty($row['is_active']) ? 1 : 0,
                'can_login_web'        => !empty($row['can_login_web']) ? 1 : 0,
                'can_login_app'        => !empty($row['can_login_app']) ? 1 : 0,
                'must_change_password' => !empty($row['must_change_password']) ? 1 : 0,
                'is_administrative'    => !empty($row['is_administrative']) ? 1 : 0,
                'last_login_at'        => $row['last_login_at'] ?? null,
            ], $permissions);
        } catch (Throwable $e) {
            // silenzioso
        }
    }
}

if (!function_exists('auth_logout')) {
    function auth_logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset(
            $_SESSION[AUTH_SESSION_USER],
            $_SESSION[AUTH_SESSION_LAST_LOGIN],
            $_SESSION[AUTH_SESSION_PERMISSIONS]
        );

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}

// --------------------------------------------------
// ACCESSO DB UTENTI
// --------------------------------------------------
if (!function_exists('auth_find_user_by_login')) {
    function auth_find_user_by_login(string $login): ?array
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        try {
            $db = db_connect();

            $sql = "
                SELECT
                    u.id,
                    u.dipendente_id,
                    u.role,
                    u.scope,
                    u.username,
                    u.password_hash,
                    u.email,
                    u.is_active,
                    u.can_login_web,
                    u.can_login_app,
                    u.must_change_password,
                    u.is_administrative,
                    u.last_login_at,
                    d.nome,
                    d.cognome,
                    d.password AS legacy_dip_password
                FROM users u
                LEFT JOIN dipendenti d ON d.id = u.dipendente_id
                WHERE (u.username = ? OR u.email = ?)
                LIMIT 1
            ";

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                return null;
            }

            $stmt->bind_param('ss', $login, $login);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('auth_mark_login_success')) {
    function auth_mark_login_success(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $db = db_connect();
            $stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            // silenzioso
        }
    }
}

if (!function_exists('auth_upgrade_password_hash')) {
    function auth_upgrade_password_hash(int $userId, string $plainPassword): void
    {
        if ($userId <= 0 || $plainPassword === '') {
            return;
        }

        try {
            $db = db_connect();
            $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                UPDATE users
                SET password_hash = ?, must_change_password = 1, updated_at = NOW()
                WHERE id = ?
            ");
            if ($stmt) {
                $stmt->bind_param('si', $newHash, $userId);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            // silenzioso
        }
    }
}

if (!function_exists('auth_password_matches_legacy_value')) {
    function auth_password_matches_legacy_value(string $plainPassword, string $legacyValue): bool
    {
        $legacyValue = trim($legacyValue);
        if ($legacyValue === '') {
            return false;
        }

        if (hash_equals($legacyValue, $plainPassword)) {
            return true;
        }

        if (preg_match('/^[a-f0-9]{32}$/i', $legacyValue) && hash_equals(strtolower($legacyValue), md5($plainPassword))) {
            return true;
        }

        if (preg_match('/^[a-f0-9]{40}$/i', $legacyValue) && hash_equals(strtolower($legacyValue), sha1($plainPassword))) {
            return true;
        }

        if (str_starts_with($legacyValue, '$2y$') || str_starts_with($legacyValue, '$2a$') || str_starts_with($legacyValue, '$argon2')) {
            return password_verify($plainPassword, $legacyValue);
        }

        return false;
    }
}

if (!function_exists('auth_change_current_user_password')) {
    function auth_change_current_user_password(string $newPassword): array
    {
        $userId = auth_id();

        if (!$userId) {
            return [
                'ok' => false,
                'message' => 'Utente non autenticato.',
            ];
        }

        $newPassword = trim($newPassword);
        if ($newPassword === '') {
            return [
                'ok' => false,
                'message' => 'Inserisci la nuova password.',
            ];
        }

        try {
            $db = db_connect();
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                UPDATE users
                SET password_hash = ?, must_change_password = 0, updated_at = NOW()
                WHERE id = ?
            ");
            if (!$stmt) {
                return [
                    'ok' => false,
                    'message' => 'Errore preparazione salvataggio password.',
                ];
            }

            $stmt->bind_param('si', $newHash, $userId);
            $ok = $stmt->execute();
            $stmt->close();

            if (!$ok) {
                return [
                    'ok' => false,
                    'message' => 'Errore durante il salvataggio della password.',
                ];
            }

            auth_refresh_session_user();

            return [
                'ok' => true,
                'message' => 'Password aggiornata correttamente.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Errore durante il cambio password.',
            ];
        }
    }
}

if (!function_exists('auth_attempt')) {
    function auth_attempt(string $login, string $password, string $context = 'web'): array
    {
        $login = trim($login);
        $password = (string)$password;
        $context = strtolower(trim($context));

        if ($login === '' || $password === '') {
            return [
                'ok' => false,
                'message' => 'Inserisci username e password.',
            ];
        }

        $user = auth_find_user_by_login($login);

        if (!$user) {
            return [
                'ok' => false,
                'message' => 'Credenziali non valide.',
            ];
        }

        if (empty($user['is_active'])) {
            return [
                'ok' => false,
                'message' => 'Utente disattivato.',
            ];
        }

        if ($context === 'web' && empty($user['can_login_web'])) {
            return [
                'ok' => false,
                'message' => 'Questo utente non può accedere al software web.',
            ];
        }

        if ($context === 'app' && empty($user['can_login_app'])) {
            return [
                'ok' => false,
                'message' => 'Questo utente non può accedere all’app mobile.',
            ];
        }

        $passwordOk = false;
        $passwordHash = trim((string)($user['password_hash'] ?? ''));

        if ($passwordHash !== '' && password_verify($password, $passwordHash)) {
            $passwordOk = true;
        } else {
            $legacyDipPassword = (string)($user['legacy_dip_password'] ?? '');
            if (auth_password_matches_legacy_value($password, $legacyDipPassword)) {
                $passwordOk = true;
                auth_upgrade_password_hash((int)$user['id'], $password);
            }
        }

        if (!$passwordOk) {
            return [
                'ok' => false,
                'message' => 'Credenziali non valide.',
            ];
        }

        $role = normalize_role((string)($user['role'] ?? ROLE_USER));
        $permissions = auth_load_permissions_from_db((int)$user['id'], $role);

        auth_login([
            'id'                   => (int)$user['id'],
            'dipendente_id'        => isset($user['dipendente_id']) ? (int)$user['dipendente_id'] : null,
            'username'             => (string)($user['username'] ?? ''),
            'nome'                 => (string)($user['nome'] ?? ''),
            'cognome'              => (string)($user['cognome'] ?? ''),
            'email'                => (string)($user['email'] ?? ''),
            'role'                 => $role,
            'scope'                => normalize_scope((string)($user['scope'] ?? SCOPE_SELF)),
            'mobile'               => 1,
            'active'               => !empty($user['is_active']) ? 1 : 0,
            'is_active'            => !empty($user['is_active']) ? 1 : 0,
            'can_login_web'        => !empty($user['can_login_web']) ? 1 : 0,
            'can_login_app'        => !empty($user['can_login_app']) ? 1 : 0,
            'must_change_password' => !empty($user['must_change_password']) ? 1 : 0,
            'is_administrative'    => !empty($user['is_administrative']) ? 1 : 0,
            'last_login_at'        => $user['last_login_at'] ?? null,
        ], $permissions);

        auth_mark_login_success((int)$user['id']);

        return [
            'ok' => true,
            'message' => 'Login effettuato con successo.',
            'must_change_password' => !empty($user['must_change_password']),
        ];
    }
}

// --------------------------------------------------
// REQUIRE LOGIN / REQUIRE ROLE
// --------------------------------------------------
if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!auth_check()) {
            redirect('modules/auth/login.php');
        }

        $currentPath = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        $changePasswordPath = app_url('modules/auth/change_password.php');

        if (
            auth_must_change_password() &&
            $currentPath !== '' &&
            $currentPath !== $changePasswordPath
        ) {
            redirect('modules/auth/change_password.php');
        }
    }
}

if (!function_exists('require_mobile_login')) {
    function require_mobile_login(): void
    {
        if (auth_check()) {
            return;
        }

        redirect_mobile('login.php');
    }
}

if (!function_exists('require_role')) {
    function require_role(array $allowedRoles): void
    {
        require_login();

        $normalized = array_map('normalize_role', $allowedRoles);

        if (!in_array(auth_role(), $normalized, true)) {
            http_response_code(403);
            exit('Ruolo non autorizzato.');
        }
    }
}

if (!function_exists('require_scope')) {
    function require_scope(array $allowedScopes): void
    {
        require_login();

        $normalized = array_map('normalize_scope', $allowedScopes);

        if (!in_array(auth_scope(), $normalized, true)) {
            http_response_code(403);
            exit('Scope non autorizzato.');
        }
    }
}

// --------------------------------------------------
// HELPERS VISIBILITÀ DATI
// --------------------------------------------------
if (!function_exists('can_view_all_assignments')) {
    function can_view_all_assignments(): bool
    {
        return can('assignments.view') && has_global_scope();
    }
}

if (!function_exists('can_view_team_assignments')) {
    function can_view_team_assignments(): bool
    {
        return can('assignments.view') && has_team_scope();
    }
}

if (!function_exists('can_view_own_assignments')) {
    function can_view_own_assignments(): bool
    {
        return auth_check() && can('assignments.view');
    }
}

if (!function_exists('can_manage_assignments')) {
    function can_manage_assignments(): bool
    {
        return can('assignments.manage');
    }
}

if (!function_exists('can_access_mobile_extended_view')) {
    function can_access_mobile_extended_view(): bool
    {
        return can_view_team_assignments();
    }
}