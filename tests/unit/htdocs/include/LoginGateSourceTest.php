<?php

declare(strict_types=1);

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';

/**
 * The login gate and the challenge entry points cannot be executed in a
 * unit test (they include header.php or exit); this pins their shape:
 * authenticate, then the factor gate, then either the challenge or the
 * session, and every login page serving op=2fa from the one include.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class LoginGateSourceTest extends TestCase
{
    use SourceFileTestTrait;

    #[Test]
    public function pendingChallengeBlocksRememberCookieRestoration(): void
    {
        $this->loadSourceFile('htdocs/include/common.php');
        self::assertStringContainsString("if (empty(\$_SESSION['xoopsUserId'])\n    && !isset(\$_SESSION['xoops2faPending'])", $this->sourceContent);
    }

    #[Test]
    public function checkloginGatesBetweenAuthenticationAndTheSession(): void
    {
        $this->loadSourceFile('htdocs/include/checklogin.php');
        $auth      = strpos($this->sourceContent, 'xoops_login_authenticate(');
        $gate      = strpos($this->sourceContent, 'XoopsUser2faHandler::mustChallenge(');
        $challenge = strpos($this->sourceContent, 'xoops_login_begin_challenge(');
        $session   = strpos($this->sourceContent, 'xoops_login_establish_session(');
        self::assertNotFalse($auth);
        self::assertNotFalse($gate);
        self::assertNotFalse($challenge);
        self::assertNotFalse($session);
        self::assertTrue($auth < $gate && $gate < $challenge && $challenge < $session);
        // a failed lookup challenges rather than admits
        self::assertStringContainsString('catch (\Throwable $e)', $this->sourceContent);
        self::assertStringContainsString('$factorState      = XoopsUser2faHandler::STATE_UNAVAILABLE;', $this->sourceContent);
        self::assertStringContainsString('XoopsUser2faHandler::policy($GLOBALS[\'xoopsConfig\'])', $this->sourceContent);
    }

    #[Test]
    public function everyLoginPageServesTheChallenge(): void
    {
        foreach (['htdocs/user.php', 'htdocs/modules/profile/user.php'] as $file) {
            $this->loadSourceFile($file);
            self::assertStringContainsString("if (\$op === '2fa') {", $this->sourceContent, $file);
            self::assertStringContainsString("include_once \$GLOBALS['xoops']->path('include/checklogin2fa.php');", $this->sourceContent, $file);
        }
        $this->loadSourceFile('htdocs/include/site-closed.php');
        self::assertStringContainsString("include_once \$GLOBALS['xoops']->path('include/checklogin2fa.php');", $this->sourceContent);
        self::assertStringContainsString("hasVar('xoops_2fa', 'POST')", $this->sourceContent);
        self::assertStringContainsString("isset(\$_SESSION['xoops2faPending'])", $this->sourceContent);
    }

    #[Test]
    public function theChallengeIncludeDeclaresOnlyTheGuardedRenderer(): void
    {
        $this->loadSourceFile('htdocs/include/checklogin2fa.php');
        self::assertSame(1, substr_count($this->sourceContent, "\n    function "));
        self::assertStringContainsString("if (!function_exists(ltrim(__NAMESPACE__ . '\\\\xoops_2fa_render', '\\\\'))) {", $this->sourceContent);
        // the headers are sent for every outcome, before anything is decided
        $headers = strpos($this->sourceContent, "header('Cache-Control: no-store');");
        $pending = strpos($this->sourceContent, "\$_SESSION['xoops2faPending'] ?? null");
        self::assertNotFalse($headers);
        self::assertNotFalse($pending);
        self::assertLessThan($pending, $headers);
        self::assertStringContainsString("header('Referrer-Policy: no-referrer');", $this->sourceContent);
        self::assertStringContainsString("header('X-Frame-Options: DENY');", $this->sourceContent);
        self::assertStringContainsString('$tpl->caching = 0;', $this->sourceContent);
        // the closed-site page includes this without loading the user language first
        self::assertStringContainsString("xoops_loadLanguage('user');", $this->sourceContent);
    }
}
