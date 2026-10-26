<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Theme discovery must leave localization to the selected admin theme.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */
#[CoversNothing]
final class AdminThemeLocalizationTest extends TestCase
{
    public static function modernLanguages(): iterable
    {
        yield 'English' => ['english'];
        yield 'English fallback' => ['missing_test_locale'];
    }

    #[DataProvider('modernLanguages')]
    public function testModernHeaderLoadsContentLabels(string $language): void
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 4) . '/fixtures/modern-header-language.php', XOOPS_ROOT_PATH,
                XOOPS_ROOT_PATH . '/modules/system/themes/modern/modern.php', $language],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors . $output);
        self::assertSame('', $errors);
        self::assertStringContainsString('PASS: discovery stayed lazy', $output);
    }

    public static function themes(): iterable
    {
        yield 'default' => ['default'];
        yield 'transition' => ['transition'];
        yield 'modern' => ['modern'];
    }

    #[DataProvider('themes')]
    public function testDiscoveryDefersLocalizationToSelectedTheme(string $selected): void
    {
        // The suite bootstrap stubs xoops_loadLanguage; use the real loader in a fresh process.
        $code = 'define("XOOPS_ROOT_PATH", ' . var_export(XOOPS_ROOT_PATH, true) . ');'
            . '$selected = ' . var_export($selected, true) . ';'
            . <<<'PHP'
define('XOOPS_PATH', XOOPS_ROOT_PATH . '/xoops_lib');
define('XOOPS_TRUST_PATH', XOOPS_PATH);
define('XOOPS_VAR_PATH', XOOPS_ROOT_PATH . '/xoops_data');
define('XOOPS_URL', 'http://localhost');
define('DS', DIRECTORY_SEPARATOR);
set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});
require XOOPS_ROOT_PATH . '/include/defines.php';
require XOOPS_ROOT_PATH . '/include/functions.php';
require XOOPS_ROOT_PATH . '/class/xoopsload.php';
xoops_load('xoopskernel');
$GLOBALS['xoops'] = new xos_kernel_Xoops2();
$GLOBALS['xoopsConfig'] = ['language' => 'english'];
require XOOPS_ROOT_PATH . '/class/theme.php';
require XOOPS_ROOT_PATH . '/modules/system/class/cpanel.php';
if (!in_array($selected, XoopsSystemCpanel::getGuis(), true)) {
    throw new RuntimeException('Selected admin theme is missing');
}
$state = [defined('_MODERN_TOTAL_USERS'), defined('_OXYGEN_SYSOPTIONS')];
$theme = new xos_opal_Theme();
$theme->path = XOOPS_ADMINTHEME_PATH . '/' . $selected;
$theme->loadLocalization();
$theme->loadLocalization('admin');
$state[] = defined('_OXYGEN_SYSOPTIONS');
$state[] = defined('_MODERN_TOTAL_USERS');
echo json_encode($state, JSON_THROW_ON_ERROR);
PHP;

        $process = proc_open(
            [PHP_BINARY, '-r', $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors . $output);
        self::assertSame('', $errors);
        self::assertSame([false, false, true, $selected === 'modern'], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }
}
