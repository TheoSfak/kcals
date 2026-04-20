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
        $errors[] = 'Invalid form submission. Please try again.';
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
        if (empty($old['full_name']))  $errors[] = 'Full name is required.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (!in_array($old['gender'], ['male','female']))      $errors[] = 'Please select a gender.';
        if (empty($old['birth_date']))   $errors[] = 'Date of birth is required.';
        if ($old['height_cm'] < 100 || $old['height_cm'] > 250) $errors[] = 'Height must be between 100 and 250 cm.';
        if ($old['weight_kg'] < 30  || $old['weight_kg'] > 300) $errors[] = 'Weight must be between 30 and 300 kg.';
        if (strlen($password) < 8)       $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $password2)    $errors[] = 'Passwords do not match.';

        // Age check (must be 16+)
        if (!empty($old['birth_date'])) {
            $birthDt = new DateTime($old['birth_date']);
            $age     = (new DateTime())->diff($birthDt)->y;
            if ($age < 16) $errors[] = 'You must be at least 16 years old to register.';
        }

        if (empty($errors)) {
            $db = getDB();

            // Duplicate email check
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'This email is already registered. <a href="' . BASE_URL . '/login.php">Log in instead?</a>';
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

$pageTitle = 'Create Account – KCALS';
$activeNav = '';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page" style="align-items:flex-start; padding-top: 3rem; padding-bottom: 3rem;">
    <div class="auth-card" style="max-width:560px;">
        <div class="auth-logo">KCALS<span>.</span></div>
        <p class="auth-subtitle">Create your free account — takes 2 minutes</p>

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
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control"
                       value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
                       placeholder="e.g. Maria Papadopoulou" required maxlength="150">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                       placeholder="you@example.com" required>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Min. 8 characters" required>
                </div>
                <div class="form-group">
                    <label for="password2">Confirm Password</label>
                    <input type="password" id="password2" name="password2" class="form-control"
                           placeholder="Repeat password" required>
                </div>
            </div>

            <!-- Body Data -->
            <div style="border-top:1px solid var(--border); padding-top:1.25rem; margin-top:0.5rem; margin-bottom:1.25rem;">
                <p class="text-small fw-600" style="color:var(--slate-mid); text-transform:uppercase; letter-spacing:.5px; margin-bottom:1rem;">Body & Lifestyle</p>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="">Select…</option>
                            <option value="male"   <?= ($old['gender'] ?? '')==='male'   ? 'selected':'' ?>>Male</option>
                            <option value="female" <?= ($old['gender'] ?? '')==='female' ? 'selected':'' ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="birth_date">Date of Birth</label>
                        <input type="date" id="birth_date" name="birth_date" class="form-control"
                               value="<?= htmlspecialchars($old['birth_date'] ?? '') ?>"
                               max="<?= date('Y-m-d', strtotime('-16 years')) ?>" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="height_cm">Height (cm)</label>
                        <input type="number" id="height_cm" name="height_cm" class="form-control"
                               value="<?= htmlspecialchars($old['height_cm'] ?? '') ?>"
                               min="100" max="250" placeholder="e.g. 170" required>
                    </div>
                    <div class="form-group">
                        <label for="weight_kg">Current Weight (kg)</label>
                        <input type="number" id="weight_kg" name="weight_kg" class="form-control"
                               value="<?= htmlspecialchars($old['weight_kg'] ?? '') ?>"
                               min="30" max="300" step="0.1" placeholder="e.g. 72.5" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="activity_level">Activity Level</label>
                    <select id="activity_level" name="activity_level" class="form-control">
                        <?php
                        $actLevels = [
                            '1.20'=>'Sedentary (desk job, no exercise)',
                            '1.375'=>'Lightly Active (exercise 1-3x/week)',
                            '1.55'=>'Moderately Active (exercise 3-5x/week)',
                            '1.725'=>'Very Active (hard exercise 6-7x/week)',
                            '1.90'=>'Extra Active (physical job + hard training)',
                        ];
                        foreach ($actLevels as $val => $label):
                            $sel = (($old['activity_level'] ?? '1.20') == $val) ? 'selected' : '';
                        ?>
                        <option value="<?= $val ?>" <?= $sel ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="diet_type">Diet Preference</label>
                    <select id="diet_type" name="diet_type" class="form-control">
                        <?php
                        $dietTypes = ['standard'=>'Standard (no restrictions)','vegan'=>'Vegan','gluten_free'=>'Gluten-Free','vegan_gf'=>'Vegan & Gluten-Free'];
                        foreach ($dietTypes as $val => $label):
                            $sel = (($old['diet_type'] ?? 'standard') == $val) ? 'selected' : '';
                        ?>
                        <option value="<?= $val ?>" <?= $sel ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">
                <i data-lucide="user-check" style="width:18px;height:18px;"></i>
                Create My Account
            </button>
        </form>

        <p class="text-center text-small mt-2" style="color:var(--slate-mid);">
            Already have an account? <a href="<?= BASE_URL ?>/login.php">Log in here</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
