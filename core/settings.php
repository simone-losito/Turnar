<?php
// core/settings.php
// Gestione impostazioni dinamiche Turnar (DB + fallback)

require_once __DIR__ . '/../core/helpers.php';

if (defined('TURNAR_SETTINGS_LOADED')) {
    return;
}
define('TURNAR_SETTINGS_LOADED', true);

$GLOBALS['turnar_settings_cache'] = null;

if (!function_exists('load_settings')) {
    function load_settings(): array
    {
        if (is_array($GLOBALS['turnar_settings_cache'])) {
            return $GLOBALS['turnar_settings_cache'];
        }

        $settings = [];
        try {
            $db = db_connect();
            $check = $db->query("SHOW TABLES LIKE 'settings'");
            if (!$check || $check->num_rows === 0) {
                return $GLOBALS['turnar_settings_cache'] = settings_defaults();
            }

            $res = $db->query("SELECT chiave, valore FROM settings");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $key = (string)($row['chiave'] ?? '');
                    if ($key !== '') {
                        $settings[$key] = (string)($row['valore'] ?? '');
                    }
                }
                $res->free();
            }
        } catch (Throwable $e) {
            return $GLOBALS['turnar_settings_cache'] = settings_defaults();
        }

        return $GLOBALS['turnar_settings_cache'] = array_merge(settings_defaults(), $settings);
    }
}

if (!function_exists('settings_defaults')) {
    function settings_defaults(): array
    {
        return [
            'app_name' => 'Turnar',
            'app_tagline' => 'Organizzazione turni e operatività',
            'app_version_visual' => '1.0.0',
            'theme_mode' => 'dark',
            'theme_primary_color' => '#6ea8ff',
            'theme_secondary_color' => '#8b5cf6',
            'company_logo_path' => '',
            'company_favicon_path' => '',
            'company_login_image_path' => '',
            'topbar_logo_mode' => 'logo_and_name',
            'default_shift_start' => '07:00',
            'default_shift_end' => '16:00',
            'enable_email' => '1',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'mail_from_address' => '',
            'mail_from_name' => 'Turnar',
            'enable_mobile' => '1',
            'mobile_notifications_enabled' => '1',
            'assignment_notify_mode' => 'app',
            'debug_mode' => '1',
            'module_dashboard' => '1',
            'module_operators' => '1',
            'module_destinations' => '1',
            'module_assignments' => '1',
            'module_calendar' => '1',
            'module_communications' => '1',
            'module_reports' => '1',
            'module_users' => '1',
            'module_settings' => '1',
            'module_mobile' => '1',
        ];
    }
}

if (!function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        $settings = load_settings();
        return $settings[$key] ?? $default;
    }
}

if (!function_exists('setting_string')) {
    function setting_string(string $key, string $default = ''): string
    {
        return (string)setting($key, $default);
    }
}

if (!function_exists('setting_bool')) {
    function setting_bool(string $key, bool $default = false): bool
    {
        $value = setting($key, $default ? '1' : '0');
        return in_array(strtolower((string)$value), ['1','true','yes','on'], true);
    }
}

if (!function_exists('setting_set')) {
    function setting_set(string $key, string $value): bool
    {
        try {
            $db = db_connect();
            $stmt = $db->prepare("INSERT INTO settings (chiave, valore) VALUES (?, ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)");
            if (!$stmt) return false;
            $stmt->bind_param('ss', $key, $value);
            $ok = $stmt->execute();
            $stmt->close();
            $GLOBALS['turnar_settings_cache'] = null;
            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('settings_save_many')) {
    function settings_save_many(array $data): bool
    {
        foreach ($data as $k => $v) {
            if (!setting_set((string)$k, (string)$v)) return false;
        }
        return true;
    }
}

if (!function_exists('app_name')) { function app_name(): string { return setting_string('app_name', 'Turnar'); } }
if (!function_exists('app_version')) { function app_version(): string { return setting_string('app_version_visual', '1.0.0'); } }
if (!function_exists('app_tagline')) { function app_tagline(): string { return setting_string('app_tagline', 'Organizzazione turni e operatività'); } }
if (!function_exists('app_theme_mode')) { function app_theme_mode(): string { return setting_string('theme_mode', 'dark'); } }
if (!function_exists('app_theme_primary')) { function app_theme_primary(): string { return setting_string('theme_primary_color', '#6ea8ff'); } }
if (!function_exists('app_theme_secondary')) { function app_theme_secondary(): string { return setting_string('theme_secondary_color', '#8b5cf6'); } }
if (!function_exists('app_company_logo')) { function app_company_logo(): string { return setting_string('company_logo_path', setting_string('company_logo', '')); } }
if (!function_exists('app_favicon')) { function app_favicon(): string { return setting_string('company_favicon_path', setting_string('app_favicon', '')); } }
if (!function_exists('app_login_image')) { function app_login_image(): string { return setting_string('company_login_image_path', ''); } }
if (!function_exists('app_topbar_logo_mode')) { function app_topbar_logo_mode(): string { return setting_string('topbar_logo_mode', 'logo_and_name'); } }

if (!function_exists('assignment_notify_mode')) {
    function assignment_notify_mode(): string
    {
        $mode = setting_string('assignment_notify_mode', 'app');
        return in_array($mode, ['app','email','both','none'], true) ? $mode : 'app';
    }
}
if (!function_exists('assignment_notify_app')) { function assignment_notify_app(): bool { return in_array(assignment_notify_mode(), ['app','both'], true); } }
if (!function_exists('assignment_notify_email')) { function assignment_notify_email(): bool { return in_array(assignment_notify_mode(), ['email','both'], true); } }

if (!function_exists('module_enabled')) {
    function module_enabled(string $moduleKey): bool
    {
        $moduleKey = trim($moduleKey);
        if ($moduleKey === '') return false;
        if ($moduleKey === 'settings') return true;
        return setting_bool('module_' . $moduleKey, true);
    }
}
