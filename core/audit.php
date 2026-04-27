<?php
// core/audit.php
// Helper centrale audit Turnar

require_once __DIR__ . '/helpers.php';

if (!function_exists('audit_log')) {
    function audit_log(
        string $action,
        ?string $description = null,
        ?string $entityType = null,
        $entityId = null,
        array $metadata = []
    ): bool {
        $action = trim($action);
        if ($action === '') {
            return false;
        }

        try {
            $db = db_connect();

            $check = $db->query("SHOW TABLES LIKE 'audit_log'");
            if (!$check || $check->num_rows === 0) {
                return false;
            }

            $userId = function_exists('auth_id') ? (int)(auth_id() ?? 0) : 0;
            $userLabel = function_exists('current_user_label') ? current_user_label() : 'Sistema';
            $entityIdString = $entityId === null ? null : (string)$entityId;
            $metadataJson = !empty($metadata)
                ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;
            $ip = function_exists('client_ip') ? client_ip() : null;
            $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

            $stmt = $db->prepare("
                INSERT INTO audit_log
                (user_id, user_label, action, entity_type, entity_id, description, metadata_json, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                return false;
            }

            $stmt->bind_param(
                'issssssss',
                $userId,
                $userLabel,
                $action,
                $entityType,
                $entityIdString,
                $description,
                $metadataJson,
                $ip,
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

if (!function_exists('audit_log_turnar')) {
    function audit_log_turnar(string $action, string $description, array $metadata = []): bool
    {
        return audit_log($action, $description, 'turnar', null, $metadata);
    }
}
