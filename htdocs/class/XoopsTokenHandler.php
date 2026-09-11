<?php
/**
 * XOOPS generic scoped token handler
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since     2.7.0
 */

declare(strict_types=1);

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Generic scoped token handler for XOOPS.
 *
 * Provides create/verify/revoke/purge for any token-based flow:
 * password reset, account activation, email verification, etc.
 *
 * Recommended scopes: 'lostpass', 'activation', 'emailchange'.
 *
 * Requires PHP 8.2+ (readonly properties, union return types).
 *
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsTokenHandler
{
    /** @var int Minimum TTL floor in seconds */
    private const MIN_TTL = 60;

    /**
     * expires_at written for a token that never expires (a null ttl).
     *
     * The largest value the unsigned int column holds, so verify()'s
     * `expires_at > now` test passes for the life of the site and
     * purgeExpired() never sees it as expired. A ttl of 0 is not "never":
     * it is floored to MIN_TTL like any other short ttl, so the default-0
     * rows of older tables keep failing verification.
     *
     * @var int
     */
    private const NO_EXPIRY = 4294967295;

    private readonly \XoopsMySQLDatabase $db;

    /**
     * @param \XoopsMySQLDatabase $db Database connection
     *
     * @return void
     */
    public function __construct(\XoopsMySQLDatabase $db)
    {
        $this->db = $db;
    }

    /**
     * Create a token for a user+scope. Returns the raw token (for email).
     *
     * By default, revokes any previous unused tokens for the same user+scope
     * before inserting, so only the latest link is valid (OWASP recommendation).
     *
     * A caller may supply the raw token instead of having one generated, for
     * tokens the caller shows or formats itself (recovery codes). It is hashed
     * exactly as given: any canonicalisation belongs to the caller, because
     * the generated tokens of other scopes are case-sensitive.
     *
     * @param int         $uid            User ID
     * @param string      $scope          Token scope (e.g. 'lostpass', 'activation')
     * @param int|null    $ttl            Time-to-live in seconds (minimum 60), or null for a token that never expires
     * @param bool        $revokePrevious Revoke unused tokens for same scope first
     * @param string|null $rawToken       Caller-supplied raw token, or null to generate one
     *
     * @return string|false Raw token string, or false on DB failure, an empty caller token, or a failed revoke
     */
    public function create(
        int $uid,
        string $scope,
        ?int $ttl = 3600,
        bool $revokePrevious = true,
        ?string $rawToken = null
    ): string|false
    {
        if ($uid <= 0 || trim($scope) === '') {
            trigger_error(
                basename(__FILE__) . ': create() requires uid > 0 and non-empty scope',
                E_USER_WARNING
            );
            return false;
        }
        if ('' === $rawToken) {
            trigger_error(
                basename(__FILE__) . ': create() refuses an empty caller-supplied token',
                E_USER_WARNING
            );
            return false;
        }

        // A revoke that did not run leaves the previous token valid beside
        // the new one, so the new one is not issued.
        if ($revokePrevious && !$this->revokeByScope($uid, $scope)) {
            return false;
        }

        if (null === $rawToken) {
            try {
                $bytes = random_bytes(32);
            } catch (\Throwable $e) {
                trigger_error(
                    sprintf('%s::create() failed to generate secure random token: %s', __CLASS__, $e->getMessage()),
                    E_USER_WARNING
                );
                return false;
            }
            $rawToken = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        }
        $hash      = hash('sha256', $rawToken);
        $now       = time();
        // A ttl past the column's range means "never" as well; without the
        // clamp strict MySQL would refuse the row after the revoke ran.
        $expiresAt = null === $ttl ? self::NO_EXPIRY : (int) min(self::NO_EXPIRY, $now + max(self::MIN_TTL, $ttl));

        $table = $this->db->prefix('tokens');
        $sql   = sprintf(
            "INSERT INTO `%s` (`uid`, `scope`, `hash`, `issued_at`, `expires_at`, `used_at`)"
            . " VALUES (%d, %s, %s, %d, %d, 0)",
            $table,
            $uid,
            $this->db->quote($scope),
            $this->db->quote($hash),
            $now,
            $expiresAt
        );

        return $this->db->exec($sql) ? $rawToken : false;
    }

    /**
     * Atomically verify and consume a token. Single DB round-trip, no race condition.
     *
     * Uses UPDATE with WHERE conditions instead of SELECT-then-UPDATE to prevent
     * TOCTOU (time-of-check-to-time-of-use) double-consumption under concurrency.
     *
     * @param int    $uid      User ID
     * @param string $scope    Token scope
     * @param string $rawToken Raw token from the URL/form
     *
     * @return bool true if the token was valid and has now been consumed
     */
    public function verify(int $uid, string $scope, string $rawToken): bool
    {
        $hash  = hash('sha256', $rawToken);
        $table = $this->db->prefix('tokens');
        $now   = time();

        $result = $this->db->exec(sprintf(
            "UPDATE `%s` SET `used_at` = %d"
            . " WHERE `uid` = %d AND `scope` = %s AND `hash` = %s"
            . " AND `used_at` = 0 AND `expires_at` > %d",
            $table,
            $now,
            $uid,
            $this->db->quote($scope),
            $this->db->quote($hash),
            $now
        ));

        return $result && $this->db->getAffectedRows() === 1;
    }

    /**
     * Revoke all unused tokens for a user+scope.
     *
     * @param int    $uid   User ID
     * @param string $scope Token scope
     *
     * @return bool true when the statement ran (whether or not a row matched), false on DB failure
     */
    public function revokeByScope(int $uid, string $scope): bool
    {
        $table = $this->db->prefix('tokens');
        $now   = time();

        return (bool) $this->db->exec(sprintf(
            "UPDATE `%s` SET `used_at` = %d"
            . " WHERE `uid` = %d AND `scope` = %s AND `used_at` = 0",
            $table,
            $now,
            $uid,
            $this->db->quote($scope)
        ));
    }

    /**
     * Delete every token of a user, in every scope.
     *
     * For account deletion: an account that is gone must not leave usable
     * tokens behind.
     *
     * @param int $uid User ID
     *
     * @return bool true when the statement ran, false on DB failure or an invalid uid
     * @throws \mysqli_sql_exception If a MySQLi error occurs and MySQLi is configured to throw exceptions.
     */
    public function deleteByUid(int $uid): bool
    {
        if ($uid <= 0) {
            trigger_error(
                basename(__FILE__) . ': deleteByUid() requires uid > 0',
                E_USER_WARNING
            );
            return false;
        }
        $table = $this->db->prefix('tokens');

        return (bool) $this->db->exec(sprintf('DELETE FROM `%s` WHERE `uid` = %d', $table, $uid));
    }

    /**
     * Count tokens issued for a user+scope within a recent time window.
     *
     * Use for cooldown checks: "already requested in the last N minutes".
     *
     * @param int    $uid    User ID
     * @param string $scope  Token scope
     * @param int    $window Lookback window in seconds
     *
     * @return int Number of tokens issued in the window, or 0 on failure
     */
    public function countRecent(int $uid, string $scope, int $window): int
    {
        $table = $this->db->prefix('tokens');
        $since = time() - max(0, $window);

        $result = $this->db->query(sprintf(
            "SELECT COUNT(*) AS `cnt` FROM `%s`"
            . " WHERE `uid` = %d AND `scope` = %s AND `issued_at` > %d",
            $table,
            $uid,
            $this->db->quote($scope),
            $since
        ));

        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return 0;
        }

        $row = $this->db->fetchArray($result);

        return is_array($row) && isset($row['cnt']) ? (int)$row['cnt'] : 0;
    }

    /**
     * Delete tokens old enough to be past the retention window,
     * where they are either expired or already consumed.
     *
     * @param int $maxAge Retention window in seconds (default 7 days)
     *
     * @return void
     */
    public function purgeExpired(int $maxAge = 604800): void
    {
        $table  = $this->db->prefix('tokens');
        $now    = time();
        $cutoff = $now - max(0, $maxAge);

        $this->db->exec(sprintf(
            "DELETE FROM `%s`"
            . " WHERE `issued_at` < %d"
            . " AND (`expires_at` < %d OR `used_at` > 0)",
            $table,
            $cutoff,
            $now
        ));
    }
}
