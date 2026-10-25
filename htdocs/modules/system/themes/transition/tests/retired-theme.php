<?php
/**
 * Check the retired Dark preference against the real admin GUI loader.
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('XOOPS_ADMINTHEME_PATH', dirname(__DIR__, 2));
require dirname(__DIR__, 3) . '/class/gui.php';
require dirname(__DIR__, 3) . '/class/cpanel.php';
function xoops_load(string $name, string $module): void {}
function xoops_getConfigOption(string $name): string
{
    return 'dark';
}
set_error_handler(static function (int $level, string $message): never {
    throw new RuntimeException($message);
});
try {
    $panel = new XoopsSystemCpanel();
    if (!($panel->gui instanceof XoopsGuiTransition) || $panel->gui->foldername !== 'transition') {
        throw new RuntimeException('Retired Dark preference must load Transition');
    }
    echo "PASS: retired Dark preference loads Transition without missing-file warnings\n";
} finally {
    restore_error_handler();
}
