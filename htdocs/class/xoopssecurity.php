<?php

use Xmf\IPAddress;
use Xmf\Request;

/**
 * XOOPS security handler
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @author    Kazumi Ono <onokazu@xoops.org>
 * @author    Jan Pedersen <mithrandir@xoops.org>
 * @author    John Neill <catzwolf@xoops.org>
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   kernel
 * @since     2.0.0
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Class XoopsSecurity
 */
class XoopsSecurity
{
    public $errors = [];

    /**
     * Check if there is a valid token in $_REQUEST[$name . '_REQUEST'] - can be expanded for more wide use, later (Mith)
     *
     * @param bool         $clearIfValid whether to clear the token after validation
     * @param string|false $token        token to validate
     * @param string       $name         name of session variable
     *
     * @return bool
     */
    public function check($clearIfValid = true, $token = false, $name = 'XOOPS_TOKEN')
    {
        return $this->validateToken($token, $clearIfValid, $name);
    }

    /**
     * Create a token in the user's session
     *
     * @param int|string    $timeout time in seconds the token should be valid
     * @param string $name    name of session variable
     *
     * @return string token value
     */
    public function createToken($timeout = 0, $name = 'XOOPS_TOKEN')
    {
        $this->garbageCollection($name);
        if ($timeout == 0) {
            $expire  = @ini_get('session.gc_maxlifetime');
            $timeout = ($expire > 0) ? $expire : 900;
        }
        // The token is CSPRNG output handed to the browser as is and compared
        // with hash_equals() in validateToken(). It is no longer derived from a
        // session secret plus the User-Agent header: that binding added nothing
        // against CSRF (a forged request comes from the victim's browser) and
        // rejected every open form when the UA changed between render and
        // submit (browser update, privacy extension, "desktop site" toggle).
        $token = bin2hex(random_bytes(16));
        // save token data on the server
        if (!isset($_SESSION[$name . '_SESSION'])) {
            $_SESSION[$name . '_SESSION'] = [];
        }
        // 'id' is read unguarded by a pre-2.7.4 validateToken() (rollback, or a
        // mixed-version node sharing the session store), which accepts
        // md5(id . UA . prefix). It must be present, so that code does not
        // warn, and random, so the digest cannot be computed from public
        // inputs; the client never sees it, so that validator fails safely.
        $token_data = [
            'id'     => bin2hex(random_bytes(32)),
            'token'  => $token,
            'expire' => time() + (int) $timeout,
        ];
        $_SESSION[$name . '_SESSION'][] = $token_data;

        return $token;
    }

    /**
     * Compare a submitted token with one session entry.
     *
     * Entries written before 2.7.4 hold only a secret 'id'; the public token was
     * md5(id . User-Agent . XOOPS_DB_PREFIX). They stay valid until they expire
     * so that a form open while the site is upgraded still submits. Remove the
     * legacy branch in 2.8.
     *
     * @param array  $entry session entry
     * @param string $token submitted token
     *
     * @return bool
     */
    private function tokenMatches(array $entry, string $token): bool
    {
        if (isset($entry['token'])) {
            return hash_equals((string) $entry['token'], $token);
        }
        if (isset($entry['id']) && '' !== $entry['id']) {
            // legacy 2.7.3 token shape; remove in 2.8
            $agent = Request::getString('HTTP_USER_AGENT', '', 'SERVER', Request::MASK_ALLOW_RAW | Request::MASK_NO_TRIM);

            return hash_equals(md5($entry['id'] . $agent . XOOPS_DB_PREFIX), $token);
        }

        return false;
    }

    /**
     * Check if a token is valid. If no token is specified, $_REQUEST[$name . '_REQUEST'] is checked
     *
     * @param string|false $token        token to validate
     * @param bool         $clearIfValid whether to clear the token value if valid
     * @param string       $name         session name to validate
     *
     * @return bool
     */
    public function validateToken($token = false, $clearIfValid = true, $name = 'XOOPS_TOKEN')
    {
        // Optional: Ensure a session is active, keep this as a safeguard, but it’s likely unnecessary
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        global $xoopsLogger;
        $token = ($token !== false) ? $token : Request::getString($name . '_REQUEST', '', 'POST');
        if ($token === '') {
            $token = Request::getString($name . '_REQUEST', '', 'GET');
        }
        if (empty($token) || empty($_SESSION[$name . '_SESSION'])) {
            $xoopsLogger->addExtra('Token Validation', 'No valid token found in request/session');

            return false;
        }
        $validFound = false;
        $token_data = &$_SESSION[$name . '_SESSION'];
        foreach (array_keys($token_data) as $i) {
            if ($this->tokenMatches((array) $token_data[$i], (string) $token)) {
                if ($this->filterToken($token_data[$i])) {
                    if ($clearIfValid) {
                        // token should be valid once, so clear it once validated
                        unset($token_data[$i]);
                    }
                    $xoopsLogger->addExtra('Token Validation', 'Valid token found');
                    $validFound = true;
                } else {
                    $str = 'Valid token expired';
                    $this->setErrors($str);
                    $xoopsLogger->addExtra('Token Validation', $str);
                }
            }
        }
        if (!$validFound && !isset($str)) {
            $str = 'No valid token found';
            $this->setErrors($str);
            $xoopsLogger->addExtra('Token Validation', $str);
        }
        $this->garbageCollection($name);

        return $validFound;
    }

    /**
     * Clear all token values from user's session
     *
     * @param string $name session name
     *
     * @return void
     */
    public function clearTokens($name = 'XOOPS_TOKEN')
    {
        $_SESSION[$name . '_SESSION'] = [];
    }

    /**
     * Check whether a token value is expired or not
     *
     * @param string $token token
     *
     * @return bool
     */
    public function filterToken($token)
    {
        return (!empty($token['expire']) && $token['expire'] >= time());
    }

    /**
     * Perform garbage collection, clearing expired tokens
     *
     * @param string $name session name
     *
     * @return void
     */
    public function garbageCollection($name = 'XOOPS_TOKEN')
    {
        $sessionName = $name . '_SESSION';
        if (!empty($_SESSION[$sessionName]) && \is_array($_SESSION[$sessionName])) {
            $_SESSION[$sessionName] = array_filter($_SESSION[$sessionName], [$this, 'filterToken']);
        }
    }

    /**
     * Check the user agent's HTTP REFERER against XOOPS_URL
     *
     * @param int $docheck 0 to not check the referer (used with XML-RPC), 1 to actively check it
     *
     * @return bool
     */
    public function checkReferer($docheck = 1)
    {
        $ref = xoops_getenv('HTTP_REFERER');
        if ($docheck == 0) {
            return true;
        }
        if ($ref == '') {
            return false;
        }
        // Compare scheme, host, and effective port exactly. A prefix match
        // (strpos === 0) accepts sibling hosts such as example.com.attacker.test, and a
        // host-only match would accept http vs https or an alternate port (SECURITY.md L-5).
        $refParts  = parse_url($ref);
        $baseParts = parse_url(XOOPS_URL);
        if ($refParts === false || $baseParts === false || empty($refParts['host']) || empty($baseParts['host'])) {
            return false;
        }
        $refScheme  = strtolower($refParts['scheme'] ?? '');
        $baseScheme = strtolower($baseParts['scheme'] ?? 'http');
        $refPort    = (int) ($refParts['port'] ?? ('https' === $refScheme ? 443 : 80));
        $basePort   = (int) ($baseParts['port'] ?? ('https' === $baseScheme ? 443 : 80));

        return strcasecmp((string) $refParts['host'], (string) $baseParts['host']) === 0
            && $refScheme === $baseScheme
            && $refPort === $basePort;
    }

    /**
     * Check superglobals for contamination
     *
     * @return void
     **/
    public function checkSuperglobals()
    {
        foreach (
            [
                'GLOBALS',
                '_SESSION',
                'HTTP_SESSION_VARS',
                '_GET',
                'HTTP_GET_VARS',
                '_POST',
                'HTTP_POST_VARS',
                '_COOKIE',
                'HTTP_COOKIE_VARS',
                '_REQUEST',
                '_SERVER',
                'HTTP_SERVER_VARS',
                '_ENV',
                'HTTP_ENV_VARS',
                '_FILES',
                'HTTP_POST_FILES',
                'xoopsDB',
                'xoopsUser',
                'xoopsUserId',
                'xoopsUserGroups',
                'xoopsUserIsAdmin',
                'xoopsConfig',
                'xoopsOption',
                'xoopsModule',
                'xoopsModuleConfig',
                'xoopsRequestUri',
            ] as $bad_global) {
            if (isset($_REQUEST[$bad_global])) {
                header('Location: ' . XOOPS_URL . '/');
                exit();
            }
        }
    }

    /**
     * Check if visitor's IP address is banned
     * Should be changed to return bool and let the action be up to the calling script
     *
     * @return void
     */
    public function checkBadips()
    {
        global $xoopsConfig;

        $addr = IPAddress::fromRequest();
        $ip = $addr->asReadable();
        if ($xoopsConfig['enable_badips'] == 1 && $ip != '0.0.0.0' && is_array($xoopsConfig['bad_ips'] ?? null)) {
            // Admin-entered patterns may be malformed (e.g. a CIDR's "/" collides
            // with the delimiter). Swallow the resulting PCRE warning for the scan
            // and treat any non-match — including a compile failure — as "allowed",
            // so a bad pattern neither warns nor blocks the request (SECURITY.md L-19).
            set_error_handler(static fn (): bool => true);
            try {
                foreach ($xoopsConfig['bad_ips'] as $bi) {
                    // Bound the length to avoid pathological backtracking and block
                    // only on a definite match.
                    if (!is_string($bi) || '' === $bi || strlen($bi) > 255) {
                        continue;
                    }
                    if (1 === preg_match('/' . $bi . '/', $ip)) {
                        exit();
                    }
                }
            } finally {
                restore_error_handler();
            }
        }
    }

    /**
     * Get the HTML code for a XoopsFormHiddenToken object - used in forms that do not use XoopsForm elements
     *
     * @param string $name session token name
     *
     * @return string
     */
    public function getTokenHTML($name = 'XOOPS_TOKEN')
    {
        require_once XOOPS_ROOT_PATH . '/class/xoopsformloader.php';
        $token = new XoopsFormHiddenToken($name);

        return $token->render();
    }

    /**
     * Add an error
     *
     * @param string $error message
     *
     * @return void
     */
    public function setErrors($error)
    {
        $this->errors[] = trim($error);
    }

    /**
     * Get generated errors
     *
     * @param bool $ashtml Format using HTML?
     *
     * @return array|string Array of array messages OR HTML string
     */
    public function &getErrors($ashtml = false)
    {
        if (!$ashtml) {
            return $this->errors;
        } else {
            $ret = '';
            if (count($this->errors) > 0) {
                foreach ($this->errors as $error) {
                    $ret .= $error . '<br>';
                }
            }

            return $ret;
        }
    }
}
