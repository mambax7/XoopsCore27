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

namespace modulesprotector;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the XoopsGTicket adapter against the REAL class/xoopssecurity.php in a
 * child process. The suite bootstrap stubs XoopsSecurity, so XoopsGTicketTest
 * works on a double; this test pins the contract the adapter depends on
 * (session entry shape, expiry, garbage collection, the error list). Any PHP
 * warning in the child is turned into a failure.
 *
 * @category  Xoops
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversNothing]
final class XoopsGTicketRealTest extends TestCase
{
    #[Test]
    public function adapterRoundTripOnTheRealXoopsSecurity(): void
    {
        $directory = sys_get_temp_dir() . '/xoops-gticket-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($directory, 0700, true));
        $root   = var_export(XOOPS_ROOT_PATH, true);
        $script = <<<PHP
<?php
declare(strict_types=1);
set_error_handler(static function (int \$no, string \$str, string \$file, int \$line): bool {
    throw new ErrorException(\$str, 0, \$no, \$file, \$line);
});
define('XOOPS_ROOT_PATH', {$root});
define('XOOPS_DB_PREFIX', 'xt');
define('XOOPS_URL', 'http://example.test');
require XOOPS_ROOT_PATH . '/xoops_lib/vendor/autoload.php';
\$xoopsLogger = new class { public function addExtra(string \$a, string \$b): void {} };
require XOOPS_ROOT_PATH . '/class/xoopssecurity.php';
\$xoopsSecurity = new XoopsSecurity();
require XOOPS_ROOT_PATH . '/xoops_lib/modules/protector/class/gtickets.php';
session_start();
\$g = \$GLOBALS['xoopsGTicket'];
\$r = [];

\$_POST['XOOPS_G_TICKET'] = \$g->issue('salt', 1800, 'area');
\$r['entry'] = \$_SESSION['XOOPS_G_TICKET_SESSION'][0];
\$r['coreSetUntouched'] = !isset(\$_SESSION['XOOPS_TOKEN_SESSION']);
\$r['roundTrip'] = \$g->check(true, 'area', false);
\$r['replay'] = \$g->check(true, 'area', false);
\$r['replayError'] = \$g->_errors;

\$_POST['XOOPS_G_TICKET'] = \$g->issue('', 1800);
\$_SESSION['XOOPS_G_TICKET_SESSION'] = array_map(static function (array \$e) { \$e['expire'] = time() - 1; return \$e; }, \$_SESSION['XOOPS_G_TICKET_SESSION']);
\$r['expired'] = \$g->check(true, '', false);
\$r['expiredError'] = \$g->_errors;

\$xoopsSecurity->errors = ['page'];
\$g->issue();
\$_POST['XOOPS_G_TICKET'] = str_repeat('0', 32);
\$r['unknown'] = \$g->check(true, '', false);
\$r['unknownError'] = \$g->_errors;
\$r['coreErrorsAfter'] = \$xoopsSecurity->errors;

\$g->issue('', 0);
\$last = end(\$_SESSION['XOOPS_G_TICKET_SESSION']);
\$r['zeroTimeoutIsShort'] = \$last['expire'] - time() <= 1;

echo json_encode(\$r);
PHP;
        file_put_contents($directory . '/run.php', $script);
        try {
            $process = proc_open(
                [PHP_BINARY, '-d', 'output_buffering=0', '-d', 'session.save_path=' . $directory, $directory . '/run.php'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors . $output);
            $r = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }

        self::assertSame(['id', 'token', 'expire'], array_keys($r['entry']), 'the entry shape isExpired() reads');
        self::assertTrue($r['coreSetUntouched'], 'a ticket never lands in the XOOPS_TOKEN set');
        self::assertTrue($r['roundTrip']);
        self::assertFalse($r['replay'], 'single use');
        self::assertSame(['No valid ticket-stub pair found'], $r['replayError']);
        self::assertFalse($r['expired']);
        self::assertSame(['Time out'], $r['expiredError'], 'expiry is seen before XoopsSecurity garbage-collects the entry');
        self::assertFalse($r['unknown']);
        self::assertSame(['No valid ticket-stub pair found'], $r['unknownError']);
        self::assertSame(['page'], $r['coreErrorsAfter'], 'the shared XoopsSecurity error list is restored');
        self::assertTrue($r['zeroTimeoutIsShort']);
    }
}
