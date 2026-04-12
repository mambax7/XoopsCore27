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
 * Upgrade from 2.0.16 to 2.0.17
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since     2.0.17
 * @author    XOOPS Team
 */
class Upgrade_2017 extends XoopsUpgrade
{
    /**
     * @return bool
     */
    public function check_auth_db(): bool
    {
        $value = $this->getDbValue('config', 'conf_id', "`conf_name` = 'ldap_use_TLS' AND `conf_catid` = " . XOOPS_CONF_AUTH);

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
        // Insert config values
        $table = $this->db->prefix('config');
        $data  = [
            'ldap_use_TLS' => "'_MD_AM_LDAP_USETLS', '0', '_MD_AM_LDAP_USETLS_DESC', 'yesno', 'int', 21",
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

        return true;
    }

    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['auth_db'];
    }
}

return Upgrade_2017::class;
