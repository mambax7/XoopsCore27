<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Legacy classes that PHP 8 refused to load, and helpers that called methods
 * which do not exist. Each file is loaded in its own process because an
 * incompatible override is a fatal error at declaration time.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversNothing]
class LegacyClassLoadTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function classFiles(): array
    {
        return [
            'tar downloader' => ['class/tardownloader.php', ['XoopsTarDownloader']],
            'zip downloader' => ['class/zipdownloader.php', ['XoopsZipDownloader']],
            'xml-rpc parser' => ['class/xml/rpc/xmlrpcparser.php', [
                'XoopsXmlRpcParser', 'RpcIntHandler', 'RpcStringHandler', 'RpcDateTimeHandler', 'RpcBase64Handler',
            ]],
        ];
    }

    /**
     * @param list<string> $classes
     */
    #[Test]
    #[DataProvider('classFiles')]
    #[RunInSeparateProcess]
    public function overridesMatchTheirParentSignatures(string $file, array $classes): void
    {
        require_once XOOPS_ROOT_PATH . '/' . $file;

        foreach ($classes as $class) {
            self::assertTrue(class_exists($class, false), $class . ' was not declared');
        }
    }

    #[Test]
    public function menuArrayHelpersFillTheTopMenuAndTabs(): void
    {
        require_once XOOPS_ROOT_PATH . '/modules/system/class/menu.php';
        $menu = new \SystemMenuHandler();

        $menu->addMenuTopArray(['a.php' => 'A']);
        $menu->addMenuTopArray(['b.php'], false);
        $menu->addMenuTabsArray(['c.php' => 'C']);
        $menu->addMenuTabsArray(['d.php'], false);

        self::assertSame(['a.php' => 'A', 'b.php' => 'b.php'], $menu->_menutop);
        self::assertSame(['c.php' => 'C', 'd.php' => 'd.php'], $menu->_menutabs);
    }

    #[Test]
    public function cacheEngineWithoutReadOrWriteReportsItself(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/cache/xoopscache.php';
        $engine   = new class extends \XoopsCacheEngine {
            public function clear($check) {}
            public function delete($key) {}
        };
        $messages = [];
        set_error_handler(static function (int $no, string $msg) use (&$messages): bool {
            if ($no === E_USER_ERROR) {
                $messages[] = $msg;
            }

            return true;
        });
        try {
            $engine->write('k', 'v');
            $engine->read('k');
        } finally {
            restore_error_handler();
        }

        self::assertSame([
            'Method write() not implemented in ' . get_class($engine),
            'Method read() not implemented in ' . get_class($engine),
        ], $messages);
    }
}
