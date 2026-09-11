<?php
/**
 * XOOPS authentication/authorization
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
 * @package             core
 * @since               2.0.0
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

xoops_loadLanguage('user');
xoops_loadLanguage('user2fa');

// from $_POST we use keys: uname, pass, rememberme, xoops_redirect
XoopsLoad::load('XoopsRequest');
$uname = XoopsRequest::getString('uname', '', 'POST');
$pass = XoopsRequest::getString('pass', '', 'POST');
$rememberme = XoopsRequest::getString('rememberme', '', 'POST');
$redirect = XoopsRequest::getUrl('xoops_redirect', '', 'POST');

if ($uname == '' || $pass == '') {
    redirect_header(XOOPS_URL . '/user.php', 1, _US_INCORRECTLOGIN);
}

require_once $GLOBALS['xoops']->path('include/loginsession.php');

$user = xoops_login_authenticate($uname, $pass);

if (false !== $user) {
    // The gate: a failed factor lookup is "unavailable" and challenges, so a
    // database blip never turns into a password-only login for an enrolled
    // account. The row is read once; the challenge gets its generation.
    /** @var XoopsUser2faHandler $factorHandler */
    $factorHandler = xoops_getHandler('user2fa');
    try {
        $factorRow        = $factorHandler->getRow((int) $user->getVar('uid'));
        $factorState      = $factorHandler->stateOfRow($factorRow);
        $factorGeneration = is_array($factorRow) ? (string) $factorRow['generation'] : '';
    } catch (\Throwable $e) {
        $factorState      = XoopsUser2faHandler::STATE_UNAVAILABLE;
        $factorGeneration = '';
    }
    if (XoopsUser2faHandler::mustChallenge(XoopsUser2faHandler::policy($GLOBALS['xoopsConfig']), $factorState)) {
        xoops_login_begin_challenge($user, $factorState, $factorGeneration, !empty($rememberme), $redirect);
    }
    xoops_login_establish_session($user, !empty($rememberme), $redirect);
} elseif (empty($redirect)) {
    // Generic message for every credential failure — do not reveal whether the
    // account exists or which factor failed (user enumeration, SECURITY.md L-3).
    redirect_header(XOOPS_URL . '/user.php', 5, _US_INCORRECTLOGIN);
} else {
    redirect_header(XOOPS_URL . '/user.php?xoops_redirect=' . urlencode($redirect), 5, _US_INCORRECTLOGIN, false);
}
