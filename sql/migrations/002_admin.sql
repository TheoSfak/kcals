-- ============================================================
-- KCALS Migration 002 – Admin backoffice
-- Run once against the kcals database
-- ============================================================

-- 1. Add admin flag to users
ALTER TABLE `users`
    ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `diet_type`;

-- 2. App-wide key/value settings store
CREATE TABLE IF NOT EXISTS `settings` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `key`        VARCHAR(100) NOT NULL UNIQUE,
    `value`      TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Seed default SMTP settings (won't overwrite existing rows)
INSERT INTO `settings` (`key`, `value`) VALUES
    ('smtp_host',       ''),
    ('smtp_port',       '587'),
    ('smtp_encryption', 'tls'),
    ('smtp_user',       ''),
    ('smtp_pass',       ''),
    ('smtp_from_name',  'KCALS'),
    ('smtp_from_email', '')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- ============================================================
-- HOW TO GRANT ADMIN ACCESS
-- Replace the email below with your account and run:
-- UPDATE `users` SET is_admin = 1 WHERE email = 'your@email.com';
-- ============================================================
