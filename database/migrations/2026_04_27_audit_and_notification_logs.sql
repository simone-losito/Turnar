-- database/migrations/2026_04_27_audit_and_notification_logs.sql
-- Turnar - audit log generale + storico invio notifiche

CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    user_label VARCHAR(190) NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(120) NULL,
    entity_id VARCHAR(80) NULL,
    description TEXT NULL,
    metadata_json LONGTEXT NULL,
    ip_address VARCHAR(80) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_created_at (created_at),
    KEY idx_audit_user_id (user_id),
    KEY idx_audit_action (action),
    KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_send_batches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sent_by_user_id INT UNSIGNED NULL,
    sent_by_label VARCHAR(190) NULL,
    date_ref DATE NOT NULL,
    total_operators INT UNSIGNED NOT NULL DEFAULT 0,
    total_turns INT UNSIGNED NOT NULL DEFAULT 0,
    total_conflicts INT UNSIGNED NOT NULL DEFAULT 0,
    forced_send TINYINT(1) NOT NULL DEFAULT 0,
    app_notifications_created INT UNSIGNED NOT NULL DEFAULT 0,
    push_notifications_sent INT UNSIGNED NOT NULL DEFAULT 0,
    ip_address VARCHAR(80) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notification_batches_date (date_ref),
    KEY idx_notification_batches_created (created_at),
    KEY idx_notification_batches_user (sent_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_send_recipients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id INT UNSIGNED NOT NULL,
    dipendente_id INT UNSIGNED NOT NULL,
    operator_label VARCHAR(190) NULL,
    app_notification_created TINYINT(1) NOT NULL DEFAULT 0,
    push_notification_sent TINYINT(1) NOT NULL DEFAULT 0,
    had_conflicts TINYINT(1) NOT NULL DEFAULT 0,
    message_preview TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notification_recipients_batch (batch_id),
    KEY idx_notification_recipients_dipendente (dipendente_id),
    CONSTRAINT fk_notification_recipients_batch
        FOREIGN KEY (batch_id) REFERENCES notification_send_batches(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
