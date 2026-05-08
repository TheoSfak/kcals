<?php
// ============================================================
// KCALS - Local Meal Prep task generation
// Converts the latest weekly plan into practical in-app prep tasks.
// ============================================================

function kcalsMealPrepLatestPlan(PDO $db, int $userId): ?array {
    $stmt = $db->prepare('
        SELECT *
        FROM weekly_plans
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function kcalsMealPrepDayDate(string $startDate, int $dayIndex): string {
    return (new DateTimeImmutable($startDate))->modify('+' . $dayIndex . ' days')->format('Y-m-d');
}

function kcalsMealPrepMealName(array $meal, string $lang): string {
    if ($lang === 'el' && !empty($meal['name_el'])) return (string) $meal['name_el'];
    return (string) ($meal['name_en'] ?? $meal['name_el'] ?? __('meal_slot_' . ($meal['slot'] ?? 'lunch')));
}

function kcalsMealPrepComponentName(array $component, string $lang): string {
    if ($lang === 'el' && !empty($component['name_el'])) return (string) $component['name_el'];
    return (string) ($component['name_en'] ?? $component['name_el'] ?? '');
}

function kcalsMealPrepSlotLabel(string $slot, string $lang): string {
    $labels = [
        'breakfast' => ['en' => 'Breakfast', 'el' => 'Πρωινό'],
        'lunch' => ['en' => 'Lunch', 'el' => 'Μεσημεριανό'],
        'dinner' => ['en' => 'Dinner', 'el' => 'Βραδινό'],
        'snack' => ['en' => 'Snack', 'el' => 'Σνακ'],
    ];
    return $labels[$slot][$lang] ?? $slot;
}

function kcalsMealPrepMealDetails(array $meal): array {
    $components = [];
    foreach ((array) ($meal['components'] ?? []) as $component) {
        if (!is_array($component)) continue;
        $components[] = [
            'name_en' => (string) ($component['name_en'] ?? ''),
            'name_el' => (string) ($component['name_el'] ?? ''),
            'grams' => (int) ($component['grams'] ?? 0),
            'food_type' => (string) ($component['food_type'] ?? 'mixed'),
        ];
    }
    return [
        'calories' => (int) ($meal['calories'] ?? 0),
        'protein_g' => (int) ($meal['protein_g'] ?? 0),
        'carbs_g' => (int) ($meal['carbs_g'] ?? 0),
        'fat_g' => (int) ($meal['fat_g'] ?? 0),
        'components' => $components,
    ];
}

function kcalsMealPrepBuildTasks(array $planRow): array {
    $planData = json_decode((string) ($planRow['plan_data_json'] ?? ''), true);
    if (!is_array($planData)) return [];

    $tasks = [];
    $dayIndex = 0;
    foreach ($planData as $dayName => $meals) {
        if (!is_array($meals)) {
            $dayIndex++;
            continue;
        }
        $date = kcalsMealPrepDayDate((string) $planRow['start_date'], $dayIndex);
        $quickMeals = [];
        $freshComponents = [];
        $sort = $dayIndex * 10;

        foreach ($meals as $mealIndex => $meal) {
            if (!is_array($meal)) continue;
            $slot = (string) ($meal['slot'] ?? 'lunch');
            $prepMinutes = (int) ($meal['prep_minutes'] ?? 0);
            $mealNameEn = kcalsMealPrepMealName($meal, 'en');
            $mealNameEl = kcalsMealPrepMealName($meal, 'el');
            $details = kcalsMealPrepMealDetails($meal);

            foreach ($details['components'] as $component) {
                if (in_array($component['food_type'], ['vegetable', 'fruit'], true)) {
                    $freshComponents[] = $component;
                }
            }

            if (in_array($slot, ['lunch', 'dinner'], true) && $prepMinutes >= 10) {
                $taskKey = $date . '-' . $slot . '-main-cook';
                $payload = [
                    'slot' => $slot,
                    'meal_name_en' => $mealNameEn,
                    'meal_name_el' => $mealNameEl,
                    'details' => $details,
                ];
                $tasks[] = [
                    'task_key' => $taskKey,
                    'task_date' => $date,
                    'day_name' => (string) $dayName,
                    'meal_slot' => $slot,
                    'task_type' => 'main_cook',
                    'title_en' => 'Cook ' . kcalsMealPrepSlotLabel($slot, 'en') . ': ' . $mealNameEn,
                    'title_el' => 'Μαγείρεμα ' . kcalsMealPrepSlotLabel($slot, 'el') . ': ' . $mealNameEl,
                    'details' => $payload,
                    'estimated_minutes' => max(10, $prepMinutes),
                    'source_hash' => sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    'sort_order' => $sort++,
                ];
            } elseif (in_array($slot, ['breakfast', 'snack'], true)) {
                $quickMeals[] = [
                    'slot' => $slot,
                    'meal_name_en' => $mealNameEn,
                    'meal_name_el' => $mealNameEl,
                    'minutes' => max(1, $prepMinutes),
                    'details' => $details,
                ];
            }
        }

        if ($quickMeals) {
            $taskKey = $date . '-quick-pack';
            $minutes = array_sum(array_map(fn($meal) => (int) $meal['minutes'], $quickMeals));
            $payload = ['meals' => $quickMeals];
            $tasks[] = [
                'task_key' => $taskKey,
                'task_date' => $date,
                'day_name' => (string) $dayName,
                'meal_slot' => null,
                'task_type' => 'quick_pack',
                'title_en' => 'Pack quick meals and snacks',
                'title_el' => 'Ετοίμασε γρήγορα γεύματα και σνακ',
                'details' => $payload,
                'estimated_minutes' => min(15, max(3, $minutes)),
                'source_hash' => sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'sort_order' => $sort++,
            ];
        }

        if ($freshComponents) {
            $byName = [];
            foreach ($freshComponents as $component) {
                $key = ($component['name_en'] ?: $component['name_el']);
                if ($key === '') continue;
                if (!isset($byName[$key])) {
                    $byName[$key] = $component;
                } else {
                    $byName[$key]['grams'] += (int) $component['grams'];
                }
            }
            $payload = ['components' => array_values($byName)];
            if ($payload['components']) {
                $tasks[] = [
                    'task_key' => $date . '-fresh-prep',
                    'task_date' => $date,
                    'day_name' => (string) $dayName,
                    'meal_slot' => null,
                    'task_type' => 'fresh_prep',
                    'title_en' => 'Wash/chop fresh produce',
                    'title_el' => 'Πλύσιμο/κόψιμο φρέσκων υλικών',
                    'details' => $payload,
                    'estimated_minutes' => min(15, max(5, count($payload['components']) * 2)),
                    'source_hash' => sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    'sort_order' => $sort++,
                ];
            }
        }

        $dayIndex++;
    }
    return $tasks;
}

function kcalsMealPrepSyncTasks(PDO $db, int $userId, array $planRow): int {
    $tasks = kcalsMealPrepBuildTasks($planRow);
    $planId = (int) $planRow['id'];
    $seen = [];
    $stmt = $db->prepare('
        INSERT INTO meal_prep_tasks
            (user_id, weekly_plan_id, task_key, task_date, day_name, meal_slot, task_type, title_en, title_el, details_json, estimated_minutes, source_hash, status, sort_order, created_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            task_date = VALUES(task_date),
            day_name = VALUES(day_name),
            meal_slot = VALUES(meal_slot),
            task_type = VALUES(task_type),
            title_en = VALUES(title_en),
            title_el = VALUES(title_el),
            details_json = VALUES(details_json),
            estimated_minutes = VALUES(estimated_minutes),
            status = IF(source_hash = VALUES(source_hash), status, "todo"),
            source_hash = VALUES(source_hash),
            sort_order = VALUES(sort_order),
            updated_at = NOW()
    ');
    foreach ($tasks as $task) {
        $seen[] = $task['task_key'];
        $stmt->execute([
            $userId,
            $planId,
            $task['task_key'],
            $task['task_date'],
            $task['day_name'],
            $task['meal_slot'],
            $task['task_type'],
            $task['title_en'],
            $task['title_el'],
            json_encode($task['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $task['estimated_minutes'],
            $task['source_hash'],
            'todo',
            $task['sort_order'],
        ]);
    }

    if ($seen) {
        $placeholders = implode(',', array_fill(0, count($seen), '?'));
        $params = array_merge([$userId, $planId], $seen);
        $db->prepare("DELETE FROM meal_prep_tasks WHERE user_id = ? AND weekly_plan_id = ? AND task_key NOT IN ($placeholders)")
            ->execute($params);
    }

    return count($tasks);
}

function kcalsMealPrepTasksForPlan(PDO $db, int $userId, int $planId): array {
    $stmt = $db->prepare('
        SELECT *
        FROM meal_prep_tasks
        WHERE user_id = ? AND weekly_plan_id = ?
        ORDER BY task_date ASC, sort_order ASC, id ASC
    ');
    $stmt->execute([$userId, $planId]);
    return $stmt->fetchAll();
}

function kcalsMealPrepProgress(array $tasks): array {
    $total = count($tasks);
    $done = 0;
    $minutes = 0;
    foreach ($tasks as $task) {
        if (($task['status'] ?? '') === 'done') $done++;
        if (($task['status'] ?? '') !== 'done') $minutes += (int) ($task['estimated_minutes'] ?? 0);
    }
    return ['total' => $total, 'done' => $done, 'remaining_minutes' => $minutes];
}
