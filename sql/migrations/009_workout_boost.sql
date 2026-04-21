-- Migration 009: Workout Boost
-- Adds workout_type and workout_minutes to user_progress

ALTER TABLE user_progress
    ADD COLUMN workout_type    VARCHAR(20) NULL    DEFAULT NULL,
    ADD COLUMN workout_minutes SMALLINT    NOT NULL DEFAULT 0;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('009_workout_boost.sql');
