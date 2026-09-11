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

namespace Tests\Unit\Modules\Profile;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RedirectHeaderException;

/**
 * The profile module's admin delete used to remove the profile row and then
 * call deleteUser(). deleteUser() can refuse (its token delete can fail), and
 * a refused account must keep its profile row, so the account goes first.
 * The confirmed-delete branch of modules/profile/admin/user.php is sliced out
 * and executed with spies; each case runs in its own process because the
 * branch needs a language constant.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class AdminUserDeleteOrderTest extends TestCase
{
    private const NS = 'Tests\\Unit\\Modules\\Profile\\DeleteSandbox';

    protected function setUp(): void
    {
        defined('_PROFILE_AM_DELETEDSUCCESS') || define('_PROFILE_AM_DELETEDSUCCESS', 'Deleted %s');
        // One case runs without the constant, as a translated pack that
        // predates it would; each test is its own process, so this holds.
        if ('theFailureMessageFallsBackWhenTheConstantIsUndefined' !== $this->name()) {
            defined('_PROFILE_AM_DELETEFAILED') || define('_PROFILE_AM_DELETEFAILED', 'Deleting %s failed');
        }
        $GLOBALS['userErrors'] = [];
        $GLOBALS['deleteOrderLog']       = [];
        $GLOBALS['deleteUserResult']     = true;
        $GLOBALS['profileDeleteResult']  = true;
        $GLOBALS['profileExists']        = true;
        $this->loadSandbox();
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function theAccountIsDeletedBeforeItsProfileRowAndSuccessRedirects(): void
    {
        $out = $this->runBranch();
        self::assertInstanceOf(RedirectHeaderException::class, $out['redirect']);
        self::assertStringContainsString('Deleted alice', $out['redirect']->getMessage());
        self::assertSame(['deleteUser:10', 'profileDelete:10'], $GLOBALS['deleteOrderLog']);
        self::assertSame('', $out['echo']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aRefusedAccountDeleteKeepsTheProfileRow(): void
    {
        $GLOBALS['deleteUserResult'] = false;
        $out = $this->runBranch();
        self::assertNull($out['redirect']);
        self::assertSame(['deleteUser:10'], $GLOBALS['deleteOrderLog'], 'the profile row must not be touched');
        // deleteUser() leaves no error on the object when its token step fails
        self::assertSame('error:Deleting alice failed', $out['echo']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function theFailureMessageFallsBackWhenTheConstantIsUndefined(): void
    {
        self::assertFalse(defined('_PROFILE_AM_DELETEFAILED'));
        $GLOBALS['deleteUserResult'] = false;
        $out = $this->runBranch();
        self::assertNull($out['redirect']);
        self::assertSame('error:Deleting alice failed; the account was not removed', $out['echo']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aRefusedAccountDeleteShowsTheObjectErrorsWhenThereAreAny(): void
    {
        $GLOBALS['deleteUserResult'] = false;
        $GLOBALS['userErrors']       = ['row locked'];
        $out = $this->runBranch();
        self::assertNull($out['redirect']);
        self::assertSame('error:row locked', $out['echo']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aFailedProfileDeleteIsReportedAfterTheAccountIsGone(): void
    {
        $GLOBALS['profileDeleteResult'] = false;
        $out = $this->runBranch();
        self::assertNull($out['redirect']);
        self::assertSame(['deleteUser:10', 'profileDelete:10'], $GLOBALS['deleteOrderLog']);
        self::assertSame('profile-errors', $out['echo']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function anAccountWithoutAProfileRowIsDeletedAndRedirects(): void
    {
        $GLOBALS['profileExists'] = false;
        $out = $this->runBranch();
        self::assertInstanceOf(RedirectHeaderException::class, $out['redirect']);
        self::assertSame(['deleteUser:10'], $GLOBALS['deleteOrderLog']);
    }

    /**
     * @return array{redirect: ?RedirectHeaderException, echo: string}
     */
    private function runBranch(): array
    {
        $fn = self::NS . '\\run_confirmed_delete';
        ob_start();
        $redirect = null;
        try {
            $fn();
        } catch (RedirectHeaderException $e) {
            $redirect = $e;
        } finally {
            $echo = (string) ob_get_clean();
        }

        return ['redirect' => $redirect, 'echo' => $echo];
    }

    private function loadSandbox(): void
    {
        if (function_exists(self::NS . '\\run_confirmed_delete')) {
            return;
        }
        $source = file_get_contents(dirname(__DIR__, 5) . '/htdocs/modules/profile/admin/user.php');
        self::assertNotFalse($source);
        $start = strpos($source, "            \$profile_handler = xoops_getModuleHandler('profile');");
        $end   = strpos($source, "        } else {\n            xoops_confirm(", (int) $start);
        self::assertNotFalse($start, 'confirmed-delete branch start marker');
        self::assertNotFalse($end, 'confirmed-delete branch end marker');
        $branch = substr($source, $start, $end - $start);

        $stubs = <<<'PHP'
        namespace Tests\Unit\Modules\Profile\DeleteSandbox;

        function xoops_error(string|array $msg): void {
            echo 'error:' . (is_array($msg) ? implode(',', $msg) : $msg);
        }
        function xoops_getModuleHandler(string $name): object {
            return new class {
                public function get(int $uid): ?object {
                    if (!$GLOBALS['profileExists']) { return null; }
                    return new class($uid) {
                        public function __construct(private int $uid) {}
                        public function isNew(): bool { return false; }
                        public function getHtmlErrors(): string { return 'profile-errors'; }
                        public function uid(): int { return $this->uid; }
                    };
                }
                public function delete(object $profile): bool {
                    $GLOBALS['deleteOrderLog'][] = 'profileDelete:' . $profile->uid();
                    return $GLOBALS['profileDeleteResult'];
                }
            };
        }
        PHP;
        eval($stubs);

        // The branch reads $handler and $obj from the page; here they are the
        // function's locals. redirect_header() is the bootstrap's throwing stub.
        $wrapper = 'namespace ' . self::NS . ";\n"
            . "function run_confirmed_delete(): void {\n"
            . "    \$handler = new class { public function deleteUser(object \$u): bool { \$GLOBALS['deleteOrderLog'][] = 'deleteUser:' . \$u->getVar('uid'); return \$GLOBALS['deleteUserResult']; } };\n"
            . "    \$obj = new class { public function getVar(string \$k): mixed { return ['uid' => 10, 'uname' => 'alice', 'email' => 'a@example.org'][\$k] ?? null; } public function getErrors(): array { return \$GLOBALS['userErrors']; } public function getHtmlErrors(): string { return 'user-errors'; } };\n"
            . $branch . "\n}\n";
        eval($wrapper);
    }
}
