-- ============================================================
-- Migration 014: Hormetic Recharge Day (v0.9.5)
-- Adds a per-user configurable recharge day (1=Mon … 7=Sun, default 3=Wed).
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `recharge_day` TINYINT UNSIGNED NOT NULL DEFAULT 3
        COMMENT '1=Monday … 7=Sunday. Day of the week for the +150 kcal recharge.' AFTER `tdee_recalibration_delta`;

-- Register migration
INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('014_recharge_day.sql');
