-- database/migrations/2026_04_28_operator_photo.sql
-- Turnar - foto dipendente per tesserino digitale

ALTER TABLE dipendenti
    ADD COLUMN IF NOT EXISTS foto VARCHAR(255) NULL AFTER matricola;
