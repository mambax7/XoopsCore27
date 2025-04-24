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
 * @author       XOOPS Development Team
 */

$adminMenuDomain = [
    [
        'domain' => _AM_DOMAIN_CONTENT,
        'icon' => 'fa-solid fa-file-lines',
        'links' => [
            ['title' => _AM_PAGES_MANAGEMENT, 'url' => 'admin.php?fct=pages'],
            ['title' => _AM_SYSTEM_BLOCKS, 'url' => 'admin.php?fct=blocksadmin'],
            ['title' => _AM_SYSTEM_COMMENTS, 'url' => 'admin.php?fct=comments'],
            ['title' => _AM_SYSTEM_BANS, 'url' => 'admin.php?fct=banners'],
            ['title' => _AM_SYSTEM_IMAGES, 'url' => 'admin.php?fct=images'],
        ],
    ],
    [
        'domain' => _AM_DOMAIN_USERS_PERMISSIONS,
        'icon' => 'fa-solid fa-users-gear',
        'links' => [
            ['title' => _AM_SYSTEM_USER, 'url' => 'admin.php?fct=users'],
            ['title' => _AM_SYSTEM_ADGS, 'url' => 'admin.php?fct=groups'],
            ['title' => _AM_SYSTEM_MLUS, 'url' => 'admin.php?fct=mailusers'],
            ['title' => _AM_SYSTEM_RANK, 'url' => 'admin.php?fct=userrank'],
            ['title' => _AM_SYSTEM_AVATARS, 'url' => 'admin.php?fct=avatars'],
            ['title' => _AM_SYSTEM_SMLS, 'url' => 'admin.php?fct=smilies']
        ],
    ],
    [
        'domain' => _AM_DOMAIN_APPEARANCE,
        'icon' => 'fa-solid fa-palette',
        'links' => [
            ['title' => _AM_SYSTEM_TPLSETS, 'url' => 'admin.php?fct=tplsets'],
            ['title' => _AM_TAG_FOOTER, 'url' => 'admin.php?fct=preferences&op=show&confcat_id=3'],
        ],
    ],
    [
        'domain' => _AM_DOMAIN_SETTINGS,
        'icon' => 'fa-solid fa-gears',
        'links' => [
            ['title' => _AM_SYSTEM_PREF, 'url' => 'admin.php?fct=preferences'],
            ['title' => _AM_SETTINGS_GENERAL, 'url' => 'admin.php?fct=preferences&op=show&confcat_id=1'],
            ['title' => _AM_SETTINGS_THEME, 'url' => 'admin.php?fct=preferences&op=show&confcat_id=8'],
        ],
    ],
    [
        'domain' => _AM_DOMAIN_MODULES,
        'icon' => 'fa-solid fa-cubes',
        'links' => [
            ['title' => _AM_SYSTEM_MODULES, 'url' => 'admin.php?fct=modulesadmin'],
            ['title' => _AM_MODULES_INSTALL, 'url' => 'admin.php?fct=modulesadmin&op=installlist'],
        ],
    ],
    [
        'domain' => _AM_DOMAIN_MAINTENANCE,
        'icon' => 'fa-solid fa-screwdriver-wrench',
        'links' => [
            ['title' => _AM_SYSTEM_MAINTENANCE, 'url' => 'admin.php?fct=maintenance'],
            ['title' => _AM_BACKUP_RESTORE, 'url' => 'admin.php?fct=backup_restore'],
            ['title' => _AM_SYSTEM_LOGS, 'url' => 'admin.php?fct=system_logs'],
            ['title' => _AM_PERFORMANCE_MONITOR, 'url' => 'admin.php?fct=performance_monitor'],
        ],
    ],
];

 