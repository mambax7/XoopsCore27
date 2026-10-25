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
define('XOOPS_ROOT_PATH', dirname(__DIR__, 5));
$fixture = null;
if (($argv[1] ?? '') === 'upgrade') {
    $fixture = sys_get_temp_dir() . '/admin-theme-upgrade-' . bin2hex(random_bytes(6));
    mkdir($fixture . '/dark', 0700, true);
    mkdir($fixture . '/transition', 0700);
    file_put_contents($fixture . '/dark/dark.php', '<?php throw new RuntimeException("Retired theme must not be included");');
    file_put_contents($fixture . '/transition/transition.php', '<?php require ' . var_export(dirname(__DIR__) . '/transition.php', true) . ';');
}
define('XOOPS_ADMINTHEME_PATH', $fixture ?? dirname(__DIR__, 2));
require XOOPS_ROOT_PATH . '/class/xoopslists.php';
require dirname(__DIR__, 3) . '/class/gui.php';
require dirname(__DIR__, 3) . '/class/cpanel.php';
function xoops_load(string $name, string $module = ''): void {}
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
    if ($fixture !== null && XoopsSystemCpanel::getGuis() !== ['transition' => 'transition']) {
        throw new RuntimeException('Selector must hide retired Dark files after an upgrade');
    }
    echo $fixture === null
        ? "PASS: retired Dark preference loads Transition without missing-file warnings\n"
        : "PASS: upgraded selector excludes leftover Dark files and keeps Transition available\n";
} finally {
    restore_error_handler();
    if ($fixture !== null) {
        foreach (['dark', 'transition'] as $theme) {
            unlink($fixture . '/' . $theme . '/' . $theme . '.php');
            rmdir($fixture . '/' . $theme);
        }
        rmdir($fixture);
    }
}
