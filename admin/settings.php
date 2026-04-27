<?php
// ============================================================
// KCALS Admin – Settings (tabs: General | SMTP | Appearance | Translations | Updates)
// ============================================================
require_once __DIR__ . '/includes/admin_auth.php';
requireAdmin();

$pageTitle   = 'Settings';
$activeAdmin = 'settings';

$smtpKeys = [
    'smtp_host', 'smtp_port', 'smtp_encryption',
    'smtp_user', 'smtp_pass', 'smtp_from_name', 'smtp_from_email',
];

$appearanceKeys = [
    'appearance_accent', 'appearance_accent_dark', 'appearance_bg',
    'appearance_font_family', 'appearance_font_size', 'appearance_border_radius',
    'appearance_site_name',
];

$generalKeys = [
    'general_site_name', 'general_tagline', 'general_default_lang',
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

// ---- Handle POST: General ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_general'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    } else {
        $allowedLangs = [];
        foreach (glob(__DIR__ . '/../lang/*.php') as $f) {
            $c = basename($f, '.php');
            if ($c !== 'set') $allowedLangs[] = $c;
        }
        try {
            $st = getDB()->query("SELECT DISTINCT lang FROM translation_overrides");
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $c) {
                if (!in_array($c, $allowedLangs, true)) $allowedLangs[] = $c;
            }
        } catch (Throwable $e) {}

        $fields = [
            'general_site_name'    => substr(trim($_POST['general_site_name']    ?? 'KCALS'), 0, 64),
            'general_tagline'      => substr(trim($_POST['general_tagline']      ?? ''), 0, 120),
            'general_default_lang' => in_array($_POST['general_default_lang'] ?? 'en', $allowedLangs, true)
                                          ? $_POST['general_default_lang'] : 'en',
        ];
        foreach ($fields as $k => $v) saveSetting($k, $v);
        header('Location: ' . BASE_URL . '/admin/settings.php?saved=1&tab=general');
        exit;
    }
}

// ---- Handle POST: Appearance ----
// ---- Handle POST: Reset Appearance ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_appearance'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    } else {
        $defaults = [
            'appearance_accent'        => '#27AE60',
            'appearance_accent_dark'   => '#1E8449',
            'appearance_bg'            => '#F7F9FC',
            'appearance_font_family'   => 'Inter',
            'appearance_font_size'     => '16',
            'appearance_border_radius' => '14',
            'appearance_site_name'     => 'KCALS',
        ];
        foreach ($defaults as $k => $v) saveSetting($k, $v);
        header('Location: ' . BASE_URL . '/admin/settings.php?saved=1&tab=appearance');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_appearance']) && !isset($_POST['reset_appearance'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    } else {
        $allowedFonts = ['Inter','Roboto','Lato','Poppins','Open Sans','Nunito','Source Sans Pro'];
        $fields = [
            'appearance_site_name'     => substr(trim($_POST['appearance_site_name'] ?? 'KCALS'), 0, 64),
            'appearance_accent'        => preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['appearance_accent']      ?? '') ? $_POST['appearance_accent']      : '#27AE60',
            'appearance_accent_dark'   => preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['appearance_accent_dark'] ?? '') ? $_POST['appearance_accent_dark'] : '#1E8449',
            'appearance_bg'            => preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['appearance_bg']          ?? '') ? $_POST['appearance_bg']          : '#F7F9FC',
            'appearance_font_family'   => in_array($_POST['appearance_font_family'] ?? '', $allowedFonts, true) ? $_POST['appearance_font_family'] : 'Inter',
            'appearance_font_size'     => (string) max(12, min(22, (int)($_POST['appearance_font_size']     ?? 16))),
            'appearance_border_radius' => (string) max(0,  min(30, (int)($_POST['appearance_border_radius'] ?? 14))),
        ];
        foreach ($fields as $k => $v) saveSetting($k, $v);
        header('Location: ' . BASE_URL . '/admin/settings.php?saved=1&tab=appearance');
        exit;
    }
}

$activeTab = $_GET['tab'] ?? 'general';
if (!in_array($activeTab, ['general', 'smtp', 'updates', 'appearance', 'translations'], true)) $activeTab = 'general';

// Load current values
$s  = getSettings($smtpKeys);
$ap = getSettings($appearanceKeys);
$gn = getSettings($generalKeys);

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
    <button class="settings-tab-btn <?= $activeTab === 'general'      ? 'active' : '' ?>"
            role="tab" aria-selected="<?= $activeTab === 'general' ? 'true' : 'false' ?>"
            onclick="switchTab('general')">
        <i data-lucide="settings"></i> General
    </button>
    <button class="settings-tab-btn <?= $activeTab === 'smtp'         ? 'active' : '' ?>"
            role="tab" aria-selected="<?= $activeTab === 'smtp' ? 'true' : 'false' ?>"
            onclick="switchTab('smtp')">
        <i data-lucide="mail"></i> SMTP
    </button>
    <button class="settings-tab-btn <?= $activeTab === 'appearance'   ? 'active' : '' ?>"
            role="tab" aria-selected="<?= $activeTab === 'appearance' ? 'true' : 'false' ?>"
            onclick="switchTab('appearance')">
        <i data-lucide="palette"></i> Appearance
    </button>
    <button class="settings-tab-btn <?= $activeTab === 'translations' ? 'active' : '' ?>"
            role="tab" aria-selected="<?= $activeTab === 'translations' ? 'true' : 'false' ?>"
            onclick="switchTab('translations')">
        <i data-lucide="languages"></i> Translations
    </button>
    <button class="settings-tab-btn <?= $activeTab === 'updates'      ? 'active' : '' ?>"
            role="tab" aria-selected="<?= $activeTab === 'updates' ? 'true' : 'false' ?>"
            onclick="switchTab('updates')">
        <i data-lucide="refresh-cw"></i> Updates
    </button>
</div>

<!-- ===== Tab: General ===== -->
<div id="tab-general" class="settings-tab-panel <?= $activeTab === 'general' ? 'active' : '' ?>">

    <form method="POST" action="<?= BASE_URL ?>/admin/settings.php" novalidate>
        <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="save_general" value="1">

        <div class="admin-card" style="margin-bottom:1.25rem;">
            <div class="admin-card-header">
                <h2>🌐 Site Identity</h2>
                <p>The site name and tagline appear in the browser tab, navbar, and footer.</p>
            </div>
            <div class="admin-card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="gn_site_name">Site Name</label>
                        <input class="form-control" type="text" id="gn_site_name" name="general_site_name"
                               maxlength="64" value="<?= htmlspecialchars($gn['general_site_name'] ?: 'KCALS') ?>">
                        <small style="color:var(--slate-light);font-size:.75rem;">Used in &lt;title&gt;, navbar brand, and footer.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gn_tagline">Tagline</label>
                        <input class="form-control" type="text" id="gn_tagline" name="general_tagline"
                               maxlength="120" value="<?= htmlspecialchars($gn['general_tagline'] ?: 'Smart Nutrition & Wellness') ?>">
                        <small style="color:var(--slate-light);font-size:.75rem;">Appended to &lt;title&gt; on the home page and meta description.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card" style="margin-bottom:1.5rem;">
            <div class="admin-card-header">
                <h2>🗣️ Default Language</h2>
                <p>Language shown to visitors who have no session preference set yet.</p>
            </div>
            <div class="admin-card-body">
                <div class="form-group" style="max-width:260px;">
                    <label class="form-label" for="gn_default_lang">Default Language</label>
                    <select class="form-control" id="gn_default_lang" name="general_default_lang">
                        <?php
                        $availLangs = [];
                        foreach (glob(__DIR__ . '/../lang/*.php') as $f) {
                            $c = basename($f, '.php');
                            if ($c !== 'set') $availLangs[$c] = $c;
                        }
                        try {
                            $st = getDB()->query("SELECT DISTINCT lang FROM translation_overrides");
                            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $c) {
                                $availLangs[$c] = $c;
                            }
                        } catch (Throwable $e) {}
                        ksort($availLangs);
                        $curDefault = $gn['general_default_lang'] ?: 'en';
                        foreach ($availLangs as $c):
                        ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $c === $curDefault ? 'selected' : '' ?>>
                            <?= htmlspecialchars(strtoupper($c)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" style="width:16px;height:16px;"></i>
            Save General Settings
        </button>
    </form>

</div><!-- /#tab-general -->

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

<!-- ===== Tab: Appearance ===== -->
<div id="tab-appearance" class="settings-tab-panel <?= $activeTab === 'appearance' ? 'active' : '' ?>">

    <form method="POST" action="<?= BASE_URL ?>/admin/settings.php" novalidate>
        <input type="hidden" name="csrf_token"       value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="save_appearance"  value="1">

        <!-- General -->
        <div class="admin-card" style="margin-bottom:1.25rem;">
            <div class="admin-card-header">
                <h2>🏷️ General</h2>
                <p>Site name shown in the browser tab and footer.</p>
            </div>
            <div class="admin-card-body">
                <div class="form-group" style="max-width:360px;">
                    <label class="form-label" for="ap_site_name">Site Name</label>
                    <input class="form-control" type="text" id="ap_site_name" name="appearance_site_name"
                           maxlength="64" value="<?= htmlspecialchars($ap['appearance_site_name'] ?? 'KCALS') ?>">
                </div>
            </div>
        </div>

        <!-- Colours -->
        <div class="admin-card" style="margin-bottom:1.25rem;">
            <div class="admin-card-header">
                <h2>🎨 Colours</h2>
                <p>Accent colour (buttons, links, badges, zones). Changes take effect immediately on save.</p>
            </div>
            <div class="admin-card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="ap_accent">Accent / Primary</label>
                        <div style="display:flex;gap:.6rem;align-items:center;">
                            <input type="color" id="ap_accent" name="appearance_accent"
                                   value="<?= htmlspecialchars($ap['appearance_accent'] ?? '#27AE60') ?>"
                                   style="width:48px;height:40px;padding:2px;border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                            <input class="form-control" type="text" id="ap_accent_hex" maxlength="7"
                                   value="<?= htmlspecialchars($ap['appearance_accent'] ?? '#27AE60') ?>"
                                   placeholder="#27AE60"
                                   style="flex:1;"
                                   oninput="syncColour(this,'ap_accent')">
                        </div>
                        <small style="color:var(--slate-light);font-size:.75rem;">Used for buttons, links, nav active state</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="ap_accent_dark">Accent Dark (hover)</label>
                        <div style="display:flex;gap:.6rem;align-items:center;">
                            <input type="color" id="ap_accent_dark" name="appearance_accent_dark"
                                   value="<?= htmlspecialchars($ap['appearance_accent_dark'] ?? '#1E8449') ?>"
                                   style="width:48px;height:40px;padding:2px;border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                            <input class="form-control" type="text" id="ap_accent_dark_hex" maxlength="7"
                                   value="<?= htmlspecialchars($ap['appearance_accent_dark'] ?? '#1E8449') ?>"
                                   placeholder="#1E8449"
                                   style="flex:1;"
                                   oninput="syncColour(this,'ap_accent_dark')">
                        </div>
                        <small style="color:var(--slate-light);font-size:.75rem;">Hover/focus state for interactive elements</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="ap_bg">Page Background</label>
                        <div style="display:flex;gap:.6rem;align-items:center;">
                            <input type="color" id="ap_bg" name="appearance_bg"
                                   value="<?= htmlspecialchars($ap['appearance_bg'] ?? '#F7F9FC') ?>"
                                   style="width:48px;height:40px;padding:2px;border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                            <input class="form-control" type="text" id="ap_bg_hex" maxlength="7"
                                   value="<?= htmlspecialchars($ap['appearance_bg'] ?? '#F7F9FC') ?>"
                                   placeholder="#F7F9FC"
                                   style="flex:1;"
                                   oninput="syncColour(this,'ap_bg')">
                        </div>
                    </div>
                </div>
                <!-- Live preview swatch -->
                <div style="margin-top:1rem;display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
                    <span style="font-size:.8rem;color:var(--slate-mid);">Preview:</span>
                    <span id="ap-preview-btn" style="display:inline-flex;align-items:center;gap:.4rem;padding:.4rem 1rem;border-radius:8px;font-weight:700;font-size:.85rem;color:#fff;background:#27AE60;transition:background .2s;">Sample Button</span>
                    <span id="ap-preview-link" style="font-size:.9rem;font-weight:600;color:#27AE60;">Sample Link</span>
                    <span id="ap-preview-bg" style="padding:.3rem .8rem;border-radius:8px;font-size:.8rem;font-weight:600;background:#F7F9FC;border:1px solid #E8EDF2;">Page BG</span>
                </div>
            </div>
        </div>

        <!-- Typography -->
        <div class="admin-card" style="margin-bottom:1.25rem;">
            <div class="admin-card-header">
                <h2>🔤 Typography</h2>
                <p>Font family and base font size. All sizes on the site scale proportionally.</p>
            </div>
            <div class="admin-card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="ap_font">Font Family</label>
                        <select class="form-control" id="ap_font" name="appearance_font_family">
                            <?php foreach (['Inter','Roboto','Lato','Poppins','Open Sans','Nunito','Source Sans Pro'] as $f): ?>
                            <option value="<?= $f ?>" <?= ($ap['appearance_font_family'] ?? 'Inter') === $f ? 'selected' : '' ?>><?= $f ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="ap_font_size">
                            Base Font Size: <strong id="ap_font_size_val"><?= $ap['appearance_font_size'] ?? 16 ?>px</strong>
                        </label>
                        <input type="range" id="ap_font_size" name="appearance_font_size"
                               min="12" max="22" step="1"
                               value="<?= (int)($ap['appearance_font_size'] ?? 16) ?>"
                               style="width:100%;"
                               oninput="document.getElementById('ap_font_size_val').textContent=this.value+'px'">
                        <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--slate-light);">
                            <span>12px (small)</span><span>16px (default)</span><span>22px (large)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shape -->
        <div class="admin-card" style="margin-bottom:1.5rem;">
            <div class="admin-card-header">
                <h2>🔲 Card Radius</h2>
                <p>Border radius applied to cards, buttons and form controls.</p>
            </div>
            <div class="admin-card-body">
                <div class="form-group" style="max-width:360px;">
                    <label class="form-label" for="ap_radius">
                        Radius: <strong id="ap_radius_val"><?= $ap['appearance_border_radius'] ?? 14 ?>px</strong>
                    </label>
                    <input type="range" id="ap_radius" name="appearance_border_radius"
                           min="0" max="30" step="1"
                           value="<?= (int)($ap['appearance_border_radius'] ?? 14) ?>"
                           style="width:100%;"
                           oninput="document.getElementById('ap_radius_val').textContent=this.value+'px'">
                    <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--slate-light);">
                        <span>0 (sharp)</span><span>14 (default)</span><span>30 (pill)</span>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" style="width:16px;height:16px;"></i>
                Save Appearance
            </button>
            <button type="submit" name="reset_appearance" value="1"
                    class="btn btn-outline"
                    onclick="return confirm('Reset all appearance settings to defaults?')">
                <i data-lucide="rotate-ccw" style="width:16px;height:16px;"></i>
                Reset to Defaults
            </button>
        </div>

    </form>

</div><!-- /#tab-appearance -->

<!-- ===== Tab: Translations ===== -->
<div id="tab-translations" class="settings-tab-panel <?= $activeTab === 'translations' ? 'active' : '' ?>">

    <!-- Language bar -->
    <div class="admin-card" style="margin-bottom:1rem;">
        <div class="admin-card-body" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:.5rem;">
                <label style="font-weight:600;font-size:.85rem;white-space:nowrap;">Language:</label>
                <select id="tr-lang-select" class="form-control" style="width:auto;min-width:140px;" onchange="trLoadLang(this.value)">
                    <!-- Populated by JS -->
                </select>
            </div>
            <button class="btn btn-outline" style="font-size:.82rem;" onclick="trShowAddLang()">
                <i data-lucide="plus-circle" style="width:15px;height:15px;"></i>
                Add Language
            </button>
            <button class="btn btn-outline" id="tr-delete-lang-btn" style="font-size:.82rem;color:#E74C3C;border-color:#E74C3C;display:none;" onclick="trDeleteLang()">
                <i data-lucide="trash-2" style="width:15px;height:15px;"></i>
                Delete Language
            </button>
            <div style="margin-left:auto;display:flex;gap:.6rem;align-items:center;">
                <input type="text" id="tr-search" class="form-control" placeholder="Filter keys…" style="width:200px;"
                       oninput="trFilterRows(this.value)">
                <label style="display:flex;align-items:center;gap:.3rem;font-size:.82rem;cursor:pointer;">
                    <input type="checkbox" id="tr-only-overrides" onchange="trFilterRows(document.getElementById('tr-search').value)">
                    Overrides only
                </label>
            </div>
        </div>
    </div>

    <!-- Add language modal -->
    <div id="tr-add-lang-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:none;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:14px;padding:2rem;width:360px;max-width:95vw;box-shadow:0 8px 40px rgba(0,0,0,.18);">
            <h3 style="margin:0 0 1rem;">Add New Language</h3>
            <div class="form-group" style="margin-bottom:.75rem;">
                <label class="form-label">Language Code (e.g. <code>fr</code>, <code>de</code>)</label>
                <input type="text" id="tr-new-lang-code" class="form-control" maxlength="10" placeholder="fr">
            </div>
            <label style="display:flex;align-items:center;gap:.5rem;font-size:.9rem;margin-bottom:1.25rem;cursor:pointer;">
                <input type="checkbox" id="tr-seed-en" checked>
                Seed all keys from English (recommended)
            </label>
            <div style="display:flex;gap:.75rem;">
                <button class="btn btn-primary" onclick="trAddLang()">Create</button>
                <button class="btn btn-outline" onclick="trHideAddLang()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Keys table -->
    <div class="admin-card">
        <div class="admin-card-body" style="padding:0;">
            <div id="tr-status" style="padding:1.5rem;color:var(--slate-mid);font-size:.9rem;text-align:center;">
                Select a language to start editing.
            </div>
            <div id="tr-table-wrap" style="display:none;overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.83rem;">
                    <thead>
                        <tr style="background:var(--bg);border-bottom:2px solid var(--border);">
                            <th style="padding:.6rem 1rem;text-align:left;font-weight:700;width:230px;">Key</th>
                            <th style="padding:.6rem 1rem;text-align:left;">Default (EN)</th>
                            <th style="padding:.6rem 1rem;text-align:left;">Override</th>
                            <th style="padding:.6rem .5rem;width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody id="tr-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /#tab-translations -->

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
    if (name === 'translations' && _trLang === null) {
        trInit();
    }
}

/* ---- Appearance: sync colour picker ↔ hex input ---- */
function syncColour(hexInput, pickerId) {
    var val = hexInput.value;
    if (/^#[0-9a-fA-F]{6}$/.test(val)) {
        document.getElementById(pickerId).value = val;
        updatePreview();
    }
}
document.addEventListener('DOMContentLoaded', function() {
    ['ap_accent','ap_accent_dark','ap_bg'].forEach(function(id) {
        var picker = document.getElementById(id);
        if (!picker) return;
        picker.addEventListener('input', function() {
            var hexId = id + '_hex';
            var hexEl = document.getElementById(hexId);
            if (hexEl) hexEl.value = picker.value;
            updatePreview();
        });
    });
    updatePreview();
});
function updatePreview() {
    var accent = (document.getElementById('ap_accent')     || {}).value || '#27AE60';
    var bg     = (document.getElementById('ap_bg')         || {}).value || '#F7F9FC';
    var btnEl  = document.getElementById('ap-preview-btn');
    var lnkEl  = document.getElementById('ap-preview-link');
    var bgEl   = document.getElementById('ap-preview-bg');
    if (btnEl) btnEl.style.background = accent;
    if (lnkEl) lnkEl.style.color      = accent;
    if (bgEl)  { bgEl.style.background = bg; }
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
    // Auto-load translations if tab is active on page load
    if (document.getElementById('tab-translations').classList.contains('active')) {
        trInit();
    }
});

// ============================================================
// Translations tab
// ============================================================
var _trCsrf   = '<?= htmlspecialchars(csrfToken()) ?>';
var _trBase   = '<?= BASE_URL ?>/admin/ajax/translation_action.php';
var _trLang   = null;
var _trAllRows = [];  // cached full key list

function trInit() {
    fetch(_trBase + '?action=languages')
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) return;
            var sel = document.getElementById('tr-lang-select');
            sel.innerHTML = '<option value="">— select —</option>';
            data.languages.forEach(function(l) {
                var opt = document.createElement('option');
                opt.value = l.code;
                opt.textContent = l.code.toUpperCase() + (l.source === 'db' ? ' (DB only)' : '');
                sel.appendChild(opt);
            });
            // If only one language, auto-select it
            if (data.languages.length === 1) {
                sel.value = data.languages[0].code;
                trLoadLang(sel.value);
            }
        });
}

function trLoadLang(lang) {
    if (!lang) return;
    _trLang = lang;
    var wrap   = document.getElementById('tr-table-wrap');
    var status = document.getElementById('tr-status');
    var delBtn = document.getElementById('tr-delete-lang-btn');
    status.style.display = 'block';
    status.textContent   = 'Loading…';
    wrap.style.display   = 'none';
    // Show delete button only for non-file languages or any non-en lang
    delBtn.style.display = (lang !== 'en') ? '' : 'none';

    fetch(_trBase + '?action=list&lang=' + encodeURIComponent(lang))
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) { status.textContent = 'Error loading keys.'; return; }
            _trAllRows = data.keys;
            status.style.display = 'none';
            wrap.style.display   = '';
            trFilterRows(document.getElementById('tr-search').value);
        })
        .catch(function() { status.textContent = 'Request failed.'; });
}

function trFilterRows(query) {
    var onlyOverrides = document.getElementById('tr-only-overrides').checked;
    var q = query.toLowerCase();
    var rows = _trAllRows.filter(function(r) {
        if (onlyOverrides && !r.is_overridden) return false;
        if (q && r.key.toLowerCase().indexOf(q) === -1
               && r.en_default.toLowerCase().indexOf(q) === -1
               && (r.db_override || '').toLowerCase().indexOf(q) === -1) return false;
        return true;
    });
    trRenderRows(rows);
}

function trRenderRows(rows) {
    var tbody = document.getElementById('tr-tbody');
    tbody.innerHTML = '';
    if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="padding:1.5rem;text-align:center;color:var(--slate-light);">No keys match.</td></tr>';
        return;
    }
    rows.forEach(function(r) {
        var tr = document.createElement('tr');
        tr.className   = 'tr-key-row' + (r.is_overridden ? ' tr-overridden' : '');
        tr.dataset.key = r.key;
        tr.style.cssText = 'border-bottom:1px solid var(--border);' + (r.is_overridden ? 'background:rgba(39,174,96,.05);' : '');

        // Key cell
        var td1 = document.createElement('td');
        td1.style.cssText = 'padding:.5rem 1rem;font-family:monospace;font-size:.78rem;color:var(--slate-mid);vertical-align:top;word-break:break-all;';
        td1.textContent = r.key;
        if (r.is_overridden) {
            var badge = document.createElement('span');
            badge.textContent = 'overridden';
            badge.style.cssText = 'display:inline-block;margin-top:.2rem;padding:.1rem .4rem;border-radius:4px;font-size:.68rem;background:rgba(39,174,96,.15);color:#1E8449;font-family:sans-serif;';
            td1.appendChild(document.createElement('br'));
            td1.appendChild(badge);
        }

        // Default value cell
        var td2 = document.createElement('td');
        td2.style.cssText = 'padding:.5rem 1rem;color:var(--slate-mid);font-size:.82rem;vertical-align:top;max-width:260px;';
        td2.textContent = r.en_default.length > 100 ? r.en_default.substring(0, 100) + '…' : r.en_default;

        // Override input cell
        var td3 = document.createElement('td');
        td3.style.cssText = 'padding:.4rem .5rem .4rem 1rem;vertical-align:top;';
        var inp = document.createElement('textarea');
        inp.rows = 2;
        inp.style.cssText = 'width:100%;min-width:220px;resize:vertical;font-size:.82rem;padding:.35rem .5rem;border:1px solid var(--border);border-radius:6px;font-family:inherit;background:#fff;';
        inp.value = r.db_override !== null ? r.db_override : (r.file_value !== null ? r.file_value : r.en_default);
        inp.placeholder = r.en_default;
        inp.dataset.originalValue = inp.value;
        inp.addEventListener('change', function() { trSaveRow(r.key, inp.value, tr, inp); });
        td3.appendChild(inp);

        // Revert button cell
        var td4 = document.createElement('td');
        td4.style.cssText = 'padding:.4rem .5rem;vertical-align:top;text-align:center;';
        if (r.is_overridden) {
            var revertBtn = document.createElement('button');
            revertBtn.title = 'Revert to default';
            revertBtn.className = 'btn btn-outline';
            revertBtn.style.cssText = 'padding:.25rem .45rem;font-size:.7rem;color:#E74C3C;border-color:#E74C3C;';
            revertBtn.innerHTML = '<i data-lucide="rotate-ccw" style="width:13px;height:13px;pointer-events:none;"></i>';
            revertBtn.addEventListener('click', function() { trDeleteRow(r.key, tr, inp, revertBtn); });
            td4.appendChild(revertBtn);
        }

        tr.appendChild(td1);
        tr.appendChild(td2);
        tr.appendChild(td3);
        tr.appendChild(td4);
        tbody.appendChild(tr);
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function trSaveRow(key, value, tr, inp) {
    var body = 'action=save&csrf_token=' + encodeURIComponent(_trCsrf)
             + '&lang=' + encodeURIComponent(_trLang)
             + '&key='  + encodeURIComponent(key)
             + '&value='+ encodeURIComponent(value);
    inp.style.borderColor = 'var(--green)';
    fetch(_trBase, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.ok) {
                inp.style.borderColor = '';
                tr.style.background = 'rgba(39,174,96,.05)';
                tr.classList.add('tr-overridden');
                // Update cached row
                var cached = _trAllRows.find(function(r){ return r.key === key; });
                if (cached) { cached.db_override = value; cached.is_overridden = true; }
            } else {
                inp.style.borderColor = '#E74C3C';
            }
        })
        .catch(function() { inp.style.borderColor = '#E74C3C'; });
}

function trDeleteRow(key, tr, inp, btn) {
    var body = 'action=delete&csrf_token=' + encodeURIComponent(_trCsrf)
             + '&lang=' + encodeURIComponent(_trLang)
             + '&key='  + encodeURIComponent(key);
    fetch(_trBase, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.ok) {
                tr.style.background = '';
                tr.classList.remove('tr-overridden');
                btn.remove();
                // Reset input to file/EN default
                var cached = _trAllRows.find(function(r){ return r.key === key; });
                if (cached) {
                    cached.db_override  = null;
                    cached.is_overridden = false;
                    inp.value = cached.file_value !== null ? cached.file_value : cached.en_default;
                }
            }
        });
}

function trShowAddLang() {
    var modal = document.getElementById('tr-add-lang-modal');
    modal.style.display = 'flex';
}
function trHideAddLang() {
    document.getElementById('tr-add-lang-modal').style.display = 'none';
}
function trAddLang() {
    var code = document.getElementById('tr-new-lang-code').value.trim().toLowerCase();
    var seed = document.getElementById('tr-seed-en').checked;
    if (!code || !/^[a-z_-]{2,10}$/.test(code)) { alert('Enter a valid 2–10 character language code.'); return; }
    var body = 'action=add_language&csrf_token=' + encodeURIComponent(_trCsrf)
             + '&code=' + encodeURIComponent(code)
             + (seed ? '&seed_from_en=1' : '');
    fetch(_trBase, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) { alert('Error: ' + data.error); return; }
            trHideAddLang();
            // Add to selector and load
            var sel = document.getElementById('tr-lang-select');
            var opt = document.createElement('option');
            opt.value = code;
            opt.textContent = code.toUpperCase() + ' (DB only)';
            sel.appendChild(opt);
            sel.value = code;
            trLoadLang(code);
        });
}

function trDeleteLang() {
    if (!_trLang || _trLang === 'en') return;
    if (!confirm('Delete ALL overrides for language "' + _trLang.toUpperCase() + '"?\n\nThis cannot be undone.')) return;
    var body = 'action=delete_language&csrf_token=' + encodeURIComponent(_trCsrf)
             + '&code=' + encodeURIComponent(_trLang);
    fetch(_trBase, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) { alert('Error: ' + data.error); return; }
            // Remove from selector
            var sel = document.getElementById('tr-lang-select');
            var opt = sel.querySelector('option[value="' + _trLang + '"]');
            if (opt) opt.remove();
            sel.value = '';
            _trLang = null;
            document.getElementById('tr-table-wrap').style.display = 'none';
            document.getElementById('tr-status').style.display = 'block';
            document.getElementById('tr-status').textContent = 'Language deleted.';
            document.getElementById('tr-delete-lang-btn').style.display = 'none';
        });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
