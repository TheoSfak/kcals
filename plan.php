<?php
// ============================================================
// KCALS – Weekly Meal Plan Generator & Viewer
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/engine/calculator.php';

requireLogin();

$db     = getDB();
$user   = getCurrentUser();
$userId = (int) $_SESSION['user_id'];

$latestProgress = getLatestProgress($userId);

if (!$latestProgress) {
    header('Location: /dashboard.php');
    exit;
}

$stats = calculateUserStats($user, $latestProgress);

// ---- Generate / Regenerate Plan ----
$generated = false;
$genError  = '';

if (isset($_GET['generate']) && $_GET['generate'] == '1') {
    if (!verifyCsrf($_GET['csrf'] ?? '')) {
        $genError = 'Invalid request. Please try again.';
    } else {
        $currentMonth   = (int) date('n');
        $targetCalories = $stats['target_kcal'];
        $zone           = $stats['zone'];
        $dietType       = $user['diet_type'];

        // Meal calorie distribution (% of day):
        // Breakfast 25%, Lunch 35%, Dinner 30%, Snack 10%
        $mealTargets = [
            'breakfast' => (int) round($targetCalories * 0.25),
            'lunch'     => (int) round($targetCalories * 0.35),
            'dinner'    => (int) round($targetCalories * 0.30),
            'snack'     => (int) round($targetCalories * 0.10),
        ];

        // Load user's disliked ingredients
        $dislikeStmt = $db->prepare('SELECT ingredient_name FROM user_dislikes WHERE user_id = ?');
        $dislikeStmt->execute([$userId]);
        $dislikes = array_column($dislikeStmt->fetchAll(), 'ingredient_name');

        // Helper: pick a recipe for a given category, fitting calorie window ±20%
        $pickRecipe = function(string $category, int $targetKcal, array $usedIdsThisWeek) use ($db, $currentMonth, $dietType, $dislikes): ?array {
            $low  = (int) round($targetKcal * 0.80);
            $high = (int) round($targetKcal * 1.20);

            $whereExtra = '';
            $params     = [$category, $low, $high];

            // Seasonal filter
            $params[] = '%' . $currentMonth . '%';
            $whereExtra .= ' AND available_months LIKE ?';

            // Diet filter
            if ($dietType === 'vegan' || $dietType === 'vegan_gf') {
                $whereExtra .= ' AND is_vegan = 1';
            }
            if ($dietType === 'gluten_free' || $dietType === 'vegan_gf') {
                $whereExtra .= ' AND is_gluten_free = 1';
            }

            // Exclude already-used IDs this week
            $excludeSql = '';
            if (!empty($usedIdsThisWeek)) {
                $placeholders = implode(',', array_fill(0, count($usedIdsThisWeek), '?'));
                $excludeSql   = " AND id NOT IN ($placeholders)";
                $params       = array_merge($params, $usedIdsThisWeek);
            }

            $sql  = "SELECT * FROM recipes
                     WHERE category = ? AND calories BETWEEN ? AND ?
                     $whereExtra $excludeSql
                     ORDER BY RAND() LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $recipe = $stmt->fetch();
            if (!$recipe) return null;

            // Client-side dislike check on ingredients JSON
            if (!empty($dislikes)) {
                $ingredients = strtolower($recipe['ingredients_json']);
                foreach ($dislikes as $disliked) {
                    if (strpos($ingredients, strtolower($disliked)) !== false) {
                        // Try once more without the blacklist enforcement if no other option
                        return null;
                    }
                }
            }
            return $recipe;
        };

        $dayNames  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        $planData  = [];
        $usedIds   = [];

        foreach ($dayNames as $day) {
            $dayMeals = [];
            foreach ($mealTargets as $category => $kcalTarget) {
                $recipe = $pickRecipe($category, $kcalTarget, $usedIds);
                if (!$recipe) {
                    // Fallback: ignore dislike / exclude constraints
                    $stmt = $db->prepare(
                        'SELECT * FROM recipes WHERE category = ? ORDER BY ABS(calories - ?) LIMIT 1'
                    );
                    $stmt->execute([$category, $kcalTarget]);
                    $recipe = $stmt->fetch();
                }
                if ($recipe) {
                    $dayMeals[] = [
                        'id'          => (int) $recipe['id'],
                        'title'       => $recipe['title'],
                        'category'    => $recipe['category'],
                        'calories'    => (int) $recipe['calories'],
                        'protein_g'   => (int) $recipe['protein_g'],
                        'carbs_g'     => (int) $recipe['carbs_g'],
                        'fat_g'       => (int) $recipe['fat_g'],
                        'prep_minutes'=> (int) $recipe['prep_minutes'],
                    ];
                    $usedIds[] = (int) $recipe['id'];
                }
            }
            $planData[$day] = $dayMeals;
        }

        // Save plan
        $startDate = date('Y-m-d', strtotime('this monday'));
        $endDate   = date('Y-m-d', strtotime('this sunday'));

        $ins = $db->prepare('
            INSERT INTO weekly_plans (user_id, start_date, end_date, target_calories, zone, plan_data_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $ins->execute([
            $userId, $startDate, $endDate,
            $targetCalories, $zone, json_encode($planData)
        ]);

        $generated = true;
    }
}

// ---- Load current active plan ----
$planStmt = $db->prepare('
    SELECT * FROM weekly_plans
    WHERE user_id = ?
    ORDER BY created_at DESC LIMIT 1
');
$planStmt->execute([$userId]);
$plan = $planStmt->fetch();
$planData = $plan ? json_decode($plan['plan_data_json'], true) : null;

$pageTitle = 'My Weekly Plan – KCALS';
$activeNav = 'plan';
require_once __DIR__ . '/includes/header.php';

$generateUrl = BASE_URL . '/plan.php?generate=1&csrf=' . urlencode(csrfToken());
?>

<div style="max-width:1100px; margin:2rem auto; padding:0 1.25rem;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem;">
        <div>
            <h1 style="font-size:1.5rem; margin-bottom:.25rem;">My Weekly Plan</h1>
            <p class="text-small" style="color:var(--slate-mid);">
                Target: <strong><?= $stats['target_kcal'] ?> kcal/day</strong>
                &bull; Zone: <span class="zone-badge <?= $stats['zone'] ?>" style="vertical-align:middle; font-size:.72rem;"><?= strtoupper($stats['zone']) ?></span>
                &bull; Deficit: <strong><?= $stats['daily_deficit'] ?> kcal/day</strong>
            </p>
        </div>
        <div style="display:flex; gap:.75rem;">
            <a href="<?= htmlspecialchars($generateUrl) ?>" class="btn btn-primary">
                <i data-lucide="wand-2" style="width:15px;height:15px;"></i>
                <?= $plan ? 'Regenerate Plan' : 'Generate Plan' ?>
            </a>
            <?php if ($plan): ?>
            <a href="<?= BASE_URL ?>/shopping.php" class="btn btn-outline">
                <i data-lucide="shopping-cart" style="width:15px;height:15px;"></i>
                Shopping List
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($generated): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem;">
        <strong>Plan generated!</strong> Here is your personalised 7-day meal plan.
    </div>
    <?php endif; ?>

    <?php if ($genError): ?>
    <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= htmlspecialchars($genError) ?></div>
    <?php endif; ?>

    <?php if ($planData): ?>

    <!-- Plan metadata -->
    <?php if ($plan): ?>
    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem;">
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <div style="font-size:1.4rem; font-weight:800; color:var(--green-dark);"><?= $plan['target_calories'] ?></div>
            <div class="text-small text-muted">Daily Kcal Target</div>
        </div>
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <div style="font-size:1.4rem; font-weight:800; color:var(--slate);"><?= $plan['start_date'] ?></div>
            <div class="text-small text-muted">Week Start</div>
        </div>
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <div style="font-size:1.4rem; font-weight:800; color:var(--slate);"><?= $plan['end_date'] ?></div>
            <div class="text-small text-muted">Week End</div>
        </div>
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <?php
                $totalPlanKcal = 0;
                foreach ($planData as $dayMeals) $totalPlanKcal += array_sum(array_column($dayMeals, 'calories'));
                $avgKcal = count($planData) > 0 ? round($totalPlanKcal / count($planData)) : 0;
            ?>
            <div style="font-size:1.4rem; font-weight:800; color:var(--slate);"><?= $avgKcal ?></div>
            <div class="text-small text-muted">Avg kcal/day</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 7-Day Grid -->
    <div class="plan-grid">
        <?php foreach ($planData as $dayName => $meals): ?>
        <?php $dayTotal = array_sum(array_column($meals, 'calories')); ?>
        <div class="day-card">
            <div class="day-card-header">
                <span class="day-name"><?= htmlspecialchars($dayName) ?></span>
                <span class="day-kcal"><?= $dayTotal ?> kcal</span>
            </div>
            <div class="meal-list">
                <?php foreach ($meals as $meal): ?>
                <div class="meal-item">
                    <div class="meal-dot <?= htmlspecialchars($meal['category']) ?>"></div>
                    <div class="meal-info">
                        <div class="meal-type"><?= htmlspecialchars(ucfirst($meal['category'])) ?></div>
                        <div class="meal-name" title="<?= htmlspecialchars($meal['title']) ?>">
                            <?= htmlspecialchars($meal['title']) ?>
                        </div>
                        <div class="text-small" style="color:var(--slate-light);">
                            P: <?= $meal['protein_g'] ?>g &bull;
                            C: <?= $meal['carbs_g'] ?>g &bull;
                            F: <?= $meal['fat_g'] ?>g &bull;
                            ⏱ <?= $meal['prep_minutes'] ?>min
                        </div>
                    </div>
                    <span class="meal-kcal"><?= $meal['calories'] ?> kcal</span>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="padding:.5rem .75rem; border-top:1px solid var(--border); background:var(--bg); border-radius:0 0 var(--radius) var(--radius);">
                <div style="display:flex; gap:.4rem; flex-wrap:wrap;">
                    <span style="font-size:.72rem; color:var(--slate-mid);">
                        P: <?= array_sum(array_column($meals,'protein_g')) ?>g
                    </span>
                    <span style="font-size:.72rem; color:var(--slate-mid);">
                        &bull; C: <?= array_sum(array_column($meals,'carbs_g')) ?>g
                    </span>
                    <span style="font-size:.72rem; color:var(--slate-mid);">
                        &bull; F: <?= array_sum(array_column($meals,'fat_g')) ?>g
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="card" style="text-align:center; padding:3rem 1.5rem;">
        <i data-lucide="calendar-x" style="width:56px;height:56px; color:var(--slate-light); display:block; margin:0 auto 1rem;"></i>
        <h2 style="margin-bottom:.5rem;">No plan yet</h2>
        <p style="max-width:400px; margin:0 auto 1.5rem;">Click the button above to generate your personalised 7-day meal plan based on your current calorie target and psychological zone.</p>
        <a href="<?= htmlspecialchars($generateUrl) ?>" class="btn btn-primary btn-lg">
            <i data-lucide="wand-2" style="width:18px;height:18px;"></i>
            Generate My Plan
        </a>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
