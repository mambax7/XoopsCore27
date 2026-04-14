<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/
/**
 * Installer path configuration page
 *
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package          installer
 * @since            2.3.0
 * @author           Haruki Setoyama  <haruki@planewave.org>
 * @author           Kazumi Ono <webmaster@myweb.ne.jp>
 * @author           Skalpa Keo <skalpa@xoops.org>
 * @author           Taiwen Jiang <phppp@users.sourceforge.net>
 * @author           DuGris (aka L. JEN) <dugris@frxoops.org>
 **/

require_once __DIR__ . '/include/common.inc.php';
defined('XOOPS_INSTALL') || die('XOOPS Installation wizard die');

include_once __DIR__ . '/class/pathcontroller.php';
include_once __DIR__ . '/../include/functions.php';

$pageHasForm = true;
$pageHasHelp = true;

$pathController = new PathController($wizard->configs['xoopsPathDefault'], $wizard->configs['dataPath']);

// Handle GET request for AJAX path checking (validation only).
// Raw $_GET — XMF autoloader is not available until the user enters the
// xoops_lib path on THIS page. This handler validates and returns HTML
// status. It does not write session state. Note: checkPath('root') reads
// version.php from the validated root path to verify XOOPS_VERSION.
$allowedPathKeys = ['root', 'data', 'lib'];
$rawAction      = $_GET['action'] ?? '';
$rawPathKey     = $_GET['var'] ?? '';
$rawNewPath     = $_GET['path'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_string($rawAction) && $rawAction === 'checkpath') {
    $pathKey = is_string($rawPathKey) ? trim($rawPathKey) : '';
    $newPath = is_string($rawNewPath) ? trim($rawNewPath) : '';

    // Whitelist the path key — reject unknown values
    if (!in_array($pathKey, $allowedPathKeys, true)) {
        echo 'Error: Unknown path key.';
        exit();
    }

    // Normalize the path via the controller (strips traversal, trailing slashes)
    $newPath = $pathController->sanitizePath($newPath);
    if (false === $newPath || !is_dir($newPath)) {
        echo 'Error: The specified path does not exist. Please verify the folder and try again.';
        exit();
    }

    // For the library path, verify the Composer autoloader is present and readable
    $autoloader = $newPath . '/vendor/autoload.php';
    if ($pathKey === 'lib' && (!is_file($autoloader) || !is_readable($autoloader))) {
        echo 'Error: Could not find or read vendor/autoload.php in the specified library path.';
        exit();
    }

    // Perform the path check (read-only — does not write session)
    $pathController->xoopsPath[$pathKey] = $newPath;
    echo genPathCheckHtml($pathKey, $pathController->checkPath($pathKey));
    exit();
}

// PathController::checkPath('lib') requires vendor/autoload.php to exist.
// execute() syncs TRUST_PATH into the session and exits after redirect.
$pathController->execute();

ob_start();
?>
    <script type="text/javascript">
        function removeTrailing(id, val) {
            if (val[val.length - 1] == '/') {
                val = val.substr(0, val.length - 1);
                $(id).value = val;
            }

            return val;
        }

        function updPath(key, val) {
            // Remove trailing slashes
            val = removeTrailing(key, val);

            // Perform AJAX request to validate the path
            $.get(<?php echo json_encode($_SERVER['PHP_SELF']); ?>, { action: "checkpath", var: key, path: val })
                .done(function(data) {
                    // Update the path check result
                    $("#" + key + 'pathimg').html(data);
                })
                .fail(function() {
                    console.error("Error while checking path for key:", key);
                });

            // Hide permissions element if it exists
            const permsElement = $("#" + key + 'perms')[0];
            if (permsElement) {
                permsElement.style.display = 'none';
            } else {
                console.warn("Permissions element with ID '" + key + "perms' not found.");
            }
        }

    </script>
    <div class="panel panel-info">
        <div class="panel-heading"><?php echo XOOPS_PATHS; ?></div>
        <div class="panel-body">

            <div class="form-group">
                <label class="xolabel" for="root"><?php echo XOOPS_ROOT_PATH_LABEL; ?></label>
                <div class="xoform-help alert alert-info"><?php echo XOOPS_ROOT_PATH_HELP; ?></div>
                <input type="text" class="form-control" name="root" id="root" value="<?php echo installerHtmlSpecialChars($pathController->xoopsPath['root']); ?>" onchange="updPath('root', this.value)"/>
                <span id="rootpathimg"><?php echo genPathCheckHtml('root', $pathController->validPath['root']); ?></span>
            </div>

            <?php
            if ($pathController->validPath['root'] && !empty($pathController->permErrors['root'])) {
                echo '<div id="rootperms" class="x2-note">';
                echo CHECKING_PERMISSIONS . '<br><p>' . ERR_NEED_WRITE_ACCESS . '</p>';
                echo '<ul class="diags">';
                foreach ($pathController->permErrors['root'] as $path => $result) {
                    if ($result) {
                        echo '<li class="success">' . sprintf(IS_WRITABLE, $path) . '</li>';
                    } else {
                        echo '<li class="failure">' . sprintf(IS_NOT_WRITABLE, $path) . '</li>';
                    }
                }
                echo '</ul></div>';
            } else {
                echo '<div id="rootperms" class="x2-note" style="display: none;"></div>';
            }
            ?>

            <div class="form-group">
                <label for="data"><?php echo XOOPS_DATA_PATH_LABEL; ?></label>
                <div class="xoform-help alert alert-info"><?php echo XOOPS_DATA_PATH_HELP; ?></div>
                <input type="text" class="form-control" name="data" id="data" value="<?php echo installerHtmlSpecialChars($pathController->xoopsPath['data']); ?>" onchange="updPath('data', this.value)"/>
                <span id="datapathimg"><?php echo genPathCheckHtml('data', $pathController->validPath['data']); ?></span>
            </div>
            <?php
            if ($pathController->validPath['data'] && !empty($pathController->permErrors['data'])) {
                echo '<div id="dataperms" class="x2-note">';
                echo CHECKING_PERMISSIONS . '<br><p>' . ERR_NEED_WRITE_ACCESS . '</p>';
                echo '<ul class="diags">';
                foreach ($pathController->permErrors['data'] as $path => $result) {
                    if ($result) {
                        echo '<li class="success">' . sprintf(IS_WRITABLE, $path) . '</li>';
                    } else {
                        echo '<li class="failure">' . sprintf(IS_NOT_WRITABLE, $path) . '</li>';
                    }
                }
                echo '</ul></div>';
            } else {
                echo '<div id="dataperms" class="x2-note" style="display: none;"></div>';
            }
            ?>

            <div class="form-group">
                <label class="xolabel" for="lib"><?php echo XOOPS_LIB_PATH_LABEL; ?></label>
                <div class="xoform-help alert alert-info"><?php echo XOOPS_LIB_PATH_HELP; ?></div>
                <input type="text" class="form-control" name="lib" id="lib" value="<?php echo installerHtmlSpecialChars($pathController->xoopsPath['lib']); ?>" onchange="updPath('lib', this.value)"/>
                <span id="libpathimg"><?php echo genPathCheckHtml('lib', $pathController->validPath['lib']); ?></span>
            </div>

            <div id="libperms" class="x2-note" style="display: none;"></div>
            <?php
            if (!empty($pathController->errorMessage)) {
                echo '<div class="alert alert-danger" role="alert">';
                echo $pathController->errorMessage;
                echo '</div>';
            }
            ?>
        </div>
    </div>


    <div class="panel panel-info">
        <div class="panel-heading"><?php echo XOOPS_URLS; ?></div>
        <div class="panel-body">

            <div class="form-group">
                <label class="xolabel" for="url"><?php echo XOOPS_URL_LABEL; ?></label>
                <div class="xoform-help alert alert-info"><?php echo XOOPS_URL_HELP; ?></div>
                <input type="text" class="form-control" name="URL" id="url" value="<?php echo installerHtmlSpecialChars($pathController->xoopsUrl); ?>" onchange="removeTrailing('url', this.value)"/>
            </div>

            <div class="form-group">
                <label class="xolabel" for="cookie_domain"><?php echo XOOPS_COOKIE_DOMAIN_LABEL; ?></label>
                <div class="xoform-help alert alert-info"><?php echo XOOPS_COOKIE_DOMAIN_HELP; ?></div>
                <input type="text" class="form-control" name="COOKIE_DOMAIN" id="cookie_domain" value="<?php echo installerHtmlSpecialChars($pathController->xoopsCookieDomain); ?>" onchange="removeTrailing('url', this.value)"/>
            </div>
        </div>
    </div>

<?php
$content = ob_get_contents();
ob_end_clean();

include __DIR__ . '/include/install_tpl.php';
