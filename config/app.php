<?php
// config/app.php
// Configurazione base dell'app Turnar

// Evita inclusioni multiple
if (defined('TURNAR_APP_LOADED')) {
    return;
}
define('TURNAR_APP_LOADED', true);

// --------------------------------------------------
// DATI BASE APPLICAZIONE
// --------------------------------------------------
define('APP_NAME', 'Turnar');
define('APP_VERSION', '0.2.0');
define('APP_ENV', 'local'); // local | production
define('APP_DEBUG', true);

// --------------------------------------------------
// TIMEZONE / LOCALE
// --------------------------------------------------
date_default_timezone_set('Europe/Rome');

if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Europe/Rome');
}
if (!defined('APP_LOCALE')) {
    define('APP_LOCALE', 'it_IT');
}
if (!defined('APP_LANGUAGE')) {
    define('APP_LANGUAGE', 'it');
}

// --------------------------------------------------
// PATH BASE PROGETTO
// --------------------------------------------------
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', '/Turnar');
}

if (!defined('APP_WEB_PATH')) {
    define('APP_WEB_PATH', APP_BASE_URL);
}

if (!defined('APP_MOBILE_PATH')) {
    define('APP_MOBILE_PATH', APP_BASE_URL . '/app');
}

// --------------------------------------------------
// BRANDING DEFAULT
// --------------------------------------------------
if (!defined('APP_DISPLAY_NAME')) {
    define('APP_DISPLAY_NAME', 'Turnar');
}
if (!defined('APP_TAGLINE')) {
    define('APP_TAGLINE', 'Piattaforma modulare per turni e attività operative');
}
if (!defined('APP_COMPANY_NAME')) {
    define('APP_COMPANY_NAME', 'Turnar');
}
if (!defined('APP_LOGO_PATH')) {
    define('APP_LOGO_PATH', APP_BASE_URL . '/assets/img/logo.png');
}
if (!defined('APP_FAVICON_PATH')) {
    define('APP_FAVICON_PATH', APP_BASE_URL . '/assets/img/favicon.ico');
}

// --------------------------------------------------
// SESSIONE
// --------------------------------------------------
if (!defined('SESSION_INACTIVITY_LIMIT')) {
    define('SESSION_INACTIVITY_LIMIT', 7200);
}

@ini_set('session.gc_maxlifetime', (string)SESSION_INACTIVITY_LIMIT);
@ini_set('session.cookie_lifetime', '0');
@session_name('TURNARSESSID');

// --------------------------------------------------
// RUOLI UFFICIALI TURNAR
// --------------------------------------------------
if (!defined('ROLE_USER')) {
    define('ROLE_USER', 'user');
}
if (!defined('ROLE_MANAGER')) {
    define('ROLE_MANAGER', 'manager');
}
if (!defined('ROLE_MASTER')) {
    define('ROLE_MASTER', 'master');
}

if (!defined('ROLE_OPERATOR')) {
    define('ROLE_OPERATOR', ROLE_USER);
}
if (!defined('ROLE_DIRECTOR')) {
    define('ROLE_DIRECTOR', ROLE_MANAGER);
}

// --------------------------------------------------
// SCOPE UFFICIALI TURNAR
// --------------------------------------------------
if (!defined('SCOPE_SELF')) {
    define('SCOPE_SELF', 'self');
}
if (!defined('SCOPE_TEAM')) {
    define('SCOPE_TEAM', 'team');
}
if (!defined('SCOPE_GLOBAL')) {
    define('SCOPE_GLOBAL', 'global');
}

if (!defined('SCOPE_AREA')) {
    define('SCOPE_AREA', SCOPE_TEAM);
}

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
// HELPER URL / PATH
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
// HELPER BRANDING
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
// HELPER MODULI SOFTWARE
// --------------------------------------------------
if (!function_exists('turnar_default_modules_matrix')) {
    function turnar_default_modules_matrix(): array
    {
        $defaults = [];

        foreach (app_modules() as $key => $enabled) {
            $defaults[$key] = [
                'web'  => !empty($enabled) ? 1 : 0,
                'app'  => in_array($key, ['dashboard', 'assignments', 'calendar', 'communications', 'mobile', 'push'], true) ? 1 : 0,
                'menu' => !empty($enabled) ? 1 : 0,
            ];
        }

        $defaults['settings'] = ['web' => 1, 'app' => 0, 'menu' => 1];

        return $defaults;
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
        if ($raw === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        foreach ($defaults as $key => $defaultState) {
            if (!isset($decoded[$key]) || !is_array($decoded[$key])) {
                $decoded[$key] = $defaultState;
                continue;
            }

            $decoded[$key] = [
                'web'  => array_key_exists('web', $decoded[$key]) ? (int)!empty($decoded[$key]['web']) : (int)$defaultState['web'],
                'app'  => array_key_exists('app', $decoded[$key]) ? (int)!empty($decoded[$key]['app']) : (int)$defaultState['app'],
                'menu' => array_key_exists('menu', $decoded[$key]) ? (int)!empty($decoded[$key]['menu']) : (int)$defaultState['menu'],
            ];
        }

        $decoded['settings'] = ['web' => 1, 'app' => 0, 'menu' => 1];

        return $decoded;
    }
}

if (!function_exists('module_state')) {
    function module_state(string $key): array
    {
        $key = trim($key);
        $matrix = turnar_modules_matrix();

        if ($key === '') {
            return ['web' => 0, 'app' => 0, 'menu' => 0];
        }

        if ($key === 'settings') {
            return ['web' => 1, 'app' => 0, 'menu' => 1];
        }

        $defaults = turnar_default_modules_matrix();
        $state = $matrix[$key] ?? ($defaults[$key] ?? ['web' => 1, 'app' => 0, 'menu' => 1]);

        return [
            'web'  => !empty($state['web']) ? 1 : 0,
            'app'  => !empty($state['app']) ? 1 : 0,
            'menu' => !empty($state['menu']) ? 1 : 0,
        ];
    }
}

if (!function_exists('module_enabled')) {
    function module_enabled(string $key): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }

        if (function_exists('is_master') && is_master()) {
            return true;
        }

        if ($key === 'settings') {
            return true;
        }

        return !empty(module_state($key)['web']);
    }
}

if (!function_exists('module_app_enabled')) {
    function module_app_enabled(string $key): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }

        if (function_exists('is_master') && is_master()) {
            return true;
        }

        return !empty(module_state($key)['app']);
    }
}

if (!function_exists('module_menu_visible')) {
    function module_menu_visible(string $key): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }

        if (function_exists('is_master') && is_master()) {
            return true;
        }

        if ($key === 'settings') {
            return true;
        }

        $state = module_state($key);
        return !empty($state['web']) && !empty($state['menu']);
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

        if ($role === 'operator') {
            $role = ROLE_USER;
        }

        if ($role === 'director') {
            $role = ROLE_MANAGER;
        }

        return in_array($role, role_values(), true) ? $role : ROLE_USER;
    }
}

if (!function_exists('normalize_scope')) {
    function normalize_scope(?string $scope): string
    {
        $scope = strtolower(trim((string)$scope));

        if ($scope === 'area') {
            $scope = SCOPE_TEAM;
        }

        return in_array($scope, scope_values(), true) ? $scope : SCOPE_SELF;
    }
}

if (!function_exists('role_label')) {
    function role_label(?string $role): string
    {
        $role = normalize_role($role);

        return match ($role) {
            ROLE_MASTER  => 'Master',
            ROLE_MANAGER => 'Manager',
            default      => 'User',
        };
    }
}

if (!function_exists('scope_label')) {
    function scope_label(?string $scope): string
    {
        $scope = normalize_scope($scope);

        return match ($scope) {
            SCOPE_GLOBAL => 'Global',
            SCOPE_TEAM   => 'Team',
            default      => 'Self',
        };
    }
}
