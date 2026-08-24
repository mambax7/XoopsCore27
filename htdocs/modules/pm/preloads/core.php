<?php
/**
 * Private Messages
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             pm
 * @since               2.4.0
 * @author              trabis <lusopoemas@gmail.com>
 */

//if (!defined('XOOPS_ROOT_PATH')) {
//    throw new \RuntimeException('XOOPS root path not defined');
//}

/**
 * PM core preloads
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              trabis <lusopoemas@gmail.com>
 */
class PmCorePreload extends XoopsPreloadItem
{
    /**
     * The current request's query string, rebuilt for safe reflection into a
     * redirect Location target, '?' included — or '' when there is nothing
     * usable. One shared implementation: see xoops_rebuildQueryString() in
     * include/file_safety.php for the threat model.
     *
     * @return string
     */
    private static function filteredQueryString()
    {
        require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

        return xoops_rebuildQueryString($_SERVER['QUERY_STRING'] ?? '');
    }

    /**
     * @param $args
     */
    public static function eventCorePmliteStart($args)
    {
        header('location: ./modules/pm/pmlite.php' . self::filteredQueryString());
        exit();
    }

    /**
     * @param $args
     */
    public static function eventCoreReadpmsgStart($args)
    {
        header('location: ./modules/pm/readpmsg.php' . self::filteredQueryString());
        exit();
    }

    /**
     * @param $args
     */
    public static function eventCoreViewpmsgStart($args)
    {
        header('location: ./modules/pm/viewpmsg.php' . self::filteredQueryString());
        exit();
    }

    /**
     * @param $args
     */
    public static function eventCoreClassSmartyXoops_pluginsXoinboxcount($args)
    {
        $args[0] = xoops_getModuleHandler('message', 'pm');
    }
}
