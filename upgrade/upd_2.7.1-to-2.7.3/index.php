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
 * Upgrade from 2.7.1 to 2.7.3
 *
 * Carries the schema and configuration changes that a fresh install already gets from
 * install/sql/mysql.structure.sql and install/include/makedata.php. Without this patch an
 * upgraded site silently diverges from a freshly installed one.
 *
 * Tasks (both idempotent, order irrelevant):
 *  1. commentsindex  — add idx_status_created(com_status, com_created) to xoopscomments and
 *                      drop the single-column com_status key it prefixes.
 *  2. onlinetracking — insert the enable_online_tracking core preference.
 *
 * UpgradeControl scans every "*-to-*" directory and asks each patch's check_ methods
 * whether it applies (UpgradeControl.php:229-250) — applicability is NOT decided by
 * comparing version numbers. So these tasks are evaluated even on a site already reporting
 * 2.7.2 or later, which is exactly what is wanted: they are corrective, and both are
 * idempotent. 2.7.2 is already released, so sites sitting on it still pick these up.
 *
 * @category     Upgrade
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package      XOOPS
 * @link         https://xoops.org
 * @since        2.7.3
 * @author       XOOPS Team
 */
class Upgrade_273 extends XoopsUpgrade
{
    /** Name of the composite index the recent-comments block needs. */
    private const COMMENTS_INDEX = 'idx_status_created';

    /** Its columns, in index order. Order matters: reversed, it does not serve the query. */
    private const COMMENTS_INDEX_COLUMNS = ['com_status', 'com_created'];

    /**
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = [
            'commentsindex',
            'onlinetracking',
        ];
        $this->usedFiles = [];
    }

    // =========================================================================
    // Task 1: commentsindex
    // =========================================================================

    /**
     * Does xoopscomments carry the index the recent-comments block needs?
     *
     * The block filters on com_status then orders by com_created. With only the
     * single-column com_status key, MySQL matched tens of thousands of rows and filesorted
     * them to return five. Measured on a 46,403-row table: 541 ms before, 0.5 ms after.
     *
     * @return bool true when idx_status_created exists
     */
    public function check_commentsindex(): bool
    {
        return self::COMMENTS_INDEX_COLUMNS
            === $this->indexColumns($this->db->prefix('xoopscomments'), self::COMMENTS_INDEX);
    }

    /**
     * Add idx_status_created(com_status, com_created) and drop the redundant com_status.
     *
     * com_status is a strict prefix of the new index, so keeping it would only cost writes.
     * It is dropped in a separate statement, and its absence is not treated as failure —
     * an install that never had it, or a re-run of this task, must still pass.
     *
     * @return bool true when the index is present afterwards
     */
    public function apply_commentsindex(): bool
    {
        $table   = $this->db->prefix('xoopscomments');
        $columns = $this->indexColumns($table, self::COMMENTS_INDEX);

        // Derived from the constant, never hand-written: a column list that drifts from
        // COMMENTS_INDEX_COLUMNS would recreate the index in a shape check_ rejects, and
        // the task would re-queue for ever while reintroducing the very bug it fixes.
        $columnList = '`' . implode('`, `', self::COMMENTS_INDEX_COLUMNS) . '`';
        $addIndex   = 'ALTER TABLE `' . $table . '` ADD INDEX `' . self::COMMENTS_INDEX . '` (' . $columnList . ')';

        if (null === $columns) {
            // Could not inspect the schema. Issuing DDL blindly here would either fail on a
            // duplicate index or, worse, appear to succeed while the final verification
            // still cannot confirm anything -- leaving the task pending on every rerun.
            trigger_error(
                'Upgrade 2.7.3: cannot read information_schema.STATISTICS, so the '
                . '`' . self::COMMENTS_INDEX . '` index on `' . $table . '` cannot be verified '
                . 'or safely created. Grant access, or add it by hand: ALTER TABLE `' . $table
                . '` ADD INDEX `' . self::COMMENTS_INDEX . '` (' . $columnList . ');',
                E_USER_WARNING
            );

            return false;
        }

        if (self::COMMENTS_INDEX_COLUMNS !== $columns) {
            // An index of the right NAME but the wrong shape does not serve the query, so
            // drop it before recreating. [] means simply absent, and nothing to drop.
            if ([] !== $columns
                && false === $this->db->exec('ALTER TABLE `' . $table . '` DROP INDEX `' . self::COMMENTS_INDEX . '`')) {
                return false;
            }
            if (false === $this->db->exec($addIndex)) {
                return false;
            }
        }

        if ($this->indexExists($table, 'com_status')) {
            // Not fatal: the composite index is what this task exists to create, and the site
            // is correct with the redundant key still present — it only costs writes. But the
            // schema then differs from a fresh install, so say so loudly rather than silently.
            if (false === $this->db->exec('ALTER TABLE `' . $table . '` DROP INDEX `com_status`')
                || $this->indexExists($table, 'com_status')) {
                trigger_error(
                    sprintf(
                        'Upgrade 2.7.3: could not drop the redundant `com_status` index on `%s`. '
                        . 'The new idx_status_created index is in place and the site is correct, '
                        . 'but this table now differs from a fresh install. Drop it by hand: '
                        . 'ALTER TABLE `%s` DROP INDEX `com_status`;',
                        $table,
                        $table
                    ),
                    E_USER_WARNING
                );
            }
        }

        // Verified by SHAPE, not by name, and identical to check_commentsindex() -- a task
        // that reports success on a weaker condition than its own check would re-queue for
        // ever.
        return self::COMMENTS_INDEX_COLUMNS === $this->indexColumns($table, self::COMMENTS_INDEX);
    }

    // =========================================================================
    // Task 2: onlinetracking
    // =========================================================================

    /**
     * Is the "Track who is online?" preference present?
     *
     * Visitor tracking moved out of the Who-is-Online block and into a system preload, so
     * it now runs on every request. That needs a real off switch rather than relying on
     * the block's group permissions, which only ever produced a partial sample.
     *
     * @return bool true when the config row exists
     */
    public function check_onlinetracking(): bool
    {
        return $this->configExists('enable_online_tracking');
    }

    /**
     * Insert the preference, defaulting to enabled to preserve current behaviour.
     *
     * @return bool true when the row exists afterwards
     */
    public function apply_onlinetracking(): bool
    {
        if ($this->configExists('enable_online_tracking')) {
            return true;
        }

        $table = $this->db->prefix('config');
        $sql   = 'INSERT INTO `' . $table . '`'
               . ' (conf_modid, conf_catid, conf_name, conf_title, conf_value, conf_desc,'
               . '  conf_formtype, conf_valuetype, conf_order)'
               . " VALUES (0, 1, 'enable_online_tracking', '_MD_AM_ONLINETRACKING', '1',"
               . " '_MD_AM_ONLINETRACKINGDSC', 'yesno', 'int', 45)";

        if (false === $this->db->exec($sql)) {
            return false;
        }

        return $this->configExists('enable_online_tracking');
    }

    // =========================================================================
    // helpers
    // =========================================================================

    /**
     * Which columns does $indexName cover on $table, in index order?
     *
     * Three distinct answers, which the callers must not conflate:
     *   null  the question could not be answered (information_schema restricted, proxy
     *         rejected the query). NEVER treat this as "absent" and issue blind DDL.
     *   []    the index does not exist.
     *   [...] the column names, ordered by SEQ_IN_INDEX.
     *
     * Column order matters: an index named idx_status_created that happens to cover
     * (com_created, com_status) does not serve "com_status = ? ORDER BY com_created" at
     * all, so checking the name alone would report a performance fix that is not present.
     *
     * @param  string $table     fully prefixed table name
     * @param  string $indexName index to inspect
     * @return array<int, string>|null
     */
    protected function indexColumns(string $table, string $indexName): ?array
    {
        $sql = 'SELECT COLUMN_NAME FROM information_schema.STATISTICS'
             . ' WHERE TABLE_SCHEMA = DATABASE()'
             . " AND TABLE_NAME = '" . $this->db->escape($table) . "'"
             . " AND INDEX_NAME = '" . $this->db->escape($indexName) . "'"
             . ' ORDER BY SEQ_IN_INDEX';

        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            return null;
        }
        // isResultSet() above already proved this is a result set, but static analysis
        // cannot narrow through a helper, so state it explicitly.
        /** @var mysqli_result $result */
        $columns = [];
        while (false !== ($row = $this->db->fetchRow($result))) {
            if (is_array($row) && isset($row[0])) {
                $columns[] = (string) $row[0];
            }
        }

        return $columns;
    }

    /**
     * Does $indexName exist on $table, whatever its shape?
     *
     * Returns false when the question cannot be answered — a check_ that cannot verify
     * must not report success.
     *
     * @param  string $table     fully prefixed table name
     * @param  string $indexName index to look for
     * @return bool
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        $columns = $this->indexColumns($table, $indexName);

        return is_array($columns) && [] !== $columns;
    }

    /**
     * Does a CORE config row with this name exist?
     *
     * Scoped to conf_modid = 0 because that is what apply_onlinetracking() inserts. Matching
     * on conf_name alone would let any module preference of the same name satisfy the check,
     * so the core row would never be created and the preference would silently go missing.
     *
     * @param  string $name conf_name
     * @return bool true when the core row exists
     */
    protected function configExists(string $name): bool
    {
        $sql = 'SELECT COUNT(*) FROM `' . $this->db->prefix('config') . '`'
             . ' WHERE conf_modid = 0'
             . " AND conf_name = '" . $this->db->escape($name) . "'";

        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            return false;
        }
        // isResultSet() above already proved this is a result set, but static analysis
        // cannot narrow through a helper, so state it explicitly.
        /** @var mysqli_result $result */
        $row = $this->db->fetchRow($result);

        return is_array($row) && (int)$row[0] > 0;
    }
}

return Upgrade_273::class;
