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
 * XOOPS Upgrade Smarty5RepairOutput
 *
 * Reports the forward-compatible Smarty 4 -> 5 repairs applied by
 * {@see Smarty5TemplateRepair}. Mirrors {@see Smarty4RepairOutput}, adding a
 * "backup written" note because S5 repairs are reversible (`*.preflight-bak`).
 *
 * @category  Xoops\Upgrade
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class Smarty5RepairOutput extends ScannerOutput
{
    /** @var string accumulated HTML output */
    protected $content = '';

    /** @var int total number of fixes applied across all files */
    protected $issueCounts = 0;

    public function __construct()
    {
        $this->content     = '';
        $this->issueCounts = 0;
    }

    /**
     * @param int $count
     *
     * @return void
     */
    public function addToCount($count)
    {
        $this->issueCounts += (int) $count;
    }

    /**
     * @return int total fixes applied
     */
    public function totalFixes(): int
    {
        return $this->issueCounts;
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
        $this->outputAppend('<h2>' . _XOOPS_SMARTY5_REPAIR_RESULTS . '</h2>');
        $this->outputAppend('<table class="table"><tr><th>'
            . _XOOPS_SMARTY5_SCANNER_FILE . '</th><th>'
            . _XOOPS_SMARTY5_SCANNER_FIXED . '</th><th>'
            . _XOOPS_SMARTY5_REPAIR_BACKUP . '</th></tr>');
    }

    public function outputWrapUp()
    {
        $this->outputAppend('</table>');
    }

    /**
     * @param ArrayObject $args keys: filename, count, backup
     */
    public function outputIssue(ArrayObject $args)
    {
        $filename = htmlspecialchars((string) ($args['filename'] ?? ''), ENT_QUOTES, 'UTF-8');
        $count    = (int) ($args['count'] ?? 0);
        $backup   = htmlspecialchars((string) ($args['backup'] ?? ''), ENT_QUOTES, 'UTF-8');

        $this->outputAppend("<tr><td>{$filename}</td><td>{$count}</td><td>{$backup}</td></tr>");
        $this->addToCount($count);
    }

    /**
     * @param string $filename relative file path
     * @param int    $count    number of fixes applied to the file
     * @param string $backup   backup file name written, or '' when none
     *
     * @return ArrayObject
     */
    public function makeOutputIssue($filename, $count, $backup = '')
    {
        return new ArrayObject([
            'filename' => $filename,
            'count'    => $count,
            'backup'   => $backup,
        ]);
    }
}
