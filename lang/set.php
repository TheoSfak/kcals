<?php
// ============================================================
// KCALS – Language Switcher Endpoint
// Sets the preferred language in the session and redirects back.
// Accepted languages: file-based (lang/*.php) + DB-only (translation_overrides).
// ============================================================
require_once __DIR__ . '/../includes/auth.php';

// Build allowed set: files + DB-only languages
$allowed = [];
foreach (glob(__DIR__ . '/*.php') as $f) {
    $code = basename($f, '.php');
    if ($code !== 'set') $allowed[] = $code;
}
try {
    $db   = getDB();
    $stmt = $db->query("SELECT DISTINCT lang FROM translation_overrides");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
        if (!in_array($code, $allowed, true)) $allowed[] = $code;
    }
} catch (Throwable $e) { /* table may not exist yet */ }

$lang = preg_replace('/[^a-z_-]/i', '', $_GET['lang'] ?? 'en');
if (in_array($lang, $allowed, true)) {
    $_SESSION['lang'] = $lang;
}

// Redirect to the referring page (relative URLs only to prevent open redirect)
$back = $_GET['back'] ?? '';
if (!preg_match('#^/[^/]#', $back) && $back !== '/') {
    $back = BASE_URL . '/';
}

header('Location: ' . $back);
exit;
