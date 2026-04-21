<?php
// ============================================================
// KCALS Admin – AJAX: Food Management Actions
// POST only. Requires CSRF + admin role.
// Actions: delete
// Returns JSON: { ok: bool, msg: string }
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

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

// ════════════════════════════════════════════════════════════
// DELETE
// ════════════════════════════════════════════════════════════
if ($action === 'delete') {
    $foodId = (int)($_POST['id'] ?? 0);
    if ($foodId <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid food ID.']);
        exit;
    }

    try {
        $st = $db->prepare('DELETE FROM `foods` WHERE id = ?');
        $st->execute([$foodId]);

        if ($st->rowCount() === 0) {
            echo json_encode(['ok' => false, 'msg' => 'Food not found.']);
            exit;
        }

        echo json_encode(['ok' => true, 'msg' => 'Food deleted.']);
    } catch (PDOException $e) {
        error_log('food_action delete error: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Database error.']);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
