<?php

/**
 * Upgrader from 2.5.5 to 2.5.6
 *
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package          upgrader
 * @since            2.5.6
 * @author           XOOPS Team
 */

use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

class Upgrade_256 extends XoopsUpgrade
{
    /**
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['com_user', 'com_email', 'com_url'];
    }

    /**
     * Check if Fast Comment fields already exist
     *
     * @return bool
     */
    public function check_com_user(): bool
    {
        $sql = 'SHOW COLUMNS FROM ' . $this->db->prefix('xoopscomments') . " LIKE 'com_user'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = 'check_com_user: ' . $this->db->error();
            return false;
        }

        return ($this->db->getRowsNum($result) > 0);
    }

    /**
     * @return bool
     */
    public function check_com_email(): bool
    {
        $sql = 'SHOW COLUMNS FROM ' . $this->db->prefix('xoopscomments') . " LIKE 'com_email'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = 'check_com_email: ' . $this->db->error();
            return false;
        }

        return ($this->db->getRowsNum($result) > 0);
    }

    /**
     * @return bool
     */
    public function check_com_url(): bool
    {
        $sql = 'SHOW COLUMNS FROM ' . $this->db->prefix('xoopscomments') . " LIKE 'com_url'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = 'check_com_url: ' . $this->db->error();
            return false;
        }

        return ($this->db->getRowsNum($result) > 0);
    }

    /**
     * @return bool
     */
    public function apply_com_user(): bool
    {
        $sql = 'ALTER TABLE ' . $this->db->prefix('xoopscomments') . ' ADD `com_user` VARCHAR( 60 ) NOT NULL AFTER `com_uid`, ADD INDEX ( `com_user` )';
        if (!$this->db->exec($sql)) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function apply_com_email(): bool
    {
        $sql = 'ALTER TABLE ' . $this->db->prefix('xoopscomments') . ' ADD `com_email` VARCHAR( 60 ) NOT NULL AFTER `com_user`, ADD INDEX ( `com_email` )';
        if (!$this->db->exec($sql)) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function apply_com_url(): bool
    {

        //$this->query( "ALTER TABLE `xoopscomments` ADD `com_user` VARCHAR( 60 ) NOT NULL AFTER `com_uid`, ADD INDEX ( `com_url` )" );

        $sql = 'ALTER TABLE ' . $this->db->prefix('xoopscomments') . ' ADD `com_url` VARCHAR( 60 ) NOT NULL AFTER `com_email` ';
        if (!$this->db->exec($sql)) {
            return false;
        }

        return true;
    }
}

return Upgrade_256::class;
