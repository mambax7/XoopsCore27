<?php
/**
 * XOOPS session handler
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
 * @package             kernel
 * @since               2.0.0
 * @author              Kazumi Ono (AKA onokazu) http://www.myweb.ne.jp/, http://jp.xoops.org/
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * XOOPS session handler (PHP 8.0+ with full types and lazy timestamp updates)
 * @package             kernel
 *
 * @author              Kazumi Ono    <onokazu@xoops.org>
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 * @author              XOOPS Development Team
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 */
class XoopsSessionHandler implements
    \SessionHandlerInterface,
    \SessionIdInterface,
    \SessionUpdateTimestampHandlerInterface
{
    /** @var XoopsDatabase */
    public $db;

    /** @var int */
    public $securityLevel = 3;

    protected array $bitMasks = [
        2 => ['v4' => 16, 'v6' => 64],
        3 => ['v4' => 24, 'v6' => 56],
        4 => ['v4' => 32, 'v6' => 128],
    ];

    public bool $enableRegenerateId = true;

    public function __construct(XoopsDatabase $db)
    {
        global $xoopsConfig;
        $this->db = $db;

        $lifetime = ($xoopsConfig['use_mysession'] && $xoopsConfig['session_name'] != '')
            ? $xoopsConfig['session_expire'] * 60
            : ini_get('session.cookie_lifetime');

        $host = parse_url(XOOPS_URL, PHP_URL_HOST);
        if (!is_string($host)) {
            $host = '';
        }
        $cookieDomain = XOOPS_COOKIE_DOMAIN;
        if (!xoops_cookieDomainMatches($host, $cookieDomain)) {
            $cookieDomain = '';
        }

        // Resolve SameSite and Secure from XOOPS config preferences
        $sameSite = isset($xoopsConfig['session_cookie_samesite']) ? (string) $xoopsConfig['session_cookie_samesite'] : 'Lax';
        $sameSite = in_array($sameSite, ['Lax', 'Strict', 'None'], true) ? $sameSite : 'Lax';

        // Auto-detect Secure from protocol; preference can only force it ON, never OFF
        $secure = (XOOPS_PROT === 'https://');
        if (!empty($xoopsConfig['session_cookie_secure'])) {
            $secure = true;
        }

        // Browsers require Secure when SameSite=None
        if ($sameSite === 'None') {
            $secure = true;
        }

        $options = [
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => $cookieDomain,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ];
        session_set_cookie_params($options);

        $this->enforceStrictMode();
    }

    /**
     * Pin session.use_strict_mode to the PHP 8.6 default.
     *
     * PHP 8.6 flips the session.use_strict_mode ini default from 0 to 1
     * (Secure Session Configuration Defaults RFC). Setting it explicitly
     * makes PHP 8.2-8.5 behave identically to 8.6: an uninitialized
     * session ID supplied by the client is rejected via validateId() and
     * a fresh ID is generated.
     *
     * PHP refuses ALL session.* ini changes not only while a session is
     * active but also once headers have been sent (any prior echo/output).
     * ini_set() then returns false - so the result is checked, and EVERY
     * failure path is reported via E_USER_WARNING (the codebase's standard
     * severity) instead of silently claiming parity.
     * The practical exposure is bounded: a request that produced output
     * before this constructor also cannot set the session cookie, so its
     * session handling is already degraded; the notice makes that visible.
     *
     * @return bool true when strict mode is verifiably in effect
     */
    public function enforceStrictMode(): bool
    {
        if ('1' === ini_get('session.use_strict_mode')) {
            return true; // already pinned (php.ini, earlier call, or PHP 8.6 default)
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            trigger_error(
                'XoopsSessionHandler: session.use_strict_mode cannot be changed'
                . ' mid-session; PHP defaults apply',
                E_USER_WARNING
            );

            return false;
        }
        if (false === ini_set('session.use_strict_mode', '1')) {
            trigger_error(
                'XoopsSessionHandler: session.use_strict_mode could not be enabled'
                . ' (output sent before session bootstrap?); PHP defaults apply',
                E_USER_WARNING
            );

            return false;
        }

        return true;
    }

    // --- SessionIdInterface ---

    /**
     * Generate a new session ID.
     *
     * Required (with validateId()) by PHP 8.6: session_set_save_handler()
     * deprecates object handlers that lack create_sid(). The ID is
     * generated directly from random_bytes() for a fixed, predictable
     * format that does not depend on session.sid_* settings or session
     * machinery. (session_create_id() would also work - php-src skips
     * handler re-entry inside save-handler callbacks - but the direct
     * generator is simpler and string-or-throw.) 32 lowercase hex
     * characters carry 128 bits of entropy and match the SSL-bridge
     * pattern in include/common.php (^[a-zA-Z0-9,-]{26,128}$).
     *
     * @return string new session ID
     * @throws \Random\RandomException if no source of randomness is available
     */
    public function create_sid(): string
    {
        return bin2hex(random_bytes(16));
    }

    // --- SessionHandlerInterface (typed) ---

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        $this->gc_force();
        return true;
    }

    public function read(string $sessionId): string|false
    {
        $ip = \Xmf\IPAddress::fromRequest();
        $sql = sprintf(
            'SELECT sess_data, sess_ip FROM %s WHERE sess_id = %s',
            $this->db->prefix('session'),
            $this->db->quote($sessionId)
        );

        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            return false; // storage failure
        }

        $row = $this->db->fetchRow($result);
        if ($row === false) {
            return ''; // not found → empty string
        }

        [$sess_data, $sess_ip] = $row;

        if ($this->securityLevel > 1) {
            if (false === $ip->sameSubnet(
                    $sess_ip,
                    $this->bitMasks[$this->securityLevel]['v4'],
                    $this->bitMasks[$this->securityLevel]['v6']
                )) {
                return ''; // IP mismatch → treat as no data
            }
        }

        return $sess_data;
    }

    public function write(string $sessionId, string $data): bool
    {
        $remoteAddress = \Xmf\IPAddress::fromRequest()->asReadable();
        $sid = $this->db->quote($sessionId);
        $now = time();

        $sql = sprintf(
            'INSERT INTO %s (sess_id, sess_updated, sess_ip, sess_data)
             VALUES (%s, %u, %s, %s)
             ON DUPLICATE KEY UPDATE
             sess_updated = %u, sess_data = %s, sess_ip = %s',
            $this->db->prefix('session'),
            $sid,
            $now,
            $this->db->quote($remoteAddress),
            $this->db->quote($data),
            $now,
            $this->db->quote($data),
            $this->db->quote($remoteAddress)
        );

        $ok = $this->db->exec($sql);
        // update_cookie() is a no-op; retained for API compatibility
        $this->update_cookie();
        return (bool)$ok;
    }

    public function destroy(string $sessionId): bool
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE sess_id = %s',
            $this->db->prefix('session'),
            $this->db->quote($sessionId)
        );
        return (bool)$this->db->exec($sql);
    }

    public function gc(int $max_lifetime): int|false
    {
        if ($max_lifetime <= 0) {
            return 0;
        }
        $mintime = time() - $max_lifetime;
        $sql = sprintf(
            'DELETE FROM %s WHERE sess_updated < %u',
            $this->db->prefix('session'),
            $mintime
        );
        if ($this->db->exec($sql)) {
            return (int)$this->db->getAffectedRows();
        }
        return false;
    }

    // --- SessionUpdateTimestampHandlerInterface (8.0+) ---

    /**
     * Validate that the stored session IP matches the current client IP
     * according to the configured security level and bit masks.
     */
    private function validateSessionIp(?string $storedIp): bool
    {
        // Low security levels or missing configuration do not enforce IP checks
        if ($this->securityLevel <= 1) {
            return true;
        }

        if (empty($storedIp) || !isset($_SERVER['REMOTE_ADDR'])) {
            // If we cannot reliably determine either side, keep previous behavior
            return true;
        }

        $clientIp = (string)$_SERVER['REMOTE_ADDR'];

        $levelMasks = $this->bitMasks[$this->securityLevel] ?? null;
        if ($levelMasks === null) {
            return true;
        }

        // Determine IP version and ensure both addresses are of the same family
        $isClientV4 = filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isClientV6 = filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $isStoredV4 = filter_var($storedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isStoredV6 = filter_var($storedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if ($isClientV4 && $isStoredV4) {
            $version = 'v4';
        } elseif ($isClientV6 && $isStoredV6) {
            $version = 'v6';
        } else {
            // Mixed or invalid IP families - do not enforce IP binding
            return true;
        }

        $bits = $levelMasks[$version] ?? null;
        if ($bits === null) {
            return true;
        }

        $clientBin = @inet_pton($clientIp);
        $storedBin = @inet_pton($storedIp);
        if ($clientBin === false || $storedBin === false || $clientBin === null || $storedBin === null) {
            // If binary conversion fails, fall back to previous permissive behavior
            return true;
        }

        return $this->applyIpMask($clientBin, $bits) === $this->applyIpMask($storedBin, $bits);
    }

    /**
     * Apply an N-bit network mask to a binary IP address.
     */
    private function applyIpMask(string $ipBin, int $bits): string
    {
        $bytes      = strlen($ipBin);
        $masked     = '';
        $fullBytes  = intdiv($bits, 8);
        $remaining  = $bits % 8;

        for ($i = 0; $i < $bytes; $i++) {
            $byte = ord($ipBin[$i]);
            if ($i < $fullBytes) {
                // Completely within the network portion
                $masked .= chr($byte);
            } elseif ($i === $fullBytes && $remaining > 0) {
                // Partially masked byte
                $mask   = (0xFF << (8 - $remaining)) & 0xFF;
                $masked .= chr($byte & $mask);
            } else {
                // Host portion is zeroed
                $masked .= chr(0);
            }
        }

        return $masked;
    }

    public function validateId(string $id): bool
    {
        $sql = sprintf(
            'SELECT sess_ip FROM %s WHERE sess_id = %s',
            $this->db->prefix('session'),
            $this->db->quote($id)
        );
        $res = $this->db->query($sql, 1, 0);
        if (!$this->db->isResultSet($res)) {
            return false;
        }
        $row = $this->db->fetchRow($res);
        if ($row === false) {
            return false;
        }

        $storedIp = $row[0] ?? null;
        if (!$this->validateSessionIp(is_string($storedIp) ? $storedIp : null)) {
            return false;
        }

        return true;
    }

    /**
     * Refresh a session's timestamp without rewriting its data.
     *
     * PHP 8.6 routes an unchanged session here instead of write() under
     * session.lazy_write - including a brand-new session that stayed
     * empty, because session_encode() now returns '' instead of false
     * for an empty session. A new empty session has no row yet, so a
     * plain UPDATE would persist nothing and the next request's
     * strict-mode validateId() would reject the ID, regenerating the
     * session forever. The upsert covers both cases: insert the missing
     * row (with IP and data), or touch only sess_updated on an existing
     * one - preserving the lazy-write optimization.
     *
     * @param string $id   session ID
     * @param string $data serialized session data (stored only when the row is first inserted)
     * @return bool true on success
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        $remoteAddress = \Xmf\IPAddress::fromRequest()->asReadable();
        $now = time();
        $sql = sprintf(
            'INSERT INTO %s (sess_id, sess_updated, sess_ip, sess_data)
             VALUES (%s, %u, %s, %s)
             ON DUPLICATE KEY UPDATE sess_updated = %u',
            $this->db->prefix('session'),
            $this->db->quote($id),
            $now,
            $this->db->quote($remoteAddress),
            $this->db->quote($data),
            $now
        );
        return (bool)$this->db->exec($sql);
    }

    // --- Helpers (same behavior as your current code) ---

    public function gc_force(): void
    {
        /*
         * Probabilistic garbage collection:
         * - We run GC with approximately 10% probability on each call.
         * - \random_int() is used instead of mt_rand() for modern, secure randomness.
         *   The range [1, 100] and threshold < 11 are chosen to preserve the
         *   original ~10% behavior from the mt_rand()-based implementation.
         * - Any failure of \random_int() is ignored so that session handling
         *   is not disrupted if a random source is temporarily unavailable.
         */
        try {
            if (\random_int(1, 100) < 11) {
                $expire = (int) @ini_get('session.gc_maxlifetime');
                if ($expire <= 0) {
                    $expire = 900;
                }
                $this->gc($expire);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function regenerate_id(bool $delete_old_session = false): bool
    {
        $success = $this->enableRegenerateId
            ? session_regenerate_id($delete_old_session)
            : true;

        if ($success) {
            $this->update_cookie();
        }
        return $success;
    }

    public function update_cookie($sess_id = null, $expire = null): void
    {
        // no-op; retained for API compatibility
    }
}
