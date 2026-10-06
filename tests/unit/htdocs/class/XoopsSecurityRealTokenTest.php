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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the REAL class/xoopssecurity.php token round trip in a child process.
 *
 * The suite bootstrap defines a stub XoopsSecurity before any test can load
 * the real file, so XoopsSecurityTest works on a mirror of the source. This
 * test loads the real file in a separate PHP process with a session store of
 * its own and reports each scenario as JSON. Any PHP warning in the child
 * (for example an unguarded $_SERVER['HTTP_USER_AGENT'] read) is turned into
 * a failure.
 *
 * @category  Xoops
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversNothing]
final class XoopsSecurityRealTokenTest extends TestCase
{
    #[Test]
    public function realClassTokenContract(): void
    {
        $directory = sys_get_temp_dir() . '/xoops-security-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($directory, 0700, true));
        $root     = var_export(XOOPS_ROOT_PATH, true);
        $script   = <<<PHP
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
session_start();
\$_SERVER['HTTP_USER_AGENT'] = 'Agent/1.0';
\$s = new XoopsSecurity();
\$r = [];

\$t = \$s->createToken();
\$r['shape'] = ['hex32' => (bool) preg_match('/^[a-f0-9]{32}\$/', \$t), 'entry' => \$_SESSION['XOOPS_TOKEN_SESSION'][0]];
\$r['legacyDigestOfNewEntry'] = \$s->validateToken(md5(\$r['shape']['entry']['id'] . 'Agent/1.0' . XOOPS_DB_PREFIX));
\$r['roundTrip'] = \$s->validateToken(\$t);
\$r['replay'] = \$s->validateToken(\$t);

\$t = \$s->createToken();
\$_SERVER['HTTP_USER_AGENT'] = 'Agent/2.0';
\$r['uaChange'] = \$s->validateToken(\$t);

\$t = \$s->createToken();
unset(\$_SERVER['HTTP_USER_AGENT']);
\$r['noUa'] = \$s->validateToken(\$t);

\$_SERVER['HTTP_USER_AGENT'] = 'Agent/1.0';
\$_SESSION['XOOPS_TOKEN_SESSION'] = [['id' => 'legacy-secret', 'expire' => time() + 300]];
\$r['legacyWrong'] = \$s->validateToken(str_repeat('0', 32));
\$r['legacy'] = \$s->validateToken(md5('legacy-secret' . 'Agent/1.0' . XOOPS_DB_PREFIX));
\$r['legacyReplay'] = \$s->validateToken(md5('legacy-secret' . 'Agent/1.0' . XOOPS_DB_PREFIX));

\$_SESSION['XOOPS_TOKEN_SESSION'] = [['id' => '', 'expire' => time() + 300]];
\$r['emptyId'] = \$s->validateToken(md5('' . 'Agent/1.0' . XOOPS_DB_PREFIX));

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

        self::assertTrue($r['shape']['hex32'], 'token stays 32 hex characters');
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $r['shape']['entry']['token'] ?? '', 'the public value is stored in the entry');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $r['shape']['entry']['id'], 'pre-2.7.4 code reads id unguarded and hashes it; it must be random');
        self::assertNotSame($r['shape']['entry']['id'], $r['shape']['entry']['token']);
        self::assertFalse($r['legacyDigestOfNewEntry'], 'the old digest of a new entry is not a valid token');
        self::assertTrue($r['roundTrip']);
        self::assertFalse($r['replay'], 'token is single use');
        self::assertTrue($r['uaChange'], 'a User-Agent change no longer rejects the form');
        self::assertTrue($r['noUa'], 'no warning and no rejection without a User-Agent header');
        self::assertFalse($r['legacyWrong']);
        self::assertTrue($r['legacy'], 'a token issued before the upgrade still validates');
        self::assertFalse($r['legacyReplay']);
        self::assertFalse($r['emptyId']);
    }
}
