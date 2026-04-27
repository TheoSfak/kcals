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
$mainCookingFamilies = ['heavy_mixed', 'fish', 'shellfish', 'red_meat', 'pork', 'lamb', 'poultry'];

function kcalsPlanComponentIds(array $meal): array {
    $ids = [];
    foreach (($meal['components'] ?? []) as $component) {
        $foodId = (int) ($component['food_id'] ?? 0);
        if ($foodId > 0) {
            $ids[$foodId] = true;
        }
    }
    return array_keys($ids);
}

function kcalsPlanMealHasAnyFood(array $meal, array $foodIds): bool {
    if (empty($foodIds)) {
        return false;
    }
    return !empty(array_intersect(kcalsPlanComponentIds($meal), array_map('intval', $foodIds)));
}

function kcalsPlanReplacementAvoidIds(array $meal, string $reason): array {
    if ($reason === 'avoid_food') {
        return [];
    }

    $ids = [];
    foreach (($meal['components'] ?? []) as $component) {
        $foodId = (int) ($component['food_id'] ?? 0);
        if ($foodId <= 0) {
            continue;
        }

        $type = (string) ($component['food_type'] ?? '');
        $effort = (string) ($component['cooking_effort'] ?? '');

        if ($reason === 'protein') {
            if (in_array($type, ['protein', 'dairy', 'mixed'], true)) {
                $ids[$foodId] = true;
            }
            continue;
        }

        if (in_array($type, ['mixed', 'protein', 'dairy'], true) || $effort === 'main_cooking') {
            $ids[$foodId] = true;
        }
    }

    if (empty($ids)) {
        foreach (($meal['components'] ?? []) as $component) {
            $foodId = (int) ($component['food_id'] ?? 0);
            if ($foodId > 0) {
                $ids[$foodId] = true;
                break;
            }
        }
    }

    return array_keys($ids);
}

function kcalsPlanMealHasMainCooking(array $meal, array $mainCookingFamilies): bool {
    foreach (($meal['components'] ?? []) as $component) {
        $family = (string) ($component['meal_family'] ?? '');
        $effort = (string) ($component['cooking_effort'] ?? '');
        if ($effort === 'main_cooking' || in_array($family, $mainCookingFamilies, true)) {
            return true;
        }
    }
    return false;
}

function kcalsPlanMealScoredFamilies(array $meal, array $mainCookingFamilies): array {
    $families = [];
    $countableFamilies = array_merge($mainCookingFamilies, ['legume', 'dairy', 'eggs', 'plant_protein']);
    foreach (($meal['components'] ?? []) as $component) {
        $family = (string) ($component['meal_family'] ?? '');
        $type = (string) ($component['food_type'] ?? '');
        $effort = (string) ($component['cooking_effort'] ?? '');
        if (
            in_array($family, $countableFamilies, true)
            && ($effort === 'main_cooking' || in_array($type, ['protein', 'dairy', 'mixed'], true))
        ) {
            $families[$family] = true;
        }
    }
    return array_keys($families);
}

function kcalsPlanLockedMealsByDaySlot($planData): array {
    if (!is_array($planData)) {
        return [];
    }

    $lockedMeals = [];
    foreach ($planData as $dayName => $dayMeals) {
        if (!is_array($dayMeals)) {
            continue;
        }
        foreach ($dayMeals as $mealIndex => $meal) {
            if (!is_array($meal) || empty($meal['locked'])) {
                continue;
            }
            $slot = (string) ($meal['slot'] ?? $meal['category'] ?? '');
            if (!in_array($slot, ['breakfast', 'lunch', 'dinner', 'snack'], true)) {
                $slot = ['breakfast', 'lunch', 'dinner', 'snack'][(int) $mealIndex] ?? 'lunch';
            }
            $lockedMeals[(string) $dayName][$slot] = $meal;
        }
    }

    return $lockedMeals;
}

function kcalsPlanDayHasOtherLockedMainCooking(array $lockedMealsForDay, string $slot, array $mainCookingFamilies): bool {
    if (!in_array($slot, ['lunch', 'dinner'], true)) {
        return false;
    }

    foreach ($lockedMealsForDay as $lockedSlot => $lockedMeal) {
        if (
            $lockedSlot !== $slot
            && in_array((string) $lockedSlot, ['lunch', 'dinner'], true)
            && is_array($lockedMeal)
            && kcalsPlanMealHasMainCooking($lockedMeal, $mainCookingFamilies)
        ) {
            return true;
        }
    }

    return false;
}

function kcalsPlanRegisterMealUsage(
    array $meal,
    array $includedIds,
    array $mainCookingFamilies,
    array &$usedFoodIds,
    array &$dayFoodIds,
    array &$weeklyFamilyCounts,
    array &$weeklyFoodCounts,
    array &$weeklyTypeCounts,
    array &$countedTypeFoods,
    array &$remainingIncludeIds,
    array &$includedUsedIds,
    bool &$dayHasIncludedFood,
    bool &$dayHasMainCooking
): void {
    if (
        in_array((string) ($meal['slot'] ?? ''), ['lunch', 'dinner'], true)
        && kcalsPlanMealHasMainCooking($meal, $mainCookingFamilies)
    ) {
        $dayHasMainCooking = true;
    }

    foreach (kcalsPlanMealScoredFamilies($meal, $mainCookingFamilies) as $family) {
        $weeklyFamilyCounts[$family] = ($weeklyFamilyCounts[$family] ?? 0) + 1;
    }

    foreach (($meal['components'] ?? []) as $component) {
        $foodId = (int) ($component['food_id'] ?? 0);
        if ($foodId <= 0) {
            continue;
        }

        $usedFoodIds[] = $foodId;
        $dayFoodIds[] = $foodId;
        $componentType = (string) ($component['food_type'] ?? '');
        $weeklyFoodCounts[$foodId] = ($weeklyFoodCounts[$foodId] ?? 0) + 1;

        if ($componentType !== '' && empty($countedTypeFoods[$componentType][$foodId])) {
            $weeklyTypeCounts[$componentType] = ($weeklyTypeCounts[$componentType] ?? 0) + 1;
            $countedTypeFoods[$componentType][$foodId] = true;
        }

        if (in_array($foodId, $includedIds, true)) {
            $dayHasIncludedFood = true;
            $includeIndex = array_search($foodId, $remainingIncludeIds, true);
            if ($includeIndex !== false) {
                $includedUsedIds[] = $foodId;
                unset($remainingIncludeIds[$includeIndex]);
                $remainingIncludeIds = array_values($remainingIncludeIds);
            }
        }
    }
}

function kcalsPlanDayQuality(array $meals, array $mainCookingFamilies): array {
    $prepMinutes = 0;
    $ingredientIds = [];
    $mainCookingCount = 0;
    $lockedCount = 0;

    foreach ($meals as $meal) {
        if (!is_array($meal)) {
            continue;
        }

        $prepMinutes += (int) ($meal['prep_minutes'] ?? 0);
        if (!empty($meal['locked'])) {
            $lockedCount++;
        }
        if (
            in_array((string) ($meal['slot'] ?? ''), ['lunch', 'dinner'], true)
            && kcalsPlanMealHasMainCooking($meal, $mainCookingFamilies)
        ) {
            $mainCookingCount++;
        }

        foreach (($meal['components'] ?? []) as $component) {
            $foodId = (int) ($component['food_id'] ?? 0);
            if ($foodId > 0) {
                $ingredientIds[$foodId] = true;
            }
        }
    }

    $ingredientCount = count($ingredientIds);
    $status = 'balanced';
    if ($mainCookingCount > 1 || $prepMinutes > 75 || $ingredientCount > 16) {
        $status = 'heavy';
    } elseif ($prepMinutes > 55 || $ingredientCount > 12) {
        $status = 'watch';
    } elseif ($prepMinutes <= 35 && $ingredientCount <= 10 && $mainCookingCount <= 1) {
        $status = 'easy';
    }

    return [
        'status' => $status,
        'prep_minutes' => $prepMinutes,
        'ingredient_count' => $ingredientCount,
        'main_cooking_count' => $mainCookingCount,
        'locked_count' => $lockedCount,
    ];
}

function kcalsPlanHistorySummary($planData, array $mainCookingFamilies): array {
    if (!is_array($planData) || empty($planData)) {
        return [
            'avg_kcal' => 0,
            'quality_counts' => ['easy' => 0, 'balanced' => 0, 'watch' => 0, 'heavy' => 0],
            'locked_count' => 0,
        ];
    }

    $totalKcal = 0;
    $dayCount = 0;
    $lockedCount = 0;
    $qualityCounts = ['easy' => 0, 'balanced' => 0, 'watch' => 0, 'heavy' => 0];

    foreach ($planData as $dayMeals) {
        if (!is_array($dayMeals)) {
            continue;
        }

        $dayCount++;
        $totalKcal += array_sum(array_map(fn($meal) => (int) ($meal['calories'] ?? 0), $dayMeals));
        $quality = kcalsPlanDayQuality($dayMeals, $mainCookingFamilies);
        $status = (string) ($quality['status'] ?? 'balanced');
        if (isset($qualityCounts[$status])) {
            $qualityCounts[$status]++;
        }
        $lockedCount += (int) ($quality['locked_count'] ?? 0);
    }

    return [
        'avg_kcal' => $dayCount > 0 ? (int) round($totalKcal / $dayCount) : 0,
        'quality_counts' => $qualityCounts,
        'locked_count' => $lockedCount,
    ];
}

function kcalsPlanMealSignature($meal): string {
    if (!is_array($meal)) {
        return '';
    }

    $parts = [
        (string) ($meal['slot'] ?? $meal['category'] ?? ''),
        (string) ($meal['name_en'] ?? $meal['name_el'] ?? $meal['title'] ?? ''),
        (string) ((int) ($meal['calories'] ?? 0)),
    ];

    foreach (($meal['components'] ?? []) as $component) {
        $foodId = (int) ($component['food_id'] ?? 0);
        if ($foodId <= 0) {
            continue;
        }
        $parts[] = $foodId . ':' . (int) ($component['grams'] ?? 0);
    }

    return implode('|', $parts);
}

function kcalsPlanComparePlans($currentPlanData, $previewPlanData, array $mainCookingFamilies): array {
    $currentSummary = kcalsPlanHistorySummary($currentPlanData, $mainCookingFamilies);
    $previewSummary = kcalsPlanHistorySummary($previewPlanData, $mainCookingFamilies);
    $changedMeals = 0;
    $changedDays = [];
    $dayNames = array_values(array_unique(array_merge(
        is_array($currentPlanData) ? array_keys($currentPlanData) : [],
        is_array($previewPlanData) ? array_keys($previewPlanData) : []
    )));

    foreach ($dayNames as $dayName) {
        $currentMeals = is_array($currentPlanData[$dayName] ?? null) ? $currentPlanData[$dayName] : [];
        $previewMeals = is_array($previewPlanData[$dayName] ?? null) ? $previewPlanData[$dayName] : [];
        $maxMeals = max(count($currentMeals), count($previewMeals));
        for ($i = 0; $i < $maxMeals; $i++) {
            if (kcalsPlanMealSignature($currentMeals[$i] ?? null) !== kcalsPlanMealSignature($previewMeals[$i] ?? null)) {
                $changedMeals++;
                $changedDays[$dayName] = true;
            }
        }
    }

    return [
        'changed_meals' => $changedMeals,
        'changed_days' => count($changedDays),
        'avg_kcal_diff' => (int) $previewSummary['avg_kcal'] - (int) $currentSummary['avg_kcal'],
        'heavy_day_diff' => (int) ($previewSummary['quality_counts']['heavy'] ?? 0) - (int) ($currentSummary['quality_counts']['heavy'] ?? 0),
        'locked_diff' => (int) $previewSummary['locked_count'] - (int) $currentSummary['locked_count'],
    ];
}

function kcalsPlanReplacementContext(array $planData, string $skipDay, int $skipIndex, array $mainCookingFamilies): array {
    $usedWeekIds = [];
    $usedTodayIds = [];
    $weeklyFamilyCounts = [];
    $weeklyFoodCounts = [];
    $weeklyTypeCounts = [];
    $countedTypeFoods = [];
    $sameDayHasMainCooking = false;

    foreach ($planData as $dayName => $dayMeals) {
        foreach ($dayMeals as $mealIndex => $meal) {
            if ((string) $dayName === $skipDay && (int) $mealIndex === $skipIndex) {
                continue;
            }

            if (
                (string) $dayName === $skipDay
                && in_array((string) ($meal['slot'] ?? ''), ['lunch', 'dinner'], true)
                && kcalsPlanMealHasMainCooking($meal, $mainCookingFamilies)
            ) {
                $sameDayHasMainCooking = true;
            }

            foreach (kcalsPlanMealScoredFamilies($meal, $mainCookingFamilies) as $family) {
                $weeklyFamilyCounts[$family] = ($weeklyFamilyCounts[$family] ?? 0) + 1;
            }

            foreach (($meal['components'] ?? []) as $component) {
                $foodId = (int) ($component['food_id'] ?? 0);
                if ($foodId <= 0) {
                    continue;
                }

                $type = (string) ($component['food_type'] ?? '');
                $usedWeekIds[] = $foodId;
                if ((string) $dayName === $skipDay) {
                    $usedTodayIds[] = $foodId;
                }

                $weeklyFoodCounts[$foodId] = ($weeklyFoodCounts[$foodId] ?? 0) + 1;
                if ($type !== '' && empty($countedTypeFoods[$type][$foodId])) {
                    $weeklyTypeCounts[$type] = ($weeklyTypeCounts[$type] ?? 0) + 1;
                    $countedTypeFoods[$type][$foodId] = true;
                }
            }
        }
    }

    return [
        'used_week_ids' => array_values(array_unique($usedWeekIds)),
        'used_today_ids' => array_values(array_unique($usedTodayIds)),
        'weekly_family_counts' => $weeklyFamilyCounts,
        'weekly_food_counts' => $weeklyFoodCounts,
        'weekly_type_counts' => $weeklyTypeCounts,
        'same_day_has_main_cooking' => $sameDayHasMainCooking,
    ];
}

function kcalsPlanIncludedNames(array $currentInclusions, array $foodIds): array {
    $foodIds = array_map('intval', $foodIds);
    $names = [];
    foreach ($currentInclusions as $food) {
        if (in_array((int) $food['food_id'], $foodIds, true)) {
            $names[] = ($GLOBALS['_kcals_lang'] === 'el') ? $food['name_el'] : $food['name_en'];
        }
    }
    return $names;
}

// ---- Generate / Regenerate Plan ----
$generated = false;
$genError  = '';
$replaceSuccess = false;
$undoSuccess = false;
$lockSuccess = false;
$unlockSuccess = false;
$dayRegenerateSuccess = false;
$dayRegeneratedLabel = '';
$restoreSuccess = false;
$replaceWarning = '';
$includedUsedNames = [];
$includedSkippedNames = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'undo_replace_meal') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $genError = __('err_plan_invalid');
    } else {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $dayName = (string) ($_POST['day_name'] ?? '');
        $mealIndex = (int) ($_POST['meal_index'] ?? -1);

        $planRowStmt = $db->prepare('SELECT * FROM weekly_plans WHERE id = ? AND user_id = ? LIMIT 1');
        $planRowStmt->execute([$planId, $userId]);
        $planRow = $planRowStmt->fetch();
        $planDataForUndo = $planRow ? json_decode($planRow['plan_data_json'], true) : null;

        if (
            !$planRow
            || !is_array($planDataForUndo)
            || !isset($planDataForUndo[$dayName][$mealIndex]['previous_meal'])
            || !is_array($planDataForUndo[$dayName][$mealIndex]['previous_meal'])
            || !empty($planDataForUndo[$dayName][$mealIndex]['locked'])
        ) {
            $genError = __('plan_undo_error');
        } else {
            $planDataForUndo[$dayName][$mealIndex] = $planDataForUndo[$dayName][$mealIndex]['previous_meal'];
            $upd = $db->prepare('UPDATE weekly_plans SET plan_data_json = ? WHERE id = ? AND user_id = ?');
            $upd->execute([
                json_encode($planDataForUndo, JSON_UNESCAPED_UNICODE),
                $planId,
                $userId,
            ]);
            $undoSuccess = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_lock_meal') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $genError = __('err_plan_invalid');
    } else {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $dayName = (string) ($_POST['day_name'] ?? '');
        $mealIndex = (int) ($_POST['meal_index'] ?? -1);
        $lockState = (int) ($_POST['lock_state'] ?? 0);

        $planRowStmt = $db->prepare('SELECT * FROM weekly_plans WHERE id = ? AND user_id = ? LIMIT 1');
        $planRowStmt->execute([$planId, $userId]);
        $planRow = $planRowStmt->fetch();
        $planDataForLock = $planRow ? json_decode($planRow['plan_data_json'], true) : null;

        if (
            !$planRow
            || !is_array($planDataForLock)
            || !isset($planDataForLock[$dayName][$mealIndex])
            || !is_array($planDataForLock[$dayName][$mealIndex])
        ) {
            $genError = __('plan_lock_error');
        } else {
            if ($lockState === 1) {
                $planDataForLock[$dayName][$mealIndex]['locked'] = true;
                $planDataForLock[$dayName][$mealIndex]['locked_at'] = date('c');
                $lockSuccess = true;
            } else {
                unset($planDataForLock[$dayName][$mealIndex]['locked'], $planDataForLock[$dayName][$mealIndex]['locked_at']);
                $unlockSuccess = true;
            }

            $upd = $db->prepare('UPDATE weekly_plans SET plan_data_json = ? WHERE id = ? AND user_id = ?');
            $upd->execute([
                json_encode($planDataForLock, JSON_UNESCAPED_UNICODE),
                $planId,
                $userId,
            ]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'replace_meal') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $genError = __('err_plan_invalid');
    } else {
        $replaceReasons = ['new', 'quick', 'simple', 'protein', 'avoid_food'];
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $dayName = (string) ($_POST['day_name'] ?? '');
        $mealIndex = (int) ($_POST['meal_index'] ?? -1);
        $slot = (string) ($_POST['slot'] ?? '');
        $reason = (string) ($_POST['replace_reason'] ?? 'new');
        $avoidFoodId = (int) ($_POST['avoid_food_id'] ?? 0);
        if (!in_array($reason, $replaceReasons, true)) {
            $reason = 'new';
        }

        $planRowStmt = $db->prepare('SELECT * FROM weekly_plans WHERE id = ? AND user_id = ? LIMIT 1');
        $planRowStmt->execute([$planId, $userId]);
        $planRow = $planRowStmt->fetch();
        $planDataForReplace = $planRow ? json_decode($planRow['plan_data_json'], true) : null;

        if (
            !$planRow
            || !is_array($planDataForReplace)
            || !isset($planDataForReplace[$dayName][$mealIndex])
            || !is_array($planDataForReplace[$dayName][$mealIndex])
        ) {
            $genError = __('plan_replace_error');
        } else {
            $oldMeal = $planDataForReplace[$dayName][$mealIndex];
            if (!empty($oldMeal['locked'])) {
                $genError = __('plan_replace_locked_error');
            }
            $slot = in_array($slot, ['breakfast', 'lunch', 'dinner', 'snack'], true)
                ? $slot
                : (string) ($oldMeal['slot'] ?? 'lunch');
            $targetKcal = max(120, (int) ($oldMeal['calories'] ?? 0));

            $dislikeStmt = $db->prepare('SELECT ingredient_name FROM user_dislikes WHERE user_id = ?');
            $dislikeStmt->execute([$userId]);
            $dislikes = array_column($dislikeStmt->fetchAll(), 'ingredient_name');

            $activeAllergies = [];
            foreach (['gluten','dairy','nuts','eggs','shellfish','soy'] as $a) {
                if (!empty($user['allergy_' . $a])) {
                    $activeAllergies[] = $a;
                }
            }
            $exclStmt = $db->prepare('SELECT food_id FROM user_food_exclusions WHERE user_id = ?');
            $exclStmt->execute([$userId]);
            $excludedIds = array_map('intval', array_column($exclStmt->fetchAll(), 'food_id'));

            require_once __DIR__ . '/engine/meal_builder.php';

            $context = kcalsPlanReplacementContext($planDataForReplace, $dayName, $mealIndex, $mainCookingFamilies);
            $avoidIds = kcalsPlanReplacementAvoidIds($oldMeal, $reason);
            if ($reason === 'avoid_food') {
                if ($avoidFoodId <= 0 || !in_array($avoidFoodId, kcalsPlanComponentIds($oldMeal), true)) {
                    $genError = __('plan_replace_error');
                } else {
                    $avoidIds[] = $avoidFoodId;
                    if (!empty($_POST['save_food_exclusion'])) {
                        $saveExcl = $db->prepare('INSERT IGNORE INTO user_food_exclusions (user_id, food_id) VALUES (?, ?)');
                        $saveExcl->execute([$userId, $avoidFoodId]);
                    }
                }
            }

            $slotProfile = [
                'adventure'    => (int) ($user['food_adventure'] ?? 2),
                'allergies'    => $activeAllergies,
                'excluded_ids' => array_values(array_unique(array_merge($excludedIds, $avoidIds))),
                'sleep_boost'  => $sleepBoost,
                'strength_day' => $strengthDay,
                'comfort_food_mode' => $isRecoveryMode,
                'complex_carb_bias' => isRechargeDay($dayName, $rechargeDay),
                'weekly_family_counts' => $context['weekly_family_counts'],
                'weekly_food_counts' => $context['weekly_food_counts'],
                'weekly_type_counts' => $context['weekly_type_counts'],
            ];

            if ($genError === '' && $reason === 'quick') {
                $slotProfile['quick_main_only'] = true;
                $slotProfile['max_prep_minutes'] = in_array($slot, ['lunch', 'dinner'], true) ? 15 : 8;
                if (in_array($slot, ['lunch', 'dinner'], true)) {
                    $slotProfile['avoid_meal_families'] = $mainCookingFamilies;
                }
            } elseif ($genError === '' && $reason === 'simple') {
                $slotProfile['adventure'] = 0;
                $slotProfile['quick_main_only'] = true;
                $slotProfile['max_prep_minutes'] = in_array($slot, ['lunch', 'dinner'], true) ? 20 : 8;
                $slotProfile['avoid_meal_families'] = ['heavy_mixed', 'shellfish', 'red_meat', 'pork', 'lamb'];
            } elseif ($genError === '' && $reason === 'protein') {
                $slotProfile['quick_main_only'] = false;
            }

            if (
                $genError === ''
                &&
                in_array($slot, ['lunch', 'dinner'], true)
                && !empty($context['same_day_has_main_cooking'])
            ) {
                $slotProfile['quick_main_only'] = true;
                $slotProfile['max_prep_minutes'] = min((int) ($slotProfile['max_prep_minutes'] ?? 20), 20);
                $slotProfile['avoid_meal_families'] = array_values(array_unique(array_merge(
                    (array) ($slotProfile['avoid_meal_families'] ?? []),
                    $mainCookingFamilies
                )));
            }

            if ($genError === '') {
                $builder = new MealBuilder($db, (string) $user['diet_type'], (int) date('n'), $dislikes, $slotProfile);
                $usedWeek = array_values(array_unique(array_merge($context['used_week_ids'], $avoidIds)));
                $usedToday = array_values(array_unique(array_merge($context['used_today_ids'], $avoidIds)));
                $replacement = $builder->buildMeal($slot, $targetKcal, $usedWeek, $usedToday);
            } else {
                $replacement = [];
            }

            if (empty($replacement['components'])) {
                $genError = __('plan_replace_error');
            } else {
                $replacement['replaced'] = true;
                $replacement['replace_reason'] = $reason;
                $replacement['replaced_at'] = date('c');
                $replacement['previous_meal'] = $oldMeal;
                $planDataForReplace[$dayName][$mealIndex] = $replacement;

                $upd = $db->prepare('UPDATE weekly_plans SET plan_data_json = ? WHERE id = ? AND user_id = ?');
                $upd->execute([
                    json_encode($planDataForReplace, JSON_UNESCAPED_UNICODE),
                    $planId,
                    $userId,
                ]);

                $replaceSuccess = true;

                if (kcalsPlanMealHasAnyFood($oldMeal, $includedIds)) {
                    $foodIdsInPlan = [];
                    foreach ($planDataForReplace as $dayMeals) {
                        foreach ($dayMeals as $meal) {
                            $foodIdsInPlan = array_merge($foodIdsInPlan, kcalsPlanComponentIds($meal));
                        }
                    }
                    $foodIdsInPlan = array_values(array_unique($foodIdsInPlan));
                    $missingIncludeIds = array_values(array_diff($includedIds, $foodIdsInPlan));
                    if (!empty($missingIncludeIds)) {
                        $replaceWarning = sprintf(
                            __('plan_replace_include_warning'),
                            htmlspecialchars(implode(', ', kcalsPlanIncludedNames($currentInclusions, $missingIncludeIds)))
                        );
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regenerate_day') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $genError = __('err_plan_invalid');
    } else {
        $dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        $dayName = (string) ($_POST['day_name'] ?? '');
        $planId = (int) ($_POST['plan_id'] ?? 0);

        $planRowStmt = $db->prepare('SELECT * FROM weekly_plans WHERE id = ? AND user_id = ? LIMIT 1');
        $planRowStmt->execute([$planId, $userId]);
        $planRow = $planRowStmt->fetch();
        $planDataForDay = $planRow ? json_decode($planRow['plan_data_json'], true) : null;

        if (
            !$planRow
            || !is_array($planDataForDay)
            || !in_array($dayName, $dayNames, true)
            || !isset($planDataForDay[$dayName])
            || !is_array($planDataForDay[$dayName])
        ) {
            $genError = __('plan_day_regenerate_error');
        } else {
            $currentMonth = (int) date('n');
            $targetCalories = (int) ($planRow['target_calories'] ?? $stats['target_kcal']);
            $dietType = $user['diet_type'];

            $dislikeStmt = $db->prepare('SELECT ingredient_name FROM user_dislikes WHERE user_id = ?');
            $dislikeStmt->execute([$userId]);
            $dislikes = array_column($dislikeStmt->fetchAll(), 'ingredient_name');

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

            require_once __DIR__ . '/engine/meal_builder.php';

            $lockedMealsByDaySlot = kcalsPlanLockedMealsByDaySlot($planDataForDay);
            $lockedMealsForDay = $lockedMealsByDaySlot[$dayName] ?? [];
            $lockedFoodIdsForDay = [];
            foreach ($lockedMealsForDay as $lockedMeal) {
                $lockedFoodIdsForDay = array_merge($lockedFoodIdsForDay, kcalsPlanComponentIds($lockedMeal));
            }
            $lockedFoodIdsForDay = array_values(array_unique($lockedFoodIdsForDay));

            $usedFoodIds = [];
            $weeklyFamilyCounts = [];
            $weeklyFoodCounts = [];
            $weeklyTypeCounts = [];
            $countedTypeFoods = [];

            foreach ($planDataForDay as $existingDay => $existingMeals) {
                if ((string) $existingDay === $dayName || !is_array($existingMeals)) {
                    continue;
                }
                $contextDayFoodIds = [];
                $contextHasIncluded = false;
                $contextHasMainCooking = false;
                foreach ($existingMeals as $existingMeal) {
                    if (!is_array($existingMeal)) {
                        continue;
                    }
                    kcalsPlanRegisterMealUsage(
                        $existingMeal,
                        $includedIds,
                        $mainCookingFamilies,
                        $usedFoodIds,
                        $contextDayFoodIds,
                        $weeklyFamilyCounts,
                        $weeklyFoodCounts,
                        $weeklyTypeCounts,
                        $countedTypeFoods,
                        $remainingIncludeIds,
                        $includedUsedIds,
                        $contextHasIncluded,
                        $contextHasMainCooking
                    );
                }
            }

            $dayTarget = applySocialBuffer($targetCalories, $dayName);
            $isDayRecharge = isRechargeDay($dayName, $rechargeDay);
            if ($isDayRecharge) {
                $dayTarget = applyRechargeDay($dayTarget, $dayName, $rechargeDay);
            }
            $mealTargets = [
                'breakfast' => (int) round($dayTarget * 0.25),
                'lunch'     => (int) round($dayTarget * 0.35),
                'dinner'    => (int) round($dayTarget * 0.30),
                'snack'     => (int) round($dayTarget * 0.10),
            ];

            $newDayMeals = [];
            $planSchedule = [];
            $dayFoodIds = [];
            $dayHasIncludedFood = false;
            $dayHasMainCooking = false;

            foreach ($mealTargets as $slot => $kcalTarget) {
                $slotProfile = $profile;
                $slotProfile['complex_carb_bias'] = $isDayRecharge;

                $otherLockedMainCooking = kcalsPlanDayHasOtherLockedMainCooking($lockedMealsForDay, $slot, $mainCookingFamilies);
                if (($slot === 'dinner' && $dayHasMainCooking) || $otherLockedMainCooking) {
                    $slotProfile['quick_main_only'] = true;
                    $slotProfile['max_prep_minutes'] = 20;
                    $slotProfile['avoid_meal_families'] = $mainCookingFamilies;
                }
                $slotProfile['weekly_family_counts'] = $weeklyFamilyCounts;
                $slotProfile['weekly_food_counts'] = $weeklyFoodCounts;
                $slotProfile['weekly_type_counts'] = $weeklyTypeCounts;
                $planSchedule[$slot] = [
                    'target' => $kcalTarget,
                    'profile' => $slotProfile,
                ];

                $meal = null;
                $lockedMeal = $lockedMealsForDay[$slot] ?? null;
                if (is_array($lockedMeal) && !empty($lockedMeal['components'])) {
                    $meal = $lockedMeal;
                    $meal['locked'] = true;
                    unset($meal['previous_meal']);
                }

                $builder = null;
                $reserveDinnerForInclude = false;
                $weekAvoidIds = array_values(array_unique(array_merge($usedFoodIds, $lockedFoodIdsForDay)));
                $todayAvoidIds = array_values(array_unique(array_merge($dayFoodIds, $lockedFoodIdsForDay)));
                if ($meal === null) {
                    $builder = new MealBuilder($db, $dietType, $currentMonth, $dislikes, $slotProfile);
                }
                if ($meal === null && !$dayHasIncludedFood) {
                    foreach ($remainingIncludeIds as $includeIndex => $includeId) {
                        $candidateMeal = $builder->buildMealWithFood($slot, $kcalTarget, (int) $includeId, $weekAvoidIds, $todayAvoidIds);
                        if ($candidateMeal !== null) {
                            $meal = $candidateMeal;
                            $includedUsedIds[] = (int) $includeId;
                            $dayHasIncludedFood = true;
                            unset($remainingIncludeIds[$includeIndex]);
                            $remainingIncludeIds = array_values($remainingIncludeIds);
                            break;
                        }
                    }
                }
                if (
                    $meal === null
                    && $slot === 'lunch'
                    && !$dayHasIncludedFood
                    && !empty($remainingIncludeIds)
                    && isset($mealTargets['dinner'])
                ) {
                    foreach ($remainingIncludeIds as $includeId) {
                        if (
                            $builder->buildMealWithFood('lunch', $kcalTarget, (int) $includeId, [], []) === null
                            && $builder->buildMealWithFood('dinner', (int) $mealTargets['dinner'], (int) $includeId, [], []) !== null
                        ) {
                            $reserveDinnerForInclude = true;
                            break;
                        }
                    }
                }
                if ($meal === null) {
                    if ($reserveDinnerForInclude) {
                        $supportProfile = $slotProfile;
                        $supportProfile['quick_main_only'] = true;
                        $supportProfile['max_prep_minutes'] = 20;
                        $supportProfile['avoid_meal_families'] = $mainCookingFamilies;
                        $supportBuilder = new MealBuilder($db, $dietType, $currentMonth, $dislikes, $supportProfile);
                        $meal = $supportBuilder->buildMeal($slot, $kcalTarget, $weekAvoidIds, $todayAvoidIds);
                    } else {
                        $meal = $builder->buildMeal($slot, $kcalTarget, $weekAvoidIds, $todayAvoidIds);
                    }
                }

                $newDayMeals[] = $meal;
                kcalsPlanRegisterMealUsage(
                    $meal,
                    $includedIds,
                    $mainCookingFamilies,
                    $usedFoodIds,
                    $dayFoodIds,
                    $weeklyFamilyCounts,
                    $weeklyFoodCounts,
                    $weeklyTypeCounts,
                    $countedTypeFoods,
                    $remainingIncludeIds,
                    $includedUsedIds,
                    $dayHasIncludedFood,
                    $dayHasMainCooking
                );
            }

            $planDataForDay[$dayName] = $newDayMeals;

            $foodIdsInPlan = [];
            foreach ($planDataForDay as $dayMeals) {
                foreach ((array) $dayMeals as $meal) {
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
                foreach ($planDataForDay[$dayName] as $mealIndex => $existingMeal) {
                    if (!empty($existingMeal['locked'])) {
                        continue;
                    }
                    $existingFoodIds = array_map(
                        fn($component) => (int) ($component['food_id'] ?? 0),
                        $existingMeal['components'] ?? []
                    );
                    if (!empty(array_intersect($existingFoodIds, $includedIds))) {
                        continue;
                    }

                    $slot = $existingMeal['slot'] ?? 'lunch';
                    $schedule = $planSchedule[$slot] ?? null;
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

                    $planDataForDay[$dayName][$mealIndex] = $replacement;
                    $foodIdsInPlan[] = (int) $missingIncludeId;
                    $foodIdsInPlan = array_values(array_unique($foodIdsInPlan));
                    break;
                }
            }

            $upd = $db->prepare('UPDATE weekly_plans SET plan_data_json = ? WHERE id = ? AND user_id = ?');
            $upd->execute([
                json_encode($planDataForDay, JSON_UNESCAPED_UNICODE),
                $planId,
                $userId,
            ]);

            $dayRegenerateSuccess = true;
            $dayRegeneratedLabel = __('day_' . strtolower($dayName));
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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_plan') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $genError = __('err_plan_invalid');
    } else {
        $restorePlanId = (int) ($_POST['restore_plan_id'] ?? 0);
        $planRowStmt = $db->prepare('SELECT * FROM weekly_plans WHERE id = ? AND user_id = ? LIMIT 1');
        $planRowStmt->execute([$restorePlanId, $userId]);
        $restorePlanRow = $planRowStmt->fetch();
        $restorePlanData = $restorePlanRow ? json_decode($restorePlanRow['plan_data_json'], true) : null;

        if (!$restorePlanRow || !is_array($restorePlanData)) {
            $genError = __('plan_history_restore_error');
        } else {
            $ins = $db->prepare('
                INSERT INTO weekly_plans (user_id, start_date, end_date, target_calories, zone, plan_data_json)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([
                $userId,
                $restorePlanRow['start_date'],
                $restorePlanRow['end_date'],
                (int) $restorePlanRow['target_calories'],
                $restorePlanRow['zone'],
                json_encode($restorePlanData, JSON_UNESCAPED_UNICODE),
            ]);
            $restoreSuccess = true;
        }
    }
}

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

        $lockedPlanStmt = $db->prepare('
            SELECT plan_data_json
            FROM weekly_plans
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC LIMIT 1
        ');
        $lockedPlanStmt->execute([$userId]);
        $lockedPlanRow = $lockedPlanStmt->fetch();
        $lockedPlanData = $lockedPlanRow ? json_decode($lockedPlanRow['plan_data_json'], true) : null;
        $lockedMealsByDaySlot = kcalsPlanLockedMealsByDaySlot($lockedPlanData);

        $mainCookingFamilies = ['heavy_mixed', 'fish', 'shellfish', 'red_meat', 'pork', 'lamb', 'poultry'];
        $mealHasMainCooking = function (array $meal) use ($mainCookingFamilies): bool {
            foreach (($meal['components'] ?? []) as $component) {
                $family = (string) ($component['meal_family'] ?? '');
                $effort = (string) ($component['cooking_effort'] ?? '');
                if ($effort === 'main_cooking' || in_array($family, $mainCookingFamilies, true)) {
                    return true;
                }
            }
            return false;
        };
        $mealMainFamilies = function (array $meal) use ($mainCookingFamilies): array {
            $families = [];
            foreach (($meal['components'] ?? []) as $component) {
                $family = (string) ($component['meal_family'] ?? '');
                $effort = (string) ($component['cooking_effort'] ?? '');
                if ($effort === 'main_cooking' || in_array($family, $mainCookingFamilies, true)) {
                    $families[$family] = true;
                }
            }
            return array_keys($families);
        };
        $mealScoredFamilies = function (array $meal) use ($mainCookingFamilies): array {
            $families = [];
            $countableFamilies = array_merge($mainCookingFamilies, ['legume', 'dairy', 'eggs', 'plant_protein']);
            foreach (($meal['components'] ?? []) as $component) {
                $family = (string) ($component['meal_family'] ?? '');
                $type = (string) ($component['food_type'] ?? '');
                $effort = (string) ($component['cooking_effort'] ?? '');
                if (
                    in_array($family, $countableFamilies, true)
                    && ($effort === 'main_cooking' || in_array($type, ['protein', 'dairy', 'mixed'], true))
                ) {
                    $families[$family] = true;
                }
            }
            return array_keys($families);
        };

        $dayNames    = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        $planData    = [];
        $planSchedule = [];
        $usedFoodIds = []; // All food IDs used this week (for variety)
        $weeklyFamilyCounts = [];
        $weeklyFoodCounts = [];
        $weeklyTypeCounts = [];
        $countedTypeFoods = [];

        foreach ($dayNames as $day) {
            $dayMeals   = [];
            $dayFoodIds = []; // Food IDs used in earlier slots today (avoid same protein lunch+dinner)
            $dayHasIncludedFood = false;
            $dayHasMainCooking = false;

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
            $lockedMealsForDay = $lockedMealsByDaySlot[$day] ?? [];

            foreach ($mealTargets as $slot => $kcalTarget) {
                $slotProfile = $profile;
                // Pass complex_carb_bias on the recharge day
                if ($isDayRecharge) {
                    $slotProfile['complex_carb_bias'] = true;
                } else {
                    $slotProfile['complex_carb_bias'] = false;
                }

                $otherLockedMainCooking = kcalsPlanDayHasOtherLockedMainCooking($lockedMealsForDay, $slot, $mainCookingFamilies);
                if (($slot === 'dinner' && $dayHasMainCooking) || $otherLockedMainCooking) {
                    $slotProfile['quick_main_only'] = true;
                    $slotProfile['max_prep_minutes'] = 20;
                    $slotProfile['avoid_meal_families'] = $mainCookingFamilies;
                }
                $slotProfile['weekly_family_counts'] = $weeklyFamilyCounts;
                $slotProfile['weekly_food_counts'] = $weeklyFoodCounts;
                $slotProfile['weekly_type_counts'] = $weeklyTypeCounts;
                $planSchedule[$day][$slot] = [
                    'target'  => $kcalTarget,
                    'profile' => $slotProfile,
                ];
                $meal = null;
                $lockedMeal = $lockedMealsForDay[$slot] ?? null;
                if (is_array($lockedMeal) && !empty($lockedMeal['components'])) {
                    $meal = $lockedMeal;
                    $meal['locked'] = true;
                    unset($meal['previous_meal']);
                }

                $builder = null;
                $reserveDinnerForInclude = false;
                if ($meal === null) {
                    $builder = new MealBuilder($db, $dietType, $currentMonth, $dislikes, $slotProfile);
                }
                if ($meal === null && !$dayHasIncludedFood) {
                    foreach ($remainingIncludeIds as $includeIndex => $includeId) {
                        $candidateMeal = $builder->buildMealWithFood($slot, $kcalTarget, (int) $includeId, $usedFoodIds, $dayFoodIds);
                        if ($candidateMeal !== null) {
                            $meal = $candidateMeal;
                            $includedUsedIds[] = (int) $includeId;
                            $dayHasIncludedFood = true;
                            unset($remainingIncludeIds[$includeIndex]);
                            $remainingIncludeIds = array_values($remainingIncludeIds);
                            break;
                        }
                    }
                }
                if (
                    $meal === null
                    && $slot === 'lunch'
                    && !$dayHasIncludedFood
                    && !empty($remainingIncludeIds)
                    && isset($mealTargets['dinner'])
                ) {
                    foreach ($remainingIncludeIds as $includeId) {
                        if (
                            $builder->buildMealWithFood('lunch', $kcalTarget, (int) $includeId, [], []) === null
                            && $builder->buildMealWithFood('dinner', (int) $mealTargets['dinner'], (int) $includeId, [], []) !== null
                        ) {
                            $reserveDinnerForInclude = true;
                            break;
                        }
                    }
                }
                if ($meal === null) {
                    if ($reserveDinnerForInclude) {
                        $supportProfile = $slotProfile;
                        $supportProfile['quick_main_only'] = true;
                        $supportProfile['max_prep_minutes'] = 20;
                        $supportProfile['avoid_meal_families'] = $mainCookingFamilies;
                        $supportBuilder = new MealBuilder($db, $dietType, $currentMonth, $dislikes, $supportProfile);
                        $meal = $supportBuilder->buildMeal($slot, $kcalTarget, $usedFoodIds, $dayFoodIds);
                    } else {
                        $meal = $builder->buildMeal($slot, $kcalTarget, $usedFoodIds, $dayFoodIds);
                    }
                }
                if (($slot === 'lunch' || $slot === 'dinner') && $mealHasMainCooking($meal)) {
                    $dayHasMainCooking = true;
                }
                foreach ($mealScoredFamilies($meal) as $family) {
                    $weeklyFamilyCounts[$family] = ($weeklyFamilyCounts[$family] ?? 0) + 1;
                }
                $dayMeals[] = $meal;
                foreach ($meal['components'] as $c) {
                    if ($c['food_id'] > 0) {
                        $usedFoodIds[] = $c['food_id'];
                        $dayFoodIds[]  = $c['food_id'];
                        $componentFoodId = (int) $c['food_id'];
                        $componentType = (string) ($c['food_type'] ?? '');
                        $weeklyFoodCounts[$componentFoodId] = ($weeklyFoodCounts[$componentFoodId] ?? 0) + 1;
                        if ($componentType !== '' && empty($countedTypeFoods[$componentType][$componentFoodId])) {
                            $weeklyTypeCounts[$componentType] = ($weeklyTypeCounts[$componentType] ?? 0) + 1;
                            $countedTypeFoods[$componentType][$componentFoodId] = true;
                        }
                        if (in_array((int) $c['food_id'], $includedIds, true)) {
                            $dayHasIncludedFood = true;
                            $includeIndex = array_search((int) $c['food_id'], $remainingIncludeIds, true);
                            if ($includeIndex !== false) {
                                $includedUsedIds[] = (int) $c['food_id'];
                                unset($remainingIncludeIds[$includeIndex]);
                                $remainingIncludeIds = array_values($remainingIncludeIds);
                            }
                        }
                    }
                }
            }
            $planData[$day] = $dayMeals;
        }

        // Strict second pass: if any saved must-include food did not land naturally
        // or during the first forced pass, replace an ordinary compatible meal.
        $foodIdsInPlan = [];
        $daysWithIncludedFood = [];
        foreach ($planData as $day => $dayMeals) {
            foreach ($dayMeals as $meal) {
                foreach (($meal['components'] ?? []) as $component) {
                    $componentFoodId = (int) ($component['food_id'] ?? 0);
                    if ($componentFoodId > 0) {
                        $foodIdsInPlan[] = $componentFoodId;
                    }
                    if (in_array($componentFoodId, $includedIds, true)) {
                        $daysWithIncludedFood[$day] = true;
                    }
                }
            }
        }
        $foodIdsInPlan = array_values(array_unique($foodIdsInPlan));
        $missingIncludeIds = array_values(array_diff($includedIds, $foodIdsInPlan, $excludedIds));

        foreach ($missingIncludeIds as $missingIncludeId) {
            $placed = false;
            foreach ([true, false] as $preferOpenDay) {
                foreach ($planData as $day => &$dayMeals) {
                    if ($preferOpenDay && !empty($daysWithIncludedFood[$day])) {
                        continue;
                    }

                    foreach ($dayMeals as $mealIndex => $existingMeal) {
                        if (!empty($existingMeal['locked'])) {
                            continue;
                        }

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
                        $daysWithIncludedFood[$day] = true;
                        $placed = true;
                        break 3;
                    }
                }
                unset($dayMeals);
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
            $targetCalories, $zone, json_encode($planData, JSON_UNESCAPED_UNICODE)
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
    ORDER BY created_at DESC, id DESC LIMIT 1
');
$planStmt->execute([$userId]);
$plan = $planStmt->fetch();
$planData = $plan ? json_decode($plan['plan_data_json'], true) : null;

$historyStmt = $db->prepare('
    SELECT *
    FROM weekly_plans
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 8
');
$historyStmt->execute([$userId]);
$planHistory = $historyStmt->fetchAll();

$previewPlan = null;
$previewPlanData = null;
$viewPlan = $plan;
$viewPlanData = $planData;
$isHistoryPreview = false;
$previewComparison = null;
$previewPlanId = (int) ($_GET['preview_plan_id'] ?? 0);
if ($previewPlanId > 0) {
    $previewStmt = $db->prepare('SELECT * FROM weekly_plans WHERE id = ? AND user_id = ? LIMIT 1');
    $previewStmt->execute([$previewPlanId, $userId]);
    $previewPlan = $previewStmt->fetch();
    $previewPlanData = $previewPlan ? json_decode($previewPlan['plan_data_json'], true) : null;

    if (!$previewPlan || !is_array($previewPlanData)) {
        $genError = __('plan_history_preview_error');
    } else {
        $viewPlan = $previewPlan;
        $viewPlanData = $previewPlanData;
        $isHistoryPreview = !$plan || (int) $previewPlan['id'] !== (int) $plan['id'];
        if ($isHistoryPreview && is_array($planData)) {
            $previewComparison = kcalsPlanComparePlans($planData, $previewPlanData, $mainCookingFamilies);
        }
    }
}

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
            <a href="#plan-history" class="btn btn-outline">
                <i data-lucide="history" style="width:15px;height:15px;"></i>
                <?= __('plan_history_btn') ?>
            </a>
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

    <?php if ($replaceSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;">
        <strong><?= __('plan_replace_success') ?></strong> <?= __('plan_replace_success_desc') ?>
    </div>
    <?php endif; ?>
    <?php if ($undoSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;">
        <strong><?= __('plan_undo_success') ?></strong> <?= __('plan_undo_success_desc') ?>
    </div>
    <?php endif; ?>
    <?php if ($lockSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;">
        <strong><?= __('plan_lock_success') ?></strong> <?= __('plan_lock_success_desc') ?>
    </div>
    <?php endif; ?>
    <?php if ($unlockSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;">
        <strong><?= __('plan_unlock_success') ?></strong> <?= __('plan_unlock_success_desc') ?>
    </div>
    <?php endif; ?>
    <?php if ($dayRegenerateSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;">
        <strong><?= sprintf(__('plan_day_regenerate_success'), htmlspecialchars($dayRegeneratedLabel)) ?></strong>
        <?= __('plan_day_regenerate_success_desc') ?>
    </div>
    <?php endif; ?>
    <?php if ($restoreSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;">
        <strong><?= __('plan_history_restore_success') ?></strong> <?= __('plan_history_restore_success_desc') ?>
    </div>
    <?php endif; ?>
    <?php if ($replaceWarning): ?>
    <div class="alert" style="background:#fff7ed; border:1px solid #fdba74; color:#9a3412; margin-bottom:1rem;">
        <?= $replaceWarning ?>
    </div>
    <?php endif; ?>

    <?php if ($genError): ?>
    <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= htmlspecialchars($genError) ?></div>
    <?php endif; ?>

    <?php if ($isHistoryPreview && $viewPlan): ?>
    <div class="plan-preview-banner no-print">
        <div>
            <strong><?= __('plan_history_previewing') ?></strong>
            <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime($viewPlan['created_at']))) ?></span>
        </div>
        <div class="plan-preview-actions">
            <a href="<?= BASE_URL ?>/plan.php" class="btn btn-outline btn-sm">
                <i data-lucide="arrow-left" style="width:13px;height:13px;"></i>
                <?= __('plan_history_back_current') ?>
            </a>
            <form method="POST" action="<?= BASE_URL ?>/plan.php" class="plan-history-restore">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="action" value="restore_plan">
                <input type="hidden" name="restore_plan_id" value="<?= (int) $viewPlan['id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm"
                        onclick="return confirm(<?= htmlspecialchars(json_encode(__('plan_history_restore_confirm')), ENT_QUOTES) ?>)">
                    <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
                    <?= __('plan_history_restore_btn') ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isHistoryPreview && is_array($previewComparison)): ?>
    <div class="plan-preview-compare no-print">
        <div class="plan-preview-compare-title"><?= __('plan_compare_title') ?></div>
        <div class="plan-preview-compare-grid">
            <span><?= sprintf(__('plan_compare_changed_meals'), (int) $previewComparison['changed_meals']) ?></span>
            <span><?= sprintf(__('plan_compare_changed_days'), (int) $previewComparison['changed_days']) ?></span>
            <span><?= sprintf(__('plan_compare_avg_kcal'), (int) $previewComparison['avg_kcal_diff']) ?></span>
            <span class="<?= (int) $previewComparison['heavy_day_diff'] > 0 ? 'is-worse' : ((int) $previewComparison['heavy_day_diff'] < 0 ? 'is-better' : '') ?>">
                <?= sprintf(__('plan_compare_heavy_days'), (int) $previewComparison['heavy_day_diff']) ?>
            </span>
            <span><?= sprintf(__('plan_compare_locked'), (int) $previewComparison['locked_diff']) ?></span>
        </div>
    </div>
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

    <?php if ($viewPlanData): ?>

    <!-- Plan metadata -->
    <?php if ($viewPlan): ?>
    <div class="no-print" style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem;">
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <div style="font-size:1.4rem; font-weight:800; color:var(--green-dark);"><?= $viewPlan['target_calories'] ?></div>
            <div class="text-small text-muted"><?= __('plan_kcal_target') ?></div>
        </div>
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <div style="font-size:1.4rem; font-weight:800; color:var(--slate);"><?= $viewPlan['start_date'] ?></div>
            <div class="text-small text-muted"><?= __('plan_week_start') ?></div>
        </div>
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <div style="font-size:1.4rem; font-weight:800; color:var(--slate);"><?= $viewPlan['end_date'] ?></div>
            <div class="text-small text-muted"><?= __('plan_week_end') ?></div>
        </div>
        <div class="card" style="flex:1; min-width:140px; padding:1rem; text-align:center;">
            <?php
                $totalPlanKcal = 0;
                foreach ($viewPlanData as $dayMeals) $totalPlanKcal += array_sum(array_column($dayMeals, 'calories'));
                $avgKcal = count($viewPlanData) > 0 ? round($totalPlanKcal / count($viewPlanData)) : 0;
            ?>
            <div style="font-size:1.4rem; font-weight:800; color:var(--slate);"><?= $avgKcal ?></div>
            <div class="text-small text-muted"><?= __('plan_avg_kcal') ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($planHistory)): ?>
    <section id="plan-history" class="plan-history no-print">
        <div class="plan-history-header">
            <div>
                <h2><?= __('plan_history_title') ?></h2>
                <p><?= __('plan_history_desc') ?></p>
            </div>
            <span><?= sprintf(__('plan_history_count'), count($planHistory)) ?></span>
        </div>
        <div class="plan-history-list">
            <?php foreach ($planHistory as $historyPlan): ?>
            <?php
                $historyData = json_decode($historyPlan['plan_data_json'], true);
                $historySummary = kcalsPlanHistorySummary($historyData, $mainCookingFamilies);
                $isCurrentHistoryPlan = $plan && (int) $historyPlan['id'] === (int) $plan['id'];
                $isPreviewHistoryPlan = $isHistoryPreview && $viewPlan && (int) $historyPlan['id'] === (int) $viewPlan['id'];
                $qualitySummaryParts = [];
                foreach (['easy', 'balanced', 'watch', 'heavy'] as $qualityStatus) {
                    $count = (int) ($historySummary['quality_counts'][$qualityStatus] ?? 0);
                    if ($count > 0) {
                        $qualitySummaryParts[] = sprintf(__('plan_history_quality_' . $qualityStatus), $count);
                    }
                }
            ?>
            <div class="plan-history-item<?= $isCurrentHistoryPlan ? ' is-current' : '' ?><?= $isPreviewHistoryPlan ? ' is-previewed' : '' ?>">
                <div class="plan-history-main">
                    <div class="plan-history-title">
                        <strong><?= htmlspecialchars(date('d/m/Y H:i', strtotime($historyPlan['created_at']))) ?></strong>
                        <?php if ($isCurrentHistoryPlan): ?>
                        <span><?= __('plan_history_current') ?></span>
                        <?php endif; ?>
                        <?php if ($isPreviewHistoryPlan): ?>
                        <span class="is-preview"><?= __('plan_history_preview_badge') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="plan-history-meta">
                        <span><?= htmlspecialchars($historyPlan['start_date']) ?> → <?= htmlspecialchars($historyPlan['end_date']) ?></span>
                        <span><?= (int) $historyPlan['target_calories'] ?> kcal</span>
                        <span><?= sprintf(__('plan_history_avg'), (int) $historySummary['avg_kcal']) ?></span>
                        <?php if ((int) $historySummary['locked_count'] > 0): ?>
                        <span><?= sprintf(__('plan_quality_locked'), (int) $historySummary['locked_count']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($qualitySummaryParts)): ?>
                    <div class="plan-history-quality"><?= htmlspecialchars(implode(' · ', $qualitySummaryParts)) ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!$isCurrentHistoryPlan): ?>
                <div class="plan-history-actions">
                    <a href="<?= BASE_URL ?>/plan.php?preview_plan_id=<?= (int) $historyPlan['id'] ?>#plan-preview" class="btn btn-outline btn-sm">
                        <i data-lucide="eye" style="width:13px;height:13px;"></i>
                        <?= __('plan_history_preview_btn') ?>
                    </a>
                    <form method="POST" action="<?= BASE_URL ?>/plan.php" class="plan-history-restore">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="action" value="restore_plan">
                        <input type="hidden" name="restore_plan_id" value="<?= (int) $historyPlan['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm"
                                onclick="return confirm(<?= htmlspecialchars(json_encode(__('plan_history_restore_confirm')), ENT_QUOTES) ?>)">
                            <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
                            <?= __('plan_history_restore_btn') ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 7-Day Grid -->
    <div class="no-print">
    <div class="plan-grid" id="plan-preview">
        <?php foreach ($viewPlanData as $dayName => $meals): ?>
        <?php
            $dayTotal = array_sum(array_column($meals, 'calories'));
            $isDayRechargeView = isRechargeDay($dayName, $rechargeDay);
            $dayQuality = kcalsPlanDayQuality($meals, $mainCookingFamilies);
        ?>
        <div class="day-card<?= $isDayRechargeView ? ' recharge-day-card' : '' ?>">
            <div class="day-card-header">
                <div class="day-card-title">
                    <span class="day-name"><?= __('day_' . strtolower($dayName)) ?></span>
                    <?php if ($isDayRechargeView): ?>
                    <span class="recharge-badge" title="<?= __('plan_recharge_badge_title') ?>">
                        ⚡ <?= __('plan_recharge_badge') ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="day-card-actions no-print">
                    <?php if ($plan && !$isHistoryPreview): ?>
                    <form method="POST" action="<?= BASE_URL ?>/plan.php" class="day-regenerate-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="action" value="regenerate_day">
                        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                        <input type="hidden" name="day_name" value="<?= htmlspecialchars($dayName) ?>">
                        <button type="submit" class="day-regenerate-btn" title="<?= htmlspecialchars(__('plan_day_regenerate_title')) ?>">
                            <i data-lucide="refresh-cw" style="width:12px;height:12px;"></i>
                            <span><?= __('plan_day_regenerate_btn') ?></span>
                        </button>
                    </form>
                    <?php endif; ?>
                    <span class="day-kcal"><?= $dayTotal ?> kcal</span>
                </div>
            </div>
            <div class="day-quality day-quality-<?= htmlspecialchars($dayQuality['status']) ?>">
                <span class="day-quality-status"><?= __('plan_quality_' . $dayQuality['status']) ?></span>
                <span><?= sprintf(__('plan_quality_prep'), (int) $dayQuality['prep_minutes']) ?></span>
                <span><?= sprintf(__('plan_quality_ingredients'), (int) $dayQuality['ingredient_count']) ?></span>
                <span><?= sprintf(__('plan_quality_main_cooking'), (int) $dayQuality['main_cooking_count']) ?></span>
                <?php if ((int) $dayQuality['locked_count'] > 0): ?>
                <span><?= sprintf(__('plan_quality_locked'), (int) $dayQuality['locked_count']) ?></span>
                <?php endif; ?>
            </div>
            <div class="meal-list">
                <?php foreach ($meals as $mealIndex => $meal): ?>
                <?php
                    $mSlot = $meal['slot'] ?? $meal['category'] ?? 'lunch';
                    $mName = ($GLOBALS['_kcals_lang'] === 'el')
                        ? ($meal['name_el'] ?? $meal['title'] ?? '')
                        : ($meal['name_en'] ?? $meal['title'] ?? '');
                    $mealHasIncludedFood = kcalsPlanMealHasAnyFood($meal, $includedIds);
                    $replaceReasonLabels = [
                        'new' => __('plan_replace_new'),
                        'quick' => __('plan_replace_quick'),
                        'simple' => __('plan_replace_simple'),
                        'protein' => __('plan_replace_protein'),
                        'avoid_food' => __('plan_replace_avoid_food'),
                    ];
                    $replaceReason = (string) ($meal['replace_reason'] ?? 'new');
                    $replaceReasonLabel = $replaceReasonLabels[$replaceReason] ?? __('plan_replace_new');
                    $mealLocked = !empty($meal['locked']);
                ?>
                <div class="meal-item<?= !empty($meal['replaced']) ? ' meal-item-replaced' : '' ?><?= $mealLocked ? ' meal-item-locked' : '' ?>">
                    <div class="meal-dot <?= htmlspecialchars($mSlot) ?>"></div>
                    <div class="meal-info">
                        <div class="meal-type">
                            <?= __('meal_slot_' . $mSlot) ?>
                            <?php if (!empty($meal['replaced'])): ?>
                            <span class="meal-replaced-badge"><?= __('plan_replace_badge') ?>: <?= htmlspecialchars($replaceReasonLabel) ?></span>
                            <?php endif; ?>
                            <?php if ($mealLocked): ?>
                            <span class="meal-locked-badge"><i data-lucide="lock" style="width:11px;height:11px;"></i><?= __('plan_locked_badge') ?></span>
                            <?php endif; ?>
                        </div>
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
                        <?php if ($plan && !$isHistoryPreview): ?>
                        <div class="meal-actions no-print">
                        <form method="POST" action="<?= BASE_URL ?>/plan.php" class="meal-lock-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="toggle_lock_meal">
                            <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                            <input type="hidden" name="day_name" value="<?= htmlspecialchars($dayName) ?>">
                            <input type="hidden" name="meal_index" value="<?= (int) $mealIndex ?>">
                            <input type="hidden" name="lock_state" value="<?= $mealLocked ? 0 : 1 ?>">
                            <button type="submit" class="meal-lock-btn<?= $mealLocked ? ' is-locked' : '' ?>">
                                <i data-lucide="<?= $mealLocked ? 'unlock' : 'lock' ?>" style="width:12px;height:12px;"></i>
                                <?= $mealLocked ? __('plan_unlock_btn') : __('plan_lock_btn') ?>
                            </button>
                        </form>
                        <?php if (!$mealLocked): ?>
                        <details class="meal-replace">
                            <summary>
                                <i data-lucide="refresh-cw" style="width:12px;height:12px;"></i>
                                <?= __('plan_replace_btn') ?>
                            </summary>
                            <form method="POST" action="<?= BASE_URL ?>/plan.php" class="meal-replace-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                <input type="hidden" name="action" value="replace_meal">
                                <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                                <input type="hidden" name="day_name" value="<?= htmlspecialchars($dayName) ?>">
                                <input type="hidden" name="meal_index" value="<?= (int) $mealIndex ?>">
                                <input type="hidden" name="slot" value="<?= htmlspecialchars($mSlot) ?>">
                                <select name="replace_reason" class="meal-replace-select" aria-label="<?= htmlspecialchars(__('plan_replace_reason_label')) ?>">
                                    <option value="new"><?= __('plan_replace_new') ?></option>
                                    <option value="quick"><?= __('plan_replace_quick') ?></option>
                                    <option value="simple"><?= __('plan_replace_simple') ?></option>
                                    <option value="protein"><?= __('plan_replace_protein') ?></option>
                                    <option value="avoid_food"><?= __('plan_replace_avoid_food') ?></option>
                                </select>
                                <?php if (!empty($meal['components'])): ?>
                                <label class="meal-replace-ingredient">
                                    <span><?= __('plan_replace_food_label') ?></span>
                                    <select name="avoid_food_id" class="meal-replace-select">
                                        <?php foreach ($meal['components'] as $component): ?>
                                        <?php
                                            $componentFoodId = (int) ($component['food_id'] ?? 0);
                                            if ($componentFoodId <= 0) {
                                                continue;
                                            }
                                            $componentName = ($GLOBALS['_kcals_lang'] === 'el')
                                                ? ($component['name_el'] ?? '')
                                                : ($component['name_en'] ?? '');
                                        ?>
                                        <option value="<?= $componentFoodId ?>"><?= htmlspecialchars($componentName) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="meal-replace-checkbox">
                                    <input type="checkbox" name="save_food_exclusion" value="1">
                                    <span><?= __('plan_replace_save_exclusion') ?></span>
                                </label>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-outline btn-sm meal-replace-submit"
                                        <?php if ($mealHasIncludedFood): ?>
                                        onclick="return confirm(<?= htmlspecialchars(json_encode(__('plan_replace_include_confirm')), ENT_QUOTES) ?>)"
                                        <?php endif; ?>>
                                    <?= __('plan_replace_submit') ?>
                                </button>
                            </form>
                        </details>
                        <?php if (!empty($meal['previous_meal'])): ?>
                        <form method="POST" action="<?= BASE_URL ?>/plan.php" class="meal-undo-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="undo_replace_meal">
                            <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                            <input type="hidden" name="day_name" value="<?= htmlspecialchars($dayName) ?>">
                            <input type="hidden" name="meal_index" value="<?= (int) $mealIndex ?>">
                            <button type="submit" class="meal-undo-btn">
                                <i data-lucide="undo-2" style="width:12px;height:12px;"></i>
                                <?= __('plan_undo_btn') ?>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
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

<?php if ($viewPlanData): ?>
<!-- ======== PRINT TABLE (hidden on screen, visible only on print) ======== -->
<div class="print-only" style="padding:0;margin:0;">
    <div class="print-header">
        <strong>KCALS</strong> &mdash; <?= __('plan_h1') ?>
        <?php if ($viewPlan): ?>
        &nbsp;&bull;&nbsp; <?= __('plan_week_start') ?>: <?= $viewPlan['start_date'] ?>
        &nbsp;&bull;&nbsp; <?= __('plan_week_end') ?>: <?= $viewPlan['end_date'] ?>
        &nbsp;&bull;&nbsp; <?= __('plan_kcal_target') ?>: <?= $viewPlan['target_calories'] ?> kcal
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
        <?php foreach ($viewPlanData as $dayName => $dayMeals):
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
