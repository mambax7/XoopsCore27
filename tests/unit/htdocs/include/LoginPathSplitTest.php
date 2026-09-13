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
 * checklogin.php did password authentication and session establishment in
 * one block, with last_login written as soon as the password was accepted.
 * A second factor needs a seam between the two, and last_login must move
 * only when a session is actually established. The two halves now live in
 * include/loginsession.php as functions; checklogin.php orchestrates.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class LoginPathSplitTest extends TestCase
{
    use SourceFileTestTrait;

    #[Test]
    public function theHelpersFileDeclaresOnlyTheTwoFunctions(): void
    {
        $this->loadSourceFile('htdocs/include/loginsession.php');
        self::assertStringContainsString('function xoops_login_authenticate(string $uname, string $pass)', $this->sourceContent);
        self::assertStringContainsString('function xoops_login_begin_challenge(XoopsUser $user, string $state, string $generation, bool $remember, string $redirect): never', $this->sourceContent);
        self::assertStringContainsString('function xoops_login_establish_session(XoopsUser $user, bool $remember, string $redirect, ?string $verifiedGeneration = null): never', $this->sourceContent);
        // Side-effect free: no top-level statements that run on include. Each
        // declaration is removed with the brace walk below; what remains must
        // be the open tag, comments and the access guard only.
        $topLevel = $this->sourceContent;
        foreach (['xoops_login_authenticate', 'xoops_login_begin_challenge', 'xoops_login_establish_session'] as $name) {
            $start = strpos($topLevel, 'function ' . $name . '(');
            self::assertNotFalse($start);
            $body = $this->functionBody($name);
            $end  = strpos($topLevel, $body, $start) + strlen($body);
            $topLevel = substr($topLevel, 0, $start) . substr($topLevel, $end);
        }
        $topLevel = (string) preg_replace('~/\*.*?\*/~s', '', $topLevel);
        $topLevel = (string) preg_replace('~//[^\n]*~', '', $topLevel);
        self::assertMatchesRegularExpression('~^\s*<\?php\s*(defined\(\'XOOPS_ROOT_PATH\'\)\s*\|\|\s*exit\([^)]*\);)?\s*$~s', $topLevel, 'loginsession.php must declare functions only');
    }

    #[Test]
    public function authenticateDoesNotTouchLastLoginOrTheSession(): void
    {
        $this->loadSourceFile('htdocs/include/loginsession.php');
        $auth = $this->functionBody('xoops_login_authenticate');
        self::assertStringContainsString('XoopsAuthFactory::getAuthConnection(', $auth);
        self::assertStringContainsString("->getVar('level')", $auth);
        self::assertStringContainsString('closesite', $auth);
        self::assertStringNotContainsString('last_login', $auth);
        self::assertStringNotContainsString('insertUser(', $auth);
        self::assertStringNotContainsString('xoopsUserId', $auth);
        self::assertStringNotContainsString('regenerate_id', $auth);
    }

    #[Test]
    public function establishSessionCarriesEverythingThatUsedToFollowThePasswordCheckInOrder(): void
    {
        $this->loadSourceFile('htdocs/include/loginsession.php');
        $body  = $this->functionBody('xoops_login_establish_session');
        $order = [
            "\$user->setVar('last_login', time());",
            '->insertUser($user)',
            'regenerate_id(true)',
            "\$_SESSION['xoopsUserId']     = \$user->getVar('uid');",
            "triggerEvent('core.behavior.user.login', \$user)",
            'XoopsUserUtility::rememberKey()',
            "'pfp' => XoopsUserUtility::rememberFingerprint(\$user, \$rememberKey->getSigning())",
            '->doLoginMaintenance(',
            'redirect_header($url, 1, sprintf(_US_LOGGINGU',
        ];
        $last = -1;
        foreach ($order as $needle) {
            $pos = strpos($body, $needle);
            self::assertNotFalse($pos, "missing: $needle");
            self::assertGreaterThan($last, $pos, "out of order: $needle");
            $last = $pos;
        }
        // remember-me is issued only on request; the parameter replaces the POST read
        self::assertStringContainsString('if ($remember && !$factorEnrolled) {', $body);
        self::assertStringNotContainsString("getString('rememberme'", $body);
    }

    #[Test]
    public function checkloginOrchestratesAndOwnsNoSessionOrCookieCode(): void
    {
        $this->loadSourceFile('htdocs/include/checklogin.php');
        $src = $this->sourceContent;
        self::assertStringContainsString("require_once \$GLOBALS['xoops']->path('include/loginsession.php');", $src);
        $auth = strpos($src, '$user = xoops_login_authenticate($uname, $pass);');
        // the remember flag keeps master's !empty() reading: a posted "0" is not a request
        $done = strpos($src, 'xoops_login_establish_session($user, !empty($rememberme), $redirect);');
        self::assertNotFalse($auth);
        self::assertNotFalse($done);
        self::assertLessThan($done, $auth);
        foreach (['last_login', 'insertUser(', 'xoopsUserId', 'regenerate_id', 'TokenFactory', 'doLoginMaintenance', 'xoops_setcookie('] as $gone) {
            self::assertStringNotContainsString($gone, $src, "$gone must live in loginsession.php now");
        }
        // the two credential-failure redirects are unchanged
        self::assertStringContainsString("redirect_header(XOOPS_URL . '/user.php', 5, _US_INCORRECTLOGIN);", $src);
        self::assertStringContainsString("redirect_header(XOOPS_URL . '/user.php?xoops_redirect=' . urlencode(\$redirect), 5, _US_INCORRECTLOGIN, false);", $src);
    }

    private function functionBody(string $name): string
    {
        $start = strpos($this->sourceContent, 'function ' . $name . '(');
        self::assertNotFalse($start, "$name not declared");
        $open = strpos($this->sourceContent, '{', $start);
        self::assertNotFalse($open);
        $depth = 0;
        $len   = strlen($this->sourceContent);
        for ($i = $open; $i < $len; $i++) {
            $c = $this->sourceContent[$i];
            if ('{' === $c) {
                $depth++;
            } elseif ('}' === $c && 0 === --$depth) {
                return substr($this->sourceContent, $open, $i - $open + 1);
            }
        }
        self::fail("unbalanced body for $name");
    }
}
