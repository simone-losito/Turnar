-- database/migrations/2026_04_28_operator_badge_pro.sql
-- Turnar - badge/tesserino digitale pro
-- Compatibile con MySQL/MariaDB XAMPP

-- badge_token
SET @col1 := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dipendenti'
    AND COLUMN_NAME = 'badge_token'
);
SET @sql1 := IF(@col1 = 0,
    'ALTER TABLE dipendenti ADD COLUMN badge_token VARCHAR(80) NULL AFTER foto',
    'SELECT "badge_token già esistente"');
PREPARE stmt1 FROM @sql1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- badge_enabled
SET @col2 := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dipendenti'
    AND COLUMN_NAME = 'badge_enabled'
);
SET @sql2 := IF(@col2 = 0,
    'ALTER TABLE dipendenti ADD COLUMN badge_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER badge_token',
    'SELECT "badge_enabled già esistente"');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- badge_expires_at
SET @col3 := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dipendenti'
    AND COLUMN_NAME = 'badge_expires_at'
);
SET @sql3 := IF(@col3 = 0,
    'ALTER TABLE dipendenti ADD COLUMN badge_expires_at DATE NULL AFTER badge_enabled',
    'SELECT "badge_expires_at già esistente"');
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- index badge_token
SET @idx := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dipendenti'
    AND INDEX_NAME = 'uq_dipendenti_badge_token'
);
SET @sql4 := IF(@idx = 0,
    'CREATE UNIQUE INDEX uq_dipendenti_badge_token ON dipendenti (badge_token)',
    'SELECT "Index già esistente"');
PREPARE stmt4 FROM @sql4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;
