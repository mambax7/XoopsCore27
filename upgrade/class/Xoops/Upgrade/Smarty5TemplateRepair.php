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

use SplFileInfo;

/**
 * XOOPS Upgrade Smarty5TemplateRepair
 *
 * Applies ONLY the forward-compatible Smarty 4 -> 5 repairs — changes that are
 * valid on Smarty 4 today and already in the Smarty 5 shape:
 *   1. Block inheritance shorthand `<{block_parent}>` / `<{block_child}>`
 *      -> the canonical `<{$smarty.block.parent}>` / `<{$smarty.block.child}>`
 *      (valid in Smarty 3/4/5; the `.txc` `$xoopsTpl` form is wrong — that is a
 *      PHP variable, not a template special var — see smarty-claude.md §5).
 *   2. `date_format` strftime `%` codes -> `date()` codes, but ONLY when every
 *      token is in {@see Smarty5TemplateChecks::DATE_FORMAT_MAP}. A format with
 *      any locale-sensitive/unmapped token is left untouched (reported for manual
 *      review by {@see Smarty5TemplateChecks}). `%M` (minutes) -> `i`, not `M`.
 *
 * It NEVER touches the report-only rules (`<{php}>`, `{insert}`, `{make_nocache}`,
 * native modifiers, …). Templates are user-customised, so every modified file is
 * first backed up to `<file>.preflight-bak` (written once, preserving the pristine
 * original across repeated repair runs).
 *
 * @category  Xoops\Upgrade
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class Smarty5TemplateRepair extends ScannerProcess
{
    /** Suffix appended to the original file path for the reversible backup. */
    public const BACKUP_SUFFIX = '.preflight-bak';

    /** @var string[] simple search patterns (block inheritance) */
    protected $patterns = [];

    /** @var string[] replacement strings paired with $patterns */
    protected $replacements = [];

    /** @var Smarty5RepairOutput */
    private $output;

    /**
     * @param Smarty5RepairOutput $output
     */
    public function __construct(Smarty5RepairOutput $output)
    {
        $this->output = $output;
        $this->loadPatterns();
    }

    protected function loadPatterns()
    {
        // Forward-compatible block inheritance. The replacement contains `$smarty`,
        // which preg_replace leaves literal ($s is not a backreference).
        $this->patterns[]     = '/<\{\s*block_parent\s*\}>/';
        $this->replacements[] = '<{$smarty.block.parent}>';

        $this->patterns[]     = '/<\{\s*block_child\s*\}>/';
        $this->replacements[] = '<{$smarty.block.child}>';
    }

    /**
     * Rewrite date_format strftime `%` codes to date() codes — mapped tokens only.
     *
     * A format string containing any token NOT in the map (locale-sensitive
     * `%A`/`%B`/`%j`/…) is returned unchanged so it is left for manual review.
     *
     * @param string $content file contents
     * @param int    &$count  incremented per date_format occurrence rewritten
     *
     * @return string updated content
     */
    public function fixDateFormat(string $content, int &$count): string
    {
        $map = Smarty5TemplateChecks::DATE_FORMAT_MAP;

        $result = preg_replace_callback(
            '/(\|\s*date_format\s*:\s*)([\'"])([^\'"]*%[^\'"]*)\2/',
            static function (array $m) use ($map, &$count): string {
                // Leave untouched when any token is not safely mappable.
                if (Smarty5TemplateChecks::UNMAPPED_TOKEN_PATTERN
                    && preg_match(Smarty5TemplateChecks::UNMAPPED_TOKEN_PATTERN, $m[3])
                ) {
                    return $m[0];
                }
                $count++;

                return $m[1] . $m[2] . strtr($m[3], $map) . $m[2];
            },
            $content
        );

        return null === $result ? $content : $result;
    }

    /**
     * Apply all forward-compatible rewrites to $content, skipping Smarty comment
     * regions (`<{* … *}>`) which are preserved verbatim.
     *
     * @param string $filename relative file name (for warnings)
     * @param string $content  full file contents
     * @param int    &$count   incremented per rewrite applied
     *
     * @return string updated content
     */
    private function repairOutsideComments(string $filename, string $content, int &$count): string
    {
        // Capturing split keeps the comment delimiters in the result: even indexes
        // are code segments (transform), odd indexes are comments (keep as-is).
        $parts = preg_split('/(<\{\*.*?\*\}>)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (false === $parts) {
            $parts = [$content];
        }

        $rebuilt = '';
        foreach ($parts as $i => $part) {
            if (1 === $i % 2) {        // a captured comment — leave untouched
                $rebuilt .= $part;
                continue;
            }
            $rebuilt .= $this->repairSegment($filename, $part, $count);
        }

        return $rebuilt;
    }

    /**
     * Apply the block-inheritance and date_format rewrites to a single code segment.
     *
     * @param string $filename relative file name (for warnings)
     * @param string $segment  a non-comment portion of the file
     * @param int    &$count   incremented per rewrite applied
     *
     * @return string updated segment
     */
    private function repairSegment(string $filename, string $segment, int &$count): string
    {
        $blockCount = 0;
        $updated    = preg_replace($this->patterns, $this->replacements, $segment, -1, $blockCount);
        if (null === $updated) {
            trigger_error(sprintf('NULL return processing: %s', $filename), E_USER_WARNING);
            $updated = $segment;
        }
        $count += $blockCount;

        $updated = $this->fixDateFormat($updated, $count);

        return $updated;
    }

    /**
     * @param SplFileInfo $fileInfo
     *
     * @return void
     */
    public function inspectFile(SplFileInfo $fileInfo)
    {
        $output = $this->output;
        if (false === $fileInfo->isWritable()) {
            return;
        }

        $filename = self::relativeToRoot($fileInfo->getPathname());

        $length = $fileInfo->getSize();
        if ($length <= 0) {
            return;
        }
        $file     = $fileInfo->openFile('r+');
        $original = $file->fread($length);

        // Apply rewrites only OUTSIDE Smarty comments, so a commented-out tag is
        // left verbatim — consistent with Smarty5TemplateChecks, which ignores
        // commented tags too (otherwise repair would touch a file checks calls clean).
        $count   = 0;
        $updated = $this->repairOutsideComments($filename, $original, $count);

        if (0 === $count) {
            return;
        }

        // Back up the pristine original once before the first overwrite, and abort
        // the repair if the backup cannot be written — never overwrite a file we
        // could not preserve.
        $pathname   = $fileInfo->getPathname();
        $backupName = $this->writeBackup($pathname, $original);
        if ('' === $backupName) {
            trigger_error(sprintf('Skipping repair, backup failed: %s', $filename), E_USER_WARNING);

            return;
        }

        // Write atomically: stage to a temp file, verify the full byte count was
        // written, then rename over the original. A failed or short write leaves
        // the original template untouched.
        $tmpPath  = $pathname . '.s5tmp';
        $expected = strlen($updated);
        $written  = file_put_contents($tmpPath, $updated, LOCK_EX);
        if (false === $written || $written !== $expected) {
            $this->removeStagingFile($tmpPath, 'short write');
            trigger_error(sprintf('Error writing file: %s', $filename), E_USER_WARNING);

            return;
        }
        // The temp file was created with the process umask; copy the original's
        // permission bits so rename() does not silently change the template's mode.
        // Best-effort: a failure only leaves umask permissions, not a damaged file.
        $perms = fileperms($pathname);
        if (false !== $perms) {
            // Wrap chmod() in a scoped error handler: no @ (keeps static analysis
            // happy) yet the native warning — which would carry the full temp path —
            // is swallowed; a sanitized notice is logged instead.
            set_error_handler(static fn (): bool => true);
            try {
                $chmodOk = chmod($tmpPath, $perms & 0777);
            } finally {
                restore_error_handler();
            }
            if (!$chmodOk) {
                trigger_error(sprintf('Could not restore permissions on: %s', basename($filename)), E_USER_NOTICE);
            }
        }
        unset($file); // release the read handle so rename can replace the original (Windows)
        if (false === @rename($tmpPath, $pathname)) {
            $this->removeStagingFile($tmpPath, 'rename failed');
            trigger_error(sprintf('Error replacing file: %s', $filename), E_USER_WARNING);

            return;
        }

        $output->outputIssue($output->makeOutputIssue($filename, $count, $backupName));
    }

    /**
     * Best-effort removal of the staging temp file. The repair has already aborted
     * with the original preserved, so a leftover .s5tmp is operational noise — log
     * a notice (not a warning) rather than ignoring the unlink result.
     *
     * @param string $tmpPath
     * @param string $context
     *
     * @return void
     */
    private function removeStagingFile(string $tmpPath, string $context): void
    {
        // @ suppresses the native warning (it would carry the full temp path); the
        // return is checked and a basename-only notice is logged instead.
        if (is_file($tmpPath) && !@unlink($tmpPath)) {
            trigger_error(
                sprintf('Could not remove staging file (%s): %s', $context, basename($tmpPath)),
                E_USER_NOTICE
            );
        }
    }

    /**
     * Write a one-time pristine backup next to the original.
     *
     * @param string $pathname absolute path of the file being repaired
     * @param string $original original file contents
     *
     * @return string the backup file's basename, or '' when no backup was written
     */
    private function writeBackup(string $pathname, string $original): string
    {
        $backupPath = $pathname . self::BACKUP_SUFFIX;
        if (file_exists($backupPath)) {
            return basename($backupPath); // preserve the earliest pristine copy
        }
        if (false === file_put_contents($backupPath, $original)) {
            trigger_error(sprintf('Could not write backup: %s', basename($backupPath)), E_USER_WARNING);

            return '';
        }

        return basename($backupPath);
    }
}
