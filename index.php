<?php
// ============================================================
// KCALS – Landing Page (index.php)
// ============================================================
require_once __DIR__ . '/includes/auth.php';
$pageTitle = __('home_title');
$activeNav = 'home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ==================== HERO ==================== -->
<section class="hero">
    <div class="container">
        <div class="hero-badge">
            <i data-lucide="zap" style="width:13px;height:13px;"></i>
            <?= __('hero_badge') ?>
        </div>
        <h1><?= __('hero_h1_line1') ?><br><span style="color:var(--green-dark)"><?= __('hero_h1_line2') ?></span></h1>
        <p><?= __('hero_sub') ?></p>
        <div class="hero-cta">
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-lg">
                <i data-lucide="rocket" style="width:18px;height:18px;"></i>
                <?= __('hero_btn_start') ?>
            </a>
            <a href="#how-it-works" class="btn btn-outline btn-lg">
                <i data-lucide="play-circle" style="width:18px;height:18px;"></i>
                <?= __('hero_btn_how') ?>
            </a>
        </div>
    </div>
</section>

<!-- ==================== STATS STRIP ==================== -->
<section style="background:var(--white); border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
    <div class="container" style="display:flex; justify-content:center; flex-wrap:wrap; gap:2.5rem; padding:1.5rem 1.25rem; text-align:center;">
        <div>
            <div style="font-size:2rem;font-weight:800;color:var(--green-dark);">7</div>
            <div style="font-size:0.78rem;color:var(--slate-mid);font-weight:600;text-transform:uppercase;letter-spacing:.5px;"><?= __('stat_days_plan') ?></div>
        </div>
        <div>
            <div style="font-size:2rem;font-weight:800;color:var(--green-dark);">3+</div>
            <div style="font-size:0.78rem;color:var(--slate-mid);font-weight:600;text-transform:uppercase;letter-spacing:.5px;"><?= __('stat_zones') ?></div>
        </div>
        <div>
            <div style="font-size:2rem;font-weight:800;color:var(--green-dark);">0</div>
            <div style="font-size:0.78rem;color:var(--slate-mid);font-weight:600;text-transform:uppercase;letter-spacing:.5px;"><?= __('stat_ai_gimmicks') ?></div>
        </div>
        <div>
            <div style="font-size:2rem;font-weight:800;color:var(--green-dark);">100%</div>
            <div style="font-size:0.78rem;color:var(--slate-mid);font-weight:600;text-transform:uppercase;letter-spacing:.5px;"><?= __('stat_math_based') ?></div>
        </div>
    </div>
</section>

<!-- ==================== HOW IT WORKS ==================== -->
<section class="section" id="how-it-works">
    <div class="container">
        <div class="text-center mb-3">
            <h2><?= __('how_title') ?></h2>
            <p><?= __('how_sub') ?></p>
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="user-plus"></i></div>
                <h3><?= __('how_1_title') ?></h3>
                <p><?= __('how_1_desc') ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="calculator"></i></div>
                <h3><?= __('how_2_title') ?></h3>
                <p><?= __('how_2_desc') ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="calendar-check"></i></div>
                <h3><?= __('how_3_title') ?></h3>
                <p><?= __('how_3_desc') ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="trending-up"></i></div>
                <h3><?= __('how_4_title') ?></h3>
                <p><?= __('how_4_desc') ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ==================== ZONE EXPLAINER ==================== -->
<section class="section" style="background:var(--white); border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
    <div class="container">
        <div class="text-center mb-3">
            <h2><?= __('zone_title') ?></h2>
            <p><?= __('zone_sub') ?></p>
        </div>
        <div class="feature-grid">
            <div class="card" style="border-left:4px solid var(--green);">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;">
                    <span class="zone-badge green"><?= __('zone_green_badge') ?></span>
                </div>
                <h3 style="margin-bottom:.5rem;"><?= __('zone_green_title') ?></h3>
                <p><?= __('zone_green_desc') ?></p>
            </div>
            <div class="card" style="border-left:4px solid var(--yellow);">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;">
                    <span class="zone-badge yellow"><?= __('zone_yellow_badge') ?></span>
                </div>
                <h3 style="margin-bottom:.5rem;"><?= __('zone_yellow_title') ?></h3>
                <p><?= __('zone_yellow_desc') ?></p>
            </div>
            <div class="card" style="border-left:4px solid var(--red);">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;">
                    <span class="zone-badge red"><?= __('zone_red_badge') ?></span>
                </div>
                <h3 style="margin-bottom:.5rem;"><?= __('zone_red_title') ?></h3>
                <p><?= __('zone_red_desc') ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ==================== CTA BOTTOM ==================== -->
<section class="section text-center">
    <div class="container-sm">
        <h2><?= __('cta_title') ?></h2>
        <p class="mt-1 mb-3"><?= __('cta_sub') ?></p>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-lg">
            <i data-lucide="arrow-right" style="width:18px;height:18px;"></i>
            <?= __('cta_btn') ?>
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

