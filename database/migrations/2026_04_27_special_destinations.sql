-- database/migrations/2026_04_27_special_destinations.sql
-- Turnar - completamento destinazioni speciali
-- Eseguibile più volte in modo sicuro su MySQL/MariaDB recenti.

-- 1) Colonne logiche sulle destinazioni/cantieri
ALTER TABLE cantieri
    ADD COLUMN IF NOT EXISTS is_special TINYINT(1) NOT NULL DEFAULT 0 AFTER pausa_pranzo,
    ADD COLUMN IF NOT EXISTS special_type VARCHAR(30) NULL DEFAULT NULL AFTER is_special,
    ADD COLUMN IF NOT EXISTS counts_as_work TINYINT(1) NOT NULL DEFAULT 1 AFTER special_type,
    ADD COLUMN IF NOT EXISTS counts_as_absence TINYINT(1) NOT NULL DEFAULT 0 AFTER counts_as_work;

-- 2) Indici utili per dashboard/report
CREATE INDEX IF NOT EXISTS idx_cantieri_special ON cantieri (is_special, special_type);
CREATE INDEX IF NOT EXISTS idx_cantieri_absence ON cantieri (counts_as_absence);
CREATE INDEX IF NOT EXISTS idx_cantieri_work ON cantieri (counts_as_work);

-- 3) Normalizzazione automatica delle destinazioni speciali più comuni
--    Se hai già creato Ferie / Permessi / Malattia / Corsi come cantieri, vengono riconosciuti.
UPDATE cantieri
SET
    is_special = 1,
    special_type = 'ferie',
    counts_as_work = 0,
    counts_as_absence = 1,
    visibile_calendario = 1,
    attivo = 1
WHERE LOWER(TRIM(commessa)) IN ('ferie', 'feria');

UPDATE cantieri
SET
    is_special = 1,
    special_type = 'permesso',
    counts_as_work = 0,
    counts_as_absence = 1,
    visibile_calendario = 1,
    attivo = 1
WHERE LOWER(TRIM(commessa)) IN ('permessi', 'permesso');

UPDATE cantieri
SET
    is_special = 1,
    special_type = 'malattia',
    counts_as_work = 0,
    counts_as_absence = 1,
    visibile_calendario = 1,
    attivo = 1
WHERE LOWER(TRIM(commessa)) IN ('malattia');

UPDATE cantieri
SET
    is_special = 1,
    special_type = 'corso',
    counts_as_work = 1,
    counts_as_absence = 0,
    visibile_calendario = 1,
    attivo = 1
WHERE LOWER(TRIM(commessa)) IN ('corsi', 'corso', 'corsi di formazione', 'corso di formazione', 'formazione');

-- 4) Sicurezza logica: una destinazione non deve valere insieme lavoro e assenza.
UPDATE cantieri
SET counts_as_work = 0
WHERE is_special = 1 AND counts_as_absence = 1;

-- 5) Le destinazioni normali restano lavoro operativo standard.
UPDATE cantieri
SET
    special_type = NULL,
    counts_as_work = 1,
    counts_as_absence = 0
WHERE is_special = 0;
