<?php
/**
 * Second-factor challenge strings.
 *
 * A file of their own, apart from user.php: xoops_loadLanguage() falls back
 * to English for a missing file but never fills gaps in a present one, so
 * a language pack that predates the challenge renders it in English rather
 * than failing on an undefined constant.
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

// XOOPS 2.7.4: two-factor challenge
define('_US_2FA_TITLE', 'Second step');
define('_US_2FA_PROMPT', 'Enter the code from your authenticator app');
define('_US_2FA_CODE', 'Authenticator code');
define('_US_2FA_RECOVERY', 'Use a recovery code instead');
define('_US_2FA_RECOVERY_HINT', 'Each recovery code works once. Using one sends you an e-mail.');
define('_US_2FA_SUBMIT', 'Continue');
define('_US_2FA_STARTAGAIN', 'This sign-in has expired or was interrupted. Please start again.');
define('_US_2FA_BACKTOLOGIN', 'Back to the login form');
define('_US_2FA_BADCODE', 'That code was not accepted.');
define('_US_2FA_LOCKED', 'Too many attempts. The second step is locked for fifteen minutes; a recovery code still works.');
define('_US_2FA_UNAVAILABLE', 'The authenticator step is not available right now. A recovery code still works, or contact the site administrator.');
define('_US_2FA_REQUIRED', 'This account has two-factor authentication enabled. Please sign in through the site\'s login page.');
define('_US_2FA_HTTP_LOGIN', 'The site login uses HTTP and will send your password without encryption. Continue only if you accept this risk, or ask the administrator to enable HTTPS for the site.');
define('_US_2FA_LOCKED_MAIL_SUBJECT', '%s: second step locked');
define('_US_2FA_LOCKED_MAIL_BODY', 'Five wrong authenticator codes were entered for your account at %s from %s. The second step is locked for fifteen minutes. If this was not you, change your password.');
define('_US_2FA_RECOVERY_MAIL_SUBJECT', '%s: a recovery code was used');
define('_US_2FA_RECOVERY_MAIL_BODY', 'A recovery code was used to sign in to your account at %s from %s. That code no longer works. If this was not you, change your password and reset your recovery codes.');
