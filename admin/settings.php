<?php
// ============================================================
// KCALS Admin – Settings (tabs: SMTP | Updates)
// ============================================================
require_once __DIR__ . '/includes/admin_auth.php';
requireAdmin();

$pageTitle   = 'Settings';
$activeAdmin = 'settings';

$smtpKeys = [
    'smtp_host', 'smtp_port', 'smtp_encryption',
    'smtp_user', 'smtp_pass', 'smtp_from_name', 'smtp_from_email',
];

$saved  = false;
$errors = [];

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    } else {
        $fields = [
            'smtp_host'       => trim($_POST['smtp_host']       ?? ''),
            'smtp_port'       => (string) max(1, min(65535, (int) ($_POST['smtp_port'] ?? 587))),
            'smtp_encryption' => in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true)
                                     ? $_POST['smtp_encryption'] : 'tls',
            'smtp_user'       => trim($_POST['smtp_user']       ?? ''),
            'smtp_from_name'  => trim($_POST['smtp_from_name']  ?? ''),
            'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
        ];

        if (!empty($fields['smtp_from_email']) &&
            !filter_var($fields['smtp_from_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'From Email is not a valid email address.';
        }

        if (empty($errors)) {
            foreach ($fields as $k => $v) {
                saveSetting($k, $v);
            }
            $newPass = $_POST['smtp_pass'] ?? '';
            if ($newPass !== '') {
                saveSetting('smtp_pass', $newPass);
            }
            header('Location: ' . BASE_URL . '/admin/settings.php?saved=1&tab=smtp');
            exit;
        }
    }
}

if (isset($_GET['saved'])) $saved = true;

$activeTab = $_GET['tab'] ?? 'smtp';
if (!in_array($activeTab, ['smtp', 'updates'], true)) $activeTab = 'smtp';

// Load current values
$s = getSettings($smtpKeys);

// Current app version
$versionFile = __DIR__ . '/../VERSION';
$currentVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'unknown';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div class="admin-breadcrumb">
    <a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a>
    <i data-lucide="chevron-right" style="width:13px;height:13px;"></i>
    Settings
</div>

<?php if ($saved): ?>
<div class="alert alert-success" style="margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem;">
    <i data-lucide="check-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
    Settings saved successfully.
</div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error" style="margin-bottom:.75rem;">
    <?= htmlspecialchars($err) ?>
</div>
<?php endforeach; ?>

<!-- ===== Tabs nav ===== -->
<div class="settings-tabs" role="tablist">
    <button class="settings-tab-btn <?= $activeTab === 'smtp'    ? 'active' : '' ?>"
            role="tab" aria-selected="<?= $activeTab === 'smtp' ? 'true' : 'false' ?>"
            onclick="switchTab('smtp')">
        <i data-lucide="mail"></i> SMTP
    </button>
    <button class="settings-tab-btn <?= $activeTab === 'updates' ? 'active' : '' ?>"
            role="tab" aria-selected="<?= $activeTab === 'updates' ? 'true' : 'false' ?>"
            onclick="switchTab('updates')">
        <i data-lucide="refresh-cw"></i> Updates
    </button>
</div>

<!-- ===== Tab: SMTP ===== -->
<div id="tab-smtp" class="settings-tab-panel <?= $activeTab === 'smtp' ? 'active' : '' ?>">

    <form method="POST" action="<?= BASE_URL ?>/admin/settings.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="save_smtp"  value="1">

        <div class="admin-card">
            <div class="admin-card-header">
                <h2>📧 SMTP Email Settings</h2>
                <p>Outgoing mail server configuration used for all application emails.</p>
            </div>
            <div class="admin-card-body">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="smtp_host">SMTP Host</label>
                        <input class="form-control" type="text" id="smtp_host" name="smtp_host"
                               placeholder="smtp.gmail.com"
                               value="<?= htmlspecialchars($s['smtp_host']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="smtp_port">Port</label>
                        <input class="form-control" type="number" id="smtp_port" name="smtp_port"
                               min="1" max="65535" placeholder="587"
                               value="<?= htmlspecialchars($s['smtp_port']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="smtp_encryption">Encryption</label>
                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                        <option value="tls"  <?= $s['smtp_encryption'] === 'tls'  ? 'selected' : '' ?>>TLS — recommended (port 587)</option>
                        <option value="ssl"  <?= $s['smtp_encryption'] === 'ssl'  ? 'selected' : '' ?>>SSL (port 465)</option>
                        <option value="none" <?= $s['smtp_encryption'] === 'none' ? 'selected' : '' ?>>None — port 25, not recommended</option>
                    </select>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="smtp_user">SMTP Username</label>
                        <input class="form-control" type="text" id="smtp_user" name="smtp_user"
                               placeholder="your@email.com"
                               value="<?= htmlspecialchars($s['smtp_user']) ?>"
                               autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="smtp_pass">SMTP Password</label>
                        <div class="input-icon-wrap">
                            <input class="form-control" type="password" id="smtp_pass" name="smtp_pass"
                                   placeholder="<?= $s['smtp_pass'] !== '' ? '••••••••  (saved)' : 'Enter password' ?>"
                                   autocomplete="new-password">
                            <button type="button" class="input-toggle-btn"
                                    onclick="togglePassVisibility()" title="Show / Hide password">
                                <i data-lucide="eye" id="eye-icon"></i>
                            </button>
                        </div>
                        <small style="font-size:.76rem;color:var(--slate-light);">
                            Leave blank to keep the stored password. For Gmail, use an
                            <a href="https://support.google.com/accounts/answer/185833"
                               target="_blank" rel="noopener" style="color:var(--green-dark);">app-specific password</a>.
                        </small>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="smtp_from_name">From Name</label>
                        <input class="form-control" type="text" id="smtp_from_name" name="smtp_from_name"
                               placeholder="KCALS"
                               value="<?= htmlspecialchars($s['smtp_from_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="smtp_from_email">From Email</label>
                        <input class="form-control" type="email" id="smtp_from_email" name="smtp_from_email"
                               placeholder="no-reply@yourdomain.com"
                               value="<?= htmlspecialchars($s['smtp_from_email']) ?>">
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:.25rem;">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" style="width:16px;height:16px;"></i>
                        Save Settings
                    </button>
                    <button type="button" class="btn btn-outline" id="btn-test"
                            onclick="testSmtpConnection()">
                        <i data-lucide="wifi" style="width:16px;height:16px;"></i>
                        Test Connection
                    </button>
                </div>

                <div id="smtp-test-result" class="smtp-test-result" role="status"></div>

            </div>
        </div>
    </form>

</div><!-- /#tab-smtp -->

<!-- ===== Tab: Updates ===== -->
<div id="tab-updates" class="settings-tab-panel <?= $activeTab === 'updates' ? 'active' : '' ?>">

    <div class="admin-card">
        <div class="admin-card-header">
            <h2>🔄 Application Updates</h2>
            <p>Check for new versions on GitHub and apply them automatically — code + database migrations.</p>
        </div>
        <div class="admin-card-body">
            <input type="hidden" id="update-csrf-token" value="<?= htmlspecialchars(csrfToken()) ?>">

            <!-- Version display -->
            <div class="update-version-row">
                <div class="update-ver-box">
                    <div class="ver-label">Installed</div>
                    <div class="ver-value" id="ver-installed"><?= htmlspecialchars($currentVersion) ?></div>
                </div>
                <div class="update-arrow">→</div>
                <div class="update-ver-box latest">
                    <div class="ver-label">Latest</div>
                    <div class="ver-value" id="ver-latest">—</div>
                </div>
                <div>
                    <span id="update-status-badge" class="update-status-badge checking">
                        <i data-lucide="loader" style="width:13px;height:13px;"></i>
                        Checking…
                    </span>
                </div>
            </div>

            <!-- Action buttons -->
            <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center;">
                <button class="btn btn-outline" id="btn-check" onclick="checkForUpdate()">
                    <i data-lucide="search" style="width:16px;height:16px;"></i>
                    Check Again
                </button>
                <button class="btn btn-primary" id="btn-apply" onclick="applyUpdate()" disabled>
                    <i data-lucide="download" style="width:16px;height:16px;"></i>
                    Apply Update
                </button>
            </div>

            <!-- Output log -->
            <div id="update-log" class="update-log" role="status" aria-live="polite"></div>

        </div>
    </div>

</div><!-- /#tab-updates -->

<script>
/* Tab switching */
function switchTab(name) {
    document.querySelectorAll('.settings-tab-btn').forEach(function(b) {
        b.classList.toggle('active', b.getAttribute('onclick').indexOf("'" + name + "'") !== -1);
        b.setAttribute('aria-selected', b.classList.contains('active') ? 'true' : 'false');
    });
    document.querySelectorAll('.settings-tab-panel').forEach(function(p) {
        p.classList.toggle('active', p.id === 'tab-' + name);
    });
    history.replaceState(null, '', location.pathname + '?tab=' + name);
    if (name === 'updates' && document.getElementById('ver-latest').textContent === '—') {
        checkForUpdate();
    }
}

/* ---- SMTP eye toggle ---- */
function togglePassVisibility() {
    var inp = document.getElementById('smtp_pass');
    var ico = document.getElementById('eye-icon');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.setAttribute('data-lucide', inp.type === 'password' ? 'eye' : 'eye-off');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

/* ---- SMTP test ---- */
function testSmtpConnection() {
    var btn = document.getElementById('btn-test');
    var res = document.getElementById('smtp-test-result');
    var csrf = document.querySelector('input[name="csrf_token"]').value;
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" style="width:16px;height:16px;"></i> Testing…';
    res.className = 'smtp-test-result';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    fetch('<?= BASE_URL ?>/admin/ajax/test_smtp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(csrf)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        res.textContent = data.message;
        res.className   = 'smtp-test-result ' + (data.ok ? 'ok' : 'fail');
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="wifi" style="width:16px;height:16px;"></i> Test Connection';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    })
    .catch(function() {
        res.textContent = 'Request failed — check the browser console.';
        res.className   = 'smtp-test-result fail';
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="wifi" style="width:16px;height:16px;"></i> Test Connection';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
}

/* ---- Update: check ---- */
var _latestVersion = null;

function setBadge(type, text, icon) {
    var b = document.getElementById('update-status-badge');
    b.className = 'update-status-badge ' + type;
    b.innerHTML = '<i data-lucide="' + icon + '" style="width:13px;height:13px;"></i> ' + text;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function checkForUpdate() {
    var btn = document.getElementById('btn-check');
    btn.disabled = true;
    setBadge('checking', 'Checking…', 'loader');
    document.getElementById('ver-latest').textContent = '…';
    document.getElementById('btn-apply').disabled = true;

    fetch('<?= BASE_URL ?>/admin/ajax/check_update.php', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        document.getElementById('ver-latest').textContent = data.latest || '?';
        _latestVersion = data.latest;
        if (data.update_available) {
            setBadge('available', 'Update Available', 'alert-circle');
            document.getElementById('btn-apply').disabled = false;
        } else if (data.error) {
            setBadge('error', data.error, 'x-circle');
        } else {
            setBadge('up-to-date', 'Up to date', 'check-circle');
        }
    })
    .catch(function() {
        btn.disabled = false;
        setBadge('error', 'Check failed', 'x-circle');
    });
}

/* ---- Update: apply ---- */
function applyUpdate() {
    if (!confirm('This will pull the latest code from GitHub and run any pending database migrations.\n\nContinue?')) return;

    var btn  = document.getElementById('btn-apply');
    var log  = document.getElementById('update-log');
    btn.disabled = true;
    document.getElementById('btn-check').disabled = true;
    log.textContent = '';
    log.classList.add('visible');
    log.textContent = '⏳ Starting update…\n';

    fetch('<?= BASE_URL ?>/admin/ajax/apply_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(document.getElementById('update-csrf-token').value)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        log.textContent = data.log || '(no output)';
        document.getElementById('btn-check').disabled = false;
        if (data.ok) {
            setBadge('up-to-date', 'Updated!', 'check-circle');
            document.getElementById('ver-installed').textContent = _latestVersion || '?';
            log.textContent += '\n\n✅ Update complete. Reload the page to see changes.';
        } else {
            setBadge('error', 'Update failed', 'x-circle');
            btn.disabled = false;
        }
    })
    .catch(function(e) {
        log.textContent += '\n❌ Request failed: ' + e;
        setBadge('error', 'Update failed', 'x-circle');
        document.getElementById('btn-check').disabled = false;
        btn.disabled = false;
    });
}

/* Auto-check on page load when updates tab is active */
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('tab-updates').classList.contains('active')) {
        checkForUpdate();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
