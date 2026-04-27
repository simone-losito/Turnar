-- database/migrations/2026_04_27_push_subscriptions.sql
-- Turnar - tabella subscription push browser/PWA

CREATE TABLE IF NOT EXISTS app_push_subscriptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dipendente_id INT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    public_key TEXT NOT NULL,
    auth_token TEXT NOT NULL,
    content_encoding VARCHAR(32) NULL DEFAULT NULL,
    user_agent TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_app_push_endpoint_hash (endpoint_hash),
    KEY idx_app_push_dipendente_active (dipendente_id, is_active),
    KEY idx_app_push_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
