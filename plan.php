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

// ======== Interview gate ========
if (empty($user['interview_done'])) {
    header('Location: ' . BASE_URL . '/preferences.php?next=plan');
    exit;
}

$latestProgress = getLatestProgress($userId);

if (!$latestProgress) {
    header('Location: /dashboard.php');
    exit;
}

$stats = calculateUserStats($user, $latestProgress);
$isPlateau = detectPlateau($userId, $db);

$latestSleep = (int) ($latestProgress['sleep_level'] ?? 5);
$sleepBoost  = $latestSleep <= 4;

// ---- Generate / Regenerate Plan ----
$generated = false;
$genError  = '';

if (isset($_GET['generate']) && $_GET['generate'] == '1') {
    if (!verifyCsrf($_GET['csrf'] ?? '')) {
        $genError = __('err_plan_invalid');
    } else {
        $currentMonth   = (int) date('n');
        // Plateau Breaker: if stagnation detected, reset to TDEE for metabolic shock
        $targetCalories = $isPlateau ? $stats['tdee'] : $stats['target_kcal'];

        // Sleep Factor: if latest sleep ≤ 4, halve the deficit to support recovery
        if ($sleepBoost && !$isPlateau) {
            $targetCalories = (int) round(($stats['tdee'] + $stats['target_kcal']) / 2);
        }
        $zone           = $stats['zone'];
        $dietType       = $user['diet_type'];

        // Load user's disliked ingredients
        $dislikeStmt = $db->prepare('SELECT ingredient_name FROM user_dislikes WHERE user_id = ?');
        $dislikeStmt->execute([$userId]);
        $dislikes = array_column($dislikeStmt->fetchAll(), 'ingredient_name');

        // Load food preference profile
        $activeAllergies = [];
        foreach (['gluten','dairy','nuts','eggs','shellfish','soy'] as $a) {
            if (!empty($user['allergy_' . $a])) {
                $activeAllergies[] = $a;
            }
        }
        $exclStmt = $db->prepare('SELECT food_id FROM user_food_exclusions WHERE user_id = ?');
        $exclStmt->execute([$userId]);
        $excludedIds = array_column($exclStmt->fetchAll(), 'food_id');

        $profile = [
            'adventure'    => (int) ($user['food_adventure'] ?? 2),
            'allergies'    => $activeAllergies,
            'excluded_ids' => $excludedIds,
            'sleep_boost'  => $sleepBoost,
        ];

        // Smart food-based meal builder
        require_once __DIR__ . '/engine/meal_builder.php';
        $builder = new MealBuilder($db, $dietType, $currentMonth, $dislikes, $profile);

        $dayNames    = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        $planData    = [];
        $usedFoodIds = []; // All food IDs used this week (for variety)

        foreach ($dayNames as $day) {
            $dayMeals   = [];
            $dayFoodIds = []; // Food IDs used in earlier slots today (avoid same protein lunch+dinner)

            // Social Buffer: adjust per-day target (−150 Mon–Fri, +750 Sat, unchanged Sun)
            $dayTarget   = applySocialBuffer($targetCalories, $day);
            $mealTargets = [
                'breakfast' => (int) round($dayTarget * 0.25),
                'lunch'     => (int) round($dayTarget * 0.35),
                'dinner'    => (int) round($dayTarget * 0.30),
                'snack'     => (int) round($dayTarget * 0.10),
            ];

            foreach ($mealTargets as $slot => $kcalTarget) {
                $meal = $builder->buildMeal($slot, $kcalTarget, $usedFoodIds, $dayFoodIds);
                $dayMeals[] = $meal;
                foreach ($meal['components'] as $c) {
                    if ($c['food_id'] > 0) {
                        $usedFoodIds[] = $c['food_id'];
                        $dayFoodIds[]  = $c['food_id'];
                    }
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

$pageTitle = __('plan_title');
$activeNav = 'plan';
require_once __DIR__ . '/includes/header.php';

$generateUrl = BASE_URL . '/plan.php?generate=1&csrf=' . urlencode(csrfToken());
?>

<div class="no-print" style="max-width:1100px; margin:2rem auto; padding:0 1.25rem;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem;">
        <div>
            <h1 style="font-size:1.5rem; margin-bottom:.25rem;"><?= __('plan_h1') ?></h1>
            <p class="text-small" style="color:var(--slate-mid);">
                <?= __('plan_target') ?> <strong><?= $stats['target_kcal'] ?> kcal/day</strong>
                &bull; <?= __('plan_zone_lbl') ?> <span class="zone-badge <?= $stats['zone'] ?>" style="vertical-align:middle; font-size:.72rem;"><?= strtoupper($stats['zone']) ?></span>
                &bull; <?= __('plan_deficit_lbl') ?> <strong><?= $stats['daily_deficit'] ?> kcal/day</strong>
            </p>
        </div>
        <div class="no-print" style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center;">
            <a href="<?= htmlspecialchars($generateUrl) ?>" class="btn btn-primary">
                <i data-lucide="wand-2" style="width:15px;height:15px;"></i>
                <?= $plan ? __('plan_regen') : __('plan_gen') ?>
            </a>
            <?php if ($plan): ?>
            <a href="<?= BASE_URL ?>/shopping.php" class="btn btn-outline">
                <i data-lucide="shopping-cart" style="width:15px;height:15px;"></i>
                <?= __('plan_shopping') ?>
            </a>
            <button type="button" onclick="window.print()" class="btn btn-outline">
                <i data-lucide="printer" style="width:15px;height:15px;"></i>
                <?= __('plan_print') ?>
            </button>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/settings.php" style="font-size:.8rem;color:#64748b;text-decoration:none;font-weight:500;">
                <i data-lucide="settings-2" style="width:13px;height:13px;vertical-align:-1px;margin-right:3px;"></i><?= __('pref_edit_link') ?>
            </a>
        </div>
    </div>

    <?php if ($generated): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem;">
        <strong><?= __('plan_generated') ?></strong> <?= __('plan_generated_desc') ?>
    </div>
    <?php endif; ?>

    <?php if ($genError): ?>
    <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= htmlspecialchars($genError) ?></div>
    <?php endif; ?>

    <?php if ($isPlateau): ?>
    <div class="alert" style="background:#fff3cd; border:1px solid #ffc107; color:#856404; margin-bottom:1.5rem;">
        <strong><?= __('plan_plateau_title') ?></strong><br>
        <?= sprintf(__('plan_plateau_desc'), number_format($stats['tdee'])) ?>
    </div>
    <?php endif; ?>

    <div class="alert" style="background:#e8f4ff; border:1px solid #90c7f5; color:#1a5276; margin-bottom:1rem;">
        <strong><?= __('plan_buffer_title') ?></strong>
        <?= __('plan_buffer_desc') ?>
    </div>

    <?php if ($sleepBoost): ?>
    <div class="alert" style="background:#f3e5ff; border:1px solid #c39bd3; color:#6c3483; margin-bottom:1rem;">
        <strong><?= __('plan_sleep_notice') ?></strong>
        <?= sprintf(__('plan_sleep_desc'), $latestSleep) ?>
    </div>
    <?php endif; ?>

    <?php if ($planData): ?>

    <!-- Plan metadata -->
    <?php if ($plan): ?>
    <div class="no-print" style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem;">
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <div style="font-size:1.4rem; font-weight:800; color:var(--green-dark);"><?= $plan['target_calories'] ?></div>
            <div class="text-small text-muted"><?= __('plan_kcal_target') ?></div>
        </div>
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <div style="font-size:1.4rem; font-weight:800; color:var(--slate);"><?= $plan['start_date'] ?></div>
            <div class="text-small text-muted"><?= __('plan_week_start') ?></div>
        </div>
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <div style="font-size:1.4rem; font-weight:800; color:var(--slate);"><?= $plan['end_date'] ?></div>
            <div class="text-small text-muted"><?= __('plan_week_end') ?></div>
        </div>
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <?php
                $totalPlanKcal = 0;
                foreach ($planData as $dayMeals) $totalPlanKcal += array_sum(array_column($dayMeals, 'calories'));
                $avgKcal = count($planData) > 0 ? round($totalPlanKcal / count($planData)) : 0;
            ?>
            <div style="font-size:1.4rem; font-weight:800; color:var(--slate);"><?= $avgKcal ?></div>
            <div class="text-small text-muted"><?= __('plan_avg_kcal') ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 7-Day Grid -->
    <div class="no-print">
    <div class="plan-grid">
        <?php foreach ($planData as $dayName => $meals): ?>
        <?php $dayTotal = array_sum(array_column($meals, 'calories')); ?>
        <div class="day-card">
            <div class="day-card-header">
                <span class="day-name"><?= __('day_' . strtolower($dayName)) ?></span>
                <span class="day-kcal"><?= $dayTotal ?> kcal</span>
            </div>
            <div class="meal-list">
                <?php foreach ($meals as $meal): ?>
                <?php
                    $mSlot = $meal['slot'] ?? $meal['category'] ?? 'lunch';
                    $mName = ($GLOBALS['_kcals_lang'] === 'el')
                        ? ($meal['name_el'] ?? $meal['title'] ?? '')
                        : ($meal['name_en'] ?? $meal['title'] ?? '');
                ?>
                <div class="meal-item">
                    <div class="meal-dot <?= htmlspecialchars($mSlot) ?>"></div>
                    <div class="meal-info">
                        <div class="meal-type"><?= __('meal_slot_' . $mSlot) ?></div>
                        <div class="meal-name" title="<?= htmlspecialchars($mName) ?>">
                            <?= htmlspecialchars($mName) ?>
                        </div>
                        <div class="meal-macros-row">
                            <span><?= __('macro_protein') ?>: <strong><?= $meal['protein_g'] ?>g</strong></span>
                            <span><?= __('macro_carbs') ?>: <strong><?= $meal['carbs_g'] ?>g</strong></span>
                            <span><?= __('macro_fat') ?>: <strong><?= $meal['fat_g'] ?>g</strong></span>
                            <span>⏱ <?= __('macro_prep') ?>: <?= $meal['prep_minutes'] ?>min</span>
                        </div>
                        <?php if (!empty($meal['components'])): ?>
                        <div class="meal-ingredients">
                            <?php foreach ($meal['components'] as $ci => $c): ?><?php if ($c['food_id'] > 0): ?><?php if ($ci > 0): ?><span class="ing-sep">&bull;</span><?php endif; ?><span class="ing-item"><?= htmlspecialchars(($GLOBALS['_kcals_lang']==='el') ? $c['name_el'] : $c['name_en']) ?> <strong><?= $c['grams'] ?>g</strong></span><?php endif; ?><?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="meal-kcal"><?= $meal['calories'] ?> kcal</span>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="padding:.5rem .75rem; border-top:1px solid var(--border); background:var(--bg); border-radius:0 0 var(--radius) var(--radius);">
                <div style="display:flex; gap:.5rem; flex-wrap:wrap; font-size:.72rem; color:var(--slate-mid);">
                    <span><?= __('macro_protein') ?>: <strong><?= array_sum(array_column($meals,'protein_g')) ?>g</strong></span>
                    <span>&bull; <?= __('macro_carbs') ?>: <strong><?= array_sum(array_column($meals,'carbs_g')) ?>g</strong></span>
                    <span>&bull; <?= __('macro_fat') ?>: <strong><?= array_sum(array_column($meals,'fat_g')) ?>g</strong></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    </div><!-- /no-print plan-grid wrapper -->

    <?php else: ?>
    <div class="card" style="text-align:center; padding:3rem 1.5rem;">
        <i data-lucide="calendar-x" style="width:56px;height:56px; color:var(--slate-light); display:block; margin:0 auto 1rem;"></i>
        <h2 style="margin-bottom:.5rem;"><?= __('plan_no_plan') ?></h2>
        <p style="max-width:400px; margin:0 auto 1.5rem;"><?= __('plan_no_plan_desc') ?></p>
        <a href="<?= htmlspecialchars($generateUrl) ?>" class="btn btn-primary btn-lg">
            <i data-lucide="wand-2" style="width:18px;height:18px;"></i>
            <?= __('plan_gen_my') ?>
        </a>
    </div>
    <?php endif; ?>

</div><!-- /no-print outer wrapper -->

<?php if ($planData): ?>
<!-- ======== PRINT TABLE (hidden on screen, visible only on print) ======== -->
<div class="print-only" style="padding:0;margin:0;">
    <div class="print-header">
        <strong>KCALS</strong> &mdash; <?= __('plan_h1') ?>
        <?php if ($plan): ?>
        &nbsp;&bull;&nbsp; <?= __('plan_week_start') ?>: <?= $plan['start_date'] ?>
        &nbsp;&bull;&nbsp; <?= __('plan_week_end') ?>: <?= $plan['end_date'] ?>
        &nbsp;&bull;&nbsp; <?= __('plan_kcal_target') ?>: <?= $plan['target_calories'] ?> kcal
        <?php endif; ?>
    </div>
    <table class="print-table">
        <thead>
            <tr>
                <th class="pt-day-col">&nbsp;</th>
                <th class="pt-meal-col"><?= __('meal_slot_breakfast') ?></th>
                <th class="pt-meal-col"><?= __('meal_slot_lunch') ?></th>
                <th class="pt-meal-col"><?= __('meal_slot_dinner') ?></th>
                <th class="pt-meal-col"><?= __('meal_slot_snack') ?></th>
                <th class="pt-total-col">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($planData as $dayName => $dayMeals):
            $slotMap = [];
            foreach ($dayMeals as $m) {
                $s = $m['slot'] ?? $m['category'] ?? 'lunch';
                $slotMap[$s] = $m;
            }
            $ptDayTotal = array_sum(array_column($dayMeals, 'calories'));
        ?>
            <tr>
                <td class="pt-day-name"><?= __('day_' . strtolower($dayName)) ?></td>
                <?php foreach (['breakfast','lunch','dinner','snack'] as $slot):
                    $m = $slotMap[$slot] ?? null;
                    $mName = '';
                    if ($m) {
                        $mName = ($GLOBALS['_kcals_lang'] === 'el')
                            ? ($m['name_el'] ?? $m['title'] ?? '')
                            : ($m['name_en'] ?? $m['title'] ?? '');
                    }
                ?>
                <td class="pt-meal-cell">
                    <?php if ($m): ?>
                    <div class="pt-food-name"><?= htmlspecialchars($mName) ?></div>
                    <?php if (!empty($m['components'])): ?>
                    <div class="pt-ingredients"><?php
                        $parts = [];
                        foreach ($m['components'] as $c) {
                            if ($c['food_id'] > 0) {
                                $cn = ($GLOBALS['_kcals_lang']==='el') ? $c['name_el'] : $c['name_en'];
                                $parts[] = htmlspecialchars($cn) . ' <strong>' . $c['grams'] . 'g</strong>';
                            }
                        }
                        echo implode(' &bull; ', $parts);
                    ?></div>
                    <?php endif; ?>
                    <div class="pt-macros">
                        <?= $m['calories'] ?> kcal &bull;
                        P:<?= $m['protein_g'] ?>g &bull;
                        C:<?= $m['carbs_g'] ?>g &bull;
                        F:<?= $m['fat_g'] ?>g &bull;
                        &#9203;<?= $m['prep_minutes'] ?>'
                    </div>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td class="pt-total">
                    <?= $ptDayTotal ?> kcal<br>
                    <span class="pt-macros">
                        P:<?= array_sum(array_column($dayMeals,'protein_g')) ?>g
                        C:<?= array_sum(array_column($dayMeals,'carbs_g')) ?>g
                        F:<?= array_sum(array_column($dayMeals,'fat_g')) ?>g
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
