-- ============================================================
-- 013 – Appearance Settings
-- Seed default values for all CSS override controls
-- ============================================================

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
    ('appearance_accent',      '#27AE60'),
    ('appearance_accent_dark', '#1E8449'),
    ('appearance_bg',          '#F7F9FC'),
    ('appearance_font_family', 'Inter'),
    ('appearance_font_size',   '16'),
    ('appearance_border_radius','14'),
    ('appearance_site_name',   'KCALS');

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('013_appearance_settings.sql');
