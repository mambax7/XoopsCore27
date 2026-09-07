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
use ReflectionMethod;
use Upgrade_270;
use Xoops\Upgrade\XoopsUpgrade;

require_once dirname(__DIR__, 2) . '/upd_2.5.11-to-2.7.0/index.php';

/**
 * relativePath() and sanitizeLogMessage() moved from the 2.7.0 patch to
 * XoopsUpgrade. The patch still calls both, so it must keep inheriting them:
 * a private redeclaration in the patch would fatal at class load, and a
 * missing base method would fatal on the first failed deletion.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class Upgrade270HelpersTest extends TestCase
{
    #[Test]
    public function patchInheritsThePathHelpersFromTheBaseClass(): void
    {
        foreach (['relativePath', 'sanitizeLogMessage'] as $method) {
            $declaringClass = (new ReflectionMethod(Upgrade_270::class, $method))->getDeclaringClass()->getName();
            self::assertSame(XoopsUpgrade::class, $declaringClass, "$method must be inherited, not redeclared");
        }

        self::assertSame(
            'Failed to clean cache: rmdir(smarty_cache): Directory not empty',
            'Failed to clean cache: ' . Upgrade_270::sanitizeLogMessage(
                'rmdir(/var/www/html/xoops_data/caches/smarty_cache): Directory not empty'
            )
        );
    }
}
