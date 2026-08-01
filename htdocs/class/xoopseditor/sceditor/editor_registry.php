<?php
/**
 * SCEditor (BBCode) Editor for XOOPS
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             class
 * @subpackage          editor
 * @since               2.8.0
 * @author              XOOPS Development Team
 * @see                 https://github.com/samclarke/SCEditor
 */
// Self-gate on the vendored library. XoopsEditorHandler::getList() skips any registry whose
// 'order' is empty (xoopseditor.php:185-187), and it does NOT consult isActive() — so this is
// the only way to keep an uninstalled editor out of the preferences dropdown. Without it the
// admin could select SCEditor, get a plain textarea, and have no idea why.
// NOTE: getList() caches its result (XoopsCache), so purge the cache after installing the
// library — see INSTALL.md.
$sceditorRoot      = XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor';
$sceditorInstalled = is_readable($sceditorRoot . '/minified/sceditor.min.js')
    && is_readable($sceditorRoot . '/minified/formats/bbcode.js')
    && is_readable($sceditorRoot . '/js/xoops-bbcode.js');

return $config = [
    'name'   => 'sceditor',
    'class'  => 'FormSCEditor',
    'file'   => $sceditorRoot . '/sceditor.php',
    'title'  => _XOOPS_EDITOR_SCEDITOR,
    'order'  => $sceditorInstalled ? 9 : 0,
    'nohtml' => 1,
];
