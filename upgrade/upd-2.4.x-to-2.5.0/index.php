<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

require_once __DIR__ . '/dbmanager.php';

/**
 * Upgrade from 2.4.x to 2.5.0
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since     2.5.0
 * @author    XOOPS Team
 */
class Upgrade_250 extends XoopsUpgrade
{
    /**
     * @var string
     */
    protected $dbmanagerFile;

    /**
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks         = ['config', 'templates', 'strayblock'];
        $this->dbmanagerFile = __DIR__ . '/dbmanager.php';
    }

    /**
     * Check if cpanel config already exists
     *
     * @return bool
     */
    public function check_config(): bool
    {
        $sql = 'SELECT COUNT(*) FROM `' . $this->db->prefix('config') . "` WHERE `conf_name` IN ('break1', 'usetips')";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        $count = $row[0] ?? 0;

        return ($count != 0);
    }

    /**
     * @return bool
     */
    public function check_templates(): bool
    {
        $sql = 'SELECT COUNT(*) FROM `' . $this->db->prefix('tplfile') . "` WHERE `tpl_file` IN ('system_header.html', 'system_header.tpl') AND `tpl_type` = 'admin'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        $count = $row[0] ?? 0;

        return ($count != 0);
    }

    /**
     * @return bool
     */
    public function apply_config(): bool
    {
        if (!file_exists($this->dbmanagerFile)) {
            $this->logs[] = 'Database manager file not found: ' . $this->dbmanagerFile;
            return false;
        }
        require_once $this->dbmanagerFile;
        $dbm = new Db_manager();

        $sql    = 'SELECT conf_id FROM `' . $this->db->prefix('config') . "` WHERE `conf_name` IN ('cpanel')";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = 'Failed to find cpanel config';
            return false;
        }
        $row = $this->db->fetchRow($result);
        if (empty($row)) {
            $this->logs[] = 'Cpanel config not found in database';
            return false;
        }

        $sql = 'UPDATE `' . $this->db->prefix('config') . "` SET `conf_value` = 'default' WHERE `conf_id` = " . (int) $row[0];
        if (!$this->db->exec($sql)) {
            $this->logs[] = 'Failed to update cpanel config';
            return false;
        }

        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'break1', '_MI_SYSTEM_PREFERENCE_BREAK_GENERAL', 'head', '', 'line_break', 'textbox', 0)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'usetips', '_MI_SYSTEM_PREFERENCE_TIPS', '1', '_MI_SYSTEM_PREFERENCE_TIPS_DSC', 'yesno', 'int', 10)");
        $icon_id = $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'typeicons', '_MI_SYSTEM_PREFERENCE_ICONS', 'default', '', 'select', 'text', 20)");
        $bc_id   = $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'typebreadcrumb', '_MI_SYSTEM_PREFERENCE_BREADCRUMB', 'default', '', 'select', 'text', 30)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'break2', '_MI_SYSTEM_PREFERENCE_BREAK_ACTIVE', 'head', '', 'line_break', 'textbox', 40)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_avatars', '_MI_SYSTEM_PREFERENCE_ACTIVE_AVATARS', '1', '', 'yesno', 'int', 50)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_banners', '_MI_SYSTEM_PREFERENCE_ACTIVE_BANNERS', '1', '', 'yesno', 'int', 60)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_blocksadmin', '_MI_SYSTEM_PREFERENCE_ACTIVE_BLOCKSADMIN', '1', '', 'hidden', 'int', 70)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_comments', '_MI_SYSTEM_PREFERENCE_ACTIVE_COMMENTS', '1', '', 'yesno', 'int', 80)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_filemanager', '_MI_SYSTEM_PREFERENCE_ACTIVE_FILEMANAGER', '1', '', 'yesno', 'int', 90)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_groups', '_MI_SYSTEM_PREFERENCE_ACTIVE_GROUPS', '1', '', 'hidden', 'int', 100)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_images', '_MI_SYSTEM_PREFERENCE_ACTIVE_IMAGES', '1', '', 'yesno', 'int', 110)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_mailusers', '_MI_SYSTEM_PREFERENCE_ACTIVE_MAILUSERS', '1', '', 'yesno', 'int', 120)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_modulesadmin', '_MI_SYSTEM_PREFERENCE_ACTIVE_MODULESADMIN', '1', '', 'hidden', 'int', 130)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_maintenance', '_MI_SYSTEM_PREFERENCE_ACTIVE_MAINTENANCE', '1', '', 'yesno', 'int', 140)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_preferences', '_MI_SYSTEM_PREFERENCE_ACTIVE_PREFERENCES', '1', '', 'hidden', 'int', 150)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_smilies', '_MI_SYSTEM_PREFERENCE_ACTIVE_SMILIES', '1', '', 'yesno', 'int', 160)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_tplsets', '_MI_SYSTEM_PREFERENCE_ACTIVE_TPLSETS', '1', '', 'hidden', 'int', 170)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_userrank', '_MI_SYSTEM_PREFERENCE_ACTIVE_USERRANK', '1', '', 'yesno', 'int', 180)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'active_users', '_MI_SYSTEM_PREFERENCE_ACTIVE_USERS', '1', '', 'yesno', 'int', 190)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'break3', '_MI_SYSTEM_PREFERENCE_BREAK_PAGER', 'head', '', 'line_break', 'textbox', 200)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'avatars_pager', '_MI_SYSTEM_PREFERENCE_AVATARS_PAGER', '10', '', 'textbox', 'int', 210)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'banners_pager', '_MI_SYSTEM_PREFERENCE_BANNERS_PAGER', '10', '', 'textbox', 'int', 220)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'comments_pager', '_MI_SYSTEM_PREFERENCE_COMMENTS_PAGER', '20', '', 'textbox', 'int', 230)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'groups_pager', '_MI_SYSTEM_PREFERENCE_GROUPS_PAGER', '15', '', 'textbox', 'int', 240)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'images_pager', '_MI_SYSTEM_PREFERENCE_IMAGES_PAGER', '15', '', 'textbox', 'int', 250)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'smilies_pager', '_MI_SYSTEM_PREFERENCE_SMILIES_PAGER', '20', '', 'textbox', 'int', 260)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'userranks_pager', '_MI_SYSTEM_PREFERENCE_USERRANKS_PAGER', '20', '', 'textbox', 'int', 270)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'users_pager', '_MI_SYSTEM_PREFERENCE_USERS_PAGER', '20', '', 'textbox', 'int', 280)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'break4', '_MI_SYSTEM_PREFERENCE_BREAK_EDITOR', 'head', '', 'line_break', 'textbox', 290)");
        $block_id = $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'blocks_editor', '_MI_SYSTEM_PREFERENCE_BLOCKS_EDITOR', 'dhtmltextarea', '_MI_SYSTEM_PREFERENCE_BLOCKS_EDITOR_DSC', 'select', 'text', 300)");
        $com_id   = $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'comments_editor', '_MI_SYSTEM_PREFERENCE_COMMENTS_EDITOR', 'dhtmltextarea', '_MI_SYSTEM_PREFERENCE_COMMENTS_EDITOR_DSC', 'select', 'text', 310)");
        $main_id  = $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'general_editor', '_MI_SYSTEM_PREFERENCE_GENERAL_EDITOR', 'dhtmltextarea', '_MI_SYSTEM_PREFERENCE_GENERAL_EDITOR_DSC', 'select', 'text', 320)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'redirect', '_MI_SYSTEM_PREFERENCE_REDIRECT', 'admin.php?fct=preferences', '', 'hidden', 'text', 330)");
        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'com_anonpost', '_MI_SYSTEM_PREFERENCE_ANONPOST', '', '', 'hidden', 'text', 340)");
        $jquery_id = $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (1, 0, 'jquery_theme', '_MI_SYSTEM_PREFERENCE_JQUERY_THEME', 'base', '', 'select', 'text', 35)");

        $dbm->insert('config', " (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) VALUES (0, 1, 'redirect_message_ajax', '_MD_AM_CUSTOM_REDIRECT', '1', '_MD_AM_CUSTOM_REDIRECT_DESC', 'yesno', 'int', 12)");

        $xoopsListsFile = XOOPS_ROOT_PATH . '/class/xoopslists.php';
        if (file_exists($xoopsListsFile)) {
            require_once $xoopsListsFile;
        } else {
            $this->logs[] = 'XoopsLists class file not found: ' . $xoopsListsFile;
            return false;
        }

        $editors = XoopsLists::getDirListAsArray(XOOPS_ROOT_PATH . '/class/xoopseditor');
        foreach ($editors as $dir) {
            $dbm->insert('configoption', " (confop_name,confop_value,conf_id) VALUES ('" . $dir . "', '" . $dir . "', " . (int) $block_id . ')');
        }
        foreach ($editors as $dir) {
            $dbm->insert('configoption', " (confop_name,confop_value,conf_id) VALUES ('" . $dir . "', '" . $dir . "', " . (int) $com_id . ')');
        }
        foreach ($editors as $dir) {
            $dbm->insert('configoption', " (confop_name,confop_value,conf_id) VALUES ('" . $dir . "', '" . $dir . "', " . (int) $main_id . ')');
        }
        $icons = XoopsLists::getDirListAsArray(XOOPS_ROOT_PATH . '/modules/system/images/icons');
        foreach ($icons as $dir) {
            $dbm->insert('configoption', " (confop_name,confop_value,conf_id) VALUES ('" . $dir . "', '" . $dir . "', " . (int) $icon_id . ')');
        }
        $breadcrumb = XoopsLists::getDirListAsArray(XOOPS_ROOT_PATH . '/modules/system/images/breadcrumb');
        foreach ($breadcrumb as $dir) {
            $dbm->insert('configoption', " (confop_name,confop_value,conf_id) VALUES ('" . $dir . "', '" . $dir . "', " . (int) $bc_id . ')');
        }
        $jqueryui = XoopsLists::getDirListAsArray(XOOPS_ROOT_PATH . '/modules/system/css/ui');
        foreach ($jqueryui as $dir) {
            $dbm->insert('configoption', " (confop_name,confop_value,conf_id) VALUES ('" . $dir . "', '" . $dir . "', " . (int) $jquery_id . ')');
        }

        return true;
    }

    /**
     * @return bool
     */
    public function apply_templates(): bool
    {
        $versionFile = XOOPS_ROOT_PATH . '/modules/system/xoops_version.php';
        if (file_exists($versionFile)) {
            /** @noinspection PhpIncludeInspection */
            include $versionFile;
        } else {
            $this->logs[] = 'System version file not found: ' . $versionFile;
            return false;
        }

        /** @var array $modversion */
        if (!isset($modversion['templates']) || !is_array($modversion['templates'])) {
            $this->logs[] = 'No templates defined in system version file.';
            return false;
        }

        if (!file_exists($this->dbmanagerFile)) {
            $this->logs[] = 'Database manager file not found: ' . $this->dbmanagerFile;
            return false;
        }
        require_once $this->dbmanagerFile;
        $dbm  = new Db_manager();
        $time = time();
        foreach ($modversion['templates'] as $tplfile) {
            // Admin templates
            $adminTplPath = XOOPS_ROOT_PATH . '/modules/system/templates/admin/' . $tplfile['file'];
            if (isset($tplfile['type']) && $tplfile['type'] === 'admin' && file_exists($adminTplPath) && $fp = fopen($adminTplPath, 'r')) {
                $newtplid  = $dbm->insert('tplfile', " VALUES (0, 1, 'system', 'default', '" . addslashes($tplfile['file']) . "', '" . addslashes($tplfile['description']) . "', " . $time . ', ' . $time . ", 'admin')");
                $tplsource = fread($fp, (int) filesize($adminTplPath));
                fclose($fp);
                $dbm->insert('tplsource', ' (tpl_id, tpl_source) VALUES (' . (int) $newtplid . ", '" . addslashes($tplsource) . "')");
            }
        }

        return true;
    }

    /**
     * Identify a block mangled in the change from XOOPS 2.4.x to 2.5.0
     *
     * The user menu block was element 1 in the old $modversion['blocks'] array, but
     * became element 0 in 2.5.0. This results in the old block not being updated with
     * new settings. We see it in 2.5.8+ where the theme overrides fail because of the
     * extension change from .html to .tpl.
     *
     * Installs are not a problem, just upgrades. This is the only block affected.
     *
     * @return CriteriaElement
     */
    private function strayblockCriteria()
    {
        $criteria = new CriteriaCompo(new Criteria('mid', '1', '='));
        $criteria->add(new Criteria('block_type', 'S', '='));
        $criteria->add(new Criteria('func_num', '1', '='));
        $criteria->add(new Criteria('template', 'system_block_user.html', '='));

        return $criteria;
    }

    /**
     * @return bool
     */
    public function check_strayblock(): bool
    {
        $criteria = $this->strayblockCriteria();
        $count    = Xmf\Database\TableLoad::countRows('newblocks', $criteria);

        return ($count === 0);
    }

    /**
     * @return bool
     */
    public function apply_strayblock(): bool
    {
        $criteria = $this->strayblockCriteria();
        $tables   = new Xmf\Database\Tables();
        $tables->useTable('newblocks');
        $tables->update('newblocks', ['func_num' => '0'], $criteria);

        return $tables->executeQueue(true);
    }
}

return Upgrade_250::class;
