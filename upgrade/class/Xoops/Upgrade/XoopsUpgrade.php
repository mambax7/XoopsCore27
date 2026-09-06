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

    /**
     * Tasks excluded from the post-apply re-check in apply().
     *
     * List a task here only when its check_{task}() reads state fixed at request
     * start (a constant from mainfile.php or include/license.php, an
     * UpgradeControl flag) and so cannot observe its own apply_{task}() until
     * the next request. Every other task is verified immediately.
     *
     * @var string[]
     */
    protected array $noRecheck = [];

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
     * apply_{task}() method. Returns false on the first failure, naming the
     * task in the log. A task that throws is reported the same way instead of
     * taking the whole wizard down.
     *
     * After the loop the checks are run once more. An apply_{task}() that
     * returns true without satisfying its own check_{task}() would otherwise
     * re-queue this patch on every request with no visible error (issue #183).
     *
     * @return bool true if all tasks applied and every check now passes
     */
    public function apply(): bool
    {
        try {
            $tasks = $this->isApplied()->tasks;
        } catch (\Throwable $e) {
            // PatchStatus already names the check in the message.
            $this->logError('%s', $this->escapeForLog($e->getMessage()));
            return false;
        }
        foreach ($tasks as $task) {
            try {
                $res = $this->{"apply_{$task}"}();
            } catch (\Throwable $e) {
                $this->logError('Task %s threw %s: %s', $task, get_class($e), $this->escapeForLog($e->getMessage()));
                return false;
            }
            if (!$res) {
                $this->logError('Task %s failed', $task);
                return false;
            }
        }

        // Verify by calling only the checks that can observe their own apply_;
        // a task in $noRecheck is not invoked again at all.
        $pending = [];
        foreach (array_diff($this->tasks, $this->noRecheck) as $task) {
            try {
                $applied = (bool) $this->{"check_{$task}"}();
            } catch (\Throwable $e) {
                $this->logError(
                    'Verification of task %s threw %s: %s',
                    $task,
                    get_class($e),
                    $this->escapeForLog($e->getMessage())
                );
                return false;
            }
            if (!$applied) {
                $pending[] = $task;
            }
        }
        if ([] !== $pending) {
            $this->logError('Task(s) still pending after apply: %s', implode(', ', $pending));
            return false;
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
     * Make exception text safe for the upgrade page: strip filesystem paths,
     * then HTML-escape in the wizard's charset (_UPGRADE_CHARSET, UTF-8 when
     * the language file is not loaded, as in tests).
     *
     * @param  string $message raw message, typically Throwable::getMessage()
     * @return string sanitized, HTML-escaped message
     */
    protected function escapeForLog(string $message): string
    {
        return htmlspecialchars(
            self::sanitizeLogMessage($message),
            ENT_QUOTES,
            defined('_UPGRADE_CHARSET') ? _UPGRADE_CHARSET : 'UTF-8'
        );
    }

    /**
     * Reduce every absolute path in a message to its basename, so exception
     * text shown on the upgrade page does not reveal the server layout.
     *
     * @param  string $message raw message, typically Throwable::getMessage()
     * @return string message with path-like tokens replaced by basenames
     */
    public static function sanitizeLogMessage(string $message): string
    {
        return (string) preg_replace_callback(
            '/([A-Za-z]:)?[\\\\\\/][^\\s]*/',
            static function (array $matches): string {
                return basename(str_replace('\\', '/', $matches[0]));
            },
            $message
        );
    }

    /**
     * Express a path relative to the XOOPS install for display, so an
     * administrator can locate the file without the page revealing the
     * absolute server layout. Paths outside every known base fall back to
     * their basename.
     *
     * @param  string $path absolute filesystem path
     * @return string path relative to XOOPS_ROOT_PATH, or prefixed with
     *                xoops_trust_path/ or xoops_data/, or a basename
     */
    protected function relativePath(string $path): string
    {
        $bases = [];
        if (defined('XOOPS_ROOT_PATH')) {
            $bases[XOOPS_ROOT_PATH] = '';
        }
        if (defined('XOOPS_TRUST_PATH')) {
            $bases[XOOPS_TRUST_PATH] = 'xoops_trust_path/';
        }
        if (defined('XOOPS_VAR_PATH')) {
            $bases[XOOPS_VAR_PATH] = 'xoops_data/';
        }
        // Compare with one separator style: on Windows the XOOPS constants and the
        // directory walkers mix "/" and "\", and DIRECTORY_SEPARATOR matches neither reliably.
        $normalized = str_replace('\\', '/', $path);
        foreach ($bases as $base => $label) {
            $prefix = rtrim(str_replace('\\', '/', (string) $base), '/') . '/';
            if (str_starts_with($normalized, $prefix)) {
                return $label . substr($normalized, strlen($prefix));
            }
        }

        return basename($normalized);
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
