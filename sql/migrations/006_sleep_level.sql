-- ============================================================
-- KCALS Migration 006 – Sleep Factor (v0.9.1)
-- Adds sleep_level to user_progress for daily sleep quality tracking.
-- Fully idempotent (ADD COLUMN IF NOT EXISTS).
-- ============================================================

-- NOTE: Run only once. If column already exists this will error (safe to ignore).
ALTER TABLE user_progress
    ADD COLUMN sleep_level INT NOT NULL DEFAULT 5
    COMMENT '1 = very poor sleep, 10 = excellent sleep';

-- Register migration
INSERT IGNORE INTO schema_migrations (filename) VALUES ('006_sleep_level.sql');
