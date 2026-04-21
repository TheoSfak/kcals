-- Migration 010: Adaptive TDEE Recalibration
-- Adds override + audit columns to users table

ALTER TABLE users
    ADD COLUMN tdee_override           INT      NULL DEFAULT NULL,
    ADD COLUMN tdee_recalibrated_at    DATETIME NULL DEFAULT NULL,
    ADD COLUMN tdee_recalibration_delta INT     NULL DEFAULT NULL;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('010_tdee_recalibration.sql');
