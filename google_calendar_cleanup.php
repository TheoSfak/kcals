<?php
require_once __DIR__ . '/includes/google_sync.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    header('Location: ' . BASE_URL . '/settings.php?google=error');
    exit;
}

$action = (string) ($_POST['calendar_action'] ?? '');
if (!in_array($action, ['remove', 'resync'], true)) {
    header('Location: ' . BASE_URL . '/settings.php?google=calendar_cleanup_error');
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

    if ($action === 'remove') {
        $_SESSION['google_calendar_cleanup_counts'] = googleSyncCalendarRemoveEvents((int) $_SESSION['user_id']);
        header('Location: ' . BASE_URL . '/settings.php?google=calendar_removed');
        exit;
    }

    $_SESSION['google_calendar_resync_counts'] = googleSyncCalendarCleanResync((int) $_SESSION['user_id']);
    header('Location: ' . BASE_URL . '/settings.php?google=calendar_resync_ok');
    exit;
} catch (Throwable $e) {
    error_log('google_calendar_cleanup.php error: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/settings.php?google=calendar_cleanup_error');
    exit;
}
