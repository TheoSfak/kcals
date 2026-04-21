-- ============================================================
-- Migration 011: Bilingual health tips (add tip_text_el)
-- Run this on any existing install to add Greek translations.
-- ============================================================

-- Step 1: add the Greek column (does nothing if it already exists)
ALTER TABLE `health_tips`
    ADD COLUMN IF NOT EXISTS `tip_text_el` TEXT NOT NULL DEFAULT '' AFTER `tip_text`;

-- Step 2: replace all tips with both EN and EL content
TRUNCATE TABLE `health_tips`;

INSERT INTO `health_tips` (`category`, `tip_text`, `tip_text_el`, `icon`) VALUES
('nutrition',
 'Drink at least 8 glasses of water daily. Hydration boosts metabolism and reduces hunger.',
 'Πίνετε τουλάχιστον 8 ποτήρια νερό την ημέρα. Η ενυδάτωση επιταχύνει τον μεταβολισμό και μειώνει την πείνα.',
 'droplets'),

('nutrition',
 'Eat slowly — it takes 20 minutes for your brain to register fullness. Put your fork down between bites.',
 'Τρώτε αργά — ο εγκέφαλος χρειάζεται 20 λεπτά για να νιώσει κορεσμό. Αφήνετε το πιρούνι ανάμεσα στις μπουκιές.',
 'clock'),

('nutrition',
 'Prioritize protein at every meal. It has the highest satiety per calorie and preserves muscle during fat loss.',
 'Δίνετε προτεραιότητα στην πρωτεΐνη σε κάθε γεύμα. Έχει τον υψηλότερο κορεσμό ανά θερμίδα και διατηρεί τη μυϊκή μάζα κατά την απώλεια λίπους.',
 'beef'),

('fitness',
 'A 20-minute brisk walk after dinner significantly improves insulin sensitivity and fat burning overnight.',
 'Μια έντονη βόλτα 20 λεπτών μετά το δείπνο βελτιώνει σημαντικά την ευαισθησία στην ινσουλίνη και την καύση λίπους κατά τη νύχτα.',
 'footprints'),

('fitness',
 'Resistance training 2-3x per week preserves lean muscle mass, which keeps your metabolism elevated.',
 'Η προπόνηση αντίστασης 2-3 φορές/εβδομάδα διατηρεί τη μυϊκή μάζα και κρατά τον μεταβολισμό σε υψηλά επίπεδα.',
 'dumbbell'),

('beauty',
 'Collagen production needs Vitamin C. Include bell peppers, citrus, or kiwi daily for glowing skin.',
 'Η παραγωγή κολλαγόνου χρειάζεται Βιταμίνη C. Συμπεριλάβετε πιπεριές, εσπεριδοειδή ή ακτινίδιο καθημερινά για λαμπερό δέρμα.',
 'sparkles'),

('beauty',
 'Sleep is your best beauty treatment. Aim for 7-9 hours — cortisol from poor sleep triggers fat storage and breakouts.',
 'Ο ύπνος είναι η καλύτερη θεραπεία ομορφιάς. Στοχεύστε 7-9 ώρες — η κορτιζόλη από τον κακό ύπνο πυροδοτεί αποθήκευση λίπους και σπυράκια.',
 'moon'),

('mindset',
 'Progress, not perfection. One "off" meal does not ruin your week. Consistency over time is what matters.',
 'Πρόοδος, όχι τελειομανία. Ένα «κακό» γεύμα δεν καταστρέφει την εβδομάδα σας. Η συνέπεια στον χρόνο είναι αυτό που μετράει.',
 'target'),

('mindset',
 'Set a weekly non-food reward for hitting your goals — a movie, a walk in nature, a new book.',
 'Ορίστε μια εβδομαδιαία ανταμοιβή χωρίς φαγητό για την επίτευξη των στόχων σας — ταινία, βόλτα στη φύση, καινούριο βιβλίο.',
 'gift'),

('sleep',
 'Avoid screens 1 hour before bed. Blue light suppresses melatonin, delaying sleep and increasing cortisol.',
 'Αποφεύγετε τις οθόνες 1 ώρα πριν τον ύπνο. Το μπλε φως καταστέλλει τη μελατονίνη, καθυστερεί τον ύπνο και αυξάνει την κορτιζόλη.',
 'monitor-off'),

('nutrition',
 'Meal prep on Sundays. Having healthy food ready removes decision fatigue and prevents impulse eating.',
 'Ετοιμάστε γεύματα τις Κυριακές. Έχοντας υγιεινό φαγητό έτοιμο εξαλείφετε την κόπωση αποφάσεων και αποφεύγετε τις παρορμητικές επιλογές.',
 'chef-hat'),

('fitness',
 'Take the stairs. Small movements throughout the day (NEAT) can add up to 300-500 extra calories burned.',
 'Ανεβείτε σκάλες. Οι μικρές κινήσεις κατά τη διάρκεια της ημέρας (NEAT) μπορούν να αθροιστούν σε 300-500 επιπλέον θερμίδες που καίγονται.',
 'trending-up');

-- Step 3: record migration
INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('011_tips_bilingual.sql');
