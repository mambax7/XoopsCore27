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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

/**
 * xoops_resolveFileWithin() and its use by the image manager.
 *
 * The helper turns a stored relative file name into a canonical path only
 * when it names a regular file strictly inside the given root, so a stored
 * name such as "../mainfile.php" can never drive a file operation outside
 * the upload directory. The image manager's delete used to unlink
 * XOOPS_UPLOAD_PATH . '/' . image_name with no such check, and read
 * database-stored uploads with an unchecked @fopen()/@fread()/@fclose()
 * trio whose failure ends in a TypeError.
 *
 * @category  Xoops
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ResolveFileWithinTest extends TestCase
{
    private string $base = '';

    private string $root = '';

    protected function setUp(): void
    {
        if (!function_exists('xoops_resolveFileWithin')) {
            self::fail('xoops_resolveFileWithin() is missing from include/file_safety.php.');
        }
        $this->base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xoops-within-' . bin2hex(random_bytes(6));
        $this->root = $this->base . DIRECTORY_SEPARATOR . 'uploads';
        mkdir($this->root . DIRECTORY_SEPARATOR . 'images', 0777, true);
        mkdir($this->base . DIRECTORY_SEPARATOR . 'uploads2', 0777, true);
        file_put_contents($this->root . DIRECTORY_SEPARATOR . 'top.png', 'x');
        file_put_contents($this->root . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'img1.png', 'x');
        file_put_contents($this->base . DIRECTORY_SEPARATOR . 'outside.php', 'x');
        file_put_contents($this->base . DIRECTORY_SEPARATOR . 'uploads2' . DIRECTORY_SEPARATOR . 'sibling.png', 'x');
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
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
            } else {
                rmdir($item->getPathname());
            }
        }
        rmdir($this->base);
    }

    #[Test]
    public function aFileInsideTheRootResolvesToItsCanonicalPath(): void
    {
        self::assertSame(realpath($this->root . '/top.png'), xoops_resolveFileWithin($this->root, 'top.png'));
        self::assertSame(realpath($this->root . '/images/img1.png'), xoops_resolveFileWithin($this->root, 'images/img1.png'));
        self::assertSame(realpath($this->root . '/images/img1.png'), xoops_resolveFileWithin($this->root . '/', '/images/img1.png'));
    }

    /** @return array<string, array{string}> */
    public static function refusedNames(): array
    {
        return [
            'parent segment'            => ['../outside.php'],
            'nested parent segments'    => ['images/../../outside.php'],
            'sibling sharing the prefix' => ['../uploads2/sibling.png'],
            'the root itself'           => [''],
            'a directory'               => ['images'],
            'missing file'              => ['images/none.png'],
            'null byte'                 => ["top.png\0.txt"],
        ];
    }

    #[Test]
    #[DataProvider('refusedNames')]
    public function aNameThatIsNotAFileInsideTheRootResolvesToEmpty(string $relative): void
    {
        self::assertSame('', xoops_resolveFileWithin($this->root, $relative));
    }

    #[Test]
    public function aMissingRootResolvesToEmpty(): void
    {
        self::assertSame('', xoops_resolveFileWithin($this->base . '/no-such-root', 'top.png'));
    }

    #[Test]
    public function aFileUnderTheFilesystemRootResolves(): void
    {
        $file = (string) realpath($this->root . '/top.png');
        // '/' on Unix, the drive root ("C:\") on Windows: the boundary must not
        // become a doubled separator.
        $fsRoot   = '\\' === DIRECTORY_SEPARATOR ? substr($file, 0, 3) : '/';
        $relative = substr($file, strlen($fsRoot));

        self::assertSame($file, xoops_resolveFileWithin($fsRoot, $relative));
    }

    #[Test]
    public function aSymlinkPointingOutsideTheRootResolvesToEmpty(): void
    {
        $link = $this->root . DIRECTORY_SEPARATOR . 'escape.php';
        set_error_handler(static fn (): bool => true);
        try {
            $made = symlink($this->base . DIRECTORY_SEPARATOR . 'outside.php', $link);
        } finally {
            restore_error_handler();
        }
        if (!$made) {
            self::markTestSkipped('symlink() is not permitted here.');
        }

        self::assertSame('', xoops_resolveFileWithin($this->root, 'escape.php'));
    }

    // ---------------------------------------------------------------------
    // Source pins: the image manager uses the helper
    // ---------------------------------------------------------------------

    private static function imageManagerSource(): string
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/images/main.php');
        self::assertNotFalse($src);

        return $src;
    }

    #[Test]
    public function imageDeleteRemovesOnlyAContainedFile(): void
    {
        $src = self::imageManagerSource();
        $start = strpos($src, "case 'delfileok':");
        $end   = strpos($src, "case 'save':", (int) $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $block = substr($src, $start, $end - $start);

        self::assertSame(
            1,
            preg_match("/xoops_resolveFileWithin\\(\\s*XOOPS_UPLOAD_PATH\\s*,\\s*\\(string\\)\\s*\\\$image->getVar\\('image_name',\\s*'n'\\)\\s*\\)/", $block),
            'The delete must resolve the stored name with xoops_resolveFileWithin(XOOPS_UPLOAD_PATH, raw image_name).'
        );
        self::assertSame(
            1,
            preg_match('/\$(\w+)\s*=\s*xoops_resolveFileWithin\(.*?if\s*\(\s*\'\'\s*!==\s*\$\1\s*\)\s*\{\s*xoops_remove_file_quietly\(\s*\$\1\s*,/s', $block),
            'Only the resolved path may be removed, and only when it resolved.'
        );
        self::assertSame(0, preg_match('/xoops_remove_file_quietly\(\s*XOOPS_UPLOAD_PATH/', $block), 'The raw stored name must not be removed.');
    }

    #[Test]
    public function categoryDeleteRemovesOnlyContainedFiles(): void
    {
        $src   = self::imageManagerSource();
        $start = strpos($src, "case 'delcatok':");
        self::assertNotFalse($start);
        $end   = strpos($src, 'break;', $start);
        self::assertNotFalse($end);
        $block = substr($src, $start, $end - $start);

        self::assertSame(
            1,
            preg_match("/\\$(\\w+)\\s*=\\s*xoops_resolveFileWithin\\(\\s*XOOPS_UPLOAD_PATH\\s*,\\s*\\(string\\)\\s*\\\$images\\[\\\$i\\]->getVar\\('image_name',\\s*'n'\\)\\s*\\).*?if\\s*\\(\\s*''\\s*!==\\s*\\$\\1\\s*\\)\\s*\\{\\s*xoops_remove_file_quietly\\(\\s*\\$\\1\\s*,/s", $block),
            'The category delete must resolve each raw image_name inside XOOPS_UPLOAD_PATH and remove only that path.'
        );
        self::assertStringContainsString('_AM_SYSTEM_IMAGES_FAILUNLINK', $block, 'A file that could not be removed is still reported.');
        self::assertSame(0, preg_match('/\bunlink\(\s*XOOPS_UPLOAD_PATH/', $src), 'No raw unlink() of an upload path may remain.');
    }

    #[Test]
    public function databaseStorageReadsTheUploadWithOneCheckedCall(): void
    {
        $src = self::imageManagerSource();

        self::assertSame(1, preg_match('/\$fbinary\s*=\s*[^;]*\bfile_get_contents\(/', $src), 'Read the upload with file_get_contents().');
        self::assertSame(
            1,
            preg_match('/set_error_handler\([^;]*;\s*try\s*\{\s*\$fbinary\s*=\s*[^;]*\bfile_get_contents\([^;]*;\s*\}\s*finally\s*\{\s*restore_error_handler\(\);/s', $src),
            'The read must run inside a scoped error handler, so a failure cannot print the full upload path.'
        );
        self::assertSame(1, preg_match('/if\s*\(\s*false\s*===\s*\$fbinary\s*\)/', $src), 'A failed read must be handled.');
        self::assertSame(0, preg_match('/@(fopen|fread|fclose|unlink)\(/', $src), 'No error-suppressed file call may remain.');
    }
}
