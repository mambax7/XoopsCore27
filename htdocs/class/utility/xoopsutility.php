<?php
/**
 * XOOPS Utilities
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2015 XOOPS Project (www.xoops.org)
 * @license             GNU GPL 2 (http://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
 * @package             class
 * @subpackage          utility
 * @since               2.3.0
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 * @version             $Id: xoopsutility.php 13082 2015-06-06 21:59:41Z beckmi $
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * XoopsUtility
 *
 * @package
 * @author              John
 * @copyright       (c) 2000-2015 XOOPS Project (www.xoops.org)
 * @version             $Id: xoopsutility.php 13082 2015-06-06 21:59:41Z beckmi $
 * @access              public
 */
class XoopsUtility
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * XoopsUtility Constructor
     */
    public function XoopsUtility()
    {
        $this->__construct();
    }

    /**
     * XoopsUtility::recursive()
     *
     * @param mixed $handler
     * @param mixed $data
     *
     * @return array|mixed
     */
    public static function recursive($handler, $data)
    {
        if (is_array($data)) {
            $return = array_map(array(
                                    'XoopsUtility',
                                    'recursive'), $handler, $data);

            return $return;
        }
        // single function
        if (is_string($handler)) {
            return function_exists($handler) ? $handler($data) : $data;
        }
        // Method of a class
        if (is_array($handler)) {
            return call_user_func(array(
                                      $handler[0],
                                      $handler[1]), $data);
        }

        return $data;
    }
}