<?php
/**
 * XOOPS second-factor handler
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
 * @since     2.7.4
 */

declare(strict_types=1);

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

require_once XOOPS_ROOT_PATH . '/class/XoopsTokenHandler.php';
require_once XOOPS_ROOT_PATH . '/class/XoopsTotp.php';
require_once XOOPS_ROOT_PATH . '/class/XoopsTwoFactorCrypto.php';

use Xmf\Key\FileStorage;

/**
 * The second-factor row of an account: state, encrypted TOTP secret,
 * monotonic step counter, failure throttle and factor generation, plus the
 * recovery codes kept in the tokens table under scope '2fa_recovery'.
 *
 * Every write that can race another (throttle, enrol, disable, regenerate)
 * runs in withTransaction() with the row locked FOR UPDATE, so a reset either
 * lands before a code is accepted or after it, never between the check and
 * the grant. Acceptance is the one exception: acceptTotp() is a single
 * conditional UPDATE whose WHERE clause carries every precondition, and
 * exactly one affected row grants, which gives the same guarantee without a
 * lock. Nothing inside a transaction touches `users`, redirects, mails or
 * fires events.
 *
 * Loaded by xoops_getHandler('user2fa'). Not a XoopsObjectHandler: there is
 * no XoopsObject for this row.
 *
 * @category  Kernel
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsUser2faHandler
{
    public const STATE_NONE        = 'none';
    public const STATE_ENROLLED    = 'enrolled';
    public const STATE_UNAVAILABLE = 'unavailable';
    public const ROW_ENROLLED      = 'enrolled';
    public const ROW_DISABLED      = 'disabled';
    public const METHOD_TOTP       = 'totp';
    public const RECOVERY_SCOPE    = '2fa_recovery';
    public const RECOVERY_CODES    = 10;
    public const LOCK_THRESHOLD    = 5;
    public const LOCK_SECONDS      = 900;
    public const POLICY_OFF        = 'off';
    public const POLICY_OPTIONAL   = 'optional';

    private readonly XoopsTokenHandler $tokens;
    private ?XoopsTwoFactorCrypto $crypto;
    private readonly bool $installed;

    /**
     * Connections with a withTransaction() in progress. Keyed by connection,
     * not by handler: two handlers on one mysqli handle share one transaction,
     * and a second START TRANSACTION would silently commit the first.
     *
     * @var \WeakMap<\XoopsMySQLDatabase, true>|null
     */
    private static ?\WeakMap $open = null;

    /**
     * Connections whose ROLLBACK was refused or threw: the abandoned
     * transaction may still be open, so nothing this handler does on them can
     * be trusted to persist. Every entry point refuses them for the rest of
     * the request.
     *
     * @var \WeakMap<\XoopsMySQLDatabase, true>|null
     */
    private static ?\WeakMap $poisoned = null;

    /**
     * @param \XoopsMySQLDatabase       $db        connection
     * @param XoopsTokenHandler|null    $tokens    recovery-code store (default: a handler on $db)
     * @param XoopsTwoFactorCrypto|null $crypto    key and cipher (default: built lazily on XOOPS_VAR_PATH/data)
     * @param bool|null                 $installed whether the 2.7.4 patch has run (default: the XOOPS_2FA_INSTALLED constant)
     */
    public function __construct(
        private readonly \XoopsMySQLDatabase $db,
        ?XoopsTokenHandler $tokens = null,
        ?XoopsTwoFactorCrypto $crypto = null,
        ?bool $installed = null,
    ) {
        $this->tokens    = $tokens ?? new XoopsTokenHandler($db);
        $this->crypto    = $crypto;
        $this->installed = $installed ?? (defined('XOOPS_2FA_INSTALLED') && XOOPS_2FA_INSTALLED);
    }

    /**
     * @return bool whether the 2.7.4 patch has created the table and preference
     */
    public function isInstalled(): bool
    {
        return $this->installed;
    }

    private function crypto(): XoopsTwoFactorCrypto
    {
        return $this->crypto ??= new XoopsTwoFactorCrypto(
            new FileStorage(XOOPS_VAR_PATH . '/data'),
            XOOPS_VAR_PATH . '/data/twofactor.lock'
        );
    }

    private function table(): string
    {
        return $this->db->prefix('user_2fa');
    }

    /**
     * @throws \RuntimeException when the query fails
     */
    private function selectRow(int $uid, bool $forUpdate): ?array
    {
        $sql = sprintf(
            'SELECT `uid`, `state`, `method`, `secret`, `confirmed_at`, `last_counter`, `failed_attempts`, `locked_until`, `generation` FROM `%s` WHERE `uid` = %d%s',
            $this->table(),
            $uid,
            $forUpdate ? ' FOR UPDATE' : ''
        );
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            throw new \RuntimeException('user_2fa lookup failed');
        }
        $row = $this->db->fetchArray($result);
        if (!is_array($row)) {
            return null;
        }
        foreach (['uid', 'confirmed_at', 'last_counter', 'failed_attempts', 'locked_until'] as $int) {
            $row[$int] = (int) $row[$int];
        }

        return $row;
    }

    /**
     * @param int $uid account
     *
     * @return array|null the row, or null when absent or the feature is not installed
     * @throws \RuntimeException when the query fails
     */
    public function getRow(int $uid): ?array
    {
        $this->assertConnectionUsable();

        return $this->installed ? $this->selectRow($uid, false) : null;
    }

    /**
     * Key provisioning must never replace a lost key while encrypted rows exist.
     *
     * @throws \RuntimeException when the database cannot answer
     */
    public function hasEncryptedSecrets(): bool
    {
        $this->assertConnectionUsable();
        if (!$this->installed) {
            return false;
        }
        $result = $this->db->query(sprintf('SELECT `uid` FROM `%s` WHERE `secret` IS NOT NULL LIMIT 1', $this->table()));
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            throw new \RuntimeException('Two-factor encrypted-secret lookup failed');
        }

        return is_array($this->db->fetchArray($result));
    }

    /**
     * The row locked for the current transaction.
     *
     * @param int $uid account
     *
     * @return array|null
     * @throws \LogicException   outside withTransaction()
     * @throws \RuntimeException when the query fails
     */
    public function lockRow(int $uid): ?array
    {
        if (!$this->inTransaction()) {
            throw new \LogicException('lockRow() requires withTransaction()');
        }

        return $this->selectRow($uid, true);
    }

    private function inTransaction(): bool
    {
        return null !== self::$open && isset(self::$open[$this->db]);
    }

    /**
     * @throws \RuntimeException when this connection's last ROLLBACK did not go through
     */
    private function assertConnectionUsable(): void
    {
        if (null !== self::$poisoned && isset(self::$poisoned[$this->db])) {
            throw new \RuntimeException('user_2fa: the connection may still hold an abandoned transaction');
        }
    }

    /**
     * The site policy, validated. A value this code does not know (including
     * the deferred "required") falls back to optional; an absent preference
     * means the 2.7.4 patch has not run and the feature is off. An empty row
     * set is a failed configuration read (see XOOPS_2FA_INSTALLED in
     * include/common.php), so every login gate fails closed on it.
     *
     * @param array $config the XOOPS_CONF row set ($xoopsConfig)
     *
     * @return string POLICY_OFF or POLICY_OPTIONAL
     */
    public static function policy(array $config): string
    {
        if ([] === $config) {
            return self::POLICY_OPTIONAL;
        }
        if (!array_key_exists('twofactor_mode', $config)) {
            return self::POLICY_OFF;
        }
        $mode = $config['twofactor_mode'];

        return (self::POLICY_OFF === $mode || self::POLICY_OPTIONAL === $mode) ? $mode : self::POLICY_OPTIONAL;
    }

    /**
     * @param string $policy from policy()
     * @param string $state  from stateFor() / stateOfRow()
     *
     * @return bool whether a login must present the second factor
     */
    public static function mustChallenge(string $policy, string $state): bool
    {
        return self::POLICY_OFF !== $policy && self::STATE_NONE !== $state;
    }

    /**
     * @param int $uid account
     *
     * @return string one of the STATE_* constants
     * @throws \RuntimeException when the lookup fails (the login gate reads that as unavailable)
     */
    public function stateFor(int $uid): string
    {
        return $this->stateOfRow($this->getRow($uid));
    }

    /**
     * The state a row (or its absence) maps to. Pages that already hold the
     * row from getRow() use this instead of a second lookup.
     *
     * @param array|null $row a getRow() result
     *
     * @return string one of the STATE_* constants
     */
    public function stateOfRow(?array $row): string
    {
        if (null === $row || self::ROW_DISABLED === $row['state']) {
            return self::STATE_NONE;
        }
        // "none" is reserved for an absent or disabled row: the login gate
        // reads it as "no factor". A state or method this code does not know
        // is a factor it cannot check, so it fails closed.
        if (self::ROW_ENROLLED !== $row['state'] || self::METHOD_TOTP !== $row['method']) {
            return self::STATE_UNAVAILABLE;
        }

        return null === $this->secretFromRow($row) ? self::STATE_UNAVAILABLE : self::STATE_ENROLLED;
    }

    /**
     * @param int $uid account
     *
     * @return string|null the base32 TOTP secret of an enrolled row, or null
     * @throws \RuntimeException when the lookup fails
     */
    public function secretFor(int $uid): ?string
    {
        $row = $this->getRow($uid);

        return (null === $row || self::ROW_ENROLLED !== $row['state']) ? null : $this->secretFromRow($row);
    }

    private function secretFromRow(array $row): ?string
    {
        if (!is_string($row['secret']) || '' === $row['secret']) {
            return null;
        }

        return $this->crypto()->open($row['secret'], XoopsTwoFactorCrypto::rowAad($row['uid'], (string) $row['method']));
    }

    /**
     * @return string 128 random bits as 32 hex characters
     */
    public function newGeneration(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @return string 128 random bits as 26 base32 characters
     */
    public function newRecoveryCode(): string
    {
        return XoopsTotp::base32Encode(random_bytes(16));
    }

    /**
     * @param string $code a recovery code as typed
     *
     * @return string whitespace stripped and upper-cased
     */
    public function canonicalRecoveryCode(string $code): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $code));
    }

    /**
     * Run $fn inside one transaction on the request's connection.
     *
     * @param callable $fn the work; return false to roll back
     * @param bool $strict throw when START or COMMIT reports failure
     *
     * @return mixed $fn's return value; false when the transaction could not start,
     *               when $fn returned false (rolled back), or when COMMIT failed (rolled back)
     * @throws \LogicException on nesting
     * @throws \Throwable      whatever $fn throws, after ROLLBACK
     */
    public function withTransaction(callable $fn, bool $strict = false): mixed
    {
        $this->assertConnectionUsable();
        if ($this->inTransaction()) {
            throw new \LogicException('withTransaction() does not nest');
        }
        if (!$this->db->exec('START TRANSACTION')) {
            if ($strict) {
                throw new \RuntimeException('Two-factor transaction could not start');
            }
            return false;
        }
        self::$open ??= new \WeakMap();
        self::$open[$this->db] = true;
        // True only once the connection accepted a COMMIT or a ROLLBACK; a
        // ROLLBACK that is refused or throws leaves it false.
        $closed = false;
        try {
            try {
                $value = $fn();
                if (false !== $value && $this->db->exec('COMMIT')) {
                    $closed = true;

                    return $value;
                }
                if (false !== $value && $strict) {
                    throw new \RuntimeException('Two-factor transaction could not commit');
                }
            } catch (\Throwable $e) {
                $closed = $this->db->exec('ROLLBACK');
                throw $e;
            }
            // $fn declined, or COMMIT was refused: either way the transaction
            // is still open on the connection and the next statement would
            // join it. Close it before letting go.
            $closed = $this->db->exec('ROLLBACK');

            return false;
        } finally {
            // A ROLLBACK that was refused or threw leaves the connection in a
            // state this code cannot see: a later START TRANSACTION would
            // implicitly commit the abandoned work, and a plain statement would
            // join it and could be rolled back later. Poison the connection so
            // every entry point refuses it for the rest of the request.
            if (!$closed) {
                self::$poisoned ??= new \WeakMap();
                self::$poisoned[$this->db] = true;
            }
            unset(self::$open[$this->db]);
        }
    }

    /**
     * Accept a TOTP step: exactly one affected row grants.
     *
     * @param int    $uid                account
     * @param int    $step               the step the code matched
     * @param string $verifiedGeneration the generation the challenge verified against
     * @param int    $now                unix time
     *
     * @return bool
     */
    public function acceptTotp(int $uid, int $step, string $verifiedGeneration, int $now): bool
    {
        $this->assertConnectionUsable();
        $sql = sprintf(
            'UPDATE `%s` SET `last_counter` = %d, `failed_attempts` = 0, `locked_until` = 0'
            . ' WHERE `uid` = %d AND `state` = %s AND `method` = %s AND `generation` = %s AND `locked_until` <= %d AND `last_counter` < %d',
            $this->table(),
            $step,
            $uid,
            $this->db->quote(self::ROW_ENROLLED),
            $this->db->quote(self::METHOD_TOTP),
            $this->db->quote($verifiedGeneration),
            $now,
            $step
        );

        if (!$this->db->exec($sql)) {
            throw new \RuntimeException('Two-factor counter write failed');
        }

        return $this->db->getAffectedRows() === 1;
    }

    /**
     * Count a failed code and report whether this request locked the account.
     *
     * @param int $uid account
     * @param int $now unix time
     * @param string|null $expectedGeneration generation of the rejected challenge; null for legacy callers
     *
     * @return array{locked: bool, transitioned: bool}|false
     */
    public function recordFailure(int $uid, int $now, ?string $expectedGeneration = null): array|false
    {
        return $this->withTransaction(function () use ($uid, $now, $expectedGeneration): array|false {
            $before = $this->lockRow($uid);
            if (null === $before || self::ROW_ENROLLED !== $before['state']
                || (null !== $expectedGeneration && !hash_equals((string) $before['generation'], $expectedGeneration))) {
                // The UPDATE below would match nothing and exec() would still
                // report success; refuse here so nothing is "counted".
                return false;
            }
            // Computed here, under the FOR UPDATE lock, and written as
            // literals: an UPDATE that derives one column from another reads
            // the old or the new value depending on the server's assignment
            // mode (MariaDB SIMULTANEOUS_ASSIGNMENT), which would move the
            // lock from the fifth wrong code to the sixth.
            $before['locked_until']    = (int) $before['locked_until'];
            $before['failed_attempts'] = (int) $before['failed_attempts'];
            $expired  = $before['locked_until'] > 0 && $before['locked_until'] <= $now;
            $attempts = $expired ? 1 : min($before['failed_attempts'] + 1, 65535);
            if ($expired) {
                $lockedUntil = 0;
            } elseif (0 === $before['locked_until'] && $attempts >= self::LOCK_THRESHOLD) {
                $lockedUntil = $now + self::LOCK_SECONDS;
            } else {
                $lockedUntil = $before['locked_until'];
            }
            $sql = sprintf(
                'UPDATE `%s` SET `failed_attempts` = %d, `locked_until` = %d WHERE `uid` = %d AND `state` = %s',
                $this->table(),
                $attempts,
                $lockedUntil,
                $uid,
                $this->db->quote(self::ROW_ENROLLED)
            );
            if (!$this->db->exec($sql)) {
                throw new \RuntimeException('Two-factor throttle write failed');
            }
            $wasLocked = $before['locked_until'] > $now;
            $isLocked  = $lockedUntil > $now;

            return ['locked' => $isLocked, 'transitioned' => $isLocked && !$wasLocked];
        }, true);
    }

    /**
     * Consume a recovery code with the factor row locked.
     *
     * @param int    $uid               account
     * @param string $code              code as typed
     * @param string $pendingGeneration the generation the pending login recorded
     *
     * @return bool
     */
    public function acceptRecovery(int $uid, string $code, string $pendingGeneration): bool
    {
        $canonical = $this->canonicalRecoveryCode($code);
        if ('' === $canonical) {
            return false;
        }

        return (bool) $this->withTransaction(function () use ($uid, $canonical, $pendingGeneration): bool {
            $row = $this->lockRow($uid);
            if (null === $row || self::ROW_ENROLLED !== $row['state'] || !hash_equals((string) $row['generation'], $pendingGeneration)) {
                return false;
            }
            if (!$this->tokens->verify($uid, self::RECOVERY_SCOPE, $canonical, true)) {
                return false;
            }

            if (!$this->db->exec(sprintf(
                'UPDATE `%s` SET `failed_attempts` = 0, `locked_until` = 0 WHERE `uid` = %d',
                $this->table(),
                $uid
            ))) {
                throw new \RuntimeException('Two-factor throttle reset failed');
            }

            return true;
        }, true);
    }

    /**
     * Create (or re-enable a disabled) row and issue ten recovery codes.
     *
     * @param int    $uid          account
     * @param string $secretBase32 the secret the user confirmed a code against
     * @param int    $acceptedStep the step of that code
     * @param int    $now          unix time
     * @param string|null $expectedGeneration setup generation, empty for no row; null for legacy callers
     *
     * @return array|false the new generation and recovery codes
     * @phpstan-return array{generation: string, codes: list<string>}|false
     */
    public function enrol(int $uid, string $secretBase32, int $acceptedStep, int $now, ?string $expectedGeneration = null): array|false
    {
        return $this->withTransaction(function () use ($uid, $secretBase32, $acceptedStep, $now, $expectedGeneration): array|false {
            $row = $this->lockRow($uid);
            if (null !== $expectedGeneration && !hash_equals((string) ($row['generation'] ?? ''), $expectedGeneration)) {
                return false;
            }
            if (null !== $row && self::ROW_DISABLED !== $row['state']) {
                // Enrolled: the second tab loses. Anything else is a row this
                // code does not know and must not overwrite (see stateFor()).
                return false;
            }
            $blob = $this->crypto()->seal($secretBase32, XoopsTwoFactorCrypto::rowAad($uid, self::METHOD_TOTP));
            if (null === $blob) {
                return false;
            }
            $generation = $this->newGeneration();
            $table      = $this->table();
            $sql        = null === $row
                ? sprintf(
                    'INSERT INTO `%s` (`uid`, `state`, `method`, `secret`, `confirmed_at`, `last_counter`, `failed_attempts`, `locked_until`, `generation`)'
                    . ' VALUES (%d, %s, %s, %s, %d, %d, 0, 0, %s)',
                    $table,
                    $uid,
                    $this->db->quote(self::ROW_ENROLLED),
                    $this->db->quote(self::METHOD_TOTP),
                    $this->db->quote($blob),
                    $now,
                    $acceptedStep,
                    $this->db->quote($generation)
                )
                : sprintf(
                    'UPDATE `%s` SET `state` = %s, `method` = %s, `secret` = %s, `confirmed_at` = %d, `last_counter` = %d,'
                    . ' `failed_attempts` = 0, `locked_until` = 0, `generation` = %s WHERE `uid` = %d',
                    $table,
                    $this->db->quote(self::ROW_ENROLLED),
                    $this->db->quote(self::METHOD_TOTP),
                    $this->db->quote($blob),
                    $now,
                    $acceptedStep,
                    $this->db->quote($generation),
                    $uid
                );
            if (!$this->db->exec($sql)) {
                return false;
            }
            $codes = $this->issueRecoveryCodes($uid);

            return false === $codes ? false : ['generation' => $generation, 'codes' => $codes];
        });
    }

    /**
     * Disable the factor: state, secret, codes and generation, in one transaction.
     *
     * @param int $uid account
     *
     * @return string|false the new generation
     */
    public function disable(int $uid): string|false
    {
        return $this->withTransaction(function () use ($uid): string|false {
            if (null === $this->lockRow($uid)) {
                return false;
            }

            return $this->disableLocked($uid);
        });
    }

    /** The caller holds the factor row lock until commit. */
    private function disableLocked(int $uid): string|false
    {
        $generation = $this->newGeneration();
        $sql = sprintf(
            'UPDATE `%s` SET `state` = %s, `secret` = NULL, `generation` = %s WHERE `uid` = %d',
            $this->table(),
            $this->db->quote(self::ROW_DISABLED),
            $this->db->quote($generation),
            $uid
        );
        if (!$this->db->exec($sql) || !$this->tokens->revokeByScope($uid, self::RECOVERY_SCOPE)) {
            return false;
        }

        return $generation;
    }

    /**
     * Verify the current factor and disable it or replace its recovery codes
     * under the same row lock, so reset cannot race verification and mutation.
     *
     * @return string[]|string|false new codes, disabled generation, or rejected verification
     * @phpstan-return list<string>|string|false
     * @throws \InvalidArgumentException for an unknown action
     * @throws \RuntimeException for unavailable crypto or failed storage
     */
    public function manage(int $uid, string $expectedGeneration, string $code, string $recovery, string $action, int $now): array|string|false
    {
        if (!in_array($action, ['disable', 'regenerate'], true)) {
            throw new \InvalidArgumentException('Unknown two-factor management action');
        }
        $this->assertConnectionUsable();
        if (!$this->installed) {
            return false;
        }

        return $this->withTransaction(function () use ($uid, $expectedGeneration, $code, $recovery, $action, $now): array|string|false {
            $row = $this->lockRow($uid);
            if (null === $row || self::ROW_ENROLLED !== $row['state']
                || !hash_equals((string) $row['generation'], $expectedGeneration)) {
                return false;
            }
            $canonical = $this->canonicalRecoveryCode($recovery);
            if ('' !== $canonical) {
                // Recovery remains available when the encryption key is lost
                // and while the authenticator-code throttle is locked.
                if (!$this->tokens->verify($uid, self::RECOVERY_SCOPE, $canonical, true)) {
                    return false;
                }
                if (!$this->db->exec(sprintf(
                    'UPDATE `%s` SET `failed_attempts` = 0, `locked_until` = 0 WHERE `uid` = %d',
                    $this->table(),
                    $uid
                ))) {
                    throw new \RuntimeException('Two-factor throttle reset failed');
                }
            } else {
                if ($row['locked_until'] > $now || self::METHOD_TOTP !== $row['method']) {
                    return false;
                }
                $secret = $this->secretFromRow($row);
                if (null === $secret) {
                    throw new \RuntimeException('Two-factor verification unavailable');
                }
                $step = XoopsTotp::matchStep($secret, $code, $now, (int) $row['last_counter']);
                if (false === $step || !$this->acceptTotp($uid, $step, $expectedGeneration, $now)) {
                    return false;
                }
            }
            $result = $action === 'disable' ? $this->disableLocked($uid) : $this->issueRecoveryCodes($uid);
            if (false === $result) {
                throw new \RuntimeException('Two-factor management write failed');
            }

            return $result;
        }, true);
    }

    /**
     * @param int $uid account
     *
     * @return string[]|false ten new codes
     * @phpstan-return list<string>|false
     */
    public function regenerateRecoveryCodes(int $uid): array|false
    {
        return $this->withTransaction(function () use ($uid): array|false {
            $row = $this->lockRow($uid);
            if (null === $row || self::ROW_ENROLLED !== $row['state']) {
                return false;
            }

            return $this->issueRecoveryCodes($uid);
        });
    }

    /**
     * Revoke the previous codes, then issue ten fresh ones (inside the caller's transaction).
     *
     * @return string[]|false
     * @phpstan-return list<string>|false
     */
    private function issueRecoveryCodes(int $uid): array|false
    {
        if (!$this->tokens->revokeByScope($uid, self::RECOVERY_SCOPE)) {
            return false;
        }
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $code = $this->newRecoveryCode();
            if (false === $this->tokens->create($uid, self::RECOVERY_SCOPE, null, false, $code)) {
                return false;
            }
            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * Operator escape hatch: xoops_data/data/2fa-reset-<uid>.txt containing
     * the word "reset" disables the factor once. The file is read, never
     * included: a write into the data directory must not become code. It is
     * renamed to .used before the reset runs; a refused rename, or a .used
     * twin already present, refuses the reset, because a rename would
     * replace the earlier marker. The uid comes from the pending or
     * wizard-authenticated login, never from the request.
     *
     * @param int         $uid     account
     * @param string|null $dataDir directory holding the file (default XOOPS_VAR_PATH/data)
     *
     * @return bool true only when the file was consumed and the row disabled
     */
    public function resetByEscapeHatch(int $uid, ?string $dataDir = null): bool
    {
        $root = realpath($dataDir ?? (XOOPS_VAR_PATH . '/data'));
        if (false === $root) {
            return false;
        }
        $name = $root . DIRECTORY_SEPARATOR . '2fa-reset-' . $uid;
        $file = $name . '.txt';
        $used = $name . '.used';
        if (file_exists($used) || is_link($file) || !is_file($file)) {
            return false;
        }
        $real = realpath($file);
        if (false === $real || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return false;
        }
        $content = file_get_contents($real);
        if (!is_string($content) || 'reset' !== trim($content)) {
            return false;
        }
        if (!rename($real, $used)) {
            return false;
        }
        trigger_error(sprintf('Two-factor escape hatch used for uid %d', $uid), E_USER_NOTICE);
        $disabled = false;
        try {
            $disabled = false !== $this->disable($uid);
        } finally {
            if (!$disabled) {
                // The reset did not happen: give the file back so the next attempt is not refused as used.
                rename($used, $file);
            }
        }

        return $disabled;
    }

    /**
     * Remove the row when an account is deleted. True without a query when the
     * feature is not installed, so a file-first upgrade can still delete users.
     *
     * @param int $uid account
     *
     * @return bool
     */
    public function deleteByUid(int $uid): bool
    {
        $this->assertConnectionUsable();
        if (!$this->installed) {
            return true;
        }

        return $this->db->exec(sprintf('DELETE FROM `%s` WHERE `uid` = %d', $this->table(), $uid));
    }
}
