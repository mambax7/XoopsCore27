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
 * Executes the restore condition of include/common.php against every claim
 * shape a signed remember-me token could carry. The condition is sliced from
 * the source by its markers and evaluated in an isolated namespace where the
 * fingerprint helper is a stub, so the test proves the expression as written,
 * not a copy of it.
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
     * Rows: [user, claims, session ends, signing key (defaults to a readable one)].
     *
     * @return array<string, array{mixed, mixed, bool, 3?: string}>
     */
    public static function cases(): array
    {
        $active = self::user(active: true);

        return [
            'matching fingerprint on the cookie path'      => [$active, (object) ['uid' => 5, 'pfp' => self::FP], false],
            'changed fingerprint'                          => [$active, (object) ['uid' => 5, 'pfp' => 'stale'], true],
            'claim missing (token issued before upgrade)'  => [$active, (object) ['uid' => 5], true],
            'null claim'                                   => [$active, (object) ['uid' => 5, 'pfp' => null], true],
            'array claim'                                  => [$active, (object) ['uid' => 5, 'pfp' => [self::FP]], true],
            'object claim'                                 => [$active, (object) ['uid' => 5, 'pfp' => (object) ['v' => self::FP]], true],
            'boolean claim'                                => [$active, (object) ['uid' => 5, 'pfp' => true], true],
            'integer claim'                                => [$active, (object) ['uid' => 5, 'pfp' => 1], true],
            'float claim'                                  => [$active, (object) ['uid' => 5, 'pfp' => 1.0], true],
            'session-store restore ignores the fingerprint' => [$active, false, false],
            'inactive account'                             => [self::user(active: false), (object) ['uid' => 5, 'pfp' => self::FP], true],
            'missing account'                              => ['', (object) ['uid' => 5, 'pfp' => self::FP], true],
            'no signing key readable'                      => [$active, (object) ['uid' => 5, 'pfp' => self::FP], true, ''],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function restoreEndsTheSessionOnlyWhenItShould(mixed $xoopsUser, mixed $rememberClaims, bool $ends, string $rememberSigningKey = 'unit-test-signing-key'): void
    {
        $this->loadSourceFile('htdocs/include/common.php');
        $start = strpos($this->sourceContent, 'if (!is_object($xoopsUser) || !$xoopsUser->isActive()');
        self::assertNotFalse($start);
        $end = strpos($this->sourceContent, "{\n", $start);
        self::assertNotFalse($end);
        // "if (<condition>) {" -> "<condition>". The slice ends at the first
        // "{\n" after the start, which is the if's opening brace as long as the
        // condition itself contains no brace; the balance check below catches a
        // truncated or over-long slice before eval() can run it.
        $condition = trim(substr($this->sourceContent, $start + 3, $end - $start - 3));
        self::assertSame(substr_count($condition, '('), substr_count($condition, ')'), 'condition slice is not balanced');
        self::assertStringStartsWith('(', $condition);
        self::assertStringEndsWith(')', $condition);

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

        $result = eval('namespace ' . $namespace . "; return " . $condition . ';');

        self::assertSame($ends, $result);
        if (!is_object($xoopsUser) || !$xoopsUser->isActive() || !is_object($rememberClaims)) {
            self::assertSame(0, $GLOBALS['rememberMatrixHelperCalls'], 'the helper must not run off the cookie path or for a missing/inactive account');
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
