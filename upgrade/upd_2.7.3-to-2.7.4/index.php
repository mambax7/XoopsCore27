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

use Xoops\Upgrade\UpgradeControl;
use Xoops\Upgrade\XoopsUpgrade;

/**
 * Upgrade from 2.7.3 to 2.7.4
 *
 * Carries the two-factor authentication storage that a fresh install gets from
 * install/sql/mysql.structure.sql and install/include/makedata.php.
 *
 * Tasks, in this order:
 *  1. user2fatable   — create the user_2fa table (InnoDB, utf8mb4, no foreign key).
 *  2. twofactormode  — insert the twofactor_mode core preference, default 'off', with
 *                      its two options.
 *
 * The order is deliberate and must stay: common.php treats the presence of the
 * twofactor_mode row in the database-loaded configuration as the "installed" signal,
 * so the row must never exist without the table. Both tasks are idempotent; both
 * report an information_schema failure instead of treating it as "absent".
 *
 * UpgradeControl scans every "*-to-*" directory and asks each patch's check_ methods
 * whether it applies — applicability is not decided by comparing version numbers.
 *
 * @category     Upgrade
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package      XOOPS
 * @link         https://xoops.org
 * @since        2.7.4
 * @author       XOOPS Team
 */
class Upgrade_274 extends XoopsUpgrade
{
    /**
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = [
            'user2fatable',
            'twofactormode',
        ];
    }

    // =========================================================================
    // Task 1: user2fatable
    // =========================================================================

    /**
     * Does the user_2fa table exist?
     *
     * @return bool true only when information_schema says it does
     */
    public function check_user2fatable(): bool
    {
        $exists = $this->tableExists($this->db->prefix('user_2fa'));
        if (null === $exists) {
            $this->logs[] = 'Could not read information_schema to check for the user_2fa table';
        }

        return true === $exists;
    }

    /**
     * Create the user_2fa table with the fresh-install shape.
     *
     * @return bool true on success
     */
    public function apply_user2fatable(): bool
    {
        $table = $this->db->prefix('user_2fa');
        $sql   = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `uid`             mediumint unsigned NOT NULL,
            `state`           varchar(10)        NOT NULL,
            `method`          varchar(16)        NOT NULL DEFAULT 'totp',
            `secret`          varbinary(255)     NULL,
            `confirmed_at`    int unsigned       NOT NULL DEFAULT 0,
            `last_counter`    bigint unsigned    NOT NULL DEFAULT 0,
            `failed_attempts` smallint unsigned  NOT NULL DEFAULT 0,
            `locked_until`    int unsigned       NOT NULL DEFAULT 0,
            `generation`      char(32)           NOT NULL,
            PRIMARY KEY (`uid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        return $this->execOrFail($sql);
    }

    // =========================================================================
    // Task 2: twofactormode
    // =========================================================================

    /**
     * Does the core twofactor_mode preference exist?
     *
     * @return bool
     */
    public function check_twofactormode(): bool
    {
        return $this->configExists('twofactor_mode');
    }

    /**
     * Insert the preference, paused ('off'), and its two options.
     *
     * @return bool true when the row exists afterwards
     */
    public function apply_twofactormode(): bool
    {
        if ($this->configExists('twofactor_mode')) {
            return true;
        }

        $sql = 'INSERT INTO `' . $this->db->prefix('config') . '`'
             . ' (conf_modid, conf_catid, conf_name, conf_title, conf_value, conf_desc,'
             . ' conf_formtype, conf_valuetype, conf_order)'
             . " VALUES (0, 1, 'twofactor_mode', '_MD_AM_TWOFACTORMODE', 'off',"
             . " '_MD_AM_TWOFACTORMODEDSC', 'select', 'text', 46)";
        if (!$this->execOrFail($sql)) {
            return false;
        }

        $confId = (int) $this->getDbValue('config', 'conf_id', "conf_modid = 0 AND conf_name = 'twofactor_mode'");
        if ($confId <= 0) {
            $this->logs[] = 'The twofactor_mode preference row was not found after it was inserted';

            return false;
        }

        $options = $this->db->prefix('configoption');
        foreach ([['_MD_AM_TWOFACTORMODE_OFF', 'off'], ['_MD_AM_TWOFACTORMODE_OPTIONAL', 'optional']] as [$name, $value]) {
            $sql = 'INSERT INTO `' . $options . '` (confop_name, confop_value, conf_id)'
                 . " VALUES ('{$name}', '{$value}', {$confId})";
            if (!$this->execOrFail($sql)) {
                return false;
            }
        }

        return $this->configExists('twofactor_mode');
    }

    // =========================================================================
    // helpers
    // =========================================================================

    /**
     * Does a table exist?
     *
     * Tri-state so callers can tell "absent" from "could not ask":
     *   true  → the table exists
     *   false → the table is absent
     *   null  → information_schema could not be read (log, never issue blind DDL)
     *
     * @param string $table fully prefixed table name
     * @return bool|null
     */
    private function tableExists(string $table): ?bool
    {
        $sql    = "SELECT 1 FROM `information_schema`.`TABLES`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND `TABLE_NAME` = " . $this->db->quote($table)
                . " LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return null;
        }

        return (bool) $this->db->fetchArray($result);
    }

    /**
     * Does a CORE config row with this name exist?
     *
     * Scoped to conf_modid = 0: matching on conf_name alone would let a module
     * preference of the same name satisfy the check and the core row would never
     * be created.
     *
     * @param  string $name conf_name
     * @return bool true when the core row exists
     */
    private function configExists(string $name): bool
    {
        $sql = 'SELECT COUNT(*) FROM `' . $this->db->prefix('config') . '`'
             . ' WHERE conf_modid = 0'
             . " AND conf_name = '" . $this->db->escape($name) . "'";

        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);

        return is_array($row) && (int) $row[0] > 0;
    }

    /**
     * Run a write and log the failure.
     *
     * @param string $sql statement
     * @return bool
     */
    private function execOrFail(string $sql): bool
    {
        if ($this->db->exec($sql)) {
            return true;
        }

        $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

        return false;
    }
}

return Upgrade_274::class;
