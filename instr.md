# 🍏 NutriLife App - Technical Specification & Development Plan

## 1. Executive Summary
Ένα σύγχρονο Wellness Web App (Pure PHP & MySQL) που παρέχει εξατομικευμένα εβδομαδιαία προγράμματα διατροφής, παρακολούθηση προόδου (tracking) και συμβουλές υγείας/ομορφιάς. Το σύστημα βασίζεται σε μαθηματικούς αλγορίθμους και ψυχολογικό profiling για να προσαρμόζει τον ρυθμό απώλειας βάρους με ασφάλεια και βιωσιμότητα.

---

## 2. Phase 1: MVP (The Core Engine - No AI)
Ο πρώτος στόχος είναι ένα λειτουργικό, σταθερό σύστημα που λαμβάνει δεδομένα, υπολογίζει τις ενεργειακές ανάγκες και επιστρέφει ένα μαθηματικά ακριβές πρόγραμμα βάσει της μεθόδου Mifflin-St Jeor.

### 2.1 Database Schema (MySQL)
Ο βασικός σκελετός των πινάκων:

```sql
-- Πίνακας Χρηστών
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150),
  `gender` ENUM('male', 'female') NOT NULL,
  `birth_date` DATE NOT NULL,
  `height_cm` INT NOT NULL,
  `activity_level` DECIMAL(3,2) NOT NULL DEFAULT 1.20,
  `diet_type` VARCHAR(50) DEFAULT 'standard',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Πίνακας Ιστορικού (Tracking)
CREATE TABLE `user_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `weight_kg` DECIMAL(5,2) NOT NULL,
  `stress_level` INT DEFAULT 5, -- 1-10
  `motivation_level` INT DEFAULT 5, -- 1-10
  `entry_date` DATE NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Πίνακας Συνταγών
CREATE TABLE `recipes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `category` ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL,
  `calories` INT NOT NULL,
  `protein_g` INT NOT NULL,
  `carbs_g` INT NOT NULL,
  `fat_g` INT NOT NULL,
  `ingredients_json` TEXT NOT NULL, 
  `is_vegan` BOOLEAN DEFAULT FALSE,
  `is_gluten_free` BOOLEAN DEFAULT FALSE,
  `satiety_score` INT DEFAULT 5, 
  `available_months` VARCHAR(50) DEFAULT '1,2,3,4,5,6,7,8,9,10,11,12'
);

-- Πίνακας "Blacklist" Υλικών
CREATE TABLE `user_dislikes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `ingredient_name` VARCHAR(100) NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Πίνακας Παραγόμενων Προγραμμάτων
CREATE TABLE `weekly_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `plan_data_json` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
2.2 The Math Engine (Pure PHP)
Η λογική του υπολογισμού BMR και TDEE (Mifflin-St Jeor):

BMR Άνδρα: (10 * weight) + (6.25 * height) - (5 * age) + 5

BMR Γυναίκας: (10 * weight) + (6.25 * height) - (5 * age) - 161

TDEE: BMR * Activity_Level

Το script θα κάνει SELECT από τον πίνακα recipes με βάση το Target TDEE και τα user_dislikes, και θα αποθηκεύει το εβδομαδιαίο πλάνο ως JSON στον πίνακα weekly_plans.

3. Phase 2: Behavioral & Tracking (The "Smart" Layer)
Ενσωμάτωση λογικής για την αποφυγή της πνευματικής κόπωσης του χρήστη.

3.1 Psychological Profiling
Κατά την εγγραφή και στα εβδομαδιαία check-ins, ζητούνται επίπεδα στρες και κινήτρου.

Green Zone (Aggressive): Χαμηλό στρες, υψηλό κίνητρο -> Έλλειμμα 25% του TDEE.

Yellow Zone (Balanced): Ενδιάμεσες τιμές -> Έλλειμμα 15% του TDEE.

Red Zone (Sustainable): Υψηλό στρες -> Έλλειμμα 5-10% του TDEE (στόχος η συντήρηση της συνήθειας, όχι η γρήγορη απώλεια).

3.2 User Dashboard
Γράφημα Προόδου: Ελαφριά υλοποίηση με Chart.js για παρακολούθηση βάρους.

Daily/Weekly Check-in: Φόρμα καταγραφής ενέργειας, διάθεσης και βάρους.

Συμβουλές: Εμφάνιση 3 στοχευμένων health & beauty tips ανά εβδομάδα, τα οποία τραβιούνται τυχαία από έναν πίνακα health_tips.

4. Phase 3: Advanced Algorithms & Monetization
Κλιμάκωση της εφαρμογής με λειτουργίες που προσθέτουν σοβαρή αξία (Premium Features).

Social Buffer (Weekend Logic): Το σύστημα αφαιρεί αυτόματα ~150 θερμίδες/ημέρα από Δευτέρα έως Παρασκευή και τις προσθέτει στο Σάββατο, επιτρέποντας κοινωνικές εξόδους χωρίς να "σπάει" η δίαιτα.

Plateau Breaker (Metabolic Reset): Αν το σύστημα εντοπίσει στασιμότητα βάρους για 14+ ημέρες (διαφορά < 0.2kg), ρυθμίζει τις θερμίδες στο 100% του TDEE για μία εβδομάδα ώστε να "ξεκολλήσει" ο μεταβολισμός.

Seasonal Logic: Τα queries των συνταγών φιλτράρονται δυναμικά με βάση τον τρέχοντα μήνα και το πεδίο available_months, προτείνοντας φθηνά και εποχιακά υλικά.

Shopping List Generator: Ομαδοποίηση όλων των υλικών της εβδομάδας σε μια καθαρή, εκτυπώσιμη λίστα super market.

Monetization (Affiliate/Ads): Δυναμική προβολή banners συνεργαζόμενων γυμναστηρίων ή e-shops υγείας/ομορφιάς μέσα στο περιβάλλον του προγράμματος (geotargeted ανάλογα με την τοποθεσία του χρήστη).

5. UI/UX & Beautification Guidelines
Η εικόνα της εφαρμογής είναι το μισό προϊόν. Ο σχεδιασμός πρέπει να αποπνέει ηρεμία, οργάνωση και επαγγελματισμό.

Χρωματική Παλέτα:

Background: Clean White ή Very Light Gray.

Primary Accent: Mint Green (#2ECC71) για κουμπιά και success states (εκπέμπει φρεσκάδα και υγεία).

Text: Dark Slate (#2C3E50) για εύκολη ανάγνωση, όχι απόλυτο μαύρο.

Τυπογραφία: Σύγχρονες, καθαρές Sans-serif γραμματοσειρές (π.χ. Inter, Roboto ή Montserrat).

Δομή (Layout): Card-based σχεδιασμός. Κάθε ημέρα και κάθε γεύμα παρουσιάζεται σε δική του "κάρτα" με απαλές σκιές (box-shadow), δημιουργώντας αίσθηση βάθους.

Οπτικά Στοιχεία:

Χρήση υψηλής ποιότητας hero banners (π.χ. φρέσκα τρόφιμα, lifestyle φωτό).

Χρήση minimal SVG icons (π.χ. Lucide ή FontAwesome) για τα γεύματα (πρωινό, μεσημεριανό) και τα dashboards.

Mobile-First: Όλη η διεπαφή (κυρίως το εβδομαδιαίο πρόγραμμα και η λίστα αγορών) πρέπει να είναι άψογα σχεδιασμένη για οθόνες κινητών, καθώς οι χρήστες θα την συμβουλεύονται εν κινήσει ή στο super market.