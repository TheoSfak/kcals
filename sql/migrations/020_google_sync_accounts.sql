-- ============================================================
-- Migration 020 - Google Sync account connection
-- Stores per-user Google OAuth connection metadata and encrypted
-- token payloads for future Drive/Calendar sync phases.
-- ============================================================

CREATE TABLE IF NOT EXISTS `google_accounts` (
  `id`                   INT NOT NULL AUTO_INCREMENT,
  `user_id`              INT NOT NULL,
  `google_sub`           VARCHAR(255) NOT NULL,
  `google_email`         VARCHAR(255) NOT NULL,
  `google_name`          VARCHAR(255) DEFAULT NULL,
  `scopes`               TEXT NULL,
  `access_token_cipher`  TEXT NULL,
  `refresh_token_cipher` TEXT NULL,
  `token_type`           VARCHAR(50) DEFAULT 'Bearer',
  `expires_at`           DATETIME DEFAULT NULL,
  `last_sync_at`         DATETIME DEFAULT NULL,
  `sync_status`          VARCHAR(40) NOT NULL DEFAULT 'connected',
  `connected_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_google_accounts_user` (`user_id`),
  KEY `idx_google_accounts_sub` (`google_sub`),
  CONSTRAINT `fk_google_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('020_google_sync_accounts.sql');
