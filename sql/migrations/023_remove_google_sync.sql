-- ============================================================
-- Migration 023 - Remove Google Sync and meal-prep Calendar data
-- Drops Google OAuth connections, Drive backup metadata and
-- Calendar event mappings now that the feature has been removed.
-- ============================================================

DROP TABLE IF EXISTS `google_calendar_events`;
DROP TABLE IF EXISTS `google_accounts`;

DELETE FROM `schema_migrations`
WHERE `filename` IN (
  '020_google_sync_accounts.sql',
  '021_google_sync_backup_metadata.sql',
  '022_google_calendar_sync_foundation.sql'
);

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('023_remove_google_sync.sql');
