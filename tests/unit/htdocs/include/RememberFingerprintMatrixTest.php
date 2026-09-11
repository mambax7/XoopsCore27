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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';

/**
 * Executes the acceptance expression of include/common.php's remember-me
 * cookie path against every claim shape a signed token could carry and every
 * account state. The expression is sliced from the source by its marker and
 * evaluated in an isolated namespace where the fingerprint helper is a stub,
 * so the test proves the expression as written, not a copy of it. Only an
 * accepted candidate may seed the session.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class RememberFingerprintMatrixTest extends TestCase
{
    use SourceFileTestTrait;

    private const FP = 'current-fingerprint';

    protected function tearDown(): void
    {
        unset($GLOBALS['rememberMatrixHelperCalls']);
    }

    /**
     * Rows: [candidate account, claims, accepted].
     *
     * @return array<string, array{mixed, object, bool}>
     */
    public static function cases(): array
    {
        $active = self::user(active: true);

        return [
            'matching fingerprint'                        => [$active, (object) ['uid' => 5, 'pfp' => self::FP], true],
            'changed fingerprint'                         => [$active, (object) ['uid' => 5, 'pfp' => 'stale'], false],
            'claim missing (token issued before upgrade)' => [$active, (object) ['uid' => 5], false],
            'null claim'                                  => [$active, (object) ['uid' => 5, 'pfp' => null], false],
            'array claim'                                 => [$active, (object) ['uid' => 5, 'pfp' => [self::FP]], false],
            'object claim'                                => [$active, (object) ['uid' => 5, 'pfp' => (object) ['v' => self::FP]], false],
            'boolean claim'                               => [$active, (object) ['uid' => 5, 'pfp' => true], false],
            'integer claim'                               => [$active, (object) ['uid' => 5, 'pfp' => 1], false],
            'float claim'                                 => [$active, (object) ['uid' => 5, 'pfp' => 1.0], false],
            'inactive account'                            => [self::user(active: false), (object) ['uid' => 5, 'pfp' => self::FP], false],
            'missing account'                             => [false, (object) ['uid' => 5, 'pfp' => self::FP], false],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function aCookieSeedsTheSessionOnlyForAnAccountThatPassesEveryCheck(mixed $rememberCandidate, object $rememberClaims, bool $accepted): void
    {
        $this->loadSourceFile('htdocs/include/common.php');
        // The acceptance decision is the one assignment between loading the
        // candidate and seeding the session.
        $start = strpos($this->sourceContent, '$rememberUser = (is_object($rememberCandidate)');
        self::assertNotFalse($start, 'the cookie path must decide acceptance in one expression');
        $end = strpos($this->sourceContent, ";\n", $start);
        self::assertNotFalse($end);
        $expression = substr($this->sourceContent, $start, $end - $start + 1);
        self::assertSame(substr_count($expression, '('), substr_count($expression, ')'), 'expression slice is not balanced');

        $namespace = __NAMESPACE__ . '\\RestoreCondition';
        if (!class_exists($namespace . '\\XoopsUserUtility', false)) {
            eval('namespace ' . $namespace . ';'
                . ' class XoopsUserUtility {'
                . '   public static function rememberFingerprint($user, $key) {'
                . '     if (!is_object($user)) { throw new \LogicException("helper reached without an account"); }'
                . '     ++$GLOBALS["rememberMatrixHelperCalls"]; return "' . self::FP . '";'
                . '   }'
                . ' }');
        }
        $GLOBALS['rememberMatrixHelperCalls'] = 0;
        $rememberSigningKey = 'unit-test-signing-key';
        $rememberUser       = null;

        // Evaluates the assignment exactly as written; the locals above are
        // the variables it reads.
        eval('namespace ' . $namespace . ";\n" . $expression);

        self::assertSame($accepted, null !== $rememberUser && $rememberUser === $rememberCandidate);
        if (!is_object($rememberCandidate) || !$rememberCandidate->isActive()) {
            self::assertSame(0, $GLOBALS['rememberMatrixHelperCalls'], 'the helper must not run for a missing or inactive account');
        }
    }

    private static function user(bool $active): object
    {
        return new class($active) {
            public function __construct(private bool $active)
            {
            }

            public function isActive(): bool
            {
                return $this->active;
            }
        };
    }
}
