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
    $connection = googleSyncGetConnection((int) $_SESSION['user_id']);
    if (!$connection) {
        header('Location: ' . BASE_URL . '/settings.php?google=not_connected');
        exit;
    }
    if (!googleSyncHasCalendarScope($connection)) {
        header('Location: ' . BASE_URL . '/settings.php?google=calendar_reconnect');
        exit;
    }
    $_SESSION['google_calendar_sync_counts'] = googleSyncCalendarNow((int) $_SESSION['user_id']);
    header('Location: ' . BASE_URL . '/settings.php?google=calendar_sync_ok');
    exit;
} catch (Throwable $e) {
    error_log('google_calendar_sync.php error: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/settings.php?google=calendar_sync_error');
    exit;
}
