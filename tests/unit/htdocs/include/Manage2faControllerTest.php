<?php
/**
 * Tests for the two-factor management controller in include/manage2fa.php
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.4
 */

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
        $GLOBALS['manageRefuse'] = false;
        $GLOBALS['manageDeliver'] = true;
        $GLOBALS['manageSessionFails'] = false;
        $GLOBALS['xoopsConfig'] = ['sitename' => 'Test', 'usercookie' => 'remember', 'twofactor_mode' => 'optional'];
        $userClass = self::NS . '\\XoopsUser';
        $GLOBALS['xoopsUser'] = new $userClass(9);
        $GLOBALS['xoopsModule'] = new class {
            public function mid(): int
            {
                return 1;
            }
        };
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
        $secret = $this->execute(['action' => 'begin', 'password' => 'correct'])['secret'];
        for ($i = 0; $i < 5; ++$i) {
            $vars = $this->execute(['action' => 'confirm', 'code' => 'invalid']);
            self::assertSame(_US_2FA_BADCODE, $vars['error']);
            // the manual key stays on the retry, and disappears with the pending secret
            self::assertSame($i < 4 ? $secret : '', $vars['secret']);
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
    public function aRefusedResetOrEnrolmentIsReportedAsUnavailableWithoutAnAuditTrail(): void
    {
        $GLOBALS['manageRefuse'] = true;
        foreach ([['action' => 'reset', 'uid' => 12, 'admin' => true, 'row' => ['state' => 'enrolled', 'generation' => 'gen'], 'log' => 'disable:12'],
                  ['action' => 'begin', 'uid' => 9, 'admin' => false, 'row' => null, 'log' => 'enrol']] as $case) {
            $GLOBALS['manageRow'] = $case['row'];
            $GLOBALS['manageLog'] = [];
            $notices = [];
            set_error_handler(static function (int $level, string $message) use (&$notices): bool {
                $notices[] = $message;
                return true;
            });
            try {
                $post = ['action' => $case['action'], 'password' => 'correct', 'uid' => $case['uid']];
                if ('begin' === $case['action']) {
                    $vars = $this->execute($post);
                    $post['code'] = (string) \XoopsTotp::codeAt($vars['secret'], \XoopsTotp::stepAt(time()));
                    $post['action'] = 'confirm';
                }
                $vars = $this->execute($post, $case['admin']);
            } finally {
                restore_error_handler();
            }
            self::assertSame(_US_2FAM_UNAVAILABLE, $vars['error'], $case['action']);
            self::assertSame('', $vars['message'], $case['action']);
            self::assertContains($case['log'], $GLOBALS['manageLog'], $case['action']);
            self::assertNotContains('notice', $GLOBALS['manageLog'], $case['action']);
            self::assertSame([], $notices, $case['action']);
            self::assertSame($case['row'], $GLOBALS['manageRow'], $case['action']);
        }
    }

    #[Test]
    public function emailEnrolmentMailsACodeAfterThePasswordAndConfirmsWithIt(): void
    {
        $vars = $this->execute(['action' => 'begin_email', 'password' => 'correct']);
        self::assertSame('', $vars['error']);
        self::assertSame('sent-msg', $vars['message']);
        self::assertTrue($vars['by_email']);
        self::assertTrue($vars['confirm_setup']);
        self::assertSame('', $vars['secret'], 'no authenticator secret is minted for the e-mail path');
        self::assertContains('reauth:9', $GLOBALS['manageLog']);
        self::assertContains('deliver:9', $GLOBALS['manageLog']);
        self::assertSame('email', $_SESSION['xoops2faSetup']['method']);

        $vars = $this->execute(['action' => 'confirm', 'code' => '111111']);
        self::assertSame(_US_2FA_BADCODE, $vars['error']);
        self::assertSame(1, $_SESSION['xoops2faSetup']['attempts']);
        self::assertNull($GLOBALS['manageRow']);

        $vars = $this->execute(['action' => 'confirm', 'code' => '654321']);
        self::assertSame('', $vars['error']);
        self::assertSame(_US_2FAM_DONE, $vars['message']);
        self::assertCount(10, $vars['codes']);
        self::assertContains('enrolEmail:9:654321:', $GLOBALS['manageLog']);
        self::assertContains('notice', $GLOBALS['manageLog']);
        self::assertSame('email', $GLOBALS['manageRow']['method']);
        self::assertArrayNotHasKey('xoops2faSetup', $_SESSION);
        self::assertSame('mailgen', $_SESSION['xoops2faGeneration']);
        self::assertTrue($_SESSION['xoops2faVerified']);
    }

    #[Test]
    public function aMailedCodeCanBeRequestedForAPendingOrEnrolledEmailFactorOnly(): void
    {
        // Nothing pending and nothing enrolled: the button does nothing.
        $vars = $this->execute(['action' => 'send']);
        self::assertNotContains('deliver:9', $GLOBALS['manageLog']);
        self::assertSame('', $vars['message']);

        // Enrolled by e-mail: no password, one delivery.
        $GLOBALS['manageRow'] = ['state' => 'enrolled', 'method' => 'email', 'generation' => 'gen'];
        $vars = $this->execute(['action' => 'send']);
        self::assertSame('sent-msg', $vars['message']);
        self::assertTrue($vars['by_email']);
        self::assertSame('FORM', $vars['form']);
        self::assertSame('SEND', $vars['send_form']);
        self::assertSame(_US_2FA_CODE_EMAIL, $vars['lang_code']);
        self::assertContains('deliver:9', $GLOBALS['manageLog']);
        self::assertNotContains('reauth:9', $GLOBALS['manageLog']);

        // The cooldown or a mailer failure is reported as the error, not as unavailable.
        $GLOBALS['manageDeliver'] = false;
        $vars = $this->execute(['action' => 'send']);
        self::assertSame('send-fail', $vars['error']);
        self::assertSame('', $vars['message']);

        // Enrolled with an authenticator: the e-mail button is not offered and not honoured.
        $GLOBALS['manageDeliver'] = true;
        $GLOBALS['manageRow'] = ['state' => 'enrolled', 'method' => 'totp', 'generation' => 'gen'];
        $GLOBALS['manageLog'] = [];
        $vars = $this->execute(['action' => 'send']);
        self::assertFalse($vars['by_email']);
        self::assertSame('', $vars['send_form']);
        self::assertNotContains('deliver:9', $GLOBALS['manageLog']);

        // The button posts under its own name; the plain field is still honoured.
        $GLOBALS['manageRow'] = ['state' => 'enrolled', 'method' => 'email', 'generation' => 'gen'];
        $GLOBALS['manageLog'] = [];
        $vars = $this->execute(['action_send' => 'Send me a code']);
        self::assertContains('deliver:9', $GLOBALS['manageLog']);
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
    public function getVar(string $name, string $format = 's'): mixed { return ['uid' => $this->uid, 'uname' => 'user', 'pass' => 'hash', 'email' => 'user@example.test'][$name] ?? null; }
    public function isAdmin(int $mid): bool { return true; }
}
class XoopsTpl {
    public int $caching = 0;
    private array $vars = [];
    public function assign(array|string $vars, mixed $value = null): void { $this->vars = (is_array($vars) ? $vars : [$vars => $value]) + $this->vars; }
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
    public const ROW_DISABLED = 'disabled', ROW_ENROLLED = 'enrolled', POLICY_OFF = 'off', METHOD_TOTP = 'totp', METHOD_EMAIL = 'email', EMAIL_TTL = 600;
    public static function policy(array $config): string { return $config['twofactor_mode']; }
    public function isInstalled(): bool { return $GLOBALS['manageInstalled']; }
    public function getRow(int $uid): ?array { return $GLOBALS['manageRow']; }
    public function hasEncryptedSecrets(): bool { return false; }
    public function enrol(int $uid, string $secret, int $step, int $now, ?string $generation = null): array|false {
        $GLOBALS['manageLog'][] = 'enrol';
        if ($GLOBALS['manageRefuse']) { return false; }
        $GLOBALS['manageRow'] = ['state' => 'enrolled', 'generation' => 'newgen'];
        return ['generation' => 'newgen', 'codes' => array_fill(0, 10, 'ABCDEFGHIJKLMNOP')];
    }
    public function manage(int $uid, string $generation, string $code, string $recovery, string $action, int $now): array|string|false {
        $GLOBALS['manageLog'][] = "manage:$uid:$generation:$action"; return $GLOBALS['manageAccept'] ? array_fill(0, 10, 'ABCDEFGHIJKLMNOP') : false;
    }
    public function recordFailure(int $uid, int $now, ?string $generation = null): array|false {
        $GLOBALS['manageLog'][] = "failure:$uid:$generation";
        return $GLOBALS['manageRefuse'] ? false : ['locked' => false, 'transitioned' => false];
    }
    public function disable(int $uid): string|false {
        $GLOBALS['manageLog'][] = "disable:$uid";
        if ($GLOBALS['manageRefuse']) { return false; }
        $GLOBALS['manageRow'] = ['state' => 'disabled', 'generation' => 'disabledgen'];
        return 'disabledgen';
    }
    public function enrolEmail(int $uid, string $code, int $now, ?string $generation = null): array|false {
        $GLOBALS['manageLog'][] = "enrolEmail:$uid:$code:$generation";
        if ($GLOBALS['manageRefuse'] || '654321' !== $code) { return false; }
        $GLOBALS['manageRow'] = ['state' => 'enrolled', 'method' => 'email', 'generation' => 'mailgen'];
        return ['generation' => 'mailgen', 'codes' => array_fill(0, 10, 'ABCDEFGHIJKLMNOP')];
    }
}
function xoops_getHandler(string $name): object {
    return match ($name) {
        'user2fa' => new XoopsUser2faHandler(),
        'tplfile' => new class {
            public function find(...$args): array
            {
                return [new \stdClass()];
            }
        },
        default => new class {
            public function getUser(int $uid): XoopsUser
            {
                return new XoopsUser($uid);
            }
        },
    };
}
function xoops_2fa_reauthenticate(XoopsUser $user, string $password): XoopsUser|false { $GLOBALS['manageLog'][] = 'reauth:' . $user->getVar('uid'); return $GLOBALS['manageAuth'] ? $user : false; }
function xoops_login_set_session(XoopsUser $user, string $generation, bool $verified): void {
    $_SESSION = [];
    if ($GLOBALS['manageSessionFails']) { throw new \RuntimeException('Session rotation failed'); }
    $_SESSION = ['xoopsUserId' => $user->getVar('uid'), 'xoops2faGeneration' => $generation, 'xoops2faVerified' => $verified];
}
function xoops_2fa_notice(...$args): void { $GLOBALS['manageLog'][] = 'notice'; }
function xoops_2fa_manage_form(array $vars): object { return new class { public function render(): string { return 'FORM'; } }; }
function xoops_2fa_send_form(string $url, array $hidden, string $label): object { return new class { public function render(): string { return 'SEND'; } }; }
function xoops_2fa_posted_action(array $known): string { return \xoops_2fa_posted_action($known); }
function xoops_2fa_deliver_code(object $handler, object $user): array { $GLOBALS['manageLog'][] = 'deliver:' . $user->getVar('uid'); return $GLOBALS['manageDeliver'] ? ['sent' => true, 'message' => 'sent-msg'] : ['sent' => false, 'message' => 'send-fail']; }
function xoops_cp_header(): void { $GLOBALS['xoopsTpl'] = new XoopsTpl(); }
function xoops_cp_footer(): void {}
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
        // The theme is the rendering boundary: header.php would boot a site.
        $source = str_replace("include \$GLOBALS['xoops']->path('header.php');", "\$GLOBALS['xoopsTpl'] = new XoopsTpl();", $source);
        $source = str_replace("include \$GLOBALS['xoops']->path('footer.php');", '', $source);
        // Avoid FileStorage's default prefix lookup opening a real database connection.
        $source = str_replace("new \\Xmf\\Key\\FileStorage(XOOPS_VAR_PATH . '/data')", "new \\Xmf\\Key\\FileStorage(XOOPS_VAR_PATH . '/data', 'controller-test')", $source);
        self::assertStringStartsWith('<?php', $source);
        self::$body = 'namespace ' . self::NS . ';' . substr($source, 5);
    }
}
