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
    <div class="hero-inner">
        <div class="hero-text">
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
        <div class="hero-visual" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 440 420" fill="none" class="hero-svg">
                <!-- Decorative blobs -->
                <circle cx="220" cy="210" r="180" fill="#EAFAF1" opacity="0.55"/>
                <circle cx="355" cy="75" r="60" fill="#C8F7D8" opacity="0.45"/>
                <circle cx="75" cy="340" r="50" fill="#D5F5E3" opacity="0.38"/>
                <!-- Main app card -->
                <rect x="85" y="58" width="272" height="308" rx="24" fill="white" style="filter:drop-shadow(0 12px 40px rgba(44,62,80,0.13))"/>
                <!-- Card header strip -->
                <rect x="85" y="58" width="272" height="50" rx="22" fill="#EAFAF1"/>
                <rect x="85" y="88" width="272" height="20" fill="#EAFAF1"/>
                <text x="221" y="90" text-anchor="middle" font-family="system-ui,-apple-system,sans-serif" font-size="13" font-weight="700" fill="#27AE60">Today's Calories</text>
                <!-- Donut ring track -->
                <circle cx="221" cy="197" r="72" stroke="#EAFAF1" stroke-width="13" fill="none"/>
                <!-- Donut ring fill ~75% (339 of 452) -->
                <circle cx="221" cy="197" r="72" stroke="#2ECC71" stroke-width="13" fill="none" stroke-dasharray="339 113" stroke-linecap="round" transform="rotate(-90 221 197)"/>
                <!-- Center labels -->
                <text x="221" y="189" text-anchor="middle" font-family="system-ui,-apple-system,sans-serif" font-size="29" font-weight="800" fill="#2C3E50">1,850</text>
                <text x="221" y="210" text-anchor="middle" font-family="system-ui,-apple-system,sans-serif" font-size="11" font-weight="600" fill="#7F8C8D">kcal target</text>
                <!-- Separator -->
                <line x1="110" y1="282" x2="332" y2="282" stroke="#F0F3F4" stroke-width="1.5"/>
                <!-- Macro bars: Protein -->
                <text x="110" y="300" font-family="system-ui,-apple-system,sans-serif" font-size="10" font-weight="600" fill="#7F8C8D">Protein</text>
                <rect x="110" y="306" width="64" height="6" rx="3" fill="#F0F3F4"/>
                <rect x="110" y="306" width="46" height="6" rx="3" fill="#E74C3C"/>
                <!-- Macro bars: Carbs -->
                <text x="187" y="300" font-family="system-ui,-apple-system,sans-serif" font-size="10" font-weight="600" fill="#7F8C8D">Carbs</text>
                <rect x="187" y="306" width="64" height="6" rx="3" fill="#F0F3F4"/>
                <rect x="187" y="306" width="41" height="6" rx="3" fill="#F39C12"/>
                <!-- Macro bars: Fat -->
                <text x="264" y="300" font-family="system-ui,-apple-system,sans-serif" font-size="10" font-weight="600" fill="#7F8C8D">Fat</text>
                <rect x="264" y="306" width="64" height="6" rx="3" fill="#F0F3F4"/>
                <rect x="264" y="306" width="29" height="6" rx="3" fill="#9B59B6"/>
                <!-- Zone bar -->
                <rect x="110" y="328" width="222" height="14" rx="7" fill="#EAFAF1"/>
                <rect x="110" y="328" width="160" height="14" rx="7" fill="#2ECC71" opacity="0.72"/>
                <text x="221" y="339" text-anchor="middle" font-family="system-ui,-apple-system,sans-serif" font-size="8" font-weight="700" fill="#186A3B">GREEN ZONE ✓</text>
                <!-- Floating card: Zone -->
                <rect x="306" y="30" width="118" height="58" rx="14" fill="white" style="filter:drop-shadow(0 4px 16px rgba(44,62,80,0.11))"/>
                <rect x="306" y="30" width="5" height="58" rx="3" fill="#2ECC71"/>
                <text x="322" y="55" font-family="system-ui,-apple-system,sans-serif" font-size="10" font-weight="600" fill="#7F8C8D">Zone</text>
                <text x="322" y="73" font-family="system-ui,-apple-system,sans-serif" font-size="15" font-weight="800" fill="#27AE60">Green ✓</text>
                <!-- Floating card: Streak -->
                <rect x="17" y="255" width="98" height="60" rx="14" fill="white" style="filter:drop-shadow(0 4px 16px rgba(44,62,80,0.11))"/>
                <text x="35" y="280" font-family="system-ui,-apple-system,sans-serif" font-size="19">🔥</text>
                <text x="58" y="278" font-family="system-ui,-apple-system,sans-serif" font-size="16" font-weight="800" fill="#2C3E50">7</text>
                <text x="35" y="300" font-family="system-ui,-apple-system,sans-serif" font-size="9" font-weight="600" fill="#7F8C8D">day streak</text>
                <!-- Decorative dots -->
                <circle cx="58" cy="118" r="8" fill="#2ECC71" opacity="0.22"/>
                <circle cx="388" cy="320" r="13" fill="#2ECC71" opacity="0.18"/>
                <circle cx="393" cy="172" r="6" fill="#27AE60" opacity="0.28"/>
                <circle cx="28" cy="188" r="4.5" fill="#2ECC71" opacity="0.3"/>
            </svg>
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

