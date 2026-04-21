<?php
// ============================================================
// KCALS – Admin: Reseed Foods Table (fix Greek encoding)
// Run once to replace all food rows with correct utf8mb4 data.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin_auth.php';

requireAdmin();

$db = getDB();
$done  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        try {
            $db->beginTransaction();

            $db->exec('DELETE FROM `foods`');

            $stmt = $db->prepare('
                INSERT INTO `foods`
                  (`name_en`, `name_el`, `food_type`, `meal_slots`,
                   `cal_per_100g`, `protein_per_100g`, `carbs_per_100g`, `fat_per_100g`,
                   `is_vegan`, `is_vegetarian`, `is_gluten_free`, `is_keto_ok`, `is_paleo_ok`,
                   `available_months`, `min_serving_g`, `max_serving_g`, `prep_minutes`)
                VALUES (?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?)
            ');

            $foods = [
                // ── PROTEINS: meat / fish ──────────────────────────────────
                ['Chicken Breast',           'Στήθος Κοτόπουλου',    'protein', 'lunch,dinner',
                  165.00, 31.00,  0.00,  3.60,  0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250, 20],
                ['Salmon',                   'Σολομός',              'protein', 'lunch,dinner',
                  208.00, 20.00,  0.00, 13.00,  0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 200, 15],
                ['Canned Tuna',              'Τόνος (κονσέρβα)',     'protein', 'lunch,dinner',
                  116.00, 26.00,  0.00,  1.00,  0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80, 180,  5],
                ['Cod',                      'Μπακαλιάρος',          'protein', 'lunch,dinner',
                   82.00, 18.00,  0.00,  0.70,  0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 220, 15],
                ['Turkey Breast',            'Γαλοπούλα',            'protein', 'lunch,dinner',
                  135.00, 30.00,  0.00,  1.00,  0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250, 20],
                ['Lean Beef Mince',          'Κιμάς Μοσχαρίσιος',   'protein', 'dinner',
                  215.00, 26.00,  0.00, 12.00,  0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 200, 25],
                ['Shrimp',                   'Γαρίδες',              'protein', 'lunch,dinner',
                   99.00, 24.00,  0.00,  0.60,  0,0,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250, 10],
                // ── PROTEINS: eggs ──────────────────────────────────────────
                ['Eggs',                     'Αυγά',                 'protein', 'breakfast,lunch',
                  155.00, 13.00,  1.10, 11.00,  0,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 200, 10],
                // ── PROTEINS: vegan / legumes ───────────────────────────────
                ['Firm Tofu',                'Τόφου',                'protein', 'breakfast,lunch,dinner',
                   76.00,  8.00,  2.00,  4.80,  1,1,1,1,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250, 15],
                ['Chickpeas (cooked)',        'Ρεβύθια',              'protein', 'lunch,dinner',
                  164.00,  9.00, 27.00,  2.60,  1,1,1,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  5],
                ['Lentils (cooked)',          'Φακές',                'protein', 'lunch,dinner',
                  116.00,  9.00, 20.00,  0.40,  1,1,1,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  5],
                ['Black Beans (cooked)',      'Μαύρα Φασόλια',        'protein', 'lunch,dinner',
                  132.00,  9.00, 24.00,  0.50,  1,1,1,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  5],
                // ── DAIRY ───────────────────────────────────────────────────
                ['Greek Yogurt',             'Ελληνικό Γιαούρτι',   'dairy', 'breakfast,snack',
                   97.00,  9.00,  4.00,  5.00,  0,1,1,1,0, '1,2,3,4,5,6,7,8,9,10,11,12', 150, 300,  2],
                ['Cottage Cheese',           'Κότατζ Τσιζ',          'dairy', 'breakfast,snack',
                   98.00, 11.00,  3.40,  4.30,  0,1,1,1,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  2],
                ['Feta Cheese',              'Φέτα',                 'dairy', 'lunch,dinner',
                  264.00, 14.00,  4.00, 21.00,  0,1,1,1,0, '1,2,3,4,5,6,7,8,9,10,11,12',  30,  80,  1],
                // ── CARBS: grains ───────────────────────────────────────────
                ['Oats',                     'Βρώμη',                'carb', 'breakfast',
                  389.00, 17.00, 66.00,  7.00,  1,1,0,0,0, '1,2,3,4,5,6,7,8,9,10,11,12',  50, 100, 10],
                ['Brown Rice (cooked)',       'Καστανό Ρύζι',         'carb', 'lunch,dinner',
                  111.00,  2.60, 23.00,  0.90,  1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  5],
                ['White Rice (cooked)',       'Λευκό Ρύζι',           'carb', 'lunch,dinner',
                  130.00,  2.70, 28.00,  0.30,  1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  5],
                ['Whole Wheat Pasta (cooked)','Ζυμαρικά Ολικής',     'carb', 'lunch,dinner',
                  124.00,  5.00, 25.00,  1.10,  1,1,0,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  5],
                ['Pasta (cooked)',            'Ζυμαρικά',             'carb', 'lunch,dinner',
                  131.00,  5.00, 25.00,  1.10,  1,1,0,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  5],
                ['Sweet Potato (cooked)',     'Γλυκοπατάτα',          'carb', 'lunch,dinner',
                   90.00,  2.00, 21.00,  0.10,  1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  5],
                ['Potato (boiled)',           'Πατάτα βραστή',        'carb', 'lunch,dinner',
                   87.00,  1.90, 20.00,  0.10,  1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  5],
                ['Quinoa (cooked)',           'Κινόα',                'carb', 'lunch,dinner',
                  120.00,  4.40, 22.00,  1.90,  1,1,1,0,0, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  5],
                ['Whole Wheat Bread',         'Ψωμί Ολικής',          'carb', 'breakfast',
                  247.00, 13.00, 41.00,  3.40,  1,1,0,0,0, '1,2,3,4,5,6,7,8,9,10,11,12',  40, 100,  2],
                ['Sourdough Bread',           'Ψωμί Προζύμι',         'carb', 'breakfast',
                  274.00,  9.00, 55.00,  1.50,  1,1,0,0,0, '1,2,3,4,5,6,7,8,9,10,11,12',  40, 100,  2],
                // ── FRUITS ──────────────────────────────────────────────────
                ['Banana',                   'Μπανάνα',              'fruit', 'breakfast,snack',
                   89.00,  1.10, 23.00,  0.30,  1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80, 200,  1],
                ['Apple',                    'Μήλο',                 'fruit', 'snack',
                   52.00,  0.30, 14.00,  0.20,  1,1,1,0,1, '1,2,3,4,5,6,7,8,9,10,11,12', 100, 250,  1],
                ['Strawberries',             'Φράουλες',             'fruit', 'breakfast,snack',
                   32.00,  0.70,  7.70,  0.30,  1,1,1,1,1, '4,5,6,7,8,9',               100, 250,  2],
                ['Blueberries',              'Μύρτιλα',              'fruit', 'breakfast,snack',
                   57.00,  0.70, 14.00,  0.30,  1,1,1,1,1, '6,7,8,9',                    80, 200,  1],
                ['Orange',                   'Πορτοκάλι',            'fruit', 'snack',
                   47.00,  0.90, 12.00,  0.10,  1,1,1,0,1, '11,12,1,2,3,4,5',           130, 300,  1],
                ['Grapes',                   'Σταφύλια',             'fruit', 'snack',
                   69.00,  0.70, 18.00,  0.20,  1,1,1,0,1, '7,8,9,10',                  100, 200,  1],
                ['Kiwi',                     'Ακτινίδιο',            'fruit', 'breakfast,snack',
                   61.00,  1.10, 15.00,  0.50,  1,1,1,0,1, '1,2,3,4,5,10,11,12',        100, 250,  1],
                // ── VEGETABLES ──────────────────────────────────────────────
                ['Spinach',                  'Σπανάκι',              'vegetable', 'lunch,dinner',
                   23.00,  2.90,  3.60,  0.40,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80, 200,  5],
                ['Broccoli',                 'Μπρόκολο',             'vegetable', 'lunch,dinner',
                   34.00,  2.80,  7.00,  0.40,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80, 200,  8],
                ['Tomato',                   'Ντομάτα',              'vegetable', 'lunch,dinner',
                   18.00,  0.90,  3.90,  0.20,  1,1,1,1,1, '5,6,7,8,9,10',                80, 250,  2],
                ['Cucumber',                 'Αγγούρι',              'vegetable', 'lunch,dinner',
                   15.00,  0.70,  3.60,  0.10,  1,1,1,1,1, '4,5,6,7,8,9,10',              80, 250,  2],
                ['Zucchini',                 'Κολοκυθάκι',           'vegetable', 'lunch,dinner',
                   18.00,  1.20,  3.10,  0.30,  1,1,1,1,1, '5,6,7,8,9,10',                80, 200,  8],
                ['Bell Pepper',              'Πιπεριά',              'vegetable', 'lunch,dinner',
                   31.00,  1.00,  6.00,  0.30,  1,1,1,1,1, '5,6,7,8,9,10',                80, 200,  5],
                ['Carrot',                   'Καρότο',               'vegetable', 'lunch,dinner',
                   41.00,  0.90, 10.00,  0.20,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80, 200,  5],
                ['Green Beans',              'Φασολάκια',            'vegetable', 'lunch,dinner',
                   31.00,  1.80,  7.00,  0.10,  1,1,1,1,1, '5,6,7,8,9,10',                80, 200, 10],
                ['Mixed Greens',             'Μικτή Σαλάτα',         'vegetable', 'lunch,dinner',
                   15.00,  1.40,  2.90,  0.20,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80, 200,  3],
                ['Eggplant',                 'Μελιτζάνα',            'vegetable', 'lunch,dinner',
                   25.00,  1.00,  6.00,  0.20,  1,1,1,1,1, '6,7,8,9',                      80, 200, 15],
                ['Mushrooms',                'Μανιτάρια',            'vegetable', 'lunch,dinner',
                   22.00,  3.10,  3.30,  0.30,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80, 200,  8],
                ['Cauliflower',              'Κουνουπίδι',           'vegetable', 'lunch,dinner',
                   25.00,  1.90,  5.00,  0.30,  1,1,1,1,1, '9,10,11,12,1,2,3',             80, 200,  8],
                // ── FATS ────────────────────────────────────────────────────
                ['Olive Oil',                'Ελαιόλαδο',            'fat', 'breakfast,lunch,dinner',
                  884.00,  0.00,  0.00,100.00,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',   5,  30,  0],
                ['Almonds',                  'Αμύγδαλα',             'fat', 'snack',
                  579.00, 21.00, 22.00, 50.00,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  20,  60,  1],
                ['Walnuts',                  'Καρύδια',              'fat', 'snack',
                  654.00, 15.00, 14.00, 65.00,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  15,  50,  1],
                ['Avocado',                  'Αβοκάντο',             'fat', 'breakfast,lunch',
                  160.00,  2.00,  9.00, 15.00,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  80, 150,  2],
                ['Almond Butter',            'Βούτυρο Αμυγδάλου',   'fat', 'breakfast,snack',
                  614.00, 21.00, 20.00, 56.00,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  15,  40,  1],
                ['Mixed Nuts',               'Μικτοί Ξηροί Καρποί', 'fat', 'snack',
                  607.00, 20.00, 21.00, 54.00,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  20,  50,  1],
                ['Cashews',                  'Κάσιους',              'fat', 'snack',
                  553.00, 18.00, 30.00, 44.00,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  20,  50,  1],
                ['Tahini',                   'Ταχίνι',               'fat', 'snack',
                  595.00, 17.00, 21.00, 54.00,  1,1,1,1,1, '1,2,3,4,5,6,7,8,9,10,11,12',  15,  40,  1],
            ];

            foreach ($foods as $f) {
                $stmt->execute($f);
            }

            $db->commit();
            $done = true;

        } catch (PDOException $e) {
            $db->rollBack();
            error_log('reseed_foods error: ' . $e->getMessage());
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$count = (int) $db->query('SELECT COUNT(*) FROM `foods`')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reseed Foods – KCALS Admin</title>
<style>
  body { font-family: sans-serif; max-width: 600px; margin: 3rem auto; padding: 0 1.5rem; color: #2c3e50; }
  .ok  { background: #d4edda; border: 1px solid #28a745; color: #155724; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
  .err { background: #f8d7da; border: 1px solid #dc3545; color: #721c24; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
  .btn { background: #2ecc71; color: #fff; border: none; padding: .75rem 2rem; font-size: 1rem; border-radius: 6px; cursor: pointer; }
  .btn:hover { background: #27ae60; }
  table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; font-size: .9rem; }
  th, td { padding: .4rem .6rem; border: 1px solid #dee2e6; text-align: left; }
  th { background: #f7f9fc; }
</style>
</head>
<body>
<h1>🌿 Reseed Foods Table</h1>
<p>Current rows in <code>foods</code>: <strong><?= $count ?></strong></p>
<p>This will <strong>delete all existing food rows</strong> and re-insert <?= count($foods ?? []) ?> foods with correct UTF-8 Greek names.</p>

<?php if ($done): ?>
<div class="ok">✅ Done! <?= $count ?> foods inserted successfully. Greek names are now correct.</div>
<p><a href="<?= BASE_URL ?>/plan.php?generate=1&csrf=<?= urlencode(csrfToken()) ?>">→ Generate a fresh plan to verify</a></p>
<?php elseif ($error): ?>
<div class="err">❌ <?= $error ?></div>
<?php endif; ?>

<?php if (!$done): ?>
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <button type="submit" class="btn">Run Reseed Now</button>
</form>
<?php endif; ?>

</body>
</html>
