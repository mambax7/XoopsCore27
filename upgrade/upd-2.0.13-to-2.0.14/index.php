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

/**
 * Upgrade from 2.0.13 to 2.0.14
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since     2.0.14
 * @author    XOOPS Team
 */
class Upgrade_2014 extends XoopsUpgrade
{
    /**
     * @return bool
     */
    public function check_0523patch(): bool
    {
        $mainfile = XOOPS_ROOT_PATH . '/mainfile.php';
        $lines    = @file($mainfile);
        if (false === $lines) {
            return false;
        }
        foreach ($lines as $line) {
            if (strpos($line, "\$_REQUEST[\$bad_global]") !== false) {
                // Patch found: do not apply again
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function apply_0523patch(): bool
    {
        $patchCode = "
    foreach ( array('GLOBALS', '_SESSION', 'HTTP_SESSION_VARS', '_GET', 'HTTP_GET_VARS', '_POST', 'HTTP_POST_VARS', '_COOKIE', 'HTTP_COOKIE_VARS', '_REQUEST', '_SERVER', 'HTTP_SERVER_VARS', '_ENV', 'HTTP_ENV_VARS', '_FILES', 'HTTP_POST_FILES', 'xoopsDB', 'xoopsUser', 'xoopsUserId', 'xoopsUserGroups', 'xoopsUserIsAdmin', 'xoopsConfig', 'xoopsOption', 'xoopsModule', 'xoopsModuleConfig', 'xoopsRequestUri') as \$bad_global ) {
        if ( isset( \$_REQUEST[\$bad_global] ) ) {
            header( 'Location: '.XOOPS_URL.'/' );
            exit();
        }
    }
";
        $manual    = '<h2>' . _MANUAL_INSTRUCTIONS . "</h2>\n<p>" . sprintf(_COPY_RED_LINES, 'mainfile.php') . "</p>
<pre style='border:1px solid black;width:650px;overflow:auto;'><span style='color:#ff0000;font-weight:bold;'>$patchCode</span>
    if (!isset(\$xoopsOption['nocommon']) && XOOPS_ROOT_PATH != '') {
        include XOOPS_ROOT_PATH.\"/include/common.php\";
    }
</pre>";
        $mainfile  = XOOPS_ROOT_PATH . '/mainfile.php';
        $lines     = @file($mainfile);
        if (false === $lines) {
            printf(_FAILED_PATCH . '<br>', 'mainfile.php');
            echo $manual;

            return false;
        }

        $insert         = -1;
        $matchProtector = '/modules/protector/include/precheck.inc.php';
        $matchDefault   = "\$xoopsOption['nocommon']";

        foreach ($lines as $k => $line) {
            if (strpos($line, "\$_REQUEST[\$bad_global]") !== false) {
                // Patch found: do not apply again
                $insert = -2;
                break;
            }
            if (strpos($line, $matchProtector) || strpos($line, $matchDefault)) {
                $insert = $k;
                break;
            }
        }
        if ($insert == -1) {
            printf(_FAILED_PATCH . '<br>', 'mainfile.php');
            echo $manual;

            return false;
        } elseif ($insert != -2) {
            if (!is_writable($mainfile)) {
                echo 'mainfile.php is read-only. Please allow the server to write to this file, or apply the patch manually';
                echo $manual;

                return false;
            } else {
                $fp = fopen($mainfile, 'wt');
                if (!$fp) {
                    echo 'Error opening mainfile.php, please apply the patch manually.';
                    echo $manual;

                    return false;
                } else {
                    $newline = PHP_EOL;
                    $prepend = implode('', array_slice($lines, 0, $insert));
                    $append  = implode('', array_slice($lines, $insert));

                    $content = $prepend . $patchCode . $append;
                    $content = str_replace(["\r\n", "\n"], $newline, $content);

                    fwrite($fp, $content);
                    fclose($fp);
                    echo 'Patch successfully applied';
                }
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    public function check_auth_db(): bool
    {
        $value = $this->getDbValue('config', 'conf_id', "`conf_name` = 'ldap_provisionning' AND `conf_catid` = " . XOOPS_CONF_AUTH);

        return (bool) $value;
    }

    /**
     * @param string $sql
     */
    protected function query(string $sql): bool
    {
        if ($this->db->exec($sql)) {
            return true;
        }

        $this->logs[] = $this->db->error();

        return false;
    }

    /**
     * @return bool
     */
    public function apply_auth_db(): bool
    {
        $cat = $this->getDbValue('configcategory', 'confcat_id', "`confcat_name` ='_MD_AM_AUTHENTICATION'");
        if ($cat !== false && $cat != XOOPS_CONF_AUTH) {
            // 2.2 downgrade bug: LDAP cat is here but has a catid of 0
            if (
                !$this->query('DELETE FROM ' . $this->db->prefix('configcategory') . " WHERE `confcat_name` ='_MD_AM_AUTHENTICATION' ")
                || !$this->query('DELETE FROM ' . $this->db->prefix('config') . " WHERE `conf_modid`=0 AND `conf_catid` = $cat")
            ) {
                return false;
            }
            $cat = false;
        }
        if (empty($cat)) {
            // Insert config category ( always XOOPS_CONF_AUTH = 7 )
            if (!$this->query(' INSERT INTO ' . $this->db->prefix('configcategory') . " (confcat_id,confcat_name) VALUES (7,'_MD_AM_AUTHENTICATION')")) {
                return false;
            }
        }
        // Insert config values
        $table = $this->db->prefix('config');
        $data  = [
            'auth_method'              => "'_MD_AM_AUTHMETHOD', 'xoops', '_MD_AM_AUTHMETHODDESC', 'select', 'text', 1",
            'ldap_port'                => "'_MD_AM_LDAP_PORT', '389', '_MD_AM_LDAP_PORT', 'textbox', 'int', 2 ",
            'ldap_server'              => "'_MD_AM_LDAP_SERVER', 'your directory server', '_MD_AM_LDAP_SERVER_DESC', 'textbox', 'text', 3 ",
            'ldap_manager_dn'          => "'_MD_AM_LDAP_MANAGER_DN', 'manager_dn', '_MD_AM_LDAP_MANAGER_DN_DESC', 'textbox', 'text', 5",
            'ldap_manager_pass'        => "'_MD_AM_LDAP_MANAGER_PASS', 'manager_pass', '_MD_AM_LDAP_MANAGER_PASS_DESC', 'textbox', 'text', 6",
            'ldap_version'             => "'_MD_AM_LDAP_VERSION', '3', '_MD_AM_LDAP_VERSION_DESC', 'textbox', 'text', 7",
            'ldap_users_bypass'        => "'_MD_AM_LDAP_USERS_BYPASS', '" . serialize(['admin']) . "', '_MD_AM_LDAP_USERS_BYPASS_DESC', 'textarea', 'array', 8",
            'ldap_loginname_asdn'      => "'_MD_AM_LDAP_LOGINNAME_ASDN', 'uid_asdn', '_MD_AM_LDAP_LOGINNAME_ASDN_D', 'yesno', 'int', 9",
            'ldap_loginldap_attr'      => "'_MD_AM_LDAP_LOGINLDAP_ATTR', 'uid', '_MD_AM_LDAP_LOGINLDAP_ATTR_D', 'textbox', 'text', 10",
            'ldap_filter_person'       => "'_MD_AM_LDAP_FILTER_PERSON', '', '_MD_AM_LDAP_FILTER_PERSON_DESC', 'textbox', 'text', 11",
            'ldap_domain_name'         => "'_MD_AM_LDAP_DOMAIN_NAME', 'mydomain', '_MD_AM_LDAP_DOMAIN_NAME_DESC', 'textbox', 'text', 12",
            'ldap_provisionning'       => "'_MD_AM_LDAP_PROVIS', '0', '_MD_AM_LDAP_PROVIS_DESC', 'yesno', 'int', 13",
            'ldap_provisionning_group' => "'_MD_AM_LDAP_PROVIS_GROUP', 'a:1:{i:0;s:1:\"2\";}', '_MD_AM_LDAP_PROVIS_GROUP_DSC', 'group_multi', 'array', 14",
            'ldap_mail_attr'           => "'_MD_AM_LDAP_MAIL_ATTR', 'mail', '_MD_AM_LDAP_MAIL_ATTR_DESC', 'textbox', 'text', 15",
            'ldap_givenname_attr'      => "'_MD_AM_LDAP_GIVENNAME_ATTR', 'givenname', '_MD_AM_LDAP_GIVENNAME_ATTR_DSC', 'textbox', 'text', 16",
            'ldap_surname_attr'        => "'_MD_AM_LDAP_SURNAME_ATTR', 'sn', '_MD_AM_LDAP_SURNAME_ATTR_DESC', 'textbox', 'text', 17",
        ];
        foreach ($data as $name => $values) {
            if (!$this->getDbValue('config', 'conf_id', "`conf_modid`=0 AND `conf_catid`=7 AND `conf_name`='$name'")) {
                if (
                    !$this->query(
                        "INSERT INTO `$table` (conf_modid,conf_catid,conf_name,conf_title,conf_value,conf_desc,conf_formtype,conf_valuetype,conf_order) "
                        . "VALUES ( 0,7,'$name',$values)"
                    )
                ) {
                    return false;
                }
            }
        }
        // Insert auth_method config options
        $id    = $this->getDbValue('config', 'conf_id', "`conf_modid`=0 AND `conf_catid`=7 AND `conf_name`='auth_method'");
        if (false === $id) {
            $this->logs[] = 'Unable to locate auth_method configuration row.';

            return false;
        }
        $table = $this->db->prefix('configoption');
        $data  = [
            '_MD_AM_AUTH_CONFOPTION_XOOPS' => 'xoops',
            '_MD_AM_AUTH_CONFOPTION_LDAP'  => 'ldap',
            '_MD_AM_AUTH_CONFOPTION_AD'    => 'ad',
        ];
        if (!$this->query("DELETE FROM `$table` WHERE `conf_id`=$id")) {
            return false;
        }
        foreach ($data as $name => $value) {
            if (!$this->query("INSERT INTO `$table` (confop_name, confop_value, conf_id) VALUES ('$name', '$value', $id)")) {
                return false;
            }
        }

        return true;
    }

    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['auth_db'];
        // $this->usedFiles = array('mainfile.php'); /* '0523patch' not run */
    }
}

return Upgrade_2014::class;
