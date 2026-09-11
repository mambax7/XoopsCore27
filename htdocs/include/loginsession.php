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
 * Park an authenticated login behind the second factor.
 *
 * The session is regenerated and emptied so nothing of a previous session
 * survives, and only the pending record is written: no xoopsUserId, so the
 * visitor stays anonymous to every other page until the challenge completes.
 * The password digest lets the challenge notice a password change; the
 * expiry is the time to open an authenticator app, not a security boundary.
 *
 * @param XoopsUser $user       the authenticated account
 * @param string    $state      the factor state the gate found
 * @param string    $generation the row generation the gate found ('' when none)
 * @param bool      $remember   whether the visitor asked to be remembered
 * @param string    $redirect   the posted xoops_redirect value, or ''
 * @return never
 */
function xoops_login_begin_challenge(XoopsUser $user, string $state, string $generation, bool $remember, string $redirect): never
{
    if (!empty($GLOBALS['xoopsConfig']['usercookie'])) {
        xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600, '/', XOOPS_COOKIE_DOMAIN, 0, true);
        xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600);
    }
    if (!$GLOBALS['sess_handler']->regenerate_id(true)) {
        $_SESSION = [];
        redirect_header(XOOPS_URL . '/user.php', 3, _US_2FA_UNAVAILABLE, false);
        exit();
    }
    $_SESSION                    = [];
    $_SESSION['xoops2faPending'] = [
        'uid'        => (int) $user->getVar('uid'),
        'state'      => $state,
        'generation' => $generation,
        'passdigest' => hash('sha256', (string) $user->getVar('pass', 'n')),
        'expires'    => time() + 300,
        'remember'   => $remember,
        'redirect'   => $redirect,
    ];
    redirect_header(XOOPS_URL . '/user.php?op=2fa', 1, _US_2FA_PROMPT, false);
    exit();
}

/**
 * Establish the session for an authenticated account and redirect.
 *
 * Everything that used to follow the password check: last_login, the session,
 * the login event, the remember-me cookie, notification maintenance and the
 * redirect. A second factor, when one is configured, runs before this.
 *
 * @param XoopsUser   $user               the authenticated account
 * @param bool        $remember           whether the visitor asked to be remembered
 * @param string      $redirect           the posted xoops_redirect value, or ''
 * @param string|null $verifiedGeneration the generation a completed challenge verified against, or null for a password-only login
 * @return never
 */
function xoops_login_establish_session(XoopsUser $user, bool $remember, string $redirect, ?string $verifiedGeneration = null): never
{
    global $xoopsConfig;

    // The factor row decides the session stamp and remember-me eligibility.
    // A completed challenge passes the generation it verified against, and
    // the row must still carry it: a reset between code consumption and this
    // point changes the generation, and the completion is refused rather
    // than adopted. A lookup failure here establishes nothing.
    /** @var XoopsUser2faHandler $factorHandler */
    $factorHandler = xoops_getHandler('user2fa');
    try {
        $factorRow = $factorHandler->getRow((int) $user->getVar('uid'));
    } catch (\Throwable $e) {
        redirect_header(XOOPS_URL . '/user.php', 3, _US_2FA_UNAVAILABLE);
        exit();
    }
    $factorGeneration = is_array($factorRow) ? (string) $factorRow['generation'] : '';
    // "Enrolled" here is any present row that is not disabled: a row this
    // code cannot check is a factor too, and refuses a cookie just the same.
    $factorEnrolled   = is_array($factorRow) && XoopsUser2faHandler::ROW_DISABLED !== $factorRow['state'];
    if (null !== $verifiedGeneration && (!$factorEnrolled || !hash_equals($factorGeneration, $verifiedGeneration))) {
        redirect_header(XOOPS_URL . '/user.php', 3, _US_2FA_STARTAGAIN);
        exit();
    }

    try {
        xoops_login_set_session($user, $factorGeneration, null !== $verifiedGeneration);
    } catch (\RuntimeException $e) {
        redirect_header(XOOPS_URL . '/user.php', 3, _US_2FA_UNAVAILABLE, false);
        exit();
    }

    /** @var XoopsMemberHandler $member_handler */
    $member_handler = xoops_getHandler('member');
    $user->setVar('last_login', time());
    if (!$member_handler->insertUser($user)) {
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
        // An enrolled account never receives a cookie in this phase, whatever
        // the policy; a cookie for any other account carries the row's
        // generation so a later enrolment or reset revokes it.
        $rememberKey = null;
        if ($remember && !$factorEnrolled) {
            xoops_load('XoopsUserUtility');
            $rememberKey = XoopsUserUtility::rememberKey();
        }
        if (null !== $rememberKey) {
            $claims = [
                'uid' => $_SESSION['xoopsUserId'],
                'pfp' => XoopsUserUtility::rememberFingerprint($user, $rememberKey->getSigning()),
                'fgen' => $factorGeneration,
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

/**
 * Establish fresh session state after login or committed enrolment, without redirecting.
 * @throws \RuntimeException when the session identifier cannot be replaced
 */
function xoops_login_set_session(XoopsUser $user, string $factorGeneration, bool $verified): void
{
    if (!$GLOBALS['sess_handler']->regenerate_id(true)) {
        $_SESSION = [];
        throw new \RuntimeException('Session rotation failed');
    }
    $_SESSION = [];
    $_SESSION['xoopsUserId'] = $user->getVar('uid');
    $_SESSION['xoopsUserGroups'] = $user->getGroups();
    $_SESSION['xoops2faGeneration'] = $factorGeneration;
    $_SESSION['xoops2faVerified'] = $verified;
    $theme = xoops_validateThemeName((string) $user->getVar('theme', 'n'));
    if ('' !== $theme && in_array($theme, $GLOBALS['xoopsConfig']['theme_set_allowed'], true)) {
        $_SESSION['xoopsUserTheme'] = $theme;
    }
}
