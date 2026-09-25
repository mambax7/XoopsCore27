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
use Upgrade_274;
use Xoops\Upgrade\UpgradeControl;
use XoopsMySQLDatabase;

/**
 * The 2.7.3 to 2.7.4 patch: the user_2fa table first, the twofactor_mode
 * preference last, both idempotent and resumable, both honest about a lookup
 * that could not be answered.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class Upgrade274Test extends TestCase
{
    private const CONFIG_INSERT = 'INSERT INTO `xoops_config` (conf_modid, conf_catid, conf_name, conf_title, conf_value, conf_desc,'
        . " conf_formtype, conf_valuetype, conf_order) VALUES (0, 1, 'twofactor_mode', '_MD_AM_TWOFACTORMODE', 'off',"
        . " '_MD_AM_TWOFACTORMODEDSC', 'select', 'text', 46)";
    private const OFF_INSERT      = "INSERT INTO `xoops_configoption` (confop_name, confop_value, conf_id) VALUES ('_MD_AM_TWOFACTORMODE_OFF', 'off', 140)";
    private const OPTIONAL_INSERT = "INSERT INTO `xoops_configoption` (confop_name, confop_value, conf_id) VALUES ('_MD_AM_TWOFACTORMODE_OPTIONAL', 'optional', 140)";

    /** @var list<string> statements handed to exec(), in order */
    private array $exec = [];

    /** @var list<array|false> fetchRow() answers, consumed in order */
    private array $rows = [];

    /** @var list<array|false> fetchArray() answers, consumed in order */
    private array $arrays = [];

    /** Substring of the statements whose query() must fail; '' fails none, '*' fails all. */
    private string $queryFailsFor = '';

    private bool $execFails = false;
    private bool $lockGranted = true;
    private bool $lockReleased = true;
    private array $queries = [];

    /** When set, answers fetchRow() for ordinary queries from the last SQL instead of $rows. */
    private ?\Closure $answer = null;

    protected function setUp(): void
    {
        if (!class_exists(\mysqli_result::class)) {
            self::markTestSkipped('The mysqli extension is required for database result mocks.');
        }
        require_once dirname(__DIR__) . '/fixtures/XoopsMySQLDatabaseStub.php';
        $file = dirname(__DIR__, 2) . '/upd_2.7.3-to-2.7.4/index.php';
        if (!is_file($file)) {
            self::fail('upd_2.7.3-to-2.7.4/index.php does not exist');
        }
        require_once $file;
        $this->exec       = [];
        $this->rows       = [];
        $this->arrays     = [];
        $this->queryFailsFor = '';
        $this->execFails  = false;
    }

    private function patch(): Upgrade_274
    {
        $db = $this->createMock(XoopsMySQLDatabase::class);
        $db->method('prefix')->willReturnCallback(static fn ($table = ''): string => 'xoops_' . $table);
        $db->method('quote')->willReturnCallback(static fn ($value): string => "'" . addslashes((string) $value) . "'");
        $db->method('escape')->willReturnCallback(static fn ($value): string => addslashes((string) $value));
        $db->method('error')->willReturn('');
        $db->method('exec')->willReturnCallback(function (string $sql): bool {
            $this->exec[] = $sql;

            return !$this->execFails;
        });
        $db->method('query')->willReturnCallback(function (string $sql): mixed {
            $this->queries[] = $sql;
            $fails = '*' === $this->queryFailsFor || ('' !== $this->queryFailsFor && str_contains($sql, $this->queryFailsFor));

            return $fails ? false : (new ReflectionClass(\mysqli_result::class))->newInstanceWithoutConstructor();
        });
        $db->method('isResultSet')->willReturnCallback(static fn ($result): bool => $result instanceof \mysqli_result);
        $db->method('fetchRow')->willReturnCallback(function () {
            if (str_contains((string) end($this->queries), 'GET_LOCK(')) {
                return [$this->lockGranted ? 1 : 0];
            }
            if (str_contains((string) end($this->queries), 'RELEASE_LOCK(')) {
                return [$this->lockReleased ? 1 : 0];
            }
            if (null !== $this->answer) {
                return ($this->answer)((string) end($this->queries));
            }
            $row = array_shift($this->rows);

            return null === $row ? false : $row;
        });
        $db->method('fetchArray')->willReturnCallback(function () {
            $row = array_shift($this->arrays);

            return null === $row ? false : $row;
        });

        return new Upgrade_274($db, $this->createMock(UpgradeControl::class));
    }

    #[Test]
    public function modeMigrationRefusesAnUnacquiredLockWithoutWriting(): void
    {
        $this->lockGranted = false;
        $this->rows = [false, [140], [0], [0]];
        self::assertFalse($this->patch()->apply_twofactormode());
        self::assertSame([], $this->exec);
        self::assertCount(1, $this->queries);
        self::assertStringContainsString('GET_LOCK(', $this->queries[0]);
    }

    #[Test]
    public function modeMigrationReleasesItsLockEvenWhenALookupFails(): void
    {
        $this->queryFailsFor = '`xoops_config`';
        $patch = $this->patch();
        self::assertFalse($patch->apply_twofactormode());
        self::assertStringContainsString('GET_LOCK(', $this->queries[0]);
        self::assertStringContainsString('RELEASE_LOCK(', end($this->queries));
        self::assertSame([], $this->exec);
    }

    #[Test]
    public function modeMigrationReportsFailureWhenItsLockCannotBeReleased(): void
    {
        $this->lockReleased = false;
        $this->rows         = [[140], [1], [1]];
        $patch              = $this->patch();
        self::assertFalse($patch->apply_twofactormode());
        self::assertSame([], $this->exec, 'the rows were already complete');
        self::assertNotSame([], $patch->logs);
    }

    #[Test]
    public function tasksCreateTheTableBeforeTheConfigRow(): void
    {
        self::assertSame(['user2fatable', 'twofactormode', 'editorprefs', 'emoticons'], $this->patch()->tasks);
    }

    #[Test]
    public function checkUser2faTableIsFalseWhenTheTableIsAbsentAndWhenInformationSchemaFails(): void
    {
        $patch        = $this->patch();
        $this->arrays = [false];
        self::assertFalse($patch->check_user2fatable());
        self::assertSame([], $patch->logs);

        $this->arrays = [['1' => '1']];
        self::assertTrue($patch->check_user2fatable());

        $this->queryFailsFor = '*';
        self::assertFalse($patch->check_user2fatable(), 'an unanswerable question is not "absent"');
        self::assertNotSame([], $patch->logs, 'and it is reported');
    }

    #[Test]
    public function applyUser2faTableEmitsTheInstallShapeWithThePrefix(): void
    {
        $patch        = $this->patch();
        $this->arrays = [false];
        self::assertTrue($patch->apply_user2fatable());
        self::assertCount(1, $this->exec);
        $sql = $this->exec[0];
        self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS `xoops_user_2fa` (', $sql);
        foreach ([
            '`uid`             mediumint unsigned NOT NULL',
            '`state`           varchar(10)        NOT NULL',
            "`method`          varchar(16)        NOT NULL DEFAULT 'totp'",
            '`secret`          varbinary(255)     NULL',
            '`confirmed_at`    int unsigned       NOT NULL DEFAULT 0',
            '`last_counter`    bigint unsigned    NOT NULL DEFAULT 0',
            '`failed_attempts` smallint unsigned  NOT NULL DEFAULT 0',
            '`locked_until`    int unsigned       NOT NULL DEFAULT 0',
            '`generation`      char(32)           NOT NULL',
            'PRIMARY KEY (`uid`)',
            'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ] as $fragment) {
            self::assertStringContainsString($fragment, $sql);
        }
        self::assertStringNotContainsString('FOREIGN KEY', $sql);

        $this->exec      = [];
        $this->arrays    = [false];
        $this->execFails = true;
        self::assertFalse($patch->apply_user2fatable());
        self::assertNotSame([], $patch->logs);
    }

    #[Test]
    public function applyUser2faTableIssuesNoDdlWhenTheTableExistsOrCannotBeLookedUp(): void
    {
        $patch        = $this->patch();
        $this->arrays = [['1' => '1']];
        self::assertTrue($patch->apply_user2fatable());
        self::assertSame([], $this->exec, 'an existing table is left alone');

        $this->queryFailsFor = '*';
        self::assertFalse($patch->apply_user2fatable());
        self::assertSame([], $this->exec, 'no DDL on an unreadable information_schema');
        self::assertNotSame([], $patch->logs);
    }

    #[Test]
    public function checkTwofactorModeNeedsTheRowAndBothOptions(): void
    {
        $patch      = $this->patch();
        $this->rows = [false];
        self::assertFalse($patch->check_twofactormode(), 'no preference row');

        $this->rows = [[140], [1], [1]];
        self::assertTrue($patch->check_twofactormode());

        $this->rows = [[140], [1], [0]];
        self::assertFalse($patch->check_twofactormode(), 'the optional option is missing');

        $this->rows          = [[140]];
        $this->queryFailsFor = 'configoption';
        self::assertFalse($patch->check_twofactormode(), 'an unreadable option table is not "complete"');
        self::assertNotSame([], $patch->logs, 'and the check reports why');
    }

    #[Test]
    public function applyTwofactorModeInsertsTheCoreRowAndBothOptions(): void
    {
        $patch      = $this->patch();
        $this->rows = [false, [140], [0], [0]];   // conf_id lookup: absent; after insert: 140; both options absent

        self::assertTrue($patch->apply_twofactormode());
        self::assertSame([self::CONFIG_INSERT, self::OFF_INSERT, self::OPTIONAL_INSERT], $this->exec);
        self::assertSame([], $this->rows, 'every answer was consumed');
    }

    #[Test]
    public function applyTwofactorModeResumesWithOnlyTheMissingOption(): void
    {
        $patch      = $this->patch();
        $this->rows = [[140], [1], [0]];   // row present, 'off' present, 'optional' missing

        self::assertTrue($patch->apply_twofactormode());
        self::assertSame([self::OPTIONAL_INSERT], $this->exec);
    }

    #[Test]
    public function applyTwofactorModeStopsWhenTheRowCannotBeFoundAfterTheInsert(): void
    {
        $patch      = $this->patch();
        $this->rows = [false, false];

        self::assertFalse($patch->apply_twofactormode());
        self::assertSame([self::CONFIG_INSERT], $this->exec, 'no option rows without a conf_id');
        self::assertNotSame([], $patch->logs);
    }

    #[Test]
    public function applyTwofactorModeInsertsNoOptionWhenTheOptionTableCannotBeRead(): void
    {
        $patch               = $this->patch();
        $this->rows          = [[140]];
        $this->queryFailsFor = 'configoption';

        self::assertFalse($patch->apply_twofactormode());
        self::assertSame([], $this->exec);
        self::assertNotSame([], $patch->logs);
    }

    #[Test]
    public function twofactorModeInsertsNothingWhenTheConfigTableCannotBeRead(): void
    {
        $patch               = $this->patch();
        $this->queryFailsFor = '`xoops_config`';

        self::assertFalse($patch->check_twofactormode());
        self::assertFalse($patch->apply_twofactormode());
        self::assertSame([], $this->exec, 'an unreadable config table is not "absent": no duplicate preference row');
        self::assertNotSame([], $patch->logs);
    }

    #[Test]
    public function applyTwofactorModeInsertsNoOptionWhenTheCountCannotBeFetched(): void
    {
        $patch      = $this->patch();
        $this->rows = [[140], false];   // row present; the first COUNT(*) yields no row

        self::assertFalse($patch->apply_twofactormode());
        self::assertSame([], $this->exec);
        self::assertNotSame([], $patch->logs);
    }

    #[Test]
    public function applyTwofactorModeIsIdempotent(): void
    {
        $patch      = $this->patch();
        $this->rows = [[140], [1], [1]];

        self::assertTrue($patch->apply_twofactormode());
        self::assertSame([], $this->exec);
    }

    /**
     * Answer the editorprefs lookups from SQL: the category, each preference and
     * each option exist once their row was inserted (or always when $present is 1).
     * $foreign puts an unrelated category at the Editors category ID.
     */
    private function editorPatch(int $present, bool $foreign = false): Upgrade_274
    {
        class_exists('SCEditorConfig', false)
            || require_once dirname(__DIR__, 3) . '/htdocs/class/xoopseditor/sceditor/class/SCEditorConfig.php';
        $patch = $this->patch();
        $this->rows = [];
        $this->answer = function (string $sql) use ($present, $foreign): array|false {
            if (str_contains($sql, "confcat_name <> '_MD_AM_EDITORS'")) {
                return [$foreign ? 1 : 0];
            }
            if (str_contains($sql, 'SELECT `conf_id`')) {
                preg_match("/conf_name = '([a-z_]+)'/", $sql, $name);
                $inserted = [] !== preg_grep("/'" . $name[1] . "'/", $this->exec);

                return 1 === $present || $inserted ? [100 + crc32($name[1]) % 100] : false;
            }
            if (str_contains($sql, 'configcategory')) {
                return [1 === $present || [] !== preg_grep('/^INSERT INTO `xoops_configcategory`/', $this->exec) ? 1 : 0];
            }
            if (preg_match("/conf_id = (\d+) AND confop_name = '([^']*)'/", $sql, $option)) {
                $row = "VALUES ('" . $option[2] . "', '" . $option[2] . "', " . $option[1] . ')';

                return [1 === $present || [] !== array_filter($this->exec, static fn (string $w): bool => str_contains($w, $row)) ? 1 : 0];
            }

            return [$present];
        };

        return $patch;
    }

    #[Test]
    public function editorPrefsInsertsTheCategoryEveryPreferenceAndItsOptions(): void
    {
        $patch = $this->editorPatch(0);

        self::assertFalse($patch->check_editorprefs());
        self::assertTrue($patch->apply_editorprefs(), implode("\n", $patch->logs));
        $items   = \SCEditorConfig::items();
        $options = array_sum(array_map(static fn (array $item): int => count($item['options']), $items));
        self::assertStringContainsString('INSERT INTO `xoops_configcategory`', $this->exec[0]);
        self::assertCount(count($items), preg_grep('/^INSERT INTO `xoops_config` /', $this->exec));
        self::assertCount($options, preg_grep('/^INSERT INTO `xoops_configoption`/', $this->exec));
        self::assertContains(
            "INSERT INTO `xoops_configoption` (confop_name, confop_value, conf_id) VALUES ('autosave', 'autosave', "
                . (100 + crc32('sceditor_plugins') % 100) . ')',
            $this->exec,
        );

        // Everything is now present: the check passes and a second run writes nothing.
        $written = count($this->exec);
        self::assertTrue($patch->check_editorprefs(), implode("
", $patch->logs));
        self::assertTrue($patch->apply_editorprefs(), implode("
", $patch->logs));
        self::assertCount($written, $this->exec);
    }

    #[Test]
    public function editorPrefsIsIdempotentAndMatchesAnOptionByNameAndValue(): void
    {
        $patch = $this->editorPatch(1);

        self::assertTrue($patch->check_editorprefs());
        self::assertTrue($patch->apply_editorprefs());
        self::assertSame([], $this->exec);
        self::assertNotSame([], preg_grep("/confop_name = 'autosave' AND confop_value = 'autosave'/", $this->queries));
    }

    #[Test]
    public function editorPrefsRefusesACategoryIdTakenByAnotherCategory(): void
    {
        $patch = $this->editorPatch(1, true);

        self::assertFalse($patch->check_editorprefs());
        self::assertFalse($patch->apply_editorprefs());
        self::assertSame([], $this->exec);
        self::assertStringContainsString('already used by another category', $patch->logs[0]);
        self::assertStringContainsString('RELEASE_LOCK(', end($this->queries));
    }

    #[Test]
    public function editorPrefsRefusesAnUnacquiredLockWithoutWriting(): void
    {
        $patch = $this->editorPatch(0);
        $this->lockGranted = false;

        self::assertFalse($patch->apply_editorprefs());
        self::assertSame([], $this->exec);
        self::assertNotSame([], $patch->logs);
    }

    #[Test]
    public function editorPrefsReleasesItsLockWhenALookupFails(): void
    {
        $patch = $this->editorPatch(0);
        $this->queryFailsFor = '`xoops_config`';

        self::assertFalse($patch->check_editorprefs());
        self::assertFalse($patch->apply_editorprefs());
        self::assertStringContainsString('RELEASE_LOCK(', end($this->queries));
        self::assertSame([], $this->exec);
    }

    #[Test]
    public function editorPrefsStopsWhenAnInsertFails(): void
    {
        $patch = $this->editorPatch(0);
        $this->execFails = true;

        self::assertFalse($patch->apply_editorprefs());
        self::assertCount(1, $this->exec, 'nothing after the failed category insert');
        self::assertStringContainsString('RELEASE_LOCK(', end($this->queries));
    }

    #[Test]
    public function emoticonsRefuseAnUnacquiredLockWithoutWriting(): void
    {
        class_exists('SCEditorEmoticons', false)
            || require_once dirname(__DIR__, 3) . '/htdocs/class/xoopseditor/sceditor/class/SCEditorEmoticons.php';
        $this->lockGranted = false;
        $patch             = $this->patch();

        self::assertFalse($patch->apply_emoticons());
        self::assertSame([], $this->exec);
        self::assertCount(1, $this->queries);
        self::assertStringContainsString('GET_LOCK(', $this->queries[0]);
        self::assertStringContainsString('xoops_smiles:emoticons', $this->queries[0]);
    }

    #[Test]
    public function emoticonsReportAnUnreadableSmilesTableWithoutWriting(): void
    {
        class_exists('SCEditorEmoticons', false)
            || require_once dirname(__DIR__, 3) . '/htdocs/class/xoopseditor/sceditor/class/SCEditorEmoticons.php';
        $patch = $this->patch();
        $this->queryFailsFor = 'FROM xoops_smiles';

        self::assertFalse($patch->check_emoticons());
        self::assertFalse($patch->apply_emoticons());
        self::assertSame([], $this->exec);
        self::assertNotSame([], $patch->logs);
        self::assertStringContainsString('RELEASE_LOCK(', end($this->queries), 'the lock is released after the failure');
    }
}
