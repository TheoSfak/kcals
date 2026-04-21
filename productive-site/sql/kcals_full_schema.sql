-- ============================================================
-- KCALS — Full Production Schema (v0.9.4)
-- Run this ONCE on a brand-new database to set up everything.
-- All statements are idempotent (IF NOT EXISTS / INSERT IGNORE).
-- ============================================================

CREATE DATABASE IF NOT EXISTS `kcals`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `kcals`;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`                        INT AUTO_INCREMENT PRIMARY KEY,
  `email`                     VARCHAR(255)  UNIQUE NOT NULL,
  `password_hash`             VARCHAR(255)  NOT NULL,
  `full_name`                 VARCHAR(150)  NOT NULL,
  `gender`                    ENUM('male','female') NOT NULL,
  `birth_date`                DATE NOT NULL,
  `height_cm`                 INT  NOT NULL,
  `activity_level`            DECIMAL(3,2) NOT NULL DEFAULT 1.20,
  `diet_type`                 VARCHAR(50)  DEFAULT 'standard',
  `food_adventure`            TINYINT(1)   NOT NULL DEFAULT 2,
  `interview_done`            TINYINT(1)   NOT NULL DEFAULT 0,
  `allergy_gluten`            TINYINT(1)   NOT NULL DEFAULT 0,
  `allergy_dairy`             TINYINT(1)   NOT NULL DEFAULT 0,
  `allergy_nuts`              TINYINT(1)   NOT NULL DEFAULT 0,
  `allergy_eggs`              TINYINT(1)   NOT NULL DEFAULT 0,
  `allergy_shellfish`         TINYINT(1)   NOT NULL DEFAULT 0,
  `allergy_soy`               TINYINT(1)   NOT NULL DEFAULT 0,
  `is_admin`                  TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`                 TINYINT(1)   NOT NULL DEFAULT 1,
  `goal_event_name`           VARCHAR(120) NULL DEFAULT NULL,
  `goal_event_date`           DATE         NULL DEFAULT NULL,
  `goal_weight_kg`            DECIMAL(5,2) NULL DEFAULT NULL,
  `tdee_override`             INT          NULL DEFAULT NULL,
  `tdee_recalibrated_at`      DATETIME     NULL DEFAULT NULL,
  `tdee_recalibration_delta`  INT          NULL DEFAULT NULL,
  `created_at`                TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_progress
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_progress` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`          INT NOT NULL,
  `weight_kg`        DECIMAL(5,2) NOT NULL,
  `stress_level`     INT DEFAULT 5,
  `motivation_level` INT DEFAULT 5,
  `energy_level`     INT DEFAULT 5,
  `sleep_level`      INT NOT NULL DEFAULT 5,
  `notes`            TEXT,
  `workout_type`     VARCHAR(20) NULL DEFAULT NULL,
  `workout_minutes`  SMALLINT    NOT NULL DEFAULT 0,
  `entry_date`       DATE NOT NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_user_date` (`user_id`, `entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: weekly_plans
-- ============================================================
CREATE TABLE IF NOT EXISTS `weekly_plans` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT NOT NULL,
  `start_date`      DATE NOT NULL,
  `end_date`        DATE NOT NULL,
  `target_calories` INT NOT NULL,
  `zone`            ENUM('green','yellow','red') NOT NULL DEFAULT 'yellow',
  `plan_data_json`  LONGTEXT NOT NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_dislikes
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_dislikes` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT NOT NULL,
  `ingredient_name` VARCHAR(100) NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_user_ingredient` (`user_id`, `ingredient_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: foods
-- ============================================================
CREATE TABLE IF NOT EXISTS `foods` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `name_en`          VARCHAR(150) NOT NULL,
  `name_el`          VARCHAR(150) NOT NULL,
  `food_type`        ENUM('protein','carb','fat','vegetable','fruit','dairy','mixed') NOT NULL,
  `cuisine_tag`      ENUM('universal','greek','mediterranean','international')    NOT NULL DEFAULT 'universal',
  `meal_slots`       VARCHAR(80)  NOT NULL DEFAULT 'breakfast,lunch,dinner,snack',
  `cal_per_100g`     DECIMAL(7,2) NOT NULL,
  `protein_per_100g` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `carbs_per_100g`   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `fat_per_100g`     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `is_vegan`         TINYINT(1)   NOT NULL DEFAULT 0,
  `is_vegetarian`    TINYINT(1)   NOT NULL DEFAULT 1,
  `is_gluten_free`   TINYINT(1)   NOT NULL DEFAULT 1,
  `is_keto_ok`       TINYINT(1)   NOT NULL DEFAULT 0,
  `is_paleo_ok`      TINYINT(1)   NOT NULL DEFAULT 1,
  `allergen_tags`    VARCHAR(100) NOT NULL DEFAULT '',
  `available_months` VARCHAR(50)  NOT NULL DEFAULT '1,2,3,4,5,6,7,8,9,10,11,12',
  `min_serving_g`    SMALLINT     NOT NULL DEFAULT 50,
  `max_serving_g`    SMALLINT     NOT NULL DEFAULT 300,
  `prep_minutes`     SMALLINT     NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_food_exclusions
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_food_exclusions` (
  `id`      INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `food_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_food` (`user_id`, `food_id`),
  CONSTRAINT `fk_ufe_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ufe_food` FOREIGN KEY (`food_id`) REFERENCES `foods`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: health_tips
-- ============================================================
CREATE TABLE IF NOT EXISTS `health_tips` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `category`    ENUM('nutrition','fitness','beauty','mindset','sleep') NOT NULL,
  `tip_text`    TEXT NOT NULL,
  `tip_text_el` TEXT NOT NULL DEFAULT '',
  `icon`        VARCHAR(50) DEFAULT 'leaf'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: recipes (legacy, kept for reference)
-- ============================================================
CREATE TABLE IF NOT EXISTS `recipes` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `title`            VARCHAR(255) NOT NULL,
  `category`         ENUM('breakfast','lunch','dinner','snack') NOT NULL,
  `calories`         INT NOT NULL,
  `protein_g`        INT NOT NULL,
  `carbs_g`          INT NOT NULL,
  `fat_g`            INT NOT NULL,
  `prep_minutes`     INT DEFAULT 15,
  `ingredients_json` TEXT NOT NULL,
  `instructions`     TEXT,
  `is_vegan`         BOOLEAN DEFAULT FALSE,
  `is_gluten_free`   BOOLEAN DEFAULT FALSE,
  `satiety_score`    INT DEFAULT 5,
  `available_months` VARCHAR(50) DEFAULT '1,2,3,4,5,6,7,8,9,10,11,12',
  `image_url`        VARCHAR(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: settings (admin key/value store)
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `key`        VARCHAR(100) NOT NULL UNIQUE,
  `value`      TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: schema_migrations (migration tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `filename`   VARCHAR(255) NOT NULL UNIQUE,
  `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED: settings defaults
-- ============================================================
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
  ('smtp_host',       ''),
  ('smtp_port',       '587'),
  ('smtp_encryption', 'tls'),
  ('smtp_user',       ''),
  ('smtp_pass',       ''),
  ('smtp_from_name',  'KCALS'),
  ('smtp_from_email', '');

-- ============================================================
-- SEED: foods
-- ============================================================
INSERT IGNORE INTO `foods`
  (`name_en`, `name_el`, `food_type`, `meal_slots`,
   `cal_per_100g`, `protein_per_100g`, `carbs_per_100g`, `fat_per_100g`,
   `is_vegan`, `is_vegetarian`, `is_gluten_free`, `is_keto_ok`, `is_paleo_ok`,
   `available_months`, `min_serving_g`, `max_serving_g`, `prep_minutes`)
VALUES
-- PROTEINS: meat / fish
('Chicken Breast',       'Στήθος Κοτόπουλου',  'protein', 'lunch,dinner',        165.00, 31.00,  0.00,  3.60, 0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250,20),
('Salmon',               'Σολομός',             'protein', 'lunch,dinner',        208.00, 20.00,  0.00, 13.00, 0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,200,15),
('Canned Tuna',          'Τόνος (κονσέρβα)',    'protein', 'lunch,dinner',        116.00, 26.00,  0.00,  1.00, 0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80,180, 5),
('Cod',                  'Μπακαλιάρος',         'protein', 'lunch,dinner',         82.00, 18.00,  0.00,  0.70, 0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,220,15),
('Turkey Breast',        'Γαλοπούλα',           'protein', 'lunch,dinner',        135.00, 30.00,  0.00,  1.00, 0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250,20),
('Lean Beef Mince',      'Κιμάς Μοσχαρίσιος',  'protein', 'dinner',              215.00, 26.00,  0.00, 12.00, 0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,200,25),
('Shrimp',               'Γαρίδες',             'protein', 'lunch,dinner',         99.00, 24.00,  0.00,  0.60, 0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250,10),
-- PROTEINS: eggs
('Eggs',                 'Αυγά',                'protein', 'breakfast,lunch',     155.00, 13.00,  1.10, 11.00, 0,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,200,10),
-- PROTEINS: vegan / legumes
('Firm Tofu',            'Τόφου',               'protein', 'breakfast,lunch,dinner', 76.00, 8.00, 2.00,  4.80, 1,1,1,1,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250,15),
('Chickpeas (cooked)',   'Ρεβύθια',             'protein', 'lunch,dinner',        164.00,  9.00, 27.00,  2.60, 1,1,1,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 5),
('Lentils (cooked)',     'Φακές',               'protein', 'lunch,dinner',        116.00,  9.00, 20.00,  0.40, 1,1,1,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 5),
('Black Beans (cooked)', 'Μαύρα Φασόλια',       'protein', 'lunch,dinner',        132.00,  9.00, 24.00,  0.50, 1,1,1,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 5),
-- DAIRY
('Greek Yogurt',         'Ελληνικό Γιαούρτι',   'dairy',   'breakfast,snack',      97.00,  9.00,  4.00,  5.00, 0,1,1,1,0, '1,2,3,4,5,6,7,8,9,10,11,12', 150,300, 2),
('Cottage Cheese',       'Κότατζ Τσιζ',         'dairy',   'breakfast,snack',      98.00, 11.00,  3.40,  4.30, 0,1,1,1,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 2),
('Feta Cheese',          'Φέτα',                'dairy',   'lunch,dinner',        264.00, 14.00,  4.00, 21.00, 0,1,1,1,0, '1,2,3,4,5,6,7,8,9,10,11,12',  30, 80, 1),
-- CARBS: grains
('Oats',                      'Βρώμη',           'carb',    'breakfast',          389.00, 17.00, 66.00,  7.00, 1,1,0,0,0, '1,2,3,4,5,6,7,8,9,10,11,12',  50,100,10),
('Brown Rice (cooked)',        'Καστανό Ρύζι',   'carb',    'lunch,dinner',       111.00,  2.60, 23.00,  0.90, 1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 5),
('White Rice (cooked)',        'Λευκό Ρύζι',     'carb',    'lunch,dinner',       130.00,  2.70, 28.00,  0.30, 1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 5),
('Whole Wheat Pasta (cooked)', 'Ζυμαρικά Ολικής','carb',   'lunch,dinner',        124.00,  5.00, 25.00,  1.10, 1,1,0,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 5),
('Pasta (cooked)',             'Ζυμαρικά',       'carb',    'lunch,dinner',        131.00,  5.00, 25.00,  1.10, 1,1,0,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 5),
('Sweet Potato (cooked)',      'Γλυκοπατάτα',   'carb',    'lunch,dinner',         90.00,  2.00, 21.00,  0.10, 1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 5),
('Potato (boiled)',            'Πατάτα βραστή',  'carb',    'lunch,dinner',         87.00,  1.90, 20.00,  0.10, 1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 5),
('Quinoa (cooked)',            'Κινόα',          'carb',    'lunch,dinner',        120.00,  4.40, 22.00,  1.90, 1,1,1,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 5),
('Whole Wheat Bread',          'Ψωμί Ολικής',   'carb',    'breakfast',           247.00, 13.00, 41.00,  3.40, 1,1,0,0,0, '1,2,3,4,5,6,7,8,9,10,11,12',  40,100, 2),
('Sourdough Bread',            'Ψωμί Προζύμι',  'carb',    'breakfast',           274.00,  9.00, 55.00,  1.50, 1,1,0,0,0, '1,2,3,4,5,6,7,8,9,10,11,12',  40,100, 2),
-- FRUITS
('Banana',          'Μπανάνα',     'fruit', 'breakfast,snack',  89.00, 1.10, 23.00, 0.30, 1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80,200, 1),
('Apple',           'Μήλο',        'fruit', 'snack',             52.00, 0.30, 14.00, 0.20, 1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100,250, 1),
('Strawberries',    'Φράουλες',    'fruit', 'breakfast,snack',   32.00, 0.70,  7.70, 0.30, 1,1,1,1,1, '4,5,6,7,8,9',                100,250, 2),
('Blueberries',     'Μύρτιλα',     'fruit', 'breakfast,snack',   57.00, 0.70, 14.00, 0.30, 1,1,1,1,1, '6,7,8,9',                     80,200, 1),
('Orange',          'Πορτοκάλι',   'fruit', 'snack',             47.00, 0.90, 12.00, 0.10, 1,1,1,0,1, '11,12,1,2,3,4,5',            130,300, 1),
('Grapes',          'Σταφύλια',    'fruit', 'snack',             69.00, 0.70, 18.00, 0.20, 1,1,1,0,1, '7,8,9,10',                   100,200, 1),
('Kiwi',            'Ακτινίδιο',   'fruit', 'breakfast,snack',   61.00, 1.10, 15.00, 0.50, 1,1,1,0,1, '1,2,3,4,5,10,11,12',         100,250, 1),
-- VEGETABLES
('Spinach',      'Σπανάκι',       'vegetable', 'lunch,dinner',  23.00, 2.90,  3.60, 0.40, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80,200, 5),
('Broccoli',     'Μπρόκολο',      'vegetable', 'lunch,dinner',  34.00, 2.80,  7.00, 0.40, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80,200, 8),
('Tomato',       'Ντομάτα',       'vegetable', 'lunch,dinner',  18.00, 0.90,  3.90, 0.20, 1,1,1,1,1, '5,6,7,8,9,10',                 80,250, 2),
('Cucumber',     'Αγγούρι',       'vegetable', 'lunch,dinner',  15.00, 0.70,  3.60, 0.10, 1,1,1,1,1, '4,5,6,7,8,9,10',               80,250, 2),
('Zucchini',     'Κολοκυθάκι',   'vegetable', 'lunch,dinner',  18.00, 1.20,  3.10, 0.30, 1,1,1,1,1, '5,6,7,8,9,10',                 80,200, 8),
('Bell Pepper',  'Πιπεριά',       'vegetable', 'lunch,dinner',  31.00, 1.00,  6.00, 0.30, 1,1,1,1,1, '5,6,7,8,9,10',                 80,200, 5),
('Carrot',       'Καρότο',        'vegetable', 'lunch,dinner',  41.00, 0.90, 10.00, 0.20, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80,200, 5),
('Green Beans',  'Φασολάκια',     'vegetable', 'lunch,dinner',  31.00, 1.80,  7.00, 0.10, 1,1,1,1,1, '5,6,7,8,9,10',                 80,200,10),
('Mixed Greens', 'Μικτή Σαλάτα', 'vegetable', 'lunch,dinner',  15.00, 1.40,  2.90, 0.20, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80,200, 3),
('Eggplant',     'Μελιτζάνα',    'vegetable', 'lunch,dinner',  25.00, 1.00,  6.00, 0.20, 1,1,1,1,1, '6,7,8,9',                      80,200,15),
('Mushrooms',    'Μανιτάρια',     'vegetable', 'lunch,dinner',  22.00, 3.10,  3.30, 0.30, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80,200, 8),
('Cauliflower',  'Κουνουπίδι',   'vegetable', 'lunch,dinner',  25.00, 1.90,  5.00, 0.30, 1,1,1,1,1, '9,10,11,12,1,2,3',             80,200, 8),
-- FATS
('Olive Oil',        'Ελαιόλαδο',          'fat', 'breakfast,lunch,dinner', 884.00,  0.00,  0.00,100.00, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  5, 30, 0),
('Almonds',          'Αμύγδαλα',           'fat', 'snack',                  579.00, 21.00, 22.00, 50.00, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 20, 60, 1),
('Walnuts',          'Καρύδια',            'fat', 'snack',                  654.00, 15.00, 14.00, 65.00, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 15, 50, 1),
('Avocado',          'Αβοκάντο',           'fat', 'breakfast,lunch',        160.00,  2.00,  9.00, 15.00, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 80,150, 2),
('Almond Butter',    'Βούτυρο Αμυγδάλου', 'fat', 'breakfast,snack',        614.00, 21.00, 20.00, 56.00, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 15, 40, 1),
('Mixed Nuts',       'Μικτοί Ξηροί Καρποί','fat','snack',                  607.00, 20.00, 21.00, 54.00, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 20, 50, 1),
('Cashews',          'Κάσιους',            'fat', 'snack',                  553.00, 18.00, 30.00, 44.00, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 20, 50, 1),
('Tahini',           'Ταχίνι',             'fat', 'snack',                  595.00, 17.00, 21.00, 54.00, 1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 15, 40, 1);

-- ============================================================
-- SEED: health tips
-- ============================================================
INSERT IGNORE INTO `health_tips` (`category`, `tip_text`, `tip_text_el`, `icon`) VALUES
('nutrition', 'Drink at least 8 glasses of water daily. Hydration boosts metabolism and reduces hunger.',                                      'Πίνετε τουλάχιστον 8 ποτήρια νερό την ημέρα. Η ενυδάτωση επιταχύνει τον μεταβολισμό και μειώνει την πείνα.',                                                                           'droplets'),
('nutrition', 'Eat slowly — it takes 20 minutes for your brain to register fullness. Put your fork down between bites.',                       'Τρώτε αργά — ο εγκέφαλος χρειάζεται 20 λεπτά για να νιώσει κορεσμό. Αφήνετε το πιρούνι ανάμεσα στις μπουκιές.',                                                                       'clock'),
('nutrition', 'Prioritize protein at every meal. It has the highest satiety per calorie and preserves muscle during fat loss.',                'Δίνετε προτεραιότητα στην πρωτεΐνη σε κάθε γεύμα. Έχει τον υψηλότερο κορεσμό ανά θερμίδα και διατηρεί τη μυϊκή μάζα κατά την απώλεια λίπους.',                                       'beef'),
('fitness',   'A 20-minute brisk walk after dinner significantly improves insulin sensitivity and fat burning overnight.',                      'Μια έντονη βόλτα 20 λεπτών μετά το δείπνο βελτιώνει σημαντικά την ευαισθησία στην ινσουλίνη και την καύση λίπους κατά τη νύχτα.',                                                   'footprints'),
('fitness',   'Resistance training 2-3x per week preserves lean muscle mass, which keeps your metabolism elevated.',                           'Η προπόνηση αντίστασης 2-3 φορές/εβδομάδα διατηρεί τη μυϊκή μάζα και κρατά τον μεταβολισμό σε υψηλά επίπεδα.',                                                                        'dumbbell'),
('beauty',    'Collagen production needs Vitamin C. Include bell peppers, citrus, or kiwi daily for glowing skin.',                           'Η παραγωγή κολλαγόνου χρειάζεται Βιταμίνη C. Συμπεριλάβετε πιπεριές, εσπεριδοειδή ή ακτινίδιο καθημερινά για λαμπερό δέρμα.',                                                       'sparkles'),
('beauty',    'Sleep is your best beauty treatment. Aim for 7-9 hours — cortisol from poor sleep triggers fat storage and breakouts.',        'Ο ύπνος είναι η καλύτερη θεραπεία ομορφιάς. Στοχεύστε 7-9 ώρες — η κορτιζόλη από τον κακό ύπνο πυροδοτεί αποθήκευση λίπους και σπυράκια.',                                          'moon'),
('mindset',   'Progress, not perfection. One "off" meal does not ruin your week. Consistency over time is what matters.',                     'Πρόοδος, όχι τελειομανία. Ένα «κακό» γεύμα δεν καταστρέφει την εβδομάδα σας. Η συνέπεια στον χρόνο είναι αυτό που μετράει.',                                                          'target'),
('mindset',   'Set a weekly non-food reward for hitting your goals — a movie, a walk in nature, a new book.',                                 'Ορίστε μια εβδομαδιαία ανταμοιβή χωρίς φαγητό για την επίτευξη των στόχων σας — ταινία, βόλτα στη φύση, καινούριο βιβλίο.',                                                          'gift'),
('sleep',     'Avoid screens 1 hour before bed. Blue light suppresses melatonin, delaying sleep and increasing cortisol.',                    'Αποφεύγετε τις οθόνες 1 ώρα πριν τον ύπνο. Το μπλε φως καταστέλλει τη μελατονίνη, καθυστερεί τον ύπνο και αυξάνει την κορτιζόλη.',                                                'monitor-off'),
('nutrition', 'Meal prep on Sundays. Having healthy food ready removes decision fatigue and prevents impulse eating.',                         'Ετοιμάστε γεύματα τις Κυριακές. Έχοντας υγιεινό φαγητό έτοιμο εξαλείφετε την κόπωση αποφάσεων και αποφεύγετε τις παρορμητικές επιλογές.',                                          'chef-hat'),
('fitness',   'Take the stairs. Small movements throughout the day (NEAT) can add up to 300-500 extra calories burned.',                      'Ανεβείτε σκάλες. Οι μικρές κινήσεις κατά τη διάρκεια της ημέρας (NEAT) μπορούν να αθροιστούν σε 300-500 επιπλέον θερμίδες που καίγονται.',                                          'trending-up');

-- ============================================================
-- SEED: migration tracking (marks all as applied)
-- ============================================================
INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES
  ('001_initial.sql'),
  ('002_admin.sql'),
  ('003_schema_migrations.sql'),
  ('004_user_active.sql'),
  ('005_foods_system.sql'),
  ('006_food_preferences.sql'),
  ('007_sleep_level.sql'),
  ('008_event_countdown.sql'),
  ('009_workout_boost.sql'),
  ('010_tdee_recalibration.sql'),
  ('011_tips_bilingual.sql');

-- ============================================================
-- AFTER IMPORT: grant yourself admin access
-- UPDATE `users` SET is_admin = 1 WHERE email = 'your@email.com';
-- ============================================================
