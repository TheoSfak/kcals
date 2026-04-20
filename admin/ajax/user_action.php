<?php
// ============================================================
// KCALS Admin – AJAX: User Management Actions
// POST only. Requires CSRF + admin role.
// Actions: update | toggle_active | delete
// Returns JSON: { ok: bool, msg: string, ... }
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// ---- Guards ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed.']);
    exit;
}
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Not authenticated.']);
    exit;
}
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'msg' => 'Invalid CSRF token.']);
    exit;
}

$db     = getDB();
$selfId = (int)$_SESSION['user_id'];

// Verify caller is admin
$chk = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
$chk->execute([$selfId]);
$caller = $chk->fetch();
if (!$caller || empty($caller['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Access denied.']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = (int)($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid user ID.']);
    exit;
}

// Verify target user exists
$fetchUser = $db->prepare('SELECT * FROM users WHERE id = ?');
$fetchUser->execute([$userId]);
$target = $fetchUser->fetch();
if (!$target) {
    echo json_encode(['ok' => false, 'msg' => 'User not found.']);
    exit;
}

// =========================================================
switch ($action) {

    // ── Update profile fields ─────────────────────────────
    case 'update':
        $fullName      = trim($_POST['full_name']      ?? '');
        $email         = strtolower(trim($_POST['email'] ?? ''));
        $dietType      = trim($_POST['diet_type']      ?? 'standard');
        $activityLevel = trim($_POST['activity_level'] ?? '1.20');
        $newPassword   = $_POST['new_password'] ?? '';
        $isAdmin       = isset($_POST['is_admin']) ? 1 : 0;

        // ---- Validation ----
        if (strlen($fullName) < 2 || strlen($fullName) > 150) {
            echo json_encode(['ok' => false, 'msg' => 'Full name must be 2–150 characters.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid email address.']);
            exit;
        }

        // Email uniqueness (exclude current user)
        $emailChk = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $emailChk->execute([$email, $userId]);
        if ($emailChk->fetchColumn()) {
            echo json_encode(['ok' => false, 'msg' => 'That email is already used by another account.']);
            exit;
        }

        $validDiets = ['standard', 'vegetarian', 'vegan', 'keto', 'paleo'];
        if (!in_array($dietType, $validDiets, true)) $dietType = 'standard';

        $validActivity = ['1.20', '1.375', '1.55', '1.725', '1.90'];
        if (!in_array($activityLevel, $validActivity, true)) $activityLevel = '1.20';

        // Can't demote yourself
        if ($userId === $selfId) $isAdmin = 1;

        // Build update query
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                echo json_encode(['ok' => false, 'msg' => 'New password must be at least 8 characters.']);
                exit;
            }
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $upd  = $db->prepare('UPDATE users SET full_name=?, email=?, diet_type=?, activity_level=?, is_admin=?, password_hash=? WHERE id=?');
            $upd->execute([$fullName, $email, $dietType, $activityLevel, $isAdmin, $hash, $userId]);
        } else {
            $upd = $db->prepare('UPDATE users SET full_name=?, email=?, diet_type=?, activity_level=?, is_admin=? WHERE id=?');
            $upd->execute([$fullName, $email, $dietType, $activityLevel, $isAdmin, $userId]);
        }

        // Return updated user row for JS to reflect immediately
        $fetchUser->execute([$userId]);
        $updated = $fetchUser->fetch();
        echo json_encode([
            'ok'   => true,
            'msg'  => 'User updated.',
            'user' => [
                'id'        => $updated['id'],
                'full_name' => $updated['full_name'],
                'email'     => $updated['email'],
                'diet_type' => $updated['diet_type'],
                'is_admin'  => (int)$updated['is_admin'],
            ],
        ]);
        break;

    // ── Toggle active / inactive ──────────────────────────
    case 'toggle_active':
        // Prevent self-deactivation
        if ($userId === $selfId) {
            echo json_encode(['ok' => false, 'msg' => 'You cannot deactivate your own account.']);
            exit;
        }
        $newState = $target['is_active'] ? 0 : 1;
        $upd      = $db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $upd->execute([$newState, $userId]);
        echo json_encode(['ok' => true, 'msg' => 'Status updated.', 'is_active' => $newState]);
        break;

    // ── Delete user ───────────────────────────────────────
    case 'delete':
        // Prevent self-deletion
        if ($userId === $selfId) {
            echo json_encode(['ok' => false, 'msg' => 'You cannot delete your own account.']);
            exit;
        }
        // CASCADE FK constraints handle related data (plans, progress, dislikes)
        $del = $db->prepare('DELETE FROM users WHERE id = ?');
        $del->execute([$userId]);
        echo json_encode(['ok' => true, 'msg' => 'User deleted.']);
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
}
