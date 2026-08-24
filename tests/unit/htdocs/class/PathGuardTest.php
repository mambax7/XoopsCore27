<?php
/**
 * Unit tests for PathGuard
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.3
 */

declare(strict_types=1);

namespace xoopsclass;

use PathGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Truth-table tests for the request-path containment contract the
 * tplsets endpoints converged on in PR #176. Every row builds a real
 * fixture tree - the guard's behavior is filesystem behavior, so the
 * table runs against the filesystem, not string comparisons.
 *
 * @category  Test
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(PathGuard::class)]
final class PathGuardTest extends TestCase
{
    private static string $base;
    private static string $themes;

    public static function setUpBeforeClass(): void
    {
        // Global class, not autoloadable by the test namespace map - load
        // it the way the endpoints do (same pattern as the XoopsFile pin).
        require_once XOOPS_ROOT_PATH . '/class/PathGuard.php';
        self::$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pathguard_' . bin2hex(random_bytes(4));
        self::$themes = self::$base . DIRECTORY_SEPARATOR . 'themes';
        mkdir(self::$themes . DIRECTORY_SEPARATOR . 'default', 0777, true);
        mkdir(self::$themes . DIRECTORY_SEPARATOR . 'thème', 0777, true);
        mkdir(self::$base . DIRECTORY_SEPARATOR . 'themes2', 0777, true);
        file_put_contents(self::$themes . '/default/style.css', 'body{}');
        file_put_contents(self::$themes . '/default/page.TPL', 'x');
        file_put_contents(self::$themes . '/default/evil.php', 'x');
        file_put_contents(self::$themes . '/thème/écran.css', 'x');
        file_put_contents(self::$base . '/themes2/steal.css', 'x');
        file_put_contents(self::$base . '/outside.css', 'x');
        // a DIRECTORY named like a stylesheet - must never pass file mode
        mkdir(self::$themes . '/default/dir.css');
        if (function_exists('symlink')) {
            @symlink(self::$base . '/outside.css', self::$themes . '/default/link-out.css');
            @symlink(self::$themes . '/default/style.css', self::$themes . '/default/link-in.css');
        }
    }

    public static function tearDownAfterClass(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir(self::$base);
    }

    /**
     * @return array<string, array{string, bool}> relative fragment, expected admitted
     */
    public static function directoryTable(): array
    {
        return [
            'themes root itself ("")'          => ['', true],
            'themes root itself ("/")'         => ['/', true],
            'plain subdirectory'               => ['/default', true],
            'no leading separator'             => ['default', true],
            'trailing slash'                   => ['/default/', true],
            'non-ASCII directory'              => ['/thème', true],
            'traversal to sibling'             => ['/../themes2', false],
            'deep traversal to sibling'        => ['/default/../../themes2', false],
            'traversal to parent'              => ['/..', false],
            'non-existent'                     => ['/nope', false],
            'a file is not a directory'        => ['/default/style.css', false],
            'interior NUL'                     => ["/def\0ault", false],
            'trailing NUL'                     => ["/default\0", false],
        ];
    }

    #[Test]
    #[DataProvider('directoryTable')]
    public function directoryContainment(string $relative, bool $admitted): void
    {
        $result = PathGuard::resolveDir(self::$themes, $relative);
        if ($admitted) {
            $this->assertIsString($result);
            $this->assertDirectoryExists($result);
            $this->assertTrue(
                $result === realpath(self::$themes)
                || str_starts_with($result, realpath(self::$themes) . DIRECTORY_SEPARATOR)
            );
        } else {
            $this->assertFalse($result);
        }
    }

    /**
     * @return array<string, array{string, bool}> relative fragment, expected admitted
     */
    public static function fileTable(): array
    {
        return [
            'plain template file'          => ['/default/style.css', true],
            'no leading separator'         => ['default/style.css', true],
            'uppercase extension'          => ['/default/page.TPL', true],
            'non-ASCII path'               => ['/thème/écran.css', true],
            'extension outside allowlist'  => ['/default/evil.php', false],
            'directory named like a file'  => ['/default/dir.css', false],
            'themes root as "file"'        => ['/', false],
            'sibling escape'               => ['/../themes2/steal.css', false],
            'deep sibling escape'          => ['/default/../../themes2/steal.css', false],
            'non-existent'                 => ['/default/nope.css', false],
            'interior NUL'                 => ["/default/sty\0le.css", false],
            'trailing NUL'                 => ["/default/style.css\0", false],
            'NUL extension mask'           => ["/default/evil.php\0.css", false],
        ];
    }

    #[Test]
    #[DataProvider('fileTable')]
    public function fileContainment(string $relative, bool $admitted): void
    {
        $result = PathGuard::resolveFile(self::$themes, $relative, ['css', 'html', 'htm', 'tpl']);
        if ($admitted) {
            $this->assertIsString($result);
            $this->assertFileExists($result);
            $this->assertTrue(str_starts_with($result, realpath(self::$themes) . DIRECTORY_SEPARATOR));
        } else {
            $this->assertFalse($result);
        }
    }

    #[Test]
    public function symlinkEscapeIsRefusedAndInternalLinkCanonicalized(): void
    {
        if (!function_exists('symlink') || !is_link(self::$themes . '/default/link-out.css')) {
            $this->markTestSkipped('symlinks unavailable on this filesystem');
        }
        // A symlink pointing OUTSIDE the root: realpath() resolves it to the
        // target, which then fails containment.
        $this->assertFalse(
            PathGuard::resolveFile(self::$themes, '/default/link-out.css', ['css'])
        );
        // A symlink pointing INSIDE the root resolves to its canonical
        // target - the caller gets the real file, not the link.
        $inside = PathGuard::resolveFile(self::$themes, '/default/link-in.css', ['css']);
        $this->assertSame(realpath(self::$themes . '/default/style.css'), $inside);
    }

    #[Test]
    public function emptyAllowlistAcceptsAnyExtension(): void
    {
        $this->assertIsString(PathGuard::resolveFile(self::$themes, '/default/evil.php'));
    }

    #[Test]
    public function canonicalResultNeverEchoesTheRawRequest(): void
    {
        $result = PathGuard::resolveDir(self::$themes, '/default/../default/');
        $this->assertIsString($result);
        $this->assertStringNotContainsString('..', $result);
    }
}
