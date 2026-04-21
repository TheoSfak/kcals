-- ============================================================
-- Migration 006 – Food Preference System
-- Adds: cuisine_tag + allergen_tags on foods
--       food_adventure + interview_done + 6 allergy flags on users
--       user_food_exclusions table
-- ============================================================

-- ---- Foods: new classification columns ----
ALTER TABLE `foods`
  ADD COLUMN IF NOT EXISTS `cuisine_tag`   ENUM('universal','greek','mediterranean','international') NOT NULL DEFAULT 'universal' AFTER `food_type`,
  ADD COLUMN IF NOT EXISTS `allergen_tags` VARCHAR(100) NOT NULL DEFAULT ''                          AFTER `is_paleo_ok`;

-- ---- Users: preference + allergy columns ----
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `food_adventure`    TINYINT(1) NOT NULL DEFAULT 2 AFTER `diet_type`,
  ADD COLUMN IF NOT EXISTS `interview_done`    TINYINT(1) NOT NULL DEFAULT 0 AFTER `food_adventure`,
  ADD COLUMN IF NOT EXISTS `allergy_gluten`    TINYINT(1) NOT NULL DEFAULT 0 AFTER `interview_done`,
  ADD COLUMN IF NOT EXISTS `allergy_dairy`     TINYINT(1) NOT NULL DEFAULT 0 AFTER `allergy_gluten`,
  ADD COLUMN IF NOT EXISTS `allergy_nuts`      TINYINT(1) NOT NULL DEFAULT 0 AFTER `allergy_dairy`,
  ADD COLUMN IF NOT EXISTS `allergy_eggs`      TINYINT(1) NOT NULL DEFAULT 0 AFTER `allergy_nuts`,
  ADD COLUMN IF NOT EXISTS `allergy_shellfish` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allergy_eggs`,
  ADD COLUMN IF NOT EXISTS `allergy_soy`       TINYINT(1) NOT NULL DEFAULT 0 AFTER `allergy_shellfish`;

-- ---- Per-food exclusion list ----
CREATE TABLE IF NOT EXISTS `user_food_exclusions` (
  `id`      INT          NOT NULL AUTO_INCREMENT,
  `user_id` INT          NOT NULL,
  `food_id` INT          NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_food` (`user_id`, `food_id`),
  CONSTRAINT `fk_ufe_user` FOREIGN KEY (`user_id`) REFERENCES `users`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ufe_food` FOREIGN KEY (`food_id`) REFERENCES `foods`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Register migration ----
INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('006_food_preferences.sql');
