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
 * Shared protected menu seed data for install and upgrade flows.
 *
 * Titles are stored as language constant identifiers and resolved at render time.
 *
 * @return array{categories: array<string, array<string, mixed>>, items: array<int, array<string, mixed>>}
 */
function system_menu_get_seed_definitions(): array
{
    return [
        'categories' => [
            'home' => [
                'title'      => 'MENUS_HOME',
                'prefix'     => '<span class="fa fa-home"></span>',
                'suffix'     => '',
                'url'        => 'index.php',
                'target'     => 0,
                'position'   => 1,
                'protected'  => 1,
                'active'     => 1,
                'group_keys' => ['admin', 'users', 'anonymous'],
            ],
            'account' => [
                'title'      => 'MENUS_ACCOUNT',
                'prefix'     => '<span class="fa fa-user fa-fw"></span>',
                'suffix'     => '',
                'url'        => '',
                'target'     => 0,
                'position'   => 2,
                'protected'  => 1,
                'active'     => 1,
                'group_keys' => ['admin', 'users', 'anonymous'],
            ],
            'admin' => [
                'title'      => 'MENUS_ADMIN',
                'prefix'     => '<span class="fa fa-wrench fa-fw"></span>',
                'suffix'     => '',
                'url'        => 'admin.php',
                'target'     => 0,
                'position'   => 3,
                'protected'  => 1,
                'active'     => 1,
                'group_keys' => ['admin'],
            ],
        ],
        'items' => [
            [
                'title'      => 'MENUS_ACCOUNT_EDIT',
                'prefix'     => '<span class="fa fa-edit fa-fw"></span>',
                'suffix'     => '',
                'url'        => 'user.php',
                'target'     => 0,
                'position'   => 1,
                'pid'        => 0,
                'protected'  => 1,
                'active'     => 1,
                'group_keys' => ['admin', 'users'],
            ],
            [
                'title'      => 'MENUS_ACCOUNT_LOGIN',
                'prefix'     => '<span class="fa fa-sign-in fa-fw"></span>',
                'suffix'     => '',
                'url'        => 'user.php',
                'target'     => 0,
                'position'   => 2,
                'pid'        => 0,
                'protected'  => 1,
                'active'     => 1,
                'group_keys' => ['anonymous'],
            ],
            [
                'title'      => 'MENUS_ACCOUNT_REGISTER',
                'prefix'     => '<span class="fa fa-sign-in fa-fw"></span>',
                'suffix'     => '',
                'url'        => 'register.php',
                'target'     => 0,
                'position'   => 3,
                'pid'        => 0,
                'protected'  => 1,
                'active'     => 1,
                'group_keys' => ['anonymous'],
            ],
            [
                'title'      => 'MENUS_ACCOUNT_MESSAGES',
                'prefix'     => '<span class="fa fa-envelope fa-fw"></span>',
                'suffix'     => '<span class="badge bg-primary rounded-pill"><{xoInboxCount}></span>',
                'url'        => 'viewpmsg.php',
                'target'     => 0,
                'position'   => 4,
                'pid'        => 0,
                'protected'  => 1,
                'active'     => 1,
                'group_keys' => ['admin', 'users'],
            ],
            [
                'title'      => 'MENUS_ACCOUNT_NOTIFICATIONS',
                'prefix'     => '<span class="fa fa-info-circle fa-fw"></span>',
                'suffix'     => '',
                'url'        => 'notifications.php',
                'target'     => 0,
                'position'   => 5,
                'pid'        => 0,
                'protected'  => 1,
                'active'     => 1,
                'group_keys' => ['admin', 'users'],
            ],
            [
                'title'      => 'MENUS_ACCOUNT_TOOLBAR',
                'prefix'     => '<span class="fa fa-wrench fa-fw"></span>',
                'suffix'     => '<span id="xswatch-toolbar-ind"></span>',
                'url'        => '#xswatch-toolbar-toggle',
                'target'     => 0,
                'position'   => 6,
                'pid'        => 0,
                'protected'  => 1,
                'active'     => 1,
                'group_keys' => ['admin', 'users'],
            ],
            [
                'title'      => 'MENUS_ACCOUNT_LOGOUT',
                'prefix'     => '<span class="fa fa-sign-out fa-fw"></span>',
                'suffix'     => '',
                'url'        => 'user.php?op=logout',
                'target'     => 0,
                'position'   => 7,
                'pid'        => 0,
                'protected'  => 1,
                'active'     => 1,
                'group_keys' => ['admin', 'users'],
            ],
        ],
    ];
}

/**
 * Resolve symbolic group keys to concrete group ids.
 *
 * @param string[]           $groupKeys
 * @param array<string, int> $groupMap
 *
 * @return int[]
 */
function system_menu_map_group_keys(array $groupKeys, array $groupMap): array
{
    $groupIds = [];
    foreach ($groupKeys as $groupKey) {
        if (isset($groupMap[$groupKey])) {
            $groupIds[] = $groupMap[$groupKey];
        }
    }

    return $groupIds;
}
