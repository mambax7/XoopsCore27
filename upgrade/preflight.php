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
 * XOOPS Upgrade Pre-flight checks (Smarty migration).
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since     2.3.0
 * @author    Skalpa Keo <skalpa@xoops.org>
 * @author    Taiwen Jiang <phppp@users.sourceforge.net>
 * @author    XOOPS Development Team
 */

use Xoops\Upgrade\ScannerOutput;
use Xoops\Upgrade\ScannerProcess;
use Xoops\Upgrade\ScannerWalker;
use Xoops\Upgrade\Smarty4ScannerOutput;
use Xoops\Upgrade\Smarty4TemplateChecks;
use Xoops\Upgrade\Smarty4TemplateRepair;
use Xoops\Upgrade\Smarty4RepairOutput;
use Xoops\Upgrade\UpgradeControl;

require_once __DIR__ . '/class/fatal_error_handler.php';

/*
 * Before xoops 2.5.8 the table 'sess_ip' was of type varchar (15). This is a problem for IPv6
 * addresses because it is longer. The upgrade process would change the column to VARCHAR(45)
 * but it requires login, which is failing. If the user has an IPv6 address, it is converted to
 * short IP during the upgrade. At the end of the upgrade IPV6 works
 *
 * Here we save the current IP address if needed
 */
$ip = $_SERVER['REMOTE_ADDR'];
if (strlen($_SERVER['REMOTE_ADDR']) > 15) {
    //new IP for upgrade
    $_SERVER['REMOTE_ADDR'] = '::1';
}

include_once __DIR__ . '/checkmainfile.php';
defined('XOOPS_ROOT_PATH') or die('Bad installation: please add this folder to the XOOPS install you want to upgrade');

$endscan = Xmf\Request::getString('endscan', 'no');
if ($endscan === 'yes') {
    $_SESSION['preflight'] = 'complete';
    header("Location: ./index.php");
    exit;
}
$_SESSION['preflight'] = 'active'; // so that manually loading preflight.php forces to active

$reporting = 0;
if (isset($_GET['debug'])) {
    $reporting = -1;
}
error_reporting($reporting);
$xoopsLogger->activated = true;
$xoopsLogger->enableRendering();
xoops_loadLanguage('logger');
set_exception_handler('fatalPhpErrorHandler'); // should have been changed by now, reset to ours

require_once __DIR__ . '/class/autoload.php';

$error = false;
$upgradeControl = new UpgradeControl($GLOBALS['xoopsDB']);

$upgradeControl->determineLanguage();

$languageRoot = realpath(__DIR__ . '/language');
$language = $upgradeControl->normalizeLanguage($upgradeControl->upgradeLanguage);
$userFile = false !== $languageRoot
    ? realpath($languageRoot . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . 'user.php')
    : false;
if (
    false !== $languageRoot
    && false !== $userFile
    && str_starts_with($userFile, $languageRoot . DIRECTORY_SEPARATOR)
) {
    include_once $userFile;
} else {
    include_once XOOPS_ROOT_PATH . "/language/english/user.php";
}
$upgradeControl->loadLanguage('smarty4');

/**
 * User options form for preflight
 *  template_dir  a directory relative to XOOPS_ROOT_PATH, i.e. /themes/ or /themes/xbootstrap/
 *  template_ext  a file extension to scan for. Typical values are tpl or html
 *  runfix        if checked, attempt to fix any issues found. Note not all possible issues can be automatically fixed
 *
 * @return string options form
 */
function tplScannerForm($parameters=null)
{
    $action = XOOPS_URL . '/upgrade/preflight.php';

    $form = '<h2>' . _XOOPS_SMARTY4_RESCAN_OPTIONS . '</h2>';
    $form .= '<form action="' . $action . '" method="post" class="form-horizontal">';

    $form .= '<div class="form-group">';
    $form .= '<input name="template_dir" class="form-control" type="text" placeholder="/themes/">';
    $form .= '<label for="template_dir">' . _XOOPS_SMARTY4_TEMPLATE_DIR  . '</label>';
    $form .= '</div>';

    $form .= '<div class="form-group">';
    $form .= '<input name="template_ext" class="form-control" type="text" placeholder="tpl">';
    $form .= '<label for="template_ext">' . _XOOPS_SMARTY4_TEMPLATE_EXT  . '</label>';
    $form .= '</div>';

    $form .= '<div class="form-group row">';
    $form .= '<div class="form-check">';
    $form .= '<legend class="col-form-label">' . _XOOPS_SMARTY4_FIX_BUTTON . '</legend>';
    $form .= '<input class="form-check-input" type="checkbox" name="runfix" >';
    $form .= '<label class="form-check-label" for="runfix">' . _YES . '</label>';
    $form .= '</div>';
    $form .= '</div>';

    $form .= '<div class="form-group">';
    $form .= '<button class="btn btn-lg btn-success" type="submit">' . _XOOPS_SMARTY4_SCANNER_RUN;
    $form .= '  <span class="fa-solid fa-caret-right"></span></button>';
    $form .= '</div>';

    $form .= '</form>';

    $form .= '<form action="' . $action . '" method="post" class="form-horizontal">';
    $form .= '<div class="form-group">';
    $form .= '<button class="btn btn-lg btn-danger" type="submit">' . _XOOPS_SMARTY4_SCANNER_END;
    $form .= '  <span class="fa-solid fa-caret-right"></span></button>';
    $form .= '<input type="hidden" name="endscan" value="yes">';
    $form .= '</div>';

    $form .= '</form>';

    return $form;
}

ob_start();

global $xoopsUser;
if (!$xoopsUser || !$xoopsUser->isAdmin()) {
    include_once __DIR__ . '/login.php';
} else {
    $template_dir = Xmf\Request::getString('template_dir', '');
    $template_ext = Xmf\Request::getString('template_ext', '');
    $runfix = Xmf\Request::getString('runfix', 'off');
    // Xmf\Debug::dump($_POST, $runfix, $template_dir, $template_ext);
    if (empty($op)) {
        $upgradeControl->loadLanguage('welcome');
        echo _XOOPS_SMARTY4_SCANNER_OFFER;
    }

    if ($runfix==='on') {
        $output = new Smarty4RepairOutput();
        $process = new Smarty4TemplateRepair($output);
    } else {
        $output = new Smarty4ScannerOutput();
        $process = new Smarty4TemplateChecks($output);
    }
    $scanner = new ScannerWalker($process, $output);
    if('' === $template_dir) {
        $scanner->addDirectory(XOOPS_ROOT_PATH . '/themes/');
        $scanner->addDirectory(XOOPS_ROOT_PATH . '/modules/');
    } else {
        $scanner->addDirectory(XOOPS_ROOT_PATH . $template_dir);
    }
    if('' === $template_ext) {
        $scanner->addExtension('tpl');
        $scanner->addExtension('html');
    } else {
        $scanner->addExtension($template_ext);
    }
    $scanner->runScan();

    echo $output->outputFetch();

    echo tplScannerForm();
}
$content = ob_get_contents();
ob_end_clean();

//echo $content;

$allSupportSites = [];
foreach ($upgradeControl->availableLanguages() as $lang) {
    $upgradeControl->supportSites = [];
    $upgradeControl->loadLanguage('support', $lang);
    $allSupportSites[$lang] = $upgradeControl->supportSites;
}

$viewModel = [
    'content'         => $content,
    'upgradeQueue'    => [],
    'upgradeLanguage' => $upgradeControl->upgradeLanguage,
    'patchCount'      => 0,
    'hasError'        => $error,
    'preflightDone'   => false,
    'languages'       => $upgradeControl->availableLanguages(),
    'supportSites'    => $allSupportSites,
];

include_once __DIR__ . '/upgrade_tpl.php';
