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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';

/**
 * common.php restores the current user from the session store or from the
 * remember-me cookie. It used to check only that the account existed, so a
 * deactivated account kept its session, and it applied the group list cached
 * at login, so a demoted account kept its old rights until the session died.
 * The restore now ends the session for an inactive account and resolves
 * groups from the database on every request.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class SessionRestoreActiveCheckTest extends TestCase
{
    use SourceFileTestTrait;

    private string $restore = '';

    protected function setUp(): void
    {
        $this->loadSourceFile('htdocs/include/common.php');
        $start = strpos($this->sourceContent, "if (!empty(\$_SESSION['xoopsUserId'])) {");
        self::assertNotFalse($start);
        $end = strpos($this->sourceContent, '$xoopsUserIsAdmin = $xoopsUser->isAdmin();', $start);
        self::assertNotFalse($end);
        $this->restore = substr($this->sourceContent, $start, $end - $start);
    }

    #[Test]
    public function inactiveAccountIsTreatedLikeAMissingOne(): void
    {
        self::assertStringContainsString('if (!is_object($xoopsUser) || !$xoopsUser->isActive()) {', $this->restore);

        // The same branch must still clear the session and both cookie forms.
        $branch = substr($this->restore, strpos($this->restore, '!$xoopsUser->isActive()'));
        $branch = substr($branch, 0, strpos($branch, '} else {'));
        self::assertStringContainsString('session_destroy();', $branch);
        self::assertSame(2, substr_count($branch, "xoops_setcookie(\$GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600"));

        // With remember-me disabled the cookie name is empty and setcookie()
        // throws, so the clearing must be conditional on the name.
        $guard = strpos($branch, "if (!empty(\$GLOBALS['xoopsConfig']['usercookie'])) {");
        self::assertNotFalse($guard);
        self::assertLessThan(strpos($branch, 'xoops_setcookie('), $guard);
    }

    #[Test]
    public function groupsComeFromTheDatabaseNotTheSessionCopy(): void
    {
        self::assertStringNotContainsString("setGroups(\$_SESSION['xoopsUserGroups'])", $this->restore);
        self::assertStringContainsString("\$_SESSION['xoopsUserGroups'] = \$xoopsUser->getGroups();", $this->restore);
    }
}
