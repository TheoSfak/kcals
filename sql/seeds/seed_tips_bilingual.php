<?php
// ============================================================
// Seed: Bilingual Health Tips (EN + EL)
// Run once via browser: http://localhost/kcals/sql/seeds/seed_tips_bilingual.php
// Or CLI: php seed_tips_bilingual.php
// DELETE this file from the server after running.
// ============================================================
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';

$db = getDB();

// 1. Add tip_text_el column if missing
$db->exec("
    ALTER TABLE `health_tips`
        ADD COLUMN IF NOT EXISTS `tip_text_el` TEXT NOT NULL DEFAULT '' AFTER `tip_text`
");

// 2. Replace all tips
$db->exec("TRUNCATE TABLE `health_tips`");

$tips = [
    [
        'category'    => 'nutrition',
        'tip_text'    => 'Drink at least 8 glasses of water daily. Hydration boosts metabolism and reduces hunger.',
        'tip_text_el' => 'Πίνετε τουλάχιστον 8 ποτήρια νερό την ημέρα. Η ενυδάτωση επιταχύνει τον μεταβολισμό και μειώνει την πείνα.',
        'icon'        => 'droplets',
    ],
    [
        'category'    => 'nutrition',
        'tip_text'    => 'Eat slowly — it takes 20 minutes for your brain to register fullness. Put your fork down between bites.',
        'tip_text_el' => 'Τρώτε αργά — ο εγκέφαλος χρειάζεται 20 λεπτά για να νιώσει κορεσμό. Αφήνετε το πιρούνι ανάμεσα στις μπουκιές.',
        'icon'        => 'clock',
    ],
    [
        'category'    => 'nutrition',
        'tip_text'    => 'Prioritize protein at every meal. It has the highest satiety per calorie and preserves muscle during fat loss.',
        'tip_text_el' => 'Δίνετε προτεραιότητα στην πρωτεΐνη σε κάθε γεύμα. Έχει τον υψηλότερο κορεσμό ανά θερμίδα και διατηρεί τη μυϊκή μάζα κατά την απώλεια λίπους.',
        'icon'        => 'beef',
    ],
    [
        'category'    => 'fitness',
        'tip_text'    => 'A 20-minute brisk walk after dinner significantly improves insulin sensitivity and fat burning overnight.',
        'tip_text_el' => 'Μια έντονη βόλτα 20 λεπτών μετά το δείπνο βελτιώνει σημαντικά την ευαισθησία στην ινσουλίνη και την καύση λίπους κατά τη νύχτα.',
        'icon'        => 'footprints',
    ],
    [
        'category'    => 'fitness',
        'tip_text'    => 'Resistance training 2-3x per week preserves lean muscle mass, which keeps your metabolism elevated.',
        'tip_text_el' => 'Η προπόνηση αντίστασης 2-3 φορές την εβδομάδα διατηρεί τη μυϊκή μάζα και κρατά τον μεταβολισμό σε υψηλά επίπεδα.',
        'icon'        => 'dumbbell',
    ],
    [
        'category'    => 'beauty',
        'tip_text'    => 'Collagen production needs Vitamin C. Include bell peppers, citrus, or kiwi daily for glowing skin.',
        'tip_text_el' => 'Η παραγωγή κολλαγόνου χρειάζεται Βιταμίνη C. Συμπεριλάβετε πιπεριές, εσπεριδοειδή ή ακτινίδιο καθημερινά για λαμπερό δέρμα.',
        'icon'        => 'sparkles',
    ],
    [
        'category'    => 'beauty',
        'tip_text'    => 'Sleep is your best beauty treatment. Aim for 7-9 hours — cortisol from poor sleep triggers fat storage and breakouts.',
        'tip_text_el' => 'Ο ύπνος είναι η καλύτερη θεραπεία ομορφιάς. Στοχεύστε 7-9 ώρες — η κορτιζόλη από τον κακό ύπνο πυροδοτεί αποθήκευση λίπους και σπυράκια.',
        'icon'        => 'moon',
    ],
    [
        'category'    => 'mindset',
        'tip_text'    => 'Progress, not perfection. One "off" meal does not ruin your week. Consistency over time is what matters.',
        'tip_text_el' => 'Πρόοδος, όχι τελειομανία. Ένα "κακό" γεύμα δεν καταστρέφει την εβδομάδα σας. Η συνέπεια στον χρόνο είναι αυτό που μετράει.',
        'icon'        => 'target',
    ],
    [
        'category'    => 'mindset',
        'tip_text'    => 'Set a weekly non-food reward for hitting your goals — a movie, a walk in nature, a new book.',
        'tip_text_el' => 'Ορίστε μια εβδομαδιαία ανταμοιβή χωρίς φαγητό για την επίτευξη των στόχων σας — ταινία, βόλτα στη φύση, καινούριο βιβλίο.',
        'icon'        => 'gift',
    ],
    [
        'category'    => 'sleep',
        'tip_text'    => 'Avoid screens 1 hour before bed. Blue light suppresses melatonin, delaying sleep and increasing cortisol.',
        'tip_text_el' => 'Αποφεύγετε τις οθόνες 1 ώρα πριν τον ύπνο. Το μπλε φως καταστέλλει τη μελατονίνη, καθυστερεί τον ύπνο και αυξάνει την κορτιζόλη.',
        'icon'        => 'monitor-off',
    ],
    [
        'category'    => 'nutrition',
        'tip_text'    => 'Meal prep on Sundays. Having healthy food ready removes decision fatigue and prevents impulse eating.',
        'tip_text_el' => 'Ετοιμάστε γεύματα τις Κυριακές. Έχοντας υγιεινό φαγητό έτοιμο εξαλείφετε την κόπωση αποφάσεων και αποφεύγετε τις παρορμητικές επιλογές.',
        'icon'        => 'chef-hat',
    ],
    [
        'category'    => 'fitness',
        'tip_text'    => 'Take the stairs. Small movements throughout the day (NEAT) can add up to 300-500 extra calories burned.',
        'tip_text_el' => 'Ανεβείτε σκάλες. Οι μικρές κινήσεις κατά τη διάρκεια της ημέρας (NEAT) μπορούν να αθροιστούν σε 300-500 επιπλέον θερμίδες που καίγονται.',
        'icon'        => 'trending-up',
    ],
];

$stmt = $db->prepare("
    INSERT INTO `health_tips` (`category`, `tip_text`, `tip_text_el`, `icon`)
    VALUES (:category, :tip_text, :tip_text_el, :icon)
");

foreach ($tips as $tip) {
    $stmt->execute($tip);
}

// 3. Record migration
$db->exec("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('011_tips_bilingual.sql')");

echo '<pre>Done. ' . count($tips) . ' tips inserted with Greek translations. Delete this file from the server.</pre>';
