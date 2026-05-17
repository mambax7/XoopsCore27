<?php
/**
 * Regression tests for modules/system/admin/blocksadmin/main.php — issue #73.
 *
 * Cloning a module block submits bid=0, so the save handler creates a
 * fresh empty block. Before the fix it never read the module metadata
 * back from the hidden clone-form fields, so the clone failed the
 * not-null name validation and lost its module association.
 *
 * blocksadmin/main.php is a procedural request handler (redirects, CSRF,
 * globals) and cannot be executed in isolation, so — like ModulesAdminTest
 * — this verifies the fix by static analysis of the source.
 *
 * @copyright    2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package      Tests\Unit\System\BlocksAdmin
 */

declare(strict_types=1);

namespace Tests\Unit\System\BlocksAdmin;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SourceFileTestTrait.php';

use Tests\Unit\System\SourceFileTestTrait;

class BlocksAdminCloneTest extends TestCase
{
    use SourceFileTestTrait;

    /** Module-binding fields the clone form round-trips as hidden inputs. */
    private const CLONE_FIELDS = [
        'mid',
        'func_num',
        'func_file',
        'show_func',
        'edit_func',
        'template',
        'dirname',
        'name',
    ];

    protected function setUp(): void
    {
        $this->loadSourceFile('htdocs/modules/system/admin/blocksadmin/main.php');
    }

    /**
     * Byte offset of the save handler's clone branch. Anchored on the
     * unique "get($block_id); } else { ... create();" shape so the inner
     * c_type switch (case 'H'/default) can't throw off detection.
     */
    private function cloneBranchOffset(): int
    {
        $matched = preg_match(
            '/\$block_handler->get\(\$block_id\);\s*\}\s*else\s*\{\s*\$block\s*=\s*\$block_handler->create\(\);/',
            $this->sourceContent,
            $m,
            PREG_OFFSET_CAPTURE
        );
        self::assertSame(
            1,
            $matched,
            "save handler must branch: bid>0 -> get(\$block_id), else create()"
        );

        return $m[0][1];
    }

    /**
     * Capture the clone-branch hydration region: from the clone create()
     * through the end of the $block->setVars([...]); call. This spans both
     * the $clone_* = Request::get*() reads and the setVars() array, so it
     * works whether a field is read inline or via a local. Window-free, so
     * it cannot under/over-read like a fixed substring length would.
     */
    private function cloneHydrationRegion(): string
    {
        $matched = preg_match(
            '/\$block_handler->get\(\$block_id\);\s*\}\s*else\s*\{\s*'
            . '\$block\s*=\s*\$block_handler->create\(\);'
            . '(.*?\$block->setVars\(\[.*?\]\);)/s',
            $this->sourceContent,
            $m
        );
        self::assertSame(
            1,
            $matched,
            'clone branch must hydrate the new block via $block->setVars([...])'
        );

        return $m[1];
    }

    /**
     * The clone branch (bid == 0 -> create()) must hydrate every
     * module-binding field from POST — this is the #73 fix.
     */
    public function testCloneBranchHydratesModuleFieldsFromPost(): void
    {
        $region = $this->cloneHydrationRegion();

        foreach (self::CLONE_FIELDS as $field) {
            self::assertMatchesRegularExpression(
                "/Request::get\w+\(\s*'" . preg_quote($field, '/') . "'.*'POST'\s*\)/",
                $region,
                "Clone branch must read '{$field}' from POST so the cloned block keeps its module binding (issue #73)"
            );
        }
    }

    /**
     * Hardening (PR #77 review): the clone branch must reject tampered
     * hidden inputs before persisting, since dirname/func_file/template
     * feed include_once paths and show/edit_func are called as functions.
     */
    public function testCloneBranchRejectsTamperedPathAndFunctionFields(): void
    {
        $region = $this->cloneHydrationRegion();

        // Path-traversal rejection on the path-building fields.
        self::assertStringContainsString(
            "'..'",
            $region,
            'Clone branch must reject ".." in dirname/func_file/template'
        );
        self::assertStringContainsString(
            "redirect_header('admin.php?fct=blocksadmin'",
            $region,
            'A tampered clone must be rejected via redirect_header(), not persisted'
        );
        // Function-name allowlist for show_func / edit_func.
        self::assertStringContainsString(
            '^[A-Za-z_]\w*$',
            $region,
            'show_func/edit_func must be validated against a PHP-identifier allowlist'
        );
    }

    /**
     * Failure mode #1: a cloned block must carry a name before insert(),
     * otherwise the not-null DB validation rejects it.
     */
    public function testCloneHydratesNameBeforeInsert(): void
    {
        $cloneOffset = $this->cloneBranchOffset();

        $nameRead = strpos($this->sourceContent, "Request::getString('name'", $cloneOffset);
        self::assertNotFalse($nameRead, "clone branch must read 'name' from POST");

        $insertPos = strpos($this->sourceContent, '$block_handler->insert(', $cloneOffset);
        self::assertNotFalse($insertPos, 'save handler must insert() the block');

        self::assertLessThan(
            $insertPos,
            $nameRead,
            "'name' must be hydrated before insert() so a clone passes name validation (issue #73)"
        );
    }

    /**
     * Regression guard: the normal edit path (bid > 0) must still load
     * the block from the handler, unchanged by the fix.
     */
    public function testEditPathStillLoadsViaHandlerGet(): void
    {
        self::assertMatchesRegularExpression(
            '/if \(\$block_id > 0\) \{\s*\$block\s*=\s*\$block_handler->get\(\$block_id\);/',
            $this->sourceContent,
            'Edit path (bid > 0) must still load the existing block via $block_handler->get()'
        );
    }
}
