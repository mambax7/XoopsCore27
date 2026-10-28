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

namespace install;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for xoInstallerRewriteDefines(), the define() rewriter that
 * writes mainfile.php and secure.php from their .dist templates.
 *
 * Every value must come back out of the generated PHP exactly as it went in,
 * and the generated line must hold nothing but the define() call: a quote or
 * backslash in a value must never end the string literal.
 *
 * @category  Xoops
 * @package   Installer
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversFunction('xoInstallerRewriteDefines')]
class InstallerRewriteDefinesTest extends TestCase
{
    private const TEMPLATE = "<?php\ndefine('XOOPS_DB_PASS', '');\ndefine(\"XOOPS_DB_PCONNECT\", 0);\n";

    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/install/include/functions.php';
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostileValues(): array
    {
        $bs = chr(92);

        return [
            'plain'                    => ['s3cret'],
            'single quote'             => ["it's"],
            'backslash then quote'     => ['x' . $bs . "');system('id');//"],
            'double backslash quote'   => ['x' . $bs . $bs . "');//"],
            'trailing backslash'       => ['pass' . $bs],
            'dollar backreference'     => ['a$1b${2}c'],
            'backslash backreference'  => ['a' . $bs . '1b' . $bs . $bs . '0'],
            'php open tag and newline' => ["a\n?><?php echo 1;"],
        ];
    }

    #[Test]
    #[DataProvider('hostileValues')]
    public function stringValueRoundTripsAsOneLiteral(string $value): void
    {
        $out = xoInstallerRewriteDefines(self::TEMPLATE, ['DB_PASS' => $value]);

        $this->assertSame($value, $this->literalOf($out, 'XOOPS_DB_PASS'));
    }

    #[Test]
    public function aValueWrittenForOneDefineIsNotRewrittenByTheNext(): void
    {
        $template = "<?php\ndefine('XOOPS_DB_PASS', '');\ndefine('XOOPS_DB_NAME', '');\n";
        $pass     = "a');define(\"XOOPS_DB_NAME\", \"x\");//";

        $out = xoInstallerRewriteDefines($template, ['DB_PASS' => $pass, 'DB_NAME' => 'xoops']);

        $this->assertSame($pass, $this->literalOf($out, 'XOOPS_DB_PASS'));
        $this->assertSame('xoops', $this->literalOf($out, 'XOOPS_DB_NAME'));
    }

    #[Test]
    public function stringValueLeavesAnIntegerDefineAlone(): void
    {
        $this->assertSame(self::TEMPLATE, xoInstallerRewriteDefines(self::TEMPLATE, ['DB_PCONNECT' => '1']));
    }

    #[Test]
    public function integerValueReplacesIntegerDefine(): void
    {
        $out = xoInstallerRewriteDefines(self::TEMPLATE, ['DB_PCONNECT' => 1]);

        $this->assertStringContainsString("define('XOOPS_DB_PCONNECT', 1)", $out);
    }

    #[Test]
    public function unknownKeyLeavesContentUnchanged(): void
    {
        $this->assertSame(self::TEMPLATE, xoInstallerRewriteDefines(self::TEMPLATE, ['NOPE' => 'x']));
    }

    /**
     * Tokenise the generated file and return the decoded string literal of
     * the named define, failing if anything but define(NAME, LITERAL);
     * appears on that statement.
     */
    private function literalOf(string $php, string $name): string
    {
        $tokens = array_values(array_filter(
            token_get_all($php),
            static fn($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_OPEN_TAG], true)
        ));
        $quotedName = var_export($name, true);
        foreach ($tokens as $i => $t) {
            if (is_array($t) && $t[1] === $quotedName) {
                $this->assertSame('(', $tokens[$i - 1]);
                $this->assertSame(',', $tokens[$i + 1]);
                $this->assertIsArray($tokens[$i + 2]);
                $this->assertSame(T_CONSTANT_ENCAPSED_STRING, $tokens[$i + 2][0]);
                $this->assertSame(')', $tokens[$i + 3]);
                $this->assertSame(';', $tokens[$i + 4]);
                $literal = substr($tokens[$i + 2][1], 1, -1);

                return (string) preg_replace_callback("/\\\\([\\\\'])/", static fn(array $m): string => $m[1], $literal);
            }
        }
        $this->fail("define for {$name} not found");
    }
}
