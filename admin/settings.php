<?php
// ============================================================
// KCALS Admin – Settings (SMTP)
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
            // Only overwrite the password when a new one is supplied
            $newPass = $_POST['smtp_pass'] ?? '';
            if ($newPass !== '') {
                saveSetting('smtp_pass', $newPass);
            }
            header('Location: ' . BASE_URL . '/admin/settings.php?saved=1');
            exit;
        }
    }
}

if (isset($_GET['saved'])) $saved = true;

// Load current values
$s = getSettings($smtpKeys);

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

<form method="POST" action="<?= BASE_URL ?>/admin/settings.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="save_smtp"  value="1">

    <!-- ===== SMTP Card ===== -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2>📧 SMTP Email Settings</h2>
            <p>Outgoing mail server configuration used for all application emails.</p>
        </div>
        <div class="admin-card-body">

            <!-- Row 1: Host + Port -->
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

            <!-- Row 2: Encryption -->
            <div class="form-group">
                <label class="form-label" for="smtp_encryption">Encryption</label>
                <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                    <option value="tls"  <?= $s['smtp_encryption'] === 'tls'  ? 'selected' : '' ?>>
                        TLS — recommended (port 587)
                    </option>
                    <option value="ssl"  <?= $s['smtp_encryption'] === 'ssl'  ? 'selected' : '' ?>>
                        SSL (port 465)
                    </option>
                    <option value="none" <?= $s['smtp_encryption'] === 'none' ? 'selected' : '' ?>>
                        None — port 25, not recommended
                    </option>
                </select>
            </div>

            <!-- Row 3: Username + Password -->
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
                        <button type="button" class="input-toggle-btn" id="btn-eye"
                                onclick="togglePassVisibility()" title="Show / Hide password">
                            <i data-lucide="eye" id="eye-icon" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                    <small style="font-size:.76rem;color:var(--slate-light);">
                        Leave blank to keep the stored password. For Gmail, use an
                        <a href="https://support.google.com/accounts/answer/185833"
                           target="_blank" rel="noopener" style="color:var(--green-dark);">app-specific password</a>.
                    </small>
                </div>
            </div>

            <!-- Row 4: From Name + From Email -->
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

            <!-- Actions -->
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

            <!-- Test result bubble -->
            <div id="smtp-test-result" class="smtp-test-result" role="status"></div>

        </div><!-- /.admin-card-body -->
    </div><!-- /.admin-card -->

</form>

<script>
function togglePassVisibility() {
    var inp = document.getElementById('smtp_pass');
    var ico = document.getElementById('eye-icon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.setAttribute('data-lucide', 'eye-off');
    } else {
        inp.type = 'password';
        ico.setAttribute('data-lucide', 'eye');
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

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
        res.textContent = 'Request failed — check the browser console for details.';
        res.className   = 'smtp-test-result fail';
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="wifi" style="width:16px;height:16px;"></i> Test Connection';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
