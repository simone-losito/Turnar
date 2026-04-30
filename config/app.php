<?php
// config/app.php
// Configurazione base dell'app Turnar

if (defined('TURNAR_APP_LOADED')) {
    return;
}
define('TURNAR_APP_LOADED', true);

// --------------------------------------------------
// DATI BASE APPLICAZIONE
// --------------------------------------------------
define('APP_NAME', 'Turnar');
define('APP_VERSION', '0.2.0');
define('APP_ENV', 'local');
define('APP_DEBUG', true);

date_default_timezone_set('Europe/Rome');

if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Europe/Rome');
if (!defined('APP_LOCALE')) define('APP_LOCALE', 'it_IT');
if (!defined('APP_LANGUAGE')) define('APP_LANGUAGE', 'it');
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
if (!defined('APP_BASE_URL')) define('APP_BASE_URL', '/Turnar');
if (!defined('APP_WEB_PATH')) define('APP_WEB_PATH', APP_BASE_URL);
if (!defined('APP_MOBILE_PATH')) define('APP_MOBILE_PATH', APP_BASE_URL . '/app');

if (!defined('APP_DISPLAY_NAME')) define('APP_DISPLAY_NAME', 'Turnar');
if (!defined('APP_TAGLINE')) define('APP_TAGLINE', 'Piattaforma modulare per turni e attività operative');
if (!defined('APP_COMPANY_NAME')) define('APP_COMPANY_NAME', 'Turnar');
if (!defined('APP_LOGO_PATH')) define('APP_LOGO_PATH', APP_BASE_URL . '/assets/img/logo.png');
if (!defined('APP_FAVICON_PATH')) define('APP_FAVICON_PATH', APP_BASE_URL . '/assets/img/favicon.ico');
if (!defined('SESSION_INACTIVITY_LIMIT')) define('SESSION_INACTIVITY_LIMIT', 7200);

@ini_set('session.gc_maxlifetime', (string)SESSION_INACTIVITY_LIMIT);
@ini_set('session.cookie_lifetime', '0');
@session_name('TURNARSESSID');

// --------------------------------------------------
// RUOLI / SCOPE
// --------------------------------------------------
if (!defined('ROLE_USER')) define('ROLE_USER', 'user');
if (!defined('ROLE_MANAGER')) define('ROLE_MANAGER', 'manager');
if (!defined('ROLE_MASTER')) define('ROLE_MASTER', 'master');
if (!defined('ROLE_OPERATOR')) define('ROLE_OPERATOR', ROLE_USER);
if (!defined('ROLE_DIRECTOR')) define('ROLE_DIRECTOR', ROLE_MANAGER);

if (!defined('SCOPE_SELF')) define('SCOPE_SELF', 'self');
if (!defined('SCOPE_TEAM')) define('SCOPE_TEAM', 'team');
if (!defined('SCOPE_GLOBAL')) define('SCOPE_GLOBAL', 'global');
if (!defined('SCOPE_AREA')) define('SCOPE_AREA', SCOPE_TEAM);

// --------------------------------------------------
// MODULI BASE TURNAR
// --------------------------------------------------
$GLOBALS['TURNAR_MODULES'] = [
    'dashboard'      => true,
    'operators'      => true,
    'destinations'   => true,
    'assignments'    => true,
    'calendar'       => true,
    'communications' => true,
    'reports'        => true,
    'gantt'          => true,
    'users'          => true,
    'settings'       => true,
    'mobile'         => true,
    'badges'         => true,
    'push'           => true,
    'email'          => true,
];

// --------------------------------------------------
// URL / PATH
// --------------------------------------------------
if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return $path === '' ? APP_BASE_URL : APP_BASE_URL . '/' . $path;
    }
}

if (!function_exists('mobile_url')) {
    function mobile_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return $path === '' ? APP_MOBILE_PATH : APP_MOBILE_PATH . '/' . $path;
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        return $path === '' ? APP_ROOT : APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}

// --------------------------------------------------
// BRANDING
// --------------------------------------------------
if (!function_exists('app_name')) {
    function app_name(): string
    {
        if (function_exists('setting_string')) {
            $value = trim(setting_string('app_name', APP_DISPLAY_NAME));
            return $value !== '' ? $value : APP_DISPLAY_NAME;
        }
        return APP_DISPLAY_NAME;
    }
}

if (!function_exists('app_version')) {
    function app_version(): string
    {
        if (function_exists('setting_string')) {
            $value = trim(setting_string('app_version_visual', APP_VERSION));
            return $value !== '' ? $value : APP_VERSION;
        }
        return APP_VERSION;
    }
}

if (!function_exists('app_modules')) {
    function app_modules(): array
    {
        return is_array($GLOBALS['TURNAR_MODULES'] ?? null) ? $GLOBALS['TURNAR_MODULES'] : [];
    }
}

// --------------------------------------------------
// MODULI SOFTWARE - MATRICE RUOLI
// Master vede sempre tutto.
// Ogni modulo può essere abilitato globalmente e poi assegnato a Manager/User.
// --------------------------------------------------
if (!function_exists('turnar_default_module_state')) {
    function turnar_default_module_state(string $key, bool $enabled = true): array
    {
        $userAllowed = in_array($key, ['dashboard', 'calendar', 'mobile'], true);
        $managerAllowed = !in_array($key, ['settings'], true);

        $state = [
            'web' => $enabled ? 1 : 0,
            'app' => in_array($key, ['dashboard', 'assignments', 'calendar', 'communications', 'mobile', 'push'], true) ? 1 : 0,
            'menu' => $enabled ? 1 : 0,
            'manager_web' => $managerAllowed ? 1 : 0,
            'manager_app' => in_array($key, ['dashboard', 'assignments', 'calendar', 'communications', 'mobile', 'push'], true) ? 1 : 0,
            'manager_menu' => $managerAllowed ? 1 : 0,
            'user_web' => $userAllowed ? 1 : 0,
            'user_app' => in_array($key, ['dashboard', 'calendar', 'mobile'], true) ? 1 : 0,
            'user_menu' => $userAllowed ? 1 : 0,
        ];

        if ($key === 'settings') {
            $state = [
                'web' => 1,
                'app' => 0,
                'menu' => 1,
                'manager_web' => 0,
                'manager_app' => 0,
                'manager_menu' => 0,
                'user_web' => 0,
                'user_app' => 0,
                'user_menu' => 0,
            ];
        }

        return $state;
    }
}

if (!function_exists('turnar_default_modules_matrix')) {
    function turnar_default_modules_matrix(): array
    {
        $defaults = [];
        foreach (app_modules() as $key => $enabled) {
            $defaults[$key] = turnar_default_module_state($key, !empty($enabled));
        }
        return $defaults;
    }
}

if (!function_exists('turnar_normalize_module_state')) {
    function turnar_normalize_module_state(string $key, array $state, array $defaultState): array
    {
        $normalized = [];
        $fields = ['web','app','menu','manager_web','manager_app','manager_menu','user_web','user_app','user_menu'];

        foreach ($fields as $field) {
            $normalized[$field] = array_key_exists($field, $state)
                ? (int)!empty($state[$field])
                : (int)!empty($defaultState[$field]);
        }

        // Compatibilità vecchia matrice: web/app/menu valgono anche per Manager se i nuovi campi non esistono.
        foreach (['web','app','menu'] as $field) {
            $managerField = 'manager_' . $field;
            if (!array_key_exists($managerField, $state) && array_key_exists($field, $state)) {
                $normalized[$managerField] = (int)!empty($state[$field]);
            }
        }

        if ($key === 'settings') {
            $normalized = turnar_default_module_state('settings', true);
        }

        return $normalized;
    }
}

if (!function_exists('turnar_modules_matrix')) {
    function turnar_modules_matrix(): array
    {
        $defaults = turnar_default_modules_matrix();

        if (!function_exists('setting_string')) {
            return $defaults;
        }

        $raw = trim(setting_string('modules_matrix', ''));
        $decoded = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $matrix = [];
        foreach ($defaults as $key => $defaultState) {
            $state = isset($decoded[$key]) && is_array($decoded[$key]) ? $decoded[$key] : [];
            $matrix[$key] = turnar_normalize_module_state($key, $state, $defaultState);
        }

        return $matrix;
    }
}

if (!function_exists('module_state')) {
    function module_state(string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            return turnar_default_module_state('', false);
        }

        $matrix = turnar_modules_matrix();
        return $matrix[$key] ?? turnar_default_module_state($key, true);
    }
}

if (!function_exists('module_role_state')) {
    function module_role_state(string $key, ?string $role = null): array
    {
        $state = module_state($key);
        $role = normalize_role($role ?? (function_exists('auth_role') ? auth_role() : ROLE_USER));

        if ($role === ROLE_MASTER) {
            return ['web' => 1, 'app' => 1, 'menu' => 1];
        }

        if ($key === 'settings') {
            return ['web' => 0, 'app' => 0, 'menu' => 0];
        }

        $prefix = $role === ROLE_MANAGER ? 'manager_' : 'user_';

        return [
            'web' => !empty($state['web']) && !empty($state[$prefix . 'web']) ? 1 : 0,
            'app' => !empty($state['app']) && !empty($state[$prefix . 'app']) ? 1 : 0,
            'menu' => !empty($state['web']) && !empty($state['menu']) && !empty($state[$prefix . 'web']) && !empty($state[$prefix . 'menu']) ? 1 : 0,
        ];
    }
}

if (!function_exists('module_enabled')) {
    function module_enabled(string $key): bool
    {
        $key = trim($key);
        if ($key === '') return false;
        if (function_exists('is_master') && is_master()) return true;
        if ($key === 'settings') return true;
        return !empty(module_role_state($key)['web']);
    }
}

if (!function_exists('module_app_enabled')) {
    function module_app_enabled(string $key): bool
    {
        $key = trim($key);
        if ($key === '') return false;
        if (function_exists('is_master') && is_master()) return true;
        return !empty(module_role_state($key)['app']);
    }
}

if (!function_exists('module_menu_visible')) {
    function module_menu_visible(string $key): bool
    {
        $key = trim($key);
        if ($key === '') return false;
        if (function_exists('is_master') && is_master()) return true;
        if ($key === 'settings') return true;
        return !empty(module_role_state($key)['menu']);
    }
}

// --------------------------------------------------
// HELPER RUOLI / SCOPE
// --------------------------------------------------
if (!function_exists('role_values')) {
    function role_values(): array
    {
        return [ROLE_USER, ROLE_MANAGER, ROLE_MASTER];
    }
}

if (!function_exists('scope_values')) {
    function scope_values(): array
    {
        return [SCOPE_SELF, SCOPE_TEAM, SCOPE_GLOBAL];
    }
}

if (!function_exists('normalize_role')) {
    function normalize_role(?string $role): string
    {
        $role = strtolower(trim((string)$role));
        if ($role === 'operator') $role = ROLE_USER;
        if ($role === 'director') $role = ROLE_MANAGER;
        return in_array($role, role_values(), true) ? $role : ROLE_USER;
    }
}

if (!function_exists('normalize_scope')) {
    function normalize_scope(?string $scope): string
    {
        $scope = strtolower(trim((string)$scope));
        if ($scope === 'area') $scope = SCOPE_TEAM;
        return in_array($scope, scope_values(), true) ? $scope : SCOPE_SELF;
    }
}

if (!function_exists('role_label')) {
    function role_label(?string $role): string
    {
        return match (normalize_role($role)) {
            ROLE_MASTER => 'Master',
            ROLE_MANAGER => 'Manager',
            default => 'User',
        };
    }
}

if (!function_exists('scope_label')) {
    function scope_label(?string $scope): string
    {
        return match (normalize_scope($scope)) {
            SCOPE_GLOBAL => 'Global',
            SCOPE_TEAM => 'Team',
            default => 'Self',
        };
    }
}
