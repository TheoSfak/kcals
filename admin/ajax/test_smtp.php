<?php
// ============================================================
// KCALS Admin – AJAX: Test SMTP connectivity
// POST only. Verifies CSRF + admin role, then attempts a
// TCP socket connection to the saved smtp_host:smtp_port.
// Returns JSON: { "ok": bool, "message": string }
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Auth
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated.']);
    exit;
}

// CSRF
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

// Admin check
$db   = getDB();
$stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$row  = $stmt->fetch();
if (!$row || empty($row['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Access denied.']);
    exit;
}

// Read saved settings
$stmt = $db->prepare(
    "SELECT `key`, value FROM settings WHERE `key` IN ('smtp_host', 'smtp_port')"
);
$stmt->execute();
$map = [];
foreach ($stmt->fetchAll() as $r) {
    $map[$r['key']] = $r['value'];
}

$host = trim($map['smtp_host'] ?? '');
$port = max(1, min(65535, (int) ($map['smtp_port'] ?? 587)));

if ($host === '') {
    echo json_encode([
        'ok'      => false,
        'message' => 'SMTP host is not configured. Save your settings first.',
    ]);
    exit;
}

// Attempt TCP connection (5-second timeout)
set_error_handler(static fn() => true);
$conn = @fsockopen($host, $port, $errno, $errstr, 5);
restore_error_handler();

if ($conn) {
    fclose($conn);
    echo json_encode([
        'ok'      => true,
        'message' => "✓ Connected to {$host}:{$port} successfully.",
    ]);
} else {
    echo json_encode([
        'ok'      => false,
        'message' => "✗ Could not reach {$host}:{$port} — {$errstr} (#{$errno})",
    ]);
}
