-- ============================================================
-- KCALS Migration 006 – Sleep Factor (v0.9.1)
-- Adds sleep_level to user_progress for daily sleep quality tracking.
-- Fully idempotent (ADD COLUMN IF NOT EXISTS).
-- ============================================================

ALTER TABLE user_progress
    ADD COLUMN IF NOT EXISTS sleep_level INT NOT NULL DEFAULT 5
    COMMENT '1 = very poor sleep, 10 = excellent sleep';

-- Register migration
INSERT IGNORE INTO schema_migrations (filename) VALUES ('007_sleep_level.sql');
