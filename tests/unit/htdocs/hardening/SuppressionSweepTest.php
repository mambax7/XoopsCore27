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

namespace hardening;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Error-suppressed calls flagged by Scrutinizer, converted.
 *
 * Every call below was silenced with @ and its failure ignored. In core
 * they now go through xoops_remove_file_quietly() / xoops_chmod_quietly()
 * (one project-standard warning, no path), or through an explicit
 * session_status() check. The upgrade scripts boot before the full core
 * and do not load include/file_safety.php, so instead of pulling in that
 * dependency they use XoopsUpgrade::removeLeftover() (upgrade/class), whose
 * result they check and log.
 *
 * Out of scope on purpose: filelogger.php keeps @mkdir() and @fopen() in
 * write(), whose failures are already checked; without the @ a failed
 * open would raise a warning that the logger itself records, re-entering
 * the same failing write.
 *
 * @category  Xoops
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class SuppressionSweepTest extends TestCase
{
    private static function source(string $relative): string
    {
        $src = file_get_contents(dirname(XOOPS_ROOT_PATH) . '/' . $relative);
        self::assertNotFalse($src, "$relative not found");

        return $src;
    }

    /** @return array<string, array{string, list<string>}> */
    public static function convertedCalls(): array
    {
        return [
            'file logger'                => ['htdocs/class/logger/filelogger.php', ['@chmod(']],
            'debug config'               => ['htdocs/include/debugconfig.php', ['@unlink(']],
            'account avatar'             => ['htdocs/edituser.php', ['@unlink(']],
            'profile avatar'             => ['htdocs/modules/profile/edituser.php', ['@unlink(']],
            'Frameworks cache'           => ['htdocs/Frameworks/art/functions.cache.php', ['@unlink(']],
            'session start'              => ['htdocs/include/common.php', ['@ini_set(', '@session_start(']],
            'upgrade 2.5.11 to 2.7.0'    => ['upgrade/upd_2.5.11-to-2.7.0/index.php', ['@rmdir(']],
            'upgrade 2.0.18 path helper' => ['upgrade/upd-2.0.18-to-2.3.0/pathcontroller.php', ['@mkdir(', '@chmod(', '@chgrp(']],
            'upgrade 2.3.3 to 2.4.0'     => ['upgrade/upd-2.3.3-to-2.4.0/index.php', ['@unlink(']],
            'upgrade 2.4.0 to 2.4.1'     => ['upgrade/upd-2.4.0-to-2.4.1/index.php', ['@unlink(']],
        ];
    }

    /**
     * @param list<string> $calls
     */
    #[Test]
    #[DataProvider('convertedCalls')]
    public function noFlaggedCallIsSuppressedAnyMore(string $file, array $calls): void
    {
        $src = self::source($file);
        foreach ($calls as $call) {
            self::assertStringNotContainsString($call, $src, "$file still contains $call");
        }
    }

    /** @return array<string, array{string, string}> */
    public static function coreHelperUse(): array
    {
        return [
            'file logger'      => ['htdocs/class/logger/filelogger.php', 'xoops_chmod_quietly('],
            'debug config'     => ['htdocs/include/debugconfig.php', 'xoops_remove_file_quietly('],
            'account avatar'   => ['htdocs/edituser.php', 'xoops_remove_file_quietly('],
            'profile avatar'   => ['htdocs/modules/profile/edituser.php', 'xoops_remove_file_quietly('],
            'Frameworks cache' => ['htdocs/Frameworks/art/functions.cache.php', 'xoops_remove_file_quietly('],
        ];
    }

    #[Test]
    #[DataProvider('coreHelperUse')]
    public function coreCallSitesUseTheFileSafetyHelpers(string $file, string $helper): void
    {
        $src = self::source($file);

        self::assertStringContainsString($helper, $src);
        self::assertSame(1, preg_match("#require_once\\s+XOOPS_ROOT_PATH\\s*\\.\\s*'/include/file_safety\\.php'#", $src), "$file must load include/file_safety.php before using $helper");
    }

    #[Test]
    public function upgradeScriptsDoNotDependOnTheFileSafetyHelpers(): void
    {
        foreach (['upgrade/upd_2.5.11-to-2.7.0/index.php', 'upgrade/upd-2.0.18-to-2.3.0/pathcontroller.php', 'upgrade/upd-2.3.3-to-2.4.0/index.php', 'upgrade/upd-2.4.0-to-2.4.1/index.php'] as $file) {
            $src = self::source($file);
            self::assertStringNotContainsString('file_safety.php', $src, $file);
            self::assertSame(0, preg_match('/xoops_(remove_file|chmod)_quietly\(/', $src), $file);
        }
    }

    #[Test]
    public function sessionStartIsGuardedByTheSessionStatus(): void
    {
        $src = self::source('htdocs/include/common.php');

        self::assertStringNotContainsString("function_exists('session_status')", $src, 'The PHP 5.3 fallback is dead on PHP 8.');
        self::assertSame(
            1,
            preg_match('/PHP_SESSION_ACTIVE\s*!==\s*session_status\(\)\s*&&\s*false\s*===\s*ini_set\(\s*\'session\.gc_maxlifetime\'/', $src),
            'gc_maxlifetime may only be set before the session starts, and a failed ini_set() must be reported.'
        );
        self::assertSame(
            1,
            preg_match('/set_error_handler\([^;]*;\s*try\s*\{\s*\$sessionStarted\s*=\s*session_start\(\);\s*\}\s*finally\s*\{\s*restore_error_handler\(\);\s*\}\s*if\s*\(\s*!\$sessionStarted\s*\)\s*\{\s*trigger_error\(/', $src),
            'session_start() must run inside a scoped handler (its warning can name the save path) and a failure must be reported once.'
        );
    }

    /** @return array<string, array{string, int}> */
    public static function upgradeCleanups(): array
    {
        return [
            'upgrade 2.5.11 to 2.7.0' => ['upgrade/upd_2.5.11-to-2.7.0/index.php', 1],
            'upgrade 2.3.3 to 2.4.0'  => ['upgrade/upd-2.3.3-to-2.4.0/index.php', 3],
            'upgrade 2.4.0 to 2.4.1'  => ['upgrade/upd-2.4.0-to-2.4.1/index.php', 6],
        ];
    }

    /**
     * Every clean-up consumes the result of XoopsUpgrade::removeLeftover()
     * (tested in the upgrade suite) and logs a failure; no bare unlink() or
     * rmdir() of those paths is left.
     */
    #[Test]
    #[DataProvider('upgradeCleanups')]
    public function upgradeCleanupsLogAFailedRemoval(string $file, int $expected): void
    {
        $src = self::source($file);

        self::assertSame(
            $expected,
            preg_match_all('/if\s*\(\s*!\$this->removeLeftover\(\s*\$\w+\s*\)\s*\)\s*\{\s*\$this->logs\[\]\s*=/', $src)
        );
        self::assertSame(0, preg_match('/\b(unlink|rmdir)\(\s*\$(tmpFile|emptyDir)\s*\)/', $src), "$file still removes a leftover with a bare call.");
    }
}
