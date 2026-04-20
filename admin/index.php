<?php
// ============================================================
// KCALS Admin – Dashboard
// ============================================================
require_once __DIR__ . '/includes/admin_auth.php';
requireAdmin();

$pageTitle   = 'Dashboard';
$activeAdmin = 'dashboard';

$db            = getDB();
$totalUsers    = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalPlans    = (int) $db->query('SELECT COUNT(*) FROM weekly_plans')->fetchColumn();
$totalCheckins = (int) $db->query('SELECT COUNT(*) FROM user_progress')->fetchColumn();
$totalRecipes  = (int) $db->query('SELECT COUNT(*) FROM recipes')->fetchColumn();
$latestUser    = $db->query(
    'SELECT full_name, email, created_at FROM users ORDER BY created_at DESC LIMIT 1'
)->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon--blue">
            <i data-lucide="users"></i>
        </div>
        <div>
            <div class="admin-stat-value"><?= number_format($totalUsers) ?></div>
            <div class="admin-stat-label">Total Users</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon--green">
            <i data-lucide="calendar-check"></i>
        </div>
        <div>
            <div class="admin-stat-value"><?= number_format($totalPlans) ?></div>
            <div class="admin-stat-label">Generated Plans</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon--purple">
            <i data-lucide="trending-up"></i>
        </div>
        <div>
            <div class="admin-stat-value"><?= number_format($totalCheckins) ?></div>
            <div class="admin-stat-label">Progress Check-ins</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon--orange">
            <i data-lucide="utensils"></i>
        </div>
        <div>
            <div class="admin-stat-value"><?= number_format($totalRecipes) ?></div>
            <div class="admin-stat-label">Recipes in DB</div>
        </div>
    </div>
</div>

<!-- Latest user -->
<?php if ($latestUser): ?>
<div class="admin-card" style="max-width:480px;">
    <div class="admin-card-header">
        <h2>Latest Registered User</h2>
    </div>
    <div class="admin-card-body" style="padding:1.1rem 1.5rem;">
        <p style="margin:0;font-weight:700;color:var(--slate);">
            <?= htmlspecialchars($latestUser['full_name']) ?>
        </p>
        <p style="margin:.2rem 0 0;font-size:.82rem;color:var(--slate-mid);">
            <?= htmlspecialchars($latestUser['email']) ?>
        </p>
        <p style="margin:.15rem 0 0;font-size:.75rem;color:var(--slate-light);">
            Joined <?= htmlspecialchars($latestUser['created_at']) ?>
        </p>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
