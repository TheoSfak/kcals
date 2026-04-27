<?php
require_once __DIR__ . '/includes/google_sync.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    header('Location: ' . BASE_URL . '/settings.php?google=error');
    exit;
}

try {
    if (!googleSyncGetConnection((int) $_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/settings.php?google=not_connected');
        exit;
    }
    googleSyncSaveCalendarSettings(
        (int) $_SESSION['user_id'],
        (string) ($_POST['calendar_reminder_mode'] ?? 'previous_evening')
    );
    header('Location: ' . BASE_URL . '/settings.php?google=calendar_saved');
    exit;
} catch (Throwable $e) {
    error_log('google_calendar_settings.php error: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/settings.php?google=error');
    exit;
}
