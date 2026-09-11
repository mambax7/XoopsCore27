<?php
/**
 * Unit tests for XoopsTotp
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.4
 */

declare(strict_types=1);

namespace xoopsclass;

use kernel\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use XoopsTotp;

/**
 * Pins the TOTP helper to RFC 6238 and to a one-step window that refuses reuse.
 *
 * @category  Test
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(XoopsTotp::class)]
class XoopsTotpTest extends KernelTestCase
{
    /** RFC 6238 test secret "12345678901234567890" in base32. */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected function setUp(): void
    {
        parent::setUp();
        require_once XOOPS_ROOT_PATH . '/class/XoopsTotp.php';
    }

    /**
     * RFC 6238 Appendix B, SHA-1 column, last six digits of the eight-digit values.
     *
     * @return array<int, array{int, string}>
     */
    public static function rfc6238Vectors(): array
    {
        return [
            [59, '287082'],
            [1111111109, '081804'],
            [1111111111, '050471'],
            [1234567890, '005924'],
            [2000000000, '279037'],
            [20000000000, '353130'],
        ];
    }

    #[Test]
    #[DataProvider('rfc6238Vectors')]
    public function codeAtMatchesTheRfc6238Vectors(int $time, string $expected): void
    {
        $this->assertSame($expected, XoopsTotp::codeAt(self::RFC_SECRET, XoopsTotp::stepAt($time)));
    }

    #[Test]
    public function base32RoundTripsAndDecodesLooseInput(): void
    {
        $this->assertSame(self::RFC_SECRET, XoopsTotp::base32Encode('12345678901234567890'));
        $this->assertSame('12345678901234567890', XoopsTotp::base32Decode(self::RFC_SECRET));
        $this->assertSame('12345678901234567890', XoopsTotp::base32Decode(strtolower('gezd gnbv gy3t qojq gezd gnbv gy3t qojq====')));
        $this->assertFalse(XoopsTotp::base32Decode('GEZD1NBV'));   // '1' is not in the alphabet
        $this->assertFalse(XoopsTotp::base32Decode(''));
        $this->assertFalse(XoopsTotp::codeAt('not base32!', 1));
    }

    #[Test]
    public function newSecretIsThirtyTwoBase32CharactersOfTwentyBytes(): void
    {
        $secret = XoopsTotp::newSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertSame(20, strlen((string) XoopsTotp::base32Decode($secret)));
        $this->assertNotSame($secret, XoopsTotp::newSecret());
    }

    #[Test]
    public function matchStepAcceptsOneStepEitherSideAndNothingFurther(): void
    {
        $now  = 1111111111;
        $step = XoopsTotp::stepAt($now);
        $code = (string) XoopsTotp::codeAt(self::RFC_SECRET, $step);

        $this->assertSame($step, XoopsTotp::matchStep(self::RFC_SECRET, $code, $now, 0));
        $this->assertSame($step, XoopsTotp::matchStep(self::RFC_SECRET, $code, $now - 30, 0)); // client one step ahead
        $this->assertSame($step, XoopsTotp::matchStep(self::RFC_SECRET, $code, $now + 30, 0)); // client one step behind
        $this->assertFalse(XoopsTotp::matchStep(self::RFC_SECRET, $code, $now + 60, 0));
        $this->assertFalse(XoopsTotp::matchStep(self::RFC_SECRET, $code, $now - 60, 0));
    }

    #[Test]
    public function matchStepRefusesStepsAtOrBelowTheLastCounter(): void
    {
        $now      = 1111111111;
        $step     = XoopsTotp::stepAt($now);
        $code     = (string) XoopsTotp::codeAt(self::RFC_SECRET, $step);
        $previous = (string) XoopsTotp::codeAt(self::RFC_SECRET, $step - 1);

        $this->assertFalse(XoopsTotp::matchStep(self::RFC_SECRET, $code, $now, $step));       // same step twice
        $this->assertFalse(XoopsTotp::matchStep(self::RFC_SECRET, $previous, $now, $step));   // N-1 after N
        $this->assertSame($step - 1, XoopsTotp::matchStep(self::RFC_SECRET, $previous, $now, $step - 2));
    }

    #[Test]
    public function matchStepRefusesMalformedCodes(): void
    {
        $now = 1111111111;
        foreach (['', '12345', '1234567', '12345a', ' 287082', "287082\n"] as $bad) {
            $this->assertFalse(XoopsTotp::matchStep(self::RFC_SECRET, $bad, $now, 0), var_export($bad, true));
        }
        $this->assertFalse(XoopsTotp::matchStep('not base32!', '287082', $now, 0));
    }
}
