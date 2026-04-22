-- ============================================================
-- 016 – General Settings
-- Site-wide settings: name, tagline, default language
-- ============================================================

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
    ('general_site_name',    'KCALS'),
    ('general_tagline',      'Smart Nutrition & Wellness'),
    ('general_default_lang', 'en');

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('016_general_settings.sql');
