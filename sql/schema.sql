-- ============================================================
-- KCALS - Wellness & Nutrition App
-- Database Schema v1.0
-- ============================================================

CREATE DATABASE IF NOT EXISTS `kcals` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kcals`;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE `users` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `email`          VARCHAR(255) UNIQUE NOT NULL,
  `password_hash`  VARCHAR(255) NOT NULL,
  `full_name`      VARCHAR(150) NOT NULL,
  `gender`         ENUM('male','female') NOT NULL,
  `birth_date`     DATE NOT NULL,
  `height_cm`      INT NOT NULL,
  `activity_level` DECIMAL(3,2) NOT NULL DEFAULT 1.20,
  `diet_type`      VARCHAR(50) DEFAULT 'standard',
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- PROGRESS / CHECK-INS
-- ============================================================
CREATE TABLE `user_progress` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`          INT NOT NULL,
  `weight_kg`        DECIMAL(5,2) NOT NULL,
  `stress_level`     INT DEFAULT 5 CHECK (`stress_level` BETWEEN 1 AND 10),
  `motivation_level` INT DEFAULT 5 CHECK (`motivation_level` BETWEEN 1 AND 10),
  `energy_level`     INT DEFAULT 5 CHECK (`energy_level` BETWEEN 1 AND 10),
  `notes`            TEXT,
  `entry_date`       DATE NOT NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_user_date` (`user_id`, `entry_date`)
) ENGINE=InnoDB;

-- ============================================================
-- RECIPES
-- ============================================================
CREATE TABLE `recipes` (
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
  `satiety_score`    INT DEFAULT 5 CHECK (`satiety_score` BETWEEN 1 AND 10),
  `available_months` VARCHAR(50) DEFAULT '1,2,3,4,5,6,7,8,9,10,11,12',
  `image_url`        VARCHAR(500) DEFAULT NULL
) ENGINE=InnoDB;

-- ============================================================
-- INGREDIENT BLACKLIST (user dislikes)
-- ============================================================
CREATE TABLE `user_dislikes` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT NOT NULL,
  `ingredient_name` VARCHAR(100) NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_user_ingredient` (`user_id`, `ingredient_name`)
) ENGINE=InnoDB;

-- ============================================================
-- WEEKLY PLANS
-- ============================================================
CREATE TABLE `weekly_plans` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT NOT NULL,
  `start_date`     DATE NOT NULL,
  `end_date`       DATE NOT NULL,
  `target_calories` INT NOT NULL,
  `zone`           ENUM('green','yellow','red') NOT NULL DEFAULT 'yellow',
  `plan_data_json` LONGTEXT NOT NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- HEALTH TIPS
-- ============================================================
CREATE TABLE `health_tips` (
  `id`       INT AUTO_INCREMENT PRIMARY KEY,
  `category` ENUM('nutrition','fitness','beauty','mindset','sleep') NOT NULL,
  `tip_text` TEXT NOT NULL,
  `icon`     VARCHAR(50) DEFAULT 'leaf'
) ENGINE=InnoDB;

-- ============================================================
-- SAMPLE RECIPES (starter data)
-- ============================================================
INSERT INTO `recipes` (`title`, `category`, `calories`, `protein_g`, `carbs_g`, `fat_g`, `prep_minutes`, `ingredients_json`, `instructions`, `is_vegan`, `is_gluten_free`, `satiety_score`, `available_months`) VALUES
-- BREAKFAST
('Greek Yogurt with Honey & Walnuts', 'breakfast', 320, 18, 30, 12, 5,
 '["200g Greek yogurt","1 tbsp honey","30g walnuts","1 pinch cinnamon"]',
 'Mix yogurt with honey. Top with crushed walnuts and cinnamon.', FALSE, TRUE, 7, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Oat Porridge with Banana & Almond Butter', 'breakfast', 410, 12, 58, 14, 10,
 '["80g rolled oats","250ml milk","1 banana","1 tbsp almond butter","1 tsp chia seeds"]',
 'Cook oats with milk. Slice banana on top, drizzle almond butter, sprinkle chia seeds.', FALSE, TRUE, 8, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Scrambled Eggs with Whole Wheat Toast', 'breakfast', 380, 22, 32, 16, 10,
 '["3 eggs","2 slices whole wheat bread","1 tsp butter","salt","pepper","fresh chives"]',
 'Scramble eggs in butter over low heat. Serve on toasted bread with chives.', FALSE, FALSE, 8, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Smoothie Bowl (Strawberry & Banana)', 'breakfast', 290, 8, 52, 6, 8,
 '["150g frozen strawberries","1 banana","100ml almond milk","2 tbsp granola","1 tbsp pumpkin seeds"]',
 'Blend fruits with milk until thick. Pour in bowl, top with granola and seeds.', TRUE, TRUE, 6, '4,5,6,7,8,9,10'),

('Avocado Toast with Poached Egg', 'breakfast', 350, 14, 28, 20, 12,
 '["1 ripe avocado","2 slices sourdough bread","2 eggs","lemon juice","chili flakes","salt"]',
 'Toast bread. Mash avocado with lemon, salt. Top with poached eggs and chili flakes.', FALSE, FALSE, 9, '1,2,3,4,5,6,7,8,9,10,11,12'),

-- LUNCH
('Grilled Chicken Salad with Quinoa', 'lunch', 480, 42, 38, 14, 20,
 '["150g chicken breast","80g quinoa","mixed greens","cherry tomatoes","cucumber","olive oil","lemon"]',
 'Grill chicken. Cook quinoa. Toss with greens, tomatoes, cucumber, dress with oil & lemon.', FALSE, TRUE, 8, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Lentil Soup with Crusty Bread', 'lunch', 420, 22, 62, 8, 35,
 '["200g red lentils","1 onion","2 carrots","2 garlic cloves","cumin","turmeric","olive oil","1 slice bread"]',
 'Sauté onion and garlic. Add lentils, carrots, spices and water. Simmer 25 min. Blend half.', TRUE, FALSE, 9, '10,11,12,1,2,3'),

('Turkey & Veggie Wrap', 'lunch', 440, 34, 42, 12, 12,
 '["120g sliced turkey breast","1 whole wheat tortilla","lettuce","tomato","cucumber","hummus","feta"]',
 'Spread hummus on tortilla. Layer turkey, veggies, feta. Roll tightly.', FALSE, FALSE, 8, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Tuna Niçoise Salad', 'lunch', 460, 38, 22, 22, 15,
 '["150g canned tuna","2 boiled eggs","green beans","olives","cherry tomatoes","potatoes","dijon dressing"]',
 'Arrange all ingredients on a plate. Drizzle with dijon vinaigrette.', FALSE, TRUE, 9, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Chickpea & Spinach Stew', 'lunch', 390, 18, 52, 10, 25,
 '["400g canned chickpeas","200g fresh spinach","1 can diced tomatoes","onion","garlic","paprika","cumin"]',
 'Sauté aromatics. Add tomatoes, spices, chickpeas. Simmer 15 min. Stir in spinach.', TRUE, TRUE, 8, '1,2,3,4,5,6,7,8,9,10,11,12'),

-- DINNER
('Baked Salmon with Roasted Vegetables', 'dinner', 520, 44, 24, 26, 30,
 '["200g salmon fillet","1 courgette","1 red pepper","cherry tomatoes","olive oil","herbs","lemon"]',
 'Season salmon. Chop veggies, toss with oil. Roast at 200°C for 25 min, salmon last 15 min.', FALSE, TRUE, 9, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Chicken Stir-fry with Brown Rice', 'dinner', 490, 40, 52, 10, 20,
 '["150g chicken breast","150g brown rice","broccoli","snap peas","carrots","soy sauce","ginger","garlic"]',
 'Cook rice. Stir-fry chicken in wok. Add veggies, soy sauce, ginger, garlic. Serve over rice.', FALSE, TRUE, 8, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Beef & Vegetable Bolognese', 'dinner', 560, 38, 58, 18, 40,
 '["150g lean minced beef","whole wheat spaghetti 100g","canned tomatoes","onion","celery","carrot","garlic"]',
 'Sauté veggies. Brown beef. Add tomatoes, simmer 30 min. Serve with al-dente pasta.', FALSE, FALSE, 9, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Baked Cod with Sweet Potato Mash', 'dinner', 440, 38, 42, 10, 35,
 '["200g cod fillet","2 medium sweet potatoes","milk","butter","lemon","dill","salt","pepper"]',
 'Bake cod at 180°C for 20 min. Boil sweet potatoes, mash with milk and butter. Serve with lemon dill sauce.', FALSE, TRUE, 8, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Tofu & Vegetable Curry', 'dinner', 430, 20, 48, 16, 30,
 '["300g firm tofu","coconut milk","curry paste","chickpeas","spinach","onion","garlic","ginger","brown rice"]',
 'Fry tofu until golden. Make curry sauce with aromatics and coconut milk. Simmer with chickpeas and spinach. Serve with rice.', TRUE, TRUE, 8, '1,2,3,4,5,6,7,8,9,10,11,12'),

-- SNACKS
('Apple with Almond Butter', 'snack', 200, 5, 26, 10, 2,
 '["1 medium apple","2 tbsp almond butter"]',
 'Slice apple. Serve with almond butter for dipping.', TRUE, TRUE, 7, '9,10,11,1,2,3,4,5'),

('Cottage Cheese with Berries', 'snack', 180, 16, 18, 4, 2,
 '["150g cottage cheese","100g mixed berries","1 tsp honey"]',
 'Top cottage cheese with fresh berries and drizzle with honey.', FALSE, TRUE, 7, '5,6,7,8,9'),

('Hummus & Veggie Sticks', 'snack', 160, 6, 20, 6, 5,
 '["80g hummus","1 carrot","1 celery stick","1 cucumber"]',
 'Cut vegetables into sticks. Serve with hummus.', TRUE, TRUE, 6, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Mixed Nuts & Dried Fruit', 'snack', 220, 6, 22, 14, 1,
 '["20g almonds","15g walnuts","15g cashews","20g raisins"]',
 'Mix together and enjoy as a handful.', TRUE, TRUE, 6, '1,2,3,4,5,6,7,8,9,10,11,12'),

('Protein Shake with Banana', 'snack', 240, 22, 30, 4, 3,
 '["1 scoop whey protein","1 banana","250ml milk","1 tsp chia seeds"]',
 'Blend all ingredients until smooth.', FALSE, TRUE, 7, '1,2,3,4,5,6,7,8,9,10,11,12');

-- ============================================================
-- SAMPLE HEALTH TIPS
-- ============================================================
INSERT INTO `health_tips` (`category`, `tip_text`, `icon`) VALUES
('nutrition', 'Drink at least 8 glasses of water daily. Hydration boosts metabolism and reduces hunger.', 'droplets'),
('nutrition', 'Eat slowly — it takes 20 minutes for your brain to register fullness. Put your fork down between bites.', 'clock'),
('nutrition', 'Prioritize protein at every meal. It has the highest satiety per calorie and preserves muscle during fat loss.', 'beef'),
('fitness', 'A 20-minute brisk walk after dinner significantly improves insulin sensitivity and fat burning overnight.', 'footprints'),
('fitness', 'Resistance training 2-3x per week preserves lean muscle mass, which keeps your metabolism elevated.', 'dumbbell'),
('beauty', 'Collagen production needs Vitamin C. Include bell peppers, citrus, or kiwi daily for glowing skin.', 'sparkles'),
('beauty', 'Sleep is your best beauty treatment. Aim for 7-9 hours — cortisol from poor sleep triggers fat storage and breakouts.', 'moon'),
('mindset', 'Progress, not perfection. One "off" meal does not ruin your week. Consistency over time is what matters.', 'target'),
('mindset', 'Set a weekly non-food reward for hitting your goals — a movie, a walk in nature, a new book.', 'gift'),
('sleep', 'Avoid screens 1 hour before bed. Blue light suppresses melatonin, delaying sleep and increasing cortisol.', 'monitor-off'),
('nutrition', 'Meal prep on Sundays. Having healthy food ready removes decision fatigue and prevents impulse eating.', 'chef-hat'),
('fitness', 'Take the stairs. Small movements throughout the day (NEAT) can add up to 300-500 extra calories burned.', 'trending-up');
