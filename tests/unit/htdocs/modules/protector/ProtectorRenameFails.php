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

namespace modulesprotector;

/**
 * Protector whose final rename always fails: the failure-path fixture for
 * ProtectorTest::writeFileAtomicKeepsTheOldFileAndCleansUpWhenTheRenameFails().
 *
 * @category  Xoops
 * @package   Protector
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class ProtectorRenameFails extends \Protector
{
    protected static function moveIntoPlace(string $tmp, string $path): bool
    {
        return false;
    }
}
