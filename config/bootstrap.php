<?php
// config/bootstrap.php
// Bootstrap centrale di Turnar

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/database.php';

// Evita inclusioni multiple
if (defined('TURNAR_BOOTSTRAP_LOADED')) {
    return;
}
define('TURNAR_BOOTSTRAP_LOADED', true);

// --------------------------------------------------
// SESSIONE
// --------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timeout inattività sessione
if (session_status() === PHP_SESSION_ACTIVE) {
    $now = time();

    if (isset($_SESSION['TURNAR_LAST_ACTIVITY'])) {
        $lastActivity = (int)$_SESSION['TURNAR_LAST_ACTIVITY'];

        if (($now - $lastActivity) > SESSION_INACTIVITY_LIMIT) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    $now - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();
            session_start();
        }
    }

    $_SESSION['TURNAR_LAST_ACTIVITY'] = $now;
}

// --------------------------------------------------
// HELPER BASE
// --------------------------------------------------
if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}

if (!function_exists('is_get')) {
    function is_get(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path = ''): void
    {
        header('Location: ' . app_url($path));
        exit;
    }
}

if (!function_exists('redirect_mobile')) {
    function redirect_mobile(string $path = ''): void
    {
        header('Location: ' . mobile_url($path));
        exit;
    }
}

if (!function_exists('json_response')) {
    function json_response(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// --------------------------------------------------
// MODULI
// --------------------------------------------------
if (!function_exists('module_access_denied')) {
    function module_access_denied(string $moduleKey): void
    {
        http_response_code(403);
        exit('Modulo non attivo: ' . h($moduleKey));
    }
}

if (!function_exists('require_module')) {
    function require_module(string $moduleKey): void
    {
        $moduleKey = trim($moduleKey);

        if ($moduleKey === '') {
            module_access_denied('sconosciuto');
        }

        if (!function_exists('module_enabled') || !module_enabled($moduleKey)) {
            module_access_denied($moduleKey);
        }
    }
}

if (!function_exists('require_active_module')) {
    function require_active_module(?string $moduleKey): void
    {
        $moduleKey = trim((string)$moduleKey);

        if ($moduleKey === '') {
            return;
        }

        require_module($moduleKey);
    }
}
