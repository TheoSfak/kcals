<?php
// ============================================================
// KCALS – Landing Page (index.php)
// ============================================================
$pageTitle = 'KCALS – Smart Nutrition & Wellness';
$activeNav = 'home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ==================== HERO ==================== -->
<section class="hero">
    <div class="container">
        <div class="hero-badge">
            <i data-lucide="zap" style="width:13px;height:13px;"></i>
            Powered by Mifflin-St Jeor Algorithm
        </div>
        <h1>Your Personal<br><span style="color:var(--green-dark)">Calorie & Wellness</span> Coach</h1>
        <p>Get a science-backed, personalised weekly meal plan that adapts to your stress, motivation and lifestyle — not just your weight.</p>
        <div class="hero-cta">
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-lg">
                <i data-lucide="rocket" style="width:18px;height:18px;"></i>
                Start Free Today
            </a>
            <a href="#how-it-works" class="btn btn-outline btn-lg">
                <i data-lucide="play-circle" style="width:18px;height:18px;"></i>
                How It Works
            </a>
        </div>
    </div>
</section>

<!-- ==================== STATS STRIP ==================== -->
<section style="background:var(--white); border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
    <div class="container" style="display:flex; justify-content:center; flex-wrap:wrap; gap:2.5rem; padding:1.5rem 1.25rem; text-align:center;">
        <div>
            <div style="font-size:2rem;font-weight:800;color:var(--green-dark);">7</div>
            <div style="font-size:0.78rem;color:var(--slate-mid);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Days Plan</div>
        </div>
        <div>
            <div style="font-size:2rem;font-weight:800;color:var(--green-dark);">3+</div>
            <div style="font-size:0.78rem;color:var(--slate-mid);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Zones</div>
        </div>
        <div>
            <div style="font-size:2rem;font-weight:800;color:var(--green-dark);">0</div>
            <div style="font-size:0.78rem;color:var(--slate-mid);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">AI Gimmicks</div>
        </div>
        <div>
            <div style="font-size:2rem;font-weight:800;color:var(--green-dark);">100%</div>
            <div style="font-size:0.78rem;color:var(--slate-mid);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Math-Based</div>
        </div>
    </div>
</section>

<!-- ==================== HOW IT WORKS ==================== -->
<section class="section" id="how-it-works">
    <div class="container">
        <div class="text-center mb-3">
            <h2>How KCALS Works</h2>
            <p>Three steps to a smarter relationship with food.</p>
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="user-plus"></i></div>
                <h3>1. Tell Us About You</h3>
                <p>Enter your height, weight, age, activity level and food preferences. Takes under 2 minutes.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="calculator"></i></div>
                <h3>2. We Calculate Your Needs</h3>
                <p>Using the Mifflin-St Jeor formula we compute your BMR, TDEE and a personalised calorie target adjusted for your stress and motivation zone.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="calendar-check"></i></div>
                <h3>3. Get Your Weekly Plan</h3>
                <p>A ready-to-follow 7-day meal plan with breakfast, lunch, dinner and snacks — all within your calorie budget.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i data-lucide="trending-up"></i></div>
                <h3>4. Track & Adapt</h3>
                <p>Log your weight and mood weekly. KCALS adjusts your deficit zone to keep you on track without burning out.</p>
            </div>
        </div>
    </div>
</section>

<!-- ==================== ZONE EXPLAINER ==================== -->
<section class="section" style="background:var(--white); border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
    <div class="container">
        <div class="text-center mb-3">
            <h2>Your Psychological Zone</h2>
            <p>Not everyone is ready for an aggressive deficit. KCALS respects your mental state.</p>
        </div>
        <div class="feature-grid">
            <div class="card" style="border-left:4px solid var(--green);">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;">
                    <span class="zone-badge green">🟢 Green Zone</span>
                </div>
                <h3 style="margin-bottom:.5rem;">Aggressive Mode</h3>
                <p>Low stress + high motivation. You're ready for a <strong>25% calorie deficit</strong> — maximum fat loss, sustainable pace.</p>
            </div>
            <div class="card" style="border-left:4px solid var(--yellow);">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;">
                    <span class="zone-badge yellow">🟡 Yellow Zone</span>
                </div>
                <h3 style="margin-bottom:.5rem;">Balanced Mode</h3>
                <p>Middle ground. A <strong>15% calorie deficit</strong> — steady progress without overwhelming your routine.</p>
            </div>
            <div class="card" style="border-left:4px solid var(--red);">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;">
                    <span class="zone-badge red">🔴 Red Zone</span>
                </div>
                <h3 style="margin-bottom:.5rem;">Sustainable Mode</h3>
                <p>High stress or low motivation. Just an <strong>8% deficit</strong> — build the habit first, results follow naturally.</p>
            </div>
        </div>
    </div>
</section>

<!-- ==================== CTA BOTTOM ==================== -->
<section class="section text-center">
    <div class="container-sm">
        <h2>Ready to start?</h2>
        <p class="mt-1 mb-3">Create your free account and get your first personalised weekly plan in minutes.</p>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-lg">
            <i data-lucide="arrow-right" style="width:18px;height:18px;"></i>
            Create Free Account
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
