<?php
// ============================================================
// KCALS Admin – Add / Edit Food
// GET ?id=N  → edit existing food
// GET (no id) → add new food
// POST       → save (insert / update)
// ============================================================
require_once __DIR__ . '/includes/admin_auth.php';
requireAdmin();

$db    = getDB();
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isNew = ($id === 0);

$errors  = [];
$success = false;

// ---- Load existing food (edit mode) ----
$food = [
    'id'               => 0,
    'name_en'          => '',
    'name_el'          => '',
    'food_type'        => 'protein',
    'meal_slots'       => 'breakfast,lunch,dinner,snack',
    'cal_per_100g'     => '',
    'protein_per_100g' => '',
    'carbs_per_100g'   => '',
    'fat_per_100g'     => '',
    'is_vegan'         => 0,
    'is_vegetarian'    => 0,
    'is_gluten_free'   => 1,
    'is_keto_ok'       => 0,
    'is_paleo_ok'      => 0,
    'available_months' => '1,2,3,4,5,6,7,8,9,10,11,12',
    'min_serving_g'    => 80,
    'max_serving_g'    => 250,
    'prep_minutes'     => 5,
    'cuisine_tag'      => 'universal',
    'allergen_tags'    => '',
];

if (!$isNew) {
    $stmt = $db->prepare('SELECT * FROM `foods` WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: foods.php');
        exit;
    }
    $food = $row;
}

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {

        // ---- Collect & sanitise ----
        $food['name_en']    = trim($_POST['name_en'] ?? '');
        $food['name_el']    = trim($_POST['name_el'] ?? '');
        $food['food_type']  = $_POST['food_type'] ?? 'protein';

        // meal_slots checkboxes
        $slots_raw    = $_POST['meal_slots'] ?? [];
        $valid_slots  = ['breakfast','lunch','dinner','snack'];
        $slots_clean  = array_intersect($slots_raw, $valid_slots);
        $food['meal_slots'] = implode(',', $slots_clean);

        $food['cal_per_100g']     = $_POST['cal_per_100g']     ?? '';
        $food['protein_per_100g'] = $_POST['protein_per_100g'] ?? '';
        $food['carbs_per_100g']   = $_POST['carbs_per_100g']   ?? '';
        $food['fat_per_100g']     = $_POST['fat_per_100g']     ?? '';
        $food['is_vegan']         = isset($_POST['is_vegan'])         ? 1 : 0;
        $food['is_vegetarian']    = isset($_POST['is_vegetarian'])    ? 1 : 0;
        $food['is_gluten_free']   = isset($_POST['is_gluten_free'])   ? 1 : 0;
        $food['is_keto_ok']       = isset($_POST['is_keto_ok'])       ? 1 : 0;
        $food['is_paleo_ok']      = isset($_POST['is_paleo_ok'])      ? 1 : 0;

        // available_months checkboxes
        $months_raw   = $_POST['available_months'] ?? [];
        $months_clean = array_filter(array_map('intval', $months_raw),
            fn($m) => $m >= 1 && $m <= 12);
        $food['available_months'] = implode(',', $months_clean);

        $food['min_serving_g'] = (int)($_POST['min_serving_g'] ?? 50);
        $food['max_serving_g'] = (int)($_POST['max_serving_g'] ?? 300);
        $food['prep_minutes']  = (int)($_POST['prep_minutes']  ?? 5);

        $valid_cuisine_tags = ['universal','greek','mediterranean','international'];
        $food['cuisine_tag'] = in_array($_POST['cuisine_tag'] ?? '', $valid_cuisine_tags, true)
            ? $_POST['cuisine_tag'] : 'universal';

        // allergen_tags: build CSV from checkboxes
        $valid_allergens = ['gluten','dairy','nuts','eggs','shellfish','soy'];
        $raw_allergens   = $_POST['allergen_tags'] ?? [];
        $clean_allergens = array_intersect($raw_allergens, $valid_allergens);
        $food['allergen_tags'] = implode(',', $clean_allergens);

        // ---- Validate ----
        $allowed_types = ['protein','carb','fat','vegetable','fruit','dairy','mixed'];
        if ($food['name_en'] === '')
            $errors[] = 'English name is required.';
        if ($food['name_el'] === '')
            $errors[] = 'Greek name is required.';
        if (!in_array($food['food_type'], $allowed_types, true))
            $errors[] = 'Invalid food type.';
        if (!is_numeric($food['cal_per_100g']) || (float)$food['cal_per_100g'] < 0)
            $errors[] = 'Calories must be a non-negative number.';
        if (!is_numeric($food['protein_per_100g']) || (float)$food['protein_per_100g'] < 0)
            $errors[] = 'Protein must be a non-negative number.';
        if (!is_numeric($food['carbs_per_100g']) || (float)$food['carbs_per_100g'] < 0)
            $errors[] = 'Carbs must be a non-negative number.';
        if (!is_numeric($food['fat_per_100g']) || (float)$food['fat_per_100g'] < 0)
            $errors[] = 'Fat must be a non-negative number.';
        if (empty($slots_clean))
            $errors[] = 'At least one meal slot must be selected.';
        if (empty($months_clean))
            $errors[] = 'At least one available month must be selected.';
        if ($food['min_serving_g'] <= 0)
            $errors[] = 'Min serving must be greater than 0.';
        if ($food['max_serving_g'] < $food['min_serving_g'])
            $errors[] = 'Max serving must be ≥ min serving.';

        // ---- Save ----
        if (empty($errors)) {
            try {
                if ($isNew) {
                    $st = $db->prepare('
                        INSERT INTO `foods`
                          (name_en, name_el, food_type, cuisine_tag, meal_slots,
                           cal_per_100g, protein_per_100g, carbs_per_100g, fat_per_100g,
                           is_vegan, is_vegetarian, is_gluten_free, is_keto_ok, is_paleo_ok,
                           allergen_tags, available_months, min_serving_g, max_serving_g, prep_minutes)
                        VALUES (?,?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?,?)
                    ');
                    $st->execute([
                        $food['name_en'], $food['name_el'], $food['food_type'],
                        $food['cuisine_tag'], $food['meal_slots'],
                        (float)$food['cal_per_100g'], (float)$food['protein_per_100g'],
                        (float)$food['carbs_per_100g'], (float)$food['fat_per_100g'],
                        $food['is_vegan'], $food['is_vegetarian'], $food['is_gluten_free'],
                        $food['is_keto_ok'], $food['is_paleo_ok'],
                        $food['allergen_tags'], $food['available_months'],
                        $food['min_serving_g'], $food['max_serving_g'], $food['prep_minutes'],
                    ]);
                } else {
                    $st = $db->prepare('
                        UPDATE `foods` SET
                          name_en=?, name_el=?, food_type=?, cuisine_tag=?, meal_slots=?,
                          cal_per_100g=?, protein_per_100g=?, carbs_per_100g=?, fat_per_100g=?,
                          is_vegan=?, is_vegetarian=?, is_gluten_free=?, is_keto_ok=?, is_paleo_ok=?,
                          allergen_tags=?, available_months=?, min_serving_g=?, max_serving_g=?, prep_minutes=?
                        WHERE id=?
                    ');
                    $st->execute([
                        $food['name_en'], $food['name_el'], $food['food_type'],
                        $food['cuisine_tag'], $food['meal_slots'],
                        (float)$food['cal_per_100g'], (float)$food['protein_per_100g'],
                        (float)$food['carbs_per_100g'], (float)$food['fat_per_100g'],
                        $food['is_vegan'], $food['is_vegetarian'], $food['is_gluten_free'],
                        $food['is_keto_ok'], $food['is_paleo_ok'],
                        $food['allergen_tags'], $food['available_months'],
                        $food['min_serving_g'], $food['max_serving_g'], $food['prep_minutes'],
                        $id,
                    ]);
                }
                header('Location: foods.php?saved=1');
                exit;
            } catch (PDOException $e) {
                error_log('food_form save error: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

$pageTitle    = $isNew ? 'Add Food' : 'Edit Food';
$activeAdmin  = 'foods';
$contentClass = '';

// ---- Helper: checked meal slot ----
function slotChecked(string $slot, string $savedSlots): string {
    return in_array($slot, explode(',', $savedSlots), true) ? 'checked' : '';
}
function monthChecked(int $m, string $savedMonths): string {
    return in_array((string)$m, explode(',', $savedMonths), true) ? 'checked' : '';
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.food-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}
@media (max-width: 640px) {
    .food-form-grid { grid-template-columns: 1fr; }
}
.food-form-group { display:flex; flex-direction:column; gap:.4rem; }
.food-form-group label { font-size:.85rem; font-weight:600; color:#2c3e50; }
.food-form-group input,
.food-form-group select { padding:.55rem .75rem; border:1px solid #d1d5db; border-radius:6px; font-size:.9rem; transition:border-color .15s; }
.food-form-group input:focus,
.food-form-group select:focus { outline:none; border-color:#2ecc71; box-shadow:0 0 0 3px rgba(46,204,113,.15); }
.food-form-group .hint { font-size:.75rem; color:#7f8c8d; }
.checkbox-group { display:flex; flex-wrap:wrap; gap:.5rem .9rem; }
.checkbox-group label { display:flex; align-items:center; gap:.35rem; font-size:.875rem; font-weight:500; cursor:pointer; }
.month-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:.35rem; }
.month-grid label { justify-content:center; font-size:.8rem; background:#f7f9fc; border:1px solid #e2e8f0; border-radius:4px; padding:.3rem .2rem; }
.month-grid label:has(input:checked) { background:#d4edda; border-color:#28a745; }
.section-sep { grid-column:1/-1; border:none; border-top:1px solid #e2e8f0; margin:.25rem 0; }
.macro-row { grid-column:1/-1; display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; }
.flag-row { grid-column:1/-1; display:grid; grid-template-columns:repeat(5,1fr); gap:1rem; }
.flag-card { background:#f7f9fc; border:1px solid #e2e8f0; border-radius:8px; padding:.75rem; display:flex; flex-direction:column; align-items:center; gap:.4rem; font-size:.78rem; font-weight:600; color:#555; cursor:pointer; transition:all .15s; }
.flag-card:has(input:checked) { background:#d4edda; border-color:#27ae60; color:#155724; }
.flag-card input { display:none; }
.form-actions { grid-column:1/-1; display:flex; gap:.75rem; padding-top:.5rem; }
.btn-save { background:#2ecc71;color:#fff;border:none;padding:.65rem 1.75rem;border-radius:6px;font-size:.95rem;font-weight:600;cursor:pointer; }
.btn-save:hover { background:#27ae60; }
.btn-cancel { background:#f0f0f0;color:#333;border:1px solid #d1d5db;padding:.65rem 1.25rem;border-radius:6px;font-size:.95rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block; }
.btn-cancel:hover { background:#e5e7eb; }
.form-errors { background:#f8d7da;border:1px solid #dc3545;border-radius:6px;padding:.875rem 1.25rem;margin-bottom:1.25rem; }
.form-errors ul { margin:.4rem 0 0 1.2rem; padding:0; }
.form-errors li { font-size:.875rem; color:#721c24; }
</style>

<!-- Breadcrumb -->
<div style="margin-bottom:1rem;font-size:.875rem;color:#7f8c8d;">
    <a href="foods.php" style="color:#2ecc71;text-decoration:none;">Foods</a>
    &rsaquo; <?= $isNew ? 'Add New Food' : 'Edit: ' . htmlspecialchars($food['name_en']) ?>
</div>

<div class="admin-card" style="padding:1.75rem;">
    <h2 style="margin:0 0 1.5rem;font-size:1.2rem;">
        <?= $isNew ? '🌱 Add New Food' : '✏️ Edit Food' ?>
    </h2>

    <?php if (!empty($errors)): ?>
    <div class="form-errors">
        <strong>Please fix the following:</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="food-form-grid">

            <!-- ── Names ── -->
            <div class="food-form-group">
                <label for="name_en">English Name *</label>
                <input type="text" id="name_en" name="name_en" required maxlength="150"
                       value="<?= htmlspecialchars($food['name_en']) ?>"
                       placeholder="e.g. Chicken Breast (cooked)">
            </div>

            <div class="food-form-group">
                <label for="name_el">Greek Name * (Ελληνικό)</label>
                <input type="text" id="name_el" name="name_el" required maxlength="150"
                       value="<?= htmlspecialchars($food['name_el']) ?>"
                       placeholder="π.χ. Στήθος Κοτόπουλου">
            </div>

            <!-- ── Type ── -->
            <div class="food-form-group">
                <label for="food_type">Food Type *</label>
                <select id="food_type" name="food_type" required>
                    <?php foreach (['protein','carb','fat','vegetable','fruit','dairy','mixed'] as $t): ?>
                    <option value="<?= $t ?>" <?= $food['food_type'] === $t ? 'selected' : '' ?>>
                        <?= ucfirst($t) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Cuisine Tag ── -->
            <div class="food-form-group">
                <label for="cuisine_tag">Cuisine Tag</label>
                <select id="cuisine_tag" name="cuisine_tag">
                    <?php foreach (['universal'=>'🌐 Universal','greek'=>'🇬🇷 Greek','mediterranean'=>'🌊 Mediterranean','international'=>'🌍 International'] as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= ($food['cuisine_tag'] ?? 'universal') === $val ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">Controls which adventure-level users see this food</span>
            </div>

            <!-- ── Prep ── -->
            <div class="food-form-group">
                <label for="prep_minutes">Prep Time (minutes)</label>
                <input type="number" id="prep_minutes" name="prep_minutes" min="0" max="240"
                       value="<?= (int)$food['prep_minutes'] ?>">
            </div>

            <hr class="section-sep">

            <!-- ── Macros ── -->
            <div class="macro-row">
                <div class="food-form-group">
                    <label for="cal_per_100g">Calories / 100g *</label>
                    <input type="number" id="cal_per_100g" name="cal_per_100g"
                           min="0" max="9999" step="0.1" required
                           value="<?= htmlspecialchars($food['cal_per_100g']) ?>">
                    <span class="hint">kcal</span>
                </div>
                <div class="food-form-group">
                    <label for="protein_per_100g">Protein / 100g *</label>
                    <input type="number" id="protein_per_100g" name="protein_per_100g"
                           min="0" max="999" step="0.1" required
                           value="<?= htmlspecialchars($food['protein_per_100g']) ?>">
                    <span class="hint">grams</span>
                </div>
                <div class="food-form-group">
                    <label for="carbs_per_100g">Carbs / 100g *</label>
                    <input type="number" id="carbs_per_100g" name="carbs_per_100g"
                           min="0" max="999" step="0.1" required
                           value="<?= htmlspecialchars($food['carbs_per_100g']) ?>">
                    <span class="hint">grams</span>
                </div>
                <div class="food-form-group">
                    <label for="fat_per_100g">Fat / 100g *</label>
                    <input type="number" id="fat_per_100g" name="fat_per_100g"
                           min="0" max="999" step="0.1" required
                           value="<?= htmlspecialchars($food['fat_per_100g']) ?>">
                    <span class="hint">grams</span>
                </div>
            </div>

            <hr class="section-sep">

            <!-- ── Serving size ── -->
            <div class="food-form-group">
                <label for="min_serving_g">Min Serving (g) *</label>
                <input type="number" id="min_serving_g" name="min_serving_g"
                       min="1" max="9999" required
                       value="<?= (int)$food['min_serving_g'] ?>">
            </div>

            <div class="food-form-group">
                <label for="max_serving_g">Max Serving (g) *</label>
                <input type="number" id="max_serving_g" name="max_serving_g"
                       min="1" max="9999" required
                       value="<?= (int)$food['max_serving_g'] ?>">
            </div>

            <hr class="section-sep">

            <!-- ── Meal slots ── -->
            <div class="food-form-group" style="grid-column:1/-1;">
                <label>Meal Slots * (when this food can appear)</label>
                <div class="checkbox-group">
                    <?php foreach (['breakfast','lunch','dinner','snack'] as $slot): ?>
                    <label>
                        <input type="checkbox" name="meal_slots[]"
                               value="<?= $slot ?>"
                               <?= slotChecked($slot, $food['meal_slots']) ?>>
                        <?= ucfirst($slot) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <hr class="section-sep">

            <!-- ── Diet flags ── -->
            <div class="food-form-group" style="grid-column:1/-1;">
                <label>Diet Flags</label>
                <div class="flag-row">
                    <?php
                    $flags = [
                        ['is_vegan',       '🌱', 'Vegan'],
                        ['is_vegetarian',  '🥚', 'Vegetarian'],
                        ['is_gluten_free', '🌾', 'Gluten-Free'],
                        ['is_keto_ok',     '🥑', 'Keto'],
                        ['is_paleo_ok',    '🍖', 'Paleo'],
                    ];
                    foreach ($flags as [$name, $icon, $label]):
                    ?>
                    <label class="flag-card">
                        <input type="checkbox" name="<?= $name ?>"
                               <?= $food[$name] ? 'checked' : '' ?>>
                        <span style="font-size:1.4rem;"><?= $icon ?></span>
                        <?= $label ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <hr class="section-sep">

            <!-- ── Allergen Tags ── -->
            <div class="food-form-group" style="grid-column:1/-1;">
                <label>Allergen Tags (check all that apply)</label>
                <div class="checkbox-group">
                    <?php
                    $allergenDefs = ['gluten'=>'🌾 Gluten','dairy'=>'🥛 Dairy','nuts'=>'🥜 Nuts','eggs'=>'🥚 Eggs','shellfish'=>'🦐 Shellfish','soy'=>'🌱 Soy'];
                    $savedAllergens = array_filter(explode(',', $food['allergen_tags'] ?? ''));
                    foreach ($allergenDefs as $val => $lbl): ?>
                    <label>
                        <input type="checkbox" name="allergen_tags[]"
                               value="<?= $val ?>"
                               <?= in_array($val, $savedAllergens, true) ? 'checked' : '' ?>>
                        <?= $lbl ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <span class="hint">Used to filter out foods for users with allergies.</span>
            </div>

            <hr class="section-sep">

            <!-- ── Available months ── -->
            <div class="food-form-group" style="grid-column:1/-1;">
                <label>Available Months (seasonal availability)</label>
                <?php
                $month_names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                ?>
                <div class="month-grid">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <label>
                        <input type="checkbox" name="available_months[]"
                               value="<?= $m ?>"
                               <?= monthChecked($m, $food['available_months']) ?>>
                        <?= $month_names[$m - 1] ?>
                    </label>
                    <?php endfor; ?>
                </div>
                <span class="hint">
                    <a href="#" onclick="toggleAllMonths(true);return false;">All</a> /
                    <a href="#" onclick="toggleAllMonths(false);return false;">None</a>
                </span>
            </div>

            <!-- ── Actions ── -->
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <?= $isNew ? '➕ Add Food' : '💾 Save Changes' ?>
                </button>
                <a href="foods.php" class="btn-cancel">Cancel</a>
                <?php if (!$isNew): ?>
                <button type="button" class="btn-cancel"
                        style="background:#fff0f0;border-color:#e74c3c;color:#c0392b;margin-left:auto;"
                        onclick="confirmDelete(<?= $id ?>, <?= htmlspecialchars(json_encode($food['name_en'])) ?>, '<?= csrfToken() ?>')">
                    🗑 Delete This Food
                </button>
                <?php endif; ?>
            </div>

        </div><!-- /.food-form-grid -->
    </form>
</div>

<script>
function toggleAllMonths(check) {
    document.querySelectorAll('input[name="available_months[]"]')
        .forEach(cb => cb.checked = check);
}

function confirmDelete(id, name, csrf) {
    if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('action',     'delete');
    fd.append('id',         id);
    fd.append('csrf_token', csrf);
    fetch('ajax/food_action.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                window.location.href = 'foods.php?deleted=1';
            } else {
                alert('Error: ' + data.msg);
            }
        })
        .catch(() => alert('Network error.'));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
