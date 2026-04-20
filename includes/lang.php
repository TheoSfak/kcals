<?php
// ============================================================
// KCALS – Language Helper
// Included by auth.php after session is started.
// Defines the __() translation function.
// ============================================================

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

$_kcals_lang_code = $_SESSION['lang'];
$_kcals_lang_file = __DIR__ . '/../lang/' . $_kcals_lang_code . '.php';

if (!file_exists($_kcals_lang_file)) {
    $_kcals_lang_code          = 'en';
    $_SESSION['lang']          = 'en';
    $_kcals_lang_file          = __DIR__ . '/../lang/en.php';
}

$GLOBALS['_kcals_lang']         = $_kcals_lang_code;
$GLOBALS['_kcals_translations'] = require $_kcals_lang_file;

/**
 * Translate a key. Falls back to English, then to the key itself.
 */
function __(string $key): string
{
    $t = $GLOBALS['_kcals_translations'] ?? [];
    if (isset($t[$key])) {
        return $t[$key];
    }

    // Fallback to English when on another language
    if (($GLOBALS['_kcals_lang'] ?? 'en') !== 'en') {
        static $_en = null;
        if ($_en === null) {
            $_en = require __DIR__ . '/../lang/en.php';
        }
        if (isset($_en[$key])) {
            return $_en[$key];
        }
    }

    return $key;
}
