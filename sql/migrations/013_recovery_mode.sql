-- ============================================================
-- Migration 013: Recovery Mode (v0.9.6)
-- Adds a flag to users tracking whether Recovery Mode is active.
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `recovery_mode` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = Recovery Mode active (stress ≥ 8 for 2+ consecutive check-ins).'
        AFTER `recharge_day`;

-- Register migration
INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('013_recovery_mode.sql');
