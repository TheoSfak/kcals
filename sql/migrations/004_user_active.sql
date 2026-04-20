-- ============================================================
-- KCALS Migration 004 – User active/inactive flag
-- Fully idempotent — safe to run on an existing database.
-- ============================================================

-- Add is_active column (1 = active, 0 = deactivated)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_admin`;

-- Ensure any existing rows default to active
UPDATE `users` SET `is_active` = 1 WHERE `is_active` IS NULL;
