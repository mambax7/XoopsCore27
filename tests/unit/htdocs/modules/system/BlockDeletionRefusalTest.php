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

namespace modulessystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use XoopsTestStubDatabase;

/**
 * SystemBlockHandler::deletionRefusal() is the one rule both delete steps
 * apply: system blocks never go, a module block only while another module
 * block with the same function remains. A custom block that copies the
 * module, function number and show function must not count as that copy.
 *
 * @category  Xoops
 * @package   System
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(\SystemBlockHandler::class)]
final class BlockDeletionRefusalTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once XOOPS_ROOT_PATH . '/kernel/block.php';
        require_once XOOPS_ROOT_PATH . '/modules/system/class/block.php';
        foreach (['_AM_SYSTEM_BLOCKS_SYSTEMCANT' => 'system', '_AM_SYSTEM_BLOCKS_MODULECANT' => 'module'] as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        // First use installs the logger's process-wide handlers; do it here,
        // outside any single test.
        new \SystemBlockHandler(new XoopsTestStubDatabase());
        new \XoopsBlock();
    }

    /**
     * Handler whose count of matching rows is fixed, recording the criteria it was asked.
     */
    private function handler(int $count): \SystemBlockHandler
    {
        return new class (new XoopsTestStubDatabase(), $count) extends \SystemBlockHandler {
            public string $asked = '';

            public function __construct(\XoopsMySQLDatabase $db, private int $count)
            {
                parent::__construct($db);
            }

            public function getCount(?\CriteriaElement $criteria = null)
            {
                $this->asked = (string) $criteria?->render();

                return $this->count;
            }
        };
    }

    /**
     * Handler over a database that records each statement and reports a fixed affected-row count.
     */
    private function deletingHandler(int $affected, int $count = 0): \SystemBlockHandler
    {
        $db = new class ($affected) extends XoopsTestStubDatabase {
            /** @var string[] */
            public array $executed = [];

            public function __construct(private int $affected)
            {
                parent::__construct();
            }

            public function exec(string $sql): bool
            {
                $this->executed[] = $sql;

                return true;
            }

            public function getAffectedRows()
            {
                return $this->affected;
            }
        };

        $handler = new class ($db, $count) extends \SystemBlockHandler {
            public function __construct(\XoopsMySQLDatabase $db, private int $count)
            {
                parent::__construct($db);
            }

            public function getCount(?\CriteriaElement $criteria = null)
            {
                return $this->count;
            }
        };
        // XoopsPersistableObjectHandler takes the factory connection, not the argument.
        $handler->db = $db;

        return $handler;
    }

    private function block(string $type, string $showFunc = 'b_news_show'): \XoopsBlock
    {
        $block = new \XoopsBlock();
        $block->setVar('bid', 7);
        $block->setVar('block_type', $type);
        $block->setVar('mid', 3);
        $block->setVar('func_num', 2);
        $block->setVar('show_func', $showFunc);

        return $block;
    }

    #[Test]
    public function systemBlocksAreNeverDeleted(): void
    {
        $this->assertSame(_AM_SYSTEM_BLOCKS_SYSTEMCANT, $this->handler(5)->deletionRefusal($this->block('S')));
    }

    #[Test]
    public function aModuleBlockGoesOnlyWhileAnotherModuleBlockRemains(): void
    {
        $this->assertSame(_AM_SYSTEM_BLOCKS_MODULECANT, $this->handler(1)->deletionRefusal($this->block('M')));
        $handler = $this->handler(2);
        $this->assertNull($handler->deletionRefusal($this->block('M')));
        $this->assertMatchesRegularExpression('/block_type`?\s*=\s*\'M\'/', $handler->asked, 'custom copies are not counted');
    }

    #[Test]
    public function anEmptyShowFuncIsStillCompared(): void
    {
        $handler = $this->handler(2);
        $this->assertNull($handler->deletionRefusal($this->block('M', '')));
        $this->assertMatchesRegularExpression('/show_func`?\s*=\s*\'\'/', $handler->asked, 'an empty show_func must not drop out of the count');
    }

    #[Test]
    public function customAndCloneBlocksMayBeDeleted(): void
    {
        $this->assertNull($this->handler(0)->deletionRefusal($this->block('C')));
        $this->assertNull($this->handler(0)->deletionRefusal($this->block('D')));
    }

    #[Test]
    public function aModuleBlockIsDeletedInOneStatementThatChecksForItsSibling(): void
    {
        $handler = $this->deletingHandler(1);
        $this->assertTrue($handler->deleteGuarded($this->block('M', '')));
        $sql = $handler->db->executed[0];
        $this->assertStringStartsWith('DELETE FROM `xoops_newblocks` WHERE bid = 7 AND EXISTS (SELECT 1 FROM (SELECT bid FROM `xoops_newblocks` WHERE', $sql);
        $this->assertStringContainsString("mid = 3 AND func_num = 2 AND show_func = '' AND block_type = 'M' AND bid <> 7 LIMIT 1) AS sibling)", $sql);
        $this->assertCount(1, $handler->db->executed, 'the sibling check is inside the DELETE, not a separate statement');
    }

    #[Test]
    public function aModuleBlockWhoseSiblingWentFirstIsRefused(): void
    {
        $handler = $this->deletingHandler(0);
        $this->assertFalse($handler->deleteGuarded($this->block('M')));
    }

    #[Test]
    public function theGuardedDeleteKeepsTheRefusalRulesForOtherTypes(): void
    {
        $this->assertFalse($this->deletingHandler(1)->deleteGuarded($this->block('S')));
        $handler = $this->deletingHandler(1);
        $this->assertTrue($handler->deleteGuarded($this->block('C')));
        $this->assertStringContainsString('`bid` = ', $handler->db->executed[0]);
    }
}
