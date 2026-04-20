<?php
// ============================================================
// KCALS – Shopping List Generator
// Aggregates all ingredients from the current weekly plan
// ============================================================
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$db     = getDB();
$userId = (int) $_SESSION['user_id'];

// Load latest plan
$stmt = $db->prepare('SELECT * FROM weekly_plans WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
$stmt->execute([$userId]);
$plan = $stmt->fetch();

$shoppingList = [];

if ($plan) {
    $planData = json_decode($plan['plan_data_json'], true);
    $recipeIds = [];
    foreach ($planData as $meals) {
        foreach ($meals as $meal) {
            $recipeIds[] = (int) $meal['id'];
        }
    }
    $recipeIds = array_unique($recipeIds);

    if (!empty($recipeIds)) {
        $placeholders = implode(',', array_fill(0, count($recipeIds), '?'));
        $recipeStmt   = $db->prepare("SELECT id, title, ingredients_json, category FROM recipes WHERE id IN ($placeholders)");
        $recipeStmt->execute($recipeIds);
        $recipes = $recipeStmt->fetchAll();

        // Aggregate ingredients
        $rawItems = [];
        foreach ($recipes as $recipe) {
            $ingredients = json_decode($recipe['ingredients_json'], true) ?? [];
            foreach ($ingredients as $item) {
                $rawItems[] = trim($item);
            }
        }

        // Deduplicate (basic merge by keyword)
        $merged = [];
        foreach ($rawItems as $item) {
            // Extract the ingredient name (strip quantities)
            $keyParts = preg_split('/\s+/', $item);
            // Use last significant word or full string as key
            $key = strtolower(implode(' ', array_slice($keyParts, -1)));
            if (!isset($merged[$key])) {
                $merged[$key] = $item;
            } else {
                // Keep the more detailed entry
                if (strlen($item) > strlen($merged[$key])) {
                    $merged[$key] = $item;
                }
            }
        }

        // Group by first letter for display
        $grouped = [];
        foreach ($merged as $item) {
            // Strip leading quantities (numbers + units) for grouping key
            $clean  = preg_replace('/^\d+\s*(g|ml|kg|l|tbsp|tsp|cup|pcs?|slice|cloves?|pinch)?\s*/i', '', $item);
            $letter = strtoupper(substr(trim($clean), 0, 1));
            $grouped[$letter][] = $item;
        }
        ksort($grouped);
        $shoppingList = $grouped;
    }
}

$pageTitle = __('shopping_title');
$activeNav = 'plan';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:820px; margin:2rem auto; padding:0 1.25rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem;">
        <div>
            <h1 style="font-size:1.5rem; margin-bottom:.25rem;"><?= __('shopping_h1') ?></h1>
            <p class="text-small" style="color:var(--slate-mid);"><?= __('shopping_sub') ?></p>
        </div>
        <div style="display:flex; gap:.75rem;">
            <a href="<?= BASE_URL ?>/plan.php" class="btn btn-outline btn-sm"><?= __('shopping_back') ?></a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i data-lucide="printer" style="width:14px;height:14px;"></i>
                <?= __('shopping_print') ?>
            </button>
        </div>
    </div>

    <?php if (!$plan): ?>
    <div class="card" style="text-align:center; padding:3rem;">
        <i data-lucide="shopping-cart" style="width:48px;height:48px; color:var(--slate-light); display:block; margin:0 auto 1rem;"></i>
        <h3><?= __('shopping_no_plan') ?></h3>
        <p style="margin:.5rem 0 1.5rem;"><?= __('shopping_no_plan_desc') ?></p>
        <a href="<?= BASE_URL ?>/plan.php" class="btn btn-primary"><?= __('shopping_gen_plan') ?></a>
    </div>
    <?php elseif (empty($shoppingList)): ?>
    <div class="alert alert-warning"><?= __('shopping_no_data') ?></div>
    <?php else: ?>

    <div class="card">
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--border);">
            <div style="background:var(--green-light); border-radius:10px; padding:.6rem .9rem; font-weight:700; color:var(--green-dark); font-size:.85rem;">
                <i data-lucide="calendar" style="width:14px;height:14px; vertical-align:-2px;"></i>
                <?= $plan['start_date'] ?> → <?= $plan['end_date'] ?>
            </div>
            <div style="font-size:.85rem; color:var(--slate-mid);">
                <?= array_sum(array_map('count', $shoppingList)) ?> <?= __('shopping_ingredients') ?>
            </div>
        </div>

        <div style="columns:2; gap:2rem;">
            <?php foreach ($shoppingList as $letter => $items): ?>
            <div style="break-inside:avoid; margin-bottom:1.25rem;">
                <h3 style="font-size:.95rem; color:var(--green-dark); margin-bottom:.5rem; border-bottom:2px solid var(--green-light); padding-bottom:.25rem;"><?= $letter ?></h3>
                <ul style="list-style:none; padding:0;">
                    <?php foreach ($items as $item): ?>
                    <li style="display:flex; align-items:flex-start; gap:.5rem; padding:.25rem 0; font-size:.88rem; color:var(--slate);">
                        <span style="display:inline-block; width:16px; height:16px; border:2px solid var(--green); border-radius:4px; flex-shrink:0; margin-top:1px;"></span>
                        <?= htmlspecialchars($item) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<style>
@media print {
    .navbar, footer, .btn { display: none !important; }
    body { background: white; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
