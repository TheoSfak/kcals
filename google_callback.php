<?php
require_once __DIR__ . '/includes/google_sync.php';

requireLogin();

$redirect = BASE_URL . '/settings.php';

try {
    if (!googleSyncIsConfigured()) {
        throw new RuntimeException('Google Sync is not configured.');
    }
    if (!empty($_GET['error'])) {
        throw new RuntimeException('Google authorization was cancelled.');
    }
    $state = (string) ($_GET['state'] ?? '');
    if ($state === '' || empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $state)) {
        throw new RuntimeException('Invalid Google authorization state.');
    }
    unset($_SESSION['google_oauth_state']);

    $code = (string) ($_GET['code'] ?? '');
    if ($code === '') {
        throw new RuntimeException('Missing Google authorization code.');
    }

    $token = googleSyncExchangeCode($code);
    $accessToken = (string) ($token['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('Google did not return an access token.');
    }
    $profile = googleSyncHttpGetJson('https://openidconnect.googleapis.com/v1/userinfo', $accessToken);
    googleSyncSaveConnection((int) $_SESSION['user_id'], $token, $profile);

    header('Location: ' . $redirect . '?google=connected');
    exit;
} catch (Throwable $e) {
    error_log('google_callback.php error: ' . $e->getMessage());
    header('Location: ' . $redirect . '?google=error');
    exit;
}
