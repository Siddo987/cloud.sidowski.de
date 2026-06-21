-- phpMyAdmin SQL Dump
-- version 4.0.10.20
-- https://www.phpmyadmin.net
--
-- Host: localhost
-- Erstellungszeit: 12. Jun 2026 um 04:10
-- Server Version: 5.1.73
-- PHP-Version: 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Datenbank: `cloud.sidowski`
--
CREATE DATABASE IF NOT EXISTS `cloud.sidowski` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `cloud.sidowski`;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `files`
--

CREATE TABLE IF NOT EXISTS `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `size` int(11) NOT NULL,
  `uploader_id` int(11) DEFAULT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `public` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `physical_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uploader_id` (`uploader_id`),
  KEY `idx_files_deleted` (`deleted`),
  KEY `idx_folder` (`folder_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=235 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `folders`
--

CREATE TABLE IF NOT EXISTS `folders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_user_parent` (`user_id`,`parent_id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `migrations`
--

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `role` enum('user','admin','owner') COLLATE utf8_unicode_ci DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verification_token` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email_verification_expires` datetime DEFAULT NULL,
  `session_version` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deletion_recovery_until` datetime DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_method` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'totp or email',
  `two_factor_secret` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `two_factor_backup_codes` text COLLATE utf8_unicode_ci COMMENT 'JSON array of hashed backup codes',
  `unlimited_upload` tinyint(1) DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_email` (`email`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=29 ;

-- --------------------------------------------------------

-- Seed admin user
INSERT INTO `users` (`username`, `password`, `role`, `created_at`, `email`, `email_verified`, `session_version`, `deleted`, `deleted_at`, `deletion_recovery_until`, `two_factor_enabled`, `two_factor_method`, `two_factor_secret`, `two_factor_backup_codes`, `unlimited_upload`)
VALUES ('admin', '$2y$10$/WIvN8NYpbta3e6rTPspIetwm2ybc3KNSLaQh5xe4R65Xtz0hCiEK', 'admin', NOW(), NULL, 0, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_tokens`
--

CREATE TABLE IF NOT EXISTS `user_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `meta` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=154 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `webauthn_challenges`
--

CREATE TABLE IF NOT EXISTS `webauthn_challenges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `challenge` varchar(255) NOT NULL,
  `type` enum('registration','authentication') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `challenge` (`challenge`),
  KEY `user_id` (`user_id`),
  KEY `idx_challenge` (`challenge`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `webauthn_credentials`
--

CREATE TABLE IF NOT EXISTS `webauthn_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `credential_id` longblob NOT NULL,
  `credential_id_b64` varchar(255) NOT NULL,
  `public_key` longblob NOT NULL,
  `public_key_algo` varchar(50) DEFAULT NULL,
  `counter` bigint(20) DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT 'Mein Sicherheitsschlüssel',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used` timestamp NULL DEFAULT NULL,
  `aaguid` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credential_id_b64` (`credential_id_b64`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_credential_id_b64` (`credential_id_b64`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `rate_limits`
--

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT '1',
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_action_idx` (`ip_address`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tabellenstruktur für Tabelle `tickets`
--
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('open', 'closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tickets_user_id` (`user_id`),
  KEY `idx_tickets_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `ticket_messages`
--
CREATE TABLE IF NOT EXISTS `ticket_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_msgs_ticket_id` (`ticket_id`),
  KEY `idx_ticket_msgs_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Constraints der Tabelle `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD CONSTRAINT `fk_ticket_msgs_ticket_id` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ticket_msgs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
