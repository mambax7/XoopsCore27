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
        self::assertStringContainsString('XoopsUser2faHandler::STATE_UNAVAILABLE', $this->sourceContent);
        self::assertStringContainsString('XoopsUser2faHandler::mustChallenge(XoopsUser2faHandler::policy($xoopsConfig), $factorState)', $this->sourceContent);
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
        self::assertStringContainsString("redirect_header(XOOPS_URL . '/user.php', 3, _US_2FA_REQUIRED);", $this->sourceContent);
        $this->loadSourceFile('htdocs/class/xml/rpc/xmlrpcapi.php');
        // the login failure, the factor refusal and the module-read refusal
        self::assertSame(3, substr_count($this->sourceContent, "            unset(\$this->user);\n\n            return false;"));
    }
}
