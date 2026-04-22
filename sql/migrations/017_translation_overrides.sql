-- ============================================================
-- 017 – Translation Overrides
-- Per-language key/value overrides managed via Admin > Settings > Translations
-- ============================================================

CREATE TABLE IF NOT EXISTS `translation_overrides` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `lang`       VARCHAR(10)  NOT NULL,
    `key`        VARCHAR(191) NOT NULL,
    `value`      TEXT         NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_lang_key` (`lang`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('017_translation_overrides.sql');
