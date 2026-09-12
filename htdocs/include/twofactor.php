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
