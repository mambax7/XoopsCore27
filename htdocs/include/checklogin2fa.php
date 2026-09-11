<?php
/**
 * Second-factor challenge.
 *
 * Reached after include/checklogin.php parked an authenticated login in
 * $_SESSION['xoops2faPending']. Re-checks the account and the factor row,
 * verifies a TOTP or recovery code through XoopsUser2faHandler and completes
 * the login through xoops_login_establish_session(); every other outcome
 * renders this page. Included by user.php, modules/profile/user.php and
 * include/site-closed.php, so it renders its own page rather than through
 * header.php. Never returns.
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
 * @since               2.7.4
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

// user.php loads the first itself; the closed-site page does not. The
// challenge strings have a file of their own so a language pack that
// predates them falls back to English as a whole, as xoops_loadLanguage()
// does for a missing file (it never fills gaps in a present one).
xoops_loadLanguage('user');
xoops_loadLanguage('user2fa');

if (!function_exists(ltrim(__NAMESPACE__ . '\\xoops_2fa_render', '\\'))) {
    /**
     * Render the challenge page and stop.
     *
     * The page is a full document of its own (like the closed-site page): it
     * must work on a closed site, where header.php never runs, and it should
     * carry no blocks or cached fragments.
     *
     * @param array $vars template variables
     * @return never
     */
    function xoops_2fa_render(array $vars): never
    {
        global $xoopsConfig;

        require_once $GLOBALS['xoops']->path('class/template.php');
        require_once $GLOBALS['xoops']->path('class/theme.php');
        $factory                = new xos_opal_ThemeFactory();
        $themeConfig            = xoops_resolveThemeConfig($xoopsConfig);
        $factory->allowedThemes = $themeConfig['theme_set_allowed'];
        $factory->defaultTheme  = $themeConfig['theme_set'];
        $xoTheme                = $factory->createInstance(['plugins' => []]);
        $themeConfig            = xoops_resolveThemeConfig($xoopsConfig);
        $tpl                    = $xoTheme->template;
        $tpl->assign([
            'xoops_theme'    => $themeConfig['theme_set'],
            'xoops_themecss' => xoops_getcss($themeConfig['theme_set']),
            'xoops_sitename' => htmlspecialchars((string) $xoopsConfig['sitename'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'xoops_charset'  => _CHARSET,
            'xoops_langcode' => _LANGCODE,
        ]);
        $tpl->assign($vars);
        $tpl->caching = 0;
        $tpl->display('db:system_user2fa.tpl');
        exit();
    }
}

// Set unconditionally: common.php already sent the site's own X-Frame-Options
// value, or none, and only an unconditional header() replaces that.
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$xo2faNow      = time();
$xo2faLoginUrl = XOOPS_URL . '/user.php';
$xo2faVars     = [
    'title'              => _US_2FA_TITLE,
    'message'            => _US_2FA_PROMPT,
    'error'              => '',
    'start_again'        => false,
    'login_url'          => $xo2faLoginUrl,
    'action_url'         => $xo2faLoginUrl,
    'token_html'         => '',
    'lang_code'          => _US_2FA_CODE,
    'lang_recovery'      => _US_2FA_RECOVERY,
    'lang_recovery_hint' => _US_2FA_RECOVERY_HINT,
    'lang_submit'        => _US_2FA_SUBMIT,
    'lang_startagain'    => _US_2FA_BACKTOLOGIN,
];

// A pending record that is missing, malformed, expired, or no longer matches
// the account is "start again", not a security error: the session handler
// drops sessions on an IP-mask change, and the record lives five minutes.
$xo2faPending = $_SESSION['xoops2faPending'] ?? null;
$xo2faValid   = is_array($xo2faPending)
    && isset($xo2faPending['uid'], $xo2faPending['state'], $xo2faPending['generation'], $xo2faPending['passdigest'], $xo2faPending['expires'])
    && is_int($xo2faPending['uid']) && is_string($xo2faPending['state']) && is_string($xo2faPending['generation'])
    && is_string($xo2faPending['passdigest']) && is_int($xo2faPending['expires']) && $xo2faPending['expires'] > $xo2faNow;

$xo2faUser = null;
$xo2faRow  = null;
if ($xo2faValid) {
    /** @var XoopsMemberHandler $xo2faMembers */
    $xo2faMembers = xoops_getHandler('member');
    $xo2faUser    = $xo2faMembers->getUser($xo2faPending['uid']);
    $xo2faValid   = is_object($xo2faUser) && (int) $xo2faUser->getVar('level') > 0
        && hash_equals($xo2faPending['passdigest'], hash('sha256', (string) $xo2faUser->getVar('pass', 'n')));
    if ($xo2faValid && 1 == $GLOBALS['xoopsConfig']['closesite']) {
        $xo2faValid = false;
        foreach ($xo2faUser->getGroups() as $xo2faGroup) {
            if (in_array($xo2faGroup, $GLOBALS['xoopsConfig']['closesite_okgrp']) || XOOPS_GROUP_ADMIN == $xo2faGroup) {
                $xo2faValid = true;
                break;
            }
        }
    }
}
/** @var XoopsUser2faHandler $xo2faHandler */
$xo2faHandler = xoops_getHandler('user2fa');
if ($xo2faValid) {
    try {
        $xo2faRow = $xo2faHandler->getRow($xo2faPending['uid']);
    } catch (\Throwable $e) {
        // Fail closed, but keep the record: a database blip should not cost
        // the visitor the password step as well.
        $xo2faVars['token_html'] = $GLOBALS['xoopsSecurity']->getTokenHTML();
        $xo2faVars['error']      = _US_2FA_UNAVAILABLE;
        xoops_2fa_render($xo2faVars);
    }
    $xo2faValid = is_array($xo2faRow)
        && XoopsUser2faHandler::ROW_ENROLLED === $xo2faRow['state']
        && hash_equals((string) $xo2faRow['generation'], $xo2faPending['generation']);
}
if (!$xo2faValid) {
    unset($_SESSION['xoops2faPending']);
    $xo2faVars['start_again'] = true;
    $xo2faVars['message']     = _US_2FA_STARTAGAIN;
    xoops_2fa_render($xo2faVars);
}

$xo2faUid        = $xo2faPending['uid'];
$xo2faGeneration = $xo2faPending['generation'];
$xo2faState      = $xo2faHandler->stateOfRow($xo2faRow);

/**
 * Best-effort account notice; never blocks the login path.
 *
 * @param XoopsUser $user    account
 * @param string    $subject sprintf template with %s = site name
 * @param string    $body    sprintf template with %s = site name, %s = client address
 */
$xo2faMail = static function (object $user, string $subject, string $body): void {
    try {
        $mailer = xoops_getMailer();
        $mailer->useMail();
        $mailer->setToUsers($user);
        $mailer->setFromEmail($GLOBALS['xoopsConfig']['adminmail']);
        $mailer->setFromName($GLOBALS['xoopsConfig']['sitename']);
        $mailer->setSubject(sprintf($subject, $GLOBALS['xoopsConfig']['sitename']));
        $mailer->setBody(sprintf($body, $GLOBALS['xoopsConfig']['sitename'], \Xmf\IPAddress::fromRequest()->asReadable()));
        $mailer->send();
    } catch (\Throwable $e) {
        trigger_error('Two-factor notice mail failed: ' . $e->getMessage(), E_USER_WARNING);
    }
};

if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? 'GET') && \Xmf\Request::hasVar('xoops_2fa', 'POST')) {
    if (!$GLOBALS['xoopsSecurity']->check()) {
        $xo2faVars['error']      = implode('<br>', $GLOBALS['xoopsSecurity']->getErrors());
        $xo2faVars['token_html'] = $GLOBALS['xoopsSecurity']->getTokenHTML();
        xoops_2fa_render($xo2faVars);
    }
    $xo2faRecovery = \Xmf\Request::getString('recovery', '', 'POST');
    $xo2faCode     = \Xmf\Request::getString('code', '', 'POST');
    $xo2faError    = _US_2FA_BADCODE;
    $xo2faCount    = true;
    $xo2faAccepted = false;  // false, or the generation the login completes with (null for password-only)
    $xo2faFailure  = false;
    // Everything in here reads or writes the factor row; a database failure
    // is "unavailable", not a 500 and not a counted failure. Nothing in here
    // exits: the completion and the renders follow the boundary.
    try {
        // Operator escape hatch, bound to the pending uid: consuming it
        // disables the factor, so the login completes as a password-only one.
        if ($xo2faHandler->resetByEscapeHatch($xo2faUid)) {
            $xo2faAccepted = null;
            $xo2faCount    = false;
        } elseif ('' !== $xo2faRecovery) {
            // Accepted during a TOTP lock as well: single-use 128-bit codes
            // cannot be guessed, and this is the legitimate user's way past
            // an attacker who knows the password and burned the throttle.
            if ($xo2faHandler->acceptRecovery($xo2faUid, $xo2faRecovery, $xo2faGeneration)) {
                $xo2faAccepted = $xo2faGeneration;
                $xo2faCount    = false;
                $xo2faMail($xo2faUser, _US_2FA_RECOVERY_MAIL_SUBJECT, _US_2FA_RECOVERY_MAIL_BODY);
            }
        } elseif ($xo2faRow['locked_until'] > $xo2faNow) {
            $xo2faError = _US_2FA_LOCKED;
            $xo2faCount = false;
        } elseif (XoopsUser2faHandler::STATE_ENROLLED !== $xo2faState) {
            $xo2faError = _US_2FA_UNAVAILABLE;
            $xo2faCount = false;
        } elseif (null === ($xo2faSecret = $xo2faHandler->secretFor($xo2faUid))) {
            // The row changed under us, or its secret cannot be read.
            $xo2faError = _US_2FA_UNAVAILABLE;
            $xo2faCount = false;
        } else {
            $xo2faStep = XoopsTotp::matchStep($xo2faSecret, $xo2faCode, $xo2faNow, (int) $xo2faRow['last_counter']);
            if (false !== $xo2faStep && $xo2faHandler->acceptTotp($xo2faUid, $xo2faStep, $xo2faGeneration, $xo2faNow)) {
                $xo2faAccepted = $xo2faGeneration;
                $xo2faCount    = false;
            }
            if (false === $xo2faStep && preg_match('/^[0-9]{6}$/', $xo2faCode)) {
                // A code two or three steps out is more likely a clock than a guess.
                $xo2faStepNow = XoopsTotp::stepAt($xo2faNow);
                foreach ([-3, -2, 2, 3] as $xo2faOffset) {
                    $xo2faExpected = XoopsTotp::codeAt($xo2faSecret, $xo2faStepNow + $xo2faOffset);
                    if (false !== $xo2faExpected && hash_equals($xo2faExpected, $xo2faCode)) {
                        trigger_error(sprintf('Two-factor code for uid %d matched %d steps from now; probable clock skew', $xo2faUid, $xo2faOffset), E_USER_NOTICE);
                        break;
                    }
                }
            }
        }
        if ($xo2faCount) {
            $xo2faFailure = $xo2faHandler->recordFailure($xo2faUid, $xo2faNow);
        }
    } catch (\Throwable $e) {
        trigger_error('Two-factor challenge failed for uid ' . $xo2faUid . ': ' . $e->getMessage(), E_USER_WARNING);
        $xo2faError    = _US_2FA_UNAVAILABLE;
        $xo2faAccepted = false;
        $xo2faFailure  = false;
    }
    if (false !== $xo2faAccepted) {
        unset($_SESSION['xoops2faPending']);
        xoops_login_establish_session($xo2faUser, (bool) $xo2faPending['remember'], (string) $xo2faPending['redirect'], $xo2faAccepted);
    }
    if (is_array($xo2faFailure) && $xo2faFailure['locked']) {
        unset($_SESSION['xoops2faPending']);
        if ($xo2faFailure['transitioned']) {
            $xo2faMail($xo2faUser, _US_2FA_LOCKED_MAIL_SUBJECT, _US_2FA_LOCKED_MAIL_BODY);
        }
        $xo2faVars['start_again'] = true;
        $xo2faVars['message']     = _US_2FA_LOCKED;
        xoops_2fa_render($xo2faVars);
    }
    $xo2faVars['error'] = $xo2faError;
}

$xo2faVars['token_html'] = $GLOBALS['xoopsSecurity']->getTokenHTML();
xoops_2fa_render($xo2faVars);
