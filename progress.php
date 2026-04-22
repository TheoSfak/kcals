<?php
// ============================================================
// KCALS – Progress & Smart Insights (v1.0.1)
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/engine/calculator.php';

requireLogin();

$db     = getDB();
$userId = (int) $_SESSION['user_id'];
$user   = getCurrentUser();

// ---- Load ALL progress rows (ASC for correlations, heatmap) ----
$stmtAll = $db->prepare('
    SELECT * FROM user_progress WHERE user_id = ?
    ORDER BY entry_date ASC
');
$stmtAll->execute([$userId]);
$allAsc  = $stmtAll->fetchAll();
$allDesc = array_reverse($allAsc);
$total   = count($allAsc);

// Chart uses last 90 entries
$chartData      = array_slice($allAsc, -90);
$latestProgress = $allDesc[0] ?? null;
$stats          = $latestProgress ? calculateUserStats($user, $latestProgress) : null;

// ============================================================
// SMART INSIGHTS — pure-PHP analysis
// ============================================================
$insights    = [];
$hasInsights = $total >= 3;

if ($hasInsights) {
    // 1. Green Zone rate + high-stress→red correlation
    $greenCount      = 0;
    $redHighStress   = 0;
    $highStressTotal = 0;
    foreach ($allAsc as $r) {
        $z = determineZone((int)$r['stress_level'], (int)$r['motivation_level']);
        if ($z === 'green') $greenCount++;
        if ((int)$r['stress_level'] >= 8) {
            $highStressTotal++;
            if ($z === 'red') $redHighStress++;
        }
    }
    $greenPct = (int) round($greenCount / $total * 100);

    // 2. Sleep ≥7 → Green Zone correlation
    $sleepGoodRows  = array_values(array_filter($allAsc, fn($r) => (int)($r['sleep_level'] ?? 0) >= 7));
    $sleepGoodGreen = array_filter($sleepGoodRows, fn($r) => determineZone((int)$r['stress_level'], (int)$r['motivation_level']) === 'green');
    $sleepGreenPct  = count($sleepGoodRows) > 0 ? (int) round(count($sleepGoodGreen) / count($sleepGoodRows) * 100) : null;

    // 3. Workout days vs rest days motivation
    $workoutRows   = array_values(array_filter($allAsc, fn($r) => !empty($r['workout_type']) && (int)$r['workout_minutes'] > 0));
    $restRows      = array_values(array_filter($allAsc, fn($r) => empty($r['workout_type']) || (int)$r['workout_minutes'] === 0));
    $avgMotWorkout = count($workoutRows) > 0 ? array_sum(array_column($workoutRows, 'motivation_level')) / count($workoutRows) : null;
    $avgMotRest    = count($restRows)    > 0 ? array_sum(array_column($restRows,    'motivation_level')) / count($restRows)    : null;

    // 4. Best streak (consecutive calendar days)
    $dates     = array_column($allAsc, 'entry_date');
    $bestStreak = 1; $curStreak = 1;
    for ($i = 1; $i < count($dates); $i++) {
        $prev = date('Y-m-d', strtotime($dates[$i - 1] . ' +1 day'));
        if ($dates[$i] === $prev) { $curStreak++; } else { $curStreak = 1; }
        if ($curStreak > $bestStreak) $bestStreak = $curStreak;
    }

    // 5. Avg weight loss per week
    $firstW  = (float) $allAsc[0]['weight_kg'];
    $lastW   = (float) $allAsc[$total - 1]['weight_kg'];
    $dropped = $firstW - $lastW;
    $daySpan = max(1, (strtotime($allAsc[$total - 1]['entry_date']) - strtotime($allAsc[0]['entry_date'])) / 86400);
    $kgPerWk = $dropped / ($daySpan / 7);

    // 6. High-stress → red zone pct
    $stressRedPct = $highStressTotal > 0 ? (int) round($redHighStress / $highStressTotal * 100) : null;

    $insights = compact(
        'greenPct','sleepGreenPct','avgMotWorkout','avgMotRest',
        'bestStreak','dropped','kgPerWk','stressRedPct',
        'greenCount','total','firstW','lastW'
    );
}

// ---- Heatmap: date → zone index for last 365 days ----
$heatmapData = [];
foreach ($allAsc as $r) {
    $z = determineZone((int)$r['stress_level'], (int)$r['motivation_level']);
    $heatmapData[$r['entry_date']] = $z;
}

$pageTitle = __('ins_page_title') . ' – KCALS';
$activeNav = 'progress';
require_once __DIR__ . '/includes/header.php';
?>

<div class="ins-page">

    <!-- ==================== HERO + SUMMARY ==================== -->
    <div class="ins-hero">
        <div class="ins-hero-text">
            <h1><?= __('ins_page_title') ?></h1>
            <p><?= __('ins_page_sub') ?></p>
        </div>
        <?php if ($stats): ?>
        <div class="ins-summary-strip">
            <div class="ins-sum-item">
                <div class="ins-sum-val"><?= $total ?></div>
                <div class="ins-sum-lbl"><?= __('ins_summary_checkins') ?></div>
            </div>
            <div class="ins-sum-item">
                <?php $gp = $insights['greenPct'] ?? 0; ?>
                <div class="ins-sum-val <?= $gp >= 50 ? 'green' : ($gp >= 30 ? 'yellow' : 'red') ?>"><?= $gp ?>%</div>
                <div class="ins-sum-lbl"><?= __('ins_summary_green_pct') ?></div>
            </div>
            <div class="ins-sum-item">
                <?php $lost = isset($insights['dropped']) ? round($insights['dropped'], 1) : 0; ?>
                <div class="ins-sum-val <?= $lost > 0 ? 'green' : '' ?>"><?= $lost > 0 ? '-'.$lost : ($lost < 0 ? '+'.abs($lost) : '0') ?> kg</div>
                <div class="ins-sum-lbl"><?= __('ins_summary_weight_lost') ?></div>
            </div>
            <div class="ins-sum-item">
                <div class="ins-sum-val">🔥 <?= $insights['bestStreak'] ?? 1 ?></div>
                <div class="ins-sum-lbl"><?= __('ins_summary_streak') ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ==================== HEATMAP ==================== -->
    <div class="ins-card">
        <div class="ins-card-head">
            <div class="ins-card-title"><i data-lucide="calendar-days" style="width:16px;height:16px;"></i> <?= __('ins_heatmap_title') ?></div>
            <div class="ins-card-sub"><?= __('ins_heatmap_sub') ?></div>
        </div>
        <?php if ($total > 0): ?>
        <div class="ins-heatmap-wrap">
        <?php
        // Build 53-week grid (Mon→Sun columns), ending today
        $today = new DateTime('today');
        $start = (clone $today)->modify('-364 days');
        $dow   = (int)$start->format('N'); // 1=Mon..7=Sun
        if ($dow > 1) $start->modify('-' . ($dow - 1) . ' days');
        $cur   = clone $start;
        $months = [];
        $col    = 0;

        echo '<div class="ins-heatmap-grid">';
        // Day-of-week labels
        echo '<div class="ins-heatmap-days"><span>M</span><span></span><span>W</span><span></span><span>F</span><span></span><span>S</span></div>';
        echo '<div class="ins-heatmap-cols">';
        while ($cur <= $today) {
            echo '<div class="ins-heatmap-col">';
            for ($d = 0; $d < 7; $d++) {
                $key  = $cur->format('Y-m-d');
                $zone = $heatmapData[$key] ?? null;
                $cls  = ($cur > $today) ? 'future' : ($zone ? 'z-'.$zone : 'empty');
                echo '<div class="ins-heatmap-cell '.$cls.'" title="'.htmlspecialchars($key.($zone ? ' · '.strtoupper($zone) : '')).'"></div>';
                if ($d === 0) {
                    $mon = $cur->format('M');
                    if (empty($months) || end($months)['label'] !== $mon) {
                        $months[] = ['label' => $mon, 'col' => $col];
                    }
                }
                $cur->modify('+1 day');
            }
            echo '</div>';
            $col++;
        }
        echo '</div></div>'; // ins-heatmap-cols + ins-heatmap-grid

        // Month labels
        echo '<div class="ins-heatmap-months">';
        foreach ($months as $m) {
            echo '<span style="grid-column:'.($m['col']+1).'">'.htmlspecialchars($m['label']).'</span>';
        }
        echo '</div>';
        ?>
        <div class="ins-heatmap-legend">
            <span class="ins-heatmap-cell z-green"></span> Green &nbsp;
            <span class="ins-heatmap-cell z-yellow"></span> Yellow &nbsp;
            <span class="ins-heatmap-cell z-red"></span> Red &nbsp;
            <span class="ins-heatmap-cell empty"></span> No check-in
        </div>
        </div><!-- /.ins-heatmap-wrap -->
        <?php else: ?>
        <p class="ins-empty"><?= __('ins_heatmap_none') ?></p>
        <?php endif; ?>
    </div>

    <!-- ==================== MULTI-METRIC CHART ==================== -->
    <div class="ins-card">
        <div class="ins-card-head">
            <div class="ins-card-title"><i data-lucide="line-chart" style="width:16px;height:16px;"></i> <?= __('ins_chart_title') ?></div>
        </div>
        <?php if (count($chartData) > 1): ?>
        <div class="ins-chart-toggles">
            <button class="ins-toggle active" data-ds="0"><?= __('ins_chart_weight') ?></button>
            <button class="ins-toggle active" data-ds="1"><?= __('ins_chart_motivation') ?></button>
            <button class="ins-toggle" data-ds="2"><?= __('ins_chart_stress') ?></button>
            <button class="ins-toggle" data-ds="3"><?= __('ins_chart_sleep') ?></button>
        </div>
        <div style="height:280px; position:relative;">
            <canvas id="insChart"></canvas>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var labels      = <?= json_encode(array_column($chartData, 'entry_date')) ?>;
            var weights     = <?= json_encode(array_map(fn($r) => (float)$r['weight_kg'], $chartData)) ?>;
            var motivations = <?= json_encode(array_map(fn($r) => (int)$r['motivation_level'], $chartData)) ?>;
            var stresses    = <?= json_encode(array_map(fn($r) => (int)$r['stress_level'], $chartData)) ?>;
            var sleeps      = <?= json_encode(array_map(fn($r) => (int)($r['sleep_level'] ?? 5), $chartData)) ?>;
            var zoneColors  = <?= json_encode(array_map(function($r) {
                $z = determineZone((int)$r['stress_level'], (int)$r['motivation_level']);
                return $z === 'green' ? '#2ECC71' : ($z === 'yellow' ? '#F39C12' : '#E74C3C');
            }, $chartData)) ?>;

            var insChart = new Chart(document.getElementById('insChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: <?= json_encode(__('ins_chart_weight')) ?>,
                            data: weights,
                            borderColor: '#2ECC71', backgroundColor: 'rgba(46,204,113,0.1)',
                            borderWidth: 2.5, pointRadius: 5,
                            pointBackgroundColor: zoneColors,
                            pointBorderColor: '#fff', pointBorderWidth: 1.5,
                            tension: 0.35, fill: true, yAxisID: 'y'
                        },
                        {
                            label: <?= json_encode(__('ins_chart_motivation')) ?>,
                            data: motivations,
                            borderColor: '#3498DB', backgroundColor: 'rgba(52,152,219,0)',
                            borderWidth: 2, pointRadius: 3, tension: 0.35, fill: false, yAxisID: 'y1'
                        },
                        {
                            label: <?= json_encode(__('ins_chart_stress')) ?>,
                            data: stresses,
                            borderColor: '#E74C3C', backgroundColor: 'rgba(231,76,60,0)',
                            borderWidth: 2, pointRadius: 3, tension: 0.35, fill: false, yAxisID: 'y1',
                            hidden: true
                        },
                        {
                            label: <?= json_encode(__('ins_chart_sleep')) ?>,
                            data: sleeps,
                            borderColor: '#9B59B6', backgroundColor: 'rgba(155,89,182,0)',
                            borderWidth: 2, pointRadius: 3, tension: 0.35, fill: false, yAxisID: 'y1',
                            hidden: true
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false } },
                    scales: {
                        y:  { position: 'left',  grid: { color: '#f0f0f0' }, ticks: { font: { size: 11 } } },
                        y1: { position: 'right', min: 1, max: 10, grid: { drawOnChartArea: false }, ticks: { font: { size: 11 }, stepSize: 1 } },
                        x:  { grid: { display: false }, ticks: { maxTicksLimit: 10, font: { size: 10 } } }
                    }
                }
            });

            document.querySelectorAll('.ins-toggle').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var di   = parseInt(this.dataset.ds);
                    var meta = insChart.getDatasetMeta(di);
                    meta.hidden = meta.hidden === null ? !insChart.data.datasets[di].hidden : !meta.hidden;
                    insChart.update();
                    this.classList.toggle('active', !meta.hidden);
                });
            });
        });
        </script>
        <?php else: ?>
        <p class="ins-empty"><?= __('progress_chart_need') ?></p>
        <?php endif; ?>
    </div>

    <!-- ==================== CORRELATIONS ==================== -->
    <div class="ins-card">
        <div class="ins-card-head">
            <div class="ins-card-title"><i data-lucide="brain" style="width:16px;height:16px;"></i> <?= __('ins_corr_title') ?></div>
            <div class="ins-card-sub"><?= __('ins_corr_sub') ?></div>
        </div>
        <?php if (!$hasInsights): ?>
        <p class="ins-empty"><?= __('ins_no_data') ?></p>
        <?php else: ?>
        <div class="ins-corr-grid">

            <div class="ins-corr-card ins-corr-green">
                <div class="ins-corr-icon">🟢</div>
                <div class="ins-corr-body"><?= sprintf(__('ins_corr_best_zone'), $insights['greenPct']) ?></div>
            </div>

            <?php if ($insights['sleepGreenPct'] !== null): ?>
            <div class="ins-corr-card ins-corr-purple">
                <div class="ins-corr-icon">😴</div>
                <div class="ins-corr-body"><?= sprintf(__('ins_corr_sleep_green'), $insights['sleepGreenPct']) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($insights['avgMotWorkout'] !== null && $insights['avgMotRest'] !== null): ?>
            <div class="ins-corr-card ins-corr-blue">
                <div class="ins-corr-icon">🏋️</div>
                <div class="ins-corr-body"><?= sprintf(__('ins_corr_workout_mot'), $insights['avgMotWorkout'], $insights['avgMotRest']) ?></div>
            </div>
            <?php endif; ?>

            <div class="ins-corr-card ins-corr-orange">
                <div class="ins-corr-icon">🔥</div>
                <div class="ins-corr-body"><?= sprintf(__('ins_corr_best_streak'), $insights['bestStreak']) ?></div>
            </div>

            <?php if (abs($insights['kgPerWk']) > 0.01): ?>
            <div class="ins-corr-card <?= $insights['kgPerWk'] > 0 ? 'ins-corr-green' : 'ins-corr-red' ?>">
                <div class="ins-corr-icon"><?= $insights['kgPerWk'] > 0 ? '📉' : '📈' ?></div>
                <div class="ins-corr-body"><?= sprintf(__('ins_corr_avg_weight'), abs($insights['kgPerWk'])) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($insights['stressRedPct'] !== null && $insights['stressRedPct'] > 0): ?>
            <div class="ins-corr-card ins-corr-red">
                <div class="ins-corr-icon">😰</div>
                <div class="ins-corr-body"><?= sprintf(__('ins_corr_stress_red'), $insights['stressRedPct']) ?></div>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </div>

    <!-- ==================== HISTORY TABLE ==================== -->
    <div class="ins-card">
        <div class="ins-card-head">
            <div class="ins-card-title"><i data-lucide="list" style="width:16px;height:16px;"></i> <?= __('progress_history') ?></div>
        </div>
        <?php if ($allDesc): ?>
        <div style="overflow-x:auto;">
            <table class="ins-table">
                <thead>
                    <tr>
                        <th><?= __('th_date') ?></th>
                        <th><?= __('th_weight') ?></th>
                        <th><?= __('th_stress') ?></th>
                        <th><?= __('th_motivation') ?></th>
                        <th><?= __('th_sleep') ?></th>
                        <th><?= __('th_workout') ?></th>
                        <th><?= __('th_zone') ?></th>
                        <th><?= __('th_notes') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allDesc as $entry):
                        $zone = determineZone((int)$entry['stress_level'], (int)$entry['motivation_level']);
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($entry['entry_date']) ?></td>
                        <td style="text-align:right; font-weight:700; color:var(--green-dark);"><?= number_format((float)$entry['weight_kg'], 1) ?> kg</td>
                        <td style="text-align:center;"><?= $entry['stress_level'] ?>/10</td>
                        <td style="text-align:center;"><?= $entry['motivation_level'] ?>/10</td>
                        <td style="text-align:center;"><?= isset($entry['sleep_level']) ? $entry['sleep_level'].'/10' : '—' ?></td>
                        <td style="text-align:center; font-size:.8rem;"><?php
                            $wt = $entry['workout_type'] ?? '';
                            $wm = (int)($entry['workout_minutes'] ?? 0);
                            echo ($wt && $wm > 0) ? htmlspecialchars(__('workout_'.$wt)).' '.$wm.' min' : '—';
                        ?></td>
                        <td style="text-align:center;"><span class="zone-badge <?= $zone ?>"><?= strtoupper($zone) ?></span></td>
                        <td style="font-size:.8rem; color:var(--slate-mid);"><?= htmlspecialchars($entry['notes'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="ins-empty"><?= sprintf(__('progress_no_entries'), htmlspecialchars(BASE_URL.'/dashboard.php')) ?></p>
        <?php endif; ?>
    </div>

</div><!-- /.ins-page -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
