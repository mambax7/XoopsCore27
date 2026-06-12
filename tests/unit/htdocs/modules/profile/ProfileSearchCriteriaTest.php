<?php

declare(strict_types=1);

namespace modulesprofile;

use Criteria;
use CriteriaCompo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the active-user filter in
 * htdocs/modules/profile/search.php (issue A2-M-5).
 *
 * The username branch used to reassign $criteria to a bare Criteria, which
 * (a) discarded the base CriteriaCompo(level > 0) active-user filter so
 * inactive/unconfirmed accounts leaked into search results, and (b) left
 * $criteria without an add() method, so a username + email/profile-field
 * search fataled on the later $criteria->add() calls.
 *
 * search.php is a procedural script driven by global bootstrap side effects,
 * so — following the sibling ProfileVisibilityCsrfTest — these tests pin both
 * the Criteria contract the fix relies on and the source shape of the fix
 * itself.
 *
 * @see \Criteria
 * @see \CriteriaCompo
 */
class ProfileSearchCriteriaTest extends TestCase
{
    /** @var \XoopsTestStubDatabase */
    private $db;

    protected function setUp(): void
    {
        $this->db = $GLOBALS['xoopsDB'];
    }

    private function readSearchSource(): string
    {
        $path   = XOOPS_ROOT_PATH . '/modules/profile/search.php';
        $source = file_get_contents($path);
        self::assertNotFalse($source, 'Unable to read ' . $path);

        return (string) $source;
    }

    // ---------------------------------------------------------------
    // Criteria contract the fix depends on
    // ---------------------------------------------------------------

    #[Test]
    public function usernameSearchKeepsActiveUserFilter(): void
    {
        // Mirror search.php: base active-user filter, then AND the username in.
        $criteria = new CriteriaCompo(new Criteria('level', 0, '>'));
        $criteria->add(new Criteria('uname', 'john%', 'LIKE'));

        $sql = $criteria->render($this->db);

        self::assertStringContainsString('`level` > 0', $sql, 'active-user filter must survive');
        self::assertStringContainsString("`uname` LIKE 'john%'", $sql);
        self::assertStringContainsString(' AND ', $sql, 'predicates must be AND-combined');
    }

    #[Test]
    public function usernamePlusEmailAndsAllPredicates(): void
    {
        // The combination that previously fataled (add() on a bare Criteria).
        $criteria = new CriteriaCompo(new Criteria('level', 0, '>'));
        $criteria->add(new Criteria('uname', 'john%', 'LIKE'));
        $criteria->add(new Criteria('email', '%@example.com', 'LIKE'));

        $sql = $criteria->render($this->db);

        self::assertStringContainsString('`level` > 0', $sql);
        self::assertStringContainsString("`uname` LIKE 'john%'", $sql);
        self::assertStringContainsString("`email` LIKE '%@example.com'", $sql);
    }

    // ---------------------------------------------------------------
    // Source shape of the fix
    // ---------------------------------------------------------------

    #[Test]
    public function searchSourceAddsUsernameRatherThanReassigningCriteria(): void
    {
        $source = $this->readSearchSource();

        self::assertStringContainsString(
            "\$criteria->add(new Criteria('uname'",
            $source,
            'username must be AND-ed into the compound criteria'
        );
        self::assertStringNotContainsString(
            "\$criteria = new Criteria('uname'",
            $source,
            'reassigning $criteria drops the level > 0 active-user filter (A2-M-5 regression)'
        );
    }
}
