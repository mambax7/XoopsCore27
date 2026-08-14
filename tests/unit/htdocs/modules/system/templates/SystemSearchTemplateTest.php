<?php
/**
 * Unit tests for modules/system/templates/system_search.tpl
 *
 * Guards the show-all control flow. The block was unreachable in both
 * directions between 97d9d6a3 (2023) and this suite: it opened on
 * `isset($nomatch) && $nomatch != true`, which is false when results exist
 * ($nomatch is never assigned) and false when they do not ($nomatch is true),
 * so neither the result list nor the no-match message could render.
 *
 * @copyright    2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package      Tests\Unit\System\Templates
 */

declare(strict_types=1);

namespace Tests\Unit\System\Templates;

require_once dirname(__DIR__) . '/SourceFileTestTrait.php';

use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

/**
 * Tests for system_search.tpl.
 *
 * These tests verify the template's control flow, the guards on optional
 * values assigned by search.php, and XOOPS Smarty delimiter usage.
 *
 * @category  Test
 * @package   Tests\Unit\System\Templates
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class SystemSearchTemplateTest extends TestCase
{
    use SourceFileTestTrait;

    /**
     * @var string Alias for sourceContent (kept for readability)
     */
    private string $templateContent;

    protected function setUp(): void
    {
        $this->loadSourceFile('htdocs/modules/system/templates/system_search.tpl');
        $this->templateContent = $this->sourceContent;
    }

    /**
     * Return the show-all block, i.e. everything from its opening guard on.
     *
     * search.php assigns showallbyuser for BOTH action=showall and
     * action=showallbyuser, so this one block serves both.
     */
    private function showAllBlock(): string
    {
        $start = strpos($this->templateContent, '<{if !empty($showallbyuser)}>');
        $this->assertNotFalse($start, 'Template should contain the show-all block');

        return substr($this->templateContent, $start);
    }

    // =========================================================================
    // Show-all control flow (issue #161)
    // =========================================================================

    /**
     * Verify that the show-all block opens on an empty-state test.
     *
     * search.php leaves $nomatch unassigned when rows were found and assigns
     * boolean true when none were, so empty() is the test that distinguishes
     * them. It also covers the results branch, where $nomatch is a string.
     */
    public function testShowAllBlockOpensOnEmptyNomatch(): void
    {
        $this->assertStringContainsString(
            '<{if empty($nomatch)}>',
            $this->showAllBlock(),
            'Show-all results should render when $nomatch is empty'
        );
    }

    /**
     * Verify that the unsatisfiable guard has not come back.
     *
     * This is the regression sentinel: no request can satisfy
     * isset($nomatch) && $nomatch != true.
     */
    public function testDoesNotUseTheUnsatisfiableNomatchGuard(): void
    {
        $this->assertStringNotContainsString(
            'isset($nomatch) &&',
            $this->templateContent,
            'The show-all guard must not require $nomatch to be both set and not true'
        );

        $this->assertStringNotContainsString(
            '$nomatch != true',
            $this->templateContent,
            'Comparing $nomatch against true is the shape of the unsatisfiable guard'
        );
    }

    /**
     * Verify that the no-match message is a sibling of the results, not a child.
     *
     * Nested inside the results branch it could never render, because that
     * branch requires $nomatch to be absent.
     */
    public function testNoMatchMessageIsSiblingOfTheResults(): void
    {
        $block = $this->showAllBlock();

        $guard   = strpos($block, '<{if empty($nomatch)}>');
        $else    = strpos($block, '<{else}>');
        $nomatch = strpos($block, '_SR_NOMATCH');

        $this->assertNotFalse($guard, 'Show-all block should open on empty($nomatch)');
        $this->assertNotFalse($else, 'Show-all block should have an else branch');
        $this->assertNotFalse($nomatch, 'Show-all block should render _SR_NOMATCH');

        $this->assertGreaterThan($guard, $else, 'The else branch belongs after the guard');
        $this->assertGreaterThan(
            $else,
            $nomatch,
            '_SR_NOMATCH belongs in the else branch, not inside the results branch'
        );
    }

    // =========================================================================
    // Guards on values search.php assigns conditionally
    // =========================================================================

    /**
     * Verify that optional row fields are guarded.
     *
     * search.php assigns uname only when the module's row carried a uid, and
     * time only when it carried a timestamp.
     */
    public function testOptionalRowFieldsAreGuarded(): void
    {
        $block = $this->showAllBlock();

        $this->assertStringContainsString(
            '<{if !empty($data.uname)}>',
            $block,
            'uname is optional and may be an empty string'
        );

        $this->assertStringContainsString(
            '<{if !empty($data.time)}>',
            $block,
            'time is optional'
        );
    }

    /**
     * Verify that the pagination guard tolerates unassigned links.
     *
     * Neither previous nor next is assigned on a single page of results, and
     * a bare truth test on an unassigned variable warns under Smarty's
     * compiled tpl_vars access.
     */
    public function testPaginationGuardToleratesUnassignedLinks(): void
    {
        $this->assertStringContainsString(
            '<{if !empty($previous) || !empty($next)}>',
            $this->showAllBlock(),
            'Pagination should be guarded with empty() checks on both links'
        );
    }

    /**
     * Verify that the keyword echo is guarded.
     *
     * showall assigns $showall; showallbyuser does not.
     */
    public function testKeywordEchoIsGuarded(): void
    {
        $this->assertStringContainsString(
            '<{if !empty($showall)}>',
            $this->showAllBlock(),
            'The keyword line is only for action=showall'
        );
    }

    // =========================================================================
    // Results block (action=results) — must stay as it was
    // =========================================================================

    /**
     * Verify that the results block still switches on its own $nomatch.
     *
     * That branch receives _SR_NOMATCH as a string, not boolean true.
     */
    public function testResultsBlockStillGuardsItsOwnNomatch(): void
    {
        $this->assertStringContainsString(
            '<{if !empty($nomatch)}>',
            $this->templateContent,
            'The results block should still render the no-match string it is given'
        );
    }

    // =========================================================================
    // Template conventions
    // =========================================================================

    /**
     * Verify that the template uses the XOOPS Smarty delimiters.
     */
    public function testUsesXoopsSmartyDelimiters(): void
    {
        $this->assertMatchesRegularExpression(
            '/<\{[^}]+\}>/',
            $this->templateContent,
            'Template should use Smarty delimiters <{ }>'
        );

        $this->assertSame(
            0,
            preg_match('/(?<![<$])\{(?:if|else|foreach|\/if|\/foreach)\b/', $this->templateContent),
            'Template must not use bare Smarty delimiters'
        );
    }
}
