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

namespace Xoops\Upgrade;

use SplFileInfo;

/**
 * XOOPS Upgrade ScannerProcess
 *
 * Scanning process abstraction for use in ScannerWalker based file processing
 *
 * @category  Xoops\Upgrade
 * @package   Xoops
 * @author    Richard Griffith <richard@geekwright.com>
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
abstract class ScannerProcess
{
    abstract public function inspectFile(SplFileInfo $fileInfo);

    /**
     * Convert an absolute pathname to a leading-slash path relative to
     * XOOPS_ROOT_PATH. Both sides are normalised to forward slashes first, so the
     * root prefix is trimmed regardless of the separator each side uses — without
     * this, a Windows "\" pathname against a "/" root leaves the full absolute
     * path in reports and scan tokens.
     *
     * @param string $pathname
     *
     * @return string
     */
    protected static function relativeToRoot(string $pathname): string
    {
        $root = rtrim(str_replace('\\', '/', XOOPS_ROOT_PATH), '/');
        $path = str_replace('\\', '/', $pathname);

        return ('' !== $root && str_starts_with($path, $root . '/'))
            ? substr($path, strlen($root))
            : $path;
    }
}
