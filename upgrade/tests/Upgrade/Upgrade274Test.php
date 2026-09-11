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

    protected function setUp(): void
    {
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
            $fails = '*' === $this->queryFailsFor || ('' !== $this->queryFailsFor && str_contains($sql, $this->queryFailsFor));

            return $fails ? false : (new ReflectionClass(\mysqli_result::class))->newInstanceWithoutConstructor();
        });
        $db->method('isResultSet')->willReturnCallback(static fn ($result): bool => $result instanceof \mysqli_result);
        $db->method('fetchRow')->willReturnCallback(function () {
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
    public function tasksCreateTheTableBeforeTheConfigRow(): void
    {
        self::assertSame(['user2fatable', 'twofactormode'], $this->patch()->tasks);
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
}
