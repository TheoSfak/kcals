<?php
// ============================================================
// KCALS Admin – HTML Head + Sidebar
// Variables expected: $pageTitle (string), $activeAdmin (string)
// ============================================================
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — KCALS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<div class="admin-layout">

    <!-- ===== Sidebar ===== -->
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <a href="<?= BASE_URL ?>/admin/index.php">
                KCALS<span>.</span><em>admin</em>
            </a>
        </div>

        <div class="admin-nav-section">
            <div class="admin-nav-title">Overview</div>
            <a href="<?= BASE_URL ?>/admin/index.php"
               class="admin-nav-link <?= ($activeAdmin ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i data-lucide="layout-dashboard"></i>Dashboard
            </a>
        </div>

        <div class="admin-nav-section">
            <div class="admin-nav-title">Configuration</div>
            <a href="<?= BASE_URL ?>/admin/settings.php"
               class="admin-nav-link <?= ($activeAdmin ?? '') === 'settings' ? 'active' : '' ?>">
                <i data-lucide="settings"></i>Settings
            </a>
        </div>

        <div class="admin-nav-section admin-nav-bottom">
            <a href="<?= BASE_URL ?>/index.php" class="admin-nav-link">
                <i data-lucide="arrow-left"></i>Back to App
            </a>
            <a href="<?= BASE_URL ?>/logout.php" class="admin-nav-link admin-nav-logout">
                <i data-lucide="log-out"></i>Log Out
            </a>
        </div>
    </aside>

    <!-- ===== Main ===== -->
    <div class="admin-main">

        <header class="admin-topbar">
            <h1 class="admin-page-title"><?= htmlspecialchars($pageTitle ?? 'Admin') ?></h1>
            <div class="admin-user-info">
                <i data-lucide="user-circle" style="width:16px;height:16px;"></i>
                <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
            </div>
        </header>

        <div class="admin-content">
            <div class="admin-content-inner">
