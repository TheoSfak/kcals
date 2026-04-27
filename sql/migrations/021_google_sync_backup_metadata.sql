-- ============================================================
-- Migration 021 - Google Drive backup metadata
-- Tracks the hidden Drive appData backup file and last error.
-- ============================================================

ALTER TABLE `google_accounts`
  ADD COLUMN IF NOT EXISTS `drive_backup_file_id` VARCHAR(255) DEFAULT NULL AFTER `expires_at`,
  ADD COLUMN IF NOT EXISTS `last_sync_error` TEXT NULL AFTER `sync_status`;

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('021_google_sync_backup_metadata.sql');
