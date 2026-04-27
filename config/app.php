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

// URL base lato browser
if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', '/Turnar');
}

if (!defined('APP_WEB_PATH')) {
    define('APP_WEB_PATH', APP_BASE_URL);
}

/*
|--------------------------------------------------------------------------
| APP MOBILE
|--------------------------------------------------------------------------
| La web-app/PWA Turnar è ospitata nella cartella /app
| Quindi la pillola "Mobile" e tutti gli helper mobile_url()
| devono puntare a /Turnar/app e non a /Turnar/mobile
*/
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
    define('SESSION_INACTIVITY_LIMIT', 7200); // 2 ore
}

@ini_set('session.gc_maxlifetime', (string)SESSION_INACTIVITY_LIMIT);
@ini_set('session.cookie_lifetime', '0');

// Nome sessione dedicato a Turnar
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

// Alias legacy per compatibilità con file già scritti
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

// Alias legacy per compatibilità
if (!defined('SCOPE_AREA')) {
    define('SCOPE_AREA', SCOPE_TEAM);
}

// --------------------------------------------------
// MODULI BASE ATTIVI
// --------------------------------------------------
$GLOBALS['TURNAR_MODULES'] = [
    'dashboard'      => true,
    'users'          => true,
    'operators'      => true,
    'destinations'   => true,
    'assignments'    => true,
    'calendar'       => true,
    'notifications'  => true,
    'communications' => true,
    'reports'        => true,
    'settings'       => true,
    'mobile'         => true,
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
        return APP_DISPLAY_NAME;
    }
}

if (!function_exists('app_version')) {
    function app_version(): string
    {
        return APP_VERSION;
    }
}

if (!function_exists('app_modules')) {
    function app_modules(): array
    {
        return is_array($GLOBALS['TURNAR_MODULES'] ?? null) ? $GLOBALS['TURNAR_MODULES'] : [];
    }
}

if (!function_exists('module_enabled')) {
    function module_enabled(string $key): bool
    {
        $mods = app_modules();
        return !empty($mods[$key]);
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