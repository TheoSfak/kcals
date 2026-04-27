<?php
require_once __DIR__ . '/includes/google_sync.php';

requireLogin();

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || !verifyCsrf($_POST['csrf_token'] ?? '')
    || ($_POST['confirm_restore'] ?? '') !== '1'
) {
    header('Location: ' . BASE_URL . '/settings.php?google=error');
    exit;
}

try {
    if (!googleSyncIsConfigured()) {
        header('Location: ' . BASE_URL . '/settings.php?google=config');
        exit;
    }
    if (!googleSyncGetConnection((int) $_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/settings.php?google=not_connected');
        exit;
    }
    $_SESSION['google_restore_counts'] = googleSyncRestoreDriveBackup((int) $_SESSION['user_id']);
    unset($_SESSION['google_restore_preview']);
    header('Location: ' . BASE_URL . '/settings.php?google=restore_ok');
    exit;
} catch (Throwable $e) {
    error_log('google_restore.php error: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/settings.php?google=restore_error');
    exit;
}
