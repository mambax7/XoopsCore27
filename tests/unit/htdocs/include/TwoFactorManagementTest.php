<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

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
