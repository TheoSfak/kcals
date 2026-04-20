<?php
// ============================================================
// KCALS – Progress Tracking Page
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/engine/calculator.php';

requireLogin();

$db     = getDB();
$userId = (int) $_SESSION['user_id'];
$user   = getCurrentUser();

// Load all progress entries
$stmt = $db->prepare('
    SELECT * FROM user_progress WHERE user_id = ?
    ORDER BY entry_date DESC LIMIT 30
');
$stmt->execute([$userId]);
$allProgress = $stmt->fetchAll();
$chartData   = array_reverse($allProgress);

$latestProgress = $allProgress[0] ?? null;
$stats = $latestProgress ? calculateUserStats($user, $latestProgress) : null;

$pageTitle = 'Progress – KCALS';
$activeNav = 'progress';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:1100px; margin:2rem auto; padding:0 1.25rem;">

    <div style="margin-bottom:1.75rem;">
        <h1 style="font-size:1.5rem; margin-bottom:.25rem;">My Progress</h1>
        <p class="text-small" style="color:var(--slate-mid);">Track your weight and wellness over time.</p>
    </div>

    <?php if ($stats): ?>
    <div class="stat-grid" style="margin-bottom:1.5rem;">
        <div class="stat-card">
            <div class="stat-value green"><?= $stats['weight'] ?> kg</div>
            <div class="stat-label">Current Weight</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['ideal_weight'] ?> kg</div>
            <div class="stat-label">Ideal Weight</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['weight_diff'] > 0 ? '+' : '' ?><?= $stats['weight_diff'] ?> kg</div>
            <div class="stat-label">To Ideal Weight</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['weeks_to_goal'] > 0 ? $stats['weeks_to_goal'].' wk' : '✓ Goal!' ?></div>
            <div class="stat-label">Est. Timeline</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['kg_per_week'] ?> kg</div>
            <div class="stat-label">Per Week (est.)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($allProgress) ?></div>
            <div class="stat-label">Check-ins Logged</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Weight Chart -->
    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header">
            <div class="card-title">
                <div class="icon-wrap"><i data-lucide="trending-down" style="width:16px;height:16px;"></i></div>
                Weight Trend (last 30 entries)
            </div>
        </div>
        <?php if (count($chartData) > 1): ?>
        <div style="height:280px;">
            <canvas id="weightChart"></canvas>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            new Chart(document.getElementById('weightChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($chartData, 'entry_date')) ?>,
                    datasets: [{
                        label: 'Weight (kg)',
                        data: <?= json_encode(array_map(fn($r)=>(float)$r['weight_kg'], $chartData)) ?>,
                        borderColor: '#2ECC71',
                        backgroundColor: 'rgba(46,204,113,0.08)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#27AE60',
                        pointRadius: 4,
                        tension: 0.35,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { grid: { color: '#eee' } },
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }
                    }
                }
            });
        });
        </script>
        <?php else: ?>
        <p style="text-align:center; color:var(--slate-mid); padding:2rem;">Log at least 2 check-ins to see your trend chart.</p>
        <?php endif; ?>
    </div>

    <!-- Progress Log Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="icon-wrap"><i data-lucide="list" style="width:16px;height:16px;"></i></div>
                Check-in History
            </div>
        </div>
        <?php if ($allProgress): ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:.85rem;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);">
                        <th style="padding:.6rem .75rem; text-align:left; color:var(--slate-mid); font-weight:600; font-size:.75rem; text-transform:uppercase; letter-spacing:.5px;">Date</th>
                        <th style="padding:.6rem .75rem; text-align:right; color:var(--slate-mid); font-weight:600; font-size:.75rem; text-transform:uppercase;">Weight</th>
                        <th style="padding:.6rem .75rem; text-align:center; color:var(--slate-mid); font-weight:600; font-size:.75rem; text-transform:uppercase;">Stress</th>
                        <th style="padding:.6rem .75rem; text-align:center; color:var(--slate-mid); font-weight:600; font-size:.75rem; text-transform:uppercase;">Motivation</th>
                        <th style="padding:.6rem .75rem; text-align:center; color:var(--slate-mid); font-weight:600; font-size:.75rem; text-transform:uppercase;">Zone</th>
                        <th style="padding:.6rem .75rem; text-align:left; color:var(--slate-mid); font-weight:600; font-size:.75rem; text-transform:uppercase;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allProgress as $entry):
                        $zone = determineZone((int)$entry['stress_level'], (int)$entry['motivation_level']);
                    ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:.65rem .75rem; font-weight:600;"><?= htmlspecialchars($entry['entry_date']) ?></td>
                        <td style="padding:.65rem .75rem; text-align:right; font-weight:700; color:var(--green-dark);"><?= number_format((float)$entry['weight_kg'], 1) ?> kg</td>
                        <td style="padding:.65rem .75rem; text-align:center;"><?= $entry['stress_level'] ?>/10</td>
                        <td style="padding:.65rem .75rem; text-align:center;"><?= $entry['motivation_level'] ?>/10</td>
                        <td style="padding:.65rem .75rem; text-align:center;"><span class="zone-badge <?= $zone ?>"><?= strtoupper($zone) ?></span></td>
                        <td style="padding:.65rem .75rem; color:var(--slate-mid); font-size:.8rem;"><?= htmlspecialchars($entry['notes'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="text-align:center; color:var(--slate-mid); padding:2rem;">No check-ins logged yet. <a href="/dashboard.php">Go to dashboard</a> to add your first.</p>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
