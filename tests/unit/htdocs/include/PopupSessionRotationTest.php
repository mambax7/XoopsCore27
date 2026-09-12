<?php
/**
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

declare(strict_types=1);

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Runs the shipped popup in PHP with real sessions and output buffering disabled. */
final class PopupSessionRotationTest extends TestCase
{
    #[Test]
    public function popupRotatesBeforeOutputAndRefusesAFailedRotation(): void
    {
        $directory = sys_get_temp_dir() . '/xoops-popup-' . bin2hex(random_bytes(6));
        mkdir($directory);
        mkdir($directory . '/language');
        mkdir($directory . '/language/english');
        file_put_contents($directory . '/language/english/user.php', '<?php');
        $source = file_get_contents(dirname(__DIR__, 4) . '/extras/login.php');
        $source = str_replace("'/path/to/xoops/directory'", var_export($directory, true), $source);
        file_put_contents($directory . '/popup.php', $source);
        $bootstrap = <<<'PHP'
<?php
namespace Xmf {
    class Request {
        public static function getString(string $name, string $default = '', string $source = ''): string {
            return ['op' => 'dologin', 'username' => 'user', 'userpass' => 'password'][$name] ?? $default;
        }
    }
}
namespace {
    define('XOOPS_ROOT_PATH', __DIR__);
    define('XOOPS_URL', 'http://example.test');
    define('XOOPS_GROUP_ADMIN', 1);
    define('_CHARSET', 'UTF-8');
    define('_LANGCODE', 'en');
    foreach (['_US_2FA_REQUIRED', '_US_LOGGINGU', '_CLOSE', '_US_2FA_UNAVAILABLE', '_US_INCORRECTLOGIN', '_US_2FA_STARTAGAIN'] as $name) { define($name, $name); }
    $xoopsConfig = ['language' => 'english', 'sitename' => 'Test', 'theme_set' => 'default', 'closesite' => 0, 'use_ssl' => 0];
    session_start();
    $initial = session_id();
    // an earlier identity on the same session must not survive a failed rotation
    $_SESSION['xoopsUserId'] = 7;
    register_shutdown_function(static function () use ($initial): void {
        file_put_contents(__DIR__ . '/result.json', json_encode(['initial' => $initial, 'final' => session_id(), 'uid' => $_SESSION['xoopsUserId'] ?? null]));
    });
    $sess_handler = new class {
        public function regenerate_id(bool $delete): bool { return ($GLOBALS['argv'][1] ?? '') === 'fail' ? false : session_regenerate_id($delete); }
    };
    class MyTextSanitizer { public static function getInstance(): self { return new self(); } }
    class XoopsUser2faHandler {
        public const STATE_UNAVAILABLE = 'unavailable';
        public static function mustChallenge(string $policy, string $state): bool { return false; }
        public static function policy(array $config): string { return 'optional'; }
    }
    function xoops_getHandler(string $name): object {
        return $name === 'user2fa' ? new class {
            public function getRow(int $uid): ?array { return null; }
            public function stateOfRow(?array $row): string { return 'none'; }
        } : new class {
            public function loginUser(string $name, string $password): object { return new class {
                public function getVar(string $name): mixed { return ['uid' => 9, 'level' => 1, 'uname' => 'user'][$name] ?? null; }
                public function setVar(string $name, mixed $value): void {}
                public function getGroups(): array { return [2]; }
            }; }
            public function insertUser(object $user): bool { return true; }
        };
    }
    function xoops_loadLanguage(string $name): void {}
    function xoops_getcss(string $theme): string { return ''; }
    function xoops_error(string $message): void { echo $message; }
    function redirect_header(...$args): never { exit(); }
}
PHP;
        file_put_contents($directory . '/mainfile.php', $bootstrap);
        try {
            foreach (['success', 'fail'] as $mode) {
                $process = proc_open([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'session.save_path=' . $directory,
                    $directory . '/popup.php', $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $errors . $output);
                $result = json_decode(file_get_contents($directory . '/result.json'), true, 512, JSON_THROW_ON_ERROR);
                if ($mode === 'success') {
                    self::assertNotSame($result['initial'], $result['final'], $errors);
                    self::assertSame(9, $result['uid']);
                } else {
                    self::assertNull($result['uid'], 'Failed session rotation must never authenticate the old session');
                }
            }
        } finally {
            foreach (glob($directory . '/*') as $file) { if (is_file($file)) { unlink($file); } }
            unlink($directory . '/language/english/user.php');
            rmdir($directory . '/language/english');
            rmdir($directory . '/language');
            rmdir($directory);
        }
    }
}
