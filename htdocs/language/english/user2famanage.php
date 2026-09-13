<?php
/**
 * Two-factor management strings.
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

define('_US_2FAM_TITLE', 'Two-factor authentication');
define('_US_2FAM_PASSWORD', 'Your current password');
define('_US_2FAM_ENABLE', 'Set up an authenticator');
define('_US_2FAM_CONFIRM', 'Confirm authenticator');
define('_US_2FAM_MANUAL', 'Manual setup key');
define('_US_2FAM_SCAN', 'Scan this QR code in your authenticator app, or enter the manual key. Then enter its six-digit code below.');
define('_US_2FAM_HTTP', 'This connection uses plain HTTP. Your password, session, authenticator setup key and recovery codes can be intercepted. Use HTTPS whenever possible.');
define('_US_2FAM_CODES', 'Save these recovery codes now');
define('_US_2FAM_CODES_HELP', 'Each code works once. These codes will not be displayed again. Keep them somewhere safe, separate from this account.');
define('_US_2FAM_DISABLE', 'Disable two-factor authentication');
define('_US_2FAM_REGENERATE', 'Replace recovery codes');
define('_US_2FAM_ENABLED', 'An authenticator is enrolled. Enter your current password and an authenticator or recovery code to make a change.');
define('_US_2FAM_DISABLED', 'Two-factor authentication is disabled.');
define('_US_2FAM_PAUSED', 'The site has paused two-factor challenges. Your factor is kept, and remember-me remains unavailable for enrolled accounts.');
define('_US_2FAM_UNAVAILABLE', 'Two-factor setup or management is unavailable. Please contact the site administrator.');
define('_US_2FAM_STARTAGAIN', 'Setup expired or the account changed. Enter your password to start setup again.');
define('_US_2FAM_BADPASSWORD', 'Your current password was not accepted.');
define('_US_2FAM_RESET', 'Reset this user’s two-factor authentication');
define('_US_2FAM_RESET_HELP', 'This disables the user’s authenticator and revokes their recovery codes and remember-me cookies. Existing signed-in sessions remain active. Enter your own administrator password to confirm.');
define('_US_2FAM_RESET_DONE', 'The user’s two-factor authentication was reset.');
define('_US_2FAM_BACK', 'Back to account');
define('_US_2FAM_DONE', 'Two-factor authentication is enabled.');
define('_US_2FAM_REPLACED', 'Previous recovery codes have been revoked.');
define('_US_2FAM_NOTICE_SUBJECT', '%s: two-factor authentication changed');
define('_US_2FAM_NOTICE_BODY', 'Two-factor authentication or recovery codes were changed for your account at %s from %s. If this was not you, contact the site administrator.');
define('_US_2FAM_RESET_SUBJECT', '%s: an administrator reset your two-factor authentication');
define('_US_2FAM_RESET_BODY', 'An administrator disabled your authenticator and revoked its recovery codes at %s from %s. Existing signed-in sessions remain active. Sign in and set up your authenticator again. Contact the site administrator if this was unexpected.');
