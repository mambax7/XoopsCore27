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
        self::assertStringContainsString('$endSession = !is_object($xoopsUser) || !$xoopsUser->isActive();', $this->restore);
        // The cookie's own checks run before the session is seeded (see the
        // cookie-path test below); this condition carries only the account
        // checks that apply to both the session-store and the cookie path.
        $conditionStart = (int) strpos($this->restore, '$endSession = !is_object($xoopsUser)');
        $branchCondition = substr($this->restore, $conditionStart, (int) strpos($this->restore, ';', $conditionStart) - $conditionStart);
        self::assertStringNotContainsString('rememberFingerprint(', $branchCondition);
        self::assertStringNotContainsString('rememberClaims', $branchCondition);

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

    #[Test]
    public function rememberTokenCarriesTheCredentialFingerprintAtBothIssueSites(): void
    {
        $claim = "'pfp' => XoopsUserUtility::rememberFingerprint(";

        // Renewal on the cookie path recomputes the claim from the loaded account.
        $renewal = strpos($this->restore, '// update our remember me cookie');
        self::assertNotFalse($renewal);
        $renewal = substr($this->restore, $renewal);
        self::assertStringContainsString($claim . '$xoopsUser, ', $renewal);

        // Initial issue at login uses the authenticated object, which already
        // carries a rehashed password when loginUser() rehashed it.
        $login = file_get_contents(dirname($this->filePath) . '/loginsession.php');
        self::assertNotFalse($login);
        self::assertStringContainsString($claim . '$user, ', $login);
    }

    #[Test]
    public function aCookieIsCheckedAgainstTheLoadedAccountBeforeItSeedsTheSession(): void
    {
        // The account is loaded and every check runs BEFORE $_SESSION['xoopsUserId']
        // is written, so a token for a missing or inactive account, or one whose
        // fingerprint no longer matches, never becomes a session at all.
        $blockStart = strpos($this->sourceContent, '$rememberClaims = false;');
        $blockEnd   = strpos($this->sourceContent, "/**
 * Log user in", (int) $blockStart);
        self::assertNotFalse($blockStart);
        self::assertNotFalse($blockEnd);
        $block = substr($this->sourceContent, $blockStart, $blockEnd - $blockStart);

        $read   = strpos($block, '\Xmf\Jwt\TokenReader::fromCookie($rememberKey, ');
        $load   = strpos($block, '$rememberCandidate = $member_handler->getUser((int) $rememberClaims->uid);');
        $decide = strpos($block, '$rememberUser = (is_object($rememberCandidate)');
        $seed   = strpos($block, "\$_SESSION['xoopsUserId'] = \$rememberUser->getVar('uid');");
        self::assertNotFalse($read);
        self::assertNotFalse($load);
        self::assertNotFalse($decide);
        self::assertNotFalse($seed);
        self::assertLessThan($load, $read);
        self::assertLessThan($decide, $load);
        self::assertLessThan($seed, $decide);
        // ... and that is the only session write in the block: nothing seeds
        // it from the raw claim before the decision.
        self::assertSame(1, substr_count($block, "\$_SESSION['xoopsUserId'] ="));

        // A signed token can still carry a malformed claim; it is type-checked
        // and compared in constant time, never coerced into a string.
        $decision = substr($block, $decide, (int) strpos($block, ";
", $decide) - $decide);
        self::assertStringContainsString('$rememberCandidate->isActive()', $decision);
        self::assertStringContainsString('is_string($rememberClaims->pfp ?? null)', $decision);
        self::assertStringContainsString('hash_equals(XoopsUserUtility::rememberFingerprint($rememberCandidate, $rememberSigningKey), $rememberClaims->pfp)', $decision);
        self::assertStringNotContainsString('(string) $rememberClaims->pfp', $block);

        // A refused cookie is not a cookie login: the flag the restore block and
        // the renewal key on is cleared before both cookie forms are expired.
        $reject = strpos($block, '$rememberClaims = false;', $decide);
        self::assertNotFalse($reject, 'the rejection branch must clear the cookie-login flag');
        self::assertLessThan((int) strpos($block, 'xoops_setcookie(', $decide), $reject);
        self::assertSame(2, substr_count(substr($block, $reject), "xoops_setcookie(\$GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600"));
    }

    #[Test]
    public function cookieValidationUsesTheSameGuardedKeySnapshotAsTheCheckAndRenewal(): void
    {
        // The token reader used to build the key on its own, before the
        // snapshot existed. Now the snapshot is taken first, only when a cookie
        // is actually present, and handed to the reader; no snapshot means the
        // cookie is rejected without any key access happening elsewhere.
        $src      = $this->sourceContent;
        $present  = strpos($src, "'' !== \\Xmf\\Request::getString(\$GLOBALS['xoopsConfig']['usercookie'], '', 'COOKIE')");
        $snapshot = strpos($src, '$rememberKey = XoopsUserUtility::rememberKey();');
        $read     = strpos($src, "\\Xmf\\Jwt\\TokenReader::fromCookie(\$rememberKey, \$GLOBALS['xoopsConfig']['usercookie'])");
        $seed     = strpos($src, "\$_SESSION['xoopsUserId'] = \$rememberUser->getVar('uid');");
        self::assertNotFalse($present);
        self::assertNotFalse($snapshot);
        self::assertNotFalse($read);
        self::assertNotFalse($seed);
        self::assertLessThan($snapshot, $present);
        self::assertLessThan($read, $snapshot);
        self::assertLessThan($seed, $read);
        self::assertStringNotContainsString("TokenReader::fromCookie('rememberme'", $src);
        // the reader runs only with a snapshot in hand
        $flat = (string) preg_replace('/\s+/', ' ', $src);
        self::assertStringContainsString('if (null !== $rememberKey) { $rememberSigningKey = $rememberKey->getSigning(); $rememberClaims', $flat);
    }

    #[Test]
    public function theKeyBytesDoNotOutliveTheRestoreBlock(): void
    {
        // The raw HMAC key and the key object are plain globals in common.php;
        // once the renewal has used them nothing else may read them.
        $blockEnd = strpos($this->sourceContent, '$xoopsUserIsAdmin = $xoopsUser->isAdmin();');
        $unset    = strpos($this->sourceContent, 'unset($rememberKey, $rememberSigningKey);');
        self::assertNotFalse($blockEnd);
        self::assertNotFalse($unset);
        self::assertLessThan($unset, $blockEnd);
    }
}
