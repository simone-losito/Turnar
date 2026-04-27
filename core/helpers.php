<?php
// core/helpers.php
// Funzioni helper comuni di Turnar

require_once __DIR__ . '/../core/auth.php';

// Evita inclusioni multiple
if (defined('TURNAR_HELPERS_LOADED')) {
    return;
}
define('TURNAR_HELPERS_LOADED', true);

// --------------------------------------------------
// DATE / ORE
// --------------------------------------------------
if (!function_exists('now_datetime')) {
    function now_datetime(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('today_date')) {
    function today_date(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('format_date_it')) {
    function format_date_it(?string $dateIso): string
    {
        $dateIso = trim((string)$dateIso);
        if ($dateIso === '') {
            return '';
        }

        $ts = strtotime($dateIso);
        if ($ts === false) {
            return $dateIso;
        }

        return date('d/m/Y', $ts);
    }
}

if (!function_exists('format_time_it')) {
    function format_time_it(?string $timeValue): string
    {
        $timeValue = trim((string)$timeValue);
        if ($timeValue === '') {
            return '';
        }

        if ($timeValue === '24:00:00') {
            return '24:00';
        }

        $ts = strtotime($timeValue);
        if ($ts === false) {
            return $timeValue;
        }

        return date('H:i', $ts);
    }
}

if (!function_exists('format_datetime_it')) {
    function format_datetime_it(?string $dateTimeValue): string
    {
        $dateTimeValue = trim((string)$dateTimeValue);
        if ($dateTimeValue === '') {
            return '';
        }

        $ts = strtotime($dateTimeValue);
        if ($ts === false) {
            return $dateTimeValue;
        }

        return date('d/m/Y H:i', $ts);
    }
}

if (!function_exists('normalize_date_iso')) {
    function normalize_date_iso(?string $dateStr): ?string
    {
        $dateStr = trim((string)$dateStr);
        if ($dateStr === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $ts = strtotime($dateStr);
            return $ts === false ? null : date('Y-m-d', $ts);
        }

        $ts = strtotime($dateStr);
        return $ts === false ? null : date('Y-m-d', $ts);
    }
}

if (!function_exists('next_date_iso')) {
    function next_date_iso(string $dateIso): ?string
    {
        $ts = strtotime($dateIso . ' +1 day');
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }
}

// --------------------------------------------------
// STRINGHE
// --------------------------------------------------
if (!function_exists('str_starts_with_ci')) {
    function str_starts_with_ci(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return mb_strtolower(mb_substr($haystack, 0, mb_strlen($needle)), 'UTF-8') === mb_strtolower($needle, 'UTF-8');
    }
}

if (!function_exists('str_contains_ci')) {
    function str_contains_ci(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }
}

if (!function_exists('slugify')) {
    function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$value);
        $value = strtolower(trim((string)$value, '-'));

        return $value;
    }
}

// --------------------------------------------------
// ARRAY / REQUEST
// --------------------------------------------------
if (!function_exists('array_get')) {
    function array_get(array $source, string $key, $default = null)
    {
        return array_key_exists($key, $source) ? $source[$key] : $default;
    }
}

if (!function_exists('post')) {
    function post(string $key, $default = null)
    {
        return array_get($_POST, $key, $default);
    }
}

if (!function_exists('get')) {
    function get(string $key, $default = null)
    {
        return array_get($_GET, $key, $default);
    }
}

// --------------------------------------------------
// UTENTE / AUDIT READY
// --------------------------------------------------
if (!function_exists('current_user_label')) {
    function current_user_label(): string
    {
        if (!auth_check()) {
            return 'Guest';
        }

        return auth_display_name();
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): ?string
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value !== '') {
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $parts = explode(',', $value);
                    return trim($parts[0]);
                }
                return $value;
            }
        }

        return null;
    }
}

// --------------------------------------------------
// DESTINAZIONI PREFERITE
// --------------------------------------------------
if (!function_exists('get_user_favorite_destinations')) {
    function get_user_favorite_destinations(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $db = db_connect();

        $stmt = $db->prepare("
            SELECT destination_id
            FROM user_favorite_destinations
            WHERE user_id = ?
            ORDER BY destination_id ASC
        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['destination_id'];
        }

        $stmt->close();

        return $ids;
    }
}

if (!function_exists('is_user_favorite_destination')) {
    function is_user_favorite_destination(int $userId, int $destinationId): bool
    {
        if ($userId <= 0 || $destinationId <= 0) {
            return false;
        }

        $db = db_connect();

        $stmt = $db->prepare("
            SELECT id
            FROM user_favorite_destinations
            WHERE user_id = ? AND destination_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $userId, $destinationId);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->fetch_assoc();
        $stmt->close();

        return (bool)$exists;
    }
}

if (!function_exists('add_user_favorite_destination')) {
    function add_user_favorite_destination(int $userId, int $destinationId): bool
    {
        if ($userId <= 0 || $destinationId <= 0) {
            return false;
        }

        $db = db_connect();

        $stmt = $db->prepare("
            INSERT IGNORE INTO user_favorite_destinations (user_id, destination_id)
            VALUES (?, ?)
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $userId, $destinationId);
        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }
}

if (!function_exists('remove_user_favorite_destination')) {
    function remove_user_favorite_destination(int $userId, int $destinationId): bool
    {
        if ($userId <= 0 || $destinationId <= 0) {
            return false;
        }

        $db = db_connect();

        $stmt = $db->prepare("
            DELETE FROM user_favorite_destinations
            WHERE user_id = ? AND destination_id = ?
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $userId, $destinationId);
        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }
}

if (!function_exists('toggle_user_favorite_destination')) {
    function toggle_user_favorite_destination(int $userId, int $destinationId): bool
    {
        if ($userId <= 0 || $destinationId <= 0) {
            return false;
        }

        if (is_user_favorite_destination($userId, $destinationId)) {
            return remove_user_favorite_destination($userId, $destinationId);
        }

        return add_user_favorite_destination($userId, $destinationId);
    }
}