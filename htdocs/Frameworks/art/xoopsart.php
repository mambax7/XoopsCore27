<?php

/**
 * Xoops Frameworks addon: art
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 * @since               1.00
 * @package             Frameworks
 */
class xoopsart
{
    /**
     *
     */
    public function __construct() {}

    /**
     * Load a collective functions of Frameworks
     *
     * @param  string $group name of  the collective functions, empty for functions.php
     * @return bool
     */
    public function loadFunctions($group = '')
    {
        // Confine $group to a plain identifier so it cannot traverse the path.
        if ('' !== $group && !preg_match('/^[a-zA-Z0-9_]+$/', (string) $group)) {
            return false;
        }

        return include_once FRAMEWORKS_ROOT_PATH . "/art/functions.{$group}" . (empty($group) ? '' : '.') . 'php';
    }
}
