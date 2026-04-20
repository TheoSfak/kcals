<?php
// ============================================================
// KCALS – Health Tips Page
// ============================================================
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$db     = getDB();
$userId = (int) $_SESSION['user_id'];

$category = $_GET['category'] ?? '';
$allowed  = ['nutrition','fitness','beauty','mindset','sleep',''];
if (!in_array($category, $allowed)) $category = '';

if ($category) {
    $stmt = $db->prepare('SELECT * FROM health_tips WHERE category = ? ORDER BY id');
    $stmt->execute([$category]);
} else {
    $stmt = $db->query('SELECT * FROM health_tips ORDER BY category, id');
}
$tips = $stmt->fetchAll();

$categories = ['nutrition','fitness','beauty','mindset','sleep'];
$icons = ['nutrition'=>'apple','fitness'=>'dumbbell','beauty'=>'sparkles','mindset'=>'brain','sleep'=>'moon'];

$pageTitle = __('tips_title');
$activeNav = 'tips';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:1100px; margin:2rem auto; padding:0 1.25rem;">
    <div style="margin-bottom:1.75rem;">
        <h1 style="font-size:1.5rem; margin-bottom:.5rem;"><?= __('tips_h1') ?></h1>
        <p class="text-small" style="color:var(--slate-mid);"><?= __('tips_sub') ?></p>
    </div>

    <!-- Category Filter -->
    <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.5rem;">
        <a href="<?= BASE_URL ?>/tips.php" class="btn btn-sm <?= $category==='' ? 'btn-primary' : 'btn-secondary' ?>"><?= __('tips_all') ?></a>
        <?php foreach ($categories as $cat): ?>
        <a href="<?= BASE_URL ?>/tips.php?category=<?= $cat ?>" class="btn btn-sm <?= $category===$cat ? 'btn-primary' : 'btn-secondary' ?>">
            <i data-lucide="<?= $icons[$cat] ?>" style="width:13px;height:13px;"></i>
            <?= htmlspecialchars(__('cat_' . $cat)) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Tips Grid -->
    <?php if ($tips): ?>
    <div class="tips-grid">
        <?php foreach ($tips as $tip): ?>
        <div class="tip-card">
            <div style="margin-bottom:.5rem;">
                <i data-lucide="<?= htmlspecialchars($tip['icon']) ?>" style="width:24px;height:24px; color:var(--green-dark);"></i>
            </div>
            <div style="font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--green-dark); margin-bottom:.35rem;">
                <?= htmlspecialchars($tip['category']) ?>
            </div>
            <p class="tip-text"><?= htmlspecialchars($tip['tip_text']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color:var(--slate-mid);"><?= __('tips_none') ?></p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
