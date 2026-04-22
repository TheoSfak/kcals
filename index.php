<?php
// ============================================================
// KCALS – Landing Page (index.php)
// ============================================================
require_once __DIR__ . '/includes/auth.php';
$pageTitle = __('home_title');
$activeNav = 'home';

// ---- Randomly pick one of 3 hero variants on each visit ----
$heroes = [
    [
        'badge'   => __('hero_badge'),
        'line1'   => __('hero_h1_line1'),
        'line2'   => __('hero_h1_line2'),
        'sub'     => __('hero_sub'),
        'trust'   => __('lp_trust_badge'),
        'img_tall'=> ['url' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=480&q=80&fit=crop&auto=format', 'alt' => 'Man training at the gym'],
        'img_sm1' => ['url' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=360&q=80&fit=crop&auto=format', 'alt' => 'Woman running outdoors'],
        'img_sm2' => ['url' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=360&q=80&fit=crop&auto=format', 'alt' => 'Healthy colourful meal'],
        'float_tl'=> '<span class="mf-label">Zone</span><span class="mf-value green">🟢 Green ✓</span>',
        'float_br'=> '<span style="font-size:1.5rem;">🔥</span><div><div class="mf-value" style="font-size:1.2rem;">7</div><div class="mf-label">day streak</div></div>',
    ],
    [
        'badge'   => __('hero_badge_2'),
        'line1'   => __('hero_h1_line1_2'),
        'line2'   => __('hero_h1_line2_2'),
        'sub'     => __('hero_sub_2'),
        'trust'   => __('lp_trust_badge_2'),
        'img_tall'=> ['url' => 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?w=480&q=80&fit=crop&auto=format', 'alt' => 'Healthy nutrition bowl'],
        'img_sm1' => ['url' => 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?w=360&q=80&fit=crop&auto=format', 'alt' => 'Yoga and wellness'],
        'img_sm2' => ['url' => 'https://images.unsplash.com/photo-1547592180-85f173990554?w=360&q=80&fit=crop&auto=format', 'alt' => 'Meal planning'],
        'float_tl'=> '<span class="mf-label">Macros</span><span class="mf-value green">🥗 All set ✓</span>',
        'float_br'=> '<span style="font-size:1.5rem;">⚡</span><div><div class="mf-value" style="font-size:1.1rem;">High</div><div class="mf-label">energy</div></div>',
    ],
    [
        'badge'   => __('hero_badge_3'),
        'line1'   => __('hero_h1_line1_3'),
        'line2'   => __('hero_h1_line2_3'),
        'sub'     => __('hero_sub_3'),
        'trust'   => __('lp_trust_badge_3'),
        'img_tall'=> ['url' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=480&q=80&fit=crop&auto=format', 'alt' => 'Woman running outdoors'],
        'img_sm1' => ['url' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=360&q=80&fit=crop&auto=format', 'alt' => 'Man at the gym'],
        'img_sm2' => ['url' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=360&q=80&fit=crop&auto=format', 'alt' => 'Healthy colourful meal'],
        'float_tl'=> '<span class="mf-label">Plan</span><span class="mf-value green">📊 Ready ✓</span>',
        'float_br'=> '<span style="font-size:1.5rem;">🎯</span><div><div class="mf-value" style="font-size:1.1rem;">On track</div><div class="mf-label">goal</div></div>',
    ],
];
$h = $heroes[array_rand($heroes)];

require_once __DIR__ . '/includes/header.php';
?>

<!-- ==================== HERO ==================== -->
<section class="hero">
    <div class="hero-inner">
        <div class="hero-text">
            <div class="hero-badge"><?= $h['badge'] ?></div>
            <h1 style="font-size:clamp(2rem,5vw,3rem); line-height:1.1; font-weight:900; margin-bottom:1rem;">
                <?= $h['line1'] ?><br>
                <span style="color:var(--green-dark)"><?= $h['line2'] ?></span>
            </h1>
            <p style="font-size:1.1rem; color:var(--slate-mid); line-height:1.7; margin-bottom:.5rem;">
                <?= $h['sub'] ?>
            </p>
            <p class="lp-trust-badge"><?= $h['trust'] ?></p>
            <div class="hero-cta" style="margin-top:1.5rem;">
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-lg">
                    <i data-lucide="rocket" style="width:18px;height:18px;"></i>
                    <?= __('hero_btn_start') ?>
                </a>
                <a href="<?= BASE_URL ?>/how_it_works.php" class="btn btn-outline btn-lg">
                    <i data-lucide="play-circle" style="width:18px;height:18px;"></i>
                    <?= __('hero_btn_how') ?>
                </a>
            </div>
        </div>

        <!-- Photo Mosaic -->
        <div class="hero-mosaic" aria-hidden="true">
            <div class="mosaic-grid">
                <div class="mosaic-tall">
                    <img src="<?= $h['img_tall']['url'] ?>" alt="<?= htmlspecialchars($h['img_tall']['alt']) ?>" loading="lazy">
                </div>
                <div class="mosaic-col">
                    <div class="mosaic-sm">
                        <img src="<?= $h['img_sm1']['url'] ?>" alt="<?= htmlspecialchars($h['img_sm1']['alt']) ?>" loading="lazy">
                    </div>
                    <div class="mosaic-sm">
                        <img src="<?= $h['img_sm2']['url'] ?>" alt="<?= htmlspecialchars($h['img_sm2']['alt']) ?>" loading="lazy">
                    </div>
                </div>
            </div>
            <!-- Floating stat cards -->
            <div class="mosaic-float mosaic-float-tl">
                <?= $h['float_tl'] ?>
            </div>
            <div class="mosaic-float mosaic-float-br">
                <?= $h['float_br'] ?>
            </div>
        </div>
    </div>
</section>

<!-- ==================== STATS STRIP ==================== -->
<section class="stats-strip">
    <div class="stats-inner">
        <div class="stat-item">
            <div class="stat-num">7</div>
            <div class="stat-label"><?= __('stat_days_plan') ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-num">3+</div>
            <div class="stat-label"><?= __('stat_zones') ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-num">0</div>
            <div class="stat-label"><?= __('stat_ai_gimmicks') ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-num">100%</div>
            <div class="stat-label"><?= __('stat_math_based') ?></div>
        </div>
    </div>
</section>

<!-- ==================== WHY NUTRITION ==================== -->
<section class="section lp-nutrition-section">
    <div class="container">
        <div class="lp-two-col">
            <!-- Image side -->
            <div class="lp-img-wrap lp-img-nutrition">
                <img src="https://images.unsplash.com/photo-1490645935967-10de6ba17061?w=640&q=80&fit=crop&auto=format"
                     alt="Healthy nutrition bowl" loading="lazy">
            </div>
            <!-- Text side -->
            <div class="lp-text-side">
                <span class="lp-section-badge"><?= __('lp_nutrition_badge') ?></span>
                <h2><?= __('lp_nutrition_h') ?></h2>
                <p><?= __('lp_nutrition_p') ?></p>
                <div class="lp-facts">
                    <div class="lp-fact">
                        <div class="lp-fact-num"><?= __('lp_fact1_num') ?></div>
                        <div class="lp-fact-lbl"><?= __('lp_fact1_lbl') ?></div>
                    </div>
                    <div class="lp-fact">
                        <div class="lp-fact-num"><?= __('lp_fact2_num') ?></div>
                        <div class="lp-fact-lbl"><?= __('lp_fact2_lbl') ?></div>
                    </div>
                    <div class="lp-fact">
                        <div class="lp-fact-num"><?= __('lp_fact3_num') ?></div>
                        <div class="lp-fact-lbl"><?= __('lp_fact3_lbl') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== FEATURE HIGHLIGHTS ==================== -->
<section class="section" style="background:var(--white); border-top:1px solid var(--border);">
    <div class="container">

        <!-- Feature 1: text left, image right -->
        <div class="lp-two-col lp-feat-row" style="margin-bottom:4rem;">
            <div class="lp-text-side">
                <span class="lp-section-badge"><?= __('lp_feat1_badge') ?></span>
                <h2><?= __('lp_feat1_h') ?></h2>
                <p><?= __('lp_feat1_p') ?></p>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary" style="margin-top:1rem;">
                    <i data-lucide="arrow-right" style="width:15px;height:15px;"></i>
                    <?= __('hero_btn_start') ?>
                </a>
            </div>
            <div class="lp-img-wrap">
                <img src="https://images.unsplash.com/photo-1547592180-85f173990554?w=640&q=80&fit=crop&auto=format"
                     alt="Healthy meal planning" loading="lazy">
            </div>
        </div>

        <!-- Feature 2: image left, text right -->
        <div class="lp-two-col lp-feat-row lp-feat-reverse" style="margin-bottom:4rem;">
            <div class="lp-img-wrap">
                <img src="https://images.unsplash.com/photo-1506126613408-eca07ce68773?w=640&q=80&fit=crop&auto=format"
                     alt="Mindfulness and wellness" loading="lazy">
            </div>
            <div class="lp-text-side">
                <span class="lp-section-badge"><?= __('lp_feat2_badge') ?></span>
                <h2><?= __('lp_feat2_h') ?></h2>
                <p><?= __('lp_feat2_p') ?></p>
            </div>
        </div>

        <!-- Feature 3: text left, image right -->
        <div class="lp-two-col lp-feat-row">
            <div class="lp-text-side">
                <span class="lp-section-badge"><?= __('lp_feat3_badge') ?></span>
                <h2><?= __('lp_feat3_h') ?></h2>
                <p><?= __('lp_feat3_p') ?></p>
            </div>
            <div class="lp-img-wrap">
                <img src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=640&q=80&fit=crop&auto=format"
                     alt="Man pushing through workout" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- ==================== TESTIMONIALS ==================== -->
<section class="section lp-testi-section">
    <div class="container">
        <div class="text-center mb-3">
            <h2><?= __('lp_testi_h') ?></h2>
            <p style="color:var(--slate-mid);"><?= __('lp_testi_sub') ?></p>
        </div>
        <div class="lp-testi-grid">
            <div class="lp-testi-card">
                <div class="lp-testi-avatar"><?= mb_substr(__('lp_t1_name'), 0, 1) ?></div>
                <p class="lp-testi-quote"><?= __('lp_t1_quote') ?></p>
                <div class="lp-testi-foot">
                    <div>
                        <strong><?= __('lp_t1_name') ?></strong>
                        <div class="lp-testi-role"><?= __('lp_t1_role') ?></div>
                    </div>
                    <span class="lp-result-badge"><?= __('lp_t1_result') ?></span>
                </div>
            </div>
            <div class="lp-testi-card lp-testi-featured">
                <div class="lp-testi-avatar"><?= mb_substr(__('lp_t2_name'), 0, 1) ?></div>
                <p class="lp-testi-quote"><?= __('lp_t2_quote') ?></p>
                <div class="lp-testi-foot">
                    <div>
                        <strong><?= __('lp_t2_name') ?></strong>
                        <div class="lp-testi-role"><?= __('lp_t2_role') ?></div>
                    </div>
                    <span class="lp-result-badge"><?= __('lp_t2_result') ?></span>
                </div>
            </div>
            <div class="lp-testi-card">
                <div class="lp-testi-avatar"><?= mb_substr(__('lp_t3_name'), 0, 1) ?></div>
                <p class="lp-testi-quote"><?= __('lp_t3_quote') ?></p>
                <div class="lp-testi-foot">
                    <div>
                        <strong><?= __('lp_t3_name') ?></strong>
                        <div class="lp-testi-role"><?= __('lp_t3_role') ?></div>
                    </div>
                    <span class="lp-result-badge"><?= __('lp_t3_result') ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== HOW IT WORKS ==================== -->
<section class="section" id="how-it-works" style="border-top:1px solid var(--border);">
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
<section class="lp-cta-section">
    <div class="container-sm text-center">
        <div class="lp-cta-inner">
            <h2 style="font-size:clamp(1.6rem,4vw,2.4rem); font-weight:900; margin-bottom:1rem;"><?= __('cta_title') ?></h2>
            <p style="color:rgba(255,255,255,0.82); font-size:1.05rem; margin-bottom:2rem;"><?= __('cta_sub') ?></p>
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-lg" style="background:#fff; color:var(--green-dark); font-weight:700;">
                <i data-lucide="rocket" style="width:18px;height:18px;"></i>
                <?= __('cta_btn') ?>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

