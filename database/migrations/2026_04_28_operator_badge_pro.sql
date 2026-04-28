-- database/migrations/2026_04_28_operator_badge_pro.sql
-- Turnar - badge/tesserino digitale pro

ALTER TABLE dipendenti
    ADD COLUMN IF NOT EXISTS badge_token VARCHAR(80) NULL AFTER foto,
    ADD COLUMN IF NOT EXISTS badge_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER badge_token,
    ADD COLUMN IF NOT EXISTS badge_expires_at DATE NULL AFTER badge_enabled;

CREATE UNIQUE INDEX IF NOT EXISTS uq_dipendenti_badge_token ON dipendenti (badge_token);
