<?php
// ============================================================
// KCALS – Admin Auth Guard + Settings Helpers
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';

/**
 * Abort with 403 if the current user is not a logged-in admin.
 */
function requireAdmin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php?back=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
    $db   = getDB();
    $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row  = $stmt->fetch();
    if (!$row || empty($row['is_admin'])) {
        http_response_code(403);
        die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<title>403 – Access Denied</title>
<style>
  body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;
       min-height:100vh;background:#F7F9FC;margin:0}
  div{text-align:center;padding:3rem}
  h1{color:#E74C3C;font-size:3rem;margin:0 0 .5rem}
  p{color:#5D6D7E;margin:.5rem 0 1.5rem}
  a{color:#27AE60;font-weight:600;text-decoration:none}
</style></head><body><div>
  <h1>403</h1><p>You do not have admin privileges.</p>
  <a href="' . BASE_URL . '/">← Back to App</a>
</div></body></html>');
    }
}

// ---- Settings helpers ----------------------------------------

/**
 * Retrieve multiple settings by key from the database.
 * Missing keys are returned as empty strings.
 */
function getSettings(array $keys): array {
    if (empty($keys)) return [];
    $db   = getDB();
    $in   = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ($in)");
    $stmt->execute($keys);
    $map  = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['key']] = (string) $row['value'];
    }
    foreach ($keys as $k) {
        if (!array_key_exists($k, $map)) $map[$k] = '';
    }
    return $map;
}

/**
 * Insert or update a single setting.
 */
function saveSetting(string $key, ?string $value): void {
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO settings (`key`, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $stmt->execute([$key, $value]);
}
