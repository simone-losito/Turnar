<?php
// core/settings.php
// Gestione impostazioni dinamiche Turnar (DB + fallback)

require_once __DIR__ . '/../core/helpers.php';

// Evita inclusioni multiple
if (defined('TURNAR_SETTINGS_LOADED')) {
    return;
}
define('TURNAR_SETTINGS_LOADED', true);

// Cache locale per evitare query ripetute
$GLOBALS['turnar_settings_cache'] = null;

// --------------------------------------------------
// CARICA TUTTE LE IMPOSTAZIONI
// --------------------------------------------------
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
                    $key = isset($row['chiave']) ? (string)$row['chiave'] : '';
                    if ($key === '') continue;

                    $settings[$key] = isset($row['valore']) ? (string)$row['valore'] : '';
                }
                $res->free();
            }
        } catch (Throwable $e) {
            return $GLOBALS['turnar_settings_cache'] = settings_defaults();
        }

        $settings = array_merge(settings_defaults(), $settings);

        return $GLOBALS['turnar_settings_cache'] = $settings;
    }
}

// --------------------------------------------------
// DEFAULT
// --------------------------------------------------
if (!function_exists('settings_defaults')) {
    function settings_defaults(): array
    {
        return [

            // =========================
            // GENERALI
            // =========================
            'app_name'           => 'Turnar',
            'app_tagline'        => 'Organizzazione turni e operatività',
            'app_version_visual' => '1.0.0',

            // =========================
            // TEMA
            // =========================
            'theme_mode'            => 'dark',
            'theme_primary_color'   => '#6ea8ff',
            'theme_secondary_color' => '#8b5cf6',

            // =========================
            // TURNI
            // =========================
            'default_shift_start' => '07:00',
            'default_shift_end'   => '16:00',

            // =========================
            // EMAIL
            // =========================
            'enable_email'     => '1',
            'smtp_host'        => '',
            'smtp_port'        => '587',
            'smtp_encryption'  => 'tls',
            'smtp_username'    => '',
            'smtp_password'    => '',
            'mail_from_address'=> '',
            'mail_from_name'   => 'Turnar',

            // =========================
            // MOBILE
            // =========================
            'enable_mobile'             => '1',
            'mobile_notifications_enabled' => '1',

            // =========================
            // 🔥 NOTIFICHE TURNI (NUOVO)
            // =========================
            'assignment_notify_mode' => 'app', 
            // valori:
            // app = solo notifiche app
            // email = solo email
            // both = entrambe
            // none = nessuna

            // =========================
            // DEBUG
            // =========================
            'debug_mode' => '1',
        ];
    }
}

// --------------------------------------------------
// GET GENERICI
// --------------------------------------------------
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
        return in_array((string)$value, ['1','true','yes','on'], true);
    }
}

// --------------------------------------------------
// SET
// --------------------------------------------------
if (!function_exists('setting_set')) {
    function setting_set(string $key, string $value): bool
    {
        try {
            $db = db_connect();

            $stmt = $db->prepare("
                INSERT INTO settings (chiave, valore)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE valore = VALUES(valore)
            ");

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
            if (!setting_set($k, (string)$v)) {
                return false;
            }
        }
        return true;
    }
}

// --------------------------------------------------
// HELPER APP
// --------------------------------------------------
if (!function_exists('app_name')) {
    function app_name(): string
    {
        return setting_string('app_name', 'Turnar');
    }
}

if (!function_exists('app_version')) {
    function app_version(): string
    {
        return setting_string('app_version_visual', '1.0.0');
    }
}

// --------------------------------------------------
// 🔥 HELPER NOTIFICHE TURNI
// --------------------------------------------------
if (!function_exists('assignment_notify_mode')) {
    function assignment_notify_mode(): string
    {
        $mode = setting_string('assignment_notify_mode', 'app');

        if (!in_array($mode, ['app','email','both','none'], true)) {
            return 'app';
        }

        return $mode;
    }
}

if (!function_exists('assignment_notify_app')) {
    function assignment_notify_app(): bool
    {
        return in_array(assignment_notify_mode(), ['app','both'], true);
    }
}

if (!function_exists('assignment_notify_email')) {
    function assignment_notify_email(): bool
    {
        return in_array(assignment_notify_mode(), ['email','both'], true);
    }
}

// --------------------------------------------------
// HELPER MODULI
// --------------------------------------------------
if (!function_exists('module_enabled')) {
    function module_enabled(string $moduleKey): bool
    {
        return true;
    }
}