<?php
/**
 * XOOPS constants
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
 * @package             kernel
 * @since               2.0.0
 * @author              Kazumi Ono (AKA onokazu) http://www.myweb.ne.jp/, http://jp.xoops.org/
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Comment Constants
 *
 */
// Guarded individually rather than behind one "already loaded" flag: a module that
// defined a subset of these itself would otherwise leave the rest undefined.
// Redefining a constant is an E_WARNING today and a fatal error in PHP 9.
defined('XOOPS_COMMENT_APPROVENONE')  || define('XOOPS_COMMENT_APPROVENONE', 0);
defined('XOOPS_COMMENT_APPROVEALL')   || define('XOOPS_COMMENT_APPROVEALL', 1);
defined('XOOPS_COMMENT_APPROVEUSER')  || define('XOOPS_COMMENT_APPROVEUSER', 2);
defined('XOOPS_COMMENT_APPROVEADMIN') || define('XOOPS_COMMENT_APPROVEADMIN', 3);
defined('XOOPS_COMMENT_PENDING')      || define('XOOPS_COMMENT_PENDING', 1);
defined('XOOPS_COMMENT_ACTIVE')       || define('XOOPS_COMMENT_ACTIVE', 2);
defined('XOOPS_COMMENT_HIDDEN')       || define('XOOPS_COMMENT_HIDDEN', 3);
defined('XOOPS_COMMENT_OLD1ST')       || define('XOOPS_COMMENT_OLD1ST', 0);
defined('XOOPS_COMMENT_NEW1ST')       || define('XOOPS_COMMENT_NEW1ST', 1);
