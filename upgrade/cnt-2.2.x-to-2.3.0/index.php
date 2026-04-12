<?php

use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

/**
 * Upgrader from 2.2.* to 2.3.0
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
 * @package             upgrader
 * @since               2.3.0
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 */
class Upgrade_220 extends XoopsUpgrade
{
    /**
     * __construct
     *
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['config', 'profile', 'block'/*, 'pm', 'module'*/];
    }

    /**
     * Check if config category already removed
     *
     * @return bool
     */
    public function check_config(): bool
    {
        $sql    = 'SHOW COLUMNS FROM `' . $this->db->prefix('configcategory') . "` LIKE 'confcat_modid'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

            return false;
        }

        return !($this->db->getRowsNum($result) > 0);
    }

    /**
     * Check if user profile table already converted
     *
     * @return bool
     */
    public function check_profile(): bool
    {
        /** @var XoopsModuleHandler $module_handler */
        $module_handler = xoops_getHandler('module');
        if (!$profile_module = $module_handler->getByDirname('profile')) {
            return true;
        }
        $sql    = 'SHOW COLUMNS FROM ' . $this->db->prefix('users') . " LIKE 'posts'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

            return false;
        }

        return !($this->db->getRowsNum($result) == 0);
    }

    /**
     * Check if block table already converted
     *
     * @return bool
     */
    public function check_block(): bool
    {
        $sql    = "SHOW TABLES LIKE '" . $this->db->prefix('block_instance') . "'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

            return false;
        }

        return !($this->db->getRowsNum($result) > 0);
    }

    /**
     * @return bool
     */
    public function apply(): bool
    {
        if (0 === \Xmf\Request::getInt('upd220', 0, 'GET')) {
            $this->logs[] = _CONFIRM_UPGRADE_220;
            $res          = false;
        } else {
            $res = parent::apply();
        }

        return $res;
    }

    /**
     * @return bool
     */
    public function apply_config(): bool
    {
        //Set core configuration back to zero for system module
        if (!$this->execOrFail('UPDATE `' . $this->db->prefix('config') . '` SET conf_modid = 0 WHERE conf_modid = 1')) {
            return false;
        }

        //Change debug modes so there can only be one active at any one time
        if (!$this->execOrFail('UPDATE `' . $this->db->prefix('config') . "` SET conf_formtype = 'select', conf_valuetype = 'int' WHERE conf_name = 'debug_mode'")) {
            return false;
        }

        //Reset category ID for non-system configs
        if (!$this->execOrFail('UPDATE `' . $this->db->prefix('config') . '` SET conf_catid = 0 WHERE conf_modid > 1 AND conf_catid > 0')) {
            return false;
        }

        // remove admin theme configuration item
        if (!$this->execOrFail('DELETE FROM `' . $this->db->prefix('config') . "` WHERE conf_name='theme_set_admin'")) {
            return false;
        }

        //Drop non-System config categories
        if (!$this->execOrFail('DELETE FROM `' . $this->db->prefix('configcategory') . '` WHERE confcat_modid > 1')) {
            return false;
        }

        //Drop category information fields added in 2.2
        if (!$this->execOrFail('ALTER TABLE `' . $this->db->prefix('configcategory') . '` DROP `confcat_nameid`, DROP `confcat_description`, DROP `confcat_modid`')) {
            return false;
        }

        // Re-add user configuration category
        if (!$this->execOrFail('INSERT INTO `' . $this->db->prefix('configcategory') . "` (confcat_id, confcat_name, confcat_order) VALUES (2, '_MD_AM_USERSETTINGS', 2)")) {
            return false;
        }

        //Rebuild user configuration items
        //Get values from Profile module
        $profile_config_arr                          = [];
        $profile_config_arr['minpass']               = 5;
        $profile_config_arr['minuname']              = 3;
        $profile_config_arr['new_user_notify']       = 1;
        $profile_config_arr['new_user_notify_group'] = XOOPS_GROUP_ADMIN;
        $profile_config_arr['activation_type']       = 0;
        $profile_config_arr['activation_group']      = XOOPS_GROUP_ADMIN;
        $profile_config_arr['uname_test_level']      = 0;
        $profile_config_arr['avatar_allow_upload']   = 0;
        $profile_config_arr['avatar_width']          = 80;
        $profile_config_arr['avatar_height']         = 80;
        $profile_config_arr['avatar_maxsize']        = 35000;
        $profile_config_arr['self_delete']           = 0;
        $profile_config_arr['bad_unames']            = serialize(['webmaster', '^xoops', '^admin']);
        $profile_config_arr['bad_emails']            = serialize(['xoops.org$']);
        $profile_config_arr['maxuname']              = 10;
        $profile_config_arr['avatar_minposts']       = 0;
        $profile_config_arr['allow_chgmail']         = 0;
        $profile_config_arr['reg_dispdsclmr']        = 0;
        $profile_config_arr['reg_disclaimer']        = '';
        $profile_config_arr['allow_register']        = 1;

        /** @var XoopsModuleHandler $module_handler */
        $module_handler = xoops_getHandler('module');
        /** @var XoopsConfigHandler $config_handler */
        $config_handler = xoops_getHandler('config');
        $profile_module = $module_handler->getByDirname('profile');
        if (is_object($profile_module)) {
            $profile_config = $config_handler->getConfigs(new Criteria('conf_modid', $profile_module->getVar('mid')));
            foreach (array_keys($profile_config) as $i) {
                $profile_config_arr[$profile_config[$i]->getVar('conf_name')] = $profile_config[$i]->getVar('conf_value', 'n');
            }
        }

        if (!$this->execOrFail('INSERT INTO `' . $this->db->prefix('config') . '` (conf_modid, conf_catid, conf_name, conf_title, conf_value, conf_desc, conf_formtype, conf_valuetype, conf_order) VALUES ' . " (0, 2, 'minpass', '_MD_AM_MINPASS', " . $this->db->quote($profile_config_arr['minpass']) . ", '_MD_AM_MINPASSDSC', 'textbox', 'int', 1)," . " (0, 2, 'minuname', '_MD_AM_MINUNAME', " . $this->db->quote($profile_config_arr['minuname']) . ", '_MD_AM_MINUNAMEDSC', 'textbox', 'int', 2)," . " (0, 2, 'new_user_notify', '_MD_AM_NEWUNOTIFY', " . $this->db->quote($profile_config_arr['new_user_notify']) . ", '_MD_AM_NEWUNOTIFYDSC', 'yesno', 'int', 4)," . " (0, 2, 'new_user_notify_group', '_MD_AM_NOTIFYTO', " . $this->db->quote($profile_config_arr['new_user_notify_group']) . ", '_MD_AM_NOTIFYTODSC', 'group', 'int', 6)," . " (0, 2, 'activation_type', '_MD_AM_ACTVTYPE', " . $this->db->quote($profile_config_arr['activation_type']) . ", '_MD_AM_ACTVTYPEDSC', 'select', 'int', 8)," . " (0, 2, 'activation_group', '_MD_AM_ACTVGROUP', " . $this->db->quote($profile_config_arr['activation_group']) . ", '_MD_AM_ACTVGROUPDSC', 'group', 'int', 10)," . " (0, 2, 'uname_test_level', '_MD_AM_UNAMELVL', " . $this->db->quote($profile_config_arr['uname_test_level']) . ", '_MD_AM_UNAMELVLDSC', 'select', 'int', 12)," . " (0, 2, 'avatar_allow_upload', '_MD_AM_AVATARALLOW', " . $this->db->quote($profile_config_arr['avatar_allow_upload']) . ", '_MD_AM_AVATARALWDSC', 'yesno', 'int', 14)," . " (0, 2, 'avatar_width', '_MD_AM_AVATARW', " . $this->db->quote($profile_config_arr['avatar_width']) . ", '_MD_AM_AVATARWDSC', 'textbox', 'int', 16)," . " (0, 2, 'avatar_height', '_MD_AM_AVATARH', " . $this->db->quote($profile_config_arr['avatar_height']) . ", '_MD_AM_AVATARHDSC', 'textbox', 'int', 18)," . " (0, 2, 'avatar_maxsize', '_MD_AM_AVATARMAX', " . $this->db->quote($profile_config_arr['avatar_maxsize']) . ", '_MD_AM_AVATARMAXDSC', 'textbox', 'int', 20)," . " (0, 2, 'self_delete', '_MD_AM_SELFDELETE', " . $this->db->quote($profile_config_arr['self_delete']) . ", '_MD_AM_SELFDELETEDSC', 'yesno', 'int', 22)," . " (0, 2, 'bad_unames', '_MD_AM_BADUNAMES', " . $this->db->quote($profile_config_arr['bad_unames']) . ", '_MD_AM_BADUNAMESDSC', 'textarea', 'array', 24)," . " (0, 2, 'bad_emails', '_MD_AM_BADEMAILS', " . $this->db->quote($profile_config_arr['bad_emails']) . ", '_MD_AM_BADEMAILSDSC', 'textarea', 'array', 26)," . " (0, 2, 'maxuname', '_MD_AM_MAXUNAME', " . $this->db->quote($profile_config_arr['maxuname']) . ", '_MD_AM_MAXUNAMEDSC', 'textbox', 'int', 3)," . " (0, 2, 'avatar_minposts', '_MD_AM_AVATARMP', " . $this->db->quote($profile_config_arr['avatar_minposts']) . ", '_MD_AM_AVATARMPDSC', 'textbox', 'int', 15)," . " (0, 2, 'allow_chgmail', '_MD_AM_ALLWCHGMAIL', " . $this->db->quote($profile_config_arr['allow_chgmail']) . ", '_MD_AM_ALLWCHGMAILDSC', 'yesno', 'int', 3)," . " (0, 2, 'reg_dispdsclmr', '_MD_AM_DSPDSCLMR', " . $this->db->quote($profile_config_arr['reg_dispdsclmr']) . ", '_MD_AM_DSPDSCLMRDSC', 'yesno', 'int', 30)," . " (0, 2, 'reg_disclaimer', '_MD_AM_REGDSCLMR', " . $this->db->quote($profile_config_arr['reg_disclaimer']) . ", '_MD_AM_REGDSCLMRDSC', 'textarea', 'text', 32)," . " (0, 2, 'allow_register', '_MD_AM_ALLOWREG', " . $this->db->quote($profile_config_arr['allow_register']) . ", '_MD_AM_ALLOWREGDSC', 'yesno', 'int', 0)")) {
            return false;
        }

        //Rebuild user configuration options
        $criteria = new CriteriaCompo(new Criteria('conf_name', "('activation_type', 'uname_test_level')", 'IN'));
        $criteria->add(new Criteria('conf_modid', 0));
        $criteria->setSort('conf_name');
        $criteria->setOrder('ASC');
        $configs             = $config_handler->getConfigs($criteria);
        $id_activation_type  = $configs[0]->getVar('conf_id');
        $id_uname_test_level = $configs[1]->getVar('conf_id');
        if (!$this->execOrFail('INSERT INTO `' . $this->db->prefix('configoption') . '` (confop_name, confop_value, conf_id) VALUES ' . " ('_MD_AM_USERACTV', '0', {$id_activation_type})," . " ('_MD_AM_AUTOACTV', '1', {$id_activation_type})," . " ('_MD_AM_ADMINACTV', '2', {$id_activation_type})," . " ('_MD_AM_STRICT', '0', {$id_uname_test_level})," . " ('_MD_AM_MEDIUM', '1', {$id_uname_test_level})," . " ('_MD_AM_LIGHT', '2', {$id_uname_test_level})")) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function apply_profile(): bool
    {
        // Restore users table
        if (!$this->execOrFail('ALTER TABLE `' . $this->db->prefix('users') . "`
              ADD url varchar(100) NOT NULL default '',
              ADD user_regdate int(10) unsigned NOT NULL default '0',
              ADD user_icq varchar(15) NOT NULL default '',
              ADD user_from varchar(100) NOT NULL default '',
              ADD user_sig tinytext,
              ADD user_viewemail tinyint(1) unsigned NOT NULL default '0',
              ADD actkey varchar(8) NOT NULL default '',
              ADD user_aim varchar(18) NOT NULL default '',
              ADD user_yim varchar(25) NOT NULL default '',
              ADD user_msnm varchar(100) NOT NULL default '',
              ADD posts mediumint(8) unsigned NOT NULL default '0',
              ADD attachsig tinyint(1) unsigned NOT NULL default '0',
              ADD theme varchar(100) NOT NULL default '',
              ADD timezone_offset float(3,1) NOT NULL default '0.0',
              ADD last_login int(10) unsigned NOT NULL default '0',
              ADD umode varchar(10) NOT NULL default '',
              ADD uorder tinyint(1) unsigned NOT NULL default '0',
              ADD notify_method tinyint(1) NOT NULL default '1',
              ADD notify_mode tinyint(1) NOT NULL default '0',
              ADD user_occ varchar(100) NOT NULL default '',
              ADD bio tinytext,
              ADD user_intrest varchar(150) NOT NULL default '',
              ADD user_mailok tinyint(1) unsigned NOT NULL default '1'
              ")) {
            return false;
        }

        // Copy data from profile table
        $profile_fields = [
            'url',
            'user_regdate',
            'user_icq',
            'user_from',
            'user_sig',
            'user_viewemail',
            'actkey',
            'user_aim',
            'user_yim',
            'user_msnm',
            'posts',
            'attachsig',
            'theme',
            'timezone_offset',
            'last_login',
            'umode',
            'uorder',
            'notify_method',
            'notify_mode',
            'user_occ',
            'bio',
            'user_intrest',
            'user_mailok',
        ];
        foreach ($profile_fields as $field) {
            if (!$this->execOrFail('UPDATE `' . $this->db->prefix('users') . '` u, `' . $this->db->prefix('user_profile') . "` p SET u.{$field} = p.{$field} WHERE u.uid=p.profileid")) {
                return false;
            }
        }

        //Set display name as real name
        if (!$this->execOrFail('UPDATE `' . $this->db->prefix('users') . "` SET name=uname WHERE name=''")) {
            return false;
        }
        //Set loginname as uname
        if (!$this->execOrFail('UPDATE `' . $this->db->prefix('users') . '` SET uname=loginname')) {
            return false;
        }
        //Drop loginname
        if (!$this->execOrFail('ALTER TABLE `' . $this->db->prefix('users') . '` DROP loginname')) {
            return false;
        }

        return true;
    }

    /**
     * @param $block
     * @param $blocks
     *
     * @return int|null|string
     */
    public function _block_lookup($block, $blocks)
    {
        if ($block['show_func'] === 'b_system_custom_show') {
            return 0;
        }

        foreach ($blocks as $key => $bk) {
            if ($block['show_func'] == $bk['show_func'] && $block['edit_func'] == $bk['edit_func'] && $block['template'] == $bk['template']) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return bool
     */
    public function apply_block(): bool
    {
        $modulesRoot = realpath(XOOPS_ROOT_PATH . '/modules');
        if (false === $modulesRoot) {
            $this->logs[] = 'Unable to resolve modules root before migrating blocks.';

            return false;
        }

        if (!$this->execOrFail('UPDATE ' . $this->db->prefix('block_module_link') . ' SET module_id = -1, pageid = 0 WHERE module_id < 2 AND pageid = 1')) {
            return false;
        }

        //Change block module link to remove pages
        //Remove page links for module subpages
        if (!$this->execOrFail('DELETE FROM ' . $this->db->prefix('block_module_link') . ' WHERE pageid > 0')) {
            return false;
        }

        $sql = 'ALTER TABLE `' . $this->db->prefix('block_module_link') . '` DROP PRIMARY KEY';
        if (!$this->execOrFail($sql)) {
            return false;
        }
        // Deduplicate BEFORE dropping pageid — the join needs all three columns
        $table = $this->db->prefix('block_module_link');
        $sql = 'DELETE duplicate_link'
            . " FROM `{$table}` AS duplicate_link"
            . " INNER JOIN `{$table}` AS kept_link"
            . ' ON duplicate_link.block_id = kept_link.block_id'
            . ' AND duplicate_link.module_id = kept_link.module_id'
            . ' AND duplicate_link.pageid = kept_link.pageid'
            . ' AND duplicate_link.linkid > kept_link.linkid';
        if (!$this->execOrFail($sql)) {
            return false;
        }
        $sql = 'ALTER TABLE `' . $table . '` DROP pageid';
        if (!$this->execOrFail($sql)) {
            return false;
        }
        $sql = 'ALTER TABLE `' . $table . '` ADD PRIMARY KEY (`block_id`, `module_id`)';
        if (!$this->execOrFail($sql)) {
            return false;
        }

        if (!$this->execOrFail('RENAME TABLE `' . $this->db->prefix('newblocks') . '` TO `' . $this->db->prefix('newblocks_bak') . '`')) {
            return false;
        }

        // Create new block table
        $sql = 'CREATE TABLE ' . $this->db->prefix('newblocks') . " (
              bid mediumint(8) unsigned NOT NULL auto_increment,
              mid smallint(5) unsigned NOT NULL default '0',
              func_num tinyint(3) unsigned NOT NULL default '0',
              options varchar(255) NOT NULL default '',
              name varchar(150) NOT NULL default '',
              title varchar(255) NOT NULL default '',
              content text,
              side tinyint(1) unsigned NOT NULL default '0',
              weight smallint(5) unsigned NOT NULL default '0',
              visible tinyint(1) unsigned NOT NULL default '0',
              block_type char(1) NOT NULL default '',
              c_type char(1) NOT NULL default '',
              isactive tinyint(1) unsigned NOT NULL default '0',
              dirname varchar(50) NOT NULL default '',
              func_file varchar(50) NOT NULL default '',
              show_func varchar(50) NOT NULL default '',
              edit_func varchar(50) NOT NULL default '',
              template varchar(50) NOT NULL default '',
              bcachetime int(10) unsigned NOT NULL default '0',
              last_modified int(10) unsigned NOT NULL default '0',
              PRIMARY KEY  (bid),
              KEY `mid` (mid),
              KEY visible (visible),
              KEY isactive_visible_mid (isactive,visible,mid),
              KEY mid_funcnum (mid,func_num)
            ) ENGINE=MyISAM;
            ";
        if (!$this->execOrFail($sql)) {
            return false;
        }

        $sql    = '   SELECT COALESCE(MAX(instanceid), 0) FROM ' . $this->db->prefix('block_instance');
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

            return false;
        }

        [$MaxInstanceId] = $this->db->fetchRow($result);
        $MaxInstanceId = (int) $MaxInstanceId;

        // Change custom block mid from 1 to 0
        $sql    = 'UPDATE `' . $this->db->prefix('newblocks_bak') . "` SET mid = 0 WHERE show_func = 'b_system_custom_show'";
        if (!$this->execOrFail($sql)) {
            return false;
        }

        $sql       = '   SELECT b.*, i.instanceid ' . '   FROM ' . $this->db->prefix('block_instance') . ' AS i LEFT JOIN ' . $this->db->prefix('newblocks_bak') . ' AS b ON b.bid = i.bid ' . '   GROUP BY b.dirname, b.bid, i.instanceid';
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

            return false;
        }
        $dirname   = '';
        $bid       = 0;
        $block_key = null;
        while (false !== ($row = $this->db->fetchArray($result))) {
            if ($row['dirname'] != $dirname) {
                $dirname     = (string) $row['dirname'];
                $modversion  = [];
                // Reject traversal attempts and slashes in the DB-sourced dirname
                if ('' === $dirname || !preg_match('/^[A-Za-z0-9_-]+$/', $dirname)) {
                    continue;
                }
                $versionFile = false !== $modulesRoot
                    ? realpath($modulesRoot . DIRECTORY_SEPARATOR . $dirname . DIRECTORY_SEPARATOR . 'xoops_version.php')
                    : false;
                if (false === $versionFile
                    || false === $modulesRoot
                    || !str_starts_with($versionFile, $modulesRoot . DIRECTORY_SEPARATOR)
                    || !is_file($versionFile)
                ) {
                    continue;
                }
                include $versionFile;
            }
            if (empty($modversion['blocks']) && $dirname !== 'system') {
                continue;
            }

            $isClone = true;
            if ($row['bid'] != $bid) {
                $bid       = $row['bid'];
                $isClone   = false;
                $block_key = null;
                $block_key = $this->_block_lookup($row, $modversion['blocks']);
            }
            if ($block_key === null) {
                continue;
            }

            // Copy data from block instance table and blocks table
            $sql = '    INSERT INTO ' . $this->db->prefix('newblocks') . '        (bid, mid, options, name, title, side, weight, visible, ' . '            func_num, ' . '            block_type, ' . '           c_type, ' . '            isactive, dirname, func_file,' . '            show_func, edit_func, template, bcachetime, last_modified)' . '    SELECT ' . '        i.instanceid, c.mid, i.options, c.name, i.title, i.side, i.weight, i.visible, ' . "        {$block_key}, " . ($isClone ? " CASE WHEN c.show_func='b_system_custom_show' THEN 'C' ELSE 'D' END," : " CASE WHEN c.show_func='b_system_custom_show' THEN 'C' WHEN c.mid = 1 THEN 'S' ELSE 'M' END,") . "        CASE WHEN c.c_type='' THEN 'H' ELSE c.c_type END," . '        c.isactive, c.dirname, c.func_file,' . '        c.show_func, c.edit_func, c.template, i.bcachetime, c.last_modified' . '    FROM ' . $this->db->prefix('block_instance') . ' AS i,' . '        ' . $this->db->prefix('newblocks_bak') . ' AS c' . '    WHERE i.bid = c.bid' . '        AND i.instanceid = ' . $row['instanceid'];
            if (!$this->execOrFail($sql)) {
                return false;
            }
        }

        $sql = '   SELECT b.* '
            . '   FROM ' . $this->db->prefix('newblocks_bak') . ' AS b LEFT JOIN ' . $this->db->prefix('block_instance') . ' AS i ON b.bid = i.bid '
            . '   WHERE i.instanceid IS NULL'
            . '   GROUP BY b.dirname, b.bid';
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

            return false;
        }
        $dirname   = '';
        $bid       = 0;
        $block_key = null;
        while (false !== ($row = $this->db->fetchArray($result))) {
            if ($row['dirname'] != $dirname) {
                $dirname     = (string) $row['dirname'];
                $modversion  = [];
                // Reject traversal attempts and slashes in the DB-sourced dirname
                if ('' === $dirname || !preg_match('/^[A-Za-z0-9_-]+$/', $dirname)) {
                    continue;
                }
                $versionFile = false !== $modulesRoot
                    ? realpath($modulesRoot . DIRECTORY_SEPARATOR . $dirname . DIRECTORY_SEPARATOR . 'xoops_version.php')
                    : false;
                if (false === $versionFile
                    || false === $modulesRoot
                    || !str_starts_with($versionFile, $modulesRoot . DIRECTORY_SEPARATOR)
                    || !is_file($versionFile)
                ) {
                    continue;
                }
                include $versionFile;
            }
            if (empty($modversion['blocks']) && $dirname !== 'system') {
                continue;
            }

            if ($row['bid'] != $bid) {
                $bid       = $row['bid'];
                $block_key = null;
                $block_key = $this->_block_lookup($row, $modversion['blocks']);
            }
            if ($block_key === null) {
                continue;
            }

            // Copy data from blocks table
            $sql = '    INSERT INTO ' . $this->db->prefix('newblocks') . '        (bid, mid, options, name, title, side, weight, visible, ' . '            func_num, ' . '            block_type, ' . '           c_type, ' . '            isactive, dirname, func_file,' . '            show_func, edit_func, template, bcachetime, last_modified)' . '    SELECT ' . "        bid + {$MaxInstanceId}, mid, options, name, name, 0, 0, 0, " . "        {$block_key}, " . "        CASE WHEN show_func='b_system_custom_show' THEN 'C' WHEN mid = 1 THEN 'S' ELSE 'M' END," . "        CASE WHEN c_type='' THEN 'H' ELSE c_type END," . '        isactive, dirname, func_file,' . '        show_func, edit_func, template, 0, last_modified' . '    FROM ' . $this->db->prefix('newblocks_bak') . '    WHERE bid = ' . $row['bid'];
            if (!$this->execOrFail($sql)) {
                return false;
            }

            // Build block-module link
            $sql = '    INSERT INTO ' . $this->db->prefix('block_module_link') . '        (block_id, module_id)' . '    SELECT ' . "        bid + {$MaxInstanceId}, -1" . '    FROM ' . $this->db->prefix('newblocks_bak') . '    WHERE bid = ' . $row['bid'];
            if (!$this->execOrFail($sql)) {
                return false;
            }
        }

        // Dealing with tables
        if (!$this->execOrFail('DROP TABLE `' . $this->db->prefix('block_instance') . '`;')) {
            return false;
        }
        if (!$this->execOrFail('DROP TABLE `' . $this->db->prefix('newblocks_bak') . '`;')) {
            return false;
        }

        // Deal with custom blocks, convert options to type and content
        $sql    = 'SELECT bid, options FROM `' . $this->db->prefix('newblocks') . "` WHERE show_func='b_system_custom_show'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

            return false;
        }
        while (false !== (list($bid, $options) = $this->db->fetchRow($result))) {
            $_options = unserialize((string) $options, ['allowed_classes' => false]);
            if (!is_array($_options) || !array_key_exists(0, $_options) || !array_key_exists(1, $_options)) {
                $this->logs[] = sprintf('Skipping custom block bid=%d: malformed options payload', (int) $bid);
                continue;
            }
            $content = (string) $_options[0];
            $type    = (string) $_options[1];
            if (!$this->execOrFail('UPDATE `' . $this->db->prefix('newblocks') . '` SET c_type = ' . $this->db->quote($type) . ", options = '', content = " . $this->db->quote($content) . ' WHERE bid = ' . (int) $bid)) {
                return false;
            }
        }

        // Deal with block options, convert array values to "," and "|" delimited
        $sql    = 'UPDATE `' . $this->db->prefix('newblocks') . "` SET options = '' WHERE show_func <> 'b_system_custom_show' AND ( options = 'a:1:{i:0;s:0:\"\";}' OR options = 'a:0:{}' )";
        if (!$this->execOrFail($sql)) {
            return false;
        }
        $sql    = 'SELECT bid, options FROM `' . $this->db->prefix('newblocks') . "` WHERE show_func <> 'b_system_custom_show' AND options <> ''";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

            return false;
        }
        while (false !== (list($bid, $_options) = $this->db->fetchRow($result))) {
            $options = unserialize($_options, ['allowed_classes' => false]);
            if (empty($options) || !is_array($options)) {
                $options = [];
            }
            $count = count($options);
            //Convert array values to comma-separated
            for ($i = 0; $i < $count; ++$i) {
                if (is_array($options[$i])) {
                    $options[$i] = implode(',', $options[$i]);
                }
            }
            $options = implode('|', $options);
            $sql     = 'UPDATE `' . $this->db->prefix('newblocks') . '` SET options = ' . $this->db->quote($options) . ' WHERE bid = ' . (int) $bid;
            if (!$this->execOrFail($sql)) {
                return false;
            }
        }

        return true;
    }

    private function execOrFail(string $sql): bool
    {
        if ($this->db->exec($sql)) {
            return true;
        }

        $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

        return false;
    }
}

return Upgrade_220::class;
