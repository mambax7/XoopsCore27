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

/**
 * @copyright    XOOPS Project https://xoops.org/
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package      system
 * @subpackage   preferences
 * @author       XOOPS Development Team, Kazumi Ono (AKA onokazu)
 */

 $modversion = [
    'name'        => _AM_SYSTEM_PREF,
    'version'     => '1.0',
    'description' => _AM_SYSTEM_PREF_DESC,
    'author'      => '',
    'credits'     => 'The XOOPS Project; Maxime Cointin (AKA Kraven30), Gregory Mage (AKA Mage)',
    'help'        => 'page=preferences',
    'license'     => 'GPL see LICENSE',
    'official'    => 1,
    'image'       => 'prefs.png',
    'icon'        => 'fa fa-wrench',
    'hasAdmin'    => 1,
    'adminpath'   => 'admin.php?fct=preferences',
    'category'    => XOOPS_SYSTEM_PREF,
    'configcat'   => [
        SYSTEM_CAT_MAIN   => 'system_main.png',
        SYSTEM_CAT_USER   => 'system_user.png',
        SYSTEM_CAT_META   => 'system_meta.png',
        SYSTEM_CAT_WORD   => 'system_word.png',
        SYSTEM_CAT_SEARCH => 'system_search.png',
        SYSTEM_CAT_MAIL   => 'system_mail.png',
        SYSTEM_CAT_AUTH   => 'system_auth.png',
        SYSTEM_CAT_TPL    => 'system_theme.png',
    ],
 ];
