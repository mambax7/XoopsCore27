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

use ArrayObject;

/**
 * XOOPS Upgrade Smarty5ScannerOutput
 *
 * Severity-aware output for the Smarty 4 -> 5 readiness scan. In addition to the
 * HTML report (paralleling {@see Smarty4ScannerOutput}) it exposes machine-readable
 * tallies that the `upd_2.7.0-to-2.7.1` patch and the preflight blocker gate consume:
 *   - countAutoFixable() : forward-compatible issues still pending repair
 *   - countBlockers()    : Smarty-4 blockers (`<{php}>`/`<{include_php}>`)
 *   - getBlockerFiles()  : relative paths of files carrying a blocker
 *   - getReportOnlyIssues(): the manual-rework items (insert, make_nocache, …)
 *
 * All dynamic values are HTML-escaped before rendering — the report is echoed
 * directly into the upgrader page.
 *
 * @category  Xoops\Upgrade
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class Smarty5ScannerOutput extends ScannerOutput
{
    /** @var string accumulated HTML output */
    protected $content = '';

    /** @var ArrayObject<string,int> per-key counts (per-rule + aggregate keys) */
    protected $counts;

    /** @var string[] relative paths of files containing a Smarty-4 blocker */
    protected array $blockerFiles = [];

    /** @var array<int, array{rule:string, file:string, match:string, tier:string}> manual-rework issues */
    protected array $reportOnly = [];

    public function __construct()
    {
        $this->content = '';
        $this->counts  = new ArrayObject([]);
    }

    /**
     * Increment a count bucket by one.
     *
     * Aggregate keys: 'checked' (added by ScannerWalker), 'notwritable',
     * 'autofixable', 'blockers', plus one bucket per rule id.
     *
     * @param string $key
     *
     * @return void
     */
    public function addToCount($key)
    {
        $count = $this->counts->offsetExists($key) ? (int) $this->counts->offsetGet($key) : 0;
        $this->counts->offsetSet($key, $count + 1);
    }

    /**
     * Read a count bucket.
     *
     * @param string $key
     *
     * @return int
     */
    public function getCount($key)
    {
        return $this->counts->offsetExists($key) ? (int) $this->counts->offsetGet($key) : 0;
    }

    /**
     * Forward-compatible issues still pending (block inheritance + mapped date_format).
     *
     * Drives check_smartytemplates() in the upgrade patch — when this is zero the
     * preflight repair has been run (or there was nothing to do).
     *
     * @return int
     */
    public function countAutoFixable(): int
    {
        return $this->getCount('autofixable');
    }

    /**
     * Smarty-4 blocker matches (`<{php}>` / `<{include_php}>`).
     *
     * @return int
     */
    public function countBlockers(): int
    {
        return $this->getCount('blockers');
    }

    /**
     * Relative paths of files carrying at least one blocker (deduplicated).
     *
     * @return string[]
     */
    public function getBlockerFiles(): array
    {
        return array_values(array_unique($this->blockerFiles));
    }

    /**
     * The manual-rework issues (everything that is neither auto-fixable nor a clean file).
     *
     * @return array<int, array{rule:string, file:string, match:string, tier:string}>
     */
    public function getReportOnlyIssues(): array
    {
        return $this->reportOnly;
    }

    /**
     * @return string recorded HTML output
     */
    public function outputFetch()
    {
        return $this->content;
    }

    /**
     * @param string $item
     *
     * @return void
     */
    public function outputAppend($item)
    {
        $this->content .= $item . "\n";
    }

    public function outputStart()
    {
        $this->outputAppend('<h2>' . _XOOPS_SMARTY5_SCANNER_RESULTS . '</h2>');
        $this->outputAppend('<table class="table"><tr><th>'
            . _XOOPS_SMARTY5_SCANNER_RULE . '</th><th>'
            . _XOOPS_SMARTY5_SCANNER_TIER . '</th><th>'
            . _XOOPS_SMARTY5_SCANNER_MATCH . '</th><th>'
            . _XOOPS_SMARTY5_SCANNER_FILE . '</th></tr>');
    }

    public function outputWrapUp()
    {
        $this->outputAppend('</table>');

        // Readiness summary — counts per tier so "readiness" never reads as "you need Smarty 5".
        $this->outputAppend('<table class="table"><tr><th>' . _XOOPS_SMARTY5_SUMMARY . '</th><th></th></tr>');
        $this->summaryRow(_XOOPS_SMARTY5_SUMMARY_CHECKED, $this->getCount('checked'));
        $this->summaryRow(_XOOPS_SMARTY5_SUMMARY_BLOCKERS, $this->countBlockers(), 'danger');
        $this->summaryRow(_XOOPS_SMARTY5_SUMMARY_AUTOFIX, $this->countAutoFixable(), 'success');
        // Blockers are also stored in reportOnly (for the patch's verify log) but
        // are summarised on their own row above, so exclude them here.
        $this->summaryRow(_XOOPS_SMARTY5_SUMMARY_MANUAL, count($this->reportOnly) - $this->countBlockers(), 'warning');
        $this->summaryRow(_XOOPS_SMARTY5_SCANNER_NOT_WRITABLE, $this->getCount('notwritable'), 'warning');
        $this->outputAppend('</table>');
    }

    /**
     * Append one summary row.
     *
     * @param string $label    already-localised label
     * @param int    $value    count
     * @param string $cssClass optional row class (e.g. 'danger', 'warning', 'success')
     *
     * @return void
     */
    private function summaryRow(string $label, int $value, string $cssClass = ''): void
    {
        $tr = '' === $cssClass ? '<tr>' : '<tr class="' . $cssClass . '">';
        $this->outputAppend($tr . '<td>' . $this->esc($label) . '</td><td>' . $value . '</td></tr>');
    }

    /**
     * @param ArrayObject $args keys: rule, file, match, writable, tier, disposition
     */
    public function outputIssue(ArrayObject $args)
    {
        $rule        = (string) ($args['rule'] ?? '');
        $file        = (string) ($args['file'] ?? '');
        $match       = (string) ($args['match'] ?? '');
        $writable    = (bool) ($args['writable'] ?? false);
        $tier        = (string) ($args['tier'] ?? '');
        $disposition = (string) ($args['disposition'] ?? Smarty5TemplateChecks::FIX_MANUAL);

        $this->addToCount($rule);
        if (!$writable) {
            $this->addToCount('notwritable');
        }

        switch ($disposition) {
            case Smarty5TemplateChecks::FIX_AUTO:
                $this->addToCount('autofixable');
                $note     = _XOOPS_SMARTY5_NOTE_AUTOFIX;
                $rowClass = 'success';
                break;
            case Smarty5TemplateChecks::FIX_BLOCK:
                $this->addToCount('blockers');
                $this->blockerFiles[] = $file;
                // Blockers are also surfaced as report-only items so the upgrade
                // patch's verify task logs them (they are never auto-fixed).
                $this->reportOnly[] = ['rule' => $rule, 'file' => $file, 'match' => $match, 'tier' => $tier];
                $note     = _XOOPS_SMARTY5_NOTE_BLOCKER;
                $rowClass = 'danger';
                break;
            case Smarty5TemplateChecks::FIX_WARN:
                $this->reportOnly[] = ['rule' => $rule, 'file' => $file, 'match' => $match, 'tier' => $tier];
                $note     = _XOOPS_SMARTY5_NOTE_WARN;
                $rowClass = 'warning';
                break;
            case Smarty5TemplateChecks::FIX_MANUAL:
            default:
                $this->reportOnly[] = ['rule' => $rule, 'file' => $file, 'match' => $match, 'tier' => $tier];
                $note     = _XOOPS_SMARTY5_NOTE_MANUAL;
                $rowClass = 'warning';
                break;
        }

        $writableNote = $writable ? '' : '<br>' . $this->esc(_XOOPS_SMARTY5_SCANNER_NOT_WRITABLE);

        $this->outputAppend('<tr class="' . $rowClass . '">'
            . '<td>' . $this->esc($rule) . '</td>'
            . '<td>' . $this->esc($tier) . '</td>'
            . '<td>' . $this->esc($match) . '</td>'
            . '<td>' . $this->esc($file) . '<br>' . $this->esc($note) . $writableNote . '</td></tr>');
    }

    /**
     * Build an issue argument bag.
     *
     * @param string $rule        rule id
     * @param string $file        relative file path
     * @param string $match       matched text
     * @param bool   $writable    whether the file is writable
     * @param string $tier        severity tier (TIER_*)
     * @param string $disposition disposition (FIX_*)
     *
     * @return ArrayObject
     */
    public function makeOutputIssue($rule, $file, $match, $writable, $tier = '', $disposition = Smarty5TemplateChecks::FIX_MANUAL)
    {
        return new ArrayObject([
            'rule'        => $rule,
            'file'        => $file,
            'match'       => $match,
            'writable'    => $writable,
            'tier'        => $tier,
            'disposition' => $disposition,
        ]);
    }

    /**
     * HTML-escape a value for safe rendering in the report.
     *
     * @param string $value
     *
     * @return string
     */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
