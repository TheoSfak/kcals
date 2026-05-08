-- ============================================================
-- Migration 024 - Local meal prep tasks
-- Stores app-local prep tasks generated from weekly plans.
-- ============================================================

CREATE TABLE IF NOT EXISTS `meal_prep_tasks` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `user_id`         INT NOT NULL,
  `weekly_plan_id`  INT NOT NULL,
  `task_key`        VARCHAR(160) NOT NULL,
  `task_date`       DATE NOT NULL,
  `day_name`        VARCHAR(20) NOT NULL,
  `meal_slot`       VARCHAR(30) DEFAULT NULL,
  `task_type`       VARCHAR(40) NOT NULL DEFAULT 'main_cook',
  `title_en`        VARCHAR(255) NOT NULL,
  `title_el`        VARCHAR(255) NOT NULL,
  `details_json`    TEXT DEFAULT NULL,
  `estimated_minutes` INT NOT NULL DEFAULT 0,
  `source_hash`     CHAR(40) NOT NULL,
  `status`          VARCHAR(20) NOT NULL DEFAULT 'todo',
  `sort_order`      INT NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meal_prep_task` (`user_id`, `weekly_plan_id`, `task_key`),
  KEY `idx_meal_prep_plan` (`weekly_plan_id`),
  KEY `idx_meal_prep_user_date` (`user_id`, `task_date`, `sort_order`),
  CONSTRAINT `fk_mpt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mpt_plan` FOREIGN KEY (`weekly_plan_id`) REFERENCES `weekly_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('024_meal_prep_tasks.sql');
