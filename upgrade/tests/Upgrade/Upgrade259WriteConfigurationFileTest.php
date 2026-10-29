<?php
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

declare(strict_types=1);

namespace Xoops\Upgrade\Tests\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Upgrade_259;

/**
 * Upgrade_259::writeConfigurationFile() against the shipped mainfile.dist.php:
 * only the executable define() calls are rewritten, the "Example:" line
 * comment is left alone, and a value holding a PHP close tag stays inside
 * the generated string literal.
 *
 * @category  Xoops
 * @package   Upgrade
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class Upgrade259WriteConfigurationFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/upd-2.5.8-to-2.5.9/index.php';
        if (!defined('_FILE_ACCESS_ERROR')) {
            define('_FILE_ACCESS_ERROR', 'Could not access the file %s');
        }
        $this->dir = sys_get_temp_dir() . '/xoops_upd259_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        copy(dirname(__DIR__, 3) . '/htdocs/mainfile.dist.php', $this->dir . '/mainfile.dist.php');
    }

    protected function tearDown(): void
    {
        foreach (['mainfile.php', 'mainfile.dist.php'] as $name) {
            if (is_file($this->dir . '/' . $name)) {
                unlink($this->dir . '/' . $name);
            }
        }
        rmdir($this->dir);
    }

    #[Test]
    public function aCloseTagInTheUrlNeverLeavesTheStringLiteral(): void
    {
        $url    = 'https://x?><?php echo 1;';
        $writer = new ReflectionMethod(Upgrade_259::class, 'writeConfigurationFile');
        $result = $writer->invoke(
            (new ReflectionClass(Upgrade_259::class))->newInstanceWithoutConstructor(),
            ['XOOPS_URL' => $url, 'XOOPS_ROOT_PATH' => "c:\\x'y"],
            $this->dir,
            'mainfile.dist.php',
            'mainfile.php',
        );
        self::assertTrue($result);

        $out = file_get_contents($this->dir . '/mainfile.php');
        self::assertStringContainsString('// Example: define("XOOPS_URL", "http://url_to_xoops_directory");', $out);
        self::assertStringContainsString("define('XOOPS_URL', 'https://x?><?php echo 1;')", $out);
        self::assertStringContainsString("define('XOOPS_ROOT_PATH', 'c:\\\\x\\'y')", $out);
        $kinds = array_map(static fn($t) => is_array($t) ? $t[0] : $t, token_get_all($out));
        self::assertCount(1, array_keys($kinds, T_OPEN_TAG, true));
        self::assertNotContains(T_CLOSE_TAG, $kinds);
        self::assertNotContains(T_INLINE_HTML, $kinds);
    }

    #[Test]
    public function aKeyMissingFromTheCurrentConfigurationKeepsTheTemplateDefault(): void
    {
        // checkmainfile.php leaves a key the old mainfile.php does not define at null.
        $writer = new ReflectionMethod(Upgrade_259::class, 'writeConfigurationFile');
        $result = $writer->invoke(
            (new ReflectionClass(Upgrade_259::class))->newInstanceWithoutConstructor(),
            ['XOOPS_URL' => null, 'XOOPS_ROOT_PATH' => '/srv/xoops'],
            $this->dir,
            'mainfile.dist.php',
            'mainfile.php',
        );
        self::assertTrue($result);

        $out = file_get_contents($this->dir . '/mainfile.php');
        self::assertStringContainsString("define('XOOPS_URL', 'https://')", $out);
        self::assertStringContainsString("define('XOOPS_ROOT_PATH', '/srv/xoops')", $out);
    }
}
