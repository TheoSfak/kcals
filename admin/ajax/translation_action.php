<?php
// ============================================================
// KCALS Admin – AJAX: Translation CRUD
// All write operations require POST + CSRF.
// GET actions: list, languages
// POST actions: save, delete, add_language, delete_language
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit; }
$db   = getDB();
$stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$row  = $stmt->fetch();
if (!$row || empty($row['is_admin'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Access denied']); exit; }

$action = $_REQUEST['action'] ?? '';

// ============================================================
// GET: languages — list all available language codes
// ============================================================
if ($action === 'languages' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $langs = [];
    // File-based
    foreach (glob(__DIR__ . '/../../lang/*.php') as $f) {
        $code = basename($f, '.php');
        if ($code === 'set') continue;
        $langs[$code] = ['code' => $code, 'source' => 'file'];
    }
    // DB-only
    $st = $db->query("SELECT DISTINCT lang FROM translation_overrides");
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $code) {
        if (!isset($langs[$code])) {
            $langs[$code] = ['code' => $code, 'source' => 'db'];
        }
    }
    ksort($langs);
    echo json_encode(['ok' => true, 'languages' => array_values($langs)]);
    exit;
}

// ============================================================
// GET: list — all keys for a language (EN base + overrides)
// ============================================================
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $lang = preg_replace('/[^a-z_-]/i', '', $_GET['lang'] ?? 'en');

    // EN base (always the canonical key list)
    $en = require __DIR__ . '/../../lang/en.php';

    // File for this language (if exists)
    $langFile = __DIR__ . '/../../lang/' . $lang . '.php';
    $fileT    = file_exists($langFile) ? require $langFile : [];

    // DB overrides for this language
    $st = $db->prepare("SELECT `key`, `value` FROM translation_overrides WHERE lang = ?");
    $st->execute([$lang]);
    $dbOverrides = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $dbOverrides[$r['key']] = $r['value'];
    }

    // Also load DB overrides for EN (in case EN has overrides too)
    $stEn = $db->prepare("SELECT `key`, `value` FROM translation_overrides WHERE lang = 'en'");
    $stEn->execute();
    $enDbOverrides = [];
    foreach ($stEn->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $enDbOverrides[$r['key']] = $r['value'];
    }

    $keys = [];
    foreach ($en as $key => $enValue) {
        // Effective EN value (may itself be overridden)
        $enEffective  = $enDbOverrides[$key] ?? $enValue;
        // Current file value for target lang
        $fileValue    = $fileT[$key]        ?? null;
        // DB override for target lang
        $dbValue      = $dbOverrides[$key]  ?? null;
        // What the user currently sees
        $currentValue = $dbValue ?? $fileValue ?? $enEffective;

        $keys[] = [
            'key'          => $key,
            'en_default'   => (string) $enEffective,
            'file_value'   => $lang === 'en' ? (string) $enValue : ($fileValue !== null ? (string) $fileValue : null),
            'db_override'  => $dbValue !== null ? (string) $dbValue : null,
            'current'      => (string) $currentValue,
            'is_overridden'=> $dbValue !== null,
        ];
    }

    // Include any DB-only keys for this language not in EN
    foreach ($dbOverrides as $key => $val) {
        if (!isset($en[$key])) {
            $keys[] = [
                'key'          => $key,
                'en_default'   => '',
                'file_value'   => null,
                'db_override'  => (string) $val,
                'current'      => (string) $val,
                'is_overridden'=> true,
            ];
        }
    }

    echo json_encode(['ok' => true, 'lang' => $lang, 'keys' => $keys]);
    exit;
}

// ============================================================
// POST: save — upsert a single key override
// ============================================================
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(419); echo json_encode(['ok'=>false,'error'=>'Invalid CSRF']); exit; }
    $lang  = preg_replace('/[^a-z_-]/i', '', $_POST['lang'] ?? '');
    $key   = trim($_POST['key']   ?? '');
    $value = $_POST['value'] ?? '';
    if ($lang === '' || $key === '') { echo json_encode(['ok'=>false,'error'=>'Missing lang or key']); exit; }
    if (strlen($lang) > 10 || strlen($key) > 191) { echo json_encode(['ok'=>false,'error'=>'Value too long']); exit; }

    $st = $db->prepare(
        "INSERT INTO translation_overrides (`lang`, `key`, `value`) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    $st->execute([$lang, $key, $value]);
    echo json_encode(['ok' => true]);
    exit;
}

// ============================================================
// POST: delete — remove a DB override (revert to file default)
// ============================================================
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(419); echo json_encode(['ok'=>false,'error'=>'Invalid CSRF']); exit; }
    $lang = preg_replace('/[^a-z_-]/i', '', $_POST['lang'] ?? '');
    $key  = trim($_POST['key'] ?? '');
    $st   = $db->prepare("DELETE FROM translation_overrides WHERE lang = ? AND `key` = ?");
    $st->execute([$lang, $key]);
    echo json_encode(['ok' => true]);
    exit;
}

// ============================================================
// POST: add_language — create a new language code, seed from EN
// ============================================================
if ($action === 'add_language' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(419); echo json_encode(['ok'=>false,'error'=>'Invalid CSRF']); exit; }
    $code = strtolower(preg_replace('/[^a-z_-]/i', '', $_POST['code'] ?? ''));
    $seed = !empty($_POST['seed_from_en']);
    if ($code === '' || strlen($code) > 10) { echo json_encode(['ok'=>false,'error'=>'Invalid language code']); exit; }

    // Check not already a file-based lang
    $file = __DIR__ . '/../../lang/' . $code . '.php';
    if (file_exists($file)) { echo json_encode(['ok'=>false,'error'=>"Language '$code' already has a file — edit it directly."]); exit; }

    if ($seed) {
        $en = require __DIR__ . '/../../lang/en.php';
        $st = $db->prepare(
            "INSERT IGNORE INTO translation_overrides (`lang`, `key`, `value`) VALUES (?, ?, ?)"
        );
        $db->beginTransaction();
        try {
            foreach ($en as $k => $v) $st->execute([$code, $k, $v]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            echo json_encode(['ok'=>false,'error'=>'DB error: '.$e->getMessage()]);
            exit;
        }
        echo json_encode(['ok' => true, 'seeded' => count($en)]);
    } else {
        // Just insert a placeholder so the language shows up
        $st = $db->prepare(
            "INSERT IGNORE INTO translation_overrides (`lang`, `key`, `value`) VALUES (?, '_lang_name', ?)"
        );
        $st->execute([$code, strtoupper($code)]);
        echo json_encode(['ok' => true, 'seeded' => 0]);
    }
    exit;
}

// ============================================================
// POST: delete_language — remove all overrides for a language
// ============================================================
if ($action === 'delete_language' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(419); echo json_encode(['ok'=>false,'error'=>'Invalid CSRF']); exit; }
    $code = strtolower(preg_replace('/[^a-z_-]/i', '', $_POST['code'] ?? ''));
    if ($code === 'en') { echo json_encode(['ok'=>false,'error'=>"Cannot delete the base language."]); exit; }
    $st = $db->prepare("DELETE FROM translation_overrides WHERE lang = ?");
    $st->execute([$code]);
    echo json_encode(['ok' => true, 'deleted' => $st->rowCount()]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
