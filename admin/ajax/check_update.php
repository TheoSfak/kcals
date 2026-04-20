<?php
// ============================================================
// KCALS Admin – AJAX: Check GitHub for latest version
// GET only. Returns JSON: { latest, installed, update_available, error? }
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

// Admin check
$db   = getDB();
$stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$row  = $stmt->fetch();
if (!$row || empty($row['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

// Read installed version
$versionFile       = __DIR__ . '/../../VERSION';
$installedVersion  = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'unknown';

// Query GitHub API for the latest release tag
$apiUrl  = 'https://api.github.com/repos/TheoSfak/kcals/releases/latest';
$context = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => "User-Agent: KCALS-Admin/1.0\r\nAccept: application/vnd.github+json\r\n",
        'timeout' => 8,
    ],
]);

set_error_handler(static fn() => true);
$body = @file_get_contents($apiUrl, false, $context);
restore_error_handler();

if ($body === false) {
    // Fallback: check latest commit on main branch
    $tagsUrl = 'https://api.github.com/repos/TheoSfak/kcals/tags';
    $body    = @file_get_contents($tagsUrl, false, $context);

    if ($body === false) {
        echo json_encode([
            'latest'           => null,
            'installed'        => $installedVersion,
            'update_available' => false,
            'error'            => 'Could not reach GitHub API.',
        ]);
        exit;
    }

    $tags   = json_decode($body, true);
    $latest = isset($tags[0]['name']) ? $tags[0]['name'] : null;
} else {
    $release = json_decode($body, true);
    $latest  = $release['tag_name'] ?? null;
}

if ($latest === null) {
    // No releases published yet — compare via latest commit SHA
    $commitUrl = 'https://api.github.com/repos/TheoSfak/kcals/commits/main';
    $cBody     = @file_get_contents($commitUrl, false, $context);
    $latestSha = null;
    if ($cBody !== false) {
        $commit    = json_decode($cBody, true);
        $latestSha = isset($commit['sha']) ? substr($commit['sha'], 0, 7) : null;
    }

    // Read local git HEAD
    $headFile  = __DIR__ . '/../../.git/refs/heads/main';
    $localSha  = file_exists($headFile) ? substr(trim(file_get_contents($headFile)), 0, 7) : null;

    $updateAvailable = ($latestSha !== null && $localSha !== null && $latestSha !== $localSha);

    echo json_encode([
        'latest'           => $latestSha ?? 'unknown',
        'installed'        => $localSha  ?? $installedVersion,
        'update_available' => $updateAvailable,
        'mode'             => 'commit',
    ]);
    exit;
}

// Semantic version compare (strip leading 'v')
$latestClean    = ltrim($latest,           'v');
$installedClean = ltrim($installedVersion, 'v');
$updateAvailable = version_compare($latestClean, $installedClean, '>');

echo json_encode([
    'latest'           => $latest,
    'installed'        => $installedVersion,
    'update_available' => $updateAvailable,
]);
