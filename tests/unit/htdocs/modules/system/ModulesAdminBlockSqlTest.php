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

namespace modulessystem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Block SQL in xoops_module_update() (modules/system/admin/modulesadmin/modulesadmin.php).
 *
 * The block-rebuild loop wrote the block template name into an UPDATE and an
 * INSERT on the newblocks table without quoting it, and escaped the other
 * strings with addslashes() rather than the connection's quote(). When the
 * INSERT failed it echoed the whole statement onto the admin page.
 *
 * xoops_module_update() needs the full kernel, so it cannot run under the unit
 * bootstrap; these source pins cover the three statements instead.
 *
 * @category  Xoops
 * @package   System
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ModulesAdminBlockSqlTest extends TestCase
{
    private static function updateFunctionSource(): string
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/modulesadmin/modulesadmin.php');
        self::assertNotFalse($src);

        $start = strpos($src, 'function xoops_module_update(');
        $end   = strpos($src, 'function xoops_module_activate(');
        self::assertNotFalse($start, 'xoops_module_update() not found');
        self::assertNotFalse($end, 'xoops_module_activate() not found');

        return substr($src, $start, $end - $start);
    }

    #[Test]
    public function templateNameIsQuotedInUpdateAndInsert(): void
    {
        $body = self::updateFunctionSource();

        self::assertSame(
            0,
            preg_match('/[\'"]\s*\.\s*\$template\s*\.\s*[\'"]/', $body),
            'The block template name is still concatenated into SQL unquoted.'
        );
        self::assertSame(
            2,
            preg_match_all('/\$xoopsDB->quote\(\s*\(string\)\s*\$template\s*\)/', $body),
            'Both the UPDATE and the INSERT must pass the template name through $xoopsDB->quote().'
        );
    }

    #[Test]
    public function blockStringsUseTheConnectionQuoteNotAddslashes(): void
    {
        self::assertSame(
            0,
            substr_count(self::updateFunctionSource(), 'addslashes('),
            'xoops_module_update() should quote strings with $xoopsDB->quote().'
        );
    }

    #[Test]
    public function failedInsertDoesNotEchoTheStatement(): void
    {
        self::assertSame(
            0,
            preg_match('/echo\s+\$sql\s*;/', self::updateFunctionSource()),
            'A failed block INSERT must not print the SQL statement on the admin page.'
        );
    }

    /**
     * The SELECT, UPDATE and INSERT on newblocks in the block-rebuild loop,
     * from "$sql = '..." to the terminating semicolon.
     *
     * @return array<string, string>
     */
    private static function blockStatements(): array
    {
        $body = self::updateFunctionSource();
        $found = preg_match_all(
            '/\$sql\s*=\s*\'(SELECT bid, name FROM|UPDATE|INSERT INTO) \'\s*\.\s*\$xoopsDB->prefix\(\'newblocks\'\).*?;/s',
            $body,
            $m,
            PREG_SET_ORDER
        );
        self::assertSame(3, $found, 'Expected exactly one SELECT, UPDATE and INSERT on newblocks.');

        $statements = [];
        foreach ($m as [$statement, $verb]) {
            $statements[explode(' ', $verb)[0]] = $statement;
        }
        self::assertSame(['SELECT', 'UPDATE', 'INSERT'], array_keys($statements));

        return $statements;
    }

    /** @return array<string, array{string, string}> */
    public static function quotedValues(): array
    {
        return [
            'SELECT show_func' => ['SELECT', "\$block['show_func']"],
            'SELECT func_file' => ['SELECT', "\$block['file']"],
            'UPDATE name'      => ['UPDATE', "\$block['name']"],
            'UPDATE edit_func' => ['UPDATE', '$editfunc'],
            'UPDATE template'  => ['UPDATE', '$template'],
            'INSERT options'   => ['INSERT', '$options'],
            'INSERT dirname'   => ['INSERT', '$dirname'],
            'INSERT func_file' => ['INSERT', "\$block['file']"],
            'INSERT show_func' => ['INSERT', "\$block['show_func']"],
            'INSERT edit_func' => ['INSERT', '$editfunc'],
            'INSERT template'  => ['INSERT', '$template'],
        ];
    }

    #[Test]
    #[DataProvider('quotedValues')]
    public function eachStringValueIsQuoted(string $statement, string $value): void
    {
        self::assertStringContainsString(
            '$xoopsDB->quote((string) ' . $value . ')',
            self::blockStatements()[$statement],
            "$value is not passed through \$xoopsDB->quote() in the $statement."
        );
    }

    #[Test]
    public function insertNameAndTitleUseTheQuotedBlockName(): void
    {
        $body = self::updateFunctionSource();

        self::assertSame(
            1,
            preg_match('/\$block_name\s*=\s*\$xoopsDB->quote\(\s*\(string\)\s*\$block\[\'name\'\]\s*\);/', $body),
            '$block_name must hold the quoted block name.'
        );
        self::assertSame(
            1,
            preg_match('/\$block_type\s*=\s*\([^;]*\)\s*\?\s*\'S\'\s*:\s*\'M\'\s*;/', $body),
            "\$block_type must stay the constant 'S' or 'M'."
        );
    }

    /**
     * Catches any value added to these statements later without quoting:
     * once the allowed forms are removed, no PHP variable may remain.
     *
     * @return array<string, array{string}>
     */
    public static function statementNames(): array
    {
        return ['SELECT' => ['SELECT'], 'UPDATE' => ['UPDATE'], 'INSERT' => ['INSERT']];
    }

    #[Test]
    #[DataProvider('statementNames')]
    public function noValueIsConcatenatedRaw(string $statement): void
    {
        $expr = '\$\w+(?:\[[^\]]+\]|->\w+\([^)]*\))?';
        $rest = preg_replace(
            [
                '/\$xoopsDB->prefix\(\'newblocks\'\)/',
                '/\$xoopsDB->quote\(\(string\) ' . $expr . '\)/',
                '/\(int\) ' . $expr . '/',
                '/\btime\(\)/',
                '/\{\$block_type\}/',
                '/\$block_name\b/',
                '/^\$sql\b/',
            ],
            'X',
            self::blockStatements()[$statement]
        );

        self::assertSame(
            0,
            preg_match_all('/\$\w+/', (string) $rest, $raw),
            "Raw value(s) in the $statement: " . implode(', ', $raw[0] ?? [])
        );
    }
}
