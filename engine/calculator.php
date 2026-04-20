<?php
// ============================================================
// KCALS – Math Engine (Mifflin-St Jeor BMR/TDEE)
// ============================================================

/**
 * Activity level multipliers (Mifflin-St Jeor standard)
 */
const ACTIVITY_LEVELS = [
    'sedentary'   => 1.20,   // Little or no exercise
    'light'       => 1.375,  // Light exercise 1-3 days/week
    'moderate'    => 1.55,   // Moderate exercise 3-5 days/week
    'active'      => 1.725,  // Hard exercise 6-7 days/week
    'very_active' => 1.90,   // Very hard exercise / physical job
];

/**
 * Caloric deficit percentages per psychological zone
 */
const DEFICIT_ZONES = [
    'green'  => 0.25,   // Low stress, high motivation  → 25% deficit
    'yellow' => 0.15,   // Middle ground                → 15% deficit
    'red'    => 0.08,   // High stress / low motivation  → 8% deficit
];

// ------------------------------------------------------------------
// BMR (Mifflin-St Jeor)
// ------------------------------------------------------------------
function calculateBMR(float $weightKg, int $heightCm, int $ageYears, string $gender): float {
    $base = (10 * $weightKg) + (6.25 * $heightCm) - (5 * $ageYears);
    return ($gender === 'male') ? $base + 5 : $base - 161;
}

// ------------------------------------------------------------------
// TDEE (Total Daily Energy Expenditure)
// ------------------------------------------------------------------
function calculateTDEE(float $bmr, float $activityLevel): float {
    return $bmr * $activityLevel;
}

// ------------------------------------------------------------------
// Determine psychological zone from stress & motivation levels
// ------------------------------------------------------------------
function determineZone(int $stressLevel, int $motivationLevel): string {
    $score = $motivationLevel - $stressLevel; // Range: -9 to +9
    if ($score >= 3) return 'green';
    if ($score >= -2) return 'yellow';
    return 'red';
}

// ------------------------------------------------------------------
// Target daily calories with deficit applied
// ------------------------------------------------------------------
function calculateTargetCalories(float $tdee, string $zone): int {
    $deficit = DEFICIT_ZONES[$zone] ?? DEFICIT_ZONES['yellow'];
    return (int) round($tdee * (1 - $deficit));
}

// ------------------------------------------------------------------
// Age from birth date
// ------------------------------------------------------------------
function calculateAge(string $birthDate): int {
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    return (int) $today->diff($birth)->y;
}

// ------------------------------------------------------------------
// Full user stats calculation
// ------------------------------------------------------------------
function calculateUserStats(array $user, array $latestProgress): array {
    $age    = calculateAge($user['birth_date']);
    $weight = (float) $latestProgress['weight_kg'];
    $bmr    = calculateBMR($weight, (int) $user['height_cm'], $age, $user['gender']);
    $tdee   = calculateTDEE($bmr, (float) $user['activity_level']);

    $stress     = (int) ($latestProgress['stress_level']     ?? 5);
    $motivation = (int) ($latestProgress['motivation_level'] ?? 5);
    $zone       = determineZone($stress, $motivation);
    $target     = calculateTargetCalories($tdee, $zone);

    // Ideal weight estimate (BMI 22 midpoint)
    $heightM     = $user['height_cm'] / 100;
    $idealWeight = round(22 * ($heightM ** 2), 1);
    $weightDiff  = round($weight - $idealWeight, 1);

    // Estimated weeks to ideal weight (500 kcal/day ≈ 0.45 kg/week)
    $dailyDeficit   = $tdee - $target;
    $weeklyDeficit  = $dailyDeficit * 7;
    $kgPerWeek      = round($weeklyDeficit / 7700, 2); // 7700 kcal ≈ 1 kg
    $weeksToGoal    = ($weightDiff > 0 && $kgPerWeek > 0)
                        ? (int) ceil($weightDiff / $kgPerWeek)
                        : 0;

    return [
        'age'          => $age,
        'weight'       => $weight,
        'bmr'          => (int) round($bmr),
        'tdee'         => (int) round($tdee),
        'zone'         => $zone,
        'target_kcal'  => $target,
        'daily_deficit'=> (int) round($dailyDeficit),
        'kg_per_week'  => $kgPerWeek,
        'ideal_weight' => $idealWeight,
        'weight_diff'  => $weightDiff,
        'weeks_to_goal'=> $weeksToGoal,
    ];
}

// ------------------------------------------------------------------
// Macronutrient split (target calories → grams)
// Ratio: 30% protein / 40% carbs / 30% fat
// ------------------------------------------------------------------
function calculateMacros(int $targetCalories): array {
    return [
        'protein_g' => (int) round(($targetCalories * 0.30) / 4),
        'carbs_g'   => (int) round(($targetCalories * 0.40) / 4),
        'fat_g'     => (int) round(($targetCalories * 0.30) / 9),
    ];
}
