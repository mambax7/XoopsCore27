<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TwoFactorManagementTest extends TestCase
{
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
