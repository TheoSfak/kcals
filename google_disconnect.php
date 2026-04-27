<?php
require_once __DIR__ . '/includes/google_sync.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    header('Location: ' . BASE_URL . '/settings.php?google=error');
    exit;
}

googleSyncDisconnect((int) $_SESSION['user_id']);
unset($_SESSION['google_oauth_state']);

header('Location: ' . BASE_URL . '/settings.php?google=disconnected');
exit;
