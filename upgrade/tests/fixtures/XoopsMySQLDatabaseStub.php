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

// The upgrade test bootstrap boots no XOOPS kernel. Patch tests that run a
// real patch constructor need the connection type it is hinted on, with the
// surface the patches call, so PHPUnit can build a mock of it.
if (!class_exists('XoopsMySQLDatabase', false)) {
    /**
     * Connection surface the upgrade patches touch.
     *
     * @category  Xoops\Upgrade\Tests
     * @package   Xoops
     * @author    XOOPS Development Team
     * @copyright 2000-2026 XOOPS Project (https://xoops.org)
     * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
     * @link      https://xoops.org
     */
    class XoopsMySQLDatabase
    {
        public function prefix($table = ''): string
        {
            return 'xoops_' . $table;
        }

        public function query($sql, $limit = 0, $start = 0)
        {
            return false;
        }

        public function exec($sql): bool
        {
            return false;
        }

        public function isResultSet($result): bool
        {
            return false;
        }

        public function fetchArray($result)
        {
            return false;
        }

        public function fetchRow($result)
        {
            return false;
        }

        public function quote($value): string
        {
            return "'" . addslashes((string) $value) . "'";
        }

        public function escape($value): string
        {
            return addslashes((string) $value);
        }

        public function error(): string
        {
            return '';
        }
    }
}
defined('_DB_QUERY_ERROR') || define('_DB_QUERY_ERROR', 'Query Failed! SQL: %s - Error: ');
