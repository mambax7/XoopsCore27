<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * search.php must validate the requested module before it does anything on
 * behalf of the request. The show-all branch took $mid straight from the query
 * string, so an unknown id reached XoopsModule::search() on false, and the
 * branch never consulted module_read at all (issue #162, fixed in 5b96e311a).
 *
 * Source contracts, like BrowsePathContainmentTest: search.php is a front
 * controller that boots through mainfile.php and renders a page, so it cannot
 * be included from a unit test and its behaviour cannot be observed directly.
 *
 * Two consequences shape the assertions below. Patterns are matched with
 * whitespace- and newline-tolerant regexes, so reformatting does not fail a
 * test. Ordering is asserted by position, because "validate before rendering"
 * is itself an ordering property: there is nothing else to compare when the
 * file cannot be run. assertRunsBefore() keeps that intent readable.
 */
final class SearchShowAllGuardTest extends TestCase
{
    private function source(): string
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/search.php');
        self::assertNotFalse($src, 'search.php should be readable');

        return $src;
    }

    /**
     * The code of one switch branch, from its case label to $until's label.
     */
    private function branch(string $from, ?string $until = null): string
    {
        $src   = $this->source();
        $start = strpos($src, "case '{$from}':");
        self::assertNotFalse($start, "search.php should have a {$from} branch");

        if (null === $until) {
            return substr($src, $start);
        }

        $end = strpos($src, "case '{$until}':", $start);
        self::assertNotFalse($end, "search.php should have a {$until} branch");

        return substr($src, $start, $end - $start);
    }

    private function assertRunsBefore(string $earlier, string $later, string $code, string $message): void
    {
        $first  = strpos($code, $earlier);
        $second = strpos($code, $later);

        self::assertNotFalse($first, sprintf('expected to find %s', $earlier));
        self::assertNotFalse($second, sprintf('expected to find %s', $later));
        self::assertGreaterThan($first, $second, $message);
    }

    #[Test]
    public function showAllChecksTheModuleBeforeCallingSearch(): void
    {
        $this->assertRunsBefore(
            '!is_object($module)',
            '$module->search(',
            $this->branch('showall'),
            'search() must not run before the guard'
        );
    }

    #[Test]
    public function showAllAppliesTheSameCriteriaAsTheResultsBranch(): void
    {
        $branch = $this->branch('showall');

        // module_read, plus the isactive and hassearch columns the results
        // branch selects on. Dropping any one of them reopens a route the
        // results branch does not have. The two variable names are pinned
        // deliberately -- the permission list is the behaviour under test.
        self::assertMatchesRegularExpression(
            '/in_array\(.*\$mid.*,\s*\$available_modules\s*,\s*true\s*\)/s',
            $branch,
            'module_read must be checked strictly'
        );
        self::assertMatchesRegularExpression(
            "/getVar\(\s*'isactive'\s*\)/",
            $branch,
            'a deactivated module must be rejected'
        );
        self::assertMatchesRegularExpression(
            "/getVar\(\s*'hassearch'\s*\)/",
            $branch,
            'a module without search support must be rejected'
        );
    }

    #[Test]
    public function showAllRendersThePageHeaderOnlyAfterTheGuard(): void
    {
        // header.php builds the theme and fires the core.header.* events; a
        // rejected request would discard all of it, and redirect_header() then
        // builds a second theme to render the redirect.
        $this->assertRunsBefore(
            'redirect_header(',
            "path('header.php')",
            $this->branch('showall'),
            'header.php must be included after the guard'
        );
    }

    #[Test]
    public function showAllWritesTheRowUidBackToTheRow(): void
    {
        // The loop assigned $results['uid'], a stray key on the outer array,
        // leaving this row's uid exactly as the search plugin returned it.
        self::assertSame(
            0,
            preg_match("/\\\$results\\['uid'\\]\s*=/", $this->source()),
            'uid belongs to the row: $results[$i][\'uid\'], never $results[\'uid\']'
        );
    }

    #[Test]
    public function theRowNormaliserCastsTheUid(): void
    {
        // Both branches test and render uid as an integer, so the shared row
        // normaliser owns the cast rather than each branch repeating it.
        self::assertMatchesRegularExpression(
            "/\\\$row\\['uid'\\]\s*=[^;]*\(int\)/s",
            $this->source(),
            'the row normaliser should cast uid to an integer'
        );
    }

    #[Test]
    public function theRowNormaliserCastsTheRequiredFieldsToStrings(): void
    {
        // link is pattern-matched and concatenated, title is escaped; neither
        // rendering path should have to rely on PHP coercing a module's int or
        // bool at the point of use.
        $src = $this->source();

        self::assertMatchesRegularExpression(
            "/\\\$row\\['link'\\]\s*=\s*\(string\)/",
            $src,
            'the row normaliser should cast link to a string'
        );
        self::assertMatchesRegularExpression(
            "/\\\$row\\['title'\\]\s*=\s*\(string\)/",
            $src,
            'the row normaliser should cast title to a string'
        );
    }

    #[Test]
    public function resultsBranchChecksModuleReadStrictly(): void
    {
        self::assertMatchesRegularExpression(
            '/in_array\(.*\$mid.*,\s*\$available_modules\s*,\s*true\s*\)/s',
            $this->branch('results', 'showall'),
            'the results branch should compare module ids strictly, as show-all does'
        );
    }

    #[Test]
    public function resultsBranchSkipsModulesMissingFromTheSearchableSet(): void
    {
        // $mids arrives from the query string and $available_modules is only a
        // module_read grant list, so a readable but non-searchable id indexes a
        // key $modules does not have.
        self::assertMatchesRegularExpression(
            '/!isset\(\$modules\[\$mid\]\)\s*\|\|\s*!is_object\(\$modules\[\$mid\]\)/',
            $this->branch('results', 'showall'),
            'The results branch should skip ids missing from the searchable module set'
        );
    }
}
