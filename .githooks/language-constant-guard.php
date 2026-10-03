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

declare(strict_types=1);

/**
 * Pre-commit rule 18 (warning only), run by .githooks/pre-commit from the
 * repository root. Prints one "path:line: code" per new language constant
 * read outside the canonical guard forms.
 * Exit status: 0 clean, 1 reads reported, 2 the rule could not run.
 *
 * @category  Xoops
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

require __DIR__ . '/lib/LanguageConstantGuard.php';

exit(LanguageConstantGuard::runAgainstIndex(STDOUT));
