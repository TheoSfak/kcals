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

    // Adaptive TDEE Recalibration: use override if set
    if (!empty($user['tdee_override'])) {
        $tdee = (float) $user['tdee_override'];
    }

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

// ------------------------------------------------------------------
// Hormetic Recharge Day (v0.9.5)
// Once per week (user-configurable day, default Wednesday = 3):
// add +150 kcal above that day's target to prevent metabolic
// adaptation by temporarily spiking leptin levels.
// ------------------------------------------------------------------
const RECHARGE_EXTRA_KCAL = 150;

/**
 * Map a 1–7 recharge_day integer to a lowercase day name.
 * 1 = Monday … 7 = Sunday
 */
function rechargeDayName(int $rechargeDay): string
{
    $map = [1=>'monday',2=>'tuesday',3=>'wednesday',4=>'thursday',5=>'friday',6=>'saturday',7=>'sunday'];
    return $map[max(1, min(7, $rechargeDay))];
}

/**
 * Returns true when $dayName matches the user's configured recharge day.
 *
 * @param string $dayName    Full English day name, e.g. 'Wednesday'
 * @param int    $rechargeDay 1 (Monday) … 7 (Sunday)
 */
function isRechargeDay(string $dayName, int $rechargeDay): bool
{
    return strtolower($dayName) === rechargeDayName($rechargeDay);
}

/**
 * Apply the Hormetic Recharge surplus to the base daily target.
 *
 * @param int    $baseTarget  Base daily kcal (after all other adjustments)
 * @param string $dayName     Full English day name
 * @param int    $rechargeDay 1 (Monday) … 7 (Sunday)
 */
function applyRechargeDay(int $baseTarget, string $dayName, int $rechargeDay): int
{
    return isRechargeDay($dayName, $rechargeDay)
        ? $baseTarget + RECHARGE_EXTRA_KCAL
        : $baseTarget;
}

// ------------------------------------------------------------------
// Social Buffer: shave 150 kcal Mon–Fri, bank all savings on Saturday
// Mon–Fri: -150 kcal/day  (5 × 150 = 750 saved)
// Saturday: +750 kcal     (social occasions without breaking balance)
// Sunday:   unchanged     (rest / recovery day)
// ------------------------------------------------------------------
const SOCIAL_BUFFER_KCAL = 150;

/**
 * Apply the Social Buffer shift to a base daily calorie target.
 *
 * @param int    $baseTarget  Base daily kcal (post zone-deficit)
 * @param string $dayName     Full English day name, e.g. 'Monday'
 */
function applySocialBuffer(int $baseTarget, string $dayName): int
{
    return match (strtolower($dayName)) {
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday'
            => $baseTarget - SOCIAL_BUFFER_KCAL,
        'saturday'
            => $baseTarget + (SOCIAL_BUFFER_KCAL * 5),
        default
            => $baseTarget, // Sunday: unchanged
    };
}

// ------------------------------------------------------------------
// Plateau Breaker: detect weight stagnation ≥14 days (<0.2 kg delta)
// ------------------------------------------------------------------
/**
 * Returns true when the user's weight has barely changed over the past
 * 14+ days (max–min delta < 0.2 kg across ≥3 entries spanning ≥14 days).
 *
 * @param int $userId
 * @param PDO $db
 */
function detectPlateau(int $userId, PDO $db): bool
{
    $stmt = $db->prepare('
        SELECT weight_kg, entry_date
        FROM user_progress
        WHERE user_id = ? AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
        ORDER BY entry_date ASC
    ');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    if (count($rows) < 3) {
        return false;
    }

    $oldest  = new DateTime($rows[0]['entry_date']);
    $newest  = new DateTime($rows[count($rows) - 1]['entry_date']);
    $daySpan = (int) $oldest->diff($newest)->days;

    if ($daySpan < 14) {
        return false;
    }

    $weights = array_column($rows, 'weight_kg');
    $delta   = (float) max($weights) - (float) min($weights);

    return $delta < 0.2;
}

// ------------------------------------------------------------------
// Recovery Mode (v0.9.6)
// Enters when stress_level ≥ 8 for 2 consecutive check-ins.
// Exits  when stress_level drops below 6.
// ------------------------------------------------------------------
const RECOVERY_STRESS_ENTER = 8;  // threshold to enter
const RECOVERY_STRESS_EXIT  = 6;  // threshold to auto-exit
const RECOVERY_DEFICIT      = 0.05; // 5% deficit in recovery mode

/**
 * Evaluate whether recovery mode should be entered, maintained, or exited.
 * Persists the updated flag to the users table.
 *
 * Returns:
 *   'entered'   — newly activated this call
 *   'active'    — already active, still triggered
 *   'exited'    — was active, now deactivated
 *   'inactive'  — not active and no trigger
 *
 * @param int   $userId
 * @param array $user    Row from users table (must include recovery_mode)
 * @param PDO   $db
 */
function evaluateRecoveryMode(int $userId, array $user, PDO $db): string
{
    $currentlyActive = (bool) ($user['recovery_mode'] ?? false);

    // Fetch the two most recent distinct-day check-ins
    $stmt = $db->prepare('
        SELECT stress_level FROM user_progress
        WHERE user_id = ?
        ORDER BY entry_date DESC
        LIMIT 2
    ');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    if (count($rows) < 1) {
        return 'inactive';
    }

    $latestStress = (int) $rows[0]['stress_level'];

    // Auto-exit: latest stress dropped below exit threshold
    if ($currentlyActive && $latestStress < RECOVERY_STRESS_EXIT) {
        $db->prepare('UPDATE `users` SET `recovery_mode` = 0 WHERE `id` = ?')->execute([$userId]);
        return 'exited';
    }

    // Auto-exit when only one check-in available and already active but not triggered
    if ($currentlyActive && count($rows) < 2) {
        // Keep active, not enough data to exit
        return 'active';
    }

    // Enter / stay: both recent check-ins have stress ≥ threshold
    if (count($rows) >= 2) {
        $prevStress = (int) $rows[1]['stress_level'];
        if ($latestStress >= RECOVERY_STRESS_ENTER && $prevStress >= RECOVERY_STRESS_ENTER) {
            if (!$currentlyActive) {
                $db->prepare('UPDATE `users` SET `recovery_mode` = 1 WHERE `id` = ?')->execute([$userId]);
                return 'entered';
            }
            return 'active';
        }
    }

    return $currentlyActive ? 'active' : 'inactive';
}

/**
 * Calculate target calories for Recovery Mode (5% deficit of TDEE).
 */
function calculateRecoveryCalories(float $tdee): int
{
    return (int) round($tdee * (1 - RECOVERY_DEFICIT));
}

// ------------------------------------------------------------------
// Event Countdown: reachability check for a user's goal event
// ------------------------------------------------------------------
/**
 * Given the user's current weight, goal weight, goal date and
 * current kg_per_week rate, returns an array with:
 *   - days_left        int
 *   - weeks_left       float
 *   - kg_to_lose       float  (positive = needs to lose)
 *   - reachable        bool   (true if achievable at ≤0.7 kg/week)
 *   - required_weekly  float  kg/week needed to reach goal in time
 *   - suggested_weight float  max safe weight achievable by event date
 *
// ------------------------------------------------------------------
// Adaptive TDEE Recalibration
// ------------------------------------------------------------------
/**
 * Every 28+ days, compare predicted vs actual weight loss.
 * If ratio falls outside 80–120%, compute true TDEE from observed
 * deficit and store as tdee_override on the users row.
 *
 * Returns an info array on recalibration, null otherwise.
 *
 * @param int   $userId
 * @param array $user    Row from users table (must include tdee_recalibrated_at)
 * @param array $stats   Output of calculateUserStats() — used for daily_deficit & target_kcal & tdee
 * @param PDO   $db
 * @return array|null  ['old_tdee', 'new_tdee', 'delta', 'direction'] or null
 */
function recalibrateTDEE(int $userId, array $user, array $stats, PDO $db): ?array
{
    // Guard: don't recalibrate more than once per 28 days
    if (!empty($user['tdee_recalibrated_at'])) {
        $lastCal   = new DateTime($user['tdee_recalibrated_at']);
        $daysSince = (int) (new DateTime())->diff($lastCal)->days;
        if ($daysSince < 28) {
            return null;
        }
    }

    // Need a weight entry ≥28 days ago
    $oldStmt = $db->prepare('
        SELECT weight_kg, entry_date FROM user_progress
        WHERE user_id = ? AND entry_date <= DATE_SUB(CURDATE(), INTERVAL 28 DAY)
        ORDER BY entry_date DESC LIMIT 1
    ');
    $oldStmt->execute([$userId]);
    $oldRow = $oldStmt->fetch();
    if (!$oldRow) {
        return null;
    }

    // Need current (latest) weight
    $newStmt = $db->prepare('
        SELECT weight_kg, entry_date FROM user_progress
        WHERE user_id = ? ORDER BY entry_date DESC LIMIT 1
    ');
    $newStmt->execute([$userId]);
    $newRow = $newStmt->fetch();
    if (!$newRow) {
        return null;
    }

    $days = (int) ((strtotime($newRow['entry_date']) - strtotime($oldRow['entry_date'])) / 86400);
    if ($days < 28) {
        return null;
    }

    $actualKgLost    = (float) $oldRow['weight_kg'] - (float) $newRow['weight_kg'];
    $predictedKgLost = ($stats['daily_deficit'] * $days) / 7700;

    if ($predictedKgLost <= 0) {
        return null; // Can't compare without a meaningful deficit
    }

    $ratio = $actualKgLost / $predictedKgLost;

    // Only recalibrate if actual loss is outside 80–120% of predicted
    if ($ratio >= 0.80 && $ratio <= 1.20) {
        return null;
    }

    // True TDEE = target + actual_daily_deficit
    $actualDailyDeficit = ($actualKgLost * 7700) / $days;
    $newTdee = (int) round($stats['target_kcal'] + $actualDailyDeficit);
    $oldTdee = $stats['tdee'];
    $delta   = $newTdee - $oldTdee;

    // Cap correction at ±500 kcal per cycle to avoid extreme swings
    $delta   = max(-500, min(500, $delta));
    $newTdee = $oldTdee + $delta;

    // Persist
    $upd = $db->prepare('
        UPDATE users
        SET tdee_override = ?, tdee_recalibrated_at = NOW(), tdee_recalibration_delta = ?
        WHERE id = ?
    ');
    $upd->execute([$newTdee, $delta, $userId]);

    return [
        'old_tdee'  => $oldTdee,
        'new_tdee'  => $newTdee,
        'delta'     => $delta,
        'direction' => $delta < 0 ? 'down' : 'up',
    ];
}

// ------------------------------------------------------------------
// Event Countdown: reachability check for a user's goal event
// ------------------------------------------------------------------
/**
 * @param float  $currentWeight
 * @param float  $goalWeight
 * @param string $goalDate       Y-m-d
 * @param float  $kgPerWeek      Current plan weekly loss rate
 */
function calculateEventCountdown(
    float  $currentWeight,
    float  $goalWeight,
    string $goalDate,
    float  $kgPerWeek
): array {
    $today    = new DateTime(date('Y-m-d'));
    $event    = new DateTime($goalDate);
    $daysLeft = (int) $today->diff($event)->days;

    // If event is in the past treat as 0
    if ($event <= $today) {
        $daysLeft = 0;
    }

    $weeksLeft      = $daysLeft / 7.0;
    $kgToLose       = round($currentWeight - $goalWeight, 2);

    $requiredWeekly = $weeksLeft > 0 ? round($kgToLose / $weeksLeft, 2) : PHP_INT_MAX;

    $maxSafe        = 0.7; // kg/week
    $reachable      = $requiredWeekly <= $maxSafe;

    // Max weight achievable safely by event date
    $suggestedWeight = round($currentWeight - ($maxSafe * $weeksLeft), 1);

    return [
        'days_left'        => $daysLeft,
        'weeks_left'       => round($weeksLeft, 1),
        'kg_to_lose'       => $kgToLose,
        'reachable'        => $reachable,
        'required_weekly'  => $requiredWeekly,
        'suggested_weight' => $suggestedWeight,
    ];
}
