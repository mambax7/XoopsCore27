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
 * XOOPS Upgrade wizard.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since     2.3.0
 * @author    Skalpa Keo <skalpa@xoops.org>
 * @author    Taiwen Jiang <phppp@users.sourceforge.net>
 * @author    XOOPS Development Team
 */

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

if (!isset($_SESSION['preflight']) || (isset($_SESSION['preflight']) && $_SESSION['preflight'] !== 'complete')) {
    $_SESSION['preflight'] = 'active';
    header("Location: ./preflight.php");
    exit;
}

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
$upgradeControl = new \Xoops\Upgrade\UpgradeControl($GLOBALS['xoopsDB']);

// Determine language FIRST (loads 'upgrade' language internally)
$upgradeControl->determineLanguage();

// Then load additional language files
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
    include_once XOOPS_ROOT_PATH . '/language/english/user.php';
}
$upgradeControl->loadLanguage('smarty4');

$upgradeControl->storeMainfileCheck($needMainfileRewrite, $mainfileKeys);
$upgradeControl->buildUpgradeQueue();

ob_start();
global $xoopsUser;
if (!$xoopsUser || !$xoopsUser->isAdmin()) {
    include_once __DIR__ . '/login.php';
} else {
    $op = Xmf\Request::getCmd('action', '');
    if (!$upgradeControl->needUpgrade) {
        $op = '';
    }
    if (empty($op)) {
        $upgradeControl->loadLanguage('welcome');
        echo _XOOPS_UPGRADE_WELCOME;
    } else {
        if (!empty($upgradeControl->needWriteFiles)) {
            echo '<div class="panel panel-danger">'
                . '<div class="panel-heading">' . _SET_FILES_WRITABLE . '</div>'
                . '<div class="panel-body"><ul class="fa-ul">';
            foreach ($upgradeControl->needWriteFiles as $file) {
                echo '<li><i class="fa-li fa-solid fa-ban text-danger"></i>' . $file . '</li>';
                $error = true;
            }
            echo '</ul></div></div>';
        } else {
            $next = $upgradeControl->getNextPatch();
            printf('<h2>' . _PERFORMING_UPGRADE . '</h2>', $next);
            /** @var XoopsUpgrade $upgrader */
            $upgrader = $upgradeControl->upgradeQueue[$next]->getPatch();
            $res = $upgrader->apply();
            if ($message = $upgrader->message()) {
                echo '<div class="well">' . $message . '</div>';
            }

            if ($res) {
                $upgradeControl->upgradeQueue[$next]->applied = true;
            } else {
                $error = true;
            }
        }
    }
    if (0 === $upgradeControl->countUpgradeQueue()) {
        echo $upgradeControl->oneButtonContinueForm(
            XOOPS_URL . '/modules/system/admin.php?fct=modulesadmin&amp;op=update&amp;module=system',
            [],
        );
    } else {
        echo $upgradeControl->oneButtonContinueForm();
    }
}
$content = ob_get_contents();
ob_end_clean();

// Pre-compute support data for all languages
$allSupportSites = [];
foreach ($upgradeControl->availableLanguages() as $lang) {
    $upgradeControl->supportSites = [];
    $upgradeControl->loadLanguage('support', $lang);
    $allSupportSites[$lang] = $upgradeControl->supportSites;
}

$viewModel = [
    'content'         => $content,
    'upgradeQueue'    => $upgradeControl->upgradeQueue,
    'upgradeLanguage' => $upgradeControl->upgradeLanguage,
    'patchCount'      => $upgradeControl->countUpgradeQueue(),
    'hasError'        => $error,
    'preflightDone'   => ($_SESSION['preflight'] ?? '') === 'complete',
    'languages'       => $upgradeControl->availableLanguages(),
    'supportSites'    => $allSupportSites,
];

include_once __DIR__ . '/upgrade_tpl.php';
