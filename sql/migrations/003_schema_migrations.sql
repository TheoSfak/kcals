-- ============================================================
-- KCALS Migration 003 – Schema migrations tracking table
-- Run once against the kcals database
-- ============================================================

CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `filename`   VARCHAR(255) NOT NULL UNIQUE,
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed the two already-applied migrations so they are not re-run
INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES
    ('001_initial.sql'),
    ('002_admin.sql'),
    ('003_schema_migrations.sql');
