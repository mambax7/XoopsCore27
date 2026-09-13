<?php
/**
 * Password-confirmed enrolment and management; also included by the users admin.
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
require_once XOOPS_ROOT_PATH . '/include/twofactor.php';
require_once XOOPS_ROOT_PATH . '/include/loginsession.php';
xoops_loadLanguage('user');
xoops_2fa_loadLanguage('user2fa');
xoops_2fa_loadLanguage('user2famanage');
xoops_2fa_sensitive_headers();

$actor = $GLOBALS['xoopsUser'];
if (!($actor instanceof XoopsUser)) {
    redirect_header(XOOPS_URL . '/user.php', 1, _US_2FA_STARTAGAIN, false);
    exit();
}
// This flag is set by the authorised users-admin include, never by request data.
$adminReset = isset($xoops2faAdminReset) && true === $xoops2faAdminReset;
$post = 'POST' === \Xmf\Request::getMethod();
$uid = $adminReset ? \Xmf\Request::getInt('uid', 0, $post ? 'POST' : 'GET') : (int) $actor->getVar('uid');
/** @var XoopsMemberHandler $memberHandler */
$memberHandler = xoops_getHandler('member');
$user = $adminReset ? $memberHandler->getUser($uid) : $actor;
if (!($user instanceof XoopsUser) || ($adminReset && (!$GLOBALS['xoopsModule'] || !$actor->isAdmin($GLOBALS['xoopsModule']->mid())))) {
    http_response_code(403);
    exit(_NOPERM);
}
/** @var XoopsUser2faHandler $handler */
$handler = xoops_getHandler('user2fa');
$crypto = new XoopsTwoFactorCrypto(new \Xmf\Key\FileStorage(XOOPS_VAR_PATH . '/data'), XOOPS_VAR_PATH . '/data/twofactor.lock');
$now = time();
$error = '';
$message = '';
$codes = [];
$setupSecret = '';
$qr = '';
/**
 * Local renderer only; the manual key remains available without the QR package.
 *
 * @param string $secret the pending base32 secret
 *
 * @return string data URI, or '' when no image can be produced
 */
$qrFor = static function (string $secret) use ($user): string {
    if (!class_exists(\chillerlan\QRCode\QRCode::class)) {
        return '';
    }
    try {
        $issuer = (string) $GLOBALS['xoopsConfig']['sitename'];
        // Key URI label: issuer and account are encoded separately around a literal colon.
        $uri = 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode((string) $user->getVar('uname', 'n')) . '?' . http_build_query(['secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => 6, 'period' => 30], '', '&', PHP_QUERY_RFC3986);

        return (new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions(['outputBase64' => true])))->render($uri);
    } catch (\Throwable) {
        return '';
    }
};
$row = null;
$installed = $handler->isInstalled();
try {
    if (!$installed) {
        throw new \RuntimeException('Factor storage is not installed');
    }
    $row = $handler->getRow($uid);
    $generation = is_array($row) ? (string) $row['generation'] : '';
    $enrolled = is_array($row) && XoopsUser2faHandler::ROW_DISABLED !== $row['state'];
    if ($post) {
        if (!$GLOBALS['xoopsSecurity']->check()) {
            $error = _US_2FAM_STARTAGAIN;
        } else {
            $action = xoops_2fa_posted_action(['begin', 'begin_email', 'confirm', 'send', 'disable', 'regenerate', 'reset']);
            $code = \Xmf\Request::getString('code', '', 'POST');
            $recovery = \Xmf\Request::getString('recovery', '', 'POST');
            if ('confirm' === $action && !$adminReset && !$enrolled) {
                $pending = $_SESSION['xoops2faSetup'] ?? null;
                if (!xoops_2fa_setup_valid($pending, $uid, (string) $user->getVar('pass', 'n'), $generation, $now)) {
                    unset($_SESSION['xoops2faSetup']);
                    $error = _US_2FAM_STARTAGAIN;
                } elseif (XoopsUser2faHandler::METHOD_EMAIL === ($pending['method'] ?? XoopsUser2faHandler::METHOD_TOTP)) {
                    $result = $handler->enrolEmail($uid, $code, $now, $generation);
                    if (false === $result) {
                        ++$_SESSION['xoops2faSetup']['attempts'];
                        if ($_SESSION['xoops2faSetup']['attempts'] >= 5) {
                            // The live code has been guessed at five times: it dies with the setup.
                            unset($_SESSION['xoops2faSetup']);
                            if (!$handler->revokeEmailCodes($uid)) {
                                throw new \RuntimeException('Code revocation failed');
                            }
                        }
                        $error = _US_2FA_BADCODE;
                    } else {
                        $current = $handler->getRow($uid);
                        if (!is_array($current) || $current['state'] !== XoopsUser2faHandler::ROW_ENROLLED || !hash_equals($result['generation'], $current['generation'])) {
                            unset($_SESSION['xoops2faSetup']);
                            throw new \RuntimeException('Factor changed before completion');
                        }
                        $row = $current;
                        xoops_login_set_session($user, $result['generation'], true);
                        if (!empty($GLOBALS['xoopsConfig']['usercookie'])) {
                            xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600, '/', XOOPS_COOKIE_DOMAIN, 0, true);
                            xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600);
                        }
                        $codes = $result['codes'];
                        $message = _US_2FAM_DONE;
                        unset($_SESSION['xoops2faSetup']);
                        xoops_2fa_notice($user, _US_2FAM_NOTICE_SUBJECT, _US_2FAM_NOTICE_BODY);
                    }
                } else {
                    $secret = $crypto->open($pending['blob'], XoopsTwoFactorCrypto::pendingAad($uid));
                    if (null === $secret) {
                        // The pending blob cannot be read any more (key lost or replaced): offer a fresh start.
                        unset($_SESSION['xoops2faSetup']);
                        throw new \RuntimeException('Setup secret unavailable');
                    }
                    $step = XoopsTotp::matchStep($secret, $code, $now, 0);
                    if (false === $step) {
                        ++$_SESSION['xoops2faSetup']['attempts'];
                        if ($_SESSION['xoops2faSetup']['attempts'] >= 5) {
                            unset($_SESSION['xoops2faSetup']);
                        }
                        $error = _US_2FA_BADCODE;
                    } else {
                        $result = $handler->enrol($uid, $secret, $step, $now, $generation);
                        if (false === $result) {
                            throw new \RuntimeException('Enrolment refused');
                        }
                        $current = $handler->getRow($uid);
                        if (!is_array($current) || $current['state'] !== XoopsUser2faHandler::ROW_ENROLLED || !hash_equals($result['generation'], $current['generation'])) {
                            unset($_SESSION['xoops2faSetup']);
                            throw new \RuntimeException('Factor changed before completion');
                        }
                        $row = $current;
                        xoops_login_set_session($user, $result['generation'], true);
                        if (!empty($GLOBALS['xoopsConfig']['usercookie'])) {
                            xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600, '/', XOOPS_COOKIE_DOMAIN, 0, true);
                            xoops_setcookie($GLOBALS['xoopsConfig']['usercookie'], null, time() - 3600);
                        }
                        $codes = $result['codes'];
                        $message = _US_2FAM_DONE;
                        xoops_2fa_notice($user, _US_2FAM_NOTICE_SUBJECT, _US_2FAM_NOTICE_BODY);
                    }
                }
            } elseif ('send' === $action && !$adminReset) {
                // A code for the pending e-mail setup, or for the enrolled e-mail factor: no password, cooldown applies.
                $pending = $_SESSION['xoops2faSetup'] ?? null;
                $pendingEmail = !$enrolled && xoops_2fa_setup_valid($pending, $uid, (string) $user->getVar('pass', 'n'), $generation, $now)
                    && XoopsUser2faHandler::METHOD_EMAIL === ($pending['method'] ?? '');
                if ($enrolled && XoopsUser2faHandler::METHOD_EMAIL === ($row['method'] ?? '') && (int) ($row['locked_until'] ?? 0) > $now) {
                    // The lock outlives a mailed code: nothing sent now could be accepted.
                    $error = _US_2FA_LOCKED;
                } elseif ($pendingEmail || ($enrolled && XoopsUser2faHandler::METHOD_EMAIL === ($row['method'] ?? ''))) {
                    $delivery = xoops_2fa_deliver_code($handler, $user);
                    if ($delivery['sent']) {
                        if ($pendingEmail) {
                            // The guesses were spent against the code this one replaces.
                            $_SESSION['xoops2faSetup']['attempts'] = 0;
                            $_SESSION['xoops2faSetup']['expires']  = $now + XoopsUser2faHandler::EMAIL_TTL;
                        }
                        $message = $delivery['message'];
                    } else {
                        $error = $delivery['message'];
                    }
                }
            } elseif (in_array($action, $adminReset ? ['reset'] : ['begin', 'begin_email', 'disable', 'regenerate'], true)) {
                // Reauthentication happens after CSRF, before key provisioning or any mutation.
                $authenticated = xoops_2fa_reauthenticate($actor, \Xmf\Request::getString('password', '', 'POST'));
                if (false === $authenticated) {
                    $error = _US_2FAM_BADPASSWORD;
                } elseif ($adminReset && !$enrolled) {
                    // Nothing to reset: keep absent/disabled rows and generations unchanged.
                    $message = _US_2FAM_DISABLED;
                } elseif ($adminReset) {
                    if (false === $handler->disable($uid)) {
                        throw new \RuntimeException('Reset refused');
                    }
                    $row = null;
                    try {
                        trigger_error(sprintf('Two-factor admin reset: actor %d, uid %d', (int) $actor->getVar('uid'), $uid), E_USER_NOTICE);
                    } catch (\Throwable) {
                        // The reset is committed; a throwing diagnostic handler must not report it as failed.
                    }
                    xoops_2fa_notice($user, _US_2FAM_RESET_SUBJECT, _US_2FAM_RESET_BODY);
                    $message = _US_2FAM_RESET_DONE;
                } elseif ('begin_email' === $action && !$enrolled) {
                    $user = $authenticated;
                    // The mailed code is stored under the site key, so this path needs one too.
                    if (!$crypto->provisionKey(fn (): bool => $handler->hasEncryptedSecrets())) {
                        throw new \RuntimeException('Key provisioning refused');
                    }
                    // Restarting keeps the guesses already spent against a code that is still live;
                    // only a freshly mailed code starts the count over.
                    $pending  = $_SESSION['xoops2faSetup'] ?? null;
                    $attempts = xoops_2fa_setup_valid($pending, $uid, (string) $user->getVar('pass', 'n'), $generation, $now)
                        && XoopsUser2faHandler::METHOD_EMAIL === ($pending['method'] ?? '') ? (int) $pending['attempts'] : 0;
                    $_SESSION['xoops2faSetup'] = ['uid' => $uid, 'generation' => $generation, 'passdigest' => hash('sha256', (string) $user->getVar('pass', 'n')), 'expires' => $now + XoopsUser2faHandler::EMAIL_TTL, 'blob' => '', 'attempts' => $attempts, 'method' => XoopsUser2faHandler::METHOD_EMAIL];
                    $delivery = xoops_2fa_deliver_code($handler, $user);
                    if ($delivery['sent']) {
                        $_SESSION['xoops2faSetup']['attempts'] = 0;
                        $message = $delivery['message'];
                    } else {
                        $error = $delivery['message'];
                    }
                } elseif ('begin' === $action && !$enrolled) {
                    $user = $authenticated;
                    if (!$crypto->provisionKey(fn (): bool => $handler->hasEncryptedSecrets())) {
                        throw new \RuntimeException('Key provisioning refused');
                    }
                    $pending = $_SESSION['xoops2faSetup'] ?? null;
                    // Only a pending authenticator setup is resumed: the e-mail one carries no secret to open.
                    if (xoops_2fa_setup_valid($pending, $uid, (string) $user->getVar('pass', 'n'), $generation, $now)
                        && XoopsUser2faHandler::METHOD_TOTP === ($pending['method'] ?? XoopsUser2faHandler::METHOD_TOTP)) {
                        $setupSecret = $crypto->open($pending['blob'], XoopsTwoFactorCrypto::pendingAad($uid));
                    } else {
                        $setupSecret = XoopsTotp::newSecret();
                        $blob = $crypto->seal($setupSecret, XoopsTwoFactorCrypto::pendingAad($uid));
                        if (null === $blob) {
                            throw new \RuntimeException('Setup encryption failed');
                        }
                        $_SESSION['xoops2faSetup'] = ['uid' => $uid, 'generation' => $generation, 'passdigest' => hash('sha256', (string) $user->getVar('pass', 'n')), 'expires' => $now + 300, 'blob' => $blob, 'attempts' => 0, 'method' => XoopsUser2faHandler::METHOD_TOTP];
                    }
                    if (!is_string($setupSecret)) {
                        throw new \RuntimeException('Setup secret unavailable');
                    }
                    $qr = $qrFor($setupSecret);
                } elseif (in_array($action, ['disable', 'regenerate'], true) && $enrolled) {
                    $result = $handler->manage($uid, $generation, $code, $recovery, $action, $now);
                    if (false === $result) {
                        $failure = $handler->recordFailure($uid, $now, $generation);
                        $error = is_array($failure) && $failure['locked'] ? _US_2FA_LOCKED : _US_2FA_BADCODE;
                        if (is_array($failure) && $failure['transitioned']) {
                            xoops_2fa_notice($user, _US_2FA_LOCKED_MAIL_SUBJECT, _US_2FA_LOCKED_MAIL_BODY);
                        }
                    } else {
                        $codes = is_array($result) ? $result : [];
                        if ('disable' === $action) {
                            $row = null;
                        }
                        $message = 'disable' === $action ? _US_2FAM_DISABLED : _US_2FAM_REPLACED;
                        xoops_2fa_notice($user, _US_2FAM_NOTICE_SUBJECT, _US_2FAM_NOTICE_BODY);
                    }
                }
            }
        }
    }
    try {
        $row = $handler->getRow($uid);
    } catch (\Throwable $e) {
        if ('' === $message) {
            throw $e;
        }
        // The action itself committed: report it, with the row as the action left it.
    }
} catch (\Throwable) {
    $error = _US_2FAM_UNAVAILABLE;
}

$enrolled = is_array($row) && XoopsUser2faHandler::ROW_DISABLED !== $row['state'];
$confirm = !$adminReset && !$enrolled && xoops_2fa_setup_valid($_SESSION['xoops2faSetup'] ?? null, $uid, (string) $user->getVar('pass', 'n'), is_array($row) ? (string) $row['generation'] : '', $now);
$confirmEmail = $confirm && XoopsUser2faHandler::METHOD_EMAIL === ($_SESSION['xoops2faSetup']['method'] ?? XoopsUser2faHandler::METHOD_TOTP);
if ($confirm && !$confirmEmail && '' === $setupSecret) {
    // A pending setup keeps showing its key and QR: on a wrong code, a refresh or a new tab. Nothing is written.
    try {
        $secret = $crypto->open($_SESSION['xoops2faSetup']['blob'], XoopsTwoFactorCrypto::pendingAad($uid));
    } catch (\Throwable) {
        $secret = null;
    }
    if (is_string($secret)) {
        $setupSecret = $secret;
        $qr = $qrFor($secret);
    } else {
        unset($_SESSION['xoops2faSetup']);
        $confirm = false;
    }
}
// Rendering only below this line: nothing further changes state.
$labels = [];
foreach (get_defined_constants() as $name => $value) {
    if (str_starts_with($name, '_US_2FAM_')) {
        $labels[strtolower(substr($name, strlen('_US_2FAM_')))] = $value;
    }
}
$maskedEmail = xoops_2fa_mask_email((string) $user->getVar('email', 'n'));
$labels['email_step'] = sprintf($labels['email_step'] ?? '%s', $maskedEmail);
$labels['email_help'] = sprintf($labels['email_help'] ?? '%s', $maskedEmail);
$byEmail = $confirmEmail || ($enrolled && XoopsUser2faHandler::METHOD_EMAIL === ($row['method'] ?? ''));
$vars = ['labels' => $labels, 'admin_reset' => $adminReset, 'uid' => $uid,
    'account' => $user->getVar('uname', 'n'), 'enrolled' => $enrolled, 'confirm_setup' => $confirm,
    'by_email' => $byEmail,
    'installed' => $installed, 'message' => $message, 'error' => $error, 'secret' => $setupSecret,
    'qr' => $qr, 'codes' => array_map(static fn (string $v): string => trim(chunk_split($v, 4, ' ')), $codes),
    'paused' => XoopsUser2faHandler::POLICY_OFF === XoopsUser2faHandler::policy($GLOBALS['xoopsConfig']),
    'http_warning' => XOOPS_PROT !== 'https://', 'lang_code' => $byEmail ? _US_2FA_CODE_EMAIL : _US_2FA_CODE, 'lang_recovery' => _US_2FA_RECOVERY,
    'action_url' => $adminReset ? XOOPS_URL . '/modules/system/admin.php?fct=users' : XOOPS_URL . '/user.php',
    'back_url' => $adminReset ? XOOPS_URL . '/modules/system/admin.php?fct=users' : XOOPS_URL . '/userinfo.php?uid=' . $uid,
    'token_html' => $GLOBALS['xoopsSecurity']->getTokenHTML()];
// Built after the header: the theme installs its form renderer when it starts.
$forms = static function () use (&$vars, $adminReset, $enrolled, $labels): void {
    $vars['form']      = xoops_2fa_manage_form($vars)->render();
    $vars['send_form'] = $vars['by_email'] && !$adminReset
        ? xoops_2fa_send_form($vars['action_url'], ['op' => $enrolled ? '2fa_manage' : '2fa_setup'], $labels['send'] ?? 'send')->render()
        : '';
};
// The row is registered by the System module update; until then the shipped file renders the page.
$template = 'db:system_user2fa_manage.tpl';
/** @var XoopsTplfileHandler $tplfiles */
$tplfiles = xoops_getHandler('tplfile');
if ([] === $tplfiles->find('default', null, null, null, 'system_user2fa_manage.tpl', true)) {
    $template = XOOPS_ROOT_PATH . '/modules/system/templates/system_user2fa_manage.tpl';
}
if ($adminReset) {
    xoops_cp_header();
    $forms();
    require_once XOOPS_ROOT_PATH . '/class/template.php';
    $tpl = new XoopsTpl();
    $tpl->caching = 0;
    $tpl->assign($vars);
    $tpl->display($template);
    xoops_cp_footer();
} else {
    // The page is part of the account area: the theme wraps it like every other user.php view.
    include $GLOBALS['xoops']->path('header.php');
    $forms();
    $GLOBALS['xoopsTpl']->assign($vars);
    $GLOBALS['xoopsTpl']->assign('xoops_pagetitle', $labels['title'] ?? '');
    $GLOBALS['xoopsTpl']->display($template);
    include $GLOBALS['xoops']->path('footer.php');
}
exit();
