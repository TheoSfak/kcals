<?php
// ============================================================
// KCALS – User Dashboard
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/engine/calculator.php';

requireLogin();

$db   = getDB();
$user = getCurrentUser();
$userId = (int) $_SESSION['user_id'];

// ---- Handle check-in form submission ----
$checkinSuccess = false;
$checkinIsEdit  = false;
$checkinErrors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $checkinErrors[] = __('err_invalid_submit2');
    } else {
        $weightKg      = (float)  ($_POST['weight_kg']       ?? 0);
        $stressLevel   = (int)    ($_POST['stress_level']     ?? 5);
        $motivationLv  = (int)    ($_POST['motivation_level'] ?? 5);
        $energyLevel   = (int)    ($_POST['energy_level']     ?? 5);
        $sleepLevel    = (int)    ($_POST['sleep_level']      ?? 5);
        $notes         = trim(    $_POST['notes']             ?? '');

        if ($weightKg < 30 || $weightKg > 300) $checkinErrors[] = __('err_weight_checkin');

        // ---- Realistic weight change guard ----
        if (empty($checkinErrors)) {
            $prevStmt = $db->prepare('
                SELECT weight_kg, entry_date FROM user_progress
                WHERE user_id = ? AND entry_date < CURDATE()
                ORDER BY entry_date DESC LIMIT 1
            ');
            $prevStmt->execute([$userId]);
            $prevRow = $prevStmt->fetch();

            if ($prevRow) {
                $daysDiff  = (int) ((strtotime(date('Y-m-d')) - strtotime($prevRow['entry_date'])) / 86400);
                $maxChange = max(1.0, $daysDiff * 1.0); // max 1 kg per day
                $actualChange = abs($weightKg - (float) $prevRow['weight_kg']);
                if ($actualChange > $maxChange) {
                    $checkinErrors[] = sprintf(__('err_weight_unrealistic'), number_format($maxChange, 1), $prevRow['entry_date']);
                }
            }
        }

        if (empty($checkinErrors)) {
            $upsert = $db->prepare('
                INSERT INTO user_progress (user_id, weight_kg, stress_level, motivation_level, energy_level, sleep_level, notes, entry_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE
                    weight_kg        = VALUES(weight_kg),
                    stress_level     = VALUES(stress_level),
                    motivation_level = VALUES(motivation_level),
                    energy_level     = VALUES(energy_level),
                    sleep_level      = VALUES(sleep_level),
                    notes            = VALUES(notes)
            ');
            $upsert->execute([$userId, $weightKg, $stressLevel, $motivationLv, $energyLevel, $sleepLevel, $notes]);
            $checkinSuccess = true;
        }
    }
}

// ---- Load latest progress ----
$latestProgress = getLatestProgress($userId);

// ---- Check if user already checked in today (edit mode) ----
$todayCheckin = null;
$todayStmt = $db->prepare('SELECT * FROM user_progress WHERE user_id = ? AND entry_date = CURDATE()');
$todayStmt->execute([$userId]);
$todayCheckin = $todayStmt->fetch();
$checkinIsEdit = !empty($todayCheckin);

// ---- Stats ----
$stats = null;
if ($latestProgress) {
    $stats = calculateUserStats($user, $latestProgress);
}
$isPlateau = $latestProgress ? detectPlateau($userId, $db) : false;

// ---- Event Countdown check ----
$eventCountdown = null;
if (
    $stats &&
    !empty($user['goal_event_date']) &&
    !empty($user['goal_weight_kg'])
) {
    $eventCountdown = calculateEventCountdown(
        (float) $latestProgress['weight_kg'],
        (float) $user['goal_weight_kg'],
        $user['goal_event_date'],
        (float) $stats['kg_per_week']
    );
}

// ---- Last 7 progress entries (for chart) ----
$progStmt = $db->prepare('
    SELECT entry_date, weight_kg
    FROM user_progress
    WHERE user_id = ?
    ORDER BY entry_date DESC
    LIMIT 7
');
$progStmt->execute([$userId]);
$progressRows = array_reverse($progStmt->fetchAll());

// ---- Current week plan ----
$planStmt = $db->prepare('
    SELECT * FROM weekly_plans
    WHERE user_id = ? AND start_date <= CURDATE() AND end_date >= CURDATE()
    ORDER BY created_at DESC LIMIT 1
');
$planStmt->execute([$userId]);
$currentPlan = $planStmt->fetch();

// ---- Random tips ----
$tipsStmt = $db->query('SELECT * FROM health_tips ORDER BY RAND() LIMIT 3');
$tips = $tipsStmt->fetchAll();

$pageTitle = __('dash_title');
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:1100px; margin:2rem auto; padding:0 1.25rem;">

    <!-- Welcome banner -->
    <?php if (isset($_GET['welcome'])): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem;">
        <?= sprintf(__('dash_welcome_msg'), htmlspecialchars($user['full_name'])) ?>
    </div>
    <?php endif; ?>

    <?php if ($checkinSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem;">
        <?= $checkinIsEdit ? __('dash_checkin_updated') : __('dash_checkin_saved') ?>
    </div>
    <?php endif; ?>

    <?php if ($isPlateau): ?>
    <div class="alert" style="background:#fff3cd; border:1px solid #ffc107; color:#856404; margin-bottom:1.5rem;">
        <strong><?= __('dash_plateau_title') ?></strong> <?= __('dash_plateau_desc') ?>
    </div>
    <?php endif; ?>

    <?php if ($stats && $todayCheckin && ((int)($todayCheckin['sleep_level'] ?? 5)) <= 4): ?>
    <?php $sleepAdjusted = (int) round(($stats['tdee'] + $stats['target_kcal']) / 2); ?>
    <div class="alert" style="background:#f3e5ff; border:1px solid #c39bd3; color:#6c3483; margin-bottom:1.5rem;">
        <strong>😴 <?= __('dash_sleep_notice_title') ?></strong><br>
        <?= sprintf(__('dash_sleep_notice'), (int)$todayCheckin['sleep_level'], $sleepAdjusted) ?>
    </div>
    <?php endif; ?>

    <?php if ($eventCountdown): ?>
    <?php
        $evName     = htmlspecialchars($user['goal_event_name'] ?: __('event_h'));
        $evDateFmt  = date('d/m/Y', strtotime($user['goal_event_date']));
        $evDateStr  = htmlspecialchars($evDateFmt);
        $evDays     = $eventCountdown['days_left'];
        $evPast     = $evDays <= 0;
        $evColor    = $evPast ? '#7f1d1d' : ($eventCountdown['reachable'] ? '#065f46' : '#92400e');
        $evBg       = $evPast ? '#fee2e2' : ($eventCountdown['reachable'] ? '#d1fae5' : '#fff3cd');
        $evBorder   = $evPast ? '#fca5a5' : ($eventCountdown['reachable'] ? '#6ee7b7' : '#fde68a');
    ?>
    <div class="alert" style="background:<?= $evBg ?>; border:1px solid <?= $evBorder ?>; color:<?= $evColor ?>; margin-bottom:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
            <strong>🎯 <?= sprintf(__('dash_event_title'), $evName) ?></strong>
            <?php if (!$evPast): ?>
            <span style="font-size:.85rem;font-weight:700;"><?= sprintf(__('dash_event_days'), $evDays) ?></span>
            <?php endif; ?>
        </div>
        <div style="font-size:.85rem;margin-top:.35rem;">
            <?php if ($evPast): ?>
                <?= __('dash_event_past') ?>
            <?php elseif ($eventCountdown['reachable']): ?>
                <?= sprintf(__('dash_event_reachable'), $eventCountdown['required_weekly'], (float)$user['goal_weight_kg'], $evDateStr) ?>
            <?php else: ?>
                <?= sprintf(__('dash_event_warning'), $eventCountdown['required_weekly'], $eventCountdown['suggested_weight'], $evDateStr) ?>
            <?php endif; ?>
        </div>
        <div style="margin-top:.5rem;">
            <a href="<?= BASE_URL ?>/settings.php" style="font-size:.78rem;font-weight:600;color:<?= $evColor ?>;opacity:.8;">
                <?= __('event_clear') ?> / <?= __('settings_h1') ?> →
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem;">
        <div>
            <h1 style="font-size:1.5rem; margin-bottom:.2rem;">
                <?= sprintf(__('dash_hello'), htmlspecialchars($user['full_name'])) ?>
            </h1>
            <p class="text-small" style="color:var(--slate-mid);">
                <?= date('l, d F Y') ?>
                <?php if ($stats): ?>
                 &bull; <?= __('dash_zone') ?>: <span class="zone-badge <?= $stats['zone'] ?>" style="vertical-align:middle; font-size:0.72rem;"><?= strtoupper($stats['zone']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/plan.php" class="btn btn-primary btn-sm">
                <i data-lucide="calendar-plus" style="width:14px;height:14px;"></i>
                <?= $currentPlan ? __('dash_view_plan') : __('dash_gen_plan') ?>
            </a>
            <a href="<?= BASE_URL ?>/progress.php" class="btn btn-outline btn-sm">
                <i data-lucide="line-chart" style="width:14px;height:14px;"></i>
                <?= __('dash_progress') ?>
            </a>
        </div>
    </div>

    <!-- ===== STAT CARDS ===== -->
    <?php if ($stats): ?>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['target_kcal'] ?></div>
            <div class="stat-label"><?= __('stat_daily_cal') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['bmr'] ?></div>
            <div class="stat-label"><?= __('stat_bmr') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['tdee'] ?></div>
            <div class="stat-label"><?= __('stat_tdee') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value green"><?= $stats['weight'] ?> kg</div>
            <div class="stat-label"><?= __('stat_curr_weight') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['ideal_weight'] ?> kg</div>
            <div class="stat-label"><?= __('stat_ideal_weight') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['weeks_to_goal'] > 0 ? $stats['weeks_to_goal'].' wk' : '✓' ?></div>
            <div class="stat-label"><?= __('stat_est_goal') ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== MAIN GRID ===== -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">

        <!-- Macros Card -->
        <?php if ($stats): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="icon-wrap"><i data-lucide="pie-chart" style="width:16px;height:16px;"></i></div>
                    <?= __('dash_macros') ?>
                </div>
                <span class="text-small" style="color:var(--slate-mid);"><?= $stats['target_kcal'] ?> kcal</span>
            </div>
            <?php $macros = calculateMacros($stats['target_kcal']); ?>
            <div class="macro-bars">
                <div class="macro-row">
                    <span class="macro-label"><?= __('macro_protein') ?></span>
                    <div class="macro-bar-bg"><div class="macro-bar-fill protein" style="width:<?= min(100, round($macros['protein_g']/2)) ?>%;"></div></div>
                    <span class="macro-val"><?= $macros['protein_g'] ?>g</span>
                </div>
                <div class="macro-row">
                    <span class="macro-label"><?= __('macro_carbs') ?></span>
                    <div class="macro-bar-bg"><div class="macro-bar-fill carbs" style="width:<?= min(100, round($macros['carbs_g']/3)) ?>%;"></div></div>
                    <span class="macro-val"><?= $macros['carbs_g'] ?>g</span>
                </div>
                <div class="macro-row">
                    <span class="macro-label"><?= __('macro_fat') ?></span>
                    <div class="macro-bar-bg"><div class="macro-bar-fill fat" style="width:<?= min(100, round($macros['fat_g']/1.5)) ?>%;"></div></div>
                    <span class="macro-val"><?= $macros['fat_g'] ?>g</span>
                </div>
            </div>
            <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border); display:flex; justify-content:space-between; font-size:.8rem; color:var(--slate-mid);">
                <span><?= __('dash_deficit') ?> <strong style="color:var(--slate);"><?= $stats['daily_deficit'] ?> kcal</strong></span>
                <span>~<?= $stats['kg_per_week'] ?> <?= __('dash_per_week') ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Weight Chart -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="icon-wrap"><i data-lucide="trending-down" style="width:16px;height:16px;"></i></div>
                    <?= __('dash_weight_hist') ?>
                </div>
            </div>
            <?php if (count($progressRows) > 1): ?>
            <div class="chart-wrap">
                <canvas id="weightChart"></canvas>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('weightChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(array_column($progressRows, 'entry_date')) ?>,
                        datasets: [{
                            label: 'Weight (kg)',
                            data: <?= json_encode(array_map(fn($r) => (float)$r['weight_kg'], $progressRows)) ?>,
                            borderColor: '#2ECC71',
                            backgroundColor: 'rgba(46,204,113,0.1)',
                            borderWidth: 2.5,
                            pointBackgroundColor: '#27AE60',
                            pointRadius: 4,
                            tension: 0.35,
                            fill: true,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { grid: { color: '#eee' }, ticks: { font: { size: 11 } } },
                            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                        }
                    }
                });
            });
            </script>
            <?php else: ?>
            <div style="text-align:center; padding:2rem 1rem; color:var(--slate-mid);">
                <i data-lucide="bar-chart-2" style="width:32px;height:32px; opacity:.3; display:block; margin:0 auto .75rem;"></i>
                <?= __('dash_no_chart') ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== BOTTOM GRID ===== -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">

        <!-- Today's Check-in -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="icon-wrap"><i data-lucide="clipboard-edit" style="width:16px;height:16px;"></i></div>
                    <?= $checkinIsEdit ? __('dash_checkin_edit_title') : __('dash_checkin_title') ?>
                </div>
                <span class="text-small text-muted"><?= date('d/m/Y') ?></span>
            </div>
            <?php if ($checkinIsEdit && !$checkinSuccess): ?>
            <div style="padding:.5rem .75rem .25rem; font-size:.78rem; color:#856404; background:#fff8e1; border-bottom:1px solid #ffe082;">
                <i data-lucide="pencil" style="width:12px;height:12px;vertical-align:-1px;"></i>
                <?= __('dash_checkin_edit_notice') ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($checkinErrors)): ?>
                <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $checkinErrors)) ?></div>
            <?php endif; ?>
            <?php
                // Pre-fill from today's check-in if it exists, else from latest
                $formData = $todayCheckin ?: $latestProgress;
            ?>
            <form method="POST" action="<?= BASE_URL ?>/dashboard.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="action"     value="checkin">

                <div class="form-group">
                    <label for="weight_kg"><?= __('dash_weight_today') ?></label>
                    <input type="number" id="weight_kg" name="weight_kg" class="form-control"
                           value="<?= htmlspecialchars($formData['weight_kg'] ?? '') ?>"
                           step="0.1" min="30" max="300" placeholder="<?= htmlspecialchars(__('dash_weight_ph')) ?>" required>
                </div>

                <div class="form-group">
                    <label><?= __('dash_sleep') ?> <span id="sleepVal" class="range-output"><?= (int)($formData['sleep_level'] ?? 5) ?></span></label>
                    <input type="range" name="sleep_level" id="sleepRange" class="form-range"
                           min="1" max="10" value="<?= (int)($formData['sleep_level'] ?? 5) ?>"
                           oninput="document.getElementById('sleepVal').textContent=this.value">
                    <div style="display:flex;justify-content:space-between;" class="form-hint"><span><?= __('range_low') ?></span><span><?= __('range_high') ?></span></div>
                </div>

                <div class="form-group">
                    <label><?= __('dash_stress') ?> <span id="stressVal" class="range-output"><?= $formData['stress_level'] ?? 5 ?></span></label>
                    <input type="range" name="stress_level" id="stressRange" class="form-range"
                           min="1" max="10" value="<?= $formData['stress_level'] ?? 5 ?>"
                           oninput="document.getElementById('stressVal').textContent=this.value">
                    <div style="display:flex;justify-content:space-between;" class="form-hint"><span><?= __('range_low') ?></span><span><?= __('range_high') ?></span></div>
                </div>

                <div class="form-group">
                    <label><?= __('dash_motivation') ?> <span id="motivationVal" class="range-output"><?= $formData['motivation_level'] ?? 5 ?></span></label>
                    <input type="range" name="motivation_level" id="motivationRange" class="form-range"
                           min="1" max="10" value="<?= $formData['motivation_level'] ?? 5 ?>"
                           oninput="document.getElementById('motivationVal').textContent=this.value">
                    <div style="display:flex;justify-content:space-between;" class="form-hint"><span><?= __('range_low') ?></span><span><?= __('range_high') ?></span></div>
                </div>

                <div class="form-group">
                    <label><?= __('dash_energy') ?> <span id="energyVal" class="range-output"><?= $formData['energy_level'] ?? 5 ?></span></label>
                    <input type="range" name="energy_level" id="energyRange" class="form-range"
                           min="1" max="10" value="<?= $formData['energy_level'] ?? 5 ?>"
                           oninput="document.getElementById('energyVal').textContent=this.value">
                    <div style="display:flex;justify-content:space-between;" class="form-hint"><span><?= __('range_low') ?></span><span><?= __('range_high') ?></span></div>
                </div>

                <div class="form-group">
                    <label for="notes"><?= __('dash_notes') ?></label>
                    <textarea id="notes" name="notes" class="form-control" rows="2"
                              placeholder="<?= htmlspecialchars(__('dash_notes_ph')) ?>"><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i data-lucide="<?= $checkinIsEdit ? 'pencil' : 'save' ?>" style="width:15px;height:15px;"></i>
                    <?= $checkinIsEdit ? __('dash_update_checkin') : __('dash_save_checkin') ?>
                </button>
            </form>
        </div>

        <!-- Health Tips -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="icon-wrap"><i data-lucide="lightbulb" style="width:16px;height:16px;"></i></div>
                    <?= __('dash_tips_title') ?>
                </div>
                <a href="<?= BASE_URL ?>/tips.php" class="text-small" style="color:var(--green-dark);"><?= __('dash_tips_all') ?></a>
            </div>
            <?php foreach ($tips as $tip): ?>
            <div class="tip-card mb-2">
                <div class="tip-icon">
                    <i data-lucide="<?= htmlspecialchars($tip['icon']) ?>" style="width:20px;height:20px; color:var(--green-dark);"></i>
                </div>
                <div class="tip-text"><?= htmlspecialchars($tip['tip_text']) ?></div>
                <div class="text-small" style="color:var(--slate-mid); margin-top:.4rem; text-transform:capitalize;">
                    <?= htmlspecialchars($tip['category']) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($tips)): ?>
            <p class="text-small" style="color:var(--slate-mid);"><?= __('dash_no_tips') ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Current Plan Preview -->
    <?php if ($currentPlan): ?>
    <?php
        $planData = json_decode($currentPlan['plan_data_json'], true);
        $zone     = $currentPlan['zone'];
        $previewDays = array_slice($planData, 0, 3, true);
    ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="icon-wrap"><i data-lucide="calendar" style="width:16px;height:16px;"></i></div>
                <?= __('dash_plan_title') ?>
                <span class="zone-badge <?= $zone ?>" style="margin-left:.5rem;"><?= strtoupper($zone) ?></span>
            </div>
            <a href="<?= BASE_URL ?>/plan.php" class="btn btn-outline btn-sm"><?= __('dash_view_full') ?></a>
        </div>
        <div class="plan-grid">
            <?php foreach ($previewDays as $dayName => $dayMeals): ?>
            <div class="day-card">
                <div class="day-card-header">
                    <span class="day-name"><?= __('day_' . strtolower($dayName)) ?></span>
                    <span class="day-kcal">
                        ~<?= array_sum(array_column($dayMeals, 'calories')) ?> kcal
                    </span>
                </div>
                <div class="meal-list">
                    <?php foreach ($dayMeals as $meal): ?>
                    <div class="meal-item">
                        <?php
                        $mSlot = $meal['slot'] ?? $meal['category'] ?? 'lunch';
                        $mName = ($GLOBALS['_kcals_lang'] === 'el')
                            ? ($meal['name_el'] ?? $meal['title'] ?? '')
                            : ($meal['name_en'] ?? $meal['title'] ?? '');
                    ?>
                    <div class="meal-dot <?= htmlspecialchars($mSlot) ?>"></div>
                        <div class="meal-info">
                            <div class="meal-type"><?= __('meal_slot_' . $mSlot) ?></div>
                            <div class="meal-name"><?= htmlspecialchars($mName) ?></div>
                        </div>
                        <span class="meal-kcal"><?= $meal['calories'] ?> kcal</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($planData) > 3): ?>
        <div style="text-align:center; margin-top:1rem;">
            <a href="<?= BASE_URL ?>/plan.php" class="btn btn-outline btn-sm"><?= __('dash_see_all') ?></a>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card" style="text-align:center; padding:2.5rem;">
        <div style="margin-bottom:1rem;">
            <i data-lucide="calendar-x" style="width:48px;height:48px; color:var(--slate-light); display:block; margin:0 auto .75rem;"></i>
            <h3 style="margin-bottom:.5rem;"><?= __('dash_no_plan_title') ?></h3>
            <p><?= __('dash_no_plan_desc') ?></p>
        </div>
        <a href="<?= BASE_URL ?>/plan.php" class="btn btn-primary">
            <i data-lucide="wand-2" style="width:16px;height:16px;"></i>
            <?= __('dash_gen_plan_btn') ?>
        </a>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
