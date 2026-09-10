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
        self::assertStringContainsString('if (!is_object($xoopsUser) || !$xoopsUser->isActive()', $this->restore);

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
        $login = file_get_contents(dirname($this->filePath) . '/checklogin.php');
        self::assertNotFalse($login);
        self::assertStringContainsString($claim . '$user, ', $login);
    }

    #[Test]
    public function aRememberTokenWithoutAMatchingFingerprintEndsTheSession(): void
    {
        $condition = substr($this->restore, (int) strpos($this->restore, 'if (!is_object($xoopsUser) || !$xoopsUser->isActive()'));
        $condition = substr($condition, 0, (int) strpos($condition, "\$xoopsUser = '';"));

        $active = strpos($condition, '!$xoopsUser->isActive()');
        $cookie = strpos($condition, 'is_object($rememberClaims)');
        $string = strpos($condition, 'is_string($rememberClaims->pfp ?? null)');
        $equal  = strpos($condition, 'hash_equals(XoopsUserUtility::rememberFingerprint($xoopsUser, ');
        self::assertNotFalse($cookie);
        self::assertNotFalse($string);
        self::assertNotFalse($equal);

        // A missing or inactive account short-circuits before the helper runs,
        // and the fingerprint terms apply only on the cookie path.
        self::assertLessThan($cookie, $active);
        self::assertLessThan($string, $cookie);
        self::assertLessThan($equal, $string);
        $flat = (string) preg_replace('/\s+/', ' ', $condition);
        self::assertStringContainsString("&& ('' === \$rememberSigningKey || !is_string(\$rememberClaims->pfp ?? null)", $flat);

        // A signed token can still carry a malformed claim; it must fail the
        // comparison, never be coerced into a string.
        self::assertStringNotContainsString('(string) $rememberClaims->pfp', $condition);

        // No readable signing key: fail closed rather than compare against a
        // fingerprint keyed with ''.
        $noKey = strpos($condition, "'' === \$rememberSigningKey");
        self::assertNotFalse($noKey);
        self::assertLessThan($noKey, $cookie);
        self::assertLessThan($string, $noKey);
    }

    #[Test]
    public function fingerprintAndSignatureShareOneSnapshotOfTheKey(): void
    {
        // Both issue sites take the key once via rememberKey() and hand that
        // same object to the signer, so a re-read of storage between the two
        // cannot produce a token whose fingerprint and signature disagree.
        $renewal = substr($this->restore, (int) strpos($this->restore, '// update our remember me cookie'));
        self::assertStringContainsString('\Xmf\Jwt\TokenFactory::build($rememberKey, $claims, $rememberTime)', $renewal);
        self::assertStringContainsString('$rememberKey = XoopsUserUtility::rememberKey();', $this->sourceContent);
        self::assertStringContainsString('$rememberSigningKey = $rememberKey->getSigning();', $this->sourceContent);

        $login = file_get_contents(dirname($this->filePath) . '/checklogin.php');
        self::assertNotFalse($login);
        self::assertStringContainsString('$rememberKey = XoopsUserUtility::rememberKey();', $login);
        self::assertStringContainsString('\Xmf\Jwt\TokenFactory::build($rememberKey, $claims, $rememberTime)', $login);
        self::assertStringContainsString("rememberFingerprint(\$user, \$rememberKey->getSigning())", $login);
        // and nothing is issued without a key
        self::assertStringContainsString('if (null !== $rememberKey) {', $login);
        self::assertStringNotContainsString("TokenFactory::build('rememberme'", $login);
        self::assertStringNotContainsString("TokenFactory::build('rememberme'", $renewal);
    }

    #[Test]
    public function loginReadsTheKeyOnlyWhenRememberMeWasRequested(): void
    {
        // Reading the key creates the key file as a side effect, so a login
        // without "remember me" must not touch it. A request that cannot be
        // honoured is explained by the warning rememberKey() itself raises for
        // every null result, so the login handler adds no second one.
        $login = file_get_contents(dirname($this->filePath) . '/checklogin.php');
        self::assertNotFalse($login);
        $request = strpos($login, 'if (!empty($rememberme)) {');
        $read    = strpos($login, '$rememberKey = XoopsUserUtility::rememberKey();');
        $issue   = strpos($login, 'if (null !== $rememberKey) {');
        self::assertNotFalse($request);
        self::assertNotFalse($read);
        self::assertNotFalse($issue);
        self::assertLessThan($read, $request);
        self::assertLessThan($issue, $read);
        self::assertStringNotContainsString('trigger_error(', substr($login, $request, $issue - $request));
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
        $seed     = strpos($src, "\$_SESSION['xoopsUserId'] = \$rememberClaims->uid;");
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
