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
use Xoops\Upgrade\XoopsUpgrade;

/**
 * {@see XoopsUpgrade::removeLeftover()}: the clean-up the upgrade scripts use
 * for temporary license files and emptied library directories.
 *
 * It removes a file, a link or an empty directory, reports whether anything
 * is left, and never lets PHP's own warning (which names the full server
 * path) reach the upgrade page; the scripts log a failure with relativePath().
 *
 * The test double skips the parent constructor: no database is needed.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsUpgradeRemoveLeftoverTest extends TestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xoops-leftover-' . bin2hex(random_bytes(6));
        mkdir($this->base);
    }

    protected function tearDown(): void
    {
        if ('' === $this->base || !is_dir($this->base)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            ($item->isDir() && !$item->isLink()) ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->base);
    }

    /**
     * Call removeLeftover() and fail the test if any PHP warning escapes it.
     */
    private function remove(string $path): bool
    {
        $patch = new class () extends XoopsUpgrade {
            public function __construct()
            {
            }

            public function removeForTest(string $path): bool
            {
                return $this->removeLeftover($path);
            }
        };
        $leaked = [];
        set_error_handler(static function (int $errno, string $message) use (&$leaked): bool {
            $leaked[] = $message;

            return true;
        });
        try {
            $result = $patch->removeForTest($path);
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $leaked, 'removeLeftover() let a PHP warning escape.');

        return $result;
    }

    #[Test]
    public function aFileIsRemoved(): void
    {
        $file = $this->base . DIRECTORY_SEPARATOR . 'tmp_license_abc';
        file_put_contents($file, 'x');

        self::assertTrue($this->remove($file));
        self::assertFileDoesNotExist($file);
    }

    #[Test]
    public function anEmptyDirectoryIsRemoved(): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'vendor';
        mkdir($dir);

        self::assertTrue($this->remove($dir));
        self::assertDirectoryDoesNotExist($dir);
    }

    #[Test]
    public function aMissingPathCountsAsRemoved(): void
    {
        self::assertTrue($this->remove($this->base . DIRECTORY_SEPARATOR . 'already-gone'));
    }

    #[Test]
    public function aPathWhoseParentsAreMissingCountsAsRemoved(): void
    {
        self::assertTrue($this->remove($this->base . DIRECTORY_SEPARATOR . 'no-dir' . DIRECTORY_SEPARATOR . 'no-sub' . DIRECTORY_SEPARATOR . 'tmp_license'));
    }

    #[Test]
    public function aPathBehindAnUnreadableParentIsNotReportedAsRemoved(): void
    {
        if ('\\' === DIRECTORY_SEPARATOR || (function_exists('posix_geteuid') && 0 === posix_geteuid())) {
            self::markTestSkipped('Directory permissions cannot hide a file here (Windows, or running as root).');
        }
        $parent = $this->base . DIRECTORY_SEPARATOR . 'locked';
        mkdir($parent);
        file_put_contents($parent . DIRECTORY_SEPARATOR . 'tmp_license', 'x');
        chmod($parent, 0000);
        try {
            clearstatcache();
            self::assertFalse(file_exists($parent . DIRECTORY_SEPARATOR . 'tmp_license'), 'precondition: the file is hidden');
            self::assertFalse($this->remove($parent . DIRECTORY_SEPARATOR . 'tmp_license'));
        } finally {
            chmod($parent, 0700);
        }
        self::assertFileExists($parent . DIRECTORY_SEPARATOR . 'tmp_license');
    }

    #[Test]
    public function aDirectoryThatStillHoldsFilesIsReportedAndKept(): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'libraries';
        mkdir($dir);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'site-addition.php', 'x');

        self::assertFalse($this->remove($dir));
        self::assertFileExists($dir . DIRECTORY_SEPARATOR . 'site-addition.php');
    }

    #[Test]
    public function aDanglingLinkIsRemoved(): void
    {
        $link = $this->base . DIRECTORY_SEPARATOR . 'dangling';
        set_error_handler(static fn (): bool => true);
        try {
            $made = symlink($this->base . DIRECTORY_SEPARATOR . 'no-target', $link);
        } finally {
            restore_error_handler();
        }
        if (!$made) {
            self::markTestSkipped('symlink() is not permitted here.');
        }

        self::assertTrue($this->remove($link));
        self::assertFalse(is_link($link));
    }
}
