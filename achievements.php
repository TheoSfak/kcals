<?php
// ============================================================
// KCALS – Achievements Page
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/engine/achievements.php';

requireLogin();

$db     = getDB();
$userId = (int) $_SESSION['user_id'];
$user   = getCurrentUser();

// Award social_butterfly if they came here from share.php
if (isset($_GET['from']) && $_GET['from'] === 'share') {
    awardAchievement($userId, 'social_butterfly', $db);
    awardAchievement($userId, 'first_share', $db);
}

// Run full check on page load too
$newlyEarned = checkAndAwardAchievements($userId, $db);

$catalogue   = getAchievementCatalogue();
$earned      = getEarnedSlugs($userId, $db);
$earnedCount = count($earned);
$totalCount  = count($catalogue);

$pageTitle = __('ach_page_title');
$activeNav = 'achievements';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ==================== NEW ACHIEVEMENT TOAST ==================== -->
<?php if (!empty($newlyEarned)): ?>
<div id="ach-toast-container">
    <?php foreach ($newlyEarned as $slug):
        $a = getAchievementBySlug($slug);
        if (!$a) continue;
    ?>
    <div class="ach-toast ach-toast-<?= htmlspecialchars($a['tier']) ?>">
        <div class="ach-toast-icon"><?= $a['icon'] ?></div>
        <div class="ach-toast-body">
            <strong><?= __('ach_new_title') ?></strong>
            <div><?= __('ach_new_unlocked') ?> <em><?= __($a['title']) ?></em></div>
        </div>
        <button class="ach-toast-close" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php endforeach; ?>
</div>
<script>
    setTimeout(function(){
        document.querySelectorAll('.ach-toast').forEach(function(t){
            t.classList.add('fading');
            setTimeout(function(){ t.remove(); }, 400);
        });
    }, 5000);
</script>
<?php endif; ?>

<!-- ==================== HERO STRIP ==================== -->
<section class="ach-hero">
    <div class="container">
        <div class="ach-hero-inner">
            <div>
                <h1 class="ach-hero-title"><?= __('ach_page_title') ?></h1>
                <p class="ach-hero-sub"><?= __('ach_page_sub') ?></p>
            </div>
            <div class="ach-progress-circle" title="<?= $earnedCount ?> / <?= $totalCount ?>">
                <svg viewBox="0 0 80 80">
                    <circle cx="40" cy="40" r="34" stroke="rgba(255,255,255,0.25)" stroke-width="8" fill="none"/>
                    <?php
                        $pct  = $totalCount > 0 ? $earnedCount / $totalCount : 0;
                        $circ = 2 * M_PI * 34;
                        $dash = round($pct * $circ, 1);
                        $gap  = round((1 - $pct) * $circ, 1);
                    ?>
                    <circle cx="40" cy="40" r="34"
                        stroke="#fff" stroke-width="8" fill="none"
                        stroke-dasharray="<?= $dash ?> <?= $gap ?>"
                        stroke-linecap="round"
                        transform="rotate(-90 40 40)"/>
                    <text x="40" y="36" text-anchor="middle" font-size="15" font-weight="800" fill="#fff"><?= $earnedCount ?></text>
                    <text x="40" y="50" text-anchor="middle" font-size="8" fill="rgba(255,255,255,.8)"><?= __('ach_total') ?></text>
                </svg>
            </div>
        </div>

        <!-- Share card button -->
        <div style="margin-top:1.5rem; display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
            <button id="ach-share-btn" class="btn" style="background:#fff; color:var(--green-dark); font-weight:700; gap:.5rem;">
                <i data-lucide="share-2" style="width:16px;height:16px;"></i>
                <?= __('ach_share_btn') ?>
            </button>
        </div>
    </div>
</section>

<!-- ==================== FILTER TABS ==================== -->
<section class="section" style="padding-top:1.5rem; padding-bottom:0;">
    <div class="container">
        <div class="ach-filters" id="ach-filters">
            <?php
            $categories = ['all','streak','checkins','weight','zone','workout','sleep','profile','special'];
            foreach ($categories as $cat): ?>
            <button class="ach-filter-btn <?= $cat === 'all' ? 'active' : '' ?>"
                    data-cat="<?= $cat ?>">
                <?= __('ach_filter_' . $cat) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================== BADGE GRID ==================== -->
<section class="section" style="padding-top:1.5rem;">
    <div class="container">
        <div class="ach-grid" id="ach-grid">
            <?php foreach ($catalogue as $a):
                $isEarned   = isset($earned[$a['slug']]);
                $earnedDate = $isEarned ? date('d/m/Y', strtotime($earned[$a['slug']])) : null;
            ?>
            <div class="ach-card <?= $isEarned ? 'earned' : 'locked' ?> tier-<?= $a['tier'] ?>"
                 data-cat="<?= $a['category'] ?>"
                 title="<?= htmlspecialchars(__($a['desc'])) ?>">
                <div class="ach-card-icon"><?= $a['icon'] ?></div>
                <div class="ach-card-tier"><?= __('ach_tier_' . $a['tier']) ?></div>
                <div class="ach-card-title"><?= htmlspecialchars(__($a['title'])) ?></div>
                <div class="ach-card-desc"><?= htmlspecialchars(__($a['desc'])) ?></div>
                <?php if ($isEarned): ?>
                    <div class="ach-card-earned"><?= __('ach_earned_on') ?> <?= $earnedDate ?></div>
                <?php else: ?>
                    <div class="ach-card-locked-lbl"><i data-lucide="lock" style="width:12px;height:12px;"></i> <?= __('ach_locked') ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================== SHARE CARD (hidden, captured by html2canvas) ==================== -->
<div id="ach-share-card" style="position:fixed; left:-9999px; top:-9999px; width:540px;">
    <div style="background:linear-gradient(135deg,#1a5c36 0%,#27ae60 100%); padding:2.5rem 2rem; border-radius:20px; font-family:Inter,system-ui,sans-serif; color:#fff;">
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
            <div style="font-size:2.5rem; font-weight:900; letter-spacing:-1px;">KCALS<span style="color:#a8e6c4;">.</span></div>
            <div>
                <div style="font-size:1rem; font-weight:700; opacity:.9;"><?= htmlspecialchars($user['full_name'] ?? '') ?></div>
                <div style="font-size:.8rem; opacity:.65;"><?= __('ach_share_title') ?></div>
            </div>
        </div>

        <!-- Earned badges mosaic -->
        <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:.5rem; margin-bottom:1.5rem;">
            <?php
            $earnedCards = array_filter($catalogue, fn($a) => isset($earned[$a['slug']]));
            $shownCount  = 0;
            foreach ($earnedCards as $a):
                if ($shownCount >= 21) break;
                $shownCount++;
            ?>
            <div style="background:rgba(255,255,255,0.18); border-radius:10px; padding:.4rem; text-align:center; font-size:1.4rem;">
                <?= $a['icon'] ?>
            </div>
            <?php endforeach; ?>
            <?php for ($i = $shownCount; $i < 21; $i++): ?>
            <div style="background:rgba(255,255,255,0.07); border-radius:10px; padding:.4rem; text-align:center; font-size:1.4rem; opacity:.3;">
                🔒
            </div>
            <?php endfor; ?>
        </div>

        <!-- Stats -->
        <div style="display:flex; gap:1.5rem; align-items:center; border-top:1px solid rgba(255,255,255,0.2); padding-top:1rem;">
            <div>
                <div style="font-size:2.5rem; font-weight:900; line-height:1;"><?= $earnedCount ?></div>
                <div style="font-size:.75rem; opacity:.75;"><?= __('ach_share_sub') ?></div>
            </div>
            <div style="font-size:.75rem; opacity:.65; flex:1; text-align:right;">
                kcals.app • <?= date('d/m/Y') ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
// ---- Category filter ----
document.getElementById('ach-filters').addEventListener('click', function(e) {
    const btn = e.target.closest('.ach-filter-btn');
    if (!btn) return;
    document.querySelectorAll('.ach-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const cat = btn.dataset.cat;
    document.querySelectorAll('.ach-card').forEach(function(card) {
        card.style.display = (cat === 'all' || card.dataset.cat === cat) ? '' : 'none';
    });
});

// ---- Share card ----
document.getElementById('ach-share-btn').addEventListener('click', function() {
    const btn  = this;
    const card = document.getElementById('ach-share-card');
    card.style.left = '-9999px';
    card.style.position = 'fixed';
    btn.disabled = true;
    btn.innerHTML = '⏳ …';
    html2canvas(card, { scale: 2, useCORS: true, backgroundColor: null }).then(function(canvas) {
        // Try Web Share API first (mobile)
        canvas.toBlob(function(blob) {
            if (navigator.share && navigator.canShare && navigator.canShare({ files: [new File([blob], 'kcals-achievements.png', { type: 'image/png' })] })) {
                navigator.share({
                    title: '<?= addslashes(__('ach_share_title')) ?>',
                    files: [new File([blob], 'kcals-achievements.png', { type: 'image/png' })]
                }).catch(function(){});
            } else {
                const link = document.createElement('a');
                link.download = 'kcals-achievements.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            }
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="share-2" style="width:16px;height:16px;"></i> <?= addslashes(__('ach_share_btn')) ?>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
