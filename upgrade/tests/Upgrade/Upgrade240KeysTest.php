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

use mysqli_result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Upgrade_240;
use Xoops\Upgrade\UpgradeControl;
use XoopsMySQLDatabase;

require_once dirname(__DIR__) . '/fixtures/XoopsMySQLDatabaseStub.php';
require_once dirname(__DIR__, 2) . '/upd-2.3.3-to-2.4.0/index.php';

/**
 * 2.7.3 replaced the single-column com_status key on xoopscomments with
 * idx_status_created (com_status, com_created). The 2.4.0 patch checked keys by
 * name, so it re-queued after 2.7.3 and re-added com_status. An index that leads
 * with the column must count as present; one that only contains it must not.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class Upgrade240KeysTest extends TestCase
{
    /** @var array<string, list<array{Key_name: string, Seq_in_index: string, Column_name: string}>> */
    private array $keys = [];

    /** @var list<string> */
    private array $executed = [];

    protected function setUp(): void
    {
        if (!class_exists(mysqli_result::class)) {
            self::markTestSkipped('The mysqli extension is required for database result mocks.');
        }
        $this->keys = [];
        foreach ([
            'modules' => ['isactive', 'weight', 'hascomments'],
            'users'   => ['level'],
            'online'  => ['online_updated', 'online_uid'],
            'config'  => ['conf_order'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $this->keys['xoops_' . $table][] = self::key($column, 1, $column);
            }
        }
        $this->executed = [];
    }

    #[Test]
    public function compositeIndexLeadingWithComStatusSatisfiesTheCheck(): void
    {
        $this->keys['xoops_xoopscomments'] = [
            self::key('idx_status_created', 1, 'com_status'),
            self::key('idx_status_created', 2, 'com_created'),
        ];

        self::assertTrue($this->patch()->check_keys());
        self::assertTrue($this->patch()->apply_keys());
        self::assertSame([], $this->executed, 'apply_keys() must not re-add com_status');
    }

    #[Test]
    public function indexWithComStatusSecondDoesNotSatisfyTheCheck(): void
    {
        $this->keys['xoops_xoopscomments'] = [
            self::key('idx_created_status', 1, 'com_created'),
            self::key('idx_created_status', 2, 'com_status'),
        ];

        self::assertFalse($this->patch()->check_keys());
        self::assertTrue($this->patch()->apply_keys());
        self::assertSame(
            ['ALTER TABLE `xoops_xoopscomments` ADD INDEX `com_status` (`com_status`)'],
            $this->executed
        );
    }

    #[Test]
    public function singleColumnKeyByNameStillSatisfiesTheCheck(): void
    {
        // Leading column differs from the name, so only Key_name matching can satisfy it.
        $this->keys['xoops_xoopscomments'] = [self::key('com_status', 1, 'com_created')];

        self::assertTrue($this->patch()->check_keys());
    }

    /**
     * @return array{Key_name: string, Seq_in_index: string, Column_name: string}
     */
    private static function key(string $name, int $seq, string $column): array
    {
        return ['Key_name' => $name, 'Seq_in_index' => (string) $seq, 'Column_name' => $column];
    }

    private function patch(): Upgrade_240
    {
        $db = new class ($this->keys, $this->executed) extends XoopsMySQLDatabase {
            /** @var array<int, list<array<string, string>>> */
            private array $pending = [];

            /**
             * @param array<string, list<array<string, string>>> $keys
             * @param list<string>                               $executed
             */
            public function __construct(private array $keys, private array &$executed)
            {
            }

            public function query($sql, $limit = 0, $start = 0)
            {
                if (1 !== preg_match('/^SHOW KEYS FROM `([^`]+)`\z/', (string) $sql, $m)) {
                    return false;
                }
                $result = (new \ReflectionClass(mysqli_result::class))->newInstanceWithoutConstructor();
                $this->pending[spl_object_id($result)] = $this->keys[$m[1]] ?? [];

                return $result;
            }

            public function isResultSet($result): bool
            {
                return $result instanceof mysqli_result;
            }

            public function fetchArray($result)
            {
                return array_shift($this->pending[spl_object_id($result)]) ?? false;
            }

            public function exec($sql): bool
            {
                $this->executed[] = (string) $sql;

                return true;
            }
        };

        return new Upgrade_240($db, $this->createMock(UpgradeControl::class));
    }
}
