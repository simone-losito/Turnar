-- database/migrations/2026_04_28_operator_photo.sql
-- Turnar - foto dipendente per tesserino digitale
-- Compatibile con MySQL/MariaDB XAMPP senza ADD COLUMN IF NOT EXISTS

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dipendenti'
      AND COLUMN_NAME = 'foto'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE dipendenti ADD COLUMN foto VARCHAR(255) NULL AFTER matricola',
    'SELECT "Colonna dipendenti.foto già presente" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
