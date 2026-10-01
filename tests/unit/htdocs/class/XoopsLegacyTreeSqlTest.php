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

namespace xoopsclass;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Records every SQL statement. Result rows are served from two queues (one
 * for fetchRow(), one for fetchArray()); once a queue is empty the result set
 * is empty, so a seeded row exercises exactly one level of recursion.
 *
 * @category  Xoops
 * @package   core
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class RecordingLegacyTreeDatabase extends \XoopsTestStubDatabase
{
    /** @var list<string> */
    public array $queries = [];

    /** @var list<array<int, mixed>> */
    public array $rowQueue = [];

    /** @var list<array<string, mixed>> */
    public array $arrayQueue = [];

    public function query(string $sql, ?int $limit = null, ?int $start = null)
    {
        $this->queries[] = $sql;

        return 'recorded-result';
    }

    public function isResultSet($result)
    {
        return 'recorded-result' === $result;
    }

    public function fetchRow($result)
    {
        return array_shift($this->rowQueue) ?? false;
    }

    public function fetchArray($result)
    {
        return array_shift($this->arrayQueue) ?? false;
    }

    public function getRowsNum($result)
    {
        return count($this->rowQueue) + count($this->arrayQueue);
    }
}

/**
 * SQL built by the deprecated XoopsTopic and XoopsTree helpers (Snyk CWE-89 on
 * class/xoopstopic.php). They are still shipped and still callable by modules:
 *
 *  - XoopsTopic::topicExists() put the topic title into SQL unquoted;
 *  - XoopsTree accepted any $order string as a raw ORDER BY clause and any
 *    $title string as a raw column in the SELECT list.
 *
 * Contract pinned here:
 *  - topicExists() quotes the title with the connection's quote();
 *  - $order is kept only when it is a string holding a comma-separated list
 *    of column names (optionally table-qualified or backtick-quoted) with
 *    optional ASC/DESC; anything else, including a non-string, drops the
 *    ORDER BY and raises one E_USER_WARNING, also across recursion;
 *  - $title must be a plain column name; otherwise no query runs, the method
 *    returns what it would return for "no rows" and raises an E_USER_WARNING.
 *
 * @category  Xoops
 * @package   core
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsLegacyTreeSqlTest extends TestCase
{
    private RecordingLegacyTreeDatabase $db;

    private bool $hadLogger = false;

    private mixed $savedLogger = null;

    /** @var list<string> */
    private array $warnings = [];

    protected function setUp(): void
    {
        $this->hadLogger   = array_key_exists('xoopsLogger', $GLOBALS);
        $this->savedLogger = $GLOBALS['xoopsLogger'] ?? null;
        // Both files log their own deprecation through the global logger. A
        // stub keeps the real XoopsLogger (which installs error and exception
        // handlers on first use) out of this test.
        $GLOBALS['xoopsLogger'] = new class {
            public function addDeprecated($message): void
            {
            }
        };
        require_once XOOPS_ROOT_PATH . '/class/xoopstopic.php'; // also loads class/xoopstree.php

        $this->db       = new RecordingLegacyTreeDatabase();
        $this->warnings = [];
        set_error_handler(function (int $errno, string $errstr): bool {
            $this->warnings[] = $errstr;

            return true;
        }, E_USER_WARNING);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        if ($this->hadLogger) {
            $GLOBALS['xoopsLogger'] = $this->savedLogger;
        } else {
            unset($GLOBALS['xoopsLogger']);
        }
    }

    /**
     * Built without the constructor: the constructors only fetch the shared
     * connection (whose first use initialises process-wide state) and log a
     * deprecation, neither of which this test is about.
     */
    private function tree(): \XoopsTree
    {
        $tree        = (new \ReflectionClass(\XoopsTree::class))->newInstanceWithoutConstructor();
        $tree->db    = $this->db;
        $tree->table = 'xoops_topics';
        $tree->id    = 'topic_id';
        $tree->pid   = 'topic_pid';

        return $tree;
    }

    private function topic(): \XoopsTopic
    {
        $topic        = (new \ReflectionClass(\XoopsTopic::class))->newInstanceWithoutConstructor();
        $topic->db    = $this->db;
        $topic->table = 'xoops_topics';

        return $topic;
    }

    // ---------------------------------------------------------------------
    // XoopsTopic::topicExists()
    // ---------------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function hostileTitles(): array
    {
        return [
            'quote break'           => ["x' OR '1'='1"],
            'comment tail'          => ["x' -- "],
            'backslash then quote'  => ["x\\' OR 1=1 #"],
            'surrounding whitespace' => ["  plain title  "],
        ];
    }

    #[Test]
    #[DataProvider('hostileTitles')]
    public function topicExistsQuotesTheTitle(string $title): void
    {
        $this->topic()->topicExists(3, $title);

        self::assertSame(
            'SELECT COUNT(*) from xoops_topics WHERE topic_pid = 3 AND topic_title = ' . $this->db->quote(trim($title)),
            $this->db->queries[0] ?? null
        );
    }

    // ---------------------------------------------------------------------
    // XoopsTree $order
    // ---------------------------------------------------------------------

    /**
     * Every XoopsTree method that appends $order, called with that order.
     *
     * @return array<string, array{\Closure(\XoopsTree, string): void}>
     */
    public static function orderedMethods(): array
    {
        return [
            'getFirstChild'     => [static function (\XoopsTree $t, string $o): void { $t->getFirstChild(1, $o); }],
            'getAllChildId'     => [static function (\XoopsTree $t, string $o): void { $t->getAllChildId(1, $o); }],
            'getAllParentId'    => [static function (\XoopsTree $t, string $o): void { $t->getAllParentId(1, $o); }],
            'getAllChild'       => [static function (\XoopsTree $t, string $o): void { $t->getAllChild(1, $o); }],
            'getChildTreeArray' => [static function (\XoopsTree $t, string $o): void { $t->getChildTreeArray(1, $o); }],
            'makeMySelBox'      => [static function (\XoopsTree $t, string $o): void {
                ob_start();
                try {
                    $t->makeMySelBox('topic_title', $o);
                } finally {
                    ob_end_clean();
                }
            }],
        ];
    }

    /** @return array<string, array{string}> */
    public static function safeOrders(): array
    {
        return [
            'single column'          => ['topic_title'],
            'direction'              => ['weight DESC'],
            'lower-case direction'   => ['weight asc'],
            'list'                   => ['weight DESC, topic_title ASC'],
            'qualified column'       => ['t.topic_title'],
            'backtick-quoted column' => ['`weight` DESC'],
            'quoted column needing quotes' => ['`display-name` DESC'],
            'qualified quoted column' => ['`t`.`display-name`'],
            'quoted column with dollar and dot' => ['`price$net` ASC, `v1.2`'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function unsafeOrders(): array
    {
        return [
            'stacked statement'  => ['topic_title; DROP TABLE xoops_users'],
            'subquery'           => ['(SELECT pass FROM xoops_users LIMIT 1)'],
            'function call'      => ['RAND()'],
            'comment'            => ['topic_title -- x'],
            'union'              => ['topic_title UNION SELECT 1'],
            'conditional'        => ['IF(1=1, topic_id, topic_title)'],
            'quote'              => ["topic_title'"],
            'trailing comma'     => ['topic_title,'],
            'numeric position'   => ['1'],
            'stray backtick in quoted name' => ['`a`b`'],
            'comma in quoted name'          => ['`a,b`'],
            'space in quoted name'          => ['`a b`'],
            'empty quoted name'             => ['``'],
            'control character in quoted name' => ["`a\x01b`"],
            'quoted name followed by comment'  => ['`weight`--'],
        ];
    }

    /** @return iterable<string, array{\Closure, string}> */
    public static function methodsWithSafeOrders(): iterable
    {
        foreach (self::orderedMethods() as $m => [$call]) {
            foreach (self::safeOrders() as $o => [$order]) {
                yield "$m / $o" => [$call, $order];
            }
        }
    }

    /** @return iterable<string, array{\Closure, string}> */
    public static function methodsWithUnsafeOrders(): iterable
    {
        foreach (self::orderedMethods() as $m => [$call]) {
            foreach (self::unsafeOrders() as $o => [$order]) {
                yield "$m / $o" => [$call, $order];
            }
        }
    }

    #[Test]
    #[DataProvider('methodsWithSafeOrders')]
    public function safeOrderIsAppended(\Closure $call, string $order): void
    {
        $call($this->tree(), $order);

        self::assertNotEmpty($this->db->queries);
        self::assertStringEndsWith(' ORDER BY ' . $order, $this->db->queries[0]);
        self::assertSame([], $this->warnings);
    }

    #[Test]
    #[DataProvider('methodsWithUnsafeOrders')]
    public function unsafeOrderIsDroppedWithAWarning(\Closure $call, string $order): void
    {
        $call($this->tree(), $order);

        self::assertNotEmpty($this->db->queries);
        self::assertStringNotContainsString('ORDER BY', $this->db->queries[0]);
        self::assertCount(1, $this->warnings);
        self::assertStringNotContainsString(XOOPS_ROOT_PATH, $this->warnings[0]);
    }

    #[Test]
    public function emptyOrderAddsNoClauseAndNoWarning(): void
    {
        $this->tree()->getFirstChild(1, '');

        self::assertStringNotContainsString('ORDER BY', $this->db->queries[0]);
        self::assertSame([], $this->warnings);
    }

    #[Test]
    public function nullOrderAddsNoClauseAndNoWarning(): void
    {
        $this->tree()->getFirstChild(1, null);

        self::assertStringNotContainsString('ORDER BY', $this->db->queries[0]);
        self::assertSame([], $this->warnings);
    }

    /** @return array<string, array{mixed}> */
    public static function nonStringOrders(): array
    {
        return [
            'empty array' => [[]],
            'array'       => [['topic_title']],
            'integer'     => [1],
            'true'        => [true],
            'object'      => [new \stdClass()],
        ];
    }

    #[Test]
    #[DataProvider('nonStringOrders')]
    public function nonStringOrderIsDroppedWithOneWarning(mixed $order): void
    {
        $this->tree()->getFirstChild(1, $order);

        self::assertStringNotContainsString('ORDER BY', $this->db->queries[0]);
        self::assertCount(1, $this->warnings);
    }

    // ---------------------------------------------------------------------
    // Recursion: the order is passed on only when it was accepted
    // ---------------------------------------------------------------------

    /**
     * Each recursive method, with one child row seeded so it recurses once.
     *
     * @return array<string, array{\Closure(\XoopsTree, string): void, string, array<int|string, mixed>}>
     */
    public static function recursiveMethods(): array
    {
        $arrayRow = ['topic_id' => 7, 'topic_pid' => 1, 'topic_title' => 'child'];

        return [
            'getAllChildId'     => [static function (\XoopsTree $t, string $o): void { $t->getAllChildId(1, $o); }, 'row', [7]],
            'getAllParentId'    => [static function (\XoopsTree $t, string $o): void { $t->getAllParentId(1, $o); }, 'row', [7]],
            'getAllChild'       => [static function (\XoopsTree $t, string $o): void { $t->getAllChild(1, $o); }, 'array', $arrayRow],
            'getChildTreeArray' => [static function (\XoopsTree $t, string $o): void { $t->getChildTreeArray(1, $o); }, 'array', $arrayRow],
            'makeMySelBox'      => [static function (\XoopsTree $t, string $o): void {
                ob_start();
                try {
                    $t->makeMySelBox('topic_title', $o);
                } finally {
                    ob_end_clean();
                }
            }, 'row', [7, 'top']],
        ];
    }

    /**
     * @param \Closure(\XoopsTree, string): void $call
     * @param array<int|string, mixed>          $row
     */
    private function runRecursive(\Closure $call, string $queue, array $row, string $order): void
    {
        if ('row' === $queue) {
            $this->db->rowQueue[] = $row;
        } else {
            $this->db->arrayQueue[] = $row;
        }
        $call($this->tree(), $order);
    }

    /**
     * @param \Closure(\XoopsTree, string): void $call
     * @param array<int|string, mixed>          $row
     */
    #[Test]
    #[DataProvider('recursiveMethods')]
    public function acceptedOrderIsPassedToTheRecursiveQuery(\Closure $call, string $queue, array $row): void
    {
        $this->runRecursive($call, $queue, $row, 'weight DESC');

        self::assertGreaterThanOrEqual(2, count($this->db->queries), 'the seeded row should trigger one recursive query');
        foreach ($this->db->queries as $sql) {
            self::assertStringEndsWith(' ORDER BY weight DESC', $sql);
        }
        self::assertSame([], $this->warnings);
    }

    /**
     * @param \Closure(\XoopsTree, string): void $call
     * @param array<int|string, mixed>          $row
     */
    #[Test]
    #[DataProvider('recursiveMethods')]
    public function rejectedOrderWarnsOnceAcrossRecursion(\Closure $call, string $queue, array $row): void
    {
        $this->runRecursive($call, $queue, $row, 'RAND()');

        self::assertGreaterThanOrEqual(2, count($this->db->queries), 'the seeded row should trigger one recursive query');
        foreach ($this->db->queries as $sql) {
            self::assertStringNotContainsString('ORDER BY', $sql);
        }
        self::assertCount(1, $this->warnings);
    }

    // ---------------------------------------------------------------------
    // XoopsTree $title (a column in the SELECT list)
    // ---------------------------------------------------------------------

    #[Test]
    public function safeTitleColumnIsSelected(): void
    {
        $this->tree()->getPathFromId(5, 'topic_title');

        self::assertSame('SELECT topic_pid, topic_title FROM xoops_topics WHERE topic_id=5', $this->db->queries[0]);
        self::assertSame([], $this->warnings);
    }

    /** @return array<string, array{\Closure(\XoopsTree, mixed): mixed, mixed}> */
    public static function titledMethods(): array
    {
        return [
            'getPathFromId'     => [static fn (\XoopsTree $t, mixed $c): mixed => $t->getPathFromId(5, $c, '/kept'), '/kept'],
            'getNicePathFromId' => [static fn (\XoopsTree $t, mixed $c): mixed => $t->getNicePathFromId(5, $c, 'index.php?x=1', '/kept'), '&nbsp;:&nbsp;/kept'],
            'makeMySelBox'      => [static function (\XoopsTree $t, mixed $c): mixed {
                ob_start();
                try {
                    $t->makeMySelBox($c);
                } finally {
                    $out = ob_get_clean();
                }

                return $out;
            }, ''],
        ];
    }

    /**
     * Not a plain column name. Qualified and backtick-quoted names are refused
     * too: makeMySelBox() reads the fetched row by $title, and rows are keyed
     * by the bare column name.
     *
     * @return array<string, array{mixed}>
     */
    public static function unsafeTitles(): array
    {
        return [
            'injected FROM'    => ['topic_title FROM xoops_users -- '],
            'table-qualified'  => ['xoops_topics.topic_title'],
            'backtick-quoted'  => ['`topic_title`'],
            'trailing newline' => ["topic_title\n"],
            'non-string'       => [['topic_title']],
        ];
    }

    /** @return iterable<string, array{\Closure, mixed, mixed}> */
    public static function methodsWithUnsafeTitles(): iterable
    {
        foreach (self::titledMethods() as $m => [$call, $expected]) {
            foreach (self::unsafeTitles() as $t => [$title]) {
                yield "$m / $t" => [$call, $expected, $title];
            }
        }
    }

    /**
     * @param \Closure(\XoopsTree, mixed): mixed $call
     */
    #[Test]
    #[DataProvider('methodsWithUnsafeTitles')]
    public function unsafeTitleRunsNoQuery(\Closure $call, mixed $expected, mixed $title): void
    {
        $result = $call($this->tree(), $title);

        self::assertSame([], $this->db->queries);
        self::assertSame($expected, $result);
        self::assertCount(1, $this->warnings);
    }
}
