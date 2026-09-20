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

namespace Xoops\Upgrade\Tests\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Upgrade_2511;
use Xoops\Upgrade\UpgradeControl;
use XoopsMySQLDatabase;

/**
 * The 2.5.10 to 2.5.11 patch used to re-queue on every wizard session:
 * check_cleancache() read a session flag that is absent at start, and
 * check_notificationmethod() looked for language-constant option names while
 * older upgrades (and a System-module update) stored the translated labels.
 * apply_notificationmethod() then INSERTed the constant names beside the
 * labels, so the check passed until the System update rewrote the options
 * and the next run failed again.
 *
 * Cache cleaning is no longer a patch task. Notification options are deleted
 * and replaced with the names a fresh install stores.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class Upgrade2511NotificationTest extends TestCase
{
    private const OPTION_NAMES = [
        '_MI_DEFAULT_NOTIFICATION_METHOD_DISABLE',
        '_MI_DEFAULT_NOTIFICATION_METHOD_PM',
        '_MI_DEFAULT_NOTIFICATION_METHOD_EMAIL',
    ];

    /** @var list<string> statements handed to exec(), in order */
    private array $exec = [];

    /** @var list<array|false> fetchRow() answers, consumed in order */
    private array $rows = [];

    /** @var list<string> statements handed to query() */
    private array $queries = [];

    /** Substring of the statements whose query() must fail; '' fails none, '*' fails all. */
    private string $queryFailsFor = '';

    private bool $execFails = false;

    /** Fail exec() after this many successful calls; null fails none by count. */
    private ?int $execFailsAfter = null;

    private int $insertId = 0;

    protected function setUp(): void
    {
        if (!class_exists(\mysqli_result::class)) {
            self::markTestSkipped('The mysqli extension is required for database result mocks.');
        }
        if (!defined('XOOPS_PATH')) {
            define('XOOPS_PATH', XOOPS_ROOT_PATH);
        }
        if (!defined('_UPGRADE_CHARSET')) {
            define('_UPGRADE_CHARSET', 'UTF-8');
        }
        require_once dirname(__DIR__) . '/fixtures/XoopsMySQLDatabaseStub.php';
        require_once dirname(__DIR__, 2) . '/upd_2.5.10-to-2.5.11/index.php';
        $this->exec          = [];
        $this->rows          = [];
        $this->queries       = [];
        $this->queryFailsFor = '';
        $this->execFails     = false;
        $this->execFailsAfter = null;
        $this->insertId      = 0;
    }

    private function patch(): Upgrade_2511
    {
        $db = $this->createMock(XoopsMySQLDatabase::class);
        $db->method('prefix')->willReturnCallback(static fn ($table = ''): string => 'xoops_' . $table);
        $db->method('quote')->willReturnCallback(static fn ($value): string => "'" . addslashes((string) $value) . "'");
        $db->method('escape')->willReturnCallback(static fn ($value): string => addslashes((string) $value));
        $db->method('error')->willReturn('');
        $db->method('getInsertId')->willReturnCallback(fn (): int => $this->insertId);
        $db->method('exec')->willReturnCallback(function (string $sql): bool {
            $this->exec[] = $sql;
            if ($this->execFails) {
                return false;
            }
            if (null !== $this->execFailsAfter && count($this->exec) > $this->execFailsAfter) {
                return false;
            }

            return true;
        });
        $db->method('query')->willReturnCallback(function (string $sql): mixed {
            $this->queries[] = $sql;
            $fails = '*' === $this->queryFailsFor
                || ('' !== $this->queryFailsFor && str_contains($sql, $this->queryFailsFor));

            return $fails ? false : (new ReflectionClass(\mysqli_result::class))->newInstanceWithoutConstructor();
        });
        $db->method('isResultSet')->willReturnCallback(static fn ($result): bool => $result instanceof \mysqli_result);
        $db->method('fetchRow')->willReturnCallback(function () {
            $row = array_shift($this->rows);

            return null === $row ? false : $row;
        });

        return new Upgrade_2511($db, $this->createMock(UpgradeControl::class));
    }

    #[Test]
    public function cacheCleaningIsNotAPatchTask(): void
    {
        self::assertNotContains('cleancache', $this->patch()->tasks);
    }

    #[Test]
    public function checkLooksForLanguageConstantNamesAndFailsWhenTheyAreAbsent(): void
    {
        // Three label-only rows: present, but none of them canonical.
        $this->rows = [[135], [3, 0]];
        $patch      = $this->patch();
        self::assertFalse($patch->check_notificationmethod());
        self::assertNotSame([], $this->queries);
        self::assertStringContainsString('_MI_DEFAULT_NOTIFICATION_METHOD_DISABLE', $this->queries[1]);
        self::assertStringNotContainsString('Temporarily disable', $this->queries[1]);
    }

    #[Test]
    public function checkPassesWhenTheThreeConstantNamedOptionsExist(): void
    {
        $this->rows = [[135], [3, 3]];
        self::assertTrue($this->patch()->check_notificationmethod());
    }

    #[Test]
    public function checkFailsWhenLabelRowsSurviveBesideTheConstantNames(): void
    {
        // The state left by the earlier apply_notificationmethod(): the three
        // constants were INSERTed beside the three translated labels, so six
        // rows exist and three of them match. Counting matches alone reported
        // this as applied and the labels stayed in the dropdown for good.
        $this->rows = [[135], [6, 3]];
        self::assertFalse($this->patch()->check_notificationmethod());
    }

    #[Test]
    public function checkFailsWhenDuplicateCanonicalRowsStandInForMissingOnes(): void
    {
        // Two disable rows and one PM row: three rows, all matching the
        // canonical predicate, but only two DISTINCT option names. A
        // matched-row count would read this as applied with the email
        // option missing from the dropdown.
        $this->rows = [[135], [3, 2]];
        self::assertFalse($this->patch()->check_notificationmethod());
        // The stub cannot run SQL, so pin the shape that yields the 2:
        // distinct canonical names, not matched rows.
        self::assertStringContainsString('COUNT(DISTINCT CASE WHEN', $this->queries[1]);
        // ... and binary comparison, so collation cannot admit case or
        // trailing-space variants as canonical.
        self::assertStringContainsString('`confop_name` = BINARY ', $this->queries[1]);
    }

    #[Test]
    public function checkFailsWhenThePreferenceHasNoOptionsAtAll(): void
    {
        // No rows: COUNT(*) is 0 and SUM() is NULL.
        $this->rows = [[135], [0, null]];
        self::assertFalse($this->patch()->check_notificationmethod());
    }

    #[Test]
    public function checkFailsWhenThePreferenceRowIsMissing(): void
    {
        $this->rows = [false];
        self::assertFalse($this->patch()->check_notificationmethod());
        self::assertSame([], $this->exec);
    }

    #[Test]
    public function applyDeletesExistingOptionsThenInsertsTheConstantNames(): void
    {
        $this->rows = [[135]];
        self::assertTrue($this->patch()->apply_notificationmethod());
        self::assertCount(4, $this->exec);
        self::assertStringContainsString('DELETE FROM `xoops_configoption` WHERE `conf_id` = 135', $this->exec[0]);
        $inserted = implode("\n", array_slice($this->exec, 1));
        foreach (self::OPTION_NAMES as $name) {
            self::assertStringContainsString($name, $inserted);
        }
        self::assertStringNotContainsString('INSERT INTO xoops_config ', $this->exec[0]);
        foreach (array_slice($this->exec, 1) as $sql) {
            self::assertStringContainsString('INSERT INTO xoops_configoption', $sql);
        }
    }

    #[Test]
    public function applyInsertsThePreferenceThenReplacesItsOptionsWhenTheRowIsMissing(): void
    {
        $this->rows     = [false];
        $this->insertId = 200;
        self::assertTrue($this->patch()->apply_notificationmethod());
        self::assertCount(5, $this->exec);
        self::assertStringContainsString("conf_name, conf_title", $this->exec[0]);
        self::assertStringContainsString("'default_notification'", $this->exec[0]);
        self::assertStringContainsString('DELETE FROM `xoops_configoption` WHERE `conf_id` = 200', $this->exec[1]);
        $inserted = implode("\n", array_slice($this->exec, 2));
        foreach (self::OPTION_NAMES as $name) {
            self::assertStringContainsString($name, $inserted);
        }
    }

    #[Test]
    public function applyDoesNotWriteWhenThePreferenceLookupCannotBeAnswered(): void
    {
        $this->queryFailsFor = '*';
        $patch               = $this->patch();
        self::assertFalse($patch->apply_notificationmethod());
        self::assertSame([], $this->exec);
        self::assertNotSame([], $patch->logs);
    }

    #[Test]
    public function applyStopsBeforeInsertingOptionsWhenTheDeleteFails(): void
    {
        $this->rows      = [[135]];
        $this->execFails = true;
        self::assertFalse($this->patch()->apply_notificationmethod());
        self::assertCount(1, $this->exec);
        self::assertStringContainsString('DELETE FROM `xoops_configoption`', $this->exec[0]);
    }

    #[Test]
    public function applyReportsFailureWhenAnOptionInsertFailsAfterTheDelete(): void
    {
        $this->rows          = [[135]];
        $this->execFailsAfter = 1;
        self::assertFalse($this->patch()->apply_notificationmethod());
        self::assertCount(2, $this->exec);
        self::assertStringContainsString('DELETE FROM `xoops_configoption`', $this->exec[0]);
        self::assertStringContainsString('INSERT INTO xoops_configoption', $this->exec[1]);
    }
}
