<?php
// ============================================================
// KCALS Admin – Foods Management (list + search + delete)
// ============================================================
require_once __DIR__ . '/includes/admin_auth.php';
requireAdmin();

$pageTitle    = 'Foods';
$activeAdmin  = 'foods';
$contentClass = 'admin-content-inner--wide';

$db = getDB();

// ---- Filters from query-string ----
$typeFilter   = $_GET['type'] ?? '';
$searchQ      = trim($_GET['q'] ?? '');

$allowed_types = ['protein','carb','fat','vegetable','fruit','dairy','mixed',''];

$where  = [];
$params = [];

if ($searchQ !== '') {
    $where[]  = '(name_en LIKE ? OR name_el LIKE ?)';
    $params[] = '%' . $searchQ . '%';
    $params[] = '%' . $searchQ . '%';
}
if (in_array($typeFilter, $allowed_types, true) && $typeFilter !== '') {
    $where[]  = 'food_type = ?';
    $params[] = $typeFilter;
}

$sql = 'SELECT * FROM `foods`';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY food_type, name_en';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$foods = $stmt->fetchAll();

// ---- Type badge colours ----
function typeBadge(string $t): string {
    $map = [
        'protein'   => '#3498DB',
        'carb'      => '#F39C12',
        'fat'       => '#9B59B6',
        'vegetable' => '#27AE60',
        'fruit'     => '#E74C3C',
        'dairy'     => '#1ABC9C',
        'mixed'     => '#7F8C8D',
    ];
    $color = $map[$t] ?? '#95A5A6';
    return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:.72rem;font-weight:600;color:#fff;background:'
         . $color . '">' . htmlspecialchars($t) . '</span>';
}

function boolBadge(int $v, string $yes = '✓', string $no = '–'): string {
    return $v
        ? '<span style="color:#27ae60;font-weight:700">' . $yes . '</span>'
        : '<span style="color:#bdc3c7">' . $no . '</span>';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ===== TOOLBAR ===== -->
<div class="admin-users-toolbar" style="flex-wrap:wrap;gap:.75rem;">
    <form method="GET" action="" style="display:flex;gap:.5rem;flex:1;min-width:200px;">
        <div class="admin-search-wrap" style="flex:1;">
            <i data-lucide="search" class="admin-search-icon"></i>
            <input type="search" name="q" class="admin-search-input"
                   placeholder="Search by name (EN or EL)…"
                   value="<?= htmlspecialchars($searchQ) ?>">
        </div>
        <select name="type" class="admin-select" onchange="this.form.submit()"
                style="padding:.45rem .75rem;border:1px solid #d1d5db;border-radius:6px;font-size:.875rem;background:#fff;">
            <option value="">All types</option>
            <?php foreach (['protein','carb','fat','vegetable','fruit','dairy','mixed'] as $t): ?>
            <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-sm btn-primary" style="white-space:nowrap;">Filter</button>
        <?php if ($searchQ || $typeFilter): ?>
        <a href="foods.php" class="btn-sm btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <span class="admin-users-count" style="white-space:nowrap;">
        <?= count($foods) ?> food<?= count($foods) !== 1 ? 's' : '' ?>
    </span>

    <a href="food_form.php" class="btn-sm btn-primary" style="white-space:nowrap;">
        <i data-lucide="plus" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i>Add Food
    </a>
</div>

<!-- ===== FLASH ===== -->
<?php if (!empty($_GET['deleted'])): ?>
<div class="admin-flash admin-flash--ok" id="flashMsg">
    Food deleted successfully.
    <button onclick="document.getElementById('flashMsg').remove()" style="float:right;background:none;border:none;cursor:pointer;font-size:1.1rem;">&times;</button>
</div>
<?php elseif (!empty($_GET['saved'])): ?>
<div class="admin-flash admin-flash--ok" id="flashMsg">
    Food saved successfully.
    <button onclick="document.getElementById('flashMsg').remove()" style="float:right;background:none;border:none;cursor:pointer;font-size:1.1rem;">&times;</button>
</div>
<?php endif; ?>

<!-- ===== TABLE ===== -->
<div class="admin-card" style="overflow:visible;">
    <div class="admin-table-wrap" style="overflow-x:auto;">
    <?php if (empty($foods)): ?>
        <p style="padding:2rem;text-align:center;color:#7f8c8d;">No foods found.</p>
    <?php else: ?>
        <table class="admin-table" id="foodsTable" style="min-width:900px;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>English Name</th>
                    <th>Greek Name</th>
                    <th>Type</th>
                    <th class="t-right">kcal</th>
                    <th class="t-right">P</th>
                    <th class="t-right">C</th>
                    <th class="t-right">F</th>
                    <th class="t-center">Vegan</th>
                    <th class="t-center">VG</th>
                    <th class="t-center">GF</th>
                    <th class="t-center">Keto</th>
                    <th class="t-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($foods as $f): ?>
                <tr id="frow-<?= $f['id'] ?>">
                    <td style="color:#7f8c8d;font-size:.8rem;"><?= $f['id'] ?></td>
                    <td><strong><?= htmlspecialchars($f['name_en']) ?></strong></td>
                    <td><?= htmlspecialchars($f['name_el']) ?></td>
                    <td><?= typeBadge($f['food_type']) ?></td>
                    <td class="t-right"><?= number_format((float)$f['cal_per_100g'], 0) ?></td>
                    <td class="t-right" style="color:#3498db;"><?= $f['protein_per_100g'] ?>g</td>
                    <td class="t-right" style="color:#e67e22;"><?= $f['carbs_per_100g'] ?>g</td>
                    <td class="t-right" style="color:#9b59b6;"><?= $f['fat_per_100g'] ?>g</td>
                    <td class="t-center"><?= boolBadge($f['is_vegan']) ?></td>
                    <td class="t-center"><?= boolBadge($f['is_vegetarian']) ?></td>
                    <td class="t-center"><?= boolBadge($f['is_gluten_free']) ?></td>
                    <td class="t-center"><?= boolBadge($f['is_keto_ok']) ?></td>
                    <td class="t-right" style="white-space:nowrap;">
                        <a href="food_form.php?id=<?= $f['id'] ?>"
                           class="btn-sm btn-ghost" title="Edit">
                            <i data-lucide="pencil" style="width:13px;height:13px;"></i>
                        </a>
                        <button class="btn-sm btn-danger js-delete-food"
                                data-id="<?= $f['id'] ?>"
                                data-name="<?= htmlspecialchars($f['name_en'], ENT_QUOTES) ?>"
                                data-csrf="<?= csrfToken() ?>"
                                title="Delete">
                            <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<!-- ===== DELETE CONFIRM MODAL ===== -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:2rem;max-width:380px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <h3 style="margin:0 0 .75rem;font-size:1.1rem;">Delete Food?</h3>
        <p style="margin:0 0 1.5rem;color:#555;">
            Delete "<strong id="deleteModalName"></strong>"? This cannot be undone.
        </p>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;">
            <button id="deleteCancelBtn" class="btn-sm btn-ghost">Cancel</button>
            <button id="deleteConfirmBtn" class="btn-sm btn-danger">Delete</button>
        </div>
    </div>
</div>

<style>
.admin-flash { padding:.875rem 1.25rem; border-radius:8px; margin-bottom:1rem; font-size:.9rem; }
.admin-flash--ok  { background:#d4edda; border:1px solid #28a745; color:#155724; }
.admin-flash--err { background:#f8d7da; border:1px solid #dc3545; color:#721c24; }
.btn-sm { display:inline-flex;align-items:center;gap:4px;padding:.3rem .7rem;border-radius:6px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;text-decoration:none; }
.btn-primary { background:#2ecc71;color:#fff; }
.btn-primary:hover { background:#27ae60; }
.btn-ghost { background:#f0f0f0;color:#333;border:1px solid #d1d5db; }
.btn-ghost:hover { background:#e5e7eb; }
.btn-danger { background:#e74c3c;color:#fff; }
.btn-danger:hover { background:#c0392b; }
</style>

<script>
const modal      = document.getElementById('deleteModal');
const modalName  = document.getElementById('deleteModalName');
const cancelBtn  = document.getElementById('deleteCancelBtn');
const confirmBtn = document.getElementById('deleteConfirmBtn');

let pendingId   = null;
let pendingCsrf = null;

document.querySelectorAll('.js-delete-food').forEach(btn => {
    btn.addEventListener('click', () => {
        pendingId   = btn.dataset.id;
        pendingCsrf = btn.dataset.csrf;
        modalName.textContent = btn.dataset.name;
        modal.style.display = 'flex';
    });
});

cancelBtn.addEventListener('click', () => { modal.style.display = 'none'; });
modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

confirmBtn.addEventListener('click', () => {
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Deleting…';

    const fd = new FormData();
    fd.append('action',     'delete');
    fd.append('id',         pendingId);
    fd.append('csrf_token', pendingCsrf);

    fetch('ajax/food_action.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const row = document.getElementById('frow-' + pendingId);
                if (row) row.remove();
                modal.style.display = 'none';
                // update count
                const count = document.querySelectorAll('#foodsTable tbody tr').length;
                document.querySelector('.admin-users-count').textContent =
                    count + ' food' + (count !== 1 ? 's' : '');
            } else {
                alert('Error: ' + data.msg);
            }
        })
        .catch(() => alert('Network error.'))
        .finally(() => {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Delete';
        });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
