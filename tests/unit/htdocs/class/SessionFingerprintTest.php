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

namespace xoopsclass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * XoopsUserUtility::sessionFingerprint() binds a server-side session to the
 * stored password hash; refreshSessionFingerprint() re-stamps only the
 * session of the account whose password was just saved.
 *
 * @category  Xoops
 * @package   Core
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(\XoopsUserUtility::class)]
final class SessionFingerprintTest extends TestCase
{
    private array $session = [];

    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/kernel/user.php';
        require_once XOOPS_ROOT_PATH . '/class/userutility.php';
        $this->session = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->session;
    }

    private function user(int $uid, string $hash): \XoopsUser
    {
        $user = new \XoopsUser();
        $user->setVar('uid', $uid);
        $user->setVar('pass', $hash);

        return $user;
    }

    #[Test]
    public function fingerprintChangesWithThePasswordHash(): void
    {
        $old = \XoopsUserUtility::sessionFingerprint($this->user(5, '$2y$10$old'));
        $new = \XoopsUserUtility::sessionFingerprint($this->user(5, '$2y$10$new'));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $old);
        $this->assertNotSame($old, $new);
        $this->assertSame($old, \XoopsUserUtility::sessionFingerprint($this->user(9, '$2y$10$old')));
    }

    #[Test]
    public function fingerprintReadsTheRawHashNotAnEscapedCopy(): void
    {
        $hash = '$2y$10$a&b<c';

        $this->assertSame(
            hash('sha256', 'xoops-session-pfp:' . $hash),
            \XoopsUserUtility::sessionFingerprint($this->user(5, $hash))
        );
    }

    #[Test]
    public function refreshStampsTheSignedInAccount(): void
    {
        $_SESSION['xoopsUserId']  = '5';
        $_SESSION['xoopsUserPfp'] = 'stale';
        $user = $this->user(5, '$2y$10$new');

        \XoopsUserUtility::refreshSessionFingerprint($user);

        $this->assertSame(\XoopsUserUtility::sessionFingerprint($user), $_SESSION['xoopsUserPfp']);
    }

    #[Test]
    public function refreshLeavesAnotherAccountsSessionAlone(): void
    {
        $_SESSION['xoopsUserId']  = 1;
        $_SESSION['xoopsUserPfp'] = 'admin-stamp';

        \XoopsUserUtility::refreshSessionFingerprint($this->user(5, '$2y$10$new'));

        $this->assertSame('admin-stamp', $_SESSION['xoopsUserPfp']);
    }

    #[Test]
    public function refreshWithoutASessionDoesNothing(): void
    {
        \XoopsUserUtility::refreshSessionFingerprint($this->user(5, '$2y$10$new'));

        $this->assertArrayNotHasKey('xoopsUserPfp', $_SESSION);
    }
}
