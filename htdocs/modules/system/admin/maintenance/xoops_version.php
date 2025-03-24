<?php
/**
 * Maintenance module version information
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or 
 * credit authors. This program is distributed in the hope that it will be 
 * useful, but WITHOUT ANY WARRANTY; without even the implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2016 XOOPS Project (www.xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              Cointin Maxime (AKA Kraven30)
 * @package             system
 */

// Module version information
$modversion = [
    'name'        => _AM_SYSTEM_MAINTENANCE,
    'version'     => '1.0',
    'description' => _AM_SYSTEM_MAINTENANCE_DESC,
    'author'      => 'Cointin Maxime (AKA Kraven30)',
    'credits'     => 'The XOOPS Project',
    'help'        => 'page=maintenance',
    'license'     => 'https://www.gnu.org/licenses/gpl-2.0.html',
    'official'    => 1,
    'image'       => 'maintenance.png',
    'icon'        => 'fa fa-bandcamp',
    'hasAdmin'    => 1,
    'adminpath'   => 'admin.php?fct=maintenance',
    'category'    => XOOPS_SYSTEM_MAINTENANCE,
];
