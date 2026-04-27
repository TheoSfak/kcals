<?php
require_once __DIR__ . '/includes/google_sync.php';

requireLogin();

if (!googleSyncIsConfigured()) {
    header('Location: ' . BASE_URL . '/settings.php?google=config');
    exit;
}

header('Location: ' . googleSyncBuildAuthUrl());
exit;
