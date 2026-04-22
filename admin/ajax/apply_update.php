<?php
// ============================================================
// KCALS Admin – AJAX: Apply Update from GitHub
// POST only. Requires CSRF + admin role.
// Steps:
//   1a. Download latest release zip from GitHub and extract (shared hosting)
//   1b. Fallback: git pull origin main (local dev with git)
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
// Step 1 — Pull latest code
//   Strategy A: Download + extract the GitHub release zip
//               (works on shared hosting, no git required)
//   Strategy B: git pull (local dev / VPS with git)
// =========================================================
$log .= "📂 Repo: $repoDir\n\n";

// --- Strategy A: zip download ---
$zipOk      = false;
$apiContext = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => "User-Agent: KCALS-Admin/1.0\r\nAccept: application/vnd.github+json\r\n",
        'timeout' => 15,
    ],
]);

$releaseBody = @file_get_contents(
    'https://api.github.com/repos/TheoSfak/kcals/releases/latest',
    false,
    $apiContext
);
$zipUrl = null;
if ($releaseBody !== false) {
    $release = json_decode($releaseBody, true);
    $zipUrl  = $release['zipball_url'] ?? null;
}

if ($zipUrl) {
    $log .= "⬇️  Downloading release zip…\n";

    // Follow redirects (GitHub sends a 302 to S3)
    $zipContext = stream_context_create([
        'http' => [
            'method'           => 'GET',
            'header'           => "User-Agent: KCALS-Admin/1.0\r\n",
            'timeout'          => 60,
            'follow_location'  => 1,
            'max_redirects'    => 5,
        ],
    ]);

    $tmpZip = tempnam(sys_get_temp_dir(), 'kcals_update_') . '.zip';
    $zipData = @file_get_contents($zipUrl, false, $zipContext);

    if ($zipData !== false && strlen($zipData) > 1000) {
        file_put_contents($tmpZip, $zipData);
        $log .= "   Downloaded " . round(strlen($zipData) / 1024, 1) . " KB\n";

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tmpZip) === true) {
                // The zip contains a single top-level folder like "TheoSfak-kcals-<sha>/"
                // We need to strip that prefix when extracting
                $topDir = null;
                for ($zi = 0; $zi < $zip->numFiles; $zi++) {
                    $name = $zip->getNameIndex($zi);
                    if ($topDir === null) {
                        $parts  = explode('/', $name, 2);
                        $topDir = $parts[0] . '/';
                    }
                    break;
                }

                $skipPatterns = [
                    '/^[^\/]+\/config\/db\.php$/',          // never overwrite DB config
                    '/^[^\/]+\/productive-site\//',          // skip productive-site mirror
                    '/^[^\/]+\/\.git\//',                    // skip any nested .git
                ];

                $extracted = 0;
                for ($zi = 0; $zi < $zip->numFiles; $zi++) {
                    $name = $zip->getNameIndex($zi);

                    // Skip the root folder entry itself
                    if ($name === $topDir) continue;

                    // Check skip patterns
                    $skip = false;
                    foreach ($skipPatterns as $pat) {
                        if (preg_match($pat, $name)) { $skip = true; break; }
                    }
                    if ($skip) continue;

                    // Strip top-level directory prefix
                    $rel  = substr($name, strlen($topDir));
                    if ($rel === '' || $rel === false) continue;

                    $dest = $repoDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

                    if (str_ends_with($name, '/')) {
                        // Directory entry
                        if (!is_dir($dest)) {
                            mkdir($dest, 0755, true);
                        }
                    } else {
                        $dir = dirname($dest);
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $content = $zip->getFromIndex($zi);
                        if ($content !== false) {
                            file_put_contents($dest, $content);
                            $extracted++;
                        }
                    }
                }
                $zip->close();
                @unlink($tmpZip);
                $log   .= "   ✅ Extracted $extracted file(s).\n\n";
                $zipOk  = true;
            } else {
                $log .= "   ❌ Could not open zip archive.\n";
                @unlink($tmpZip);
            }
        } else {
            $log .= "   ⚠️  ZipArchive not available — falling back to git.\n";
            @unlink($tmpZip);
        }
    } else {
        $log .= "   ❌ Zip download failed or empty response.\n";
        @unlink($tmpZip);
    }
}

// --- Strategy B: git pull (fallback for local dev / VPS) ---
if (!$zipOk) {
    $log .= "⬇️  git pull origin main\n";
    $gitOk  = false;
    $gitCmd = 'git pull origin main 2>&1';
    $gitOk  = runCmd($gitCmd, $repoDir, $log);

    if (!$gitOk) {
        foreach ([
            'C:\\Program Files\\Git\\bin\\git.exe',
            'C:\\Program Files (x86)\\Git\\bin\\git.exe',
            '/usr/bin/git',
            '/usr/local/bin/git',
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
            'log' => $log . "\n❌ Update failed — zip download failed and git pull failed.\n"
                          . "Make sure the server can reach github.com (check allow_url_fopen / curl) "
                          . "or that git is installed.",
        ]);
        exit;
    }
    $log .= "\n✅ Code pulled via git.\n\n";
}

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
                $code = (int) $e->getCode();           // SQLSTATE
                $info = $e->errorInfo ?? [];
                $mysql = (int) ($info[1] ?? 0);        // MySQL native error number

                // Non-fatal: column/key/table already exists (idempotent migrations)
                // MySQL 1060 = Duplicate column name
                // MySQL 1061 = Duplicate key name
                // MySQL 1050 = Table already exists  (SQLSTATE 42S01)
                // MySQL 1062 = Duplicate entry       (INSERT IGNORE handles, but just in case)
                $nonFatal = in_array($mysql, [1060, 1061, 1050, 1062], true)
                         || in_array((string) $code, ['42S01', '42S21'], true);

                if ($nonFatal) {
                    $log .= "   ⚠️  Statement " . ($idx + 1) . " skipped (already applied): " . $e->getMessage() . "\n";
                    continue;
                }

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
