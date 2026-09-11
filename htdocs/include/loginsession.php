<?php
/**
 * Password login helpers: authenticate, then establish the session.
 *
 * Split out of include/checklogin.php so that a second authentication factor
 * can sit between the two steps. This file declares functions only; it has no
 * side effects on include.
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
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Check the credentials and the account's right to log in.
 *
 * Redirects and exits when the account is inactive or the site is closed to
 * its groups, as the inline code did. Writes no session or login state:
 * last_login, the session and the remember-me cookie belong to
 * xoops_login_establish_session(). The adapter itself may still write, as it
 * always has: the native adapter persists a rehashed password on a
 * successful check, and LDAP provisioning may create or update the account.
 *
 * @param string $uname posted user name
 * @param string $pass  posted password
 * @return XoopsUser|false the authenticated account, or false
 */
function xoops_login_authenticate(string $uname, string $pass)
{
    global $xoopsConfig;

    include_once $GLOBALS['xoops']->path('class/auth/authfactory.php');
    xoops_loadLanguage('auth');
    /** @var XoopsMySQLDatabase $xoopsDB */
    $xoopsDB   = XoopsDatabaseFactory::getDatabaseConnection();
    $xoopsAuth = XoopsAuthFactory::getAuthConnection($xoopsDB->escape($uname));
    $user      = $xoopsAuth->authenticate($uname, $pass);
    if (false === $user) {
        return false;
    }
    if (0 == $user->getVar('level')) {
        redirect_header(XOOPS_URL . '/index.php', 5, _US_NOACTTPADM);
    }
    if ($xoopsConfig['closesite'] == 1) {
        $allowed = false;
        foreach ($user->getGroups() as $group) {
            if (in_array($group, $xoopsConfig['closesite_okgrp']) || XOOPS_GROUP_ADMIN == $group) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            redirect_header(XOOPS_URL . '/index.php', 1, _NOPERM);
        }
    }

    return $user;
}

/**
 * Establish the session for an authenticated account and redirect.
 *
 * Everything that used to follow the password check: last_login, the session,
 * the login event, the remember-me cookie, notification maintenance and the
 * redirect. A second factor, when one is configured, runs before this.
 *
 * @param XoopsUser $user     the authenticated account
 * @param bool      $remember whether the visitor asked to be remembered
 * @param string    $redirect the posted xoops_redirect value, or ''
 * @return never
 */
function xoops_login_establish_session(XoopsUser $user, bool $remember, string $redirect): never
{
    global $xoopsConfig;

    /** @var XoopsMemberHandler $member_handler */
    $member_handler = xoops_getHandler('member');
    $user->setVar('last_login', time());
    if (!$member_handler->insertUser($user)) {
    }
    // Regenerate a new session id and destroy old session
    $GLOBALS['sess_handler']->regenerate_id(true);
    $_SESSION                    = [];
    $_SESSION['xoopsUserId']     = $user->getVar('uid');
    $_SESSION['xoopsUserGroups'] = $user->getGroups();
    // Read raw via 'n' format — getVar()'s default 's' escapes '&' to
    // '&amp;', which the validator's HTML guard would reject.
    $user_theme = xoops_validateThemeName((string) $user->getVar('theme', 'n'));
    if ($user_theme !== '' && in_array($user_theme, $xoopsConfig['theme_set_allowed'], true)) {
        $_SESSION['xoopsUserTheme'] = $user_theme;
    }
    $xoopsPreload = XoopsPreload::getInstance();
    $xoopsPreload->triggerEvent('core.behavior.user.login', $user);
    // Set cookie for rememberme
    if (!empty($GLOBALS['xoopsConfig']['usercookie'])) {
        // The fingerprint of the stored hash lets a later password change
        // revoke this token; $user already carries a rehashed password when
        // loginUser() rehashed it. One snapshot of the key serves both the
        // fingerprint and the signature, and without a key nothing is issued.
        // The key is read only on request: reading it creates the key file.
        // rememberKey() explains a null result itself with a warning.
        $rememberKey = null;
        if ($remember) {
            xoops_load('XoopsUserUtility');
            $rememberKey = XoopsUserUtility::rememberKey();
        }
        if (null !== $rememberKey) {
            $claims = [
                'uid' => $_SESSION['xoopsUserId'],
                'pfp' => XoopsUserUtility::rememberFingerprint($user, $rememberKey->getSigning()),
            ];
            $rememberTime = 60 * 60 * 24 * 30;
            $token = \Xmf\Jwt\TokenFactory::build($rememberKey, $claims, $rememberTime);
            xoops_setcookie(
                $GLOBALS['xoopsConfig']['usercookie'],
                $token,
                time() + $rememberTime,
                '/',
                XOOPS_COOKIE_DOMAIN,
                XOOPS_PROT === 'https://',
                true,
            );
        } else {
            xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600, '/', XOOPS_COOKIE_DOMAIN, 0, true);
            xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600);
        }
    }

    if (!empty($redirect) && !strpos($redirect, 'register')) {
        $xoops_redirect = rawurldecode($redirect);
        $parsed         = parse_url(XOOPS_URL);
        $url            = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : 'http://';
        if (isset($parsed['host'])) {
            $url .= $parsed['host'];
            if (isset($parsed['port'])) {
                $url .= ':' . $parsed['port'];
            }
        } else {
            $host = parse_url(XOOPS_URL, PHP_URL_HOST);
            if (!is_string($host)) {
                $host = ''; // Or a safe default/fallback
            }
            $url .= $host;
        }
        if (isset($parsed['path']) && $parsed['path']) {
            if (strncmp($parsed['path'], $xoops_redirect, strlen($parsed['path']))) {
                $url .= $parsed['path'];
            }
        }
        $url .= $xoops_redirect;
    } else {
        $url = XOOPS_URL . '/index.php';
    }

    // RMV-NOTIFY
    // Perform some maintenance of notification records
    /** @var \XoopsNotificationHandler $notification_handler */
    $notification_handler = xoops_getHandler('notification');
    $notification_handler->doLoginMaintenance($user->getVar('uid'));

    redirect_header($url, 1, sprintf(_US_LOGGINGU, $user->getVar('uname')), false);
    exit();
}
