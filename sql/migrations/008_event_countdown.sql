-- ============================================================
-- KCALS Migration 008 – Event Countdown (v0.9.2)
-- Adds goal_event_name + goal_event_date + goal_weight_kg to users.
-- NOTE: Run only once. Safe to skip if columns already exist.
-- ============================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS goal_event_name VARCHAR(120)  NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS goal_event_date DATE          NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS goal_weight_kg  DECIMAL(5,2)  NULL DEFAULT NULL;

-- Register migration
INSERT IGNORE INTO schema_migrations (filename) VALUES ('008_event_countdown.sql');
