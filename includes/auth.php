<?php
// ============================================================
// KCALS – Authentication & Session Helpers
// ============================================================

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

// ------------------------------------------------------------------
// CSRF helpers
// ------------------------------------------------------------------
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ------------------------------------------------------------------
// Auth helpers
// ------------------------------------------------------------------
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function loginUser(int $id, string $email, string $name): void {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $id;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name']  = $name;
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ------------------------------------------------------------------
// User data helper
// ------------------------------------------------------------------
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

// ------------------------------------------------------------------
// Latest progress record
// ------------------------------------------------------------------
function getLatestProgress(int $userId): ?array {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM user_progress WHERE user_id = ? ORDER BY entry_date DESC LIMIT 1'
    );
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}
