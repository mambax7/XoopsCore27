<?php
/*
 * Test bootstrap for the Smarty 4 -> 5 readiness scanner family.
 *
 * Defines just enough of the XOOPS environment (XOOPS_ROOT_PATH, the Smarty5
 * language constants, and a PSR-4 autoloader for Xoops\Upgrade\*) to exercise the
 * pure regex/repair logic without a full XOOPS boot. Used by both the PHPUnit
 * suite and the no-dependency runner (run-smarty5-tests.php).
 */

declare(strict_types=1);

// Sandbox root used only for relative-path trimming in the scanners.
if (!defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', __DIR__);
}
if (!defined('XOOPS_VAR_PATH')) {
    define('XOOPS_VAR_PATH', __DIR__ . '/tmp/var');
}

// PSR-4 autoloader for the real upgrade classes under ../class/Xoops/Upgrade.
$classDir = dirname(__DIR__) . '/class/Xoops/Upgrade';
spl_autoload_register(static function (string $class) use ($classDir): void {
    $prefix = 'Xoops\\Upgrade\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $classDir . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Real Smarty5 language constants (also confirms the language file parses).
require dirname(__DIR__) . '/language/english/smarty5.php';

/** Absolute path to a committed fixture .tpl. */
function s5_fixture(string $name): string
{
    return __DIR__ . '/fixtures/smarty/' . $name;
}

/**
 * Copy a fixture into a fresh temp working dir and return the temp file path,
 * so repair tests never mutate the committed fixtures.
 */
function s5_fixture_copy(string $name): string
{
    $tmpDir = __DIR__ . '/tmp/work';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0777, true);
    }
    $dest = $tmpDir . '/' . uniqid('s5_', true) . '_' . $name;
    copy(s5_fixture($name), $dest);

    return $dest;
}
