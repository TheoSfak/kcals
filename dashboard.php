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
$checkinErrors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $checkinErrors[] = 'Invalid submission.';
    } else {
        $weightKg      = (float)  ($_POST['weight_kg']       ?? 0);
        $stressLevel   = (int)    ($_POST['stress_level']     ?? 5);
        $motivationLv  = (int)    ($_POST['motivation_level'] ?? 5);
        $energyLevel   = (int)    ($_POST['energy_level']     ?? 5);
        $notes         = trim(    $_POST['notes']             ?? '');

        if ($weightKg < 30 || $weightKg > 300) $checkinErrors[] = 'Weight must be between 30 and 300 kg.';

        if (empty($checkinErrors)) {
            $upsert = $db->prepare('
                INSERT INTO user_progress (user_id, weight_kg, stress_level, motivation_level, energy_level, notes, entry_date)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE
                    weight_kg        = VALUES(weight_kg),
                    stress_level     = VALUES(stress_level),
                    motivation_level = VALUES(motivation_level),
                    energy_level     = VALUES(energy_level),
                    notes            = VALUES(notes)
            ');
            $upsert->execute([$userId, $weightKg, $stressLevel, $motivationLv, $energyLevel, $notes]);
            $checkinSuccess = true;
        }
    }
}

// ---- Load latest progress ----
$latestProgress = getLatestProgress($userId);

// ---- Stats ----
$stats = null;
if ($latestProgress) {
    $stats = calculateUserStats($user, $latestProgress);
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

$pageTitle = 'Dashboard – KCALS';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:1100px; margin:2rem auto; padding:0 1.25rem;">

    <!-- Welcome banner -->
    <?php if (isset($_GET['welcome'])): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem;">
        <strong>Welcome to KCALS, <?= htmlspecialchars($user['full_name']) ?>!</strong>
        Your account is ready. Generate your first weekly plan below.
    </div>
    <?php endif; ?>

    <?php if ($checkinSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem;">
        Check-in saved! Your stats have been updated.
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem;">
        <div>
            <h1 style="font-size:1.5rem; margin-bottom:.2rem;">
                Hello, <?= htmlspecialchars($user['full_name']) ?> 👋
            </h1>
            <p class="text-small" style="color:var(--slate-mid);">
                <?= date('l, d F Y') ?>
                <?php if ($stats): ?>
                 &bull; Zone: <span class="zone-badge <?= $stats['zone'] ?>" style="vertical-align:middle; font-size:0.72rem;"><?= strtoupper($stats['zone']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
            <a href="/plan.php" class="btn btn-primary btn-sm">
                <i data-lucide="calendar-plus" style="width:14px;height:14px;"></i>
                <?= $currentPlan ? 'View My Plan' : 'Generate Plan' ?>
            </a>
            <a href="/progress.php" class="btn btn-outline btn-sm">
                <i data-lucide="line-chart" style="width:14px;height:14px;"></i>
                Progress
            </a>
        </div>
    </div>

    <!-- ===== STAT CARDS ===== -->
    <?php if ($stats): ?>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['target_kcal'] ?></div>
            <div class="stat-label">Daily Calories</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['bmr'] ?></div>
            <div class="stat-label">BMR (kcal)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['tdee'] ?></div>
            <div class="stat-label">TDEE (kcal)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value green"><?= $stats['weight'] ?> kg</div>
            <div class="stat-label">Current Weight</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['ideal_weight'] ?> kg</div>
            <div class="stat-label">Ideal Weight</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['weeks_to_goal'] > 0 ? $stats['weeks_to_goal'].' wk' : '✓' ?></div>
            <div class="stat-label">Est. to Goal</div>
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
                    Daily Macros
                </div>
                <span class="text-small" style="color:var(--slate-mid);"><?= $stats['target_kcal'] ?> kcal</span>
            </div>
            <?php $macros = calculateMacros($stats['target_kcal']); ?>
            <div class="macro-bars">
                <div class="macro-row">
                    <span class="macro-label">Protein</span>
                    <div class="macro-bar-bg"><div class="macro-bar-fill protein" style="width:<?= min(100, round($macros['protein_g']/2)) ?>%;"></div></div>
                    <span class="macro-val"><?= $macros['protein_g'] ?>g</span>
                </div>
                <div class="macro-row">
                    <span class="macro-label">Carbs</span>
                    <div class="macro-bar-bg"><div class="macro-bar-fill carbs" style="width:<?= min(100, round($macros['carbs_g']/3)) ?>%;"></div></div>
                    <span class="macro-val"><?= $macros['carbs_g'] ?>g</span>
                </div>
                <div class="macro-row">
                    <span class="macro-label">Fat</span>
                    <div class="macro-bar-bg"><div class="macro-bar-fill fat" style="width:<?= min(100, round($macros['fat_g']/1.5)) ?>%;"></div></div>
                    <span class="macro-val"><?= $macros['fat_g'] ?>g</span>
                </div>
            </div>
            <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border); display:flex; justify-content:space-between; font-size:.8rem; color:var(--slate-mid);">
                <span>Daily deficit: <strong style="color:var(--slate);"><?= $stats['daily_deficit'] ?> kcal</strong></span>
                <span>~<?= $stats['kg_per_week'] ?> kg/week</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Weight Chart -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="icon-wrap"><i data-lucide="trending-down" style="width:16px;height:16px;"></i></div>
                    Weight History
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
                Log a few check-ins to see your weight trend here.
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
                    Today's Check-in
                </div>
                <span class="text-small text-muted"><?= date('d/m/Y') ?></span>
            </div>
            <?php if (!empty($checkinErrors)): ?>
                <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $checkinErrors)) ?></div>
            <?php endif; ?>
            <form method="POST" action="/dashboard.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="action"     value="checkin">

                <div class="form-group">
                    <label for="weight_kg">Weight today (kg)</label>
                    <input type="number" id="weight_kg" name="weight_kg" class="form-control"
                           value="<?= htmlspecialchars($latestProgress['weight_kg'] ?? '') ?>"
                           step="0.1" min="30" max="300" placeholder="e.g. 70.5" required>
                </div>

                <div class="form-group">
                    <label>Stress Level: <span id="stressVal" class="range-output"><?= $latestProgress['stress_level'] ?? 5 ?></span></label>
                    <input type="range" name="stress_level" id="stressRange" class="form-range"
                           min="1" max="10" value="<?= $latestProgress['stress_level'] ?? 5 ?>"
                           oninput="document.getElementById('stressVal').textContent=this.value">
                    <div style="display:flex;justify-content:space-between;" class="form-hint"><span>1 (very low)</span><span>10 (very high)</span></div>
                </div>

                <div class="form-group">
                    <label>Motivation Level: <span id="motivationVal" class="range-output"><?= $latestProgress['motivation_level'] ?? 5 ?></span></label>
                    <input type="range" name="motivation_level" id="motivationRange" class="form-range"
                           min="1" max="10" value="<?= $latestProgress['motivation_level'] ?? 5 ?>"
                           oninput="document.getElementById('motivationVal').textContent=this.value">
                    <div style="display:flex;justify-content:space-between;" class="form-hint"><span>1 (very low)</span><span>10 (very high)</span></div>
                </div>

                <div class="form-group">
                    <label>Energy Level: <span id="energyVal" class="range-output"><?= $latestProgress['energy_level'] ?? 5 ?></span></label>
                    <input type="range" name="energy_level" id="energyRange" class="form-range"
                           min="1" max="10" value="<?= $latestProgress['energy_level'] ?? 5 ?>"
                           oninput="document.getElementById('energyVal').textContent=this.value">
                    <div style="display:flex;justify-content:space-between;" class="form-hint"><span>1 (very low)</span><span>10 (very high)</span></div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes (optional)</label>
                    <textarea id="notes" name="notes" class="form-control" rows="2"
                              placeholder="How are you feeling today?"><?= htmlspecialchars($latestProgress['notes'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i data-lucide="save" style="width:15px;height:15px;"></i>
                    Save Check-in
                </button>
            </form>
        </div>

        <!-- Health Tips -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="icon-wrap"><i data-lucide="lightbulb" style="width:16px;height:16px;"></i></div>
                    Weekly Wellness Tips
                </div>
                <a href="/tips.php" class="text-small" style="color:var(--green-dark);">View all →</a>
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
            <p class="text-small" style="color:var(--slate-mid);">No tips available yet.</p>
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
                Current Week Plan
                <span class="zone-badge <?= $zone ?>" style="margin-left:.5rem;"><?= strtoupper($zone) ?></span>
            </div>
            <a href="/plan.php" class="btn btn-outline btn-sm">View Full Plan</a>
        </div>
        <div class="plan-grid">
            <?php foreach ($previewDays as $dayName => $dayMeals): ?>
            <div class="day-card">
                <div class="day-card-header">
                    <span class="day-name"><?= htmlspecialchars($dayName) ?></span>
                    <span class="day-kcal">
                        ~<?= array_sum(array_column($dayMeals, 'calories')) ?> kcal
                    </span>
                </div>
                <div class="meal-list">
                    <?php foreach ($dayMeals as $meal): ?>
                    <div class="meal-item">
                        <div class="meal-dot <?= htmlspecialchars($meal['category']) ?>"></div>
                        <div class="meal-info">
                            <div class="meal-type"><?= htmlspecialchars($meal['category']) ?></div>
                            <div class="meal-name"><?= htmlspecialchars($meal['title']) ?></div>
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
            <a href="/plan.php" class="btn btn-outline btn-sm">See all 7 days →</a>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card" style="text-align:center; padding:2.5rem;">
        <div style="margin-bottom:1rem;">
            <i data-lucide="calendar-x" style="width:48px;height:48px; color:var(--slate-light); display:block; margin:0 auto .75rem;"></i>
            <h3 style="margin-bottom:.5rem;">No active plan this week</h3>
            <p>Generate your personalised weekly meal plan based on your current stats.</p>
        </div>
        <a href="/plan.php" class="btn btn-primary">
            <i data-lucide="wand-2" style="width:16px;height:16px;"></i>
            Generate My Weekly Plan
        </a>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
