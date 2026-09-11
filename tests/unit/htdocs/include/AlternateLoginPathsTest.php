<?php

declare(strict_types=1);

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';

/**
 * Login paths that cannot show a challenge refuse an account whose factor
 * must be presented: the upgrade wizard (unless the escape-hatch file is
 * present), XML-RPC and the SSL popup login. Pinned from source; none of
 * these files can be executed in a unit test.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class AlternateLoginPathsTest extends TestCase
{
    use SourceFileTestTrait;

    #[Test]
    public function theWizardRefusesAnEnrolledAccountUnlessTheEscapeHatchIsPresent(): void
    {
        $this->loadSourceFile('upgrade/login.php');
        $allowed = strpos($this->sourceContent, 'if ($isAllowed) {');
        $factor  = strpos($this->sourceContent, "xoops_getHandler('user2fa')");
        $write   = strpos($this->sourceContent, "\$user->setVar('last_login', time());");
        self::assertNotFalse($allowed);
        self::assertNotFalse($factor);
        self::assertNotFalse($write);
        self::assertTrue($allowed < $factor && $factor < $write);
        self::assertStringContainsString("resetByEscapeHatch((int) \$user->getVar('uid'))", $this->sourceContent);
        self::assertStringContainsString('XoopsUser2faHandler::mustChallenge(XoopsUser2faHandler::policy($xoopsConfig), $factorState)', $this->sourceContent);
        // a lookup or reset failure is a refusal, not an uncaught exception
        self::assertStringContainsString('} catch (\\Throwable $e) {', $this->sourceContent);
        self::assertStringNotContainsString('$refusal = $e->getMessage();', $this->sourceContent);
        self::assertStringContainsString("\$refusal = 'second-factor verification unavailable';", $this->sourceContent);
        self::assertStringContainsString('if (null !== $refusal) {', $this->sourceContent);
        $this->assertBindsTheSession('upgrade/login.php');
    }

    /**
     * A session seeded by hand carries the stamp include/common.php checks
     * on every request, or an enrolled account signed in while the policy is
     * off would lose it on the next request.
     */
    private function assertBindsTheSession(string $file): void
    {
        $this->loadSourceFile($file);
        $seed  = strpos($this->sourceContent, "\$_SESSION['xoopsUserId']     = \$user->getVar('uid');");
        $stamp = strpos($this->sourceContent, "\$_SESSION['xoops2faGeneration'] = is_array(\$factorRow) ? (string) \$factorRow['generation'] : '';");
        self::assertNotFalse($seed, $file);
        self::assertNotFalse($stamp, $file);
        self::assertLessThan($stamp, $seed, $file);
        self::assertStringContainsString("\$_SESSION['xoops2faVerified']   = false;", $this->sourceContent, $file);
    }

    #[Test]
    public function popupDoesNotAutomaticallyDowngradeToAnHttpCore(): void
    {
        $this->loadSourceFile('extras/login.php');
        $start = strpos($this->sourceContent, "            // The SSL bridge");
        self::assertNotFalse($start);
        $end = strpos($this->sourceContent, "        if (!\$GLOBALS['sess_handler']->regenerate_id", $start);
        self::assertNotFalse($end);
        $body = substr($this->sourceContent, $start, $end - $start);
        // Exclude the enclosing mustChallenge brace; exit becomes an observable boundary.
        $body = preg_replace('/\s*}\s*$/', '', $body);
        $body = str_replace('exit();', 'return;', $body);
        foreach (['http', 'https'] as $scheme) {
            $ns = __NAMESPACE__ . '\\Popup' . ucfirst($scheme);
            $GLOBALS['popupRedirect'] = null;
            ob_start();
            try {
                eval('namespace ' . $ns . '; const XOOPS_URL = "' . $scheme . '://example.test";'
                    . 'const _US_2FA_HTTP_LOGIN = "HTTP transport warning"; const _US_2FA_REQUIRED = "Core login";'
                    . 'function xoops_error($message) { echo $message; }'
                    . 'function redirect_header($url, ...$args) { $GLOBALS["popupRedirect"] = $url; }'
                    . $body);
                $html = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            if ('http' === $scheme) {
                self::assertNull($GLOBALS['popupRedirect']);
                self::assertStringContainsString('HTTP transport warning', $html);
                self::assertStringContainsString('href="http://example.test/user.php"', $html);
            } else {
                self::assertSame('https://example.test/user.php', $GLOBALS['popupRedirect']);
                self::assertSame('', $html);
            }
        }
    }

    #[Test]
    public function xmlRpcAndTheSslPopupRefuseAnAccountThatMustBeChallenged(): void
    {
        foreach (['htdocs/class/xml/rpc/xmlrpcapi.php', 'extras/login.php'] as $file) {
            $this->loadSourceFile($file);
            $login = strpos($this->sourceContent, '->loginUser(');
            $gate  = strpos($this->sourceContent, 'XoopsUser2faHandler::mustChallenge(XoopsUser2faHandler::policy(');
            self::assertNotFalse($login, $file);
            self::assertNotFalse($gate, $file);
            self::assertLessThan($gate, $login, $file);
            self::assertStringContainsString('XoopsUser2faHandler::STATE_UNAVAILABLE', $this->sourceContent, $file);
        }
        $this->loadSourceFile('extras/login.php');
        self::assertStringContainsString("if (!\$GLOBALS['sess_handler']->regenerate_id(true))", $this->sourceContent);
        self::assertStringContainsString("redirect_header(XOOPS_URL . '/user.php', 3, _US_2FA_REQUIRED, false);", $this->sourceContent);
        self::assertStringContainsString("xoops_loadLanguage('user2fa');", $this->sourceContent);
        $this->assertBindsTheSession('extras/login.php');
        $this->loadSourceFile('htdocs/class/xml/rpc/xmlrpcapi.php');
        // the login failure, the factor refusal and the module-read refusal
        self::assertSame(3, substr_count($this->sourceContent, "            unset(\$this->user);\n\n            return false;"));
    }
}
