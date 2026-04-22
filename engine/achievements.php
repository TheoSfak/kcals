<?php
// ============================================================
// KCALS – Achievements Engine
// ============================================================

/**
 * Return the full catalogue of all achievements.
 * Each entry: slug, icon, category, tier (bronze/silver/gold/platinum), title/desc lang keys.
 */
function getAchievementCatalogue(): array
{
    return [
        // ---- FIRST STEPS ----
        ['slug' => 'first_checkin',        'icon' => '🌱', 'category' => 'streak',   'tier' => 'bronze',   'title' => 'ach_first_checkin_title',        'desc' => 'ach_first_checkin_desc'],
        ['slug' => 'profile_complete',     'icon' => '✅', 'category' => 'profile',  'tier' => 'bronze',   'title' => 'ach_profile_complete_title',     'desc' => 'ach_profile_complete_desc'],
        ['slug' => 'first_plan',           'icon' => '📋', 'category' => 'profile',  'tier' => 'bronze',   'title' => 'ach_first_plan_title',           'desc' => 'ach_first_plan_desc'],
        ['slug' => 'first_share',          'icon' => '📤', 'category' => 'profile',  'tier' => 'bronze',   'title' => 'ach_first_share_title',          'desc' => 'ach_first_share_desc'],

        // ---- CHECK-INS ----
        ['slug' => 'checkins_5',           'icon' => '📝', 'category' => 'checkins', 'tier' => 'bronze',   'title' => 'ach_checkins_5_title',           'desc' => 'ach_checkins_5_desc'],
        ['slug' => 'checkins_10',          'icon' => '📊', 'category' => 'checkins', 'tier' => 'bronze',   'title' => 'ach_checkins_10_title',          'desc' => 'ach_checkins_10_desc'],
        ['slug' => 'checkins_25',          'icon' => '📈', 'category' => 'checkins', 'tier' => 'silver',   'title' => 'ach_checkins_25_title',          'desc' => 'ach_checkins_25_desc'],
        ['slug' => 'checkins_50',          'icon' => '🎖️', 'category' => 'checkins', 'tier' => 'gold',     'title' => 'ach_checkins_50_title',          'desc' => 'ach_checkins_50_desc'],
        ['slug' => 'checkins_100',         'icon' => '👑', 'category' => 'checkins', 'tier' => 'platinum', 'title' => 'ach_checkins_100_title',         'desc' => 'ach_checkins_100_desc'],

        // ---- STREAKS ----
        ['slug' => 'streak_3',             'icon' => '🔥', 'category' => 'streak',   'tier' => 'bronze',   'title' => 'ach_streak_3_title',             'desc' => 'ach_streak_3_desc'],
        ['slug' => 'streak_7',             'icon' => '🔥', 'category' => 'streak',   'tier' => 'silver',   'title' => 'ach_streak_7_title',             'desc' => 'ach_streak_7_desc'],
        ['slug' => 'streak_14',            'icon' => '⚡', 'category' => 'streak',   'tier' => 'silver',   'title' => 'ach_streak_14_title',            'desc' => 'ach_streak_14_desc'],
        ['slug' => 'streak_30',            'icon' => '🏆', 'category' => 'streak',   'tier' => 'gold',     'title' => 'ach_streak_30_title',            'desc' => 'ach_streak_30_desc'],
        ['slug' => 'streak_60',            'icon' => '💪', 'category' => 'streak',   'tier' => 'gold',     'title' => 'ach_streak_60_title',            'desc' => 'ach_streak_60_desc'],
        ['slug' => 'streak_100',           'icon' => '🌟', 'category' => 'streak',   'tier' => 'platinum', 'title' => 'ach_streak_100_title',           'desc' => 'ach_streak_100_desc'],

        // ---- WEIGHT LOSS ----
        ['slug' => 'weight_first_drop',    'icon' => '🎯', 'category' => 'weight',   'tier' => 'bronze',   'title' => 'ach_weight_first_drop_title',    'desc' => 'ach_weight_first_drop_desc'],
        ['slug' => 'weight_3kg',           'icon' => '⚖️', 'category' => 'weight',   'tier' => 'bronze',   'title' => 'ach_weight_3kg_title',           'desc' => 'ach_weight_3kg_desc'],
        ['slug' => 'weight_5kg',           'icon' => '🏅', 'category' => 'weight',   'tier' => 'silver',   'title' => 'ach_weight_5kg_title',           'desc' => 'ach_weight_5kg_desc'],
        ['slug' => 'weight_10kg',          'icon' => '🥈', 'category' => 'weight',   'tier' => 'gold',     'title' => 'ach_weight_10kg_title',          'desc' => 'ach_weight_10kg_desc'],
        ['slug' => 'weight_15kg',          'icon' => '🥇', 'category' => 'weight',   'tier' => 'gold',     'title' => 'ach_weight_15kg_title',          'desc' => 'ach_weight_15kg_desc'],
        ['slug' => 'weight_20kg',          'icon' => '💎', 'category' => 'weight',   'tier' => 'platinum', 'title' => 'ach_weight_20kg_title',          'desc' => 'ach_weight_20kg_desc'],

        // ---- ZONE / MENTAL ----
        ['slug' => 'first_green',          'icon' => '😊', 'category' => 'zone',     'tier' => 'bronze',   'title' => 'ach_first_green_title',          'desc' => 'ach_first_green_desc'],
        ['slug' => 'green_5',              'icon' => '🟢', 'category' => 'zone',     'tier' => 'silver',   'title' => 'ach_green_5_title',              'desc' => 'ach_green_5_desc'],
        ['slug' => 'green_10',             'icon' => '🧘', 'category' => 'zone',     'tier' => 'gold',     'title' => 'ach_green_10_title',             'desc' => 'ach_green_10_desc'],
        ['slug' => 'bounce_back',          'icon' => '💫', 'category' => 'zone',     'tier' => 'silver',   'title' => 'ach_bounce_back_title',          'desc' => 'ach_bounce_back_desc'],
        ['slug' => 'high_motivation',      'icon' => '💡', 'category' => 'zone',     'tier' => 'bronze',   'title' => 'ach_high_motivation_title',      'desc' => 'ach_high_motivation_desc'],
        ['slug' => 'zen_master',           'icon' => '☯️', 'category' => 'zone',     'tier' => 'platinum', 'title' => 'ach_zen_master_title',           'desc' => 'ach_zen_master_desc'],

        // ---- WORKOUT ----
        ['slug' => 'workout_first',        'icon' => '🏋️', 'category' => 'workout',  'tier' => 'bronze',   'title' => 'ach_workout_first_title',        'desc' => 'ach_workout_first_desc'],
        ['slug' => 'workout_10',           'icon' => '🚴', 'category' => 'workout',  'tier' => 'bronze',   'title' => 'ach_workout_10_title',           'desc' => 'ach_workout_10_desc'],
        ['slug' => 'workout_30',           'icon' => '🏃', 'category' => 'workout',  'tier' => 'silver',   'title' => 'ach_workout_30_title',           'desc' => 'ach_workout_30_desc'],
        ['slug' => 'workout_marathon',     'icon' => '🎽', 'category' => 'workout',  'tier' => 'gold',     'title' => 'ach_workout_marathon_title',     'desc' => 'ach_workout_marathon_desc'],
        ['slug' => 'workout_60min',        'icon' => '⏱️', 'category' => 'workout',  'tier' => 'silver',   'title' => 'ach_workout_60min_title',        'desc' => 'ach_workout_60min_desc'],

        // ---- SLEEP ----
        ['slug' => 'sleep_7days',          'icon' => '😴', 'category' => 'sleep',    'tier' => 'silver',   'title' => 'ach_sleep_7days_title',          'desc' => 'ach_sleep_7days_desc'],
        ['slug' => 'sleep_king',           'icon' => '🌙', 'category' => 'sleep',    'tier' => 'gold',     'title' => 'ach_sleep_king_title',           'desc' => 'ach_sleep_king_desc'],

        // ---- SPECIAL ----
        ['slug' => 'night_owl',            'icon' => '🦉', 'category' => 'special',  'tier' => 'bronze',   'title' => 'ach_night_owl_title',            'desc' => 'ach_night_owl_desc'],
        ['slug' => 'early_bird',           'icon' => '🐦', 'category' => 'special',  'tier' => 'bronze',   'title' => 'ach_early_bird_title',           'desc' => 'ach_early_bird_desc'],
        ['slug' => 'comeback_kid',         'icon' => '🔄', 'category' => 'special',  'tier' => 'silver',   'title' => 'ach_comeback_kid_title',         'desc' => 'ach_comeback_kid_desc'],
        ['slug' => 'perfect_week',         'icon' => '🌈', 'category' => 'special',  'tier' => 'gold',     'title' => 'ach_perfect_week_title',         'desc' => 'ach_perfect_week_desc'],
        ['slug' => 'social_butterfly',     'icon' => '🦋', 'category' => 'special',  'tier' => 'silver',   'title' => 'ach_social_butterfly_title',     'desc' => 'ach_social_butterfly_desc'],
    ];
}

/**
 * Get slugs already earned by a user.
 */
function getEarnedSlugs(int $userId, PDO $db): array
{
    $stmt = $db->prepare('SELECT achievement_slug, earned_at FROM user_achievements WHERE user_id = ?');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $earned = [];
    foreach ($rows as $row) {
        $earned[$row['achievement_slug']] = $row['earned_at'];
    }
    return $earned;
}

/**
 * Award an achievement if not already earned.
 * Returns true if newly awarded.
 */
function awardAchievement(int $userId, string $slug, PDO $db): bool
{
    try {
        $stmt = $db->prepare('INSERT IGNORE INTO user_achievements (user_id, achievement_slug) VALUES (?, ?)');
        $stmt->execute([$userId, $slug]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('awardAchievement error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check all achievement conditions for a user and award any newly unlocked ones.
 * Returns array of newly awarded achievement slugs.
 */
function checkAndAwardAchievements(int $userId, PDO $db): array
{
    $earned  = getEarnedSlugs($userId, $db);
    $newlyEarned = [];

    // ---- Helper: award if not already earned ----
    $award = function(string $slug) use ($userId, $db, &$earned, &$newlyEarned): void {
        if (!isset($earned[$slug]) && awardAchievement($userId, $slug, $db)) {
            $earned[$slug] = date('Y-m-d H:i:s');
            $newlyEarned[] = $slug;
        }
    };

    // ---- Fetch all progress rows ----
    $stmt = $db->prepare('
        SELECT weight_kg, stress_level, motivation_level, sleep_level,
               workout_type, workout_minutes, entry_date
        FROM user_progress
        WHERE user_id = ?
        ORDER BY entry_date ASC
    ');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $totalCheckins = count($rows);

    if ($totalCheckins === 0) return [];

    // ---- first_checkin ----
    $award('first_checkin');

    // ---- Check-in counts ----
    if ($totalCheckins >= 5)   $award('checkins_5');
    if ($totalCheckins >= 10)  $award('checkins_10');
    if ($totalCheckins >= 25)  $award('checkins_25');
    if ($totalCheckins >= 50)  $award('checkins_50');
    if ($totalCheckins >= 100) $award('checkins_100');

    // ---- Streak (consecutive days ending today or yesterday) ----
    $streak = 0;
    $dates  = array_column($rows, 'entry_date');
    $dateSet = array_flip($dates);
    $today  = date('Y-m-d');
    $check  = isset($dateSet[$today]) ? $today : date('Y-m-d', strtotime('-1 day'));
    while (isset($dateSet[$check])) {
        $streak++;
        $check = date('Y-m-d', strtotime($check . ' -1 day'));
    }
    if ($streak >= 3)   $award('streak_3');
    if ($streak >= 7)   $award('streak_7');
    if ($streak >= 14)  $award('streak_14');
    if ($streak >= 30)  $award('streak_30');
    if ($streak >= 60)  $award('streak_60');
    if ($streak >= 100) $award('streak_100');

    // ---- Weight loss ----
    $firstWeight = (float) $rows[0]['weight_kg'];
    $lastWeight  = (float) end($rows)['weight_kg'];
    $dropped     = $firstWeight - $lastWeight;
    if ($dropped >= 0.5)  $award('weight_first_drop');
    if ($dropped >= 3.0)  $award('weight_3kg');
    if ($dropped >= 5.0)  $award('weight_5kg');
    if ($dropped >= 10.0) $award('weight_10kg');
    if ($dropped >= 15.0) $award('weight_15kg');
    if ($dropped >= 20.0) $award('weight_20kg');

    // ---- Zone / mental health ----
    require_once __DIR__ . '/calculator.php';
    $greenCount    = 0;
    $consecutiveGreen = 0;
    $maxConsecGreen   = 0;
    $hadRed        = false;
    $bouncedBack   = false;
    $prevZone      = null;

    foreach ($rows as $row) {
        $zone = determineZone((int)$row['stress_level'], (int)$row['motivation_level']);
        if ($zone === 'green') {
            $greenCount++;
            $consecutiveGreen++;
            if ($hadRed) $bouncedBack = true;
        } else {
            if ($consecutiveGreen > $maxConsecGreen) $maxConsecGreen = $consecutiveGreen;
            $consecutiveGreen = 0;
        }
        if ($zone === 'red') $hadRed = true;
        if ((int)$row['motivation_level'] >= 9) $award('high_motivation');
        $prevZone = $zone;
    }
    if ($consecutiveGreen > $maxConsecGreen) $maxConsecGreen = $consecutiveGreen;

    if ($greenCount >= 1)         $award('first_green');
    if ($maxConsecGreen >= 5)     $award('green_5');
    if ($maxConsecGreen >= 10)    $award('green_10');
    if ($maxConsecGreen >= 20)    $award('zen_master');
    if ($bouncedBack)             $award('bounce_back');

    // ---- Workouts ----
    $workoutSessions = array_filter($rows, fn($r) => !empty($r['workout_type']) && (int)$r['workout_minutes'] > 0);
    $workoutCount = count($workoutSessions);
    if ($workoutCount >= 1)  $award('workout_first');
    if ($workoutCount >= 10) $award('workout_10');
    if ($workoutCount >= 30) $award('workout_30');
    if ($workoutCount >= 60) $award('workout_marathon');
    foreach ($workoutSessions as $w) {
        if ((int)$w['workout_minutes'] >= 60) { $award('workout_60min'); break; }
    }

    // ---- Sleep (7 days in a row with sleep_level >= 7) ----
    $sleepStreak = 0;
    $maxSleepStreak = 0;
    foreach ($rows as $row) {
        if ((int)($row['sleep_level'] ?? 0) >= 7) {
            $sleepStreak++;
            if ($sleepStreak >= 7)  $award('sleep_7days');
            if ($sleepStreak >= 14) $award('sleep_king');
        } else {
            $sleepStreak = 0;
        }
    }

    // ---- Special ----
    // Night owl: check-in logged after 22:00 (created_at not stored in rows, use hour of earned_at)
    $lastRow = end($rows);
    $hour = (int) date('H');
    if ($hour >= 22 || $hour < 2) $award('night_owl');
    if ($hour >= 5 && $hour < 8)  $award('early_bird');

    // Comeback kid: had a gap of 7+ days then came back
    for ($i = 1; $i < count($rows); $i++) {
        $gap = (strtotime($rows[$i]['entry_date']) - strtotime($rows[$i-1]['entry_date'])) / 86400;
        if ($gap >= 7) { $award('comeback_kid'); break; }
    }

    // Perfect week: 7 check-ins in last 7 days
    $last7 = 0;
    for ($d = 0; $d < 7; $d++) {
        $day = date('Y-m-d', strtotime("-$d days"));
        if (isset($dateSet[$day])) $last7++;
    }
    if ($last7 === 7) $award('perfect_week');

    // ---- Weekly plans ----
    $planStmt = $db->prepare('SELECT COUNT(*) FROM weekly_plans WHERE user_id = ?');
    $planStmt->execute([$userId]);
    if ($planStmt->fetchColumn() >= 1) $award('first_plan');

    return $newlyEarned;
}

/**
 * Get catalogue entry by slug.
 */
function getAchievementBySlug(string $slug): ?array
{
    foreach (getAchievementCatalogue() as $a) {
        if ($a['slug'] === $slug) return $a;
    }
    return null;
}

/**
 * Count earned achievements for a user (for display on dashboard/nav).
 */
function countEarnedAchievements(int $userId, PDO $db): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM user_achievements WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}
