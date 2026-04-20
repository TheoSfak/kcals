<?php
// ============================================================
// KCALS Admin – User Management
// ============================================================
require_once __DIR__ . '/includes/admin_auth.php';
requireAdmin();

$pageTitle    = 'Users';
$activeAdmin  = 'users';
$contentClass = 'admin-content-inner--wide';

$db    = getDB();
$users = $db->query("
    SELECT
        u.id, u.full_name, u.email, u.gender, u.birth_date,
        u.height_cm, u.activity_level, u.diet_type,
        u.is_admin, u.is_active, u.created_at,
        (SELECT COUNT(*) FROM weekly_plans  WHERE user_id = u.id) AS plan_count,
        (SELECT COUNT(*) FROM user_progress WHERE user_id = u.id) AS checkin_count
    FROM users u
    ORDER BY u.created_at DESC
")->fetchAll();

// ---- Helpers ----
function userInitials(string $name): string {
    $parts    = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $initials .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $initials ?: '?';
}

function userAvatarColor(string $name): string {
    $colors = ['#3498DB','#9B59B6','#E67E22','#1ABC9C','#E74C3C','#2ECC71','#F39C12','#2980B9'];
    return $colors[abs(crc32($name)) % count($colors)];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Toolbar -->
<div class="admin-users-toolbar">
    <div class="admin-search-wrap">
        <i data-lucide="search" class="admin-search-icon"></i>
        <input type="search" id="userSearch" class="admin-search-input"
               placeholder="Search by name or email…"
               oninput="filterUsers(this.value)">
    </div>
    <span class="admin-users-count" id="usersCount">
        <?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?>
    </span>
</div>

<!-- Table card -->
<div class="admin-card" style="overflow:visible;">
    <div class="admin-table-wrap">
        <table class="admin-table" id="usersTable">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Status</th>
                    <th>Role</th>
                    <th>Diet</th>
                    <th class="t-center">Plans</th>
                    <th class="t-center">Check-ins</th>
                    <th>Joined</th>
                    <th class="t-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr id="urow-<?= $u['id'] ?>"
                    data-search="<?= htmlspecialchars(strtolower($u['full_name'] . ' ' . $u['email']), ENT_QUOTES) ?>">

                    <!-- User cell -->
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar" style="background:<?= userAvatarColor($u['full_name']) ?>">
                                <?= userInitials($u['full_name']) ?>
                            </div>
                            <div>
                                <div class="user-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </td>

                    <!-- Status -->
                    <td>
                        <span class="status-badge <?= $u['is_active'] ? 'status-active' : 'status-inactive' ?>"
                              id="status-<?= $u['id'] ?>">
                            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>

                    <!-- Role -->
                    <td>
                        <span class="role-badge <?= $u['is_admin'] ? 'role-admin' : 'role-user' ?>"
                              id="role-<?= $u['id'] ?>">
                            <?= $u['is_admin'] ? 'Admin' : 'User' ?>
                        </span>
                    </td>

                    <!-- Diet -->
                    <td class="t-muted"><?= htmlspecialchars(ucfirst($u['diet_type'] ?? 'standard')) ?></td>

                    <!-- Plans -->
                    <td class="t-center t-bold"><?= (int)$u['plan_count'] ?></td>

                    <!-- Check-ins -->
                    <td class="t-center t-bold"><?= (int)$u['checkin_count'] ?></td>

                    <!-- Joined -->
                    <td class="t-muted t-nowrap"><?= date('d M Y', strtotime($u['created_at'])) ?></td>

                    <!-- Actions -->
                    <td class="t-right">
                        <div class="user-actions">
                            <!-- Edit -->
                            <button class="ua-btn ua-edit" title="Edit user"
                                    data-user="<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>"
                                    onclick="openEditModal(this)">
                                <i data-lucide="pencil"></i>
                            </button>

                            <!-- Deactivate / Reactivate -->
                            <button class="ua-btn <?= $u['is_active'] ? 'ua-deactivate' : 'ua-reactivate' ?>"
                                    id="toggle-<?= $u['id'] ?>"
                                    data-id="<?= $u['id'] ?>"
                                    data-active="<?= $u['is_active'] ? '1' : '0' ?>"
                                    title="<?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?>"
                                    onclick="toggleActive(this)">
                                <i data-lucide="<?= $u['is_active'] ? 'user-x' : 'user-check' ?>"></i>
                            </button>

                            <!-- Delete (can't delete yourself) -->
                            <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                            <button class="ua-btn ua-delete" title="Delete user"
                                    data-id="<?= $u['id'] ?>"
                                    data-name="<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>"
                                    onclick="deleteUser(this)">
                                <i data-lucide="trash-2"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div id="noResults" class="admin-no-results" style="display:none;">
            <i data-lucide="search-x" style="width:32px;height:32px;color:#BDC3C7;"></i>
            <p>No users match your search.</p>
        </div>
    </div>
</div>

<!-- ===== Edit Modal ===== -->
<div class="admin-modal-overlay" id="editModal" onclick="overlayClick(event)">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h3 id="editModalTitle">Edit User</h3>
            <button class="admin-modal-close" onclick="closeEditModal()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="editForm" onsubmit="saveUser(event)">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action"    value="update">
            <input type="hidden" name="user_id"   id="editUserId">

            <div class="admin-modal-body">
                <div class="modal-form-grid">
                    <!-- Full Name -->
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" id="editFullName"
                               required maxlength="150">
                    </div>
                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="editEmail" required>
                    </div>
                    <!-- Diet Type -->
                    <div class="form-group">
                        <label class="form-label">Diet Type</label>
                        <select class="form-control" name="diet_type" id="editDietType">
                            <option value="standard">Standard</option>
                            <option value="vegetarian">Vegetarian</option>
                            <option value="vegan">Vegan</option>
                            <option value="keto">Keto</option>
                            <option value="paleo">Paleo</option>
                        </select>
                    </div>
                    <!-- Activity Level -->
                    <div class="form-group">
                        <label class="form-label">Activity Level</label>
                        <select class="form-control" name="activity_level" id="editActivityLevel">
                            <option value="1.20">Sedentary (×1.20)</option>
                            <option value="1.375">Lightly Active (×1.375)</option>
                            <option value="1.55">Moderately Active (×1.55)</option>
                            <option value="1.725">Very Active (×1.725)</option>
                            <option value="1.90">Extra Active (×1.90)</option>
                        </select>
                    </div>
                </div>

                <!-- New Password -->
                <div class="form-group" style="margin-top:0.5rem;">
                    <label class="form-label">
                        New Password
                        <small class="t-muted" style="font-weight:500;">(leave blank to keep current)</small>
                    </label>
                    <input type="password" class="form-control" name="new_password" id="editPassword"
                           autocomplete="new-password" minlength="8"
                           placeholder="Min. 8 characters">
                </div>

                <!-- Admin toggle (hidden for self) -->
                <div class="form-group" id="adminToggleWrap" style="margin-top:0.5rem;">
                    <label class="form-label-check">
                        <input type="checkbox" name="is_admin" id="editIsAdmin" value="1">
                        <span class="check-label">
                            <i data-lucide="shield-check" style="width:14px;height:14px;"></i>
                            Grant admin access
                        </span>
                    </label>
                </div>

                <!-- Save error -->
                <div id="editError" class="admin-inline-error" style="display:none;"></div>
            </div>

            <div class="admin-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="editSaveBtn">
                    <i data-lucide="check" style="width:14px;height:14px;"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF    = <?= json_encode(csrfToken()) ?>;
const SELF_ID = <?= (int)$_SESSION['user_id'] ?>;
const ACTION_URL = <?= json_encode(BASE_URL . '/admin/ajax/user_action.php') ?>;

// ── Search ──────────────────────────────────────────────────
function filterUsers(q) {
    q = q.toLowerCase().trim();
    let visible = 0;
    document.querySelectorAll('#usersTable tbody tr').forEach(row => {
        const match = !q || row.dataset.search.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('usersCount').textContent =
        visible + ' user' + (visible !== 1 ? 's' : '');
    document.getElementById('noResults').style.display = visible === 0 ? 'flex' : 'none';
}

// ── Edit Modal ───────────────────────────────────────────────
let currentEditUser = null;

function openEditModal(btn) {
    currentEditUser = JSON.parse(btn.dataset.user);
    const u = currentEditUser;

    document.getElementById('editModalTitle').textContent = 'Edit — ' + u.full_name;
    document.getElementById('editUserId').value       = u.id;
    document.getElementById('editFullName').value     = u.full_name;
    document.getElementById('editEmail').value        = u.email;
    document.getElementById('editDietType').value     = u.diet_type     || 'standard';
    document.getElementById('editPassword').value     = '';

    // Activity level – match closest option
    const actSel = document.getElementById('editActivityLevel');
    const actVal = parseFloat(u.activity_level).toFixed(3);
    let matched  = false;
    for (const opt of actSel.options) {
        if (parseFloat(opt.value).toFixed(3) === actVal) {
            opt.selected = true; matched = true; break;
        }
    }
    if (!matched) actSel.options[0].selected = true;

    // Admin checkbox – hide for self (can't demote yourself)
    const adminWrap = document.getElementById('adminToggleWrap');
    if (parseInt(u.id) === SELF_ID) {
        adminWrap.style.display = 'none';
    } else {
        adminWrap.style.display = '';
        document.getElementById('editIsAdmin').checked = !!parseInt(u.is_admin);
    }

    document.getElementById('editError').style.display = 'none';
    document.getElementById('editModal').classList.add('open');
    lucide.createIcons();
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

function overlayClick(e) {
    if (e.target === document.getElementById('editModal')) closeEditModal();
}

function saveUser(e) {
    e.preventDefault();
    const btn  = document.getElementById('editSaveBtn');
    const form = document.getElementById('editForm');
    const errEl = document.getElementById('editError');
    errEl.style.display = 'none';

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" style="width:14px;height:14px;"></i> Saving…';
    lucide.createIcons();

    const data = new FormData(form);

    fetch(ACTION_URL, { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (!res.ok) {
                errEl.textContent    = res.msg || 'Save failed.';
                errEl.style.display  = 'block';
            } else {
                // Update row in-place
                const u = res.user;
                const row = document.getElementById('urow-' + u.id);
                if (row) {
                    row.querySelector('.user-name').textContent  = u.full_name;
                    row.querySelector('.user-email').textContent = u.email;
                    row.dataset.search = (u.full_name + ' ' + u.email).toLowerCase();

                    const roleBadge = document.getElementById('role-' + u.id);
                    roleBadge.textContent = u.is_admin ? 'Admin' : 'User';
                    roleBadge.className   = 'role-badge ' + (u.is_admin ? 'role-admin' : 'role-user');
                }
                closeEditModal();
            }
        })
        .catch(() => {
            errEl.textContent   = 'Network error. Please try again.';
            errEl.style.display = 'block';
        })
        .finally(() => {
            btn.disabled      = false;
            btn.innerHTML     = '<i data-lucide="check" style="width:14px;height:14px;"></i> Save Changes';
            lucide.createIcons();
        });
}

// ── Toggle Active ────────────────────────────────────────────
function toggleActive(btn) {
    const userId   = parseInt(btn.dataset.id);
    const isActive = btn.dataset.active === '1';
    const action   = isActive ? 'Deactivate' : 'Reactivate';

    if (!confirm(action + ' this user?')) return;

    btn.disabled = true;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('action',     'toggle_active');
    fd.append('user_id',    userId);

    fetch(ACTION_URL, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { alert(res.msg || 'Error'); return; }
            const nowActive = res.is_active;
            // Update button
            btn.dataset.active  = nowActive ? '1' : '0';
            btn.className       = 'ua-btn ' + (nowActive ? 'ua-deactivate' : 'ua-reactivate');
            btn.title           = nowActive ? 'Deactivate' : 'Reactivate';
            btn.innerHTML       = '<i data-lucide="' + (nowActive ? 'user-x' : 'user-check') + '"></i>';
            // Update status badge
            const badge = document.getElementById('status-' + userId);
            badge.textContent = nowActive ? 'Active' : 'Inactive';
            badge.className   = 'status-badge ' + (nowActive ? 'status-active' : 'status-inactive');
            lucide.createIcons();
        })
        .catch(() => alert('Network error. Please try again.'))
        .finally(() => { btn.disabled = false; });
}

// ── Delete ───────────────────────────────────────────────────
function deleteUser(btn) {
    const userId = parseInt(btn.dataset.id);
    const name   = btn.dataset.name;
    if (!confirm('Delete "' + name + '"? This removes ALL their data (plans, progress, etc.) and cannot be undone.')) return;

    btn.disabled = true;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('action',     'delete');
    fd.append('user_id',    userId);

    fetch(ACTION_URL, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { alert(res.msg || 'Error'); btn.disabled = false; return; }
            const row = document.getElementById('urow-' + userId);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity    = '0';
                setTimeout(() => {
                    row.remove();
                    // Update count
                    const cnt = document.querySelectorAll('#usersTable tbody tr:not([style*="display: none"])').length;
                    document.getElementById('usersCount').textContent = cnt + ' user' + (cnt !== 1 ? 's' : '');
                }, 300);
            }
        })
        .catch(() => { alert('Network error. Please try again.'); btn.disabled = false; });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
