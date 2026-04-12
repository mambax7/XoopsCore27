<?php

/**
 * Upgrader from 2.5.4 to 2.5.5
 *
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package          upgrader
 * @since            2.5.5
 * @author           Taiwen Jiang <phppp@users.sourceforge.net>
 * @author           trabis <lusopoemas@gmail.com>
 */

use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

class Upgrade_255 extends XoopsUpgrade
{
    /**
     * Check if keys already exist
     *
     * @return bool
     */
    public function check_keys(): bool
    {
        $tables['groups_users_link'] = ['uid'];

        foreach ($tables as $table => $keys) {
            $sql = 'SHOW KEYS FROM `' . $this->db->prefix($table) . '`';
            $result = $this->db->query($sql);
            if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
                $this->logs[] = sprintf('check_keys: SHOW KEYS failed for table %s: %s', $table, $this->db->error());
                return false;
            }
            $existing_keys = [];
            while (false !== ($row = $this->db->fetchArray($result))) {
                $existing_keys[] = $row['Key_name'];
            }
            foreach ($keys as $key) {
                if (!in_array($key, $existing_keys)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Apply keys that are missing
     *
     * @return bool
     */
    public function apply_keys(): bool
    {
        $tables['groups_users_link'] = ['uid'];

        foreach ($tables as $table => $keys) {
            $sql = 'SHOW KEYS FROM `' . $this->db->prefix($table) . '`';
            $result = $this->db->query($sql);
            if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
                $this->logs[] = sprintf('apply_keys: SHOW KEYS failed for table %s: %s', $table, $this->db->error());
                return false;
            }
            $existing_keys = [];
            while (false !== ($row = $this->db->fetchArray($result))) {
                $existing_keys[] = $row['Key_name'];
            }
            foreach ($keys as $key) {
                if (!in_array($key, $existing_keys)) {
                    $sql = 'ALTER TABLE `' . $this->db->prefix($table) . "` ADD INDEX `{$key}` (`{$key}`)";
                    if (!$this->db->exec($sql)) {
                        $this->logs[] = sprintf('apply_keys: ALTER TABLE failed for key %s on %s: %s', $key, $table, $this->db->error());

                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check imptotal
     *
     * @return bool
     */
    public function check_imptotal(): bool
    {
        $sql    = 'SELECT `imptotal` FROM `' . $this->db->prefix('banner') . '` WHERE `bid` = 1';
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = 'check_imptotal: query failed: ' . $this->db->error();

            return false;
        }

        $fieldInfo = mysqli_fetch_field_direct($result, 0);
        if (false === $fieldInfo) {
            $this->logs[] = 'check_imptotal: unable to read imptotal column metadata';

            return false;
        }

        return ($fieldInfo->length != 8);
    }

    /**
     * Apply imptotal
     *
     * @return bool
     */
    public function apply_imptotal(): bool
    {
        $sql = 'ALTER TABLE `' . $this->db->prefix('banner') . "` CHANGE `imptotal` `imptotal` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'";
        if (!$this->db->exec($sql)) {
            $this->logs[] = 'apply_imptotal: ALTER TABLE failed: ' . $this->db->error();

            return false;
        }

        return true;
    }

    /**
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['keys', 'imptotal'];
    }
}

return Upgrade_255::class;
