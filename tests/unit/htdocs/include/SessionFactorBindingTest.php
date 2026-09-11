<?php

declare(strict_types=1);

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';

/**
 * common.php binds every authenticated request to the account's factor row:
 * an enrolled row ends a session whose stored generation is not the row's,
 * a required factor that was never presented ends the session, and the
 * remember-me cookie carries the generation in its fgen claim and is refused
 * when it differs. common.php cannot be included in a unit test, so the
 * shape is pinned from source.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class SessionFactorBindingTest extends TestCase
{
    use SourceFileTestTrait;

    private string $restore = '';
    private string $session = '';

    protected function setUp(): void
    {
        $this->loadSourceFile('htdocs/include/common.php');
        $a = strpos($this->sourceContent, 'Load xoopsUserId from cookie');
        $b = strpos($this->sourceContent, 'Log user in and deal with Sessions and Cookies');
        $c = strpos($this->sourceContent, "triggerEvent('core.include.common.auth.success')");
        self::assertNotFalse($a);
        self::assertNotFalse($b);
        self::assertNotFalse($c);
        $this->restore = substr($this->sourceContent, $a, $b - $a);
        $this->session = substr($this->sourceContent, $b, $c - $b);
    }

    #[Test]
    public function theCookieRestoreChecksTheFactorAfterTheFingerprintAndBeforeSeeding(): void
    {
        $pfp  = strpos($this->restore, 'rememberFingerprint($rememberCandidate');
        $row  = strpos($this->restore, "xoops_getHandler('user2fa')->getRow(");
        $seed = strpos($this->restore, "\$_SESSION['xoopsUserId'] = \$rememberUser->getVar('uid');");
        self::assertNotFalse($pfp);
        self::assertNotFalse($row);
        self::assertNotFalse($seed);
        self::assertTrue($pfp < $row && $row < $seed);
        // absent fgen reads as ''; present but not a string refuses; enrolled refuses; a failed lookup refuses
        self::assertStringContainsString("\$rememberFgen = \$rememberClaims->fgen ?? '';", $this->restore);
        self::assertStringContainsString('!is_string($rememberFgen)', $this->restore);
        self::assertStringContainsString("XoopsUser2faHandler::ROW_ENROLLED === \$rememberRow['state']", $this->restore);
        self::assertStringContainsString('catch (\Throwable $e) {', $this->restore);
        self::assertStringContainsString('$rememberRow = false;', $this->restore);
        self::assertStringContainsString('false === $rememberRow ||', $this->restore);
        self::assertStringContainsString("hash_equals(is_array(\$rememberRow) ? (string) \$rememberRow['generation'] : '', \$rememberFgen)", $this->restore);
    }

    #[Test]
    public function anEstablishedSessionIsEndedOnAGenerationMismatchOrAnUnverifiedRequiredFactor(): void
    {
        self::assertStringContainsString("\$factorRow = xoops_getHandler('user2fa')->getRow((int) \$_SESSION['xoopsUserId']);", $this->session);
        self::assertStringContainsString("!is_string(\$stored) || !hash_equals((string) \$factorRow['generation'], \$stored)", $this->session);
        self::assertStringContainsString("\$_SESSION['xoops2faGeneration'] = is_array(\$factorRow) ? (string) \$factorRow['generation'] : '';", $this->session);
        self::assertStringContainsString("XoopsUser2faHandler::mustChallenge(XoopsUser2faHandler::policy(\$xoopsConfig), \$factorState) && true !== (\$_SESSION['xoops2faVerified'] ?? false)", $this->session);
        // a failed lookup logs and continues, and skips both checks
        self::assertStringContainsString('trigger_error(', $this->session);
        self::assertStringContainsString('$factorRow = false;', $this->session);
        self::assertStringContainsString('} elseif (false !== $factorRow) {', $this->session);
        self::assertStringContainsString('if (!$endSession && false !== $factorRow', $this->session);
        // the renewal carries the generation
        self::assertStringContainsString("'fgen' => is_array(\$factorRow) ? (string) \$factorRow['generation'] : '',", $this->session);
        // one cleanup path serves the inactive account and the factor mismatch
        self::assertSame(1, substr_count($this->session, 'session_destroy();'));
        self::assertStringContainsString('if ($endSession) {', $this->session);
        // the factor checks run before the renewal, so the renewal sees the row
        $factor  = strpos($this->session, "xoops_getHandler('user2fa')");
        $renewal = strpos($this->session, "'fgen' =>");
        self::assertNotFalse($factor);
        self::assertNotFalse($renewal);
        self::assertLessThan($renewal, $factor);
    }
}
