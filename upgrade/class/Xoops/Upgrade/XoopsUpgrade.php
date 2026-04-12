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

namespace Xoops\Upgrade;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

use XoopsMySQLDatabase;

/**
 * XOOPS Upgrade base class
 *
 * Namespaced, DI-enabled replacement for the legacy upgrade/class/abstract.php.
 * Concrete upgrade patches extend this class and implement check_{task}() and
 * apply_{task}() pairs for each entry in $tasks.
 *
 * @category  Xoops\Upgrade
 * @package   Xoops
 * @author    Taiwen Jiang <phppp@users.sourceforge.net>
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
abstract class XoopsUpgrade
{
    /** @var XoopsMySQLDatabase $db database connection */
    protected XoopsMySQLDatabase $db;

    /** @var UpgradeControl $control upgrade controller */
    protected UpgradeControl $control;

    /** @var string[] $usedFiles files required to be writable by this patch */
    public array $usedFiles = [];

    /** @var string[] $tasks task identifiers this patch provides check_/apply_ pairs for */
    public array $tasks = [];

    /** @var string[] $logs accumulated log messages */
    public array $logs = [];

    /** @var string|null $languageFolder language folder name, or null when not loaded */
    public ?string $languageFolder = null;

    /**
     * Constructor.
     *
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade controller for language loading
     * @param string|null        $dirname optional language directory name to load immediately
     */
    public function __construct(
        XoopsMySQLDatabase $db,
        UpgradeControl $control,
        ?string $dirname = null,
    ) {
        $this->db = $db;
        $this->control = $control;
        if ($dirname !== null) {
            $this->loadLanguage($dirname);
        }
    }

    /**
     * Check whether this patch has been applied and return a status object.
     *
     * @return PatchStatus status describing pending tasks and required files
     */
    public function isApplied(): PatchStatus
    {
        return new PatchStatus($this);
    }

    /**
     * Apply all pending tasks for this patch.
     *
     * Iterates over tasks returned by isApplied() and calls the corresponding
     * apply_{task}() method. Returns false on the first failure.
     *
     * @return bool true if all tasks applied successfully, false on first failure
     */
    public function apply(): bool
    {
        $patchStatus = $this->isApplied();
        $tasks = $patchStatus->tasks;
        foreach ($tasks as $task) {
            $res = $this->{"apply_{$task}"}();
            if (!$res) {
                return false;
            }
        }
        return true;
    }

    /**
     * Load language strings for this patch via the upgrade controller.
     *
     * @param  string $dirname language directory name
     * @return void
     */
    protected function loadLanguage(string $dirname): void
    {
        $this->control->loadLanguage($dirname);
    }

    /**
     * Return all accumulated log messages joined with HTML line breaks.
     *
     * @return string log output, or empty string when no messages have been logged
     */
    public function message(): string
    {
        return empty($this->logs) ? '' : implode('<br>', $this->logs);
    }

    /**
     * Append a plain message to the log.
     *
     * @param  string $message message to record
     * @return void
     */
    protected function log(string $message): void
    {
        $this->logs[] = $message;
    }

    /**
     * Append a formatted error message to the log, wrapped in a danger span.
     *
     * @param  string $format sprintf format string
     * @param  mixed  ...$args values to interpolate into the format string
     * @return void
     */
    protected function logError(string $format, mixed ...$args): void
    {
        $this->logs[] = sprintf('<span class="text-danger">' . $format . '</span>', ...$args);
    }

    /**
     * Append a formatted success message to the log, wrapped in a success span.
     *
     * @param  string $format sprintf format string
     * @param  mixed  ...$args values to interpolate into the format string
     * @return void
     */
    protected function logSuccess(string $format, mixed ...$args): void
    {
        $this->logs[] = sprintf('<span class="text-success">' . $format . '</span>', ...$args);
    }

    /**
     * Retrieve a single field value from the database.
     *
     * Uses $this->db so callers do not need to pass the database object.
     *
     * @param  string $table     unprefixed table name
     * @param  string $field     column name to SELECT
     * @param  string $condition optional WHERE clause (without the WHERE keyword)
     * @return mixed             first column of the first row, or false when not found
     */
    protected function getDbValue(string $table, string $field, string $condition = ''): mixed
    {
        $table = $this->db->prefix($table);
        $sql   = "SELECT `{$field}` FROM `{$table}`";
        if ($condition) {
            $sql .= " WHERE {$condition}";
        }
        $result = $this->db->query($sql);
        if ($this->db->isResultSet($result) && ($result instanceof \mysqli_result)) {
            $row = $this->db->fetchRow($result);
            if ($row) {
                return $row[0];
            }
        }
        return false;
    }
}
