<?php

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
// The containment contract shared by all four tplsets endpoints, pinned
// by the PathGuard truth-table test: NUL refusal, realpath with
// ValueError backstop, false-result refusal, boundary-aware root
// containment, is_dir/is_file by mode, extension allowlist.
require_once XOOPS_ROOT_PATH . '/class/PathGuard.php';

$GLOBALS['xoopsLogger']->usePopup = true;

$op = XoopsRequest::getCmd('op', 'default');
switch ($op) {
    // Display tree folder
    case 'tpls_display_folder':
        $root = XOOPS_THEME_PATH;
        // No urldecode(): PHP already decoded the request once, and a
        // second decode would re-materialize %00 / %2f sequences as live
        // bytes. getString() is kept deliberately - its trim() silently
        // drops EDGE NULs, so "x\0" arrives as the VALID "x" and then
        // faces the same containment checks as any other value, while an
        // interior "ok\0evil" passes intact (verified by execution), so
        // the interior NUL is REJECTED explicitly inside the try below.
        // Not getPath(): its PATH filter truncates at the first
        // byte outside [-_./A-Z0-9=&%?~], which breaks non-ASCII theme
        // names ("/thème/" becomes "/th") - also verified by execution.
        // POST, matching the jqueryFileTree $.post() caller.
        $cleanDir = Request::getString('dir', '', 'POST');
        if ('' !== $cleanDir && !str_ends_with($cleanDir, '/')) {
            $cleanDir .= '/'; // the tree JS concatenates rel = dir + file
        }
        $path_file = PathGuard::resolveDir($root, $cleanDir);
        if (false === $path_file) {
            redirect_header(XOOPS_URL . '/modules/system/admin.php?fct=tplsets', 2, _AM_SYSTEM_TEMPLATES_ERROR);
            exit;
        }
        // Use the CANONICAL path for everything below: keeping the raw
        // request string open would leave a time-of-check/time-of-use
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
                            // House encoder for every output in this endpoint:
                            // htmlspecialchars(ENT_QUOTES, UTF-8) - same escapes
                            // where it matters, without entity-encoding multibyte
                            // text (review catch).
                            echo "<li class=\"directory collapsed\"><a href=\"#\" rel=\"" . htmlspecialchars($cleanDir . $file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "/\">" . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></li>';
                        }
                    }
                }
                // All files
                foreach ($files as $file) {
                    if (file_exists($requestDir . $file) && $file !== '.' && $file !== '..' && !is_dir($requestDir . $file) && $file !== 'index.html') {
                        $extensions      = ['.html', '.htm', '.css', '.tpl'];
                        $extension_verif = strtolower((string) strrchr($file, '.'));
                        // Safe by construction: the allowlist below admits
                        // only these four tokens, so $ext can never carry
                        // attribute- or JS-breaking characters (review catch).
                        $ext = ltrim($extension_verif, '.');

                        if (in_array($extension_verif, $extensions, true)) {
                            // JS string literals inside an HTML attribute need
                            // json_encode() THEN htmlspecialchars(): entities
                            // decode before the JS parser runs, so htmlentities()
                            // alone left an apostrophe filename ("it's.css") free
                            // to terminate the literal (review catch - same
                            // encoder the restore button already uses). The
                            // SUBSTITUTE flags matter: a filename need not be
                            // valid UTF-8, and without them json_encode()
                            // returns false (emitting a JS parse error) and
                            // htmlspecialchars() returns '' - such an entry now
                            // renders with U+FFFD and fails realpath validation
                            // on click instead of breaking the page (review
                            // catch, verified by execution). Two arguments only:
                            // the handler never used the old path/file pair.
                            $enc = static fn($v) => json_encode($v, JSON_INVALID_UTF8_SUBSTITUTE);
                            $jsCall = htmlspecialchars(
                                'tpls_edit_file(' . $enc($cleanDir . $file) . ', ' . $enc($ext) . ');',
                                ENT_QUOTES | ENT_SUBSTITUTE,
                                'UTF-8'
                            );
                            echo "<li class=\"file ext_$ext\"><a href=\"#\" onclick=\"" . $jsCall . "\" rel=\"" . $jsCall . "\">" . htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></li>';
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
        // getPath(), so non-ASCII template paths survive. PathGuard file
        // mode: real file strictly inside themes/, template extensions only.
        $clean_path_file = Request::getString('path_file', '', 'POST');
        $path_file = PathGuard::resolveFile(XOOPS_ROOT_PATH . '/themes', trim($clean_path_file), ['css', 'html', 'htm', 'tpl']);
        if (false === $path_file) {
            redirect_header(XOOPS_URL . '/modules/system/admin.php?fct=tplsets', 2, _AM_SYSTEM_TEMPLATES_ERROR);
            exit;
        }
        // The guard just proved the root resolves.
        $check_path = realpath(XOOPS_ROOT_PATH . '/themes');

        // Relative form of the VALIDATED path, not the raw request value:
        // the restore button and the hidden path_file field below both
        // re-derive from what the checks actually approved (review catch).
        $relPath = str_replace('\\', '/', substr($path_file, strlen($check_path)));

        $path_file = str_replace('\\', '/', $path_file);

        //Button restore
        $restore = '';
        if (file_exists($path_file . '.back')) {
            // Pass the validated RELATIVE path (the server rebuilds the root
            // side), not the absolute server path - which leaked the server
            // root into admin HTML and forced a Windows special case in the
            // handler. json_encode() then htmlspecialchars(): the correct
            // encoder for a JS string literal inside an HTML attribute.
            // SUBSTITUTE flags: without them a non-UTF-8 path yields
            // json_encode() false, the (string) cast turns that into '',
            // and the button renders a silent no-op tpls_restore().
            $restoreArg = htmlspecialchars((string) json_encode($relPath, JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $restore = '<button class="ui-corner-all tooltip" type="button" onclick="tpls_restore(' . $restoreArg . ')" value="' . _AM_SYSTEM_TEMPLATES_RESTORE . '" title="' . _AM_SYSTEM_TEMPLATES_RESTORE . '">
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
                        . htmlspecialchars((string) $content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</textarea></td>
                </tr>
              </table>';
        // The ONE token this form renders. It serves BOTH posts: the restore
        // JS reads it with .val() (validated without consuming - see
        // tpls_restore below) and the save submits it normally. A second
        // getTokenHTML() here would put two same-name inputs in the form and
        // make each path depend silently on input order - one token, shared,
        // is the contract (review catch).
        XoopsLoad::load('XoopsFormHiddenToken');
        $xoopsToken = new XoopsFormHiddenToken();
        echo $xoopsToken->render();
        // path_file re-derived from the validated path; the old hidden
        // "file" and "ext" fields are gone - tpls_save never read either
        // (review catch).
        echo '<input type="hidden" name="path_file" value="' . htmlspecialchars($relPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></form>';
        break;

    // Restore backup file
    case 'tpls_restore':
        // check(false): validate WITHOUT consuming. The editor form renders
        // one token shared by restore and save - the default check() would
        // burn it here and refuse the save that follows (review catch).
        if (!$GLOBALS['xoopsSecurity']->check(false)) {
            xoops_error(implode('<br>', $GLOBALS['xoopsSecurity']->getErrors()));
            break;
        }
        // The button now posts the validated RELATIVE path and the server
        // rebuilds the root side - the same contract as the editor and the
        // save, which removes the old absolute-path special case (and the
        // server-path leak into admin HTML). Interior NULs are rejected
        // explicitly; edge NULs are silently trimmed by getString(), so the
        // value that arrives here is a NUL-free path facing the same
        // containment checks as any other input. ValueError stays as backstop.
        $restoreRel = Request::getString('path_file', '', 'POST');
        // PathGuard file mode covers the NUL refusal, the false-realpath
        // narrowing static analysis asked for, the root-plus-separator
        // containment (SECURITY.md A2-M-3), and the template-extension
        // allowlist in one call.
        $resolved = ('' === $restoreRel)
            ? false
            : PathGuard::resolveFile(XOOPS_ROOT_PATH . '/themes', trim($restoreRel), ['css', 'html', 'htm', 'tpl']);
        if (false === $resolved) {
            xoops_error(_AM_SYSTEM_TEMPLATES_RESTORE_NOTOK);
            break;
        }

        $old_file = $resolved . '.back';
        $new_file = $resolved;
        // is_link(): a planted symlink at .back must never become the live
        // template via rename() - rename moves the LINK itself into place,
        // and the web server may then follow it when serving the file. The
        // realpath guards refuse such a file at edit/save time, but the
        // served path would bypass them (review catch; the save-side backup
        // is symlink-proof the same way).
        if (!is_link($old_file) && file_exists($old_file) && file_exists($new_file)) {
            // No unlink() first: the old delete-then-rename pair could
            // remove the live template and then fail the rename, leaving
            // NEITHER file. rename() replaces the destination atomically
            // (verified on Windows PHP 8.5 too); where a filesystem
            // refuses the replace, the restore reports failure with the
            // template intact (review catch).
            // Scoped handler: rename()'s native warning embeds both full
            // server paths, and a registered error handler still receives
            // it even under this file's error_reporting(0) - verified by
            // execution. The explicit diagnostic names only the basename
            // (review catch, mirrors the save path).
            $renamed = false;
            set_error_handler(static function (): bool {
                return true;
            }, E_WARNING);
            try {
                $renamed = rename($old_file, $new_file);
            } finally {
                restore_error_handler();
            }
            if ($renamed) {
                xoops_result(_AM_SYSTEM_TEMPLATES_RESTORE_OK);
                exit();
            }
            trigger_error('Template restore failed for ' . basename($new_file), E_USER_WARNING);
        }
        xoops_error(_AM_SYSTEM_TEMPLATES_RESTORE_NOTOK);
        break;
}
