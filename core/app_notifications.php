<?php
// core/app_notifications.php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

if (!function_exists('app_notification_create')) {
    function app_notification_create(
        int $dipendenteId,
        string $titolo,
        string $messaggio,
        string $tipo = 'info',
        ?string $link = null
    ): bool {
        if ($dipendenteId <= 0) {
            return false;
        }

        $titolo = trim($titolo);
        $messaggio = trim($messaggio);
        $tipo = trim($tipo);

        if ($titolo === '' || $messaggio === '') {
            return false;
        }

        if (!in_array($tipo, ['info', 'success', 'warning', 'danger', 'turno'], true)) {
            $tipo = 'info';
        }

        try {
            $db = db_connect();

            $check = $db->query("SHOW TABLES LIKE 'app_notifications'");
            if (!$check || $check->num_rows === 0) {
                return false;
            }

            $stmt = $db->prepare("
                INSERT INTO app_notifications
                (dipendente_id, titolo, messaggio, tipo, link, is_read)
                VALUES (?, ?, ?, ?, ?, 0)
            ");

            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('issss', $dipendenteId, $titolo, $messaggio, $tipo, $link);
            $ok = $stmt->execute();
            $stmt->close();

            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('app_notification_list_for_dipendente')) {
    function app_notification_list_for_dipendente(int $dipendenteId, int $limit = 50): array
    {
        if ($dipendenteId <= 0) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        try {
            $db = db_connect();

            $stmt = $db->prepare("
                SELECT id, titolo, messaggio, tipo, link, is_read, created_at, read_at
                FROM app_notifications
                WHERE dipendente_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT ?
            ");

            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('ii', $dipendenteId, $limit);
            $stmt->execute();
            $res = $stmt->get_result();

            $items = [];
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }

            $stmt->close();

            return $items;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('app_notification_unread_count')) {
    function app_notification_unread_count(int $dipendenteId): int
    {
        if ($dipendenteId <= 0) {
            return 0;
        }

        try {
            $db = db_connect();

            $stmt = $db->prepare("
                SELECT COUNT(*) AS totale
                FROM app_notifications
                WHERE dipendente_id = ? AND is_read = 0
            ");

            if (!$stmt) {
                return 0;
            }

            $stmt->bind_param('i', $dipendenteId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            return (int)($row['totale'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('app_notification_mark_as_read')) {
    function app_notification_mark_as_read(int $notificationId, int $dipendenteId): bool
    {
        if ($notificationId <= 0 || $dipendenteId <= 0) {
            return false;
        }

        try {
            $db = db_connect();

            $stmt = $db->prepare("
                UPDATE app_notifications
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND dipendente_id = ?
            ");

            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('ii', $notificationId, $dipendenteId);
            $ok = $stmt->execute();
            $stmt->close();

            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('app_notification_mark_all_as_read')) {
    function app_notification_mark_all_as_read(int $dipendenteId): bool
    {
        if ($dipendenteId <= 0) {
            return false;
        }

        try {
            $db = db_connect();

            $stmt = $db->prepare("
                UPDATE app_notifications
                SET is_read = 1, read_at = NOW()
                WHERE dipendente_id = ? AND is_read = 0
            ");

            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('i', $dipendenteId);
            $ok = $stmt->execute();
            $stmt->close();

            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('send_app_notification')) {
    function send_app_notification(int $dipendenteId, string $messaggio, string $titolo = 'Turnar', string $tipo = 'info', ?string $link = null): bool
    {
        return app_notification_create($dipendenteId, $titolo, $messaggio, $tipo, $link);
    }
}