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

namespace modulessystem;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/modules/system/class/maintenance.php';

/**
 * SystemMaintenance with its three seams pointed at the test: a temporary
 * dump directory, a controllable permission change and a fixed icon URL.
 *
 * @category  Xoops
 * @package   System
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class DumpWriteProbe extends \SystemMaintenance
{
    public static string $dir = '';

    public static bool $chmodSucceeds = true;

    public static function dumpDirectoryPath(): string
    {
        return self::$dir;
    }

    protected static function restrictDumpPermissions(string $file): bool
    {
        return self::$chmodSucceeds;
    }

    protected static function resultIcon(string $image): string
    {
        return 'icon:' . $image;
    }

    /** The real permission change, for the exception test. */
    public static function realRestrictDumpPermissions(string $file): bool
    {
        return parent::restrictDumpPermissions($file);
    }
}

/**
 * The SQL-dump directory fails closed.
 *
 * A dump holds the whole database (password hashes, e-mail addresses, the
 * site configuration). SystemMaintenance::dumpDirectory() created the dump
 * directory and its two deny-all guard files with @mkdir() and
 * @file_put_contents(), ignored every failure, and dump_write() then wrote
 * the dump anyway and ignored a failed @chmod($file, 0600).
 *
 * prepareDumpDirectory() now reports whether the directory and both guards
 * are in place (an existing .htaccess only counts when it denies all),
 * without letting a PHP warning (which would carry the full server path)
 * escape; dump_write() refuses to write when they are not, and reports a
 * dump whose permissions could not be restricted.
 *
 * @category  Xoops
 * @package   System
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class DumpDirectoryGuardTest extends TestCase
{
    private string $base = '';

    public static function setUpBeforeClass(): void
    {
        defined('_AM_SYSTEM_MAINTENANCE_DUMP_FILE_CREATED') || define('_AM_SYSTEM_MAINTENANCE_DUMP_FILE_CREATED', 'File created');
        defined('_AM_SYSTEM_MAINTENANCE_DUMP_RESULT') || define('_AM_SYSTEM_MAINTENANCE_DUMP_RESULT', 'Result');
    }

    protected function setUp(): void
    {
        if (!method_exists(\SystemMaintenance::class, 'prepareDumpDirectory')) {
            self::fail('SystemMaintenance::prepareDumpDirectory() is missing.');
        }
        $this->base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xoops-dumps-' . bin2hex(random_bytes(6));
        mkdir($this->base, 0777, true);
        DumpWriteProbe::$dir           = $this->base . DIRECTORY_SEPARATOR . 'dumps';
        DumpWriteProbe::$chmodSucceeds = true;
    }

    protected function tearDown(): void
    {
        DumpWriteProbe::$dir           = '';
        DumpWriteProbe::$chmodSucceeds = true;
        if ('' === $this->base || !is_dir($this->base)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->base);
    }

    /**
     * Run prepareDumpDirectory() and fail the test if any PHP warning or
     * notice escapes it.
     */
    private function prepare(string $dir): bool
    {
        $leaked = [];
        set_error_handler(static function (int $errno, string $message) use (&$leaked): bool {
            $leaked[] = $message;

            return true;
        });
        try {
            $result = \SystemMaintenance::prepareDumpDirectory($dir);
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $leaked, 'prepareDumpDirectory() let a PHP warning escape.');

        return $result;
    }

    /** @return list<string> dump files written into the probe's directory */
    private function writtenDumps(): array
    {
        return is_dir(DumpWriteProbe::$dir) ? glob(DumpWriteProbe::$dir . '/dump_*.sql') ?: [] : [];
    }

    private function dumpWrite(): array
    {
        $probe = (new \ReflectionClass(DumpWriteProbe::class))->newInstanceWithoutConstructor();

        return $probe->dump_write(["-- dump body\n", '']);
    }

    // ---------------------------------------------------------------------
    // prepareDumpDirectory()
    // ---------------------------------------------------------------------

    #[Test]
    public function aNewDirectoryIsCreatedWithBothGuardFiles(): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'dumps';

        self::assertTrue($this->prepare($dir));
        self::assertDirectoryExists($dir);
        self::assertStringContainsString('Require all denied', (string) file_get_contents($dir . '/.htaccess'));
        self::assertFileExists($dir . '/index.html');
    }

    #[Test]
    public function anExistingDenyAllGuardIsKeptAsItIs(): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'dumps';
        mkdir($dir);
        file_put_contents($dir . '/.htaccess', "# site-specific rules\nRequire all denied\n");
        file_put_contents($dir . '/index.html', '<!-- keep -->');

        self::assertTrue($this->prepare($dir));
        self::assertSame("# site-specific rules\nRequire all denied\n", file_get_contents($dir . '/.htaccess'));
        self::assertSame('<!-- keep -->', file_get_contents($dir . '/index.html'));
    }

    #[Test]
    public function anOlderDenyFromAllGuardIsAccepted(): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'dumps';
        mkdir($dir);
        file_put_contents($dir . '/.htaccess', "Order allow,deny\nDeny from all\n");

        self::assertTrue($this->prepare($dir));
    }

    #[Test]
    public function anEmptyGuardIsRewritten(): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'dumps';
        mkdir($dir);
        file_put_contents($dir . '/.htaccess', '');

        self::assertTrue($this->prepare($dir));
        self::assertStringContainsString('Require all denied', (string) file_get_contents($dir . '/.htaccess'));
    }

    /** @return array<string, array{string}> */
    public static function ineffectiveDenyRules(): array
    {
        return [
            'deny scoped to one file'   => ["<Files index.html>\nRequire all denied\n</Files>\n"],
            'deny then grant'           => ["Require all denied\nRequire all granted\n"],
            'deny with an ip exception' => ["Require all denied\nRequire ip 10.0.0.1\n"],
            'deny with allow from'      => ["Deny from all\nAllow from 10.0.0.1\n"],
            'satisfy any'               => ["Require all denied\nSatisfy any\n"],
            'deny inside RequireAny'    => ["<RequireAny>\nRequire all denied\n</RequireAny>\n"],
            'mismatched closing tag'    => ["<Files x>\n</IfModule>\nRequire all denied\n</Files>\n"],
            'stray closing tag'         => ["</Files>\nRequire all denied\n"],
            'unclosed container'        => ["Require all denied\n<Files x>\n"],
            'deny only inside IfModule' => ["<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('ineffectiveDenyRules')]
    public function aGuardWhoseDenyIsScopedOrOverriddenFailsClosed(string $rules): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'dumps';
        mkdir($dir);
        file_put_contents($dir . '/.htaccess', $rules);

        self::assertFalse($this->prepare($dir));
        self::assertSame($rules, file_get_contents($dir . '/.htaccess'));
    }

    #[Test]
    public function theGeneratedGuardIsAccepted(): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'dumps';
        self::assertTrue($this->prepare($dir));
        // Run again: the guard written the first time must pass the check.
        self::assertTrue($this->prepare($dir));
    }

    /**
     * @return bool whether the link could be made
     */
    private static function makeLink(string $target, string $link): bool
    {
        set_error_handler(static fn (): bool => true);
        try {
            return symlink($target, $link);
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function aSymlinkedDumpDirectoryIsRefused(): void
    {
        mkdir($this->base . DIRECTORY_SEPARATOR . 'elsewhere');
        if (!self::makeLink($this->base . DIRECTORY_SEPARATOR . 'elsewhere', $this->base . DIRECTORY_SEPARATOR . 'dumps')) {
            self::markTestSkipped('symlink() is not permitted here.');
        }

        self::assertFalse($this->prepare($this->base . DIRECTORY_SEPARATOR . 'dumps'));
    }

    #[Test]
    public function symlinkedGuardFilesAreRefused(): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'dumps';
        mkdir($dir);
        file_put_contents($this->base . DIRECTORY_SEPARATOR . 'outside.htaccess', "Require all denied\n");
        if (!self::makeLink($this->base . DIRECTORY_SEPARATOR . 'outside.htaccess', $dir . '/.htaccess')) {
            self::markTestSkipped('symlink() is not permitted here.');
        }
        self::assertFalse($this->prepare($dir), '.htaccess');

        unlink($dir . '/.htaccess');
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
        file_put_contents($this->base . DIRECTORY_SEPARATOR . 'outside.html', '');
        self::makeLink($this->base . DIRECTORY_SEPARATOR . 'outside.html', $dir . '/index.html');
        self::assertFalse($this->prepare($dir), 'index.html');
    }

    #[Test]
    public function aGuardWithoutADenyAllRuleFailsClosedAndIsLeftAlone(): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'dumps';
        mkdir($dir);
        file_put_contents($dir . '/.htaccess', "Options +Indexes\n");

        self::assertFalse($this->prepare($dir));
        self::assertSame("Options +Indexes\n", file_get_contents($dir . '/.htaccess'));
    }

    #[Test]
    public function aDirectoryThatCannotBeCreatedFailsClosed(): void
    {
        // A regular file where the parent directory should be: mkdir() must fail.
        file_put_contents($this->base . DIRECTORY_SEPARATOR . 'blocker', 'x');

        self::assertFalse($this->prepare($this->base . DIRECTORY_SEPARATOR . 'blocker' . DIRECTORY_SEPARATOR . 'dumps'));
    }

    #[Test]
    public function aGuardFileThatCannotBeWrittenFailsClosed(): void
    {
        $dir = $this->base . DIRECTORY_SEPARATOR . 'dumps';
        mkdir($dir);
        // A directory named .htaccess: the guard can be neither read nor written.
        mkdir($dir . DIRECTORY_SEPARATOR . '.htaccess');

        self::assertFalse($this->prepare($dir));
    }

    // ---------------------------------------------------------------------
    // dump_write()
    // ---------------------------------------------------------------------

    #[Test]
    public function dumpWriteWritesTheDumpAndLinksIt(): void
    {
        [, $html] = $this->dumpWrite();

        $dumps = $this->writtenDumps();
        self::assertCount(1, $dumps);
        self::assertSame("-- dump body\n", file_get_contents($dumps[0]));
        self::assertStringContainsString('op=dump_download&amp;file=' . urlencode(basename($dumps[0])), $html);
        self::assertStringContainsString('icon:success.png', $html);
        self::assertStringNotContainsString('0600', $html);
    }

    #[Test]
    public function dumpWriteRefusesWhenTheDirectoryCannotBeGuarded(): void
    {
        file_put_contents($this->base . DIRECTORY_SEPARATOR . 'blocker', 'x');
        DumpWriteProbe::$dir = $this->base . DIRECTORY_SEPARATOR . 'blocker' . DIRECTORY_SEPARATOR . 'dumps';

        [, $html] = $this->dumpWrite();

        self::assertSame(['blocker'], array_values(array_diff(scandir($this->base) ?: [], ['.', '..'])), 'Nothing but the blocking file may exist.');
        self::assertStringContainsString('no dump was written', $html);
        self::assertStringContainsString('icon:cancel.png', $html);
        self::assertStringNotContainsString('op=dump_download', $html);
    }

    #[Test]
    public function dumpWriteRefusesWhenTheGuardDoesNotDenyAll(): void
    {
        mkdir(DumpWriteProbe::$dir);
        file_put_contents(DumpWriteProbe::$dir . '/.htaccess', "Options +Indexes\n");

        [, $html] = $this->dumpWrite();

        self::assertSame([], $this->writtenDumps());
        self::assertStringContainsString('no dump was written', $html);
    }

    #[Test]
    public function dumpWriteKeepsAndReportsADumpWhosePermissionsCouldNotBeRestricted(): void
    {
        DumpWriteProbe::$chmodSucceeds = false;

        [, $html] = $this->dumpWrite();

        self::assertCount(1, $this->writtenDumps(), 'The dump is kept.');
        self::assertStringContainsString('op=dump_download', $html);
        self::assertStringContainsString('could not be restricted to the owner (0600)', $html);
    }

    #[Test]
    public function aFailedDumpWriteIsReportedWithoutAWarning(): void
    {
        if ('\\' === DIRECTORY_SEPARATOR || (function_exists('posix_geteuid') && 0 === posix_geteuid())) {
            self::markTestSkipped('A read-only directory cannot refuse the write here (Windows, or running as root).');
        }
        self::assertTrue($this->prepare(DumpWriteProbe::$dir));
        chmod(DumpWriteProbe::$dir, 0555);
        $leaked = [];
        set_error_handler(static function (int $errno, string $message) use (&$leaked): bool {
            $leaked[] = $message;

            return true;
        });
        try {
            [, $html] = $this->dumpWrite();
        } finally {
            restore_error_handler();
            chmod(DumpWriteProbe::$dir, 0755);
        }

        self::assertSame([], $leaked, 'The failed write let a PHP warning (with the full dump path) escape.');
        self::assertSame([], $this->writtenDumps());
        self::assertStringContainsString('icon:cancel.png', $html);
        self::assertStringNotContainsString('op=dump_download', $html);
    }

    #[Test]
    public function aShortDumpWriteCountsAsFailedAndIsRemoved(): void
    {
        $src = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/system/class/maintenance.php');

        self::assertSame(
            1,
            preg_match('/\$written\s*=\s*strlen\(\s*\$ret\[0\]\s*\)\s*===\s*file_put_contents\(\s*\$path_file\s*,\s*\$ret\[0\]\s*\)/', $src),
            'A dump write counts only when every byte was written.'
        );
        self::assertSame(
            1,
            preg_match('/if\s*\(\s*!\$written\s*&&\s*is_file\(\s*\$path_file\s*\)\s*\)\s*\{\s*unlink\(\s*\$path_file\s*\)/', $src),
            'A partial dump must be removed, not left behind.'
        );
    }

    #[Test]
    public function everyResultTableClosesItsRows(): void
    {
        $tables = [$this->dumpWrite()[1]];
        DumpWriteProbe::$chmodSucceeds = false;
        $tables[] = $this->dumpWrite()[1];
        file_put_contents($this->base . DIRECTORY_SEPARATOR . 'blocker', 'x');
        DumpWriteProbe::$dir = $this->base . DIRECTORY_SEPARATOR . 'blocker' . DIRECTORY_SEPARATOR . 'dumps';
        $tables[] = $this->dumpWrite()[1];

        foreach ($tables as $html) {
            self::assertStringNotContainsString('<tr></table>', $html);
            self::assertSame(substr_count($html, '<tr>'), substr_count($html, '</tr>'), $html);
        }
    }

    #[Test]
    public function aPermissionWarningTurnedIntoAnExceptionCountsAsAFailedChange(): void
    {
        set_error_handler(static function (int $errno, string $message): bool {
            throw new \ErrorException($message, 0, $errno);
        });
        try {
            $result = DumpWriteProbe::realRestrictDumpPermissions($this->base . DIRECTORY_SEPARATOR . 'no-such-dump.sql');
        } finally {
            restore_error_handler();
        }

        self::assertFalse($result);
    }

    // ---------------------------------------------------------------------
    // Source pins
    // ---------------------------------------------------------------------

    #[Test]
    public function newMessagesAreReadBehindDefinedGuardsAndNoCallIsSuppressed(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/modules/system/class/maintenance.php');
        self::assertNotFalse($src);

        foreach (['_AM_SYSTEM_MAINTENANCE_DUMP_DIR_UNSAFE', '_AM_SYSTEM_MAINTENANCE_DUMP_CHMOD_FAILED'] as $constant) {
            self::assertSame(1, preg_match("/defined\\('{$constant}'\\)/", $src), "$constant must be read behind defined(): translated packs predate it.");
        }
        self::assertSame(0, preg_match('/@\s*(chmod|mkdir|file_put_contents)\(/', $src), 'No error-suppressed call may remain in maintenance.php.');
    }
}
