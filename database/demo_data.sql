-- =====================================
-- TURNAR - DEMO DATA COMPLETO
-- =====================================

SET FOREIGN_KEY_CHECKS=0;

-- =========================
-- RESET BASE (opzionale)
-- =========================
TRUNCATE users;
TRUNCATE dipendenti;
TRUNCATE cantieri;
TRUNCATE eventi_turni;
TRUNCATE settings;

-- =========================
-- DIPENDENTI
-- =========================
INSERT INTO dipendenti (
    id, nome, cognome, telefono, email,
    tipologia, livello, attivo
) VALUES
(1, 'Mario', 'Rossi', '0000000001', 'mario@demo.local', 'tecnico', 'base', 1),
(2, 'Luca', 'Bianchi', '0000000002', 'luca@demo.local', 'elettricista', 'medio', 1),
(3, 'Giulia', 'Verdi', '0000000003', 'giulia@demo.local', 'meccanico', 'senior', 1);

-- =========================
-- USERS (LOGIN)
-- password = admin123
-- =========================
INSERT INTO users (
    id, dipendente_id, role, scope,
    username, password_hash, email,
    is_active, can_login_web, can_login_app,
    is_administrative
) VALUES
(1, 1, 'master', 'global', 'admin', '$2y$10$wH8K1x6Y7Q5F7m2l0s6v7u6FzJrO0Vq1kzH8G9KzVZ7bPq8qJdZ2W', 'admin@turnar.local', 1, 1, 1, 1);

-- =========================
-- CANTIERI
-- =========================
INSERT INTO cantieri (
    id, commessa, cliente, indirizzo, comune,
    tipologia, stato, attivo,
    pausa_pranzo, is_special, counts_as_work, counts_as_absence
) VALUES
(1, 'Roma Centro', 'Cliente Demo', 'Via Roma 1', 'Roma', 'civile', 'in_corso', 1, 1.00, 0, 1, 0),
(2, 'Milano Nord', 'Cliente Demo', 'Via Milano 10', 'Milano', 'industriale', 'in_corso', 1, 0.50, 0, 1, 0),

-- SPECIALI
(3, 'FERIE', '', '', '', 'altro', 'attivo', 1, 0.00, 1, 0, 1),
(4, 'PERMESSI', '', '', '', 'altro', 'attivo', 1, 0.00, 1, 0, 1),
(5, 'MALATTIA', '', '', '', 'altro', 'attivo', 1, 0.00, 1, 0, 1),
(6, 'CORSI', '', '', '', 'altro', 'attivo', 1, 0.00, 1, 1, 0);

-- =========================
-- EVENTI TURNI
-- =========================
INSERT INTO eventi_turni (
    id, data, id_cantiere, id_dipendente,
    ora_inizio, ora_fine, is_capocantiere
) VALUES
(1, CURDATE(), 1, 1, '07:00', '16:00', 1),
(2, CURDATE(), 1, 2, '07:00', '16:00', 0),
(3, CURDATE(), 2, 3, '08:00', '17:00', 0),
(4, CURDATE(), 3, 1, '00:00', '00:00', 0);

-- =========================
-- SETTINGS BASE
-- =========================
INSERT INTO settings (chiave, valore) VALUES
('app_name', 'TURNAR'),
('timezone', 'Europe/Rome'),
('theme', 'dark');

SET FOREIGN_KEY_CHECKS=1;