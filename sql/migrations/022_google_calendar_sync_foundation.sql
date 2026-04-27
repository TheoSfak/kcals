-- ============================================================
-- Migration 022 - Google Calendar meal-prep sync foundation
-- Stores calendar preferences and future Google event IDs.
-- No events are created by this migration.
-- ============================================================

ALTER TABLE `google_accounts`
  ADD COLUMN IF NOT EXISTS `calendar_id` VARCHAR(255) NOT NULL DEFAULT 'primary' AFTER `drive_backup_file_id`,
  ADD COLUMN IF NOT EXISTS `calendar_reminder_mode` VARCHAR(40) NOT NULL DEFAULT 'previous_evening' AFTER `calendar_id`,
  ADD COLUMN IF NOT EXISTS `calendar_last_sync_at` DATETIME DEFAULT NULL AFTER `calendar_reminder_mode`;

CREATE TABLE IF NOT EXISTS `google_calendar_events` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `user_id`         INT NOT NULL,
  `weekly_plan_id`  INT DEFAULT NULL,
  `event_key`       VARCHAR(160) NOT NULL,
  `google_event_id` VARCHAR(255) NOT NULL,
  `event_date`      DATE DEFAULT NULL,
  `event_type`      VARCHAR(40) NOT NULL DEFAULT 'meal_prep',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_google_calendar_event` (`user_id`, `event_key`),
  KEY `idx_google_calendar_plan` (`weekly_plan_id`),
  CONSTRAINT `fk_gce_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gce_plan` FOREIGN KEY (`weekly_plan_id`) REFERENCES `weekly_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('022_google_calendar_sync_foundation.sql');
