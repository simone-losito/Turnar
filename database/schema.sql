-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Apr 27, 2026 alle 20:54
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `turnar`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `app_notifications`
--

CREATE TABLE `app_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `dipendente_id` int(10) UNSIGNED NOT NULL,
  `titolo` varchar(190) NOT NULL,
  `messaggio` text NOT NULL,
  `tipo` varchar(50) NOT NULL DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `app_push_subscriptions`
--

CREATE TABLE `app_push_subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `dipendente_id` int(10) UNSIGNED NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `cantieri`
--

CREATE TABLE `cantieri` (
  `id` int(10) UNSIGNED NOT NULL,
  `commessa` varchar(150) NOT NULL COMMENT 'nome cantiere/commessa',
  `cliente` varchar(255) DEFAULT NULL,
  `codice_commessa` varchar(100) DEFAULT NULL,
  `indirizzo` varchar(255) DEFAULT NULL,
  `comune` varchar(100) NOT NULL,
  `tipologia` varchar(50) NOT NULL COMMENT 'civile, industriale, ecc.',
  `stato` enum('pianificato','in_corso','sospeso','chiuso') NOT NULL DEFAULT 'pianificato',
  `cig` varchar(50) DEFAULT NULL,
  `cup` varchar(50) DEFAULT NULL,
  `data_inizio` date DEFAULT NULL,
  `data_fine_prevista` date DEFAULT NULL,
  `data_fine_effettiva` date DEFAULT NULL,
  `importo_previsto` decimal(12,2) DEFAULT NULL,
  `note_operativo` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `visibile_calendario` tinyint(1) NOT NULL DEFAULT 1,
  `is_special` tinyint(1) NOT NULL DEFAULT 0,
  `counts_as_work` tinyint(1) NOT NULL DEFAULT 1,
  `counts_as_absence` tinyint(1) NOT NULL DEFAULT 0,
  `pausa_pranzo` decimal(4,2) NOT NULL DEFAULT 1.00,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Elenco cantieri / commesse';

-- --------------------------------------------------------

--
-- Struttura della tabella `dipendenti`
--

CREATE TABLE `dipendenti` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `matricola` varchar(100) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `fcm_token` varchar(255) DEFAULT NULL,
  `codice_fiscale` varchar(16) DEFAULT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `indirizzo_residenza` varchar(255) DEFAULT NULL,
  `data_assunzione` date DEFAULT NULL,
  `tipo_contratto` enum('indeterminato','determinato') NOT NULL DEFAULT 'indeterminato',
  `data_scadenza_contratto` date DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `tipologia` varchar(50) DEFAULT NULL COMMENT 'elettrico, meccanico, tecnico, ecc.',
  `livello` varchar(50) DEFAULT NULL,
  `preposto` tinyint(1) NOT NULL DEFAULT 0,
  `capo_cantiere` tinyint(1) NOT NULL DEFAULT 0,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Elenco dipendenti assegnabili ai cantieri';

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_turni`
--

CREATE TABLE `eventi_turni` (
  `id` int(10) UNSIGNED NOT NULL,
  `data` date NOT NULL,
  `id_cantiere` int(10) UNSIGNED NOT NULL,
  `id_dipendente` int(10) UNSIGNED NOT NULL,
  `ora_inizio` time NOT NULL,
  `ora_fine` time NOT NULL,
  `note` text DEFAULT NULL,
  `is_capocantiere` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Capocantiere per quel giorno e cantiere',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'utente che ha creato/modificato il turno'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Turni giornalieri dei dipendenti sui cantieri';

-- --------------------------------------------------------

--
-- Struttura della tabella `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `label` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catalogo permessi granulari Turnar';

-- --------------------------------------------------------

--
-- Struttura della tabella `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `role` enum('master','manager','user') NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permessi base per ruolo';

-- --------------------------------------------------------

--
-- Struttura della tabella `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `chiave` varchar(190) NOT NULL,
  `valore` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `dipendente_id` int(10) UNSIGNED DEFAULT NULL,
  `role` enum('master','manager','user') NOT NULL DEFAULT 'user',
  `scope` enum('global','team','self') NOT NULL DEFAULT 'self',
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `can_login_web` tinyint(1) NOT NULL DEFAULT 1,
  `can_login_app` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `is_administrative` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Utenti accesso Turnar separati dalla tabella dipendenti';

-- --------------------------------------------------------

--
-- Struttura della tabella `user_favorite_destinations`
--

CREATE TABLE `user_favorite_destinations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `cantiere_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Destinazioni preferite salvate per utente';

-- --------------------------------------------------------

--
-- Struttura della tabella `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Override permessi per singolo utente';

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `app_notifications`
--
ALTER TABLE `app_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_app_notifications_dipendente` (`dipendente_id`),
  ADD KEY `idx_app_notifications_read` (`is_read`);

--
-- Indici per le tabelle `app_push_subscriptions`
--
ALTER TABLE `app_push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_endpoint` (`endpoint`(255)),
  ADD KEY `idx_push_dipendente` (`dipendente_id`);

--
-- Indici per le tabelle `cantieri`
--
ALTER TABLE `cantieri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cantieri_commessa` (`commessa`),
  ADD KEY `idx_cantieri_comune` (`comune`),
  ADD KEY `idx_cantieri_tipologia` (`tipologia`);

--
-- Indici per le tabelle `dipendenti`
--
ALTER TABLE `dipendenti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dipendenti_matricola` (`matricola`),
  ADD KEY `idx_dipendenti_nome_cognome` (`nome`,`cognome`),
  ADD KEY `idx_dipendenti_tipologia` (`tipologia`),
  ADD KEY `idx_dipendenti_capo_cantiere` (`capo_cantiere`),
  ADD KEY `idx_dipendenti_preposto` (`preposto`);

--
-- Indici per le tabelle `eventi_turni`
--
ALTER TABLE `eventi_turni`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eventi_data` (`data`),
  ADD KEY `idx_eventi_dipendente` (`id_dipendente`),
  ADD KEY `idx_eventi_cantiere` (`id_cantiere`),
  ADD KEY `idx_eventi_created_by` (`created_by`);

--
-- Indici per le tabelle `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_code` (`code`),
  ADD KEY `idx_permissions_module` (`module`);

--
-- Indici per le tabelle `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_role_permission` (`role`,`permission_id`),
  ADD KEY `idx_role_permissions_permission` (`permission_id`);

--
-- Indici per le tabelle `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_settings_chiave` (`chiave`);

--
-- Indici per le tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_dipendente_id` (`dipendente_id`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_scope` (`scope`),
  ADD KEY `idx_users_active` (`is_active`);

--
-- Indici per le tabelle `user_favorite_destinations`
--
ALTER TABLE `user_favorite_destinations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_favorite_destination` (`user_id`,`cantiere_id`),
  ADD KEY `idx_user_favorite_destinations_cantiere` (`cantiere_id`);

--
-- Indici per le tabelle `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_permission` (`user_id`,`permission_id`),
  ADD KEY `idx_user_permissions_permission` (`permission_id`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `app_notifications`
--
ALTER TABLE `app_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `app_push_subscriptions`
--
ALTER TABLE `app_push_subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `cantieri`
--
ALTER TABLE `cantieri`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `dipendenti`
--
ALTER TABLE `dipendenti`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_turni`
--
ALTER TABLE `eventi_turni`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `user_favorite_destinations`
--
ALTER TABLE `user_favorite_destinations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_dipendente` FOREIGN KEY (`dipendente_id`) REFERENCES `dipendenti` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `user_favorite_destinations`
--
ALTER TABLE `user_favorite_destinations`
  ADD CONSTRAINT `fk_user_favorite_destinations_cantiere` FOREIGN KEY (`cantiere_id`) REFERENCES `cantieri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_favorite_destinations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `fk_user_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
