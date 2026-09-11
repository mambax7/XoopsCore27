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
 *                      its two options; each row is checked and inserted on its own so
 *                      an interrupted run resumes where it stopped.
 *
 * The order is deliberate and must stay: common.php treats the presence of the
 * twofactor_mode row in the database-loaded configuration as the "installed" signal,
 * so the row must never exist without the table. Both tasks are idempotent; both
 * report a failed lookup instead of treating it as "absent", and neither issues DDL
 * or an INSERT it could not first verify the need for.
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
    /** The select options of twofactor_mode: language constant and stored value. */
    private const MODE_OPTIONS = [
        ['_MD_AM_TWOFACTORMODE_OFF', 'off'],
        ['_MD_AM_TWOFACTORMODE_OPTIONAL', 'optional'],
    ];

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
     * Asks information_schema again first: when that cannot be read, no DDL is
     * issued and the check's message stands.
     *
     * @return bool true on success
     */
    public function apply_user2fatable(): bool
    {
        $table  = $this->db->prefix('user_2fa');
        $exists = $this->tableExists($table);
        if (true === $exists) {
            return true;
        }
        if (null === $exists) {
            $this->logs[] = 'Could not read information_schema; the user_2fa table was not created';

            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
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
     * Do the core twofactor_mode preference and both of its options exist?
     *
     * @return bool
     */
    public function check_twofactormode(): bool
    {
        $confId = $this->modeConfId();
        if (null === $confId) {
            $this->logs[] = 'Could not read the config table to check for the twofactor_mode preference';

            return false;
        }
        if (0 === $confId) {
            return false;
        }

        return [] === $this->missingModeOptions($confId);
    }

    /**
     * Insert whichever of the preference row and its options is missing.
     *
     * @return bool true when every row exists afterwards
     */
    public function apply_twofactormode(): bool
    {
        $confId = $this->modeConfId();
        if (null === $confId) {
            $this->logs[] = 'Could not read the config table; the twofactor_mode preference was not inserted';

            return false;
        }
        if (0 === $confId) {
            $sql = 'INSERT INTO `' . $this->db->prefix('config') . '`'
                 . ' (conf_modid, conf_catid, conf_name, conf_title, conf_value, conf_desc,'
                 . ' conf_formtype, conf_valuetype, conf_order)'
                 . " VALUES (0, 1, 'twofactor_mode', '_MD_AM_TWOFACTORMODE', 'off',"
                 . " '_MD_AM_TWOFACTORMODEDSC', 'select', 'text', 46)";
            if (!$this->execOrFail($sql)) {
                return false;
            }
            $confId = $this->modeConfId();
            if (null === $confId || 0 === $confId) {
                $this->logs[] = 'The twofactor_mode preference row was not found after it was inserted';

                return false;
            }
        }

        $missing = $this->missingModeOptions($confId);
        if (null === $missing) {
            $this->logs[] = 'Could not read the configoption table; the twofactor_mode options were not inserted';

            return false;
        }
        $options = $this->db->prefix('configoption');
        foreach ($missing as [$name, $value]) {
            $sql = 'INSERT INTO `' . $options . '` (confop_name, confop_value, conf_id)'
                 . " VALUES ('{$name}', '{$value}', {$confId})";
            if (!$this->execOrFail($sql)) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // helpers
    // =========================================================================

    /**
     * conf_id of the CORE twofactor_mode row.
     *
     * Tri-state, like tableExists(): the row's conf_id, 0 when the row is absent,
     * null when the config table could not be read. The base class's getDbValue()
     * folds "absent" and "unreadable" into one false, and the config table has no
     * unique key on (conf_modid, conf_name), so a read failure taken as "absent"
     * would insert a duplicate core preference.
     *
     * Scoped to conf_modid = 0: matching on conf_name alone would let a module
     * preference of the same name satisfy the check and the core row would never
     * be created.
     *
     * @return int|null
     */
    private function modeConfId(): ?int
    {
        $sql    = 'SELECT `conf_id` FROM `' . $this->db->prefix('config') . '`'
                . " WHERE conf_modid = 0 AND conf_name = 'twofactor_mode'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return null;
        }
        $row = $this->db->fetchRow($result);

        return is_array($row) ? (int) $row[0] : 0;
    }

    /**
     * Which of the twofactor_mode options are missing for this conf_id?
     *
     * @param int $confId conf_id of the preference row
     * @return array<int, array{string, string}>|null the missing options, or null when the table could not be read
     */
    private function missingModeOptions(int $confId): ?array
    {
        $table   = $this->db->prefix('configoption');
        $missing = [];
        foreach (self::MODE_OPTIONS as $option) {
            $sql    = 'SELECT COUNT(*) FROM `' . $table . '`'
                    . ' WHERE conf_id = ' . $confId
                    . ' AND confop_value = ' . $this->db->quote($option[1]);
            $result = $this->db->query($sql);
            if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
                return null;
            }
            $row = $this->db->fetchRow($result);
            if (!is_array($row)) {
                // COUNT(*) always yields one row; none means the read failed.
                return null;
            }
            if ((int) $row[0] === 0) {
                $missing[] = $option;
            }
        }

        return $missing;
    }

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
