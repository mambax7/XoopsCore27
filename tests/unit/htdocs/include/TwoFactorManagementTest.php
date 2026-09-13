<?php
/**
 * Tests for the two-factor management helpers in include/twofactor.php
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

use PHPUnit\Framework\TestCase;

/**
 * Helpers in include/twofactor.php: notice delivery, setup binding and language fallback.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class TwoFactorManagementTest extends TestCase
{
    public function testMailFailuresAreReportedWithoutEscapingIntoTheCommittedAction(): void
    {
        $source = file_get_contents(XOOPS_ROOT_PATH . '/include/twofactor.php');
        $start = strpos($source, 'function xoops_2fa_notice(');
        self::assertNotFalse($start);
        eval('namespace TwoFactorNoticeTest; class XoopsUser {} function xoops_getMailer() {'
            . 'if ($GLOBALS["noticeMode"] === "throw") { throw new \\RuntimeException("private transport credentials"); }'
            . 'return new class { public function __call($name, $args) {} public function send() { return $GLOBALS["noticeMode"] === "success"; } }; }'
            . substr($source, $start));
        $user = new \TwoFactorNoticeTest\XoopsUser();
        $config = $GLOBALS['xoopsConfig'] ?? [];
        $GLOBALS['xoopsConfig'] = ['adminmail' => 'nobody@example.invalid', 'sitename' => 'Test'];
        try {
            foreach (['throw', 'false', 'success'] as $mode) {
                $GLOBALS['noticeMode'] = $mode;
                $warnings = [];
                set_error_handler(static function ($level, $message) use (&$warnings): bool {
                    $warnings[] = [$level, $message];
                    return true;
                });
                try {
                    \TwoFactorNoticeTest\xoops_2fa_notice($user, 'Subject', 'Body');
                } finally {
                    restore_error_handler();
                }
                self::assertSame($mode === 'success' ? [] : [[E_USER_WARNING, 'Two-factor management notice could not be sent']], $warnings, $mode);
            }
            $GLOBALS['noticeMode'] = 'throw';
            set_error_handler(static function (): never { throw new \RuntimeException('Diagnostic handler failed'); });
            try {
                \TwoFactorNoticeTest\xoops_2fa_notice($user, 'Subject', 'Body');
            } finally {
                restore_error_handler();
            }
        } finally {
            $GLOBALS['xoopsConfig'] = $config;
            unset($GLOBALS['noticeMode']);
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testALanguageFileGapIsFilledFromEnglishWithoutOverwritingTheTranslation(): void
    {
        require_once XOOPS_ROOT_PATH . '/include/twofactor.php';
        $config = $GLOBALS['xoopsConfig'] ?? [];
        $GLOBALS['xoopsConfig'] = ['language' => 'klingon'];
        // A fresh process: the gap is real, and the translation defined this one constant and nothing else.
        self::assertFalse(defined('_US_2FAM_TITLE'));
        self::assertFalse(defined('_US_2FAM_RESET'));
        define('_US_2FAM_TITLE', 'translated');
        $translated = 'translated';
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            xoops_2fa_loadLanguage('user2famanage');
        } finally {
            restore_error_handler();
            $GLOBALS['xoopsConfig'] = $config;
        }
        self::assertSame([], $warnings);
        self::assertSame($translated, _US_2FAM_TITLE);
        preg_match_all("/define\('(_US_2FAM_[A-Z_]+)'/", (string) file_get_contents(XOOPS_ROOT_PATH . '/language/english/user2famanage.php'), $m);
        self::assertNotEmpty($m[1]);
        foreach ($m[1] as $constant) {
            self::assertTrue(defined($constant), $constant);
        }
    }

    /** The form token element asks the security object for a token; earlier tests may have replaced it. */
    private function withSecurity(callable $fn): void
    {
        $previous = $GLOBALS['xoopsSecurity'] ?? null;
        $GLOBALS['xoopsSecurity'] = new class {
            public function createToken(int $timeout = 0, string $name = 'XOOPS_TOKEN'): string { return 'token-value'; }
            public function getTokenHTML(string $name = 'XOOPS_TOKEN'): string { return ''; }
        };
        try {
            $fn();
        } finally {
            $GLOBALS['xoopsSecurity'] = $previous;
        }
    }

    /** @return array<string, mixed> */
    private function manageVars(array $over = []): array
    {
        $labels = [];
        foreach (['password', 'enable', 'enable_email', 'choose', 'email_help', 'confirm', 'confirm_email', 'manual', 'reset', 'reset_help',
                  'regenerate', 'disable', 'enabled', 'enabled_email', 'email_step', 'step_app', 'step_add', 'step_code', 'scan', 'code_help', 'code_help_email', 'send'] as $name) {
            $labels[$name] = 'Label ' . $name;
        }

        return $over + ['labels' => $labels, 'admin_reset' => false, 'enrolled' => false, 'confirm_setup' => false, 'by_email' => false,
            'secret' => '', 'qr' => '', 'uid' => 9, 'action_url' => 'http://localhost/user.php', 'lang_code' => 'Code', 'lang_recovery' => 'Recovery'];
    }

    public function testManagementFormOffersExactlyTheControlsOfEachState(): void
    {
        require_once XOOPS_ROOT_PATH . '/include/twofactor.php';
        $this->withSecurity(function (): void {
        $buttons = static fn (string $html): array => preg_match_all('/name=.action_([a-z_]+)/', $html, $m) ? $m[1] : [];

        $html = xoops_2fa_manage_form($this->manageVars())->render();
        self::assertStringContainsString('xoopsFormValidate_xo2fa_manage()', $html, 'the form name is a JavaScript identifier');
        self::assertSame(['begin', 'begin_email'], $buttons($html));
        self::assertMatchesRegularExpression('/name=.password./', $html);
        self::assertStringContainsString('required', $html);
        self::assertDoesNotMatchRegularExpression('/name=.code./', $html);
        self::assertStringContainsString('value="2fa_setup"', $html);
        self::assertStringContainsString('XOOPS_TOKEN_REQUEST', $html);
        self::assertStringContainsString('Label choose', $html);

        $html = xoops_2fa_manage_form($this->manageVars(['confirm_setup' => true, 'secret' => '<img src=x onerror=alert(1)>', 'qr' => 'data:image/png;base64,QQ==']))->render();
        self::assertSame(['confirm'], $buttons($html));
        self::assertDoesNotMatchRegularExpression('/name=.password./', $html);
        self::assertMatchesRegularExpression('/name=.code./', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('<img src="data:image/png;base64,QQ==" alt="Label scan"', $html);
        self::assertStringContainsString('<li>Label step_app</li>', $html);
        self::assertStringContainsString('Label code_help', $html);
        self::assertDoesNotMatchRegularExpression('/name=.recovery./', $html);

        $html = xoops_2fa_manage_form($this->manageVars(['confirm_setup' => true, 'by_email' => true]))->render();
        self::assertSame(['confirm'], $buttons($html));
        self::assertStringContainsString('Label confirm_email', $html);
        self::assertStringContainsString('Label email_step', $html);
        self::assertStringContainsString('Label code_help_email', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<ol>', $html);

        $html = xoops_2fa_manage_form($this->manageVars(['enrolled' => true]))->render();
        self::assertSame(['regenerate', 'disable'], $buttons($html));
        self::assertMatchesRegularExpression('/name=.password./', $html);
        self::assertMatchesRegularExpression('/name=.code./', $html);
        self::assertMatchesRegularExpression('/name=.recovery./', $html);
        self::assertStringContainsString('value="2fa_manage"', $html);
        self::assertStringContainsString('Label enabled', $html);
        self::assertStringNotContainsString('Label enabled_email', $html);

        $html = xoops_2fa_manage_form($this->manageVars(['enrolled' => true, 'by_email' => true]))->render();
        self::assertStringContainsString('Label enabled_email', $html);

        $html = xoops_2fa_manage_form($this->manageVars(['admin_reset' => true, 'enrolled' => true, 'uid' => '9<x']))->render();
        self::assertSame(['reset'], $buttons($html));
        self::assertMatchesRegularExpression('/name=.password./', $html);
        self::assertDoesNotMatchRegularExpression('/name=.code./', $html);
        self::assertStringContainsString('name="uid" id="uid" value="9"', $html);
        self::assertStringContainsString('value="users_2fa_reset"', $html);
        self::assertStringContainsString('Label reset_help', $html);

        $html = xoops_2fa_send_form('http://localhost/user.php', ['op' => '2fa_manage'], 'Send <me>')->render();
        self::assertSame(['send'], $buttons($html));
        self::assertStringContainsString('value="2fa_manage"', $html);
        self::assertStringContainsString('Send &lt;me&gt;', $html);
        self::assertDoesNotMatchRegularExpression('/name=.password./', $html);
        self::assertStringContainsString('XOOPS_TOKEN_REQUEST', $html);
        });
    }

    public function testChallengeFormCarriesTheLoginMarkersAndBothCodeFields(): void
    {
        require_once XOOPS_ROOT_PATH . '/include/twofactor.php';
        $this->withSecurity(function (): void {
        $html = xoops_2fa_challenge_form(['action_url' => 'http://localhost/user.php', 'lang_code' => 'Code', 'lang_recovery' => 'Recovery',
            'lang_recovery_hint' => 'Hint <i>', 'lang_submit' => 'Continue'])->render();
        self::assertMatchesRegularExpression('/name=.code./', $html);
        self::assertStringContainsString('autofocus', $html);
        self::assertMatchesRegularExpression('/name=.recovery./', $html);
        self::assertStringContainsString('Hint &lt;i&gt;', $html);
        self::assertStringContainsString('name="op" id="op" value="2fa"', $html);
        self::assertStringContainsString('name="xoops_2fa" id="xoops_2fa" value="1"', $html);
        self::assertStringContainsString('XOOPS_TOKEN_REQUEST', $html);
        self::assertStringContainsString("value='Continue'", $html);
        });
    }

    public function testThePostedActionComesFromTheButtonNameOrAPlainField(): void
    {
        require_once XOOPS_ROOT_PATH . '/include/twofactor.php';
        $_POST = ['action_disable' => 'Disable two-factor authentication'];
        self::assertSame('disable', xoops_2fa_posted_action(['begin', 'disable', 'regenerate']));
        $_POST = ['action' => 'begin'];
        self::assertSame('begin', xoops_2fa_posted_action(['begin', 'disable']));
        $_POST = ['action_reset' => 'x'];
        self::assertSame('', xoops_2fa_posted_action(['begin', 'disable']), 'an action the page does not accept is not returned');
        $_POST = ['action' => 'reset', 'action_begin' => 'x'];
        self::assertSame('', xoops_2fa_posted_action(['begin', 'disable']), 'the plain field is held to the same list and does not fall through');
        $_POST = [];
        self::assertSame('', xoops_2fa_posted_action(['begin']));
    }

    public function testMailedCodeDeliveryReportsCooldownFailureAndTheMaskedAddress(): void
    {
        foreach (['_US_2FA_SEND_WAIT' => 'wait', '_US_2FA_SEND_FAILED' => 'failed', '_US_2FA_SENT' => 'sent to %s',
                  '_US_2FA_EMAIL_SUBJECT' => '%s code', '_US_2FA_EMAIL_BODY' => '%s %s %d'] as $constant => $value) {
            defined($constant) || define($constant, $value);
        }
        $source = file_get_contents(XOOPS_ROOT_PATH . '/include/twofactor.php');
        $start  = strpos($source, 'function xoops_2fa_send_code(');
        $end    = strpos($source, '/** Validate the password-authorised setup session');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        eval('namespace TwoFactorDeliverTest;'
            . ' class XoopsUser { public function getVar(string $n, string $f = "s"): mixed { return ["uid" => 7, "email" => "someone@example.test"][$n] ?? ""; } }'
            . ' class XoopsUser2faHandler { public const EMAIL_TTL = 600; public function issueEmailCode(int $uid): string|null|false { return $GLOBALS["deliverCode"]; } }'
            . ' function xoops_getMailer(): object { if ("throw" === $GLOBALS["deliverMail"]) { throw new \\RuntimeException("transport"); }'
            . ' return new class { public function __call(string $m, array $a): mixed { $GLOBALS["deliverLog"][] = [$m, $a]; return "send" === $m ? $GLOBALS["deliverMail"] : $this; } }; }'
            . substr($source, $start, $end - $start));
        $handler = new \TwoFactorDeliverTest\XoopsUser2faHandler();
        $user    = new \TwoFactorDeliverTest\XoopsUser();
        $config  = $GLOBALS['xoopsConfig'] ?? [];
        $GLOBALS['xoopsConfig'] = ['adminmail' => 'nobody@example.invalid', 'sitename' => 'Site'];
        try {
            $GLOBALS['deliverLog'] = [];
            [$GLOBALS['deliverCode'], $GLOBALS['deliverMail']] = [null, true];
            self::assertSame(['sent' => false, 'message' => _US_2FA_SEND_WAIT], \TwoFactorDeliverTest\xoops_2fa_deliver_code($handler, $user));
            [$GLOBALS['deliverCode'], $GLOBALS['deliverMail']] = [false, true];
            self::assertSame(['sent' => false, 'message' => _US_2FA_SEND_FAILED], \TwoFactorDeliverTest\xoops_2fa_deliver_code($handler, $user));
            self::assertSame([], $GLOBALS['deliverLog'], 'nothing is mailed without a code');
            [$GLOBALS['deliverCode'], $GLOBALS['deliverMail']] = ['123456', false];
            self::assertSame(['sent' => false, 'message' => _US_2FA_SEND_FAILED], \TwoFactorDeliverTest\xoops_2fa_deliver_code($handler, $user));
            [$GLOBALS['deliverCode'], $GLOBALS['deliverMail']] = ['123456', 'throw'];
            self::assertSame(['sent' => false, 'message' => _US_2FA_SEND_FAILED], \TwoFactorDeliverTest\xoops_2fa_deliver_code($handler, $user));
            $GLOBALS['deliverLog'] = [];
            [$GLOBALS['deliverCode'], $GLOBALS['deliverMail']] = ['123456', true];
            self::assertSame(['sent' => true, 'message' => sprintf(_US_2FA_SENT, 's***@example.test')], \TwoFactorDeliverTest\xoops_2fa_deliver_code($handler, $user));
            $calls = array_column($GLOBALS['deliverLog'], 1, 0);
            self::assertSame([sprintf(_US_2FA_EMAIL_BODY, 'Site', '123456', 10)], $calls['setBody'], 'the body carries the code and its lifetime in minutes');
            self::assertSame([sprintf(_US_2FA_EMAIL_SUBJECT, 'Site')], $calls['setSubject']);
        } finally {
            $GLOBALS['xoopsConfig'] = $config;
            unset($GLOBALS['deliverCode'], $GLOBALS['deliverMail'], $GLOBALS['deliverLog']);
        }
        self::assertSame('***', \TwoFactorDeliverTest\xoops_2fa_mask_email('not-an-address'));
        self::assertSame('é***@x.y', \TwoFactorDeliverTest\xoops_2fa_mask_email('émile@x.y'));
    }

    public function testPendingSetupIsBoundToAccountPasswordGenerationAndExpiry(): void
    {
        require_once XOOPS_ROOT_PATH . '/include/twofactor.php';
        $pending = ['uid' => 7, 'passdigest' => hash('sha256', 'hash'), 'generation' => 'g', 'expires' => 200, 'blob' => 'sealed', 'attempts' => 0];
        self::assertTrue(xoops_2fa_setup_valid($pending, 7, 'hash', 'g', 100));
        foreach ([[8, 'hash', 'g', 100], [7, 'new', 'g', 100], [7, 'hash', 'new', 100], [7, 'hash', 'g', 200]] as $args) {
            self::assertFalse(xoops_2fa_setup_valid($pending, ...$args));
        }
        self::assertFalse(xoops_2fa_setup_valid($pending + ['unused' => true], 7, 'hash', 'g', 201));
        $pending['attempts'] = 5;
        self::assertFalse(xoops_2fa_setup_valid($pending, 7, 'hash', 'g', 100));
        self::assertFalse(xoops_2fa_setup_valid(null, 7, 'hash', 'g', 100));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testProfilePreservesCoreTwoFactorPosts(): void
    {
        require_once XOOPS_ROOT_PATH . '/modules/profile/preloads/core.php';
        $saved = $_POST;
        try {
            foreach (['2fa', '2fa_setup', '2fa_manage'] as $op) {
                $_POST = ['op' => $op];
                ProfileCorePreload::eventCoreUserStart([]);
                self::assertSame($op, $_POST['op']);
            }
        } finally {
            $_POST = $saved;
        }
    }
}
