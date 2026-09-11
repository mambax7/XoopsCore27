<?php

declare(strict_types=1);

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;
use RedirectHeaderException;
use Tests\Unit\Include\CheckLogin2faSandbox\EstablishedException;
use Tests\Unit\Include\CheckLogin2faSandbox\RenderedException;

/**
 * Executes include/checklogin2fa.php in an isolated namespace where the
 * handler, the security token, the mailer and the renderer are spies.
 * Rendering throws RenderedException carrying the template variables;
 * completing the login throws EstablishedException carrying the arguments.
 * The include's only function declaration is guarded, so the body can be
 * evaluated again for every test in one process.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class CheckLogin2faTest extends TestCase
{
    private const NS = 'Tests\\Unit\\Include\\CheckLogin2faSandbox';

    /** base32 of "12345678901234567890", the RFC 6238 test secret */
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private static string $body = '';

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aFreshChallengeRequestLoadsTheRealCompletionHelper(): void
    {
        self::assertFalse(function_exists('xoops_login_establish_session'));
        $this->execute();
        self::assertTrue(function_exists('xoops_login_establish_session'));
        self::assertSame(realpath(XOOPS_ROOT_PATH . '/include/loginsession.php'),
            realpath((new \ReflectionFunction('xoops_login_establish_session'))->getFileName()));
    }

    protected function setUp(): void
    {
        foreach (['_US_2FA_TITLE', '_US_2FA_PROMPT', '_US_2FA_CODE', '_US_2FA_RECOVERY', '_US_2FA_RECOVERY_HINT', '_US_2FA_SUBMIT',
                  '_US_2FA_STARTAGAIN', '_US_2FA_BACKTOLOGIN', '_US_2FA_BADCODE', '_US_2FA_LOCKED', '_US_2FA_UNAVAILABLE',
                  '_US_2FA_LOCKED_MAIL_SUBJECT', '_US_2FA_LOCKED_MAIL_BODY', '_US_2FA_RECOVERY_MAIL_SUBJECT', '_US_2FA_RECOVERY_MAIL_BODY'] as $c) {
            defined($c) || define($c, $c . (str_ends_with($c, 'SUBJECT') ? ' %s' : ' %s %s'));
        }
        $GLOBALS['xoopsConfig']    = ['closesite' => 0, 'closesite_okgrp' => [], 'sitename' => 'Site', 'adminmail' => 'a@b.c'];
        $GLOBALS['sandboxLog']     = [];
        $GLOBALS['sandboxRow']     = ['uid' => 9, 'state' => 'enrolled', 'method' => 'totp', 'secret' => 'x', 'generation' => 'gen-1', 'last_counter' => 0, 'locked_until' => 0];
        $GLOBALS['sandboxState']   = 'enrolled';
        $GLOBALS['sandboxSecret']  = self::SECRET;
        $GLOBALS['sandboxAccept']  = true;
        $GLOBALS['sandboxFailure'] = ['locked' => false, 'transitioned' => false];
        $GLOBALS['sandboxRecovery'] = false;
        $GLOBALS['sandboxHatch']   = false;
        $GLOBALS['sandboxToken']   = true;
        $GLOBALS['sandboxGroups']  = [2];
        $GLOBALS['sandboxUser']    = new class {
            public function getVar(string $k, string $f = 's'): mixed
            {
                return ['uid' => 9, 'level' => 1, 'pass' => 'hash9', 'email' => 'u@x.y', 'uname' => 'u'][$k] ?? null;
            }

            public function getGroups(): array
            {
                return $GLOBALS['sandboxGroups'];
            }
        };
        $GLOBALS['xoopsSecurity'] = new class {
            public function check(): bool
            {
                $GLOBALS['sandboxLog'][] = 'token';

                return $GLOBALS['sandboxToken'];
            }

            public function getTokenHTML(): string
            {
                return '<input type="hidden" name="XOOPS_TOKEN">';
            }

            public function getErrors(): array
            {
                return ['bad token'];
            }
        };
        $_POST                     = [];
        $_SESSION                  = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR']    = '203.0.113.5';
        $this->loadSandbox();
    }

    private function pending(array $overrides = []): void
    {
        $_SESSION['xoops2faPending'] = $overrides + [
            'uid'        => 9,
            'state'      => 'enrolled',
            'generation' => 'gen-1',
            'passdigest' => hash('sha256', 'hash9'),
            'expires'    => time() + 200,
            'remember'   => false,
            'redirect'   => '',
        ];
    }

    private function post(array $fields): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST                     = $fields + ['xoops_2fa' => '1'];
    }

    private function code(int $offset = 0): string
    {
        return (string) \XoopsTotp::codeAt(self::SECRET, \XoopsTotp::stepAt(time()) + $offset);
    }

    /** @return array{0: string, 1: mixed} ['rendered', vars] | ['established', args] | ['redirect', exception] */
    private function execute(): array
    {
        try {
            eval(self::$body);
        } catch (RenderedException $e) {
            return ['rendered', $e->vars];
        } catch (EstablishedException $e) {
            return ['established', $e->args];
        } catch (RedirectHeaderException $e) {
            return ['redirect', $e];
        }
        self::fail('the include must never return');
    }

    #[Test]
    public function withoutAPendingRecordThePageSaysStartAgain(): void
    {
        [$what, $vars] = $this->execute();
        self::assertSame('rendered', $what);
        self::assertTrue($vars['start_again']);
        self::assertSame(_US_2FA_STARTAGAIN, $vars['message']);
        self::assertContains('header:Cache-Control: no-store', $GLOBALS['sandboxLog']);
        self::assertContains('header:X-Frame-Options: DENY', $GLOBALS['sandboxLog']);
        self::assertContains('header:Referrer-Policy: no-referrer', $GLOBALS['sandboxLog']);
    }

    #[Test]
    public function anExpiredOrMalformedRecordIsDroppedAndSaysStartAgain(): void
    {
        foreach ([['expires' => time() - 1], ['uid' => '9'], ['generation' => null], ['remember' => []], ['redirect' => []]] as $bad) {
            $this->pending($bad);
            [$what, $vars] = $this->execute();
            self::assertSame('rendered', $what);
            self::assertTrue($vars['start_again'], json_encode($bad));
            self::assertArrayNotHasKey('xoops2faPending', $_SESSION);
        }
    }

    #[Test]
    public function aChangedPasswordGenerationOrRowStateInvalidatesTheChallenge(): void
    {
        foreach ([['passdigest' => 'other'], ['generation' => 'gen-0']] as $bad) {
            $this->pending($bad);
            [$what, $vars] = $this->execute();
            self::assertSame('rendered', $what);
            self::assertTrue($vars['start_again'], json_encode($bad));
            self::assertArrayNotHasKey('xoops2faPending', $_SESSION);
        }
        $this->pending();
        $GLOBALS['sandboxRow']['state'] = 'disabled';
        [$what, $vars] = $this->execute();
        self::assertTrue($vars['start_again']);
        self::assertArrayNotHasKey('xoops2faPending', $_SESSION);
    }

    #[Test]
    public function aLookupFailureRendersUnavailableAndKeepsThePendingRecord(): void
    {
        $this->pending();
        $GLOBALS['sandboxRow'] = new \RuntimeException('down');
        [$what, $vars] = $this->execute();
        self::assertSame('rendered', $what);
        self::assertFalse($vars['start_again']);
        self::assertSame(_US_2FA_UNAVAILABLE, $vars['error']);
        self::assertArrayHasKey('xoops2faPending', $_SESSION);
    }

    #[Test]
    public function aGetRendersTheFormWithTheTokenAndTouchesNothing(): void
    {
        $this->pending();
        [$what, $vars] = $this->execute();
        self::assertSame('rendered', $what);
        self::assertFalse($vars['start_again']);
        self::assertSame('', $vars['error']);
        self::assertSame(_US_2FA_PROMPT, $vars['message']);
        self::assertStringContainsString('XOOPS_TOKEN', $vars['token_html']);
        self::assertSame([], array_filter($GLOBALS['sandboxLog'], static fn (string $l): bool => !str_starts_with($l, 'header:')));
        self::assertArrayHasKey('xoops2faPending', $_SESSION);
    }

    #[Test]
    public function aBadTokenIsRefusedBeforeAnyCodeIsLookedAt(): void
    {
        $this->pending();
        $GLOBALS['sandboxToken'] = false;
        $this->post(['code' => $this->code()]);
        [$what, $vars] = $this->execute();
        self::assertSame('rendered', $what);
        self::assertSame('bad token', $vars['error']);
        self::assertSame(['token'], array_values(array_filter($GLOBALS['sandboxLog'], static fn (string $l): bool => !str_starts_with($l, 'header:'))));
        self::assertArrayHasKey('xoops2faPending', $_SESSION);
    }

    #[Test]
    public function aValidCodeCompletesTheLoginWithTheVerifiedGeneration(): void
    {
        $this->pending(['remember' => true, 'redirect' => '/x']);
        $step = \XoopsTotp::stepAt(time());
        $this->post(['code' => (string) \XoopsTotp::codeAt(self::SECRET, $step)]);
        [$what, $args] = $this->execute();
        self::assertSame('established', $what);
        self::assertSame([9, true, '/x', 'gen-1'], $args);
        self::assertArrayNotHasKey('xoops2faPending', $_SESSION);
        self::assertContains('acceptTotp:9:' . $step . ':gen-1', $GLOBALS['sandboxLog']);
        self::assertNotContains('mail', $GLOBALS['sandboxLog']);
    }

    #[Test]
    public function aRefusedAcceptanceIsAFailureNotASuccess(): void
    {
        $this->pending();
        $GLOBALS['sandboxAccept'] = false;   // the generation changed under us, or a replayed step
        $this->post(['code' => $this->code()]);
        [$what, $vars] = $this->execute();
        self::assertSame('rendered', $what);
        self::assertSame(_US_2FA_BADCODE, $vars['error']);
        self::assertContains('recordFailure:9', $GLOBALS['sandboxLog']);
        self::assertArrayHasKey('xoops2faPending', $_SESSION);
    }

    #[Test]
    public function aWrongCodeIsCountedAndTheLockingOneDropsThePendingRecordAndMails(): void
    {
        $this->pending();
        $this->post(['code' => '000000']);
        [$what, $vars] = $this->execute();
        self::assertSame('rendered', $what);
        self::assertSame(_US_2FA_BADCODE, $vars['error']);
        self::assertArrayHasKey('xoops2faPending', $_SESSION);
        self::assertContains('recordFailure:9', $GLOBALS['sandboxLog']);
        self::assertNotContains('mail', $GLOBALS['sandboxLog']);

        $GLOBALS['sandboxLog']     = [];
        $GLOBALS['sandboxFailure'] = ['locked' => true, 'transitioned' => true];
        [$what, $vars] = $this->execute();
        self::assertSame('rendered', $what);
        self::assertTrue($vars['start_again']);
        self::assertSame(_US_2FA_LOCKED, $vars['message']);
        self::assertArrayNotHasKey('xoops2faPending', $_SESSION);
        self::assertContains('mail', $GLOBALS['sandboxLog']);

        // a wrong code during an existing lock is counted but not re-announced
        $this->pending();
        $GLOBALS['sandboxLog']     = [];
        $GLOBALS['sandboxFailure'] = ['locked' => true, 'transitioned' => false];
        [$what, $vars] = $this->execute();
        self::assertTrue($vars['start_again']);
        self::assertNotContains('mail', $GLOBALS['sandboxLog']);
    }

    #[Test]
    public function aLockedRowRefusesWithoutEvaluatingTheCode(): void
    {
        $this->pending();
        $GLOBALS['sandboxRow']['locked_until'] = time() + 100;
        $this->post(['code' => $this->code()]);
        [$what, $vars] = $this->execute();
        self::assertSame('rendered', $what);
        self::assertSame(_US_2FA_LOCKED, $vars['error']);
        // the token and the escape-hatch probe run for every POST; nothing else did
        self::assertSame(['token', 'hatch:9'], array_values(array_filter($GLOBALS['sandboxLog'], static fn (string $l): bool => !str_starts_with($l, 'header:'))));
    }

    #[Test]
    public function aCodeFromANearbyStepIsRefusedAndLoggedAsSkew(): void
    {
        $this->pending();
        $step = \XoopsTotp::stepAt(time());
        $this->post(['code' => (string) \XoopsTotp::codeAt(self::SECRET, $step + 3)]);
        $notices = [];
        set_error_handler(static function (int $no, string $msg) use (&$notices): bool {
            $notices[] = $msg;

            return true;
        }, E_USER_NOTICE);
        try {
            [$what, $vars] = $this->execute();
        } finally {
            restore_error_handler();
        }
        if (\XoopsTotp::stepAt(time()) !== $step) {
            self::markTestSkipped('the 30-second step boundary passed during the test');
        }
        self::assertSame(_US_2FA_BADCODE, $vars['error']);
        self::assertContains('recordFailure:9', $GLOBALS['sandboxLog']);
        self::assertCount(1, $notices);
        self::assertStringContainsString('3 steps from now', $notices[0]);
        self::assertStringNotContainsString((string) \XoopsTotp::codeAt(self::SECRET, $step + 3), $notices[0]);
    }

    #[Test]
    public function anUnavailableFactorRefusesTotpButARecoveryCodeStillWorks(): void
    {
        $this->pending(['state' => 'unavailable']);
        $GLOBALS['sandboxState'] = 'unavailable';
        $this->post(['code' => '123456']);
        [$what, $vars] = $this->execute();
        self::assertSame('rendered', $what);
        self::assertSame(_US_2FA_UNAVAILABLE, $vars['error']);
        self::assertNotContains('recordFailure:9', $GLOBALS['sandboxLog']);
        self::assertArrayHasKey('xoops2faPending', $_SESSION);

        $GLOBALS['sandboxRecovery'] = true;
        $this->post(['recovery' => 'abcd efgh']);
        [$what, $args] = $this->execute();
        self::assertSame('established', $what);
        self::assertSame('gen-1', $args[3]);
        self::assertContains('acceptRecovery:9:abcd efgh:gen-1', $GLOBALS['sandboxLog']);
        self::assertContains('mail', $GLOBALS['sandboxLog']);
        self::assertArrayNotHasKey('xoops2faPending', $_SESSION);
    }

    #[Test]
    public function aFactorStoreFailureDuringVerificationIsUnavailableAndNotCounted(): void
    {
        $this->pending();
        $GLOBALS['sandboxAccept'] = new \RuntimeException('down');
        $step = \XoopsTotp::stepAt(time());
        $this->post(['code' => (string) \XoopsTotp::codeAt(self::SECRET, $step)]);
        [$what, $vars] = @$this->execute();
        self::assertSame('rendered', $what);
        self::assertSame(_US_2FA_UNAVAILABLE, $vars['error']);
        self::assertContains('acceptTotp:9:' . $step . ':gen-1', $GLOBALS['sandboxLog']);
        self::assertNotContains('recordFailure:9', $GLOBALS['sandboxLog']);
        self::assertArrayHasKey('xoops2faPending', $_SESSION);
    }

    #[Test]
    public function anUnreadableSecretIsUnavailableAndNotCounted(): void
    {
        $this->pending();
        $GLOBALS['sandboxSecret'] = null;
        $this->post(['code' => '123456']);
        [$what, $vars] = $this->execute();
        self::assertSame('rendered', $what);
        self::assertSame(_US_2FA_UNAVAILABLE, $vars['error']);
        self::assertNotContains('recordFailure:9', $GLOBALS['sandboxLog']);
        self::assertArrayHasKey('xoops2faPending', $_SESSION);
    }

    #[Test]
    public function aRecoveryCodeIsAcceptedDuringALockAndAWrongOneIsCounted(): void
    {
        $this->pending();
        $GLOBALS['sandboxRow']['locked_until'] = time() + 100;
        $GLOBALS['sandboxRecovery'] = true;
        $this->post(['recovery' => 'abcd']);
        [$what] = $this->execute();
        self::assertSame('established', $what);

        $this->pending();
        $GLOBALS['sandboxRecovery'] = false;
        $this->post(['recovery' => 'nope']);
        [$what, $vars] = $this->execute();
        self::assertSame(_US_2FA_BADCODE, $vars['error']);
        self::assertContains('recordFailure:9', $GLOBALS['sandboxLog']);
    }

    #[Test]
    public function theEscapeHatchCompletesTheLoginWithoutAFactor(): void
    {
        $this->pending();
        $GLOBALS['sandboxHatch'] = true;
        $this->post([]);
        [$what, $args] = $this->execute();
        self::assertSame('established', $what);
        self::assertNull($args[3]);
        self::assertContains('hatch:9', $GLOBALS['sandboxLog']);
        self::assertArrayNotHasKey('xoops2faPending', $_SESSION);
    }

    #[Test]
    public function closedSiteNormalizesDatabaseGroupIdsBeforeStrictComparison(): void
    {
        foreach ([
            [['2'], [2], true],
            [[2], ['2'], true],
            [['1'], [], true],
            [[2], [true], false],
            [['3'], [2], false],
        ] as [$groups, $allowed, $expected]) {
            $this->pending();
            $GLOBALS['sandboxGroups'] = $groups;
            $GLOBALS['xoopsConfig']['closesite'] = 1;
            $GLOBALS['xoopsConfig']['closesite_okgrp'] = $allowed;
            [$what, $vars] = $this->execute();
            self::assertSame('rendered', $what);
            self::assertSame(!$expected, $vars['start_again']);
        }
    }

    #[Test]
    public function theRecheckRefusesAClosedSiteToAGroupWithoutAccess(): void
    {
        $this->pending();
        $GLOBALS['xoopsConfig']['closesite'] = 1;
        [$what, $vars] = $this->execute();
        self::assertTrue($vars['start_again']);
        self::assertArrayNotHasKey('xoops2faPending', $_SESSION);

        $this->pending();
        $GLOBALS['xoopsConfig']['closesite_okgrp'] = [2];
        [$what, $vars] = $this->execute();
        self::assertFalse($vars['start_again']);
    }

    private function loadSandbox(): void
    {
        if (class_exists(self::NS . '\\RenderedException', false)) {
            return;
        }
        require_once dirname(__DIR__, 4) . '/htdocs/class/XoopsTotp.php';
        require_once dirname(__DIR__, 4) . '/htdocs/kernel/user2fa.php';
        class_alias(\XoopsTotp::class, self::NS . '\\XoopsTotp');
        class_alias(\XoopsUser2faHandler::class, self::NS . '\\XoopsUser2faHandler');
        $stubs = <<<'PHP'
        namespace Tests\Unit\Include\CheckLogin2faSandbox;

        class RenderedException extends \RuntimeException { public function __construct(public array $vars) { parent::__construct('rendered'); } }
        class EstablishedException extends \RuntimeException { public function __construct(public array $args) { parent::__construct('established'); } }
        function xoops_2fa_render(array $vars): never { throw new RenderedException($vars); }
        function xoops_login_establish_session(object $user, bool $remember, string $redirect, ?string $verified = null): never {
            throw new EstablishedException([$user->getVar('uid'), $remember, $redirect, $verified]);
        }
        function xoops_getHandler(string $name): object {
            return match ($name) {
                'member' => new class { public function getUser(int $uid): mixed { return $GLOBALS['sandboxUser']; } },
                'user2fa' => new class {
                    public function getRow(int $uid): ?array { if ($GLOBALS['sandboxRow'] instanceof \Throwable) { throw $GLOBALS['sandboxRow']; } return $GLOBALS['sandboxRow']; }
                    public function stateOfRow(?array $row): string { return $GLOBALS['sandboxState']; }
                    public function secretFor(int $uid): ?string { return $GLOBALS['sandboxSecret']; }
                    public function acceptTotp(int $uid, int $step, string $gen, int $now): bool { $GLOBALS['sandboxLog'][] = "acceptTotp:$uid:$step:$gen"; if ($GLOBALS['sandboxAccept'] instanceof \Throwable) { throw $GLOBALS['sandboxAccept']; } return $GLOBALS['sandboxAccept']; }
                    public function recordFailure(int $uid, int $now, string $generation): array|false { if ($generation !== 'gen-1') { throw new \LogicException('Wrong failure generation'); } $GLOBALS['sandboxLog'][] = "recordFailure:$uid"; return $GLOBALS['sandboxFailure']; }
                    public function acceptRecovery(int $uid, string $code, string $gen): bool { $GLOBALS['sandboxLog'][] = "acceptRecovery:$uid:$code:$gen"; return $GLOBALS['sandboxRecovery']; }
                    public function resetByEscapeHatch(int $uid): bool { $GLOBALS['sandboxLog'][] = "hatch:$uid"; return $GLOBALS['sandboxHatch']; }
                },
            };
        }
        function xoops_getMailer(): object {
            return new class {
                public function __call(string $m, array $a): mixed { if ('send' === $m) { $GLOBALS['sandboxLog'][] = 'mail'; return true; } return $this; }
            };
        }
        function header(string $h): void { $GLOBALS['sandboxLog'][] = 'header:' . $h; }
        PHP;
        eval($stubs);

        $source = file_get_contents(dirname(__DIR__, 4) . '/htdocs/include/checklogin2fa.php');
        self::assertNotFalse($source);
        self::assertStringStartsWith('<?php', $source);
        self::$body = 'namespace ' . self::NS . ";\n" . substr($source, strlen('<?php'));
    }
}
