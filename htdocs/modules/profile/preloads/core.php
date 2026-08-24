<?php
/**
 * Extended User Profile
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
 * @package             profile
 * @since               2.4.0
 * @author              trabis <lusopoemas@gmail.com>
 */

use Xmf\Request;

//if (!defined('XOOPS_ROOT_PATH')) {
//    throw new \RuntimeException('XOOPS root path not defined');
//}

/**
 * Profile core preloads
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              trabis <lusopoemas@gmail.com>
 */
class ProfileCorePreload extends XoopsPreloadItem
{
    /**
     * The current request's query string, '?' included, ready to append to a
     * redirect target — or '' when there is nothing usable. The raw
     * QUERY_STRING is attacker-controlled, and reflecting it unchecked into a
     * Location header invites cache-poisoning and phishing parameter injection
     * (CRLF itself is already blocked by PHP). Rather than gate on a character
     * allowlist — which reflected malformed percent-escapes verbatim and cost
     * a long urlencoded xoops_redirect its whole query — the string is parsed
     * with parse_str(), which mirrors how the redirect target itself will read
     * it, and re-emitted with http_build_query(), so every reflected byte is
     * RFC 3986-safe or a valid escape by construction. The length cap is
     * header-size sanity only.
     *
     * @return string
     */
    private static function filteredQueryString()
    {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if ('' === $queryString || strlen($queryString) > 2000) {
            return '';
        }
        parse_str($queryString, $params);
        $rebuilt = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return ('' === $rebuilt) ? '' : ('?' . $rebuilt);
    }

    /**
     * @param $args
     */
    public static function eventCoreUserStart($args)
    {
        $op = 'main';
        if (Request::hasVar('op', 'POST')) {
            $op = Request::getString('op', '', 'POST');
        } elseif (Request::hasVar('op', 'GET')) {
            $op = Request::getString('op', '', 'GET');
        }
        $from = Request::getString('from', '', 'GET');
        if ($op !== 'login' && $from !== 'profile') {
            header('location: ./modules/profile/user.php' . self::filteredQueryString());
            exit();
        }
    }

    /**
     * @param $args
     */
    public static function eventCoreEdituserStart($args)
    {
        header('location: ./modules/profile/edituser.php' . self::filteredQueryString());
        exit();
    }

    /**
     * @param $args
     */
    public static function eventCoreLostpassStart($args)
    {
        // Disabled: profile module's lostpass used a weak md5-based token.
        // All password resets now go through the secure core flow (htdocs/lostpass.php)
        // which uses random, one-time, expiring tokens via XoopsTokenHandler.
        return;
    }

    /**
     * @param $args
     */
    public static function eventCoreRegisterStart($args)
    {
        header('location: ./modules/profile/register.php' . self::filteredQueryString());
        exit();
    }

    /**
     * @param $args
     */
    public static function eventCoreUserinfoStart($args)
    {
        header('location: ./modules/profile/userinfo.php' . self::filteredQueryString());
        exit();
    }
}
