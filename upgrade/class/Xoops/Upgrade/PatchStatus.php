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

/**
 * XOOPS Upgrade PatchStatus
 *
 * Holds the status of a single upgrade patch, indicating whether it has been
 * applied and which tasks and files are still needed.
 *
 * @category  Xoops\Upgrade
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class PatchStatus
{
    /** @var string $patchClass class name of patch */
    public string $patchClass = '';

    /** @var bool $applied true if this patch is applied, false if it is needed */
    public bool $applied = true;

    /** @var string[] $tasks tasks that need to be run */
    public array $tasks = [];

    /** @var string[] $files files that need to be writable */
    public array $files = [];

    /** @var XoopsUpgrade the patch instance */
    private XoopsUpgrade $patch;

    /**
     * PatchStatus constructor.
     *
     * @param XoopsUpgrade $patch patch to check status for
     */
    public function __construct(XoopsUpgrade $patch)
    {
        $this->patchClass = get_class($patch);
        $this->patch = $patch;
        foreach ($patch->tasks as $task) {
            if (!$patch->{"check_{$task}"}()) {
                $this->addTask($task);
            }
        }
        if (!empty($patch->usedFiles) && !$this->applied) {
            $this->files = $patch->usedFiles;
        }
    }

    /**
     * Get the stored patch instance.
     *
     * @return XoopsUpgrade
     */
    public function getPatch(): XoopsUpgrade
    {
        return $this->patch;
    }

    /**
     * Add a task that needs to be run to the tasks property
     *
     * @param  string $task task name
     * @return void
     */
    protected function addTask(string $task): void
    {
        $this->tasks[] = $task;
        $this->applied = false;
    }
}
