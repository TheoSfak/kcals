<?php
// ============================================================
// KCALS – Register Page
// ============================================================
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$errors  = [];
$success = false;
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = __('err_invalid_submit');
    } else {
        // Sanitise / collect inputs
        $old = [
            'full_name'      => trim($_POST['full_name']      ?? ''),
            'email'          => trim($_POST['email']          ?? ''),
            'gender'         => $_POST['gender']              ?? '',
            'birth_date'     => $_POST['birth_date']          ?? '',
            'height_cm'      => (int) ($_POST['height_cm']    ?? 0),
            'activity_level' => $_POST['activity_level']      ?? '1.20',
            'diet_type'      => $_POST['diet_type']           ?? 'standard',
            'weight_kg'      => (float) ($_POST['weight_kg']  ?? 0),
        ];
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        // Validation
        if (empty($old['full_name']))  $errors[] = __('err_full_name');
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = __('err_email');
        if (!in_array($old['gender'], ['male','female']))      $errors[] = __('err_gender');
        if (empty($old['birth_date']))   $errors[] = __('err_dob');
        if ($old['height_cm'] < 100 || $old['height_cm'] > 250) $errors[] = __('err_height');
        if ($old['weight_kg'] < 30  || $old['weight_kg'] > 300) $errors[] = __('err_weight_reg');
        if (strlen($password) < 8)       $errors[] = __('err_password_short');
        if ($password !== $password2)    $errors[] = __('err_password_match');

        // Age check (must be 16+)
        if (!empty($old['birth_date'])) {
            $birthDt = new DateTime($old['birth_date']);
            $age     = (new DateTime())->diff($birthDt)->y;
            if ($age < 16) $errors[] = __('err_age');
        }

        if (empty($errors)) {
            $db = getDB();

            // Duplicate email check
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            if ($stmt->fetch()) {
                $errors[] = sprintf(__('err_email_taken'), htmlspecialchars(BASE_URL . '/login.php'));
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                $ins = $db->prepare('
                    INSERT INTO users (email, password_hash, full_name, gender, birth_date, height_cm, activity_level, diet_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $ins->execute([
                    $old['email'], $hash, $old['full_name'], $old['gender'],
                    $old['birth_date'], $old['height_cm'],
                    $old['activity_level'], $old['diet_type'],
                ]);
                $userId = (int) $db->lastInsertId();

                // Insert first progress entry (weight)
                $prog = $db->prepare('
                    INSERT INTO user_progress (user_id, weight_kg, stress_level, motivation_level, entry_date)
                    VALUES (?, ?, 5, 5, CURDATE())
                ');
                $prog->execute([$userId, $old['weight_kg']]);

                loginUser($userId, $old['email'], $old['full_name']);
                header('Location: ' . BASE_URL . '/dashboard.php?welcome=1');
                exit;
            }
        }
    }
}

$pageTitle = __('register_title');
$activeNav = '';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page" style="align-items:flex-start; padding-top: 3rem; padding-bottom: 3rem;">
    <div class="auth-card" style="max-width:560px;">
        <div class="auth-logo">KCALS<span>.</span></div>
        <p class="auth-subtitle"><?= __('register_subtitle') ?></p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?>
                    <div><?= $e ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/register.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

            <!-- Personal Info -->
            <div class="form-group">
                <label for="full_name"><?= __('register_name') ?></label>
                <input type="text" id="full_name" name="full_name" class="form-control"
                       value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
                       placeholder="e.g. Maria Papadopoulou" required maxlength="150">
            </div>

            <div class="form-group">
                <label for="email"><?= __('register_email') ?></label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                       placeholder="you@example.com" required>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="password"><?= __('register_password') ?></label>
                    <div class="input-icon-wrap">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="<?= htmlspecialchars(__('register_password_ph')) ?>" required>
                        <button type="button" class="input-toggle-btn" onclick="togglePwd('password','eye-pw1')" title="Show / hide password" aria-label="Show or hide password">
                            <i data-lucide="eye" id="eye-pw1"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password2"><?= __('register_password2') ?></label>
                    <div class="input-icon-wrap">
                        <input type="password" id="password2" name="password2" class="form-control"
                               placeholder="<?= htmlspecialchars(__('register_password2_ph')) ?>" required>
                        <button type="button" class="input-toggle-btn" onclick="togglePwd('password2','eye-pw2')" title="Show / hide confirm password" aria-label="Show or hide confirm password">
                            <i data-lucide="eye" id="eye-pw2"></i>
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
            </div>

            <!-- Body Data -->
            <div style="border-top:1px solid var(--border); padding-top:1.25rem; margin-top:0.5rem; margin-bottom:1.25rem;">
                <p class="text-small fw-600" style="color:var(--slate-mid); text-transform:uppercase; letter-spacing:.5px; margin-bottom:1rem;"><?= __('register_body_title') ?></p>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="gender"><?= __('register_gender') ?></label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value=""><?= __('register_gender_sel') ?></option>
                            <option value="male"   <?= ($old['gender'] ?? '')==='male'   ? 'selected':'' ?>><?= __('register_gender_male') ?></option>
                            <option value="female" <?= ($old['gender'] ?? '')==='female' ? 'selected':'' ?>><?= __('register_gender_female') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="birth_date"><?= __('register_dob') ?></label>
                        <input type="date" id="birth_date" name="birth_date" class="form-control"
                               value="<?= htmlspecialchars($old['birth_date'] ?? '') ?>"
                               max="<?= date('Y-m-d', strtotime('-16 years')) ?>" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="height_cm"><?= __('register_height') ?></label>
                        <input type="number" id="height_cm" name="height_cm" class="form-control"
                               value="<?= htmlspecialchars($old['height_cm'] ?? '') ?>"
                               min="100" max="250" placeholder="e.g. 170" required>
                    </div>
                    <div class="form-group">
                        <label for="weight_kg"><?= __('register_weight') ?></label>
                        <input type="number" id="weight_kg" name="weight_kg" class="form-control"
                               value="<?= htmlspecialchars($old['weight_kg'] ?? '') ?>"
                               min="30" max="300" step="0.1" placeholder="e.g. 72.5" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="activity_level"><?= __('register_activity') ?></label>
                    <select id="activity_level" name="activity_level" class="form-control">
                        <?php
                        $actLevels = [
                            '1.20'  => __('activity_sedentary'),
                            '1.375' => __('activity_light'),
                            '1.55'  => __('activity_moderate'),
                            '1.725' => __('activity_very'),
                            '1.90'  => __('activity_extra'),
                        ];
                        foreach ($actLevels as $val => $label):
                            $sel = (($old['activity_level'] ?? '1.20') == $val) ? 'selected' : '';
                        ?>
                        <option value="<?= $val ?>" <?= $sel ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="diet_type"><?= __('register_diet') ?></label>
                    <select id="diet_type" name="diet_type" class="form-control">
                        <?php
                        $dietTypes = [
                            'standard'   => __('diet_standard'),
                            'vegan'      => __('diet_vegan'),
                            'gluten_free'=> __('diet_gf'),
                            'vegan_gf'   => __('diet_vegan_gf'),
                        ];
                        foreach ($dietTypes as $val => $label):
                            $sel = (($old['diet_type'] ?? 'standard') == $val) ? 'selected' : '';
                        ?>
                        <option value="<?= $val ?>" <?= $sel ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">
                <i data-lucide="user-check" style="width:18px;height:18px;"></i>
                <?= __('register_btn') ?>
            </button>
        </form>

        <p class="text-center text-small mt-2" style="color:var(--slate-mid);">
            <?= sprintf(__('register_login_link'), htmlspecialchars(BASE_URL . '/login.php')) ?>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
