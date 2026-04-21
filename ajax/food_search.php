<?php
// ============================================================
// KCALS – Food Search AJAX Endpoint
// GET ?q=<query>
// Returns JSON array of matching foods [{id, name_en, name_el}]
// Requires login (session must be active).
// ============================================================
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo '[]';
    exit;
}

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2 || mb_strlen($q) > 100) {
    header('Content-Type: application/json');
    echo '[]';
    exit;
}

$db   = getDB();
$like = '%' . $q . '%';

try {
    $stmt = $db->prepare('
        SELECT `id`, `name_en`, `name_el`
        FROM   `foods`
        WHERE  `name_en` LIKE ? OR `name_el` LIKE ?
        ORDER  BY `name_en`
        LIMIT  30
    ');
    $stmt->execute([$like, $like]);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('food_search.php error: ' . $e->getMessage());
    $rows = [];
}

header('Content-Type: application/json');
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
