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

use Xoops\Upgrade\ScannerWalker;
use Xoops\Upgrade\Smarty5ScannerOutput;
use Xoops\Upgrade\Smarty5TemplateChecks;
use Xoops\Upgrade\UpgradeControl;
use Xoops\Upgrade\XoopsUpgrade;

/**
 * Upgrade from 2.7.0 to 2.7.1 — Smarty modernisation
 *
 * This patch is the VERIFY-and-finalise half of the Smarty 4 -> 5 readiness work.
 * Template *repair* is performed interactively by preflight.php (backups + diff
 * review) because theme/module templates are user-customised; this patch must
 * never silently rewrite them and must never hard-abort the upgrade over a
 * template issue.
 *
 * Tasks (run in order):
 *  1. smartytemplates  — VERIFY templates are clean. Counts forward-compatible
 *                        (auto-fixable) Smarty issues with the same scanner the
 *                        preflight uses (Smarty5TemplateChecks, count-only).
 *                        Invariant: when preflight repair has run, 0 auto-fixable
 *                        issues remain -> check passes. If issues remain, the task
 *                        warns once (pointing back to preflight), logs report-only
 *                        items, and still completes (skip-with-warning) — it never
 *                        writes templates or blocks the queue.
 *  2. smartycache      — Purge compiled templates so stale Smarty-4 compiles built
 *                        from pre-repair sources cannot survive. Invariant: a
 *                        durable one-shot, recorded with a marker file (not the
 *                        session) so it does not re-queue after a new session.
 *  3. smartyextensions — Guard that xoops/smartyextensions is installed and its
 *                        ExtensionRegistry is autoloadable (composer runs out of
 *                        band). Invariant: a missing package logs an actionable
 *                        error but never wedges the queue (session-flag warn-once).
 *
 * Fresh-install parity: core 2.7.1 ships clean templates, so smartytemplates and
 * smartycache pass with no work. The xoops/smartyextensions package is optional and
 * NOT bundled (it is not in composer.dist.json); its check is advisory only —
 * absence logs an actionable notice and never blocks the upgrade.
 *
 * @category     Upgrade
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package      XOOPS
 * @link         https://xoops.org
 * @since        2.7.1
 * @author       XOOPS Team
 */
class Upgrade_271 extends XoopsUpgrade
{
    /** @var string session flag: smartytemplates has warned about pending issues this session */
    protected string $smartyTplKey = 'smarty5-templates-warned-271';

    /** @var string session flag: smartyextensions guard has warned this session */
    protected string $smartyExtKey = 'smarty5-extensions-warned-271';

    /**
     * Per-request memoised fallback scan tally. The normal path reads the tally
     * recorded by preflight.php ($_SESSION['smartyScan']); this static only caches
     * the rare fallback walk so it never runs more than once per request.
     *
     * @var array<string,mixed>|null
     */
    protected static ?array $scanCache = null;

    /**
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        // Ensure the Smarty5 scanner's output labels are defined whenever this
        // patch is instantiated (the scan runs inside check_/apply_).
        $this->control->loadLanguage('smarty5');
        $this->tasks = [
            'smartytemplates',
            'smartycache',
            'smartyextensions',
        ];
        $this->usedFiles = [];
    }

    // =========================================================================
    // Task 1: smartytemplates — verify (repair happens in preflight)
    // =========================================================================

    /**
     * Pass when no forward-compatible (auto-fixable) Smarty issue remains.
     *
     * Skip-with-warning contract: when issues DO remain, the task still passes
     * once apply_ has warned this session (session flag), so the queue is never
     * wedged by user-customised templates the admin chose not to repair. The flag
     * is cleared the moment the tree becomes clean, so a later re-scan re-confirms.
     *
     * @return bool true if verified clean, or already warned this session
     */
    public function check_smartytemplates(): bool
    {
        if (0 === $this->countAutoFixable() && empty($this->scanReportOnly())) {
            unset($_SESSION[$this->smartyTplKey]); // genuinely clean — drop any stale warn flag
            return true;
        }

        // Auto-fixable OR report-only (blocker / manual-review) items remain: honour
        // the warn-once flag so the queue finishes, but not before apply_ has logged
        // the manual-review warnings on the first pass.
        return !empty($_SESSION[$this->smartyTplKey]);
    }

    /**
     * Never rewrites templates. If forward-compatible issues remain the admin
     * skipped the preflight Smarty repair — warn and point back to it. Report-only
     * items (blockers, {insert}, native modifiers, …) are surfaced as warnings.
     * Always completes (skip-with-warning).
     *
     * @return bool always true
     */
    public function apply_smartytemplates(): bool
    {
        $pending = $this->countAutoFixable();
        if ($pending > 0) {
            $this->logError(
                '%d template(s) still have auto-fixable Smarty issues. Run the Smarty repair in '
                . 'preflight.php (it backs up templates and lets you review diffs) before going live.',
                $pending
            );
        }

        foreach ($this->scanReportOnly() as $issue) {
            $this->logError(
                'Smarty manual review (%s): %s in %s',
                htmlspecialchars((string) ($issue['tier'] ?? ''), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($issue['rule'] ?? ''), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($issue['file'] ?? ''), ENT_QUOTES, 'UTF-8')
            );
        }

        if (0 === $pending && empty($this->scanReportOnly())) {
            $this->logSuccess('Templates verified clean for Smarty 5 readiness.');
        }

        $_SESSION[$this->smartyTplKey] = true; // warned once; queue may complete

        return true;
    }

    // =========================================================================
    // Task 2: smartycache — clear compiled templates
    // =========================================================================

    /**
     * Durable one-shot: true once the compiled-template cache has been purged.
     *
     * Recorded with a marker file (not $_SESSION) so a new or expired browser
     * session does not make the 2.7.1 patch reappear as pending after the purge.
     *
     * @return bool
     */
    public function check_smartycache(): bool
    {
        return is_file($this->cachePurgedMarker());
    }

    /**
     * Purge compiled templates so stale Smarty-4 compiles cannot survive the repair.
     *
     * @return bool true once purged and the durable marker is written; false if the
     *              marker cannot be recorded (so the task is not falsely complete)
     */
    public function apply_smartycache(): bool
    {
        $this->purge(XOOPS_ROOT_PATH . '/templates_c');
        if (defined('XOOPS_VAR_PATH')) {
            $this->purge(XOOPS_VAR_PATH . '/caches/smarty_compile');
        }
        // Record completion durably. If the marker cannot be written, the task is
        // genuinely not complete (check_ would stay false), so report failure
        // instead of claiming success.
        $marker = $this->cachePurgedMarker();
        $dir    = dirname($marker);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->logError('Could not create the cache marker directory: %s', basename($dir));

            return false;
        }
        if (false === @file_put_contents($marker, (string) time())) {
            $this->logError('Could not write the cache-purged marker: %s', basename($marker));

            return false;
        }
        $this->logSuccess('Compiled Smarty templates purged.');

        return true;
    }

    /**
     * Durable marker file recording that the one-shot compiled-template purge ran.
     *
     * @return string
     */
    private function cachePurgedMarker(): string
    {
        $base = defined('XOOPS_VAR_PATH') ? XOOPS_VAR_PATH . '/data' : XOOPS_ROOT_PATH;

        return $base . '/smarty5_cache_purged_271.marker';
    }

    // =========================================================================
    // Task 3: smartyextensions — verify dependency + registration
    // =========================================================================

    /**
     * Pass when xoops/smartyextensions is autoloadable, or after warning once.
     *
     * @return bool
     */
    public function check_smartyextensions(): bool
    {
        // String form (not ::class) so static analysis does not require this
        // optional package to be present.
        if (class_exists('Xoops\\SmartyExtensions\\ExtensionRegistry')) {
            return true;
        }

        return !empty($_SESSION[$this->smartyExtKey]); // warned once, let queue finish
    }

    /**
     * Guard (not installer): log whether xoops/smartyextensions is present.
     *
     * @return bool always true
     */
    public function apply_smartyextensions(): bool
    {
        // String form (not ::class) so static analysis does not require this
        // optional package to be present.
        if (class_exists('Xoops\\SmartyExtensions\\ExtensionRegistry')) {
            $this->logSuccess('xoops/smartyextensions present and registered.');
        } else {
            // Advisory only: this optional package is not bundled with 2.7.1 and
            // its absence never blocks the upgrade.
            $this->logError(
                'Optional package xoops/smartyextensions is not installed; its template plugins '
                . 'are unavailable. To add it, run "composer require xoops/smartyextensions" in '
                . 'htdocs/xoops_lib. This is advisory and does not block the upgrade.'
            );
        }
        $_SESSION[$this->smartyExtKey] = true;

        return true;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Number of forward-compatible (auto-fixable) Smarty issues.
     *
     * Reads the tally recorded by preflight.php ($_SESSION['smartyScan']) — the
     * single scan authority — so the heavy themes/+modules/ walk does NOT run on
     * every upgrade page view (it is ~20s over thousands of files and previously
     * risked exceeding max_execution_time, hanging the queue). Protected so tests
     * can stub it.
     *
     * @return int
     */
    protected function countAutoFixable(): int
    {
        $scan = $_SESSION['smartyScan'] ?? null;
        if (is_array($scan) && array_key_exists('autofixable', $scan)) {
            return (int) $scan['autofixable'];
        }

        return (int) ($this->scanIntoSession()['autofixable'] ?? 0);
    }

    /**
     * Report-only issues (blockers + manual-rework items).
     *
     * Reads the preflight-recorded scan; see {@see countAutoFixable()}.
     *
     * @return array<int, array{rule:string, file:string, match:string, tier:string}>
     */
    protected function scanReportOnly(): array
    {
        $scan = $_SESSION['smartyScan'] ?? null;
        if (is_array($scan) && array_key_exists('reportOnly', $scan)) {
            return (array) $scan['reportOnly'];
        }

        return (array) ($this->scanIntoSession()['reportOnly'] ?? []);
    }

    /**
     * Fallback ONLY: when no preflight scan is recorded in the session (the
     * blocker gate normally guarantees one before the queue starts), run the
     * count-only scan ONCE, cache the tally in the session so subsequent page
     * views do not re-walk the tree, and return it.
     *
     * @return array<string,mixed>
     */
    private function scanIntoSession(): array
    {
        if (null !== self::$scanCache) {
            return self::$scanCache;
        }

        $output  = new Smarty5ScannerOutput();
        $process = new Smarty5TemplateChecks($output);
        $scanner = new ScannerWalker($process, $output);
        foreach (['/themes/', '/modules/'] as $dir) {
            $path = XOOPS_ROOT_PATH . $dir;
            if (is_dir($path)) {
                $scanner->addDirectory($path);
            }
        }
        $scanner->addExtension('tpl');
        $scanner->addExtension('html');
        $scanner->runScan();

        $tally = [
            'ran'         => true,
            'autofixable' => $output->countAutoFixable(),
            'blockers'    => $output->countBlockers(),
            'files'       => $output->getBlockerFiles(),
            'reportOnly'  => $output->getReportOnlyIssues(),
            'at'          => time(),
        ];
        // Merge so a token recorded by preflight (if any) is preserved; normalise a
        // non-array session value first so corrupt state cannot fatal array_merge().
        $existing = $_SESSION['smartyScan'] ?? [];
        $_SESSION['smartyScan'] = array_merge(is_array($existing) ? $existing : [], $tally);

        return self::$scanCache = $tally;
    }

    /**
     * Recursively delete the CONTENTS of a directory, preserving the directory
     * itself and its index/.htaccess access guards.
     *
     * @param string $dir absolute directory path
     *
     * @return void
     */
    protected function purge(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $keep    = ['.', '..', 'index.php', 'index.html', '.htaccess'];
        $entries = scandir($dir);
        if (false === $entries) {
            return;
        }
        foreach ($entries as $entry) {
            if (in_array($entry, $keep, true)) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->purgeTree($path);
            } else {
                $this->removePath($path);
            }
        }
    }

    /**
     * Recursively delete a directory and everything under it.
     *
     * @param string $dir absolute directory path
     *
     * @return void
     */
    private function purgeTree(string $dir): void
    {
        $entries = scandir($dir);
        if (false === $entries) {
            return;
        }
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->purgeTree($path);
            } else {
                $this->removePath($path);
            }
        }
        $this->removePath($dir);
    }

    /**
     * Best-effort removal of a file, symlink, or empty directory. Logs a low-level
     * notice (rather than silently ignoring the result) when removal genuinely
     * fails, so a permission problem during the purge is visible without wedging
     * the upgrade.
     *
     * @param string $path
     *
     * @return void
     */
    private function removePath(string $path): void
    {
        // @ suppresses the native PHP warning (which would leak the full path);
        // the return is checked and a basename-only message is logged instead.
        $removed = (is_dir($path) && !is_link($path)) ? @rmdir($path) : @unlink($path);
        if (false === $removed && file_exists($path)) {
            trigger_error('Could not remove ' . basename($path), E_USER_WARNING);
        }
    }
}

return Upgrade_271::class;
