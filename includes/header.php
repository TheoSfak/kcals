<?php
// ============================================================
// KCALS – HTML Header Include
// Usage: include this at the top of every page
// Variables expected:
//   $pageTitle   – string  (page <title>)
//   $activeNav   – string  (nav link key: 'home','dashboard','plan','progress','tips')
// ============================================================
require_once __DIR__ . '/auth.php';

// Load general settings (site name, tagline)
$_genSettings = function_exists('getSettings')
    ? getSettings(['general_site_name','general_tagline'])
    : [];
$_siteName = trim($_genSettings['general_site_name'] ?? '') ?: 'KCALS';
$_tagline  = trim($_genSettings['general_tagline']  ?? '') ?: 'Smart Nutrition & Wellness';

$pageTitle  = $pageTitle ?? ($_siteName . ' – ' . $_tagline);
$activeNav  = $activeNav ?? '';
$isLoggedIn = isLoggedIn();
$_lang      = $GLOBALS['_kcals_lang'] ?? 'en';
$_back      = urlencode($_SERVER['REQUEST_URI'] ?? '/');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($_siteName . ' – ' . $_tagline) ?> | Personalised weekly nutrition plans, progress tracking and wellness tips.">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Lucide Icons (CDN) -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <!-- Chart.js (dashboard only) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <!-- App CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <!-- ===== Appearance Overrides (admin-controlled) ===== -->
    <?php
    $appKeys = ['appearance_accent','appearance_accent_dark','appearance_bg',
                'appearance_font_family','appearance_font_size','appearance_border_radius'];
    $appSettings = function_exists('getSettings') ? getSettings($appKeys) : [];
    $accent      = preg_match('/^#[0-9a-fA-F]{3,6}$/', $appSettings['appearance_accent']       ?? '') ? $appSettings['appearance_accent']      : null;
    $accentDark  = preg_match('/^#[0-9a-fA-F]{3,6}$/', $appSettings['appearance_accent_dark']  ?? '') ? $appSettings['appearance_accent_dark']  : null;
    $bgCol       = preg_match('/^#[0-9a-fA-F]{3,6}$/', $appSettings['appearance_bg']           ?? '') ? $appSettings['appearance_bg']           : null;
    $fontFam     = $appSettings['appearance_font_family'] ?? '';
    $fontSize    = (int) ($appSettings['appearance_font_size']    ?? 16);
    $radius      = (int) ($appSettings['appearance_border_radius'] ?? 14);
    $allowedFonts = ['Inter','Roboto','Lato','Poppins','Open Sans','Nunito','Source Sans Pro'];
    $fontFam     = in_array($fontFam, $allowedFonts, true) ? $fontFam : null;
    $fontSize    = ($fontSize >= 12 && $fontSize <= 22) ? $fontSize : null;
    $radius      = ($radius  >= 0  && $radius  <= 30)  ? $radius  : null;
    $hasOverrides = $accent || $accentDark || $bgCol || $fontFam || $fontSize !== null || $radius !== null;
    if ($hasOverrides):
        // Load Google Font if not Inter
        if ($fontFam && $fontFam !== 'Inter'):
            $gfSlug = str_replace(' ', '+', $fontFam);
    ?>
    <link href="https://fonts.googleapis.com/css2?family=<?= htmlspecialchars($gfSlug) ?>:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php endif; ?>
    <style>
    :root {
        <?= $accent     ? "--green:      {$accent};".PHP_EOL        : '' ?>
        <?= $accentDark ? "--green-dark: {$accentDark};".PHP_EOL    : '' ?>
        <?= $bgCol      ? "--bg:         {$bgCol};".PHP_EOL         : '' ?>
        <?= $radius !== null ? "--radius: {$radius}px;".PHP_EOL     : '' ?>
    }
    <?= $fontFam   ? "body, button, input, select, textarea { font-family: '{$fontFam}', sans-serif; }" : '' ?>
    <?= $fontSize !== null ? "html { font-size: {$fontSize}px; }" : '' ?>
    </style>
    <?php endif; ?>
</head>
<body>

<!-- ==================== DISCLAIMER MODAL ==================== -->
<div id="disclaimer-overlay" class="disclaimer-overlay" role="dialog" aria-modal="true" aria-labelledby="disclaimer-title">
    <div class="disclaimer-modal">
        <div class="disclaimer-icon" aria-hidden="true">
            <i data-lucide="shield-alert"></i>
        </div>
        <h2 id="disclaimer-title"><?= __('disclaimer_title') ?></h2>
        <p><?= __('disclaimer_body') ?></p>
        <button id="disclaimer-accept" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
            <i data-lucide="check-circle" style="width:18px;height:18px;"></i>
            <?= __('disclaimer_accept') ?>
        </button>
    </div>
</div>
<script>
(function(){
    if(!localStorage.getItem('kcals_disclaimer_accepted')){
        document.getElementById('disclaimer-overlay').classList.add('visible');
    }
    document.getElementById('disclaimer-accept').addEventListener('click', function(){
        localStorage.setItem('kcals_disclaimer_accepted','1');
        var ov = document.getElementById('disclaimer-overlay');
        ov.classList.remove('visible');
        ov.classList.add('hiding');
        setTimeout(function(){ ov.style.display='none'; }, 380);
    });
})();
</script>

<nav class="navbar">
    <div class="navbar-inner">
        <a class="navbar-brand" href="<?= BASE_URL ?>/index.php"><?= htmlspecialchars($_siteName) ?><span>.</span></a>

        <ul class="navbar-nav">
            <?php if ($isLoggedIn): ?>
                <li><a href="<?= BASE_URL ?>/dashboard.php"  class="<?= $activeNav==='dashboard' ? 'active':'' ?>"><i data-lucide="layout-dashboard"></i><?= __('nav_dashboard') ?></a></li>
                <li><a href="<?= BASE_URL ?>/plan.php"        class="<?= $activeNav==='plan' ? 'active':'' ?>"><i data-lucide="calendar"></i><?= __('nav_plan') ?></a></li>
                <li><a href="<?= BASE_URL ?>/progress.php"    class="<?= $activeNav==='progress' ? 'active':'' ?>"><i data-lucide="trending-up"></i><?= __('nav_progress') ?></a></li>
                <li><a href="<?= BASE_URL ?>/tips.php"        class="<?= $activeNav==='tips' ? 'active':'' ?>"><i data-lucide="lightbulb"></i><?= __('nav_tips') ?></a></li>
                <li><a href="<?= BASE_URL ?>/settings.php"    class="<?= $activeNav==='preferences' ? 'active':'' ?>"><i data-lucide="sliders-horizontal"></i><?= __('nav_preferences') ?></a></li>
                <li><a href="<?= BASE_URL ?>/how_it_works.php" class="<?= $activeNav==='how_it_works' ? 'active':'' ?>"><i data-lucide="book-open"></i><?= __('nav_how_it_works') ?></a></li>
                <li><a href="<?= BASE_URL ?>/share.php" class="<?= $activeNav==='share' ? 'active':'' ?>"><i data-lucide="share-2"></i><?= __('nav_share') ?></a></li>
                <li><a href="<?= BASE_URL ?>/achievements.php" class="<?= $activeNav==='achievements' ? 'active':'' ?>"><i data-lucide="trophy"></i><?= __('nav_achievements') ?></a></li>
                <li><a href="<?= BASE_URL ?>/logout.php"      class="btn-nav"><i data-lucide="log-out"></i><?= __('nav_logout') ?></a></li>
            <?php else: ?>
                <li><a href="<?= BASE_URL ?>/index.php"       class="<?= $activeNav==='home' ? 'active':'' ?>"><?= __('nav_home') ?></a></li>
                <li><a href="<?= BASE_URL ?>/how_it_works.php" class="<?= $activeNav==='how_it_works' ? 'active':'' ?>"><?= __('nav_how_it_works') ?></a></li>
                <li><a href="<?= BASE_URL ?>/login.php"        class="<?= $activeNav==='login' ? 'active':'' ?>"><?= __('nav_login') ?></a></li>
                <li><a href="<?= BASE_URL ?>/register.php"     class="btn-nav"><?= __('nav_get_started') ?></a></li>
            <?php endif; ?>
        </ul>

        <!-- Language Switcher -->
        <div class="lang-switcher">
            <a href="<?= BASE_URL ?>/lang/set.php?lang=en&back=<?= $_back ?>"
               class="lang-btn<?= $_lang === 'en' ? ' active' : '' ?>" title="English">
                🇬🇧 EN
            </a>
            <a href="<?= BASE_URL ?>/lang/set.php?lang=el&back=<?= $_back ?>"
               class="lang-btn<?= $_lang === 'el' ? ' active' : '' ?>" title="Ελληνικά">
                🇬🇷 EL
            </a>
        </div>
    </div>
</nav>

