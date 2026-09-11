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

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RedirectHeaderException;

/**
 * Executes include/loginsession.php. The file is loaded, as written, into an
 * isolated namespace where every collaborator (auth factory, member and
 * notification handlers, preload, session handler, cookie and key helpers)
 * is a spy that records its call in order, so the helpers' behaviour is
 * proven by running them rather than by matching their source text.
 *
 * Each test runs in its own process: the helpers need site constants the
 * bootstrap does not define, and the file can be evaluated only once.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class LoginSessionBehaviourTest extends TestCase
{
    private const NS = 'Tests\\Unit\\Include\\LoginSessionSandbox';

    private string $emptyInclude = '';

    protected function setUp(): void
    {
        defined('XOOPS_COOKIE_DOMAIN') || define('XOOPS_COOKIE_DOMAIN', 'localhost');
        defined('_US_NOACTTPADM') || define('_US_NOACTTPADM', 'Not activated');
        defined('_US_LOGGINGU') || define('_US_LOGGINGU', 'Logging in as %s');

        $this->emptyInclude = (string) tempnam(sys_get_temp_dir(), 'xoops-auth-');
        file_put_contents($this->emptyInclude, '<?php');
        $GLOBALS['xoops'] = new class($this->emptyInclude) {
            public function __construct(private string $file)
            {
            }

            public function path(string $p): string
            {
                return $this->file;
            }
        };
        $GLOBALS['sandboxLog']          = [];
        $GLOBALS['sandboxAuthResult']   = false;
        $GLOBALS['sandboxInsertResult'] = true;
        $GLOBALS['sandboxKey']          = null;
        $GLOBALS['sess_handler']        = new class {
            public function regenerate_id(bool $delete): void
            {
                $GLOBALS['sandboxLog'][] = 'regenerate_id:uid=' . ($_SESSION['xoopsUserId'] ?? 'none');
            }
        };
        $GLOBALS['xoopsConfig'] = [
            'closesite'         => 0,
            'closesite_okgrp'   => [],
            'theme_set_allowed' => ['default'],
            'usercookie'        => 'xoops_user',
        ];
        $_SESSION = ['xoopsUserId' => 'stale', 'other' => 'stale'];
        $this->loadSandbox();
    }

    protected function tearDown(): void
    {
        if (is_file($this->emptyInclude)) {
            unlink($this->emptyInclude);
        }
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function wrongCredentialsReturnFalseAndWriteNothing(): void
    {
        $GLOBALS['sandboxAuthResult'] = false;
        $fn = self::NS . '\\xoops_login_authenticate';

        self::assertFalse($fn('alice', 'wrong'));
        self::assertSame(['loadLanguage:auth', 'authenticate:alice'], $GLOBALS['sandboxLog']);
        self::assertSame(['xoopsUserId' => 'stale', 'other' => 'stale'], $_SESSION);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function anUnactivatedAccountIsRedirectedWithoutASession(): void
    {
        $GLOBALS['sandboxAuthResult'] = $this->user(level: 0);
        $fn = self::NS . '\\xoops_login_authenticate';
        try {
            $fn('alice', 'pw');
            self::fail('expected a redirect');
        } catch (RedirectHeaderException $e) {
            self::assertSame(XOOPS_URL . '/index.php', $e->url);
            self::assertSame(_US_NOACTTPADM, $e->getMessage());
        }
        self::assertSame(['xoopsUserId' => 'stale', 'other' => 'stale'], $_SESSION);
        self::assertSame(['loadLanguage:auth', 'authenticate:alice'], $GLOBALS['sandboxLog']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aClosedSiteAdmitsOnlyTheAllowedGroupsAndAdministrators(): void
    {
        $GLOBALS['xoopsConfig']['closesite']       = 1;
        $GLOBALS['xoopsConfig']['closesite_okgrp'] = [7];
        $fn = self::NS . '\\xoops_login_authenticate';

        $GLOBALS['sandboxAuthResult'] = $this->user(groups: [3]);
        try {
            $fn('alice', 'pw');
            self::fail('expected a redirect');
        } catch (RedirectHeaderException $e) {
            self::assertSame(_NOPERM, $e->getMessage());
        }

        $allowed                      = $this->user(groups: [3, 7]);
        $GLOBALS['sandboxAuthResult'] = $allowed;
        self::assertSame($allowed, $fn('alice', 'pw'));

        $admin                        = $this->user(groups: [XOOPS_GROUP_ADMIN]);
        $GLOBALS['sandboxAuthResult'] = $admin;
        self::assertSame($admin, $fn('alice', 'pw'));
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aSuccessfulCheckReturnsTheAdapterObjectWithoutSessionOrLoginStateWrites(): void
    {
        $user                         = $this->user();
        $GLOBALS['sandboxAuthResult'] = $user;
        $fn = self::NS . '\\xoops_login_authenticate';

        self::assertSame($user, $fn('alice', 'pw'));
        self::assertSame(['loadLanguage:auth', 'authenticate:alice'], $GLOBALS['sandboxLog']);
        self::assertSame([], $user->writes, 'authenticate must not set anything on the account');
        self::assertSame(['xoopsUserId' => 'stale', 'other' => 'stale'], $_SESSION);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function establishingASessionRunsEveryStepInOrderAndRedirectsHome(): void
    {
        $user = $this->user();
        $fn   = self::NS . '\\xoops_login_establish_session';
        try {
            $fn($user, false, '');
            self::fail('expected a redirect');
        } catch (RedirectHeaderException $e) {
            self::assertSame(XOOPS_URL . '/index.php', $e->url);
            self::assertSame(sprintf(_US_LOGGINGU, 'alice'), $e->getMessage());
        }
        self::assertSame([
            'setVar:last_login',
            'insertUser:5',
            'regenerate_id:uid=stale',              // the old session is still there when it is regenerated
            'event:core.behavior.user.login:uid=5', // ... and the new one is seeded before the event
            'setcookie:xoops_user:expire',
            'setcookie:xoops_user:expire',
            'doLoginMaintenance:5',
        ], $GLOBALS['sandboxLog']);
        self::assertSame(['xoopsUserId' => 5, 'xoopsUserGroups' => [2], 'xoopsUserTheme' => 'default'], $_SESSION);
        self::assertGreaterThan(0, $user->vars['last_login']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aRememberRequestIssuesATokenCarryingTheUidAndTheFingerprint(): void
    {
        $storage = new \Xmf\Key\ArrayStorage();
        $storage->save('rememberme', str_repeat('k', 32));
        $GLOBALS['sandboxKey'] = new \Xmf\Key\Basic($storage, 'rememberme');
        $user = $this->user();
        $fn   = self::NS . '\\xoops_login_establish_session';
        try {
            $fn($user, true, '');
        } catch (RedirectHeaderException) {
        }
        $log = $GLOBALS['sandboxLog'];
        self::assertContains('rememberKey', $log);
        $issued = array_values(array_filter($log, static fn (string $l): bool => str_starts_with($l, 'setcookie:xoops_user:issue:')));
        self::assertCount(1, $issued);
        self::assertNotContains('setcookie:xoops_user:expire', $log);
        self::assertLessThan(array_search($issued[0], $log, true), array_search('rememberKey', $log, true));

        $token  = substr($issued[0], strlen('setcookie:xoops_user:issue:'));
        $claims = \Xmf\Jwt\TokenReader::fromString($GLOBALS['sandboxKey'], $token);
        self::assertIsObject($claims);
        self::assertSame(5, $claims->uid);
        self::assertSame('fp-of-' . $user->vars['pass'], $claims->pfp);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aRememberRequestWithoutAReadableKeyExpiresTheCookieInstead(): void
    {
        $GLOBALS['sandboxKey'] = null;
        $fn = self::NS . '\\xoops_login_establish_session';
        try {
            $fn($this->user(), true, '');
        } catch (RedirectHeaderException) {
        }
        $log = $GLOBALS['sandboxLog'];
        self::assertContains('rememberKey', $log);
        self::assertCount(2, array_keys($log, 'setcookie:xoops_user:expire', true));
        self::assertSame([], array_filter($log, static fn (string $l): bool => str_contains($l, ':issue:')));
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function withNoCookieNameConfiguredNoCookieIsTouched(): void
    {
        $GLOBALS['xoopsConfig']['usercookie'] = '';
        $fn = self::NS . '\\xoops_login_establish_session';
        try {
            $fn($this->user(), true, '');
        } catch (RedirectHeaderException) {
        }
        self::assertSame([], array_filter($GLOBALS['sandboxLog'], static fn (string $l): bool => str_starts_with($l, 'setcookie:') || 'rememberKey' === $l));
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function theRedirectTargetIsBuiltFromTheSiteUrlAndDropsRegistrationPages(): void
    {
        $fn = self::NS . '\\xoops_login_establish_session';
        try {
            $fn($this->user(), false, '/modules/news/article.php?storyid=3');
            self::fail('expected a redirect');
        } catch (RedirectHeaderException $e) {
            self::assertSame(XOOPS_URL . '/modules/news/article.php?storyid=3', $e->url);
        }
        try {
            $fn($this->user(), false, '/register.php');
            self::fail('expected a redirect');
        } catch (RedirectHeaderException $e) {
            self::assertSame(XOOPS_URL . '/index.php', $e->url);
        }
    }

    /**
     * @param int[] $groups
     */
    private function user(int $level = 1, array $groups = [2]): object
    {
        $class = self::NS . '\\XoopsUser';

        return new $class(['uid' => 5, 'uname' => 'alice', 'pass' => '$2y$hash', 'level' => $level, 'theme' => 'default'], $groups);
    }

    private function loadSandbox(): void
    {
        if (function_exists(self::NS . '\\xoops_login_authenticate')) {
            return;
        }
        $stubs = <<<'PHP'
        namespace Tests\Unit\Include\LoginSessionSandbox;

        class XoopsUser {
            public array $writes = [];
            public function __construct(public array $vars, private array $groups) {}
            public function getVar(string $k, string $f = 's'): mixed { return $this->vars[$k] ?? null; }
            public function setVar(string $k, mixed $v): void { $this->vars[$k] = $v; $this->writes[] = $k; $GLOBALS['sandboxLog'][] = 'setVar:' . $k; }
            public function getGroups(): array { return $this->groups; }
        }
        class XoopsDatabaseFactory {
            public static function getDatabaseConnection(): object { return new class { public function escape(string $s): string { return $s; } }; }
        }
        class XoopsAuthFactory {
            public static function getAuthConnection(string $uname): object {
                return new class { public function authenticate(string $u, string $p): mixed { $GLOBALS['sandboxLog'][] = 'authenticate:' . $u; return $GLOBALS['sandboxAuthResult']; } };
            }
        }
        class XoopsPreload {
            public static function getInstance(): self { return new self(); }
            public function triggerEvent(string $name, mixed $arg): void { $GLOBALS['sandboxLog'][] = 'event:' . $name . ':uid=' . ($_SESSION['xoopsUserId'] ?? 'none'); }
        }
        class XoopsUserUtility {
            public static function rememberKey(): ?\Xmf\Key\KeyAbstract { $GLOBALS['sandboxLog'][] = 'rememberKey'; return $GLOBALS['sandboxKey']; }
            public static function rememberFingerprint(object $user, string $signing): string { return 'fp-of-' . $user->getVar('pass'); }
        }
        function xoops_loadLanguage(string $name): void { $GLOBALS['sandboxLog'][] = 'loadLanguage:' . $name; }
        function xoops_load(string $name): void {}
        function xoops_validateThemeName(string $name): string { return $name; }
        function xoops_getHandler(string $name): object {
            return match ($name) {
                'member' => new class { public function insertUser(object $u): bool { $GLOBALS['sandboxLog'][] = 'insertUser:' . $u->getVar('uid'); return $GLOBALS['sandboxInsertResult']; } },
                'notification' => new class { public function doLoginMaintenance(int $uid): void { $GLOBALS['sandboxLog'][] = 'doLoginMaintenance:' . $uid; } },
            };
        }
        function xoops_setcookie(string $name, ?string $value, int $expire, string $path = '/', string $domain = '', $secure = false, bool $httponly = false): void {
            $GLOBALS['sandboxLog'][] = 'setcookie:' . $name . ($expire > time() ? ':issue:' . $value : ':expire');
        }
        PHP;
        eval($stubs);

        $source = file_get_contents(dirname(__DIR__, 4) . '/htdocs/include/loginsession.php');
        self::assertNotFalse($source);
        self::assertStringStartsWith('<?php', $source);
        eval('namespace ' . self::NS . ";\n" . substr($source, strlen('<?php')));
    }
}
