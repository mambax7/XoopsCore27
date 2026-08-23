<?php

use Xmf\Assert;
use Xmf\Request;

/**
 * Template Manager
 * Manage all templates: theme and module
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
 * @author              Maxime Cointin (AKA Kraven30)
 * @package             system
 */
/** @var XoopsUser $xoopsUser */
/** @var XoopsModule $xoopsModule */
/** @var XoopsConfigItem $xoopsConfig */

include dirname(__DIR__, 2) . '/header.php';

//if (!defined('XOOPS_ROOT_PATH')) {
//    throw new \RuntimeException('XOOPS root path not defined');
//}

if (!is_object($xoopsUser) || !is_object($xoopsModule) || !$xoopsUser->isAdmin($xoopsModule->mid())) {
    exit(_NOPERM);
}

error_reporting(0);
$GLOBALS['xoopsLogger']->activated = false;

if (file_exists(__DIR__ . '/../../language/' . $xoopsConfig['language'] . '/admin/tplsets.php')) {
    include_once __DIR__ . '/../../language/' . $xoopsConfig['language'] . '/admin/tplsets.php';
} else {
    include_once __DIR__ . '/../../language/english/admin/tplsets.php';
}

XoopsLoad::load('XoopsRequest');

$GLOBALS['xoopsLogger']->usePopup = true;

$op = XoopsRequest::getCmd('op', 'default');
switch ($op) {
    // Display tree folder
    case 'tpls_display_folder':
        $root = XOOPS_THEME_PATH;
        // No urldecode(): PHP already decoded the request once, and a
        // second decode would re-materialize %00 / %2f sequences as live
        // bytes. getString() is kept deliberately - its trim() drops only
        // EDGE NULs (an interior "ok\0evil" passes intact, verified by
        // execution), so the NUL is REJECTED explicitly inside the try
        // below. Not getPath(): its PATH filter truncates at the first
        // byte outside [-_./A-Z0-9=&%?~], which breaks non-ASCII theme
        // names ("/thème/" becomes "/th") - also verified by execution.
        // POST, matching the jqueryFileTree $.post() caller.
        $cleanDir = Request::getString('dir', '', 'POST');
        if ('' !== $cleanDir && !str_ends_with($cleanDir, '/')) {
            $cleanDir .= '/'; // the tree JS concatenates rel = dir + file
        }
        $requestDir = $root . $cleanDir;
        try {
            // A NUL cannot occur in a legitimate path - refuse it outright.
            Assert::true(!str_contains($cleanDir, "\0"), _AM_SYSTEM_TEMPLATES_ERROR);
            // realpath() belongs INSIDE the try: PHP 8 throws ValueError
            // for NUL bytes, the backstop should this check ever regress.
            $path_file = realpath($requestDir);
            $check_path = realpath($root);
            // realpath() returns false for a non-existent path - refuse it
            // explicitly instead of feeding false onward (review catch).
            Assert::true(is_string($check_path) && is_dir($check_path), _AM_SYSTEM_TEMPLATES_ERROR);
            Assert::true(is_string($path_file) && is_dir($path_file), _AM_SYSTEM_TEMPLATES_ERROR);
            // Boundary-aware containment: exact root, or root plus a
            // separator. A bare prefix check accepts a SIBLING whose name
            // merely begins with the root ("/themes" matching "/themes2"),
            // which a traversal path can reach (review catch).
            Assert::true(
                $path_file === $check_path
                || str_starts_with($path_file, $check_path . DIRECTORY_SEPARATOR),
                _AM_SYSTEM_TEMPLATES_ERROR
            );
        } catch (\InvalidArgumentException | \ValueError $e) {
            // handle the exception
            redirect_header(XOOPS_URL . '/modules/system/admin.php?fct=tplsets', 2, $e->getMessage());
            exit;
        }
        // Use the CANONICAL path for everything below: keeping the raw
        // $requestDir string open would leave a time-of-check/time-of-use
        // gap through symlink components between the containment check and
        // the listing (review catch).
        $requestDir = rtrim($path_file, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        //
        if (file_exists($requestDir)) {
            $files = scandir($requestDir);
            natcasesort($files);
            if (count($files) > 2) { /* The 2 accounts for . and .. */
                echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
                // All dirs
                foreach ($files as $file) {
                    if (file_exists($requestDir . $file) && $file !== '.' && $file !== '..' && is_dir($requestDir . $file)) {
                        //retirer .svn
                        $file_no_valid = ['.svn', 'icons', 'img', 'images', 'language'];

                        if (!in_array($file, $file_no_valid)) {
                            echo "<li class=\"directory collapsed\"><a href=\"#\" rel=\"" . htmlentities($cleanDir . $file, ENT_QUOTES | ENT_HTML5) . "/\">" . htmlentities($file, ENT_QUOTES | ENT_HTML5) . '</a></li>';
                        }
                    }
                }
                // All files
                foreach ($files as $file) {
                    if (file_exists($requestDir . $file) && $file !== '.' && $file !== '..' && !is_dir($requestDir . $file) && $file !== 'index.html') {
                        $ext = preg_replace('/^.*\./', '', $file);

                        $extensions      = ['.html', '.htm', '.css', '.tpl'];
                        $extension_verif = strtolower((string) strrchr($file, '.'));

                        if (in_array($extension_verif, $extensions)) {
                            echo "<li class=\"file ext_$ext\"><a href=\"#\" onclick=\"tpls_edit_file('" . htmlentities($cleanDir . $file, ENT_QUOTES | ENT_HTML5) . "', '" . htmlentities($cleanDir, ENT_QUOTES | ENT_HTML5) . "', '" . htmlentities($file, ENT_QUOTES | ENT_HTML5) . "', '" . $ext . "');\" rel=\"tpls_edit_file('" . htmlentities($cleanDir . $file, ENT_QUOTES | ENT_HTML5) . "', '" . htmlentities($cleanDir, ENT_QUOTES | ENT_HTML5) . "', '" . htmlentities($file, ENT_QUOTES | ENT_HTML5) . "', '" . $ext . "');\">" . htmlentities($file, ENT_QUOTES | ENT_HTML5) . '</a></li>';
                        } else {
                            //echo "<li class=\"file ext_$ext\">" . htmlentities($file) . "</li>";
                        }
                    }
                }
                echo '</ul>';
            }
        }
        break;
    // Edit File
    case 'tpls_edit_file':
        // POST (templates.js sends type:"POST"). getString(), not
        // getPath(), so non-ASCII template paths survive; the NUL is
        // rejected explicitly below, ValueError as backstop.
        $clean_file = Request::getString('file', '', 'POST');
        $clean_path_file = Request::getString('path_file', '', 'POST');
        try {
            Assert::true(!str_contains($clean_path_file, "\0"), _AM_SYSTEM_TEMPLATES_ERROR);
            // realpath() inside the try with ValueError in the catch, same
            // as tpls_display_folder: PHP 8 throws for NUL bytes.
            $path_file = realpath(XOOPS_ROOT_PATH.'/themes'.trim($clean_path_file));
            $check_path = realpath(XOOPS_ROOT_PATH.'/themes');
            // Refuse a false realpath(), require an actual FILE (a bare
            // "/" resolved to the themes root itself and reached the editor
            // as a directory - review catch), and require root-plus-
            // separator containment, not the bare prefix a sibling
            // directory can satisfy.
            Assert::true(is_string($check_path) && is_dir($check_path), _AM_SYSTEM_TEMPLATES_ERROR);
            Assert::true(is_string($path_file) && is_file($path_file), _AM_SYSTEM_TEMPLATES_ERROR);
            Assert::true(
                str_starts_with($path_file, $check_path . DIRECTORY_SEPARATOR),
                _AM_SYSTEM_TEMPLATES_ERROR
            );
            // The editor opens template types only - same allowlist the
            // save path enforces, applied before any read.
            $pathInfo = pathinfo($path_file);
            Assert::true(
                in_array(strtolower($pathInfo['extension'] ?? ''), ['css', 'html', 'htm', 'tpl'], true),
                _AM_SYSTEM_TEMPLATES_ERROR
            );
        } catch (\InvalidArgumentException | \ValueError $e) {
            // handle the exception
            redirect_header(XOOPS_URL . '/modules/system/admin.php?fct=tplsets', 2, $e->getMessage());
            exit;
        }

        $path_file = str_replace('\\', '/', $path_file);

        //Button restore
        $restore = '';
        if (file_exists($path_file . '.back')) {
            $restore = '<button class="ui-corner-all tooltip" type="button" onclick="tpls_restore(\'' . $path_file . '\')" value="' . _AM_SYSTEM_TEMPLATES_RESTORE . '" title="' . _AM_SYSTEM_TEMPLATES_RESTORE . '">
                            <img src="' . system_AdminIcons('revert.png') . '" alt="' . _AM_SYSTEM_TEMPLATES_RESTORE . '" />
                        </button>';
        }
        xoops_load('XoopsFile');
        XoopsFile::load('file');

        $file    = XoopsFile::getHandler('file', $path_file);
        $content = $file->read();
        if (empty($content)) {
            echo _AM_SYSTEM_TEMPLATES_EMPTY_FILE;
        }
        $ext = preg_replace('/^.*\./', '', $clean_path_file);

        echo '<form name="back" action="admin.php?fct=tplsets&op=tpls_save" method="POST">
              <table border="0">
                <tr>
                    <td>
                          <div class="xo-btn-actions">
                              <div class="xo-buttons">
                                  <button class="ui-corner-all tooltip" type="submit" value="' . _AM_SYSTEM_TEMPLATES_SAVE . '" title="' . _AM_SYSTEM_TEMPLATES_SAVE . '">
                                      <img src="' . system_AdminIcons('save.png') . '" alt="' . _AM_SYSTEM_TEMPLATES_SAVE . '" />
                                  </button>
                                  ' . $restore . '
                                  <button class="ui-corner-all tooltip" type="button" onclick="$(\'#display_contenu\').hide();$(\'#display_form\').fadeIn(\'fast\');" title="' . _AM_SYSTEM_TEMPLATES_CANCEL . '">
                                      <img src="' . system_AdminIcons('cancel.png') . '" alt="' . _AM_SYSTEM_TEMPLATES_CANCEL . '" />
                                  </button>
                                  <div class="clear"></div>
                             </div>
                         </div>
                    </td>
                </tr>
                <tr>
                    <td><textarea id="code_mirror" name="templates" rows=24 cols=110>'
                        . htmlentities((string) $content, ENT_QUOTES | ENT_HTML5)
                    . '</textarea></td>
                </tr>
              </table>';
        XoopsLoad::load('XoopsFormHiddenToken');
        $xoopsToken = new XoopsFormHiddenToken();
        echo $xoopsToken->render();
        echo '<input type="hidden" name="path_file" value="' . htmlentities($clean_path_file, ENT_QUOTES | ENT_HTML5)
            .'"><input type="hidden" name="file" value="' . htmlentities(trim($clean_file), ENT_QUOTES | ENT_HTML5)
            .'"><input type="hidden" name="ext" value="' . htmlentities($ext, ENT_QUOTES | ENT_HTML5) . '"></form>';
        break;

    // Restore backup file
    case 'tpls_restore':
        if (!$GLOBALS['xoopsSecurity']->check()) {
            xoops_error(implode('<br>', $GLOBALS['xoopsSecurity']->getErrors()));
            break;
        }
        $extensions = ['.html', '.htm', '.css', '.tpl'];

        // getText() preserved deliberately: the JS posts the ABSOLUTE
        // canonical path, and getPath()'s filter truncates it at the first
        // byte outside its ASCII set - "C:\..." becomes "C", breaking
        // restore on Windows outright (verified by execution). The NUL is
        // rejected explicitly instead; ValueError stays as backstop.
        $restorePath = Request::getText('path_file', '', 'POST');
        if (str_contains($restorePath, "\0")) {
            xoops_error(_AM_SYSTEM_TEMPLATES_RESTORE_NOTOK);
            break;
        }
        $themesRoot  = realpath(XOOPS_ROOT_PATH . '/themes');
        try {
            $resolved = realpath($restorePath);
        } catch (\ValueError $e) {
            $resolved = false;
        }

        // Early guard narrows $resolved to a real file path before ANY use -
        // static analysis flagged the old shape, where a false $resolved
        // reached the concatenation and the filesystem calls (Scrutinizer).
        // Root-plus-separator containment, not a bare prefix: strpos(...)===0
        // accepts a sibling dir whose path starts with the themes-root
        // string, e.g. ".../themes-evil/..." (SECURITY.md A2-M-3).
        if (!is_string($resolved) || !is_string($themesRoot)
            || !is_file($resolved)
            || !str_starts_with($resolved, $themesRoot . DIRECTORY_SEPARATOR)) {
            xoops_error(_AM_SYSTEM_TEMPLATES_RESTORE_NOTOK);
            break;
        }

        $old_file = $resolved . '.back';
        $new_file = $resolved;

        $extension_verif = strtolower((string) strrchr($new_file, '.'));
        if (in_array($extension_verif, $extensions) && file_exists($old_file) && file_exists($new_file)) {
            if (unlink($new_file)) {
                if (rename($old_file, $new_file)) {
                    xoops_result(_AM_SYSTEM_TEMPLATES_RESTORE_OK);
                    exit();
                }
            }
        }
        xoops_error(_AM_SYSTEM_TEMPLATES_RESTORE_NOTOK);
        break;
}
