<?php
/**
 * Two-factor site seed: the accounts, factor rows and policy the smoke run expects
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.4
 */
declare(strict_types=1);

// Installation-only process. The HTTP workers never include this fixture.
$root = (string) getenv('XOOPS_2FA_HTTP_ROOT');
if (!preg_match('~/twofactor_http_[a-f0-9]{16}/htdocs$~', str_replace('\\', '/', $root))) {
    throw new RuntimeException('Invalid disposable docroot');
}
$xoopsOption['nocommon'] = true;
define('DS', DIRECTORY_SEPARATOR);
require $root . '/mainfile.php';
require XOOPS_ROOT_PATH . '/include/defines.php';
require XOOPS_ROOT_PATH . '/include/license.php';
require XOOPS_ROOT_PATH . '/language/english/global.php';
require XOOPS_ROOT_PATH . '/install/language/english/install.php';
require XOOPS_ROOT_PATH . '/install/language/english/install2.php';
require XOOPS_ROOT_PATH . '/install/class/dbmanager.php';
require XOOPS_ROOT_PATH . '/class/xoopskernel.php';
$xoops = new xos_kernel_Xoops2();
require XOOPS_ROOT_PATH . '/include/functions.php';
require XOOPS_ROOT_PATH . '/install/include/makedata.php';
$dbm = new Db_manager();
foreach (['sql/mysql.structure.sql', 'sql/mysql.data.sql', 'language/english/mysql.lang.data.sql'] as $file) {
    if (!$dbm->queryFromFile(XOOPS_ROOT_PATH . '/install/' . $file)) {
        throw new RuntimeException('Installer SQL failed: ' . $file . ' ' . $dbm->db->error());
    }
}
$groups = make_groups($dbm);
if (!$groups || !make_data($dbm, 'smokeadmin', password_hash('test-password-only', PASSWORD_DEFAULT), 'nobody@example.invalid', 'english', $groups)) {
    throw new RuntimeException('Installer seed failed');
}
if ($dbm->f_tables !== []) {
    throw new RuntimeException('Installer reported failed inserts: ' . json_encode($dbm->f_tables));
}
if (!$dbm->db->exec('UPDATE ' . $dbm->prefix('config') . " SET conf_value='optional' WHERE conf_name='twofactor_mode'")) {
    throw new RuntimeException('Could not enable fixture two-factor policy');
}
if (1 !== $dbm->db->getAffectedRows()) {
    throw new RuntimeException('Fixture two-factor policy row is missing from the shipped config data');
}
if (!$dbm->db->exec('UPDATE ' . $dbm->prefix('config') . " SET conf_value='0' WHERE conf_name IN ('debug_mode','use_mysession','enable_online_tracking')")) {
    throw new RuntimeException('Could not configure fixture sessions and logging');
}
// Profile routing/preloads need the real module metadata and read permission;
// these management routes do not use Profile's custom field tables.
if (!$dbm->insert('modules', "(mid,name,version,last_update,weight,isactive,dirname,hasmain,hasadmin,hassearch,hasconfig,hascomments,hasnotification) VALUES (2,'Profile',192,0,1,0,'profile',1,1,0,0,0,0)")) {
    throw new RuntimeException('Could not seed Profile module');
}
foreach ([1, 2, 3] as $group) {
    if (!$dbm->insert('group_permission', "(gperm_groupid,gperm_itemid,gperm_modid,gperm_name) VALUES ($group,2,1,'module_read')")) {
        throw new RuntimeException('Could not seed Profile permission for group ' . $group);
    }
}
echo "Installed shipped schema and seed data\n";
