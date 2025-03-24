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
 * @subpackage   avatars
 * @since        2.7.0
 * @author       XOOPS Development Team, Kazumi Ono (AKA onokazu)
 */

$modversion = [
    'name'        => _AM_SYSTEM_AVATARS,
    'version'     => '1.0',
    'description' => _AM_SYSTEM_AVATARS_DESC,
    'author'      => '',
    'credits'     => 'The XOOPS Project; Andricq Nicolas (AKA MusS)',
    'help'        => 'page=avatars',
    'license'     => 'GPL see LICENSE',
    'official'    => 1,
    'image'       => 'avatar.png',
    'icon'        => 'fa fa-user-circle',
    'hasAdmin'    => 1,
    'adminpath'   => 'admin.php?fct=avatars',
    'category'    => XOOPS_SYSTEM_AVATAR,
];
