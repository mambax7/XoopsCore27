<?php

declare(strict_types=1);

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

/** Executes the management controller with observable storage, authentication and rendering boundaries. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Manage2faControllerTest extends TestCase
{
    private const NS = 'Tests\\Unit\\Include\\Manage2faSandbox';
    private static string $body;

    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/XoopsTotp.php';
        require_once XOOPS_ROOT_PATH . '/include/twofactor.php';
        preg_match_all('/\b_US_2FA[A-Z_]*\b/', file_get_contents(XOOPS_ROOT_PATH . '/include/manage2fa.php'), $constants);
        foreach (array_unique($constants[0]) as $constant) {
            defined($constant) || define($constant, $constant);
        }
        defined('XOOPS_PROT') || define('XOOPS_PROT', 'https://');
        defined('XOOPS_COOKIE_DOMAIN') || define('XOOPS_COOKIE_DOMAIN', '');
        defined('_LANGCODE') || define('_LANGCODE', 'en');
        defined('_CHARSET') || define('_CHARSET', 'UTF-8');
        defined('_US_2FAM_TITLE') || define('_US_2FAM_TITLE', 'Two-factor settings');
        $this->sandbox();
        $_SESSION = $_POST = $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $GLOBALS['manageLog'] = [];
        $GLOBALS['manageRow'] = null;
        $GLOBALS['manageAuth'] = true;
        $GLOBALS['manageToken'] = true;
        $GLOBALS['manageCrypto'] = true;
        $GLOBALS['manageInstalled'] = true;
        $GLOBALS['manageAccept'] = true;
        $GLOBALS['manageSessionFails'] = false;
        $GLOBALS['xoopsConfig'] = ['sitename' => 'Test', 'usercookie' => 'remember', 'twofactor_mode' => 'optional'];
        $userClass = self::NS . '\\XoopsUser';
        $GLOBALS['xoopsUser'] = new $userClass(9);
        $GLOBALS['xoopsModule'] = new class { public function mid(): int { return 1; } };
        $GLOBALS['xoopsSecurity'] = new class {
            public function check(): bool { $GLOBALS['manageLog'][] = 'csrf'; return $GLOBALS['manageToken']; }
            public function getTokenHTML(): string { return '<input name="token">'; }
        };
    }

    private function execute(array $post = [], bool $admin = false): array
    {
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
        $xoops2faAdminReset = $admin;
        try {
            eval(self::$body);
        } catch (\Tests\Unit\Include\Manage2faSandbox\Rendered $e) {
            return $e->vars;
        }
        self::fail('Controller did not render');
    }

    #[Test]
    public function setupGetDoesNotProvisionOrWriteAndUsesCoreRoutes(): void
    {
        $vars = $this->execute();
        self::assertSame('', $vars['secret']);
        self::assertSame('Two-factor settings', $vars['labels']['title']);
        self::assertSame([], $_SESSION);
        self::assertSame([], array_values(array_filter($GLOBALS['manageLog'], static fn ($v) => !str_starts_with($v, 'header:'))));
        self::assertSame(XOOPS_URL . '/user.php', $vars['action_url']);
        self::assertContains('header:X-Frame-Options: DENY', $GLOBALS['manageLog']);
        self::assertContains('header:Cache-Control: no-store', $GLOBALS['manageLog']);
    }

    #[Test]
    public function csrfAndPasswordPrecedeEveryMutation(): void
    {
        foreach (['begin', 'disable', 'regenerate', 'reset'] as $action) {
            $GLOBALS['manageRow'] = $action === 'begin' ? null : ['state' => 'enrolled', 'generation' => 'gen'];
            $GLOBALS['manageToken'] = false;
            $GLOBALS['manageLog'] = [];
            $vars = $this->execute(['action' => $action, 'password' => 'correct', 'uid' => 12], $action === 'reset');
            self::assertSame(_US_2FAM_STARTAGAIN, $vars['error']);
            self::assertNotContains('reauth:9', $GLOBALS['manageLog']);
            $GLOBALS['manageToken'] = true;
            $GLOBALS['manageAuth'] = false;
            $GLOBALS['manageLog'] = [];
            $vars = $this->execute(['action' => $action, 'password' => 'wrong', 'uid' => 12], $action === 'reset');
            self::assertSame(_US_2FAM_BADPASSWORD, $vars['error']);
            self::assertSame(['csrf', 'reauth:9'], array_values(array_filter($GLOBALS['manageLog'], static fn ($v) => !str_starts_with($v, 'header:'))));
        }
    }

    #[Test]
    public function beginStoresCiphertextAndConfirmationDisplaysCodesOnce(): void
    {
        $vars = $this->execute(['action' => 'begin', 'password' => 'correct']);
        self::assertNotSame('', $vars['secret']);
        self::assertTrue($vars['confirm_setup']);
        self::assertStringStartsWith('encrypted:', $_SESSION['xoops2faSetup']['blob']);
        self::assertNotSame($vars['secret'], $_SESSION['xoops2faSetup']['blob']);
        self::assertSame('', $vars['qr'], 'manual key works without the optional QR package');
        $code = \XoopsTotp::codeAt($vars['secret'], \XoopsTotp::stepAt(time()));
        $vars = $this->execute(['action' => 'confirm', 'code' => $code]);
        self::assertSame(_US_2FAM_DONE, $vars['message'], json_encode([$vars['error'], $GLOBALS['manageLog'], $_SESSION]));
        self::assertCount(10, $vars['codes']);
        self::assertSame('newgen', $_SESSION['xoops2faGeneration']);
        self::assertTrue($_SESSION['xoops2faVerified']);
        self::assertArrayNotHasKey('xoops2faSetup', $_SESSION);
        self::assertSame([], $this->execute()['codes']);
    }

    #[Test]
    public function failedSessionRotationDoesNotDisplayCommittedRecoveryCodes(): void
    {
        $vars = $this->execute(['action' => 'begin', 'password' => 'correct']);
        $code = \XoopsTotp::codeAt($vars['secret'], \XoopsTotp::stepAt(time()));
        $GLOBALS['manageSessionFails'] = true;
        $vars = $this->execute(['action' => 'confirm', 'code' => $code]);
        self::assertSame(_US_2FAM_UNAVAILABLE, $vars['error']);
        self::assertSame('', $vars['message']);
        self::assertSame([], $vars['codes']);
        self::assertSame([], $_SESSION);
        self::assertSame('enrolled', $GLOBALS['manageRow']['state'], 'Committed enrolment is retained');
        self::assertNotContains('notice', $GLOBALS['manageLog']);
    }

    #[Test]
    public function unavailableCryptoPreventsSetupAndStoresNothing(): void
    {
        $GLOBALS['manageCrypto'] = false;
        $vars = $this->execute(['action' => 'begin', 'password' => 'correct']);
        self::assertSame(_US_2FAM_UNAVAILABLE, $vars['error']);
        self::assertSame([], $_SESSION);
        self::assertNotContains('enrol', $GLOBALS['manageLog']);
    }

    #[Test]
    public function staleSetupIsRefusedAndFiveWrongCodesRemovePendingSecret(): void
    {
        $this->execute(['action' => 'begin', 'password' => 'correct']);
        $_SESSION['xoops2faSetup']['generation'] = 'old';
        $vars = $this->execute(['action' => 'confirm', 'code' => '000000']);
        self::assertSame(_US_2FAM_STARTAGAIN, $vars['error']);
        self::assertArrayNotHasKey('xoops2faSetup', $_SESSION);
        $this->execute(['action' => 'begin', 'password' => 'correct']);
        for ($i = 0; $i < 5; ++$i) {
            $vars = $this->execute(['action' => 'confirm', 'code' => 'invalid']);
            self::assertSame(_US_2FA_BADCODE, $vars['error']);
        }
        self::assertArrayNotHasKey('xoops2faSetup', $_SESSION);
        self::assertNotContains('enrol', $GLOBALS['manageLog']);
    }

    #[Test]
    public function rejectedManagementCodeCountsOnlyAgainstItsGeneration(): void
    {
        $GLOBALS['manageRow'] = ['state' => 'enrolled', 'generation' => 'gen'];
        $GLOBALS['manageAccept'] = false;
        $vars = $this->execute(['action' => 'disable', 'password' => 'correct', 'code' => 'invalid']);
        self::assertSame(_US_2FA_BADCODE, $vars['error']);
        self::assertContains('failure:9:gen', $GLOBALS['manageLog']);
        self::assertSame([], $vars['codes']);
        self::assertNotContains('notice', $GLOBALS['manageLog']);
    }

    #[Test]
    public function missingInstallationCannotStartSetup(): void
    {
        $GLOBALS['manageInstalled'] = false;
        $vars = $this->execute(['action' => 'begin', 'password' => 'correct']);
        self::assertSame(_US_2FAM_UNAVAILABLE, $vars['error']);
        self::assertFalse($vars['installed']);
        self::assertNotContains('provision', $GLOBALS['manageLog']);
    }

    #[Test]
    public function managementUsesGenerationAndAdminUsesActingPassword(): void
    {
        $GLOBALS['manageRow'] = ['state' => 'enrolled', 'generation' => 'gen'];
        $vars = $this->execute(['action' => 'regenerate', 'password' => 'correct', 'recovery' => 'recovery']);
        self::assertCount(10, $vars['codes']);
        self::assertContains('manage:9:gen:regenerate', $GLOBALS['manageLog']);
        $GLOBALS['manageLog'] = [];
        $notices = [];
        set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = [$level, $message];
            return true;
        });
        try {
            $vars = $this->execute(['action' => 'reset', 'password' => 'correct', 'uid' => 12], true);
        } finally {
            restore_error_handler();
        }
        self::assertSame([[E_USER_NOTICE, 'Two-factor admin reset: actor 9, uid 12']], $notices);
        self::assertSame(_US_2FAM_RESET_DONE, $vars['message']);
        self::assertContains('reauth:9', $GLOBALS['manageLog']);
        self::assertContains('disable:12', $GLOBALS['manageLog']);
    }

    #[Test]
    public function adminResetWithoutAnActiveFactorIsAnAuthenticatedNoOp(): void
    {
        foreach ([null, ['state' => 'disabled', 'generation' => 'oldgen']] as $row) {
            $GLOBALS['manageRow'] = $row;
            $GLOBALS['manageLog'] = [];
            $notices = [];
            set_error_handler(static function (int $level, string $message) use (&$notices): bool {
                $notices[] = [$level, $message];
                return true;
            });
            try {
                $vars = $this->execute(['action' => 'reset', 'password' => 'correct', 'uid' => 12], true);
            } finally {
                restore_error_handler();
            }
            self::assertSame([], $notices, 'No-op reset must not emit an audit notice');
            self::assertSame('', $vars['error']);
            self::assertSame(_US_2FAM_DISABLED, $vars['message']);
            self::assertContains('reauth:9', $GLOBALS['manageLog']);
            self::assertNotContains('disable:12', $GLOBALS['manageLog']);
            self::assertNotContains('notice', $GLOBALS['manageLog']);
            self::assertSame($row, $GLOBALS['manageRow']);
        }
    }

    private function sandbox(): void
    {
        if (class_exists(self::NS . '\\Rendered', false)) { return; }
        $stubs = <<<'PHP'
namespace Tests\Unit\Include\Manage2faSandbox;
class Rendered extends \RuntimeException { public function __construct(public array $vars) { parent::__construct('rendered'); } }
class XoopsUser {
    public function __construct(private int $uid) {}
    public function getVar(string $name, string $format = 's'): mixed { return ['uid' => $this->uid, 'uname' => 'user', 'pass' => 'hash'][$name] ?? null; }
    public function isAdmin(int $mid): bool { return true; }
}
class XoopsTpl {
    public int $caching = 0;
    private array $vars = [];
    public function assign(array $vars): void { $this->vars = $vars; }
    public function display(string $template): never { throw new Rendered($this->vars); }
}
class XoopsTwoFactorCrypto {
    public function __construct(...$args) {}
    public static function pendingAad(int $uid): string { return 'pending:' . $uid; }
    public function provisionKey(callable $check): bool { $GLOBALS['manageLog'][] = 'provision'; $check(); return $GLOBALS['manageCrypto']; }
    public function seal(string $secret, string $aad): ?string { return 'encrypted:' . base64_encode($secret); }
    public function open(string $blob, string $aad): ?string { return base64_decode(substr($blob, 10)); }
}
class XoopsUser2faHandler {
    public const ROW_DISABLED = 'disabled', ROW_ENROLLED = 'enrolled', POLICY_OFF = 'off';
    public static function policy(array $config): string { return $config['twofactor_mode']; }
    public function isInstalled(): bool { return $GLOBALS['manageInstalled']; }
    public function getRow(int $uid): ?array { return $GLOBALS['manageRow']; }
    public function hasEncryptedSecrets(): bool { return false; }
    public function enrol(int $uid, string $secret, int $step, int $now, string $generation): array {
        $GLOBALS['manageLog'][] = 'enrol'; $GLOBALS['manageRow'] = ['state' => 'enrolled', 'generation' => 'newgen'];
        return ['generation' => 'newgen', 'codes' => array_fill(0, 10, 'ABCDEFGHIJKLMNOP')];
    }
    public function manage(int $uid, string $generation, string $code, string $recovery, string $action, int $now): array|false {
        $GLOBALS['manageLog'][] = "manage:$uid:$generation:$action"; return $GLOBALS['manageAccept'] ? array_fill(0, 10, 'ABCDEFGHIJKLMNOP') : false;
    }
    public function recordFailure(int $uid, int $now, string $generation): array { $GLOBALS['manageLog'][] = "failure:$uid:$generation"; return ['locked' => false, 'transitioned' => false]; }
    public function disable(int $uid): string { $GLOBALS['manageLog'][] = "disable:$uid"; $GLOBALS['manageRow'] = ['state' => 'disabled', 'generation' => 'disabledgen']; return 'disabledgen'; }
}
function xoops_getHandler(string $name): object { return $name === 'user2fa' ? new XoopsUser2faHandler() : new class { public function getUser(int $uid): XoopsUser { return new XoopsUser($uid); } }; }
function xoops_2fa_reauthenticate(XoopsUser $user, string $password): XoopsUser|false { $GLOBALS['manageLog'][] = 'reauth:' . $user->getVar('uid'); return $GLOBALS['manageAuth'] ? $user : false; }
function xoops_login_set_session(XoopsUser $user, string $generation, bool $verified): void {
    $_SESSION = [];
    if ($GLOBALS['manageSessionFails']) { throw new \RuntimeException('Session rotation failed'); }
    $_SESSION = ['xoopsUserId' => $user->getVar('uid'), 'xoops2faGeneration' => $generation, 'xoops2faVerified' => $verified];
}
function xoops_2fa_notice(...$args): void { $GLOBALS['manageLog'][] = 'notice'; }
function xoops_setcookie(...$args): void { $GLOBALS['manageLog'][] = 'cookie'; }
function header(string $value): void { $GLOBALS['manageLog'][] = 'header:' . $value; }
function xoops_loadLanguage(string $name): void {}
function class_exists(string $name, bool $autoload = true): bool { return $name === \chillerlan\QRCode\QRCode::class ? false : \class_exists($name, $autoload); }
PHP;
        eval($stubs);
        class_alias(\XoopsTotp::class, self::NS . '\\XoopsTotp');
        $source = file_get_contents(XOOPS_ROOT_PATH . '/include/manage2fa.php');
        // Rendering is the test boundary; loading the real template also installs global handlers.
        $source = str_replace("require_once XOOPS_ROOT_PATH . '/class/template.php';", '', $source);
        // Avoid FileStorage's default prefix lookup opening a real database connection.
        $source = str_replace("new \\Xmf\\Key\\FileStorage(XOOPS_VAR_PATH . '/data')", "new \\Xmf\\Key\\FileStorage(XOOPS_VAR_PATH . '/data', 'controller-test')", $source);
        self::$body = 'namespace ' . self::NS . ';' . substr($source, 5);
    }
}
