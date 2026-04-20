<?php
// ============================================================
// KCALS Admin – AJAX: Apply Update from GitHub
// POST only. Requires CSRF + admin role.
// Steps:
//   1. git pull origin main
//   2. Detect + run any pending SQL migration files
//   3. Update VERSION file from repo
// Returns JSON: { ok: bool, log: string }
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// ---- Guards ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'log' => 'Method not allowed.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'log' => 'Not authenticated.']);
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'log' => 'Invalid CSRF token.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$row  = $stmt->fetch();
if (!$row || empty($row['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'log' => 'Access denied.']);
    exit;
}

// ---- Helpers ----
$log = '';

function runCmd(string $cmd, string $cwd, string &$log): bool {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, $cwd);
    if (!is_resource($proc)) {
        $log .= "❌ Failed to start: $cmd\n";
        return false;
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    if ($stdout) $log .= $stdout . "\n";
    if ($stderr) $log .= $stderr . "\n";

    return $exit === 0;
}

// ---- Step 1: git pull ----
$repoDir = realpath(__DIR__ . '/../../');
$log    .= "📂 Working directory: $repoDir\n\n";
$log    .= "⬇️  Running: git pull origin main\n";

$gitOk = runCmd('git pull origin main 2>&1', $repoDir, $log);

if (!$gitOk) {
    // Try common Windows git paths
    foreach (['C:\\Program Files\\Git\\bin\\git.exe', 'C:\\Program Files (x86)\\Git\\bin\\git.exe'] as $gitPath) {
        if (file_exists($gitPath)) {
            $log   .= "Retrying with: $gitPath\n";
            $gitOk  = runCmd('"' . $gitPath . '" pull origin main 2>&1', $repoDir, $log);
            break;
        }
    }
}

if (!$gitOk) {
    echo json_encode(['ok' => false, 'log' => $log . "\n❌ git pull failed. Ensure git is in PATH and the web user has permissions."]);
    exit;
}

$log .= "\n✅ Code pulled successfully.\n\n";

// ---- Step 2: pending SQL migrations ----
$migrationsDir = $repoDir . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'migrations';

// Ensure tracking table exists (idempotent)
$db->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `filename`   VARCHAR(255) NOT NULL UNIQUE,
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fetch already-applied migrations
$applied = [];
foreach ($db->query("SELECT filename FROM schema_migrations")->fetchAll() as $r) {
    $applied[] = $r['filename'];
}

// Collect all .sql files, sort ascending so order is preserved
$sqlFiles = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql');
if ($sqlFiles) {
    sort($sqlFiles);
}

$pendingFiles = [];
foreach ($sqlFiles as $path) {
    $basename = basename($path);
    if (!in_array($basename, $applied, true)) {
        $pendingFiles[] = $path;
    }
}

if (empty($pendingFiles)) {
    $log .= "🗃️  No pending database migrations.\n";
} else {
    $log .= "🗃️  Running " . count($pendingFiles) . " pending migration(s):\n";

    foreach ($pendingFiles as $path) {
        $basename = basename($path);
        $log     .= "   → $basename … ";

        $sql = file_get_contents($path);
        if ($sql === false) {
            $log .= "❌ Could not read file.\n";
            continue;
        }

        try {
            // Execute each statement separated by semicolons
            $db->exec($sql);
            // Record as applied
            $ins = $db->prepare("INSERT IGNORE INTO schema_migrations (filename) VALUES (?)");
            $ins->execute([$basename]);
            $log .= "✅ Done\n";
        } catch (PDOException $e) {
            $log .= "❌ Error: " . $e->getMessage() . "\n";
            echo json_encode(['ok' => false, 'log' => $log]);
            exit;
        }
    }
}

// ---- Step 3: Re-read VERSION from disk (git pull may have updated it) ----
$versionFile = $repoDir . DIRECTORY_SEPARATOR . 'VERSION';
$newVersion  = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'unknown';
$log        .= "\n🏷️  Version is now: $newVersion\n";
$log        .= "\n🎉 Update finished successfully!\n";

echo json_encode(['ok' => true, 'log' => $log, 'version' => $newVersion]);
