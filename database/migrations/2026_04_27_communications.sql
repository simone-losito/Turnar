-- database/migrations/2026_04_27_communications.sql
-- Turnar - modulo comunicazioni app/email con allegati

CREATE TABLE IF NOT EXISTS communications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_user_id INT UNSIGNED NULL,
    sender_label VARCHAR(190) NULL,
    subject VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    channel_app TINYINT(1) NOT NULL DEFAULT 1,
    channel_email TINYINT(1) NOT NULL DEFAULT 0,
    target_mode ENUM('all','selected') NOT NULL DEFAULT 'selected',
    status ENUM('draft','sent') NOT NULL DEFAULT 'sent',
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_communications_status (status),
    KEY idx_communications_sent_at (sent_at),
    KEY idx_communications_sender (sender_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_recipients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    communication_id INT UNSIGNED NOT NULL,
    dipendente_id INT UNSIGNED NOT NULL,
    recipient_label VARCHAR(190) NULL,
    recipient_email VARCHAR(190) NULL,
    app_notification_created TINYINT(1) NOT NULL DEFAULT 0,
    push_sent TINYINT(1) NOT NULL DEFAULT 0,
    email_sent TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_communication_recipient (communication_id, dipendente_id),
    KEY idx_communication_recipients_dipendente (dipendente_id),
    CONSTRAINT fk_communication_recipients_communication
        FOREIGN KEY (communication_id) REFERENCES communications(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    communication_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_communication_attachments_communication (communication_id),
    CONSTRAINT fk_communication_attachments_communication
        FOREIGN KEY (communication_id) REFERENCES communications(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
