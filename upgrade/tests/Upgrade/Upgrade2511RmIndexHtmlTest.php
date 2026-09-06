<?php

declare(strict_types=1);

namespace Xoops\Upgrade\Tests\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Upgrade_2511;

require_once dirname(__DIR__, 2) . '/upd_2.5.10-to-2.5.11/index.php';

if (!defined('_UPGRADE_CHARSET')) {
    define('_UPGRADE_CHARSET', 'UTF-8');
}

/**
 * Test double for {@see Upgrade_2511}: skips the DB-bound constructor and points
 * the index.html scan at a temp tree instead of XOOPS_ROOT_PATH.
 */
final class Upgrade2511Stub extends Upgrade_2511
{
    public function __construct(string $root)
    {
        // intentionally skip parent (no DB, no XOOPS constants needed)
        $prop = new ReflectionProperty(Upgrade_2511::class, 'pathsToCheck');
        $prop->setValue($this, [$root]);
    }
}

/**
 * apply_rmindexhtml() used to report success even when unlink() failed, so
 * check_rmindexhtml() kept re-queuing the patch on every request with no
 * message (issue #183). It must now report the files it could not delete.
 */
final class Upgrade2511RmIndexHtmlTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = __DIR__ . '/../tmp/work/rm_' . uniqid('', true);
        mkdir($this->root . '/a', 0777, true);
        mkdir($this->root . '/b', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (['a', 'b'] as $dir) {
            $path = $this->root . '/' . $dir;
            if (is_dir($path)) {
                chmod($path, 0777);
                foreach (['index.html', 'index.php'] as $file) {
                    if (file_exists("$path/$file")) {
                        unlink("$path/$file");
                    }
                }
                rmdir($path);
            }
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    private function pair(string $dir): void
    {
        file_put_contents("{$this->root}/$dir/index.html", '<html></html>');
        file_put_contents("{$this->root}/$dir/index.php", '<?php');
    }

    #[Test]
    public function deletableFilesAreRemovedAndCheckPasses(): void
    {
        $this->pair('a');
        $this->pair('b');
        $patch = new Upgrade2511Stub($this->root);

        self::assertFalse($patch->check_rmindexhtml());
        self::assertTrue($patch->apply_rmindexhtml());
        self::assertFileDoesNotExist("{$this->root}/a/index.html");
        self::assertFileDoesNotExist("{$this->root}/b/index.html");
        self::assertTrue($patch->check_rmindexhtml());
        self::assertSame('', $patch->message());
    }

    #[Test]
    public function undeletableFileFailsTheTaskAndIsNamed(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Directory permissions do not block unlink() on Windows.');
        }
        if (function_exists('posix_geteuid') && 0 === posix_geteuid()) {
            self::markTestSkipped('root can unlink regardless of directory permissions.');
        }

        $this->pair('a');
        $this->pair('b');
        // Writable file in a directory the process cannot write: is_writable() is
        // true, unlink() fails. This is the shape reported in issue #183.
        chmod("{$this->root}/b/index.html", 0666);
        chmod("{$this->root}/b", 0555);
        $patch = new Upgrade2511Stub($this->root);

        self::assertFalse($patch->check_rmindexhtml());
        self::assertFalse($patch->apply_rmindexhtml(), 'an undeletable file must fail the task');
        self::assertFileDoesNotExist("{$this->root}/a/index.html", 'deletable files are still removed');
        self::assertFileExists("{$this->root}/b/index.html");
        self::assertStringContainsString('Could not delete 1 obsolete index.html', $patch->message());
        self::assertStringContainsString('b/index.html', $patch->message());
        self::assertFalse($patch->check_rmindexhtml(), 'still pending, and now reported instead of looping');
    }
}
