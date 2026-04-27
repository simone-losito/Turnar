<?php
// core/push.php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/push.php';

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

if (!function_exists('app_push_vapid_public_key')) {
    function app_push_vapid_public_key(): string
    {
        return trim((string)TURNAR_PUSH_PUBLIC_KEY);
    }
}

if (!function_exists('app_push_is_configured')) {
    function app_push_is_configured(): bool
    {
        return
            trim((string)TURNAR_PUSH_PUBLIC_KEY) !== '' &&
            trim((string)TURNAR_PUSH_PRIVATE_KEY) !== '' &&
            trim((string)TURNAR_PUSH_SUBJECT) !== '';
    }
}

if (!function_exists('save_push_subscription')) {
    function save_push_subscription(
        int $dipendenteId,
        string $endpoint,
        string $publicKey,
        string $authToken,
        ?string $contentEncoding = null,
        ?string $userAgent = null
    ): bool {
        if ($dipendenteId <= 0) {
            return false;
        }

        $endpoint = trim($endpoint);
        $publicKey = trim($publicKey);
        $authToken = trim($authToken);
        $contentEncoding = trim((string)$contentEncoding);
        $userAgent = trim((string)$userAgent);

        if ($endpoint === '' || $publicKey === '' || $authToken === '') {
            return false;
        }

        try {
            $db = db_connect();

            $check = $db->query("SHOW TABLES LIKE 'app_push_subscriptions'");
            if (!$check || $check->num_rows === 0) {
                return false;
            }

            $endpointHash = hash('sha256', $endpoint);

            $stmt = $db->prepare("
                INSERT INTO app_push_subscriptions
                (
                    dipendente_id,
                    endpoint,
                    endpoint_hash,
                    public_key,
                    auth_token,
                    content_encoding,
                    user_agent,
                    is_active,
                    last_seen_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    dipendente_id = VALUES(dipendente_id),
                    endpoint = VALUES(endpoint),
                    public_key = VALUES(public_key),
                    auth_token = VALUES(auth_token),
                    content_encoding = VALUES(content_encoding),
                    user_agent = VALUES(user_agent),
                    is_active = 1,
                    last_seen_at = NOW()
            ");

            if (!$stmt) {
                return false;
            }

            $stmt->bind_param(
                'issssss',
                $dipendenteId,
                $endpoint,
                $endpointHash,
                $publicKey,
                $authToken,
                $contentEncoding,
                $userAgent
            );

            $ok = $stmt->execute();
            $stmt->close();

            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('disable_push_subscription_by_endpoint')) {
    function disable_push_subscription_by_endpoint(string $endpoint): bool
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return false;
        }

        try {
            $db = db_connect();

            $check = $db->query("SHOW TABLES LIKE 'app_push_subscriptions'");
            if (!$check || $check->num_rows === 0) {
                return false;
            }

            $endpointHash = hash('sha256', $endpoint);

            $stmt = $db->prepare("
                UPDATE app_push_subscriptions
                SET is_active = 0, updated_at = NOW()
                WHERE endpoint_hash = ?
            ");

            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('s', $endpointHash);
            $ok = $stmt->execute();
            $stmt->close();

            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('get_push_subscriptions_for_dipendente')) {
    function get_push_subscriptions_for_dipendente(int $dipendenteId): array
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
                SELECT id, endpoint, public_key, auth_token, content_encoding
                FROM app_push_subscriptions
                WHERE dipendente_id = ? AND is_active = 1
                ORDER BY id DESC
            ");

            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('i', $dipendenteId);
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

if (!function_exists('send_browser_push_to_dipendente')) {
    function send_browser_push_to_dipendente(
        int $dipendenteId,
        string $title,
        string $body,
        string $url = 'index.php'
    ): bool {
        if ($dipendenteId <= 0) {
            return false;
        }

        if (!app_push_is_configured()) {
            return false;
        }

        if (
            !class_exists('\\Minishlink\\WebPush\\WebPush') ||
            !class_exists('\\Minishlink\\WebPush\\Subscription')
        ) {
            return false;
        }

        $subscriptions = get_push_subscriptions_for_dipendente($dipendenteId);
        if (empty($subscriptions)) {
            return false;
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject' => trim((string)TURNAR_PUSH_SUBJECT),
                    'publicKey' => trim((string)TURNAR_PUSH_PUBLIC_KEY),
                    'privateKey' => trim((string)TURNAR_PUSH_PRIVATE_KEY),
                ],
            ];

            $webPush = new \Minishlink\WebPush\WebPush($auth);

            $payload = json_encode([
                'title' => $title,
                'body'  => $body,
                'url'   => $url,
                'icon'  => 'icon.php?size=192',
                'badge' => 'icon.php?size=192',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($subscriptions as $sub) {
                $endpoint = trim((string)($sub['endpoint'] ?? ''));
                $publicKey = trim((string)($sub['public_key'] ?? ''));
                $authToken = trim((string)($sub['auth_token'] ?? ''));
                $contentEncoding = trim((string)($sub['content_encoding'] ?? ''));

                if ($endpoint === '' || $publicKey === '' || $authToken === '') {
                    continue;
                }

                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $endpoint,
                    'publicKey' => $publicKey,
                    'authToken' => $authToken,
                    'contentEncoding' => $contentEncoding !== '' ? $contentEncoding : 'aesgcm',
                ]);

                $webPush->queueNotification($subscription, $payload);
            }

            $sent = false;

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent = true;
                } else {
                    $endpoint = '';
                    try {
                        $endpoint = $report->getRequest()->getUri()->__toString();
                    } catch (Throwable $e) {
                        $endpoint = '';
                    }

                    if ($endpoint !== '') {
                        disable_push_subscription_by_endpoint($endpoint);
                    }
                }
            }

            return $sent;
        } catch (Throwable $e) {
            return false;
        }
    }
}