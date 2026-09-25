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
 *  3. emoticons      — register SCEditor's emoticons as smileys: copy each image to
 *                      uploads/smilies and insert its smiles row; codes that already
 *                      exist (an admin's own smiley included) are left alone.
 *  4. editorprefs    — add the Editors preference category (8) and the SCEditor
 *                      preferences with their options, each row checked on its own.
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
            // editorprefs first: the optional emoticon copy can fail on a
            // read-only folder, and the runner stops at the first failure.
            'editorprefs',
            'emoticons',
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
        $missing = $this->missingModeOptions($confId);
        if (null === $missing) {
            $this->logs[] = 'Could not read the configoption table to check the twofactor_mode options';

            return false;
        }

        return [] === $missing;
    }

    /**
     * Insert whichever of the preference row and its options is missing.
     *
     * @return bool true when every row exists afterwards
     */
    public function apply_twofactormode(): bool
    {
        // Config has no unique key for a preference or its options.
        return $this->withLock('config', 'twofactor_mode', fn (): bool => $this->applyModeRows());
    }

    /**
     * Run $work while holding a site-wide lock, so two upgrade requests cannot
     * both see a row missing and insert it twice. DDL in other tasks must not
     * release it. Database + table + purpose are hashed to stay within MySQL's
     * 64-byte lock-name limit.
     *
     * @param string          $table   unprefixed table the work writes to
     * @param string          $purpose lock name suffix, also used in the log lines
     * @param callable(): bool $work    the check-then-insert step
     *
     * @return bool false when the lock is not acquired or released, or $work fails
     */
    private function withLock(string $table, string $purpose, callable $work): bool
    {
        $lock = 'SHA2(CONCAT(DATABASE(), ' . $this->db->quote(':' . $this->db->prefix($table) . ':' . $purpose) . '), 256)';
        $result = $this->db->query('SELECT GET_LOCK(' . $lock . ', 10)');
        $row = $this->db->isResultSet($result) && $result instanceof \mysqli_result ? $this->db->fetchRow($result) : false;
        if (!is_array($row) || 1 !== (int) $row[0]) {
            $this->logs[] = 'Could not acquire the ' . $purpose . ' migration lock; retry the upgrade';

            return false;
        }
        $success = false;
        try {
            $success = $work();
        } finally {
            $result = $this->db->query('SELECT RELEASE_LOCK(' . $lock . ')');
            $row = $this->db->isResultSet($result) && $result instanceof \mysqli_result ? $this->db->fetchRow($result) : false;
            if (!is_array($row) || 1 !== (int) $row[0]) {
                $this->logs[] = 'Could not release the ' . $purpose . ' migration lock';
                $success = false;
            }
        }

        return $success;
    }

    /** Insert missing rows while apply_twofactormode() holds the site lock. */
    private function applyModeRows(): bool
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
     * Scoped to conf_modid = 0 and conf_catid = 1: matching on conf_name alone
     * would let a module preference, or a core row in another category, satisfy
     * the check while include/common.php, which loads only XOOPS_CONF, never sees
     * the preference and the core row is never created.
     *
     * @return int|null
     */
    private function modeConfId(): ?int
    {
        $sql    = 'SELECT `conf_id` FROM `' . $this->db->prefix('config') . '`'
                . " WHERE conf_modid = 0 AND conf_catid = 1 AND conf_name = 'twofactor_mode'";
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
     * @return string[][]|null the missing options, or null when the table could not be read
     * @phpstan-return array<int, array{string, string}>|null
     */
    private function missingModeOptions(int $confId): ?array
    {
        $table   = $this->db->prefix('configoption');
        $missing = [];
        foreach (self::MODE_OPTIONS as $option) {
            $sql    = 'SELECT COUNT(*) FROM `' . $table . '`'
                    . ' WHERE conf_id = ' . $confId
                    . ' AND confop_name = ' . $this->db->quote($option[0])
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
    // =========================================================================
    // Task 3: emoticons
    // =========================================================================

    /**
     * Does every SCEditor emoticon have its smiles row and its image in uploads?
     *
     * @return bool
     */
    public function check_emoticons(): bool
    {
        class_exists('SCEditorEmoticons', false) || require_once XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/class/SCEditorEmoticons.php';
        $missing = \SCEditorEmoticons::missing($this->db);
        if (null === $missing) {
            $this->logs[] = 'Could not read the smiles table to check the SCEditor emoticons';

            return false;
        }

        return [] === $missing;
    }

    /**
     * Copy missing emoticon images and insert missing smiles rows.
     *
     * @return bool true when every emoticon is registered afterwards
     *
     * @throws \mysqli_sql_exception when MySQLi is set to throw and an INSERT fails
     */
    public function apply_emoticons(): bool
    {
        class_exists('SCEditorEmoticons', false) || require_once XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/class/SCEditorEmoticons.php';

        // smiles has no unique key on code; serialize concurrent runs.
        return $this->withLock('smiles', 'emoticons', fn (): bool => \SCEditorEmoticons::install($this->db, $this->logs));
    }

    // =========================================================================
    // Task 4: editorprefs
    // =========================================================================

    /**
     * Do the Editors preference category, every SCEditor preference and all of
     * their options exist?
     *
     * @return bool
     */
    public function check_editorprefs(): bool
    {
        $missing = $this->missingEditorRows();
        if (null === $missing) {
            $this->logs[] = 'The Editors preferences could not be checked';

            return false;
        }

        return [] === $missing;
    }

    /**
     * Insert whichever category, preference or option rows are missing.
     *
     * @return bool true when every row exists afterwards
     *
     * @throws \mysqli_sql_exception when MySQLi is set to throw and an INSERT fails
     */
    public function apply_editorprefs(): bool
    {
        // The config tables have no unique key either.
        return $this->withLock('config', 'editorprefs', fn (): bool => $this->applyEditorRows());
    }

    /** Insert missing rows while apply_editorprefs() holds the site lock. */
    private function applyEditorRows(): bool
    {
        $missing = $this->missingEditorRows();
        if (null === $missing) {
            $this->logs[] = 'The Editors preferences were not inserted';

            return false;
        }
        foreach ($missing as $item) {
            if ('category' === $item['type']) {
                $sql = 'INSERT INTO `' . $this->db->prefix('configcategory') . '` (confcat_id, confcat_name, confcat_order)'
                     . ' VALUES (' . \SCEditorConfig::CATEGORY . ", '_MD_AM_EDITORS', 0)";
            } elseif ('config' === $item['type']) {
                $def = $item['item'];
                $sql = 'INSERT INTO `' . $this->db->prefix('config') . '`'
                     . ' (conf_modid, conf_catid, conf_name, conf_title, conf_value, conf_desc,'
                     . ' conf_formtype, conf_valuetype, conf_order) VALUES (0, ' . \SCEditorConfig::CATEGORY . ', '
                     . $this->db->quote($item['name']) . ', ' . $this->db->quote($def['title']) . ', '
                     . $this->db->quote($def['value']) . ', ' . $this->db->quote($def['desc']) . ', '
                     . $this->db->quote($def['formtype']) . ', ' . $this->db->quote($def['valuetype']) . ', ' . $def['order'] . ')';
            } else {
                // An option's conf_id exists only after its preference row: look it up now.
                $confId = $this->editorConfId($item['name']);
                if (null === $confId || 0 === $confId) {
                    $this->logs[] = sprintf('Could not find the %s preference for its options', $item['name']);

                    return false;
                }
                $sql = 'INSERT INTO `' . $this->db->prefix('configoption') . '` (confop_name, confop_value, conf_id) VALUES ('
                     . $this->db->quote($item['option']) . ', ' . $this->db->quote($item['option']) . ', ' . $confId . ')';
            }
            if (!$this->execOrFail($sql)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rows still to insert, in insert order: the category, then each preference
     * followed by its options.
     *
     * @return array<int, array{type: string, name?: string, item?: array, option?: string}>|null null when a lookup failed
     */
    private function missingEditorRows(): ?array
    {
        class_exists('SCEditorConfig', false) || require_once XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/class/SCEditorConfig.php';
        $missing  = [];
        $foreign  = $this->countRows('configcategory', 'confcat_id = ' . \SCEditorConfig::CATEGORY . " AND confcat_name <> '_MD_AM_EDITORS'");
        if (0 < $foreign) {
            $this->logs[] = sprintf('Preference category %d is already used by another category; the Editors preferences need that ID', \SCEditorConfig::CATEGORY);

            return null;
        }
        $category = null === $foreign ? null : $this->countRows('configcategory', 'confcat_id = ' . \SCEditorConfig::CATEGORY);
        if (null === $category) {
            return null;
        }
        if (0 === $category) {
            $missing[] = ['type' => 'category'];
        }
        foreach (\SCEditorConfig::items() as $name => $item) {
            $confId = $this->editorConfId($name);
            if (null === $confId) {
                return null;
            }
            if (0 === $confId) {
                $missing[] = ['type' => 'config', 'name' => $name, 'item' => $item];
            }
            foreach ($item['options'] as $option) {
                $count = 0 === $confId ? 0 : $this->countRows(
                    'configoption',
                    'conf_id = ' . $confId . ' AND confop_name = ' . $this->db->quote($option)
                    . ' AND confop_value = ' . $this->db->quote($option),
                );
                if (null === $count) {
                    return null;
                }
                if (0 === $count) {
                    $missing[] = ['type' => 'option', 'name' => $name, 'option' => $option];
                }
            }
        }

        return $missing;
    }

    /** conf_id of an Editors preference, 0 when absent, null when the lookup failed. */
    private function editorConfId(string $name): ?int
    {
        $sql    = 'SELECT `conf_id` FROM `' . $this->db->prefix('config') . '`'
                . ' WHERE conf_modid = 0 AND conf_catid = ' . \SCEditorConfig::CATEGORY
                . ' AND conf_name = ' . $this->db->quote($name);
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return null;
        }
        $row = $this->db->fetchRow($result);

        return is_array($row) ? (int) $row[0] : 0;
    }

    /** COUNT(*) of a table under a condition, null when the lookup failed. */
    private function countRows(string $table, string $where): ?int
    {
        $result = $this->db->query('SELECT COUNT(*) FROM `' . $this->db->prefix($table) . '` WHERE ' . $where);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return null;
        }
        $row = $this->db->fetchRow($result);

        return is_array($row) ? (int) $row[0] : null;
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

return Upgrade_274::class;
