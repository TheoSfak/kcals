<?php
require_once __DIR__ . '/includes/google_sync.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
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
    $_SESSION['google_restore_preview'] = googleSyncPreviewDriveBackup((int) $_SESSION['user_id']);
    header('Location: ' . BASE_URL . '/settings.php?google=preview_ok');
    exit;
} catch (Throwable $e) {
    error_log('google_restore_preview.php error: ' . $e->getMessage());
    unset($_SESSION['google_restore_preview']);
    header('Location: ' . BASE_URL . '/settings.php?google=preview_error');
    exit;
}
