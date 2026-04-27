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
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$stats = calculateUserStats($user, $latestProgress);
$isPlateau = detectPlateau($userId, $db);

// Hormetic Recharge Day (v0.9.5)
$rechargeDay     = max(1, min(7, (int) ($user['recharge_day'] ?? 3))); // 1=Mon…7=Sun, default Wed
$rechargeDayName = rechargeDayName($rechargeDay);  // e.g. 'wednesday'

// Recovery Mode (v0.9.6)
$isRecoveryMode = (bool) ($user['recovery_mode'] ?? false);

$latestSleep = (int) ($latestProgress['sleep_level'] ?? 5);
$sleepBoost  = $latestSleep <= 4;

// Workout Boost: vars derived from latest check-in (used for banner + plan generation)
$latestWorkoutType    = $latestProgress['workout_type']    ?? '';
$latestWorkoutMinutes = (int) ($latestProgress['workout_minutes'] ?? 0);
$workoutBoost         = $latestWorkoutMinutes > 0 && !empty($latestWorkoutType);
$strengthDay          = ($latestWorkoutType === 'strength' && $workoutBoost);
$burnedKcal           = 0;
if ($workoutBoost) {
    $burnRates  = ['cardio' => 6, 'strength' => 4, 'yoga' => 3];
    $burnRate   = $burnRates[$latestWorkoutType] ?? 5;
    $burnedKcal = $latestWorkoutMinutes * $burnRate;
}

// Foods the user wants KCALS to include when possible
$inclStmt = $db->prepare('
    SELECT ufi.food_id, f.name_en, f.name_el
    FROM user_food_inclusions ufi
    JOIN foods f ON f.id = ufi.food_id
    WHERE ufi.user_id = ?
    ORDER BY f.name_en
');
$inclStmt->execute([$userId]);
$currentInclusions = $inclStmt->fetchAll();
$includedIds = array_map('intval', array_column($currentInclusions, 'food_id'));

// ---- Generate / Regenerate Plan ----
$generated = false;
$genError  = '';
$includedUsedNames = [];
$includedSkippedNames = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_plan') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $genError = __('err_plan_invalid');
    } else {
        $currentMonth   = (int) date('n');
        // Plateau Breaker: if stagnation detected, reset to TDEE for metabolic shock
        $targetCalories = $isPlateau ? $stats['tdee'] : $stats['target_kcal'];

        // Recovery Mode (v0.9.6): override to 5% deficit regardless of zone
        if ($isRecoveryMode) {
            $targetCalories = calculateRecoveryCalories((float) $stats['tdee']);
        }

        // Sleep Factor: if latest sleep ≤ 4, halve the deficit to support recovery
        if ($sleepBoost && !$isPlateau && !$isRecoveryMode) {
            $targetCalories = (int) round(($stats['tdee'] + $stats['target_kcal']) / 2);
        }

        // Workout Boost: add back burned calories to preserve the deficit
        if ($workoutBoost) {
            $targetCalories += $burnedKcal;
        }
        $strengthDay = ($latestWorkoutType === 'strength' && $workoutBoost);
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
        $excludedIds = array_map('intval', array_column($exclStmt->fetchAll(), 'food_id'));
        $remainingIncludeIds = array_values(array_diff($includedIds, $excludedIds));
        $includedUsedIds = [];

        $profile = [
            'adventure'    => (int) ($user['food_adventure'] ?? 2),
            'allergies'    => $activeAllergies,
            'excluded_ids' => $excludedIds,
            'sleep_boost'  => $sleepBoost,
            'strength_day' => $strengthDay,
            'comfort_food_mode' => $isRecoveryMode,
        ];

        // Smart food-based meal builder (instantiated per-day to allow per-day profile tweaks)
        require_once __DIR__ . '/engine/meal_builder.php';

        $dayNames    = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        $planData    = [];
        $planSchedule = [];
        $usedFoodIds = []; // All food IDs used this week (for variety)

        foreach ($dayNames as $day) {
            $dayMeals   = [];
            $dayFoodIds = []; // Food IDs used in earlier slots today (avoid same protein lunch+dinner)

            // Social Buffer: adjust per-day target (−150 Mon–Fri, +750 Sat, unchanged Sun)
            $dayTarget = applySocialBuffer($targetCalories, $day);

            // Hormetic Recharge Day (v0.9.5): add +150 kcal above TDEE
            $isDayRecharge = isRechargeDay($day, $rechargeDay);
            if ($isDayRecharge) {
                $dayTarget = applyRechargeDay($dayTarget, $day, $rechargeDay);
            }
            $mealTargets = [
                'breakfast' => (int) round($dayTarget * 0.25),
                'lunch'     => (int) round($dayTarget * 0.35),
                'dinner'    => (int) round($dayTarget * 0.30),
                'snack'     => (int) round($dayTarget * 0.10),
            ];

            foreach ($mealTargets as $slot => $kcalTarget) {
                // Pass complex_carb_bias on the recharge day
                if ($isDayRecharge) {
                    $profile['complex_carb_bias'] = true;
                } else {
                    $profile['complex_carb_bias'] = false;
                }
                $planSchedule[$day][$slot] = [
                    'target'  => $kcalTarget,
                    'profile' => $profile,
                ];
                $builder = new MealBuilder($db, $dietType, $currentMonth, $dislikes, $profile);
                $meal = null;
                foreach ($remainingIncludeIds as $includeIndex => $includeId) {
                    $candidateMeal = $builder->buildMealWithFood($slot, $kcalTarget, (int) $includeId, $usedFoodIds, $dayFoodIds);
                    if ($candidateMeal !== null) {
                        $meal = $candidateMeal;
                        $includedUsedIds[] = (int) $includeId;
                        unset($remainingIncludeIds[$includeIndex]);
                        $remainingIncludeIds = array_values($remainingIncludeIds);
                        break;
                    }
                }
                if ($meal === null) {
                    $meal = $builder->buildMeal($slot, $kcalTarget, $usedFoodIds, $dayFoodIds);
                }
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

        // Strict second pass: if any saved must-include food did not land naturally
        // or during the first forced pass, replace an ordinary compatible meal.
        $foodIdsInPlan = [];
        foreach ($planData as $dayMeals) {
            foreach ($dayMeals as $meal) {
                foreach (($meal['components'] ?? []) as $component) {
                    $componentFoodId = (int) ($component['food_id'] ?? 0);
                    if ($componentFoodId > 0) {
                        $foodIdsInPlan[] = $componentFoodId;
                    }
                }
            }
        }
        $foodIdsInPlan = array_values(array_unique($foodIdsInPlan));
        $missingIncludeIds = array_values(array_diff($includedIds, $foodIdsInPlan, $excludedIds));

        foreach ($missingIncludeIds as $missingIncludeId) {
            $placed = false;
            foreach ($planData as $day => &$dayMeals) {
                foreach ($dayMeals as $mealIndex => $existingMeal) {
                    $existingFoodIds = array_map(
                        fn($component) => (int) ($component['food_id'] ?? 0),
                        $existingMeal['components'] ?? []
                    );
                    if (!empty(array_intersect($existingFoodIds, $includedIds))) {
                        continue;
                    }

                    $slot = $existingMeal['slot'] ?? 'lunch';
                    $schedule = $planSchedule[$day][$slot] ?? null;
                    if (!$schedule) {
                        continue;
                    }

                    $builder = new MealBuilder($db, $dietType, $currentMonth, $dislikes, $schedule['profile']);
                    $replacement = $builder->buildMealWithFood(
                        $slot,
                        (int) $schedule['target'],
                        (int) $missingIncludeId,
                        [],
                        []
                    );
                    if ($replacement === null) {
                        continue;
                    }

                    $dayMeals[$mealIndex] = $replacement;
                    $placed = true;
                    break 2;
                }
            }
            unset($dayMeals);

            if ($placed) {
                $foodIdsInPlan[] = (int) $missingIncludeId;
                $foodIdsInPlan = array_values(array_unique($foodIdsInPlan));
            }
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
        $includedUsedIds = array_values(array_intersect($includedIds, $foodIdsInPlan));
        foreach ($currentInclusions as $food) {
            $name = ($GLOBALS['_kcals_lang'] === 'el') ? $food['name_el'] : $food['name_en'];
            if (in_array((int) $food['food_id'], $includedUsedIds, true)) {
                $includedUsedNames[] = $name;
            } else {
                $includedSkippedNames[] = $name;
            }
        }
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
            <form method="POST" action="<?= BASE_URL ?>/plan.php" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="action" value="generate_plan">
                <button type="submit" class="btn btn-primary"
                        <?php if (!empty($currentInclusions)): ?>
                        onclick="return confirm(<?= htmlspecialchars(json_encode(__('plan_include_confirm')), ENT_QUOTES) ?>)"
                        <?php endif; ?>>
                <i data-lucide="wand-2" style="width:15px;height:15px;"></i>
                <?= $plan ? __('plan_regen') : __('plan_gen') ?>
                </button>
            </form>
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

    <div class="alert" style="background:#f0fdf4; border:1px solid #86efac; color:#14532d; margin-bottom:1rem;">
        <strong>✅ <?= __('plan_must_include_h') ?></strong>
        <?php if (!empty($currentInclusions)): ?>
            <?php
            $includeNames = array_map(
                fn($f) => htmlspecialchars(($GLOBALS['_kcals_lang'] === 'el') ? $f['name_el'] : $f['name_en']),
                $currentInclusions
            );
            ?>
            <?= implode(', ', $includeNames) ?>
        <?php else: ?>
            <?= __('plan_include_none') ?>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/settings.php" style="color:#166534;font-weight:700;margin-left:.5rem;"><?= __('plan_include_manage') ?></a>
    </div>

    <?php if ($generated): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem;">
        <strong><?= __('plan_generated') ?></strong> <?= __('plan_generated_desc') ?>
    </div>
    <?php if (!empty($includedUsedNames)): ?>
    <div class="alert" style="background:#ecfdf5; border:1px solid #6ee7b7; color:#065f46; margin-bottom:1rem;">
        <strong><?= __('plan_included_used') ?></strong> <?= htmlspecialchars(implode(', ', $includedUsedNames)) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($includedSkippedNames)): ?>
    <div class="alert" style="background:#fff7ed; border:1px solid #fdba74; color:#9a3412; margin-bottom:1rem;">
        <strong><?= __('plan_included_skipped') ?></strong> <?= htmlspecialchars(implode(', ', $includedSkippedNames)) ?>.
        <?= __('plan_included_hint') ?>
    </div>
    <?php endif; ?>
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

    <?php if ($workoutBoost): ?>
    <div class="alert" style="background:#e8f8f0; border:1px solid #52be80; color:#1e6e42; margin-bottom:1rem;">
        <strong><?= __('plan_workout_notice') ?></strong>
        <?= sprintf(__('plan_workout_desc'), __('workout_' . $latestWorkoutType), $latestWorkoutMinutes, $burnedKcal) ?>
    </div>
    <?php endif; ?>

    <div class="alert" style="background:#fff8e1; border:1px solid #ffc107; color:#6d4c00; margin-bottom:1rem;">
        <strong><?= __('plan_recharge_notice') ?></strong>
        <?= sprintf(__('plan_recharge_desc'), __(('day_' . $rechargeDayName)), RECHARGE_EXTRA_KCAL) ?>
    </div>

    <?php if ($isRecoveryMode): ?>
    <?php $recoveryTarget = calculateRecoveryCalories((float) $stats['tdee']); ?>
    <div class="alert" style="background:#fce8f3; border:1px solid #d98fba; color:#6b1c47; margin-bottom:1rem;">
        <strong>🧘 <?= __('plan_recovery_notice') ?></strong>
        <?= sprintf(__('plan_recovery_desc'), $recoveryTarget) ?>
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
        <?php
            $dayTotal = array_sum(array_column($meals, 'calories'));
            $isDayRechargeView = isRechargeDay($dayName, $rechargeDay);
        ?>
        <div class="day-card<?= $isDayRechargeView ? ' recharge-day-card' : '' ?>">
            <div class="day-card-header">
                <span class="day-name"><?= __('day_' . strtolower($dayName)) ?></span>
                <?php if ($isDayRechargeView): ?>
                <span class="recharge-badge" title="<?= __('plan_recharge_badge_title') ?>">
                    ⚡ <?= __('plan_recharge_badge') ?>
                </span>
                <?php endif; ?>
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
        <form method="POST" action="<?= BASE_URL ?>/plan.php" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="generate_plan">
            <button type="submit" class="btn btn-primary btn-lg"
                    <?php if (!empty($currentInclusions)): ?>
                    onclick="return confirm(<?= htmlspecialchars(json_encode(__('plan_include_confirm')), ENT_QUOTES) ?>)"
                    <?php endif; ?>>
            <i data-lucide="wand-2" style="width:18px;height:18px;"></i>
            <?= __('plan_gen_my') ?>
            </button>
        </form>
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
