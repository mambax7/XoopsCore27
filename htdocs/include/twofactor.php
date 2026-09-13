<?php
/**
 * Shared two-factor management helpers.
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
declare(strict_types=1);

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Load a two-factor language file and fill its gaps from English.
 *
 * xoops_loadLanguage() falls back to English only when the whole file is
 * absent. A translation that predates a constant would otherwise leave it
 * undefined, and the page reading it would stop with an Error.
 */
function xoops_2fa_loadLanguage(string $name): void
{
    xoops_loadLanguage($name);
    if ('english' === ($GLOBALS['xoopsConfig']['language'] ?? 'english')) {
        return;
    }
    // The English file is a list of define() calls; the ones the translation already made warn and are skipped.
    set_error_handler(static fn (): bool => true);
    try {
        include XOOPS_ROOT_PATH . '/language/english/' . $name . '.php';
    } finally {
        restore_error_handler();
    }
}

/**
 * Mail a sign-in code to the account address.
 *
 * @param XoopsUser $user account
 * @param string    $code the six-digit code
 *
 * @return bool whether the mailer accepted it
 */
function xoops_2fa_send_code(XoopsUser $user, string $code): bool
{
    try {
        $mailer = xoops_getMailer();
        $mailer->useMail();
        $mailer->setToUsers($user);
        $mailer->setFromEmail($GLOBALS['xoopsConfig']['adminmail']);
        $mailer->setFromName($GLOBALS['xoopsConfig']['sitename']);
        $mailer->setSubject(sprintf(_US_2FA_EMAIL_SUBJECT, $GLOBALS['xoopsConfig']['sitename']));
        $mailer->setBody(sprintf(_US_2FA_EMAIL_BODY, $GLOBALS['xoopsConfig']['sitename'], $code, (int) (XoopsUser2faHandler::EMAIL_TTL / 60)));

        return (bool) $mailer->send();
    } catch (\Throwable) {
        return false;
    }
}

/**
 * Issue and mail a fresh code; the strings tell the visitor what happened.
 *
 * @param XoopsUser2faHandler $handler factor handler
 * @param XoopsUser           $user    account
 *
 * @return array{sent: bool, message: string} sent, and the status line to show
 */
function xoops_2fa_deliver_code(XoopsUser2faHandler $handler, XoopsUser $user): array
{
    $code = $handler->issueEmailCode((int) $user->getVar('uid'));
    if (null === $code) {
        return ['sent' => false, 'message' => _US_2FA_SEND_WAIT];
    }
    if (false === $code || !xoops_2fa_send_code($user, $code)) {
        return ['sent' => false, 'message' => _US_2FA_SEND_FAILED];
    }

    return ['sent' => true, 'message' => sprintf(_US_2FA_SENT, xoops_2fa_mask_email((string) $user->getVar('email', 'n')))];
}

/**
 * @param string $email address
 *
 * @return string the address with most of its local part hidden
 */
function xoops_2fa_mask_email(string $email): string
{
    $at = strrpos($email, '@');
    if (false === $at) {
        return '***';
    }
    $local = substr($email, 0, $at);

    return mb_substr($local, 0, 1) . '***' . substr($email, $at);
}

/**
 * The management form, built for the site's form renderer so every theme styles it.
 *
 * Captions are language constants and go to the renderer as they are, like
 * every core form; label values are markup, escaped here where they carry a
 * value from the request or the row.
 *
 * @param array $v the page variables: labels, admin_reset, enrolled, confirm_setup, by_email,
 *                 secret, qr, uid, action_url, lang_code, lang_recovery
 *
 * @return XoopsThemeForm
 */
function xoops_2fa_manage_form(array $v): XoopsThemeForm
{
    require_once XOOPS_ROOT_PATH . '/class/xoopsformloader.php';
    $l    = $v['labels'];
    $e    = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $form = new XoopsThemeForm('', 'xo2fa_manage', $v['action_url'], 'post', true);
    $form->setExtra('autocomplete="off"');
    if ($v['admin_reset']) {
        $form->addElement(new XoopsFormLabel('', $e($l['reset_help'] ?? '')));
        $actions = ['reset' => $l['reset'] ?? 'reset'];
    } elseif ($v['enrolled']) {
        $form->addElement(new XoopsFormLabel('', $e($v['by_email'] ? ($l['enabled_email'] ?? '') : ($l['enabled'] ?? ''))));
        $actions = ['regenerate' => $l['regenerate'] ?? 'regenerate', 'disable' => $l['disable'] ?? 'disable'];
    } elseif ($v['confirm_setup'] && $v['by_email']) {
        $form->addElement(new XoopsFormLabel('', $e($l['email_step'] ?? '')));
        $actions = ['confirm' => $l['confirm_email'] ?? 'confirm'];
    } elseif ($v['confirm_setup']) {
        $steps = '<ol><li>' . $e($l['step_app'] ?? '') . '</li><li>' . $e($l['step_add'] ?? '') . '</li><li>' . $e($l['step_code'] ?? '') . '</li></ol>';
        if ('' !== $v['qr']) {
            $steps .= '<p><img src="' . $e($v['qr']) . '" alt="' . $e($l['scan'] ?? '') . '" width="256" height="256"></p>';
        }
        $form->addElement(new XoopsFormLabel('', $steps));
        $form->addElement(new XoopsFormLabel($l['manual'] ?? '', '<code dir="ltr">' . $e($v['secret']) . '</code>'));
        $actions = ['confirm' => $l['confirm'] ?? 'confirm'];
    } else {
        $form->addElement(new XoopsFormLabel('', $e($l['choose'] ?? '') . '<br>' . $e($l['email_help'] ?? '')));
        $actions = ['begin' => $l['enable'] ?? 'begin', 'begin_email' => $l['enable_email'] ?? 'begin_email'];
    }
    if (!$v['confirm_setup'] || $v['admin_reset']) {
        $password = new XoopsFormPassword($l['password'] ?? '', 'password', 30, 255);
        $password->setExtra('autocomplete="current-password" required');
        $form->addElement($password, true);
    }
    if (!$v['admin_reset'] && ($v['enrolled'] || $v['confirm_setup'])) {
        $code = new XoopsFormText($v['lang_code'], 'code', 12, 6);
        $code->setExtra('inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" dir="ltr"');
        if ($v['confirm_setup']) {
            $code->setDescription($e($v['by_email'] ? ($l['code_help_email'] ?? '') : ($l['code_help'] ?? '')));
        }
        $form->addElement($code);
        if ($v['enrolled']) {
            $recovery = new XoopsFormText($v['lang_recovery'], 'recovery', 30, 40);
            $recovery->setExtra('autocomplete="off" dir="ltr"');
            $form->addElement($recovery);
        }
    }
    $form->addElement(new XoopsFormHidden('op', $v['admin_reset'] ? 'users_2fa_reset' : ($v['enrolled'] ? '2fa_manage' : '2fa_setup')));
    if ($v['admin_reset']) {
        $form->addElement(new XoopsFormHidden('uid', (string) (int) $v['uid']));
    }
    $buttons = new XoopsFormElementTray('', ' ');
    foreach ($actions as $action => $label) {
        // The renderers show the value as the button text, so the action travels in the name.
        $buttons->addElement(new XoopsFormButton('', 'action_' . $action, $label, 'submit'));
    }
    $form->addElement($buttons);

    return $form;
}

/**
 * The one-button form that mails a fresh code.
 *
 * @param string $actionUrl where it posts
 * @param array  $hidden    name => value fields naming the page and the request
 * @param string $label     button text
 *
 * @return XoopsThemeForm
 */
function xoops_2fa_send_form(string $actionUrl, array $hidden, string $label): XoopsThemeForm
{
    require_once XOOPS_ROOT_PATH . '/class/xoopsformloader.php';
    $form = new XoopsThemeForm('', 'xo2fa_send', $actionUrl, 'post', true);
    foreach ($hidden as $name => $value) {
        $form->addElement(new XoopsFormHidden($name, $value));
    }
    $form->addElement(new XoopsFormButton('', 'action_send', $label, 'submit'));

    return $form;
}

/**
 * The login challenge form.
 *
 * @param array $v the page variables: action_url, lang_code, lang_recovery, lang_recovery_hint, lang_submit
 *
 * @return XoopsThemeForm
 */
function xoops_2fa_challenge_form(array $v): XoopsThemeForm
{
    require_once XOOPS_ROOT_PATH . '/class/xoopsformloader.php';
    $e    = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $form = new XoopsThemeForm('', 'xo2fa_challenge', $v['action_url'], 'post', true);
    $form->setExtra('autocomplete="off"');
    $code = new XoopsFormText($v['lang_code'], 'code', 12, 6);
    $code->setExtra('inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" dir="ltr" autofocus');
    $form->addElement($code);
    $recovery = new XoopsFormText($v['lang_recovery'], 'recovery', 30, 40);
    $recovery->setExtra('autocomplete="off" dir="ltr"');
    $recovery->setDescription($e($v['lang_recovery_hint']));
    $form->addElement($recovery);
    $form->addElement(new XoopsFormHidden('op', '2fa'));
    $form->addElement(new XoopsFormHidden('xoops_2fa', '1'));
    $form->addElement(new XoopsFormButton('', 'submit', $v['lang_submit'], 'submit'));

    return $form;
}

/**
 * The action a management or challenge POST asks for.
 *
 * The renderers put the button text in the value, so each button is named
 * action_<name>; a plain "action" field is still honoured.
 *
 * @param string[] $known the actions the page accepts
 *
 * @return string the action, or '' when none was posted
 */
function xoops_2fa_posted_action(array $known): string
{
    $action = \Xmf\Request::getCmd('action', '', 'POST');
    if ('' !== $action) {
        return in_array($action, $known, true) ? $action : '';
    }
    foreach ($known as $candidate) {
        if (\Xmf\Request::hasVar('action_' . $candidate, 'POST')) {
            return $candidate;
        }
    }

    return '';
}

/** Validate the password-authorised setup session before using its encrypted secret. */
function xoops_2fa_setup_valid(mixed $pending, int $uid, string $passwordHash, string $generation, int $now): bool
{
    return is_array($pending)
        && ($pending['uid'] ?? null) === $uid
        && is_string($pending['passdigest'] ?? null)
        && hash_equals(hash('sha256', $passwordHash), $pending['passdigest'])
        && is_string($pending['generation'] ?? null) && hash_equals($generation, $pending['generation'])
        && is_int($pending['expires'] ?? null) && $pending['expires'] > $now
        && is_int($pending['attempts'] ?? null) && $pending['attempts'] >= 0 && $pending['attempts'] < 5
        && is_string($pending['blob'] ?? null);
}

/** Reauthenticate through the configured backend, requiring the same account. */
function xoops_2fa_reauthenticate(XoopsUser $user, string $password): XoopsUser|false
{
    if ('' === $password) {
        return false;
    }
    require_once XOOPS_ROOT_PATH . '/include/loginsession.php';
    $authenticated = xoops_login_authenticate((string) $user->getVar('uname', 'n'), $password);

    return $authenticated instanceof XoopsUser && (int) $authenticated->getVar('uid') === (int) $user->getVar('uid')
        ? $authenticated : false;
}

/** Best-effort security notification, outside any factor transaction. */
function xoops_2fa_notice(XoopsUser $user, string $subject, string $body): void
{
    $sent = false;
    try {
        $mailer = xoops_getMailer();
        $mailer->useMail();
        $mailer->setToUsers($user);
        $mailer->setFromEmail($GLOBALS['xoopsConfig']['adminmail']);
        $mailer->setFromName($GLOBALS['xoopsConfig']['sitename']);
        $mailer->setSubject(sprintf($subject, $GLOBALS['xoopsConfig']['sitename']));
        $mailer->setBody(sprintf(
            $body,
            $GLOBALS['xoopsConfig']['sitename'],
            \Xmf\IPAddress::fromRequest()->asReadable()
        ));
        $sent = $mailer->send();
    } catch (\Throwable) {
        // Report transport exceptions without exposing their credentials or message.
    }
    if (!$sent) {
        try {
            trigger_error('Two-factor management notice could not be sent', E_USER_WARNING);
        } catch (\Throwable) {
            // A custom diagnostic handler must not fail an already committed action.
        }
    }
}
