<?php
// core/app_push.php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/settings.php';

if (!function_exists('app_push_is_supported')) {
    function app_push_is_supported(): bool
    {
        return class_exists('\Minishlink\WebPush\WebPush') && class_exists('\Minishlink\WebPush\Subscription');
    }
}

if (!function_exists('app_push_public_key')) {
    function app_push_public_key(): string
    {
        return trim((string)setting('push_vapid_public_key', ''));
    }
}

if (!function_exists('app_push_private_key')) {
    function app_push_private_key(): string
    {
        return trim((string)setting('push_vapid_private_key', ''));
    }
}

if (!function_exists('app_push_subject')) {
    function app_push_subject(): string
    {
        $subject = trim((string)setting('push_vapid_subject', 'mailto:admin@example.com'));
        return $subject !== '' ? $subject : 'mailto:admin@example.com';
    }
}

if (!function_exists('app_push_keys_ready')) {
    function app_push_keys_ready(): bool
    {
        return app_push_public_key() !== '' && app_push_private_key() !== '';
    }
}

if (!function_exists('app_push_can_register_browser')) {
    function app_push_can_register_browser(): bool
    {
        return app_push_keys_ready();
    }
}

if (!function_exists('app_push_save_subscription')) {
    function app_push_save_subscription(int $dipendenteId, array $subscription, string $userAgent = ''): bool
    {
        if ($dipendenteId <= 0) {
            return false;
        }

        $endpoint = trim((string)($subscription['endpoint'] ?? ''));
        $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
        $p256dh = trim((string)($keys['p256dh'] ?? ''));
        $auth = trim((string)($keys['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return false;
        }

        try {
            $db = db_connect();

            $check = $db->query("SHOW TABLES LIKE 'app_push_subscriptions'");
            if (!$check || $check->num_rows === 0) {
                return false;
            }

            $stmt = $db->prepare("
                INSERT INTO app_push_subscriptions
                    (dipendente_id, endpoint, p256dh, auth, user_agent)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    dipendente_id = VALUES(dipendente_id),
                    p256dh = VALUES(p256dh),
                    auth = VALUES(auth),
                    user_agent = VALUES(user_agent),
                    updated_at = CURRENT_TIMESTAMP
            ");

            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('issss', $dipendenteId, $endpoint, $p256dh, $auth, $userAgent);
            $ok = $stmt->execute();
            $stmt->close();

            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('app_push_delete_subscription_by_endpoint')) {
    function app_push_delete_subscription_by_endpoint(string $endpoint, int $dipendenteId = 0): bool
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return false;
        }

        try {
            $db = db_connect();

            if ($dipendenteId > 0) {
                $stmt = $db->prepare("
                    DELETE FROM app_push_subscriptions
                    WHERE endpoint = ? AND dipendente_id = ?
                ");
                if (!$stmt) {
                    return false;
                }
                $stmt->bind_param('si', $endpoint, $dipendenteId);
            } else {
                $stmt = $db->prepare("
                    DELETE FROM app_push_subscriptions
                    WHERE endpoint = ?
                ");
                if (!$stmt) {
                    return false;
                }
                $stmt->bind_param('s', $endpoint);
            }

            $ok = $stmt->execute();
            $stmt->close();

            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('app_push_list_for_dipendente')) {
    function app_push_list_for_dipendente(int $dipendenteId): array
    {
        if ($dipendenteId <= 0) {
            return [];
        }

        try {
            $db = db_connect();

            $check = $db->query("SHOW TABLES LIKE 'app_push_subscriptions'");
            if (!$check || $check->num_rows === 0) {
                return [];
            }

            $stmt = $db->prepare("
                SELECT endpoint, p256dh, auth
                FROM app_push_subscriptions
                WHERE dipendente_id = ?
                ORDER BY updated_at DESC, id DESC
            ");

            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('i', $dipendenteId);
            $stmt->execute();
            $res = $stmt->get_result();

            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }

            $stmt->close();
            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('app_push_send_to_dipendente')) {
    function app_push_send_to_dipendente(
        int $dipendenteId,
        string $title,
        string $body,
        string $url = '',
        array $extra = []
    ): bool {
        if ($dipendenteId <= 0 || !app_push_is_supported() || !app_push_keys_ready()) {
            return false;
        }

        $subscriptions = app_push_list_for_dipendente($dipendenteId);
        if (empty($subscriptions)) {
            return false;
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject' => app_push_subject(),
                    'publicKey' => app_push_public_key(),
                    'privateKey' => app_push_private_key(),
                ],
            ];

            $webPush = new \Minishlink\WebPush\WebPush($auth);

            $payload = json_encode([
                'title' => $title,
                'body'  => $body,
                'url'   => $url,
                'icon'  => 'icon.php?size=192',
                'badge' => 'icon.php?size=192',
                'data'  => $extra,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($subscriptions as $sub) {
                $endpoint = trim((string)($sub['endpoint'] ?? ''));
                $p256dh = trim((string)($sub['p256dh'] ?? ''));
                $authKey = trim((string)($sub['auth'] ?? ''));

                if ($endpoint === '' || $p256dh === '' || $authKey === '') {
                    continue;
                }

                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $endpoint,
                    'keys' => [
                        'p256dh' => $p256dh,
                        'auth' => $authKey,
                    ],
                ]);

                $webPush->queueNotification($subscription, $payload);
            }

            $success = false;

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();

                if ($report->isSuccess()) {
                    $success = true;
                } else {
                    $reason = method_exists($report, 'getReason') ? (string)$report->getReason() : '';
                    if (stripos($reason, '410') !== false || stripos($reason, '404') !== false) {
                        app_push_delete_subscription_by_endpoint($endpoint);
                    }
                }
            }

            return $success;
        } catch (Throwable $e) {
            return false;
        }
    }
}