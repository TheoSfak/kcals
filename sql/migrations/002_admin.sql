-- ============================================================
-- KCALS Migration 002 – Admin backoffice
-- Fully idempotent — safe to run on an existing database.
-- ============================================================

-- 1. Add is_admin flag to users (IF NOT EXISTS — MariaDB 10.0+ / MySQL 8.0+)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `diet_type`;

-- 2. App-wide key/value settings store
CREATE TABLE IF NOT EXISTS `settings` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `key`        VARCHAR(100) NOT NULL UNIQUE,
    `value`      TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Seed default SMTP settings (INSERT IGNORE = safe to re-run)
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
    ('smtp_host',       ''),
    ('smtp_port',       '587'),
    ('smtp_encryption', 'tls'),
    ('smtp_user',       ''),
    ('smtp_pass',       ''),
    ('smtp_from_name',  'KCALS'),
    ('smtp_from_email', '');

-- ============================================================
-- To grant admin access run:
-- UPDATE `users` SET is_admin = 1 WHERE email = 'your@email.com';
-- ============================================================
