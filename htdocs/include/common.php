<?php
/**
 * XOOPS common initialization file
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
 */
defined('XOOPS_MAINFILE_INCLUDED') || die('Restricted access');

global $xoops, $xoopsPreload, $xoopsLogger, $xoopsErrorHandler, $xoopsSecurity, $sess_handler;

/**
 * YOU SHOULD NEVER USE THE FOLLOWING TO CONSTANTS, THEY WILL BE REMOVED
 */
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('NWLINE') or define('NWLINE', "\n");

/**
 * Include files with definitions
 */
include_once XOOPS_ROOT_PATH . '/include/defines.php';
include_once XOOPS_ROOT_PATH . '/include/version.php';
include_once XOOPS_ROOT_PATH . '/include/license.php';

/**
 * Include XoopsLoad
 */
require_once XOOPS_ROOT_PATH . '/class/xoopsload.php';

/**
 * YOU SHOULD BE CAREFUL WITH THE PRELOAD METHODS IN 2.4*, THEY WILL BE DEPRECATED AND IMPLEMENTED IN A DIFFERENT WAY
 */
/**
 *  Create Instance of Preload Object
 */
XoopsLoad::load('preload');
$xoopsPreload = XoopsPreload::getInstance();
$xoopsPreload->triggerEvent('core.include.common.start');

/**
 * YOU SHOULD BE CAREFUL WITH THE {@xos_kernel_Xoops2}, MOST METHODS WILL BE DEPRECATED
 */
/**
 * Create Instance of xos_kernel_Xoops2 Object
 * Attention, not all methods can be used at this point
 */
XoopsLoad::load('xoopskernel');
$xoops = new xos_kernel_Xoops2();
$xoops->pathTranslation();
$xoopsRequestUri = & $_SERVER['REQUEST_URI'];// Deprecated (use the corrected $_SERVER variable now)

/**
 * Create Instance of XoopsSecurity Object and check Superglobals
 */
XoopsLoad::load('xoopssecurity');
$xoopsSecurity = new XoopsSecurity();
$xoopsSecurity->checkSuperglobals();

/**
 * Create Instance of XoopsLogger Object
 */
XoopsLoad::load('xoopslogger');
$xoopsLogger       = XoopsLogger::getInstance();
$xoopsErrorHandler = XoopsLogger::getInstance();
$xoopsLogger->startTime();
$xoopsLogger->startTime('XOOPS Boot');

/**
 * Attach the file logger IMMEDIATELY when xoops_data/data/debug.php asks for it.
 *
 * Deliberately here and not beside the debug_mode block further down: everything between
 * the two points -- the database connection, config loading, the theme resolve -- is
 * where a broken site actually fails, and those are precisely the errors and queries
 * worth having. Registering after them meant the most useful diagnostics were never
 * written.
 *
 * Only XOOPS_VAR_PATH is required, which mainfile.php has already defined. The logger
 * takes the same seat DebugBar uses, so it receives notices, warnings, errors,
 * deprecations and SQL with no change to any producer. With no debug.php this is one
 * file_exists() and nothing more.
 */
// Guarded exactly as mainfile.php guards it, and for the same reason. Leaving this
// unguarded made mainfile.php's care pointless: it survives the partial upgrade, then
// common.php fatals fifteen lines later. include_once is a no-op when mainfile.php
// already loaded the file, so on a healthy install this costs nothing.
$xoopsDebugLoader = XOOPS_ROOT_PATH . '/include/debugconfig.php';
if (is_readable($xoopsDebugLoader)) {
    try {
        require_once $xoopsDebugLoader;
    } catch (\Throwable $e) {
        // Fail closed, and deliberately silent: nothing has configured error display yet.
        //
        // Nothing is recorded here either. xoops_setDebugConfigError() lives in the file
        // that just failed to parse, so in this exact case it does not exist to be called
        // -- the recorder covers a broken debug.php, not a broken debugconfig.php. An
        // earlier comment here pointed at it as though it applied.
    }
}
unset($xoopsDebugLoader);

$xoopsDebugConfig = function_exists('xoops_getDebugConfig') ? xoops_getDebugConfig() : [];

// Called unconditionally, not only when a debug.php exists. XOOPS_ENVIRONMENT and
// XOOPS_ENV are defined at the top of this function precisely so they exist on every
// request -- and guarding the call meant they were absent on exactly the sites that have no
// debug.php, which is every production site. A consumer reading them bare then fatals in
// production and nowhere else. With no config the call sets both constants and returns.
if (function_exists('xoops_applyDebugConfig')) {
    xoops_applyDebugConfig();
}

if ([] !== $xoopsDebugConfig) {
    // Applied HERE as well as further down. On an install whose mainfile.php predates
    // 2.7.3 nothing has raised error_reporting yet, so php.ini still governs -- and if
    // that is restrictive, handleError() discards the early errors before any logger sees
    // them. Attaching the logger early is pointless unless reporting is raised with it.

    // 'core_log' since 2.7.3; xoops_getDebugConfig() still publishes the older
    // 'log' spelling as an alias pointing at the same array, so a debug.php
    // written before the rename needs no edit.
    if (!empty($xoopsDebugConfig['core_log']['enabled'])) {
        XoopsLoad::load('filelogger');
        if (class_exists('XoopsFileLogger', false)) {
            $xoopsLogger->addLogger(new XoopsFileLogger((array) $xoopsDebugConfig['core_log']));
        }
    }
}
unset($xoopsDebugConfig);

/**
 * Include Required Files
 */
include_once $xoops->path('kernel/object.php');
include_once $xoops->path('class/criteria.php');
include_once $xoops->path('class/module.textsanitizer.php');
require_once $xoops->path('include/xoopssetcookie.php');
include_once $xoops->path('include/functions.php');

/* new installs should create this in mainfile */
if (!defined('XOOPS_COOKIE_DOMAIN')) {
    define('XOOPS_COOKIE_DOMAIN', xoops_getBaseDomain(XOOPS_URL));
}

/**
 * Check Proxy;
 * Requires functions
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$xoopsSecurity->checkReferer(XOOPS_DB_CHKREF)) {
    define('XOOPS_DB_PROXY', 1);
}

/**
 * Get database for making it global
 * Requires XoopsLogger, XOOPS_DB_PROXY;
 */
include_once $xoops->path('class/database/databasefactory.php');
/** @var XoopsMySQLDatabase $xoopsDB */
$xoopsDB = XoopsDatabaseFactory::getDatabaseConnection();

/**
 * Get xoops configs
 * Requires functions and database loaded
 */
/** @var XoopsConfigHandler $config_handler */
$config_handler = xoops_getHandler('config');
$xoopsConfig    = $config_handler->getConfigsByCat(XOOPS_CONF);

/**
 * Merge file and db configs.
 */
if (file_exists($file = $GLOBALS['xoops']->path('var/configs/xoopsconfig.php'))) {
    $fileConfigs = include $file;
    $xoopsConfig = array_merge($xoopsConfig, (array) $fileConfigs);
    unset($fileConfigs, $file);
} else {
    trigger_error('File Path Error: ' . 'var/configs/xoopsconfig.php' . ' does not exist.');
}

// Boundary normalise theme_set / theme_set_allowed once, so every
// downstream reader (header.php, site-closed.php, editors, theme
// factory, the legacy CSS path helpers) sees a validated current
// theme and a validated allowed list. xoops_getConfigOption() has its
// own cache and is NOT covered here — the few callers that go through
// that path must use xoops_resolveThemeConfig() at the call site.
$xoopsConfig = array_replace($xoopsConfig, xoops_resolveThemeConfig($xoopsConfig));

/**
 * clickjack protection - Add option to HTTP header restricting using site in an iframe
 */
$xFrameOptions = $xoopsConfig['xFrameOptions'] ?? 'sameorigin';
if (!headers_sent() && !empty($xFrameOptions)) {
    header('X-Frame-Options: ' . $xFrameOptions);
}

//check if user set a local timezone (from XavierS)
// $xoops_server_timezone="Etc/GMT";
// if ($xoopsConfig["server_TZ"]>0) {
// $xoops_server_timezone .="+".$xoopsConfig["server_TZ"]; } else{
// $xoops_server_timezone .=$xoopsConfig["server_TZ"]; } date_default_timezone_set($xoops_server_timezone);

//check if 'date.timezone' is set in php.ini
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

/**
 * Enable Gzip compression, r
 * Requires configs loaded and should go before any output
 */
$xoops->gzipCompression();

/**
 * Start of Error Reporting.
 *
 * Two independent switches feed this:
 *  - $xoopsConfig['debug_mode'], set in Admin -> Preferences, drives the in-page debug
 *    output shown to administrators;
 *  - xoops_data/data/debug.php, if present, drives file logging and PHP error settings.
 *
 * Either can be used alone. A site is normally left with debug_mode off and no
 * debug.php, which is the historical behaviour and costs nothing.
 *
 * The loader was already included above, where the file logger is attached; the result is
 * cached, so reading it again here is free. That earlier point is also what lets an
 * install whose mainfile.php predates
 * 2.7.3 still get file logging, even though its XOOPS_DEBUG constant was fixed earlier.
 */
// Guarded like the first read above, and for the identical reason. Guarding only the
// earlier call site left this one to fatal on a truncated debugconfig.php a hundred lines
// later -- the boot cleared every guard and then died anyway, which made the whole
// fail-closed exercise decorative.
$xoopsDebugConfig = function_exists('xoops_getDebugConfig') ? xoops_getDebugConfig() : [];

if ($xoopsConfig['debug_mode'] == 1 || $xoopsConfig['debug_mode'] == 2) {
    xoops_loadLanguage('logger');
    error_reporting(E_ALL);
    $xoopsLogger->enableRendering();
    $xoopsLogger->usePopup = ($xoopsConfig['debug_mode'] == 2);
} elseif ([] !== $xoopsDebugConfig) {
    // File logging WITHOUT the in-page output.
    //
    // errors must still be reported or there is nothing to record, and rendering stays
    // off so enabling this cannot change what a visitor sees.
    //
    // activated stays FALSE on purpose. It gates the in-memory collectors ($queries,
    // $blocks, $extra) that exist only to be rendered into the page -- and nothing is
    // going to render here. Leaving it true accumulated every statement of every request
    // for a dump that never happens, which a long import would turn into an out-of-memory
    // failure. Dispatch to registered loggers is deliberately outside that guard in
    // addQuery/addBlock/addExtra/handleError, so the file logger still receives
    // everything.
    xoops_loadLanguage('logger');
    if (function_exists('xoops_applyDebugConfig')) {
        xoops_applyDebugConfig();
    }
    $xoopsLogger->activated = false;
} else {
    error_reporting(0);
    $xoopsLogger->activated = false;
}
// Not left in the global scope: this array would otherwise be visible to every template
// and module that reaches into globals. The earlier block unsets it for the same reason.
unset($xoopsDebugConfig);


/**
 * Check Bad Ip Addressed against database and block bad ones, requires configs loaded
 */
$xoopsSecurity->checkBadips();

/**
 * Load Language settings and defines
 */
$xoopsPreload->triggerEvent('core.include.common.language');
xoops_loadLanguage('global');
xoops_loadLanguage('errors');
xoops_loadLanguage('pagetype');

/**
 * User Sessions
 */
$xoopsUser        = '';
$xoopsUserIsAdmin = false;
/** @var XoopsMemberHandler $member_handler */
$member_handler   = xoops_getHandler('member');
/** @var \XoopsSessionHandler $sess_handler */
$sess_handler     = xoops_getHandler('session');
// SSL session bridge: transfers session ID from HTTP to HTTPS via POST
$sslSessionId = \Xmf\Request::getString($xoopsConfig['sslpost_name'], '', 'POST');
if ($xoopsConfig['use_ssl'] && $sslSessionId !== '' && preg_match('/^[a-zA-Z0-9,-]{26,128}$/', $sslSessionId)) {
    session_id($sslSessionId); // NOSONAR - required for SSL bridging, input is regex-validated above
} elseif ($xoopsConfig['use_mysession'] && $xoopsConfig['session_name'] != '' && $xoopsConfig['session_expire'] > 0) {
    session_name($xoopsConfig['session_name']);
    session_cache_expire($xoopsConfig['session_expire']);
    @ini_set('session.gc_maxlifetime', $xoopsConfig['session_expire'] * 60);
}

session_set_save_handler($sess_handler, true);

if (function_exists('session_status')) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
} else {
    // this should silently fail if session has already started (for PHP 5.3)
    @session_start();
}
$xoopsPreload->triggerEvent('core.behavior.session.start');
/**
 * Remove expired session for xoopsUserId
 */
if ($xoopsConfig['use_mysession']
    && $xoopsConfig['session_name'] != ''
    && !\Xmf\Request::hasVar($xoopsConfig['session_name'], 'COOKIE')
    && !empty($_SESSION['xoopsUserId'])
) {
    unset($_SESSION['xoopsUserId']);
}

/**
 * Load xoopsUserId from cookie if "Remember me" is enabled.
 */
$rememberClaims = false;
if (empty($_SESSION['xoopsUserId'])
    && !empty($GLOBALS['xoopsConfig']['usercookie'])
) {
    $rememberClaims = \Xmf\Jwt\TokenReader::fromCookie('rememberme', $GLOBALS['xoopsConfig']['usercookie']);
    if (false !== $rememberClaims && !empty($rememberClaims->uid)) {
        $_SESSION['xoopsUserId'] = $rememberClaims->uid;
    } else {
        xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600, '/', XOOPS_COOKIE_DOMAIN, 0, true);
        xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600);
    }
}

/**
 * Log user in and deal with Sessions and Cookies
 */
if (!empty($_SESSION['xoopsUserId'])) {
    $xoopsUser = $member_handler->getUser($_SESSION['xoopsUserId']);
    if (!is_object($xoopsUser)) {
        $xoopsUser = '';
        $_SESSION  = [];
        session_destroy();
        xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600, '/', XOOPS_COOKIE_DOMAIN, 0, true);
        xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600);
    } else {
        if (((int) $xoopsUser->getVar('last_login') + 60 * 5) < time()) {
            $sql = 'UPDATE ' . $xoopsDB->prefix('users') . " SET last_login = '" . time()
                   . "' WHERE uid = " . (int) $_SESSION['xoopsUserId'];
            try {
                $xoopsDB->exec($sql);
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    \sprintf(_DB_QUERY_ERROR, $sql) . $xoopsDB->error(),
                    E_USER_ERROR,
                );
            }
        }

        //$sess_handler->update_cookie();
        if (isset($_SESSION['xoopsUserGroups'])) {
            $xoopsUser->setGroups($_SESSION['xoopsUserGroups']);
        } else {
            $_SESSION['xoopsUserGroups'] = $xoopsUser->getGroups();
        }
        if (is_object($rememberClaims)) {   // only do during a 'remember me' login
            // Read raw via 'n' format — getVar()'s default 's' escapes '&'
            // to '&amp;', which the validator's HTML guard would reject.
            $user_theme = xoops_validateThemeName((string) $xoopsUser->getVar('theme', 'n'));
            if ($user_theme !== '' && $user_theme !== $xoopsConfig['theme_set']
                && in_array($user_theme, $xoopsConfig['theme_set_allowed'], true)
            ) {
                $_SESSION['xoopsUserTheme'] = $user_theme;
            }
            // update our remember me cookie
            $claims = [
                'uid' => $_SESSION['xoopsUserId'],
            ];
            $rememberTime = 60 * 60 * 24 * 30;
            $token = \Xmf\Jwt\TokenFactory::build('rememberme', $claims, $rememberTime);
            xoops_setcookie(
                $GLOBALS['xoopsConfig']['usercookie'],
                $token,
                time() + $rememberTime,
                '/',
                XOOPS_COOKIE_DOMAIN,
                (XOOPS_PROT === 'https://'),
                true,
            );
        }
        $xoopsUserIsAdmin = $xoopsUser->isAdmin();
    }
}
// Cookie is handled by session_set_cookie_params() in the session handler (PHP 8.2+)
// user characteristics are established
$xoopsPreload->triggerEvent('core.include.common.auth.success');

/**
 * Debug level for XOOPS
 * Check /xoops_data/configs/xoopsconfig.php for details
 *
 * Note: temporary solution only. Will be re-designed in XOOPS 3.0
 */
if ($xoopsLogger->activated) {
    $level = isset($xoopsConfig['debugLevel']) ? (int) $xoopsConfig['debugLevel'] : 2;
    if (($level == 2 && empty($xoopsUserIsAdmin)) || ($level == 1 && !$xoopsUser)) {
        error_reporting(0);
        $xoopsLogger->activated = false;
    }
    unset($level);
}

/**
 * YOU SHOULD NEVER USE THE FOLLOWING METHOD, IT WILL BE REMOVED
 */
/**
 * Theme Selection
 */
$xoops->themeSelect();
xoops_load('XoopsFormRendererInterface');
xoops_load('XoopsFormRenderer');

/**
 * Closed Site
 */
if ($xoopsConfig['closesite'] == 1) {
    include_once $xoops->path('include/site-closed.php');
}

/**
 * Load Xoops Module
 */
if (file_exists('./xoops_version.php')) {
    $url_arr        = explode('/', stristr($_SERVER['PHP_SELF'], '/modules/'));
    /** @var XoopsModuleHandler $module_handler */
    $module_handler = xoops_getHandler('module');
    $xoopsModule    = $module_handler->getByDirname($url_arr[2]);
    unset($url_arr);

    if (!$xoopsModule || !$xoopsModule->getVar('isactive')) {
        include_once $xoops->path('header.php');
        echo '<h4>' . _MODULENOEXIST . '</h4>';
        include_once $xoops->path('footer.php');
        exit();
    }
    /** @var XoopsGroupPermHandler $moduleperm_handler */
    $moduleperm_handler = xoops_getHandler('groupperm');
    if ($xoopsUser) {
        if (!$moduleperm_handler->checkRight('module_read', $xoopsModule->getVar('mid'), $xoopsUser->getGroups())) {
            redirect_header(XOOPS_URL, 1, _NOPERM, false);
        }
        $xoopsUserIsAdmin = $xoopsUser->isAdmin($xoopsModule->getVar('mid'));
    } else {
        if (!$moduleperm_handler->checkRight('module_read', $xoopsModule->getVar('mid'), XOOPS_GROUP_ANONYMOUS)) {
            redirect_header(XOOPS_URL . '/user.php?from=' . $xoopsModule->getVar('dirname', 'n'), 1, _NOPERM);
        }
    }

    if ($xoopsModule->getVar('dirname', 'n') !== 'system') {
        if (file_exists($file = $xoops->path('modules/' . $xoopsModule->getVar('dirname', 'n') . '/language/' . $xoopsConfig['language'] . '/main.php'))) {
            include_once $file;
        } elseif (file_exists($file = $xoops->path('modules/' . $xoopsModule->getVar('dirname', 'n') . '/language/english/main.php'))) {
            include_once $file;
        }
        unset($file);
    }

    if ($xoopsModule->getVar('hasconfig') == 1 || $xoopsModule->getVar('hascomments') == 1 || $xoopsModule->getVar('hasnotification') == 1) {
        $xoopsModuleConfig = $config_handler->getConfigsByCat(0, $xoopsModule->getVar('mid'));
    }
} elseif ($xoopsUser) {
    $xoopsUserIsAdmin = $xoopsUser->isAdmin(1);
}

/**
 * YOU SHOULD AVOID USING THE FOLLOWING FUNCTION, IT WILL BE REMOVED
 */
//Creates 'system_modules_active' cache file if it has been deleted.
xoops_getActiveModules();

$xoopsLogger->stopTime('XOOPS Boot');
$xoopsLogger->startTime('Module init');

$xoopsPreload->triggerEvent('core.include.common.end');

/**
 * The error screen, last of all.
 *
 * Deliberately the final statement in this file. An error screen installs its own error
 * and exception handlers, and so did XoopsLogger further up; whichever runs last owns
 * them. Activating any earlier produces a screen that is quietly displaced before the
 * first error ever happens -- which presents as the screen being broken rather than as
 * the screen being overwritten, and is a genuinely unpleasant afternoon.
 *
 * Core registers nothing itself and knows no provider by name: it reads the owner
 * declared in xoops_data/data/debug.php and triggers core.debug.errorscreen, which the
 * module owning that token answers. Always defines XOOPS_ERROR_SCREEN_STATUS and
 * XOOPS_ERROR_SCREEN_MESSAGE, whatever the outcome, so a module can tell "no provider is
 * installed" apart from "a provider is installed and currently off". Costs one function
 * call and a cached array read when no debug.php exists.
 */
if (function_exists('xoops_activateErrorScreen')) {
    xoops_activateErrorScreen();
}
