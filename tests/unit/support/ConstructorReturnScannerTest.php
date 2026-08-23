<?php
/**
 * Unit tests for ConstructorReturnScanner
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
 * @since     2.7.3
 */

declare(strict_types=1);

namespace testsupport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Truth table for the shared constructor value-return scanner - every
 * edge the review rounds surfaced, pinned in one place so both
 * convention tests inherit the same semantics.
 *
 * @category  Test
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(ConstructorReturnScanner::class)]
final class ConstructorReturnScannerTest extends TestCase
{
    /**
     * @return array<string, array{string, array<int, int>}> source, expected violation lines
     */
    public static function table(): array
    {
        return [
            'plain ctor value return' => [
                '<?php class C { public function __construct() { return null; } }',
                [1],
            ],
            'bare return stays legal' => [
                '<?php class C { public function __construct() { if (1) { return; } } }',
                [],
            ],
            'uppercase __CONSTRUCT' => [
                '<?php class C { public function __CONSTRUCT() { return false; } }',
                [1],
            ],
            'interface ctor then braced code with returns' => [
                '<?php interface I { public function __construct(); } function f() { return 42; }',
                [],
            ],
            'abstract ctor then method with return' => [
                '<?php abstract class A { abstract public function __construct(); public function g() { return 1; } }',
                [],
            ],
            'doc-commented closure inside ctor' => [
                '<?php class C { public function __construct() { $f = function /** doc */ () { return 5; }; } }',
                [],
            ],
            'by-ref nested function inside ctor' => [
                '<?php class C { public function __construct() { $f = function &() { $x = 1; return $x; }; } }',
                [],
            ],
            'method return outside ctor' => [
                '<?php class C { public function get() { return 1; } }',
                [],
            ],
        ];
    }

    #[Test]
    #[DataProvider('table')]
    public function scanMatchesTheTable(string $source, array $expectedLines): void
    {
        $this->assertSame($expectedLines, ConstructorReturnScanner::scan($source));
    }

    #[Test]
    public function nestedAnonymousClassConstructorIsAttributedCorrectly(): void
    {
        // The review case: an anonymous class declares its own
        // __construct inside another constructor. Only the nested
        // constructor's value return may be reported (line 5); the
        // unrelated method's return (line 10) must never be - the frame
        // stack makes this hold by construction, with no reliance on a
        // statement terminator clearing a leaked flag.
        $source = <<<'PHP'
<?php
class Outer {
    public function __construct() {
        $x = new class {
            public function __construct() { return 1; }
        };
    }
    public function unrelated() {
        $x = 2;
        return $x;
    }
}
PHP;
        $this->assertSame([5], ConstructorReturnScanner::scan($source));
    }

    #[Test]
    public function emptyNestedConstructorLeaksNothing(): void
    {
        // Variant with an EMPTY nested constructor body - no semicolon
        // inside it to rescue a leaked pending flag. The method return
        // after the outer constructor must stay unreported.
        $source = <<<'PHP'
<?php
class Outer {
    public function __construct() {
        $x = new class { public function __construct() {} };
    }
    public function unrelated() {
        $y = 3;
        return $y;
    }
}
PHP;
        $this->assertSame([], ConstructorReturnScanner::scan($source));
    }
}
