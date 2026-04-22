<?php
// ============================================================
// KCALS – Weekly Zone Card (Shareable)
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/engine/calculator.php';

requireLogin();

$db     = getDB();
$userId = (int) $_SESSION['user_id'];
$user   = getCurrentUser();

// ---- Week boundaries (Mon–Sun) ----
$today     = new DateTimeImmutable('today');
$weekStart = $today->modify('monday this week')->format('Y-m-d');
$weekEnd   = $today->modify('sunday this week')->format('Y-m-d');

// ---- Check-ins this week ----
$weekStmt = $db->prepare('
    SELECT * FROM user_progress
    WHERE user_id = ? AND entry_date BETWEEN ? AND ?
    ORDER BY entry_date ASC
');
$weekStmt->execute([$userId, $weekStart, $weekEnd]);
$weekRows = $weekStmt->fetchAll();

// ---- Streak: consecutive days with a check-in up to today ----
$streakStmt = $db->prepare('
    SELECT entry_date FROM user_progress
    WHERE user_id = ? AND entry_date <= CURDATE()
    ORDER BY entry_date DESC
');
$streakStmt->execute([$userId]);
$streakDates = array_column($streakStmt->fetchAll(), 'entry_date');
$streak = 0;
$checkDate = $today;
foreach ($streakDates as $d) {
    if ($d === $checkDate->format('Y-m-d')) {
        $streak++;
        $checkDate = $checkDate->modify('-1 day');
    } else {
        break;
    }
}

// ---- Weight change this week ----
$weightChange = null;
if (count($weekRows) >= 2) {
    $first = (float) $weekRows[0]['weight_kg'];
    $last  = (float) end($weekRows)['weight_kg'];
    $weightChange = round($last - $first, 1);
} elseif (count($weekRows) === 1) {
    // Compare with last entry before this week
    $prevStmt = $db->prepare('
        SELECT weight_kg FROM user_progress
        WHERE user_id = ? AND entry_date < ?
        ORDER BY entry_date DESC LIMIT 1
    ');
    $prevStmt->execute([$userId, $weekStart]);
    $prev = $prevStmt->fetch();
    if ($prev) {
        $weightChange = round((float) end($weekRows)['weight_kg'] - (float) $prev['weight_kg'], 1);
    }
}

// ---- Latest stats for zone + target ----
$latestProgress = !empty($weekRows) ? end($weekRows) : null;
if (!$latestProgress) {
    $fallbackStmt = $db->prepare('SELECT * FROM user_progress WHERE user_id = ? ORDER BY entry_date DESC LIMIT 1');
    $fallbackStmt->execute([$userId]);
    $latestProgress = $fallbackStmt->fetch();
}
$stats = $latestProgress ? calculateUserStats($user, $latestProgress) : null;

$zone       = $stats['zone']        ?? 'yellow';
$targetKcal = $stats['target_kcal'] ?? 0;
$checkinCount = count($weekRows);

// Zone display config
$zoneConfig = [
    'green'  => ['label' => 'Green',  'emoji' => '🟢', 'bg' => '#EAFAF1', 'accent' => '#27AE60', 'text' => '#1a7a42'],
    'yellow' => ['label' => 'Yellow', 'emoji' => '🟡', 'bg' => '#FEFDE7', 'accent' => '#F1C40F', 'text' => '#8a6d00'],
    'red'    => ['label' => 'Red',    'emoji' => '🔴', 'bg' => '#FEF0F0', 'accent' => '#E74C3C', 'text' => '#8a1a1a'],
];
$zc = $zoneConfig[$zone] ?? $zoneConfig['yellow'];

$weekLabel = (new DateTime($weekStart))->format('d M') . ' – ' . (new DateTime($weekEnd))->format('d M Y');

$pageTitle = __('share_page_title');
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:640px; margin:2rem auto; padding:0 1.25rem;">

    <div style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.5rem; font-weight:800; margin-bottom:.25rem;"><?= __('share_h1') ?></h1>
        <p style="color:var(--slate-mid); font-size:.9rem;"><?= __('share_sub') ?></p>
    </div>

    <?php if ($checkinCount === 0 && !$latestProgress): ?>
    <div class="alert" style="background:#fff3cd; border:1px solid #ffc107; color:#856404;">
        <?= __('share_no_data') ?>
    </div>
    <?php else: ?>

    <!-- ===================== ZONE CARD ===================== -->
    <div id="zone-card" style="
        background: <?= $zc['bg'] ?>;
        border: 2.5px solid <?= $zc['accent'] ?>;
        border-radius: 20px;
        padding: 2rem 2rem 1.5rem;
        font-family: 'Inter', sans-serif;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0,0,0,0.10);
    ">
        <!-- Background watermark -->
        <div style="
            position:absolute; right:-18px; bottom:-24px;
            font-size:9rem; opacity:0.07; line-height:1;
            pointer-events:none; user-select:none;
        "><?= $zc['emoji'] ?></div>

        <!-- Header row -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.5rem;">
            <div>
                <div style="font-size:1.35rem; font-weight:800; color:var(--slate-dark); line-height:1.2;">
                    <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?>'s Week
                </div>
                <div style="font-size:.78rem; color:var(--slate-mid); margin-top:.2rem;">
                    <?= __('share_week_of') ?>: <?= htmlspecialchars($weekLabel) ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:.65rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:<?= $zc['text'] ?>; margin-bottom:.25rem;"><?= __('share_zone_label') ?></div>
                <div style="
                    background: <?= $zc['accent'] ?>;
                    color: #fff;
                    font-size:1rem;
                    font-weight:800;
                    padding:.3rem .9rem;
                    border-radius:50px;
                    display:inline-block;
                    letter-spacing:.03em;
                "><?= $zc['emoji'] ?> <?= $zc['label'] ?></div>
            </div>
        </div>

        <!-- Stats grid -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.85rem; margin-bottom:1.5rem;">

            <div style="background:rgba(255,255,255,0.7); border-radius:14px; padding:1rem; text-align:center;">
                <div style="font-size:2rem; font-weight:800; color:<?= $zc['text'] ?>; line-height:1;"><?= $streak ?></div>
                <div style="font-size:.73rem; color:var(--slate-mid); margin-top:.2rem; font-weight:500;"><?= __('share_streak') ?></div>
            </div>

            <div style="background:rgba(255,255,255,0.7); border-radius:14px; padding:1rem; text-align:center;">
                <div style="font-size:2rem; font-weight:800; color:var(--slate-dark); line-height:1;"><?= $checkinCount ?>/7</div>
                <div style="font-size:.73rem; color:var(--slate-mid); margin-top:.2rem; font-weight:500;"><?= __('share_checkins') ?></div>
            </div>

            <div style="background:rgba(255,255,255,0.7); border-radius:14px; padding:1rem; text-align:center;">
                <?php if ($weightChange !== null): ?>
                    <div style="font-size:2rem; font-weight:800; color:<?= $weightChange <= 0 ? $zc['text'] : '#E74C3C' ?>; line-height:1;">
                        <?= $weightChange > 0 ? '+' : '' ?><?= $weightChange ?> kg
                    </div>
                <?php else: ?>
                    <div style="font-size:1.5rem; font-weight:800; color:var(--slate-mid); line-height:1;">—</div>
                <?php endif; ?>
                <div style="font-size:.73rem; color:var(--slate-mid); margin-top:.2rem; font-weight:500;"><?= __('share_weight_change') ?></div>
            </div>

            <div style="background:rgba(255,255,255,0.7); border-radius:14px; padding:1rem; text-align:center;">
                <div style="font-size:1.75rem; font-weight:800; color:var(--slate-dark); line-height:1;"><?= number_format($targetKcal) ?></div>
                <div style="font-size:.73rem; color:var(--slate-mid); margin-top:.2rem; font-weight:500;"><?= __('share_target_kcal') ?> kcal</div>
            </div>

        </div>

        <!-- Footer -->
        <div style="
            border-top: 1px solid <?= $zc['accent'] ?>33;
            padding-top:.85rem;
            display:flex; align-items:center; justify-content:space-between;
        ">
            <span style="font-size:.72rem; color:var(--slate-mid);"><?= __('share_tagline') ?></span>
            <span style="font-size:1.1rem; font-weight:800; color:<?= $zc['text'] ?>; letter-spacing:-.5px;">KCALS<span style="color:var(--slate-mid)">.</span></span>
        </div>
    </div>
    <!-- ===================== / ZONE CARD ===================== -->

    <!-- Action buttons -->
    <div style="display:flex; gap:.75rem; margin-top:1.25rem; flex-wrap:wrap;">
        <button id="download-btn" class="btn btn-primary" onclick="downloadCard()">
            <i data-lucide="download" style="width:15px;height:15px;"></i>
            <?= __('share_download') ?>
        </button>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline">
            <i data-lucide="arrow-left" style="width:15px;height:15px;"></i>
            <?= __('share_back') ?>
        </a>
    </div>

    <p style="margin-top:.85rem; font-size:.78rem; color:var(--slate-mid);">
        💡 <?php if ($_lang === 'el'): ?>Αποθήκευσε την κάρτα και μοιράσου τη στο Instagram, WhatsApp ή οπουδήποτε θέλεις!
        <?php else: ?>Save the card and share it on Instagram, WhatsApp or wherever you like!<?php endif; ?>
    </p>

    <?php endif; ?>
</div>

<!-- html2canvas from CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"
        integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
function downloadCard() {
    var btn = document.getElementById('download-btn');
    btn.disabled = true;
    btn.textContent = '...';

    var card = document.getElementById('zone-card');
    html2canvas(card, {
        scale: 2,
        useCORS: true,
        backgroundColor: null,
        logging: false
    }).then(function(canvas) {
        var link = document.createElement('a');
        link.download = 'kcals-zone-card.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="download" style="width:15px;height:15px;"></i> <?= addslashes(__('share_download')) ?>';
        lucide.createIcons();
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
