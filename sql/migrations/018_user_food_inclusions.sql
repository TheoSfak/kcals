-- ============================================================
-- Migration 018 - Must-Include Food Preferences
-- Adds per-user foods that should be included when possible in
-- generated weekly plans.
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_food_inclusions` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `user_id`    INT NOT NULL,
  `food_id`    INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_food_include` (`user_id`, `food_id`),
  CONSTRAINT `fk_ufi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ufi_food` FOREIGN KEY (`food_id`) REFERENCES `foods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('018_user_food_inclusions.sql');
