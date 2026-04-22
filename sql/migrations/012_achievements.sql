-- ============================================================
-- Migration 012 – Achievements System
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_achievements` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`          INT NOT NULL,
    `achievement_slug` VARCHAR(64) NOT NULL,
    `earned_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_achievement` (`user_id`, `achievement_slug`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('012_achievements.sql');
