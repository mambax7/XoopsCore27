<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Legacy classes that PHP 8 refused to load, helpers that called methods
 * which do not exist, the comment nav bar's post mode, and
 * XoopsTpl::fetchFromData(). Each class file is loaded in its own process
 * because an incompatible override is a fatal error at declaration time.
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

    /**
     * @return array<string, array{?string, string}>
     */
    public static function commentModes(): array
    {
        return [
            'flat'       => ['flat', 'flat'],
            'thread'     => ['thread', 'thread'],
            'nocomments' => ['nocomments', 'nocomments'],
            'empty'      => ['', 'thread'],
            'null'       => [null, 'thread'],
            'unknown'    => ['x"><script>', 'flat'],
        ];
    }

    #[Test]
    #[DataProvider('commentModes')]
    #[RunInSeparateProcess]
    public function commentNavBarPostsTheModeItShows(?string $mode, string $posted): void
    {
        $GLOBALS['xoopsConfig']['anonpost'] = 1;
        $GLOBALS['xoopsUser']               = null;
        $GLOBALS['xoopsLogger'] ??= \XoopsLogger::getInstance();
        require_once XOOPS_ROOT_PATH . '/language/english/global.php';
        require_once XOOPS_ROOT_PATH . '/class/xoopscomments.php';
        // printNavBar() does not use the instance; the constructor would open a database connection.
        $comments = (new \ReflectionClass(\XoopsComments::class))->newInstanceWithoutConstructor();

        ob_start();
        $comments->printNavBar(7, $mode, 1);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('&amp;mode=' . $posted . '"', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function fetchFromDataRendersTheSourceWithScopedVars(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/template.php';
        // Smarty's own constructor only; XoopsTpl's reads site configuration.
        $tpl = (new \ReflectionClass(\XoopsTpl::class))->newInstanceWithoutConstructor();
        (new \ReflectionMethod(\Smarty::class, '__construct'))->invoke($tpl);
        $tpl->setCompileDir(sys_get_temp_dir());
        $tpl->left_delimiter  = '<{';
        $tpl->right_delimiter = '}>';
        $tpl->assign('site', 'XOOPS');

        self::assertSame('Hi Bob at XOOPS', $tpl->fetchFromData('Hi <{$name}> at <{$site}>', false, ['name' => 'Bob']));
        self::assertNull($tpl->getTemplateVars('name'), '$vars must not leak into the template engine');

        ob_start();
        $returned = $tpl->fetchFromData('<{$site}>', true);
        self::assertSame('XOOPS', ob_get_clean());
        self::assertSame('', $returned);
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
