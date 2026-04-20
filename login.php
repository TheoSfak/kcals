<?php
// ============================================================
// KCALS – Login Page
// ============================================================
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = __('err_invalid_submit');
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = __('err_email_empty');
        } else {
            $db   = getDB();
            $stmt = $db->prepare('SELECT id, email, password_hash, full_name, is_active FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = __('err_credentials');
            } elseif (empty($user['is_active'])) {
                $errors[] = __('err_account_disabled');
            } else {
                loginUser((int)$user['id'], $user['email'], $user['full_name']);
                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            }
        }
    }
}

$pageTitle = __('login_title');
$activeNav = 'login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">KCALS<span>.</span></div>
        <p class="auth-subtitle"><?= __('login_subtitle') ?></p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success"><?= __('login_registered') ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

            <div class="form-group">
                <label for="email"><?= __('login_email') ?></label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@example.com" autofocus required>
            </div>

            <div class="form-group">
                <label for="password"><?= __('login_password') ?></label>
                <div class="input-icon-wrap">
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="<?= htmlspecialchars(__('login_password_ph')) ?>" required>
                    <button type="button" class="input-toggle-btn" onclick="togglePwd('password','eye-login')" title="Show / hide password" aria-label="Show or hide password">
                        <i data-lucide="eye" id="eye-login"></i>
                    </button>
                </div>
            </div>

<script>
function togglePwd(fieldId, iconId) {
    var f = document.getElementById(fieldId);
    var i = document.getElementById(iconId);
    if (f.type === 'password') {
        f.type = 'text';
        i.setAttribute('data-lucide', 'eye-off');
    } else {
        f.type = 'password';
        i.setAttribute('data-lucide', 'eye');
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
</script>

            <button type="submit" class="btn btn-primary btn-block btn-lg mt-1">
                <i data-lucide="log-in" style="width:18px;height:18px;"></i>
                <?= __('login_btn') ?>
            </button>
        </form>

        <p class="text-center text-small mt-2" style="color:var(--slate-mid);">
            <?= sprintf(__('login_register_link'), htmlspecialchars(BASE_URL . '/register.php')) ?>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
