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
        $errors[] = 'Invalid submission. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = 'Please enter your email and password.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare('SELECT id, email, password_hash, full_name FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Incorrect email or password.';
            } else {
                loginUser((int)$user['id'], $user['email'], $user['full_name']);
                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Login – KCALS';
$activeNav = 'login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">KCALS<span>.</span></div>
        <p class="auth-subtitle">Welcome back! Log in to your account.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">Account created! You can now log in.</div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@example.com" autofocus required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Your password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg mt-1">
                <i data-lucide="log-in" style="width:18px;height:18px;"></i>
                Log In
            </button>
        </form>

        <p class="text-center text-small mt-2" style="color:var(--slate-mid);">
            No account yet? <a href="<?= BASE_URL ?>/register.php">Create one free</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
