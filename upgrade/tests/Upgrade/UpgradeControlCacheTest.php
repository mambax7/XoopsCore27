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
use Xoops\Upgrade\UpgradeControl;
use XoopsMySQLDatabase;

/**
 * Cache cleaning moved out of the 2.5.11 / 2.7.0 patch tasks onto the
 * controller so a missing session flag cannot re-queue those patches.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class UpgradeControlCacheTest extends TestCase
{
    #[Test]
    public function cleanCachesIsANoOpWhenSystemMaintenanceIsNotOnDisk(): void
    {
        require_once dirname(__DIR__) . '/fixtures/XoopsMySQLDatabaseStub.php';
        $control = new UpgradeControl($this->createMock(XoopsMySQLDatabase::class));
        // The upgrade test bootstrap sets XOOPS_ROOT_PATH to upgrade/tests,
        // which has no modules/system/class/maintenance.php.
        self::assertFalse($control->cleanCaches());
    }
}
