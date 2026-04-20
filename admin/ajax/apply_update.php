<?php
// ============================================================
// KCALS Admin – AJAX: Apply Update from GitHub
// POST only. Requires CSRF + admin role.
// Steps:
//   1. git pull origin main
//   2. Ensure schema_migrations table exists (bootstrap)
//   3. Detect + run any new sql/migrations/*.sql files
//   4. Re-read VERSION from disk
// Returns JSON: { ok: bool, log: string, version?: string }
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

// =========================================================
// Helpers
// =========================================================

$log     = '';
$repoDir = realpath(__DIR__ . '/../../');

/**
 * Run a shell command via proc_open, capture stdout+stderr.
 * Returns true on exit-code 0.
 */
function runCmd(string $cmd, string $cwd, string &$log): bool
{
    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = proc_open($cmd, $desc, $pipes, $cwd);
    if (!is_resource($proc)) {
        $log .= "❌ Failed to start process: $cmd\n";
        return false;
    }
    fclose($pipes[0]);
    $out  = stream_get_contents($pipes[1]);
    $err  = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    if ($out) $log .= rtrim($out) . "\n";
    if ($err) $log .= rtrim($err) . "\n";
    return $exit === 0;
}

// Split a SQL file into individual statements.
// Handles: -- and # line comments, block comments,
// single/double/backtick-quoted identifiers, escaped chars.
// Skips CREATE DATABASE / USE statements (DB already exists).
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $current    = '';
    $len        = strlen($sql);
    $i          = 0;

    while ($i < $len) {
        $c    = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        // -- line comment
        if ($c === '-' && $next === '-') {
            while ($i < $len && $sql[$i] !== "\n") $i++;
            continue;
        }

        // # line comment
        if ($c === '#') {
            while ($i < $len && $sql[$i] !== "\n") $i++;
            continue;
        }

        // /* block comment */
        if ($c === '/' && $next === '*') {
            $i += 2;
            while ($i < $len) {
                if ($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/') { $i += 2; break; }
                $i++;
            }
            continue;
        }

        // Quoted token: ', ", `
        if ($c === "'" || $c === '"' || $c === '`') {
            $q        = $c;
            $current .= $c;
            $i++;
            while ($i < $len) {
                $qc = $sql[$i];
                if ($qc === '\\') {                          // backslash escape
                    $current .= $qc . ($sql[$i + 1] ?? '');
                    $i += 2;
                    continue;
                }
                if ($qc === $q) {
                    if (($sql[$i + 1] ?? '') === $q) {       // doubled-quote escape
                        $current .= $qc . $qc;
                        $i += 2;
                        continue;
                    }
                    $current .= $qc;
                    $i++;
                    break;
                }
                $current .= $qc;
                $i++;
            }
            continue;
        }

        // Statement terminator
        if ($c === ';') {
            $trimmed = trim($current);
            if ($trimmed !== '') {
                // Skip statements we must not run on an existing DB
                if (!preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/i', $trimmed)) {
                    $statements[] = $trimmed;
                }
            }
            $current = '';
            $i++;
            continue;
        }

        $current .= $c;
        $i++;
    }

    $trimmed = trim($current);
    if ($trimmed !== '' && !preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/i', $trimmed)) {
        $statements[] = $trimmed;
    }

    return $statements;
}

// =========================================================
// Step 1 — git pull
// =========================================================
$log .= "📂 Repo: $repoDir\n\n";
$log .= "⬇️  git pull origin main\n";

$gitCmd = 'git pull origin main 2>&1';
$gitOk  = runCmd($gitCmd, $repoDir, $log);

if (!$gitOk) {
    // Try explicit git path (common Windows installations)
    foreach ([
        'C:\\Program Files\\Git\\bin\\git.exe',
        'C:\\Program Files (x86)\\Git\\bin\\git.exe',
    ] as $gitPath) {
        if (file_exists($gitPath)) {
            $log .= "Retrying with: $gitPath\n";
            $gitOk = runCmd('"' . $gitPath . '" pull origin main 2>&1', $repoDir, $log);
            break;
        }
    }
}

if (!$gitOk) {
    echo json_encode([
        'ok'  => false,
        'log' => $log . "\n❌ git pull failed — make sure git is in PATH and the web process has read access to the repo.",
    ]);
    exit;
}

$log .= "\n✅ Code pulled.\n\n";

// =========================================================
// Step 2 — Bootstrap schema_migrations table
// =========================================================
$db->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `filename`   VARCHAR(255) NOT NULL UNIQUE,
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// =========================================================
// Step 3 — Run pending migrations
// =========================================================
$migrDir  = $repoDir . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'migrations';
$sqlFiles = glob($migrDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
sort($sqlFiles);

// Already-applied set
$applied = [];
foreach ($db->query("SELECT filename FROM schema_migrations")->fetchAll() as $r) {
    $applied[] = $r['filename'];
}

$pending = array_filter($sqlFiles, fn($p) => !in_array(basename($p), $applied, true));

if (empty($pending)) {
    $log .= "🗃️  No pending migrations.\n";
} else {
    $log .= "🗃️  " . count($pending) . " pending migration(s):\n";
    foreach ($pending as $path) {
        $base  = basename($path);
        $log  .= "\n   📄 $base\n";
        $sql   = file_get_contents($path);
        if ($sql === false) {
            $log .= "   ❌ Cannot read file — skipped.\n";
            continue;
        }

        $stmts = splitSqlStatements($sql);
        $ok    = true;

        foreach ($stmts as $idx => $s) {
            try {
                $db->exec($s);
            } catch (PDOException $e) {
                $log .= "   ❌ Statement " . ($idx + 1) . " failed: " . $e->getMessage() . "\n";
                $log .= "      SQL: " . mb_substr($s, 0, 120) . "…\n";
                $ok   = false;
                break;
            }
        }

        if ($ok) {
            $ins = $db->prepare("INSERT IGNORE INTO schema_migrations (filename) VALUES (?)");
            $ins->execute([$base]);
            $log .= "   ✅ Applied (" . count($stmts) . " statement" . (count($stmts) !== 1 ? 's' : '') . ")\n";
        } else {
            echo json_encode(['ok' => false, 'log' => $log . "\n❌ Migration failed — update aborted."]);
            exit;
        }
    }
}

// =========================================================
// Step 4 — Read updated VERSION
// =========================================================
$versionFile = $repoDir . DIRECTORY_SEPARATOR . 'VERSION';
$newVersion  = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'unknown';
$log        .= "\n🏷️  Version: $newVersion\n";
$log        .= "\n🎉 Update complete!\n";

echo json_encode(['ok' => true, 'log' => $log, 'version' => $newVersion]);