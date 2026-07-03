<?php
/**
 * XOOPS control panel functions
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
 * @package             kernel
 * @since               2.0.0
 */

define('XOOPS_CPFUNC_LOADED', 1);
define('XOOPS_WRITE_FILE_WRITE_ERROR', 'Failed to write file: %s');

// xoops_file_label(), xoops_chmod_quietly(), and xoops_remove_file_quietly()
// live in a side-effect-free include so callers that only need the
// file helpers (e.g. modules/system/class/maintenance.php, also loaded
// from upgrade scripts) don't pick up the XOOPS_CPFUNC_LOADED define,
// which forces redirect_header() into the 'default' theme.
require_once __DIR__ . '/file_safety.php';

/**
 * CP Header
 *
 */
function xoops_cp_header()
{
    xoops_load('cpanel', 'system');
    $cpanel = XoopsSystemCpanel::getInstance();
    $cpanel->gui->header();
}

/**
 * CP Footer
 *
 */
function xoops_cp_footer()
{
    // Emit a request token on every control-panel page so admin AJAX actions
    // (status toggles, drag/drop ordering, ...) can submit and validate it.
    if (isset($GLOBALS['xoopsSecurity']) && is_object($GLOBALS['xoopsSecurity'])) {
        echo '<div id="xo-admin-token" style="display:none">'
            . $GLOBALS['xoopsSecurity']->getTokenHTML() . '</div>';
    }
    xoops_load('cpanel', 'system');
    $cpanel = XoopsSystemCpanel::getInstance();
    $cpanel->gui->footer();
}

/**
 * Open Table: DO NOT USE
 *
 * We need these because theme files will not be included
 *
 */
function openTable()
{
    echo "<table width='100%' border='0' cellspacing='1' cellpadding='8' style='border: 2px solid #2F5376;'><tr class='bg4'><td valign='top'>\n";
}

/**
 * Cloe Table : NO NOT USE
 *
 */
function closeTable()
{
    echo '</td></tr></table>';
}

/**
 * Enclose Items in a table : DO NOT USE
 *
 * @param string $title
 * @param string $content
 */
function themecenterposts($title, $content)
{
    echo '<table cellpadding="4" cellspacing="1" width="98%" class="outer"><tr><td class="head">' . $title . '</td></tr><tr><td><br>' . $content . '<br></td></tr></table>';
}

/**
 * Text Form : DO NOT USE
 *
 * @param mixed $url
 * @param mixed $value
 * @return mixed
 */
function myTextForm($url, $value)
{
    return '<form action="' . $url . '" method="post"><input type="submit" value="' . $value . '" /></form>';
}

/**
 * Enter description here...
 *
 * @return mixed
 */
function xoopsfwrite()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    } else {
    }
    if (!$GLOBALS['xoopsSecurity']->checkReferer()) {
        return false;
    } else {
    }

    return true;
}

/**
 * Write a file through a temporary sibling and replace the target on success.
 *
 * @param string $filename
 * @param string $content
 * @return bool
 */
function xoops_write_file_atomically($filename, $content)
{
    $directory = dirname($filename);
    $label     = xoops_file_label($filename);
    $tempFile  = tempnam($directory, 'xwf');
    if ($tempFile === false) {
        trigger_error(sprintf('Failed to create temp file for %s', $label), E_USER_WARNING);

        return false;
    }

    $expectedBytes = strlen($content);
    $bytesWritten  = file_put_contents($tempFile, $content, LOCK_EX);
    if ($bytesWritten === false) {
        xoops_remove_file_quietly($tempFile, 'temporary');
        trigger_error(sprintf('Failed to write file: %s', $label), E_USER_WARNING);

        return false;
    }
    if ($bytesWritten !== $expectedBytes) {
        xoops_remove_file_quietly($tempFile, 'temporary');
        trigger_error(
            sprintf(
                'Short write for %s: wrote %d of %d bytes',
                $label,
                $bytesWritten,
                $expectedBytes
            ),
            E_USER_WARNING
        );

        return false;
    }
    $targetPerms = 0644;
    if (file_exists($filename)) {
        $currentPerms = fileperms($filename);
        if ($currentPerms !== false) {
            $targetPerms = $currentPerms & 0777;
        }
    }
    // Non-fatal: file is written, only the perms may not take. Continue
    // with the rename rather than aborting — most callers care about
    // content integrity over exact perms. The helper suppresses the
    // native PHP warning so a single failure produces a single
    // project-standard log line.
    xoops_chmod_quietly($tempFile, $targetPerms, 'temp');

    // The four @rename(...) calls below are inside `if (!...)` checks —
    // failure is detected by the boolean return and reported via
    // trigger_error(). The `@` is retained to suppress PHP's native
    // warning, which would otherwise double-report alongside our own
    // diagnostic line. Removing it would not improve detection but would
    // pollute display_errors output.
    if (!@rename($tempFile, $filename)) {
        if (!file_exists($filename)) {
            xoops_remove_file_quietly($tempFile, 'temporary');
            trigger_error(sprintf(XOOPS_WRITE_FILE_WRITE_ERROR, $label), E_USER_WARNING);

            return false;
        }

        $backupFile = tempnam($directory, 'xwb');
        if ($backupFile === false) {
            xoops_remove_file_quietly($tempFile, 'temporary');
            trigger_error(sprintf(XOOPS_WRITE_FILE_WRITE_ERROR, $label), E_USER_WARNING);

            return false;
        }
        // tempnam() created a 0-byte placeholder we don't need; remove it
        // so the rename below can take its slot.
        xoops_remove_file_quietly($backupFile, 'backup');
        if (!@rename($filename, $backupFile)) {
            xoops_remove_file_quietly($tempFile, 'temporary');
            trigger_error(sprintf(XOOPS_WRITE_FILE_WRITE_ERROR, $label), E_USER_WARNING);

            return false;
        }

        if (!@rename($tempFile, $filename)) {
            if (!@rename($backupFile, $filename)) {
                xoops_remove_file_quietly($tempFile, 'temporary');
                trigger_error(
                    sprintf(
                        'Failed to replace file and restore original: %s. Original content was left in backup file %s; manual restoration may be required.',
                        $label,
                        basename($backupFile)
                    ),
                    E_USER_WARNING
                );

                return false;
            }

            xoops_remove_file_quietly($tempFile, 'temporary');
            trigger_error(sprintf(XOOPS_WRITE_FILE_WRITE_ERROR, $label), E_USER_WARNING);

            return false;
        }

        xoops_remove_file_quietly($backupFile, 'backup');
    }

    return true;
}

/**
 * Xoops Write Index File
 *
 * @param string $path
 * @return bool
 */
function xoops_write_index_file($path = '')
{
    if (empty($path)) {
        return false;
    }
    if (!xoopsfwrite()) {
        return false;
    }

    $path     = substr($path, -1) === '/' ? substr($path, 0, -1) : $path;
    $filename = $path . '/index.php';
    $content  = '<?php' . PHP_EOL
        . 'http_response_code(404);' . PHP_EOL
        . 'exit;' . PHP_EOL;
    if (file_exists($filename)) {
        return true;
    }
    if (!xoops_write_file_atomically($filename, $content)) {
        return false;
    }

    return true;
}
