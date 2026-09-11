<?php
/**
 * Password-confirmed enrolment and management; also included by the users admin.
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');
require_once XOOPS_ROOT_PATH . '/include/twofactor.php';
require_once XOOPS_ROOT_PATH . '/include/loginsession.php';
xoops_loadLanguage('user');
xoops_loadLanguage('user2fa');
xoops_loadLanguage('user2famanage');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$actor = $GLOBALS['xoopsUser'];
if (!($actor instanceof XoopsUser)) {
    redirect_header(XOOPS_URL . '/user.php', 1, _US_2FA_STARTAGAIN, false);
    exit();
}
// This flag is set by the authorised users-admin include, never by request data.
$adminReset = isset($xoops2faAdminReset) && true === $xoops2faAdminReset;
$post = 'POST' === \Xmf\Request::getMethod();
$uid = $adminReset ? \Xmf\Request::getInt('uid', 0, $post ? 'POST' : 'GET') : (int) $actor->getVar('uid');
$user = $adminReset ? xoops_getHandler('member')->getUser($uid) : $actor;
if (!($user instanceof XoopsUser) || ($adminReset && (!$GLOBALS['xoopsModule'] || !$actor->isAdmin($GLOBALS['xoopsModule']->mid())))) {
    http_response_code(403);
    exit(_NOPERM);
}
$handler = xoops_getHandler('user2fa');
$crypto = new XoopsTwoFactorCrypto(new \Xmf\Key\FileStorage(XOOPS_VAR_PATH . '/data'), XOOPS_VAR_PATH . '/data/twofactor.lock');
$now = time();
$error = '';
$message = '';
$codes = [];
$setupSecret = '';
$qr = '';
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
            $action = \Xmf\Request::getCmd('action', '', 'POST');
            $code = \Xmf\Request::getString('code', '', 'POST');
            $recovery = \Xmf\Request::getString('recovery', '', 'POST');
            if ('confirm' === $action && !$adminReset && !$enrolled) {
                $pending = $_SESSION['xoops2faSetup'] ?? null;
                if (!xoops_2fa_setup_valid($pending, $uid, (string) $user->getVar('pass', 'n'), $generation, $now)) {
                    unset($_SESSION['xoops2faSetup']);
                    $error = _US_2FAM_STARTAGAIN;
                } else {
                    $secret = $crypto->open($pending['blob'], XoopsTwoFactorCrypto::pendingAad($uid));
                    if (null === $secret) {
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
            } elseif (in_array($action, $adminReset ? ['reset'] : ['begin', 'disable', 'regenerate'], true)) {
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
                    trigger_error(sprintf('Two-factor admin reset: actor %d, uid %d', (int) $actor->getVar('uid'), $uid), E_USER_NOTICE);
                    xoops_2fa_notice($user, _US_2FAM_RESET_SUBJECT, _US_2FAM_RESET_BODY);
                    $message = _US_2FAM_RESET_DONE;
                } elseif ('begin' === $action && !$enrolled) {
                    $user = $authenticated;
                    if (!$crypto->provisionKey(fn (): bool => $handler->hasEncryptedSecrets())) {
                        throw new \RuntimeException('Key provisioning refused');
                    }
                    $pending = $_SESSION['xoops2faSetup'] ?? null;
                    if (xoops_2fa_setup_valid($pending, $uid, (string) $user->getVar('pass', 'n'), $generation, $now)) {
                        $setupSecret = $crypto->open($pending['blob'], XoopsTwoFactorCrypto::pendingAad($uid));
                    } else {
                        $setupSecret = XoopsTotp::newSecret();
                        $blob = $crypto->seal($setupSecret, XoopsTwoFactorCrypto::pendingAad($uid));
                        if (null === $blob) {
                            throw new \RuntimeException('Setup encryption failed');
                        }
                        $_SESSION['xoops2faSetup'] = ['uid' => $uid, 'generation' => $generation, 'passdigest' => hash('sha256', (string) $user->getVar('pass', 'n')), 'expires' => $now + 300, 'blob' => $blob, 'attempts' => 0];
                    }
                    if (!is_string($setupSecret)) {
                        throw new \RuntimeException('Setup secret unavailable');
                    }
                    // Local renderer only; manual key remains available without the QR package.
                    if (class_exists(\chillerlan\QRCode\QRCode::class)) {
                        try {
                            $issuer = (string) $GLOBALS['xoopsConfig']['sitename'];
                            $uri = 'otpauth://totp/' . rawurlencode($issuer . ':' . $user->getVar('uname', 'n')) . '?' . http_build_query(['secret' => $setupSecret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => 6, 'period' => 30], '', '&', PHP_QUERY_RFC3986);
                            $qr = (new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions(['outputBase64' => true])))->render($uri);
                        } catch (\Throwable) {
                            $qr = '';
                        }
                    }
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
                        $message = 'disable' === $action ? _US_2FAM_DISABLED : _US_2FAM_REPLACED;
                        xoops_2fa_notice($user, _US_2FAM_NOTICE_SUBJECT, _US_2FAM_NOTICE_BODY);
                    }
                }
            }
        }
    }
    $row = $handler->getRow($uid);
} catch (\Throwable) {
    $error = _US_2FAM_UNAVAILABLE;
}

$enrolled = is_array($row) && XoopsUser2faHandler::ROW_DISABLED !== $row['state'];
$confirm = !$adminReset && !$enrolled && xoops_2fa_setup_valid($_SESSION['xoops2faSetup'] ?? null, $uid, (string) $user->getVar('pass', 'n'), is_array($row) ? (string) $row['generation'] : '', $now);
require_once XOOPS_ROOT_PATH . '/class/template.php';
$tpl = new XoopsTpl();
$tpl->caching = 0;
$labels = [];
foreach (get_defined_constants() as $name => $value) {
    if (str_starts_with($name, '_US_2FAM_')) {
        $labels[strtolower(substr($name, strlen('_US_2FAM_')))] = $value;
    }
}
$tpl->assign(['labels' => $labels, 'admin_reset' => $adminReset, 'uid' => $uid,
    'account' => $user->getVar('uname', 'n'), 'enrolled' => $enrolled, 'confirm_setup' => $confirm,
    'installed' => $installed, 'message' => $message, 'error' => $error, 'secret' => $setupSecret,
    'qr' => $qr, 'codes' => array_map(static fn (string $v): string => trim(chunk_split($v, 4, ' ')), $codes),
    'paused' => XoopsUser2faHandler::POLICY_OFF === XoopsUser2faHandler::policy($GLOBALS['xoopsConfig']),
    'http_warning' => XOOPS_PROT !== 'https://', 'lang_code' => _US_2FA_CODE, 'lang_recovery' => _US_2FA_RECOVERY,
    'action_url' => $adminReset ? XOOPS_URL . '/modules/system/admin.php?fct=users' : XOOPS_URL . '/user.php',
    'back_url' => $adminReset ? XOOPS_URL . '/modules/system/admin.php?fct=users' : XOOPS_URL . '/userinfo.php?uid=' . $uid,
    'token_html' => $GLOBALS['xoopsSecurity']->getTokenHTML(), 'langcode' => _LANGCODE, 'charset' => _CHARSET,
    'direction' => \Xmf\I18n\Direction::dir(_LANGCODE)]);
$tpl->display('db:system_user2fa_manage.tpl');
exit();
