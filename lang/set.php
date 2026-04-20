<?php
// ============================================================
// KCALS – Language Switcher Endpoint
// Sets the preferred language in the session and redirects back.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';

$allowed = ['en', 'el'];
$lang    = $_GET['lang'] ?? 'en';

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
