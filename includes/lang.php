<?php
// ============================================================
// KCALS – Language Helper
// Included by auth.php after session is started.
// Defines the __() translation function.
// DB overrides (translation_overrides table) take priority over files.
// ============================================================

// Resolve default language: DB setting if available, else 'en'
if (!isset($_SESSION['lang'])) {
    $__defaultLang = 'en';
    try {
        $__db = getDB();
        $__st = $__db->prepare("SELECT value FROM settings WHERE `key` = 'general_default_lang' LIMIT 1");
        $__st->execute();
        $__row = $__st->fetch(PDO::FETCH_ASSOC);
        if ($__row && $__row['value'] !== '') $__defaultLang = $__row['value'];
    } catch (Throwable $__e) { /* table may not exist yet */ }
    $_SESSION['lang'] = $__defaultLang;
}

$_kcals_lang_code = $_SESSION['lang'];
$_kcals_lang_file = __DIR__ . '/../lang/' . $_kcals_lang_code . '.php';

if (!file_exists($_kcals_lang_file)) {
    // Language has no file — use EN as base (DB overrides still apply on top)
    $_kcals_lang_file = __DIR__ . '/../lang/en.php';
}

$GLOBALS['_kcals_lang']         = $_kcals_lang_code;
$GLOBALS['_kcals_translations'] = require $_kcals_lang_file;

// Load DB overrides for this language (single query; safe if table absent)
$GLOBALS['_kcals_overrides'] = [];
try {
    $__db  = getDB();
    $__st  = $__db->prepare("SELECT `key`, `value` FROM translation_overrides WHERE lang = ?");
    $__st->execute([$_kcals_lang_code]);
    foreach ($__st->fetchAll(PDO::FETCH_ASSOC) as $__r) {
        $GLOBALS['_kcals_overrides'][$__r['key']] = $__r['value'];
    }
} catch (Throwable $__e) { /* table not yet created — ignore */ }

/**
 * Translate a key.
 * Priority: DB override → lang file → English file → key itself.
 */
function __(string $key): string
{
    // 1. DB override for current language
    $o = $GLOBALS['_kcals_overrides'] ?? [];
    if (isset($o[$key])) return $o[$key];

    // 2. Compiled file translation
    $t = $GLOBALS['_kcals_translations'] ?? [];
    if (isset($t[$key])) return $t[$key];

    // 3. English file fallback
    if (($GLOBALS['_kcals_lang'] ?? 'en') !== 'en') {
        static $_en = null;
        if ($_en === null) {
            $_en = require __DIR__ . '/../lang/en.php';
        }
        if (isset($_en[$key])) return $_en[$key];
    }

    return $key;
}
