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
 * be included from a unit test.
 */
final class SearchShowAllGuardTest extends TestCase
{
    private function showAllBranch(): string
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/search.php');
        self::assertNotFalse($src, 'search.php should be readable');

        $start = strpos($src, "case 'showall':");
        self::assertNotFalse($start, 'search.php should have a showall branch');

        return substr($src, $start);
    }

    #[Test]
    public function showAllChecksTheModuleBeforeCallingSearch(): void
    {
        $branch = $this->showAllBranch();

        $guard  = strpos($branch, '!is_object($module)');
        $search = strpos($branch, '$module->search(');

        self::assertNotFalse($guard, 'The show-all branch should reject a missing module');
        self::assertNotFalse($search, 'The show-all branch should call the module search');
        self::assertGreaterThan($guard, $search, 'search() must not run before the guard');
    }

    #[Test]
    public function showAllAppliesTheSameCriteriaAsTheResultsBranch(): void
    {
        $branch = $this->showAllBranch();

        // module_read, plus the isactive and hassearch columns the results
        // branch selects on. Dropping any one of them reopens a route the
        // results branch does not have.
        //
        // Matched loosely: these pin which checks run, not how they are spelled.
        // The two variable names are pinned deliberately -- the permission list
        // is the behaviour under test -- but whitespace and line breaks are not.
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
        $branch = $this->showAllBranch();

        $guard  = strpos($branch, 'redirect_header(');
        $header = strpos($branch, "path('header.php')");

        self::assertNotFalse($guard, 'The show-all branch should reject invalid requests');
        self::assertNotFalse($header, 'The show-all branch should include the page header');
        self::assertGreaterThan($guard, $header, 'header.php must be included after the guard');
    }

    #[Test]
    public function showAllWritesTheRowUidBackToTheRow(): void
    {
        // The loop assigned $results['uid'], a stray key on the outer array,
        // leaving this row's uid exactly as the search plugin returned it.
        $src = file_get_contents(XOOPS_ROOT_PATH . '/search.php');
        self::assertNotFalse($src);
        self::assertSame(
            0,
            preg_match("/\\\$results\\['uid'\\]\s*=/", $src),
            'uid belongs to the row: $results[$i][\'uid\'], never $results[\'uid\']'
        );
    }

    #[Test]
    public function theRowNormaliserCastsTheUid(): void
    {
        // Both branches test and render uid as an integer, so the shared row
        // normaliser owns the cast rather than each branch repeating it.
        $src = file_get_contents(XOOPS_ROOT_PATH . '/search.php');
        self::assertNotFalse($src);
        self::assertMatchesRegularExpression(
            "/\\\$row\\['uid'\\]\s*=[^;]*\(int\)/s",
            $src,
            'the row normaliser should cast uid to an integer'
        );
    }

    #[Test]
    public function resultsBranchChecksModuleReadStrictly(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/search.php');
        self::assertNotFalse($src);

        $start = strpos($src, "case 'results':");
        $end   = strpos($src, "case 'showall':");
        self::assertNotFalse($start, 'search.php should have a results branch');
        self::assertNotFalse($end, 'search.php should have a showall branch');

        self::assertMatchesRegularExpression(
            '/in_array\(.*\$mid.*,\s*\$available_modules\s*,\s*true\s*\)/s',
            substr($src, $start, $end - $start),
            'the results branch should compare module ids strictly, as show-all does'
        );
    }

    #[Test]
    public function resultsBranchSkipsModulesMissingFromTheSearchableSet(): void
    {
        // $mids arrives from the query string and $available_modules is only a
        // module_read grant list, so a readable but non-searchable id indexes a
        // key $modules does not have.
        $src = file_get_contents(XOOPS_ROOT_PATH . '/search.php');
        self::assertNotFalse($src);
        self::assertMatchesRegularExpression(
            '/!isset\(\$modules\[\$mid\]\)\s*\|\|\s*!is_object\(\$modules\[\$mid\]\)/',
            $src,
            'The results branch should skip ids missing from the searchable module set'
        );
    }
}
