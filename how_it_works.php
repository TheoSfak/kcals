<?php
// ============================================================
// KCALS – How It Works
// Public page (no login required), explains every algorithm
// and smart system in plain language.
// ============================================================
require_once __DIR__ . '/includes/auth.php';

$pageTitle = __('hiw_page_title');
$activeNav = 'how_it_works';
require_once __DIR__ . '/includes/header.php';
?>

<div class="hiw-page">

    <!-- ======== HERO ======== -->
    <section class="hiw-hero">
        <div class="hiw-hero-inner">
            <div class="hiw-hero-badge">
                <i data-lucide="flask-conical" style="width:14px;height:14px;vertical-align:-2px;"></i>
                Science-backed • Transparent • No black boxes
            </div>
            <h1><?= __('hiw_hero_title') ?></h1>
            <p><?= __('hiw_hero_sub') ?></p>
        </div>
    </section>

    <!-- ======== SECTION 1: Core Calculations ======== -->
    <section class="hiw-section">
        <div class="hiw-section-label">
            <span>01</span> <?= __('hiw_s1_title') ?>
        </div>

        <div class="hiw-card">
            <button class="hiw-card-btn" onclick="toggleCard(this)" aria-expanded="false">
                <span class="hiw-card-title"><?= __('hiw_bmr_title') ?></span>
                <i data-lucide="chevron-down" class="hiw-chevron"></i>
            </button>
            <div class="hiw-card-body">
                <p><?= __('hiw_bmr_body') ?></p>
                <div class="hiw-formula-box">
                    <code>Men:&nbsp;&nbsp; (10 × kg) + (6.25 × cm) − (5 × age) + 5</code>
                    <code>Women: (10 × kg) + (6.25 × cm) − (5 × age) − 161</code>
                </div>
            </div>
        </div>

        <div class="hiw-card">
            <button class="hiw-card-btn" onclick="toggleCard(this)" aria-expanded="false">
                <span class="hiw-card-title"><?= __('hiw_tdee_title') ?></span>
                <i data-lucide="chevron-down" class="hiw-chevron"></i>
            </button>
            <div class="hiw-card-body">
                <p><?= __('hiw_tdee_body') ?></p>
                <table class="hiw-table">
                    <thead><tr><th>Activity Level</th><th>Multiplier</th></tr></thead>
                    <tbody>
                        <tr><td>Sedentary (desk job, no exercise)</td><td>× 1.2</td></tr>
                        <tr><td>Lightly Active (1–3×/week)</td><td>× 1.375</td></tr>
                        <tr><td>Moderately Active (3–5×/week)</td><td>× 1.55</td></tr>
                        <tr><td>Very Active (6–7×/week)</td><td>× 1.725</td></tr>
                        <tr><td>Extra Active (physical job + hard training)</td><td>× 1.9</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hiw-card">
            <button class="hiw-card-btn" onclick="toggleCard(this)" aria-expanded="false">
                <span class="hiw-card-title"><?= __('hiw_zones_title') ?></span>
                <i data-lucide="chevron-down" class="hiw-chevron"></i>
            </button>
            <div class="hiw-card-body">
                <?= __('hiw_zones_body') ?>
                <div class="hiw-zones-grid">
                    <div class="hiw-zone hiw-zone--green">
                        <span class="hiw-zone-badge">🟢 Green</span>
                        <strong>25% deficit</strong>
                        <small>Low stress + High motivation</small>
                    </div>
                    <div class="hiw-zone hiw-zone--yellow">
                        <span class="hiw-zone-badge">🟡 Yellow</span>
                        <strong>15% deficit</strong>
                        <small>Middle ground</small>
                    </div>
                    <div class="hiw-zone hiw-zone--red">
                        <span class="hiw-zone-badge">🔴 Red</span>
                        <strong>8% deficit</strong>
                        <small>High stress / Low motivation</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ======== SECTION 2: Smart Systems ======== -->
    <section class="hiw-section">
        <div class="hiw-section-label">
            <span>02</span> <?= __('hiw_s2_title') ?>
        </div>

        <?php
        $smartCards = [
            ['hiw_plateau_title', 'hiw_plateau_body',  'trending-up'],
            ['hiw_recal_title',   'hiw_recal_body',    'refresh-cw'],
            ['hiw_recharge_title','hiw_recharge_body', 'zap'],
            ['hiw_recovery_title','hiw_recovery_body', 'heart-pulse'],
            ['hiw_sleep_title',   'hiw_sleep_body',    'moon'],
            ['hiw_workout_title', 'hiw_workout_body',  'dumbbell'],
        ];
        foreach ($smartCards as [$titleKey, $bodyKey, $icon]): ?>
        <div class="hiw-card">
            <button class="hiw-card-btn" onclick="toggleCard(this)" aria-expanded="false">
                <span class="hiw-card-title"><?= __($titleKey) ?></span>
                <i data-lucide="chevron-down" class="hiw-chevron"></i>
            </button>
            <div class="hiw-card-body">
                <p><?= __($bodyKey) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <!-- ======== SECTION 3: Meal Planning ======== -->
    <section class="hiw-section">
        <div class="hiw-section-label">
            <span>03</span> <?= __('hiw_s3_title') ?>
        </div>

        <?php
        $mealCards = [
            ['hiw_builder_title', 'hiw_builder_body', 'utensils'],
            ['hiw_macros_title',  'hiw_macros_body',  'pie-chart'],
            ['hiw_buffer_title',  'hiw_buffer_body',  'calendar-heart'],
        ];
        foreach ($mealCards as [$titleKey, $bodyKey, $icon]): ?>
        <div class="hiw-card">
            <button class="hiw-card-btn" onclick="toggleCard(this)" aria-expanded="false">
                <span class="hiw-card-title"><?= __($titleKey) ?></span>
                <i data-lucide="chevron-down" class="hiw-chevron"></i>
            </button>
            <div class="hiw-card-body">
                <p><?= __($bodyKey) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <!-- ======== SECTION 4: Personalisation ======== -->
    <section class="hiw-section">
        <div class="hiw-section-label">
            <span>04</span> <?= __('hiw_s4_title') ?>
        </div>

        <?php
        $prefCards = [
            ['hiw_event_title', 'hiw_event_body', 'calendar-clock'],
            ['hiw_prefs_title', 'hiw_prefs_body', 'leaf'],
        ];
        foreach ($prefCards as [$titleKey, $bodyKey, $icon]): ?>
        <div class="hiw-card">
            <button class="hiw-card-btn" onclick="toggleCard(this)" aria-expanded="false">
                <span class="hiw-card-title"><?= __($titleKey) ?></span>
                <i data-lucide="chevron-down" class="hiw-chevron"></i>
            </button>
            <div class="hiw-card-body">
                <p><?= __($bodyKey) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <!-- ======== SECTION 5: Smart Insights ======== -->
    <section class="hiw-section">
        <div class="hiw-section-label">
            <span>05</span> <?= __('hiw_s5_title') ?>
        </div>

        <?php
        $insCards = [
            ['hiw_insights_title', 'hiw_insights_body', 'brain'],
            ['hiw_heatmap_title',  'hiw_heatmap_body',  'calendar-days'],
            ['hiw_corr_title',     'hiw_corr_body',     'activity'],
        ];
        foreach ($insCards as [$titleKey, $bodyKey, $icon]): ?>
        <div class="hiw-card">
            <button class="hiw-card-btn" onclick="toggleCard(this)" aria-expanded="false">
                <span class="hiw-card-title"><?= __($titleKey) ?></span>
                <i data-lucide="chevron-down" class="hiw-chevron"></i>
            </button>
            <div class="hiw-card-body">
                <p><?= __($bodyKey) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <!-- ======== DISCLAIMER + SOURCES ======== -->
    <section class="hiw-footer-note">
        <p><?= __('hiw_disclaimer') ?></p>
        <p class="hiw-sources"><?= __('hiw_sources') ?></p>
    </section>

    <!-- ======== CTA (logged-out only) ======== -->
    <?php if (!isLoggedIn()): ?>
    <section class="hiw-cta">
        <h2><?= __('cta_title') ?></h2>
        <p><?= __('cta_sub') ?></p>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-lg">
            <i data-lucide="user-plus" style="width:18px;height:18px;"></i>
            <?= __('cta_btn') ?>
        </a>
    </section>
    <?php endif; ?>

</div><!-- /.hiw-page -->

<style>
/* ======================================================
   How It Works — Page Styles
   Uses existing CSS variables; no new dependencies.
   ====================================================== */

.hiw-page {
    max-width: 820px;
    margin: 0 auto;
    padding: 2rem 1.25rem 4rem;
}

/* Hero */
.hiw-hero {
    text-align: center;
    padding: 3rem 1rem 2.5rem;
}
.hiw-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: var(--green-light, #e8f5e9);
    color: var(--green-dark, #2e7d32);
    font-size: .75rem;
    font-weight: 600;
    border-radius: 99px;
    padding: .3rem .9rem;
    margin-bottom: 1.1rem;
    letter-spacing: .03em;
    text-transform: uppercase;
}
.hiw-hero h1 {
    font-size: clamp(1.7rem, 4vw, 2.4rem);
    font-weight: 800;
    margin-bottom: .75rem;
    line-height: 1.2;
    color: var(--text-main, #1a1a2e);
}
.hiw-hero p {
    font-size: 1.05rem;
    color: var(--slate-mid, #64748b);
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.65;
}

/* Section */
.hiw-section {
    margin-bottom: 2rem;
}
.hiw-section-label {
    display: flex;
    align-items: center;
    gap: .65rem;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--slate-light, #94a3b8);
    margin-bottom: 1rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
}
.hiw-section-label span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px; height: 22px;
    background: var(--green-dark, #2e7d32);
    color: #fff;
    border-radius: 50%;
    font-size: .65rem;
    font-weight: 700;
    flex-shrink: 0;
}

/* Accordion Card */
.hiw-card {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 12px;
    margin-bottom: .65rem;
    overflow: hidden;
    box-shadow: var(--shadow, 0 1px 3px rgba(0,0,0,.06));
    transition: box-shadow .2s;
}
.hiw-card:hover {
    box-shadow: 0 3px 10px rgba(0,0,0,.09);
}
.hiw-card-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: 1rem 1.25rem;
    background: none;
    border: none;
    cursor: pointer;
    text-align: left;
    font-family: inherit;
}
.hiw-card-btn:focus-visible {
    outline: 2px solid var(--green-dark, #2e7d32);
    outline-offset: -2px;
}
.hiw-card-title {
    font-size: .98rem;
    font-weight: 600;
    color: var(--text-main, #1a1a2e);
    line-height: 1.4;
}
.hiw-chevron {
    width: 18px; height: 18px;
    flex-shrink: 0;
    color: var(--slate-light, #94a3b8);
    transition: transform .25s ease;
}
.hiw-card-btn[aria-expanded="true"] .hiw-chevron {
    transform: rotate(180deg);
}
.hiw-card-body {
    display: none;
    padding: 0 1.25rem 1.25rem;
    font-size: .93rem;
    color: var(--text-body, #374151);
    line-height: 1.7;
    border-top: 1px solid var(--border, #e2e8f0);
}
.hiw-card-body.open {
    display: block;
}
.hiw-card-body p { margin: .75rem 0 0; }
.hiw-card-body ul { margin: .75rem 0 0 1.25rem; }
.hiw-card-body li { margin-bottom: .35rem; }

/* Formula Box */
.hiw-formula-box {
    background: var(--surface-alt, #f8fafc);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    padding: .85rem 1rem;
    margin-top: .85rem;
    display: flex;
    flex-direction: column;
    gap: .3rem;
}
.hiw-formula-box code {
    font-family: 'Courier New', monospace;
    font-size: .84rem;
    color: var(--green-dark, #2e7d32);
}

/* Activity Table */
.hiw-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: .85rem;
    font-size: .87rem;
}
.hiw-table th {
    background: var(--surface-alt, #f8fafc);
    text-align: left;
    padding: .5rem .75rem;
    font-weight: 600;
    color: var(--slate-mid, #64748b);
    border-bottom: 2px solid var(--border, #e2e8f0);
}
.hiw-table td {
    padding: .45rem .75rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
    color: var(--text-body, #374151);
}
.hiw-table tr:last-child td { border-bottom: none; }
.hiw-table td:last-child { font-weight: 600; color: var(--green-dark, #2e7d32); }

/* Zones Grid */
.hiw-zones-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: .75rem;
    margin-top: 1rem;
}
.hiw-zone {
    border-radius: 10px;
    padding: .9rem 1rem;
    display: flex;
    flex-direction: column;
    gap: .3rem;
    border: 1px solid transparent;
}
.hiw-zone--green  { background: #f0fdf4; border-color: #bbf7d0; }
.hiw-zone--yellow { background: #fefce8; border-color: #fde68a; }
.hiw-zone--red    { background: #fff1f2; border-color: #fecdd3; }
.hiw-zone strong { font-size: 1.05rem; }
.hiw-zone small  { font-size: .78rem; color: var(--slate-mid, #64748b); }

/* Footer note / sources */
.hiw-footer-note {
    margin-top: 2.5rem;
    padding: 1.25rem 1.5rem;
    background: var(--surface-alt, #f8fafc);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 12px;
    font-size: .88rem;
    color: var(--slate-mid, #64748b);
    line-height: 1.65;
}
.hiw-footer-note p + p { margin-top: .6rem; }
.hiw-sources { font-size: .8rem; opacity: .8; }

/* CTA */
.hiw-cta {
    margin-top: 3rem;
    text-align: center;
    padding: 2.5rem 1.5rem;
    background: var(--green-light, #e8f5e9);
    border-radius: 16px;
}
.hiw-cta h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: .5rem; }
.hiw-cta p  { color: var(--slate-mid, #64748b); margin-bottom: 1.25rem; }
</style>

<script>
function toggleCard(btn) {
    var expanded = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    var body = btn.nextElementSibling;
    body.classList.toggle('open', !expanded);
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
// Open the first card in each section by default
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.hiw-section').forEach(function (sec) {
        var firstBtn = sec.querySelector('.hiw-card-btn');
        if (firstBtn) toggleCard(firstBtn);
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
