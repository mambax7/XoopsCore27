<?php
/**
 * Unit tests for XoopsUser2faHandler
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
 * @package   core
 * @since     2.7.4
 */

declare(strict_types=1);

namespace kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Xmf\Key\FileStorage;
use XoopsMySQLDatabase;
use XoopsTokenHandler;
use XoopsTwoFactorCrypto;
use XoopsUser2faHandler;

/**
 * Pins the factor handler's statements to their exact text, its acceptance
 * statement to one winner per step on a real table, and its transactions to
 * rollback on failure and refusal to nest.
 *
 * The token handler and the crypto are final, so the real classes run on the
 * same captured-statement mock and on a temporary key file.
 *
 * @category  Test
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(XoopsUser2faHandler::class)]
class XoopsUser2faHandlerTest extends KernelTestCase
{
    private const GEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const NOW = 1700000000;

    /** @var list<string> every statement, exec() and query() alike, in order */
    private array $sql = [];

    /** @var list<array|false> fetchArray() answers, consumed in order */
    private array $rows = [];

    private bool $queryFails = false;

    /** @var bool|callable(string): bool */
    private mixed $execResult = true;

    private int $affected = 1;

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('ext-sodium is required');
        }
        if (!extension_loaded('mysqli')) {
            $this->markTestSkipped('ext-mysqli is required for the mysqli_result stub');
        }
        require_once XOOPS_ROOT_PATH . '/kernel/user2fa.php';
        $this->sql        = [];
        $this->rows       = [];
        $this->queryFails = false;
        $this->execResult = true;
        $this->affected   = 1;
        $this->dir        = sys_get_temp_dir() . '/xoops2fa-h-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        // tearDown runs after a skipped setUp too: only touch our own directory.
        if ('' !== $this->dir && is_dir($this->dir)) {
            $this->removeTree($this->dir);
        }
        parent::tearDown();
    }

    /** The escape-hatch tests use a subdirectory; symlinks are unlinked, not followed. */
    private function removeTree(string $dir): void
    {
        foreach ((array) glob($dir . '/{,.}[!.,!..]*', GLOB_BRACE) as $path) {
            if (is_dir($path) && !is_link($path)) {
                $this->removeTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /* ---------------------------------------------------------------- */
    /* fixtures                                                          */
    /* ---------------------------------------------------------------- */

    private function db(): XoopsMySQLDatabase
    {
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function (string $statement): bool {
            $this->sql[] = $statement;

            return is_callable($this->execResult) ? ($this->execResult)($statement) : $this->execResult;
        });
        $db->method('query')->willReturnCallback(function (string $statement) {
            $this->sql[] = $statement;

            return $this->queryFails ? false : (new \ReflectionClass(\mysqli_result::class))->newInstanceWithoutConstructor();
        });
        $db->method('isResultSet')->willReturnCallback(static fn ($result): bool => $result instanceof \mysqli_result);
        $db->method('fetchArray')->willReturnCallback(function () {
            $row = array_shift($this->rows);

            return null === $row ? false : $row;
        });
        $db->method('getAffectedRows')->willReturnCallback(fn (): int => $this->affected);

        return $db;
    }

    /** A crypto with a provisioned key, or without one. */
    private function crypto(bool $withKey = true): XoopsTwoFactorCrypto
    {
        $crypto = new XoopsTwoFactorCrypto(new FileStorage($this->dir, $withKey ? 'test' : 'nokey'), $this->dir . '/twofactor.lock');
        if ($withKey) {
            $this->assertTrue($crypto->provisionKey(false));
        }

        return $crypto;
    }

    private function handler(?XoopsTwoFactorCrypto $crypto = null, bool $installed = true, ?XoopsMySQLDatabase $db = null): XoopsUser2faHandler
    {
        $db ??= $this->db();

        return new XoopsUser2faHandler($db, new XoopsTokenHandler($db), $crypto ?? $this->crypto(), $installed);
    }

    private function row(array $over = []): array
    {
        return $over + [
            'uid'             => 10,
            'state'           => 'enrolled',
            'method'          => 'totp',
            'secret'          => 'v1:blob',
            'confirmed_at'    => 1,
            'last_counter'    => 100,
            'failed_attempts' => 0,
            'locked_until'    => 0,
            'generation'      => self::GEN,
        ];
    }

    /** Statements from the log that start with $prefix. */
    private function statements(string $prefix): array
    {
        return array_values(array_filter($this->sql, static fn (string $s): bool => str_starts_with($s, $prefix)));
    }

    /* ---------------------------------------------------------------- */
    /* state                                                             */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function stateForIsNoneWithoutAQueryWhenNotInstalled(): void
    {
        $handler = $this->handler(null, false);
        $this->assertFalse($handler->isInstalled());
        $this->assertSame('none', $handler->stateFor(10));
        $this->assertNull($handler->getRow(10));
        $this->assertTrue($handler->deleteByUid(10));
        $this->assertSame([], $this->sql);
    }

    #[Test]
    public function stateForMapsRowsAndTheKey(): void
    {
        $crypto  = $this->crypto();
        $handler = $this->handler($crypto);

        $this->rows = [false];
        $this->assertSame('none', $handler->stateFor(10));

        $this->rows = [$this->row(['state' => 'disabled', 'secret' => null])];
        $this->assertSame('none', $handler->stateFor(10));

        // A state or method this code does not know is never "no factor".
        $this->rows = [$this->row(['state' => 'pending']), $this->row(['method' => 'webauthn'])];
        $this->assertSame('unavailable', $handler->stateFor(10));
        $this->assertSame('unavailable', $handler->stateFor(10));

        $sealed     = (string) $crypto->seal('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', XoopsTwoFactorCrypto::rowAad(10, 'totp'));
        $this->rows = [$this->row(['secret' => $sealed]), $this->row(['secret' => $sealed])];
        $this->assertSame('enrolled', $handler->stateFor(10));
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $handler->secretFor(10));

        // A blob moved from another account's row does not open.
        $moved      = (string) $crypto->seal('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', XoopsTwoFactorCrypto::rowAad(11, 'totp'));
        $this->rows = [$this->row(['secret' => $moved]), $this->row(['secret' => $moved])];
        $this->assertSame('unavailable', $handler->stateFor(10));
        $this->assertNull($handler->secretFor(10));

        // Garbage in the column, and a null secret on an enrolled row.
        $this->rows = [$this->row(['secret' => 'v1:blob']), $this->row(['secret' => null])];
        $this->assertSame('unavailable', $handler->stateFor(10));
        $this->assertSame('unavailable', $handler->stateFor(10));

        // A lost key: unavailable, never "none".
        $this->rows = [$this->row(['secret' => $sealed])];
        $this->assertSame('unavailable', $this->handler($this->crypto(false), true, $this->db())->stateFor(10));

        $this->assertSame(
            'SELECT `uid`, `state`, `method`, `secret`, `confirmed_at`, `last_counter`, `failed_attempts`, `locked_until`, `generation` FROM `xoops_user_2fa` WHERE `uid` = 10',
            $this->sql[0]
        );
    }

    #[Test]
    public function getRowThrowsWhenTheLookupFails(): void
    {
        $handler          = $this->handler();
        $this->queryFails = true;
        $this->expectException(\RuntimeException::class);
        $handler->stateFor(10);
    }

    /* ---------------------------------------------------------------- */
    /* TOTP acceptance                                                   */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function acceptTotpPinsItsStatement(): void
    {
        $handler = $this->handler();

        $this->affected = 1;
        $this->assertTrue($handler->acceptTotp(10, 101, self::GEN, self::NOW));
        $this->assertSame(
            'UPDATE `xoops_user_2fa` SET `last_counter` = 101, `failed_attempts` = 0, `locked_until` = 0'
            . " WHERE `uid` = 10 AND `state` = 'enrolled' AND `method` = 'totp' AND `generation` = '" . self::GEN . "'"
            . ' AND `locked_until` <= 1700000000 AND `last_counter` < 101',
            $this->sql[0]
        );

        $this->affected = 0;
        $this->assertFalse($handler->acceptTotp(10, 101, self::GEN, self::NOW));

        $this->affected   = 1;
        $this->execResult = false;
        $this->assertFalse($handler->acceptTotp(10, 101, self::GEN, self::NOW));
    }

    #[Test]
    public function acceptTotpOnSqliteGrantsOneWinnerPerStepAndRefusesEarlierSteps(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required to execute the handler SQL');
        }
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE xoops_user_2fa ('
            . 'uid INTEGER NOT NULL PRIMARY KEY, state TEXT NOT NULL, method TEXT NOT NULL DEFAULT \'totp\','
            . ' secret BLOB NULL, confirmed_at INTEGER NOT NULL DEFAULT 0, last_counter INTEGER NOT NULL DEFAULT 0,'
            . ' failed_attempts INTEGER NOT NULL DEFAULT 0, locked_until INTEGER NOT NULL DEFAULT 0, generation TEXT NOT NULL)');
        $pdo->exec("INSERT INTO xoops_user_2fa (uid, state, secret, last_counter, generation) VALUES (10, 'enrolled', 'x', 100, 'g1')");

        $this->execResult = function (string $statement) use ($pdo): bool {
            $this->affected = (int) $pdo->exec($statement);

            return true;
        };
        $handler = $this->handler();
        $read    = static fn (): array => (array) $pdo->query('SELECT last_counter, failed_attempts, locked_until FROM xoops_user_2fa WHERE uid = 10')->fetch(\PDO::FETCH_ASSOC);

        $this->assertTrue($handler->acceptTotp(10, 101, 'g1', self::NOW), 'the next step is accepted');
        $this->assertFalse($handler->acceptTotp(10, 101, 'g1', self::NOW), 'the same step is accepted once');
        $this->assertFalse($handler->acceptTotp(10, 100, 'g1', self::NOW), 'N-1 after N is refused');
        $this->assertFalse($handler->acceptTotp(10, 102, 'g2', self::NOW), 'a changed generation is refused');
        $this->assertSame(['last_counter' => 101, 'failed_attempts' => 0, 'locked_until' => 0], array_map('intval', $read()));

        $pdo->exec('UPDATE xoops_user_2fa SET locked_until = ' . (self::NOW + 10) . ' WHERE uid = 10');
        $this->assertFalse($handler->acceptTotp(10, 102, 'g1', self::NOW), 'a locked row is refused');

        $pdo->exec('UPDATE xoops_user_2fa SET locked_until = 0, failed_attempts = 3 WHERE uid = 10');
        $this->assertTrue($handler->acceptTotp(10, 102, 'g1', self::NOW), 'unlocked again');
        $this->assertSame(['last_counter' => 102, 'failed_attempts' => 0, 'locked_until' => 0], array_map('intval', $read()), 'success clears the counter and the lock');
    }

    /* ---------------------------------------------------------------- */
    /* throttle                                                          */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function recordFailurePinsTheThrottleStatementAndDetectsTheTransition(): void
    {
        $handler    = $this->handler();
        $this->rows = [
            $this->row(['failed_attempts' => 4, 'locked_until' => 0]),
            $this->row(['failed_attempts' => 5, 'locked_until' => self::NOW + 900]),
        ];

        $this->assertSame(['locked' => true, 'transitioned' => true], $handler->recordFailure(10, self::NOW));
        $this->assertSame([
            'START TRANSACTION',
            'SELECT `uid`, `state`, `method`, `secret`, `confirmed_at`, `last_counter`, `failed_attempts`, `locked_until`, `generation` FROM `xoops_user_2fa` WHERE `uid` = 10 FOR UPDATE',
            'UPDATE `xoops_user_2fa` SET `failed_attempts` = IF(`locked_until` > 0 AND `locked_until` <= 1700000000, 1, LEAST(`failed_attempts` + 1, 65535)),'
            . ' `locked_until` = IF(`locked_until` > 0 AND `locked_until` <= 1700000000, 0, IF(`locked_until` = 0 AND `failed_attempts` >= 5, 1700000900, `locked_until`))'
            . " WHERE `uid` = 10 AND `state` = 'enrolled'",
            'SELECT `uid`, `state`, `method`, `secret`, `confirmed_at`, `last_counter`, `failed_attempts`, `locked_until`, `generation` FROM `xoops_user_2fa` WHERE `uid` = 10',
            'COMMIT',
        ], $this->sql);

        // Already locked before: counted, no transition.
        $this->sql  = [];
        $this->rows = [
            $this->row(['failed_attempts' => 6, 'locked_until' => self::NOW + 100]),
            $this->row(['failed_attempts' => 7, 'locked_until' => self::NOW + 100]),
        ];
        $this->assertSame(['locked' => true, 'transitioned' => false], $handler->recordFailure(10, self::NOW));

        // Fewer than five: not locked.
        $this->rows = [
            $this->row(['failed_attempts' => 1, 'locked_until' => 0]),
            $this->row(['failed_attempts' => 2, 'locked_until' => 0]),
        ];
        $this->assertSame(['locked' => false, 'transitioned' => false], $handler->recordFailure(10, self::NOW));

        // Disabled row: the UPDATE would match nothing, so it is not issued.
        $this->sql  = [];
        $this->rows = [$this->row(['state' => 'disabled'])];
        $this->assertFalse($handler->recordFailure(10, self::NOW));
        $this->assertSame([], $this->statements('UPDATE'));
        $this->assertSame('ROLLBACK', end($this->sql));

        // No row: nothing to count, rolled back.
        $this->sql  = [];
        $this->rows = [false];
        $this->assertFalse($handler->recordFailure(10, self::NOW));
        $this->assertSame([
            'START TRANSACTION',
            'SELECT `uid`, `state`, `method`, `secret`, `confirmed_at`, `last_counter`, `failed_attempts`, `locked_until`, `generation` FROM `xoops_user_2fa` WHERE `uid` = 10 FOR UPDATE',
            'ROLLBACK',
        ], $this->sql);
    }

    /* ---------------------------------------------------------------- */
    /* recovery                                                          */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function acceptRecoveryCanonicalisesAndSerialisesOnTheRow(): void
    {
        $handler    = $this->handler();
        $this->rows = [$this->row()];

        $this->assertTrue($handler->acceptRecovery(10, "abcd efgh ijkl\tmnop qrst uvwx yz", self::GEN));
        $this->assertCount(5, $this->sql);
        $this->assertSame('START TRANSACTION', $this->sql[0]);
        $this->assertStringEndsWith('WHERE `uid` = 10 FOR UPDATE', $this->sql[1]);
        $this->assertStringStartsWith('UPDATE `xoops_tokens` SET `used_at` = ', $this->sql[2]);
        $this->assertStringContainsString(
            "WHERE `uid` = 10 AND `scope` = '2fa_recovery' AND `hash` = '" . hash('sha256', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') . "' AND `used_at` = 0",
            $this->sql[2]
        );
        $this->assertSame('UPDATE `xoops_user_2fa` SET `failed_attempts` = 0, `locked_until` = 0 WHERE `uid` = 10', $this->sql[3]);
        $this->assertSame('COMMIT', $this->sql[4]);

        // Disabled row: the token is never consumed.
        $this->sql  = [];
        $this->rows = [$this->row(['state' => 'disabled'])];
        $this->assertFalse($handler->acceptRecovery(10, 'ABCDEFGH', self::GEN));
        $this->assertSame([], $this->statements('UPDATE `xoops_tokens`'));
        $this->assertSame('ROLLBACK', end($this->sql));

        // Generation moved since the pending login: refused.
        $this->sql  = [];
        $this->rows = [$this->row(['generation' => str_repeat('b', 32)])];
        $this->assertFalse($handler->acceptRecovery(10, 'ABCDEFGH', self::GEN));
        $this->assertSame([], $this->statements('UPDATE `xoops_tokens`'));
        $this->assertSame('ROLLBACK', end($this->sql));

        // Unknown or used code: verify() affects no row.
        $this->sql      = [];
        $this->rows     = [$this->row()];
        $this->affected = 0;
        $this->assertFalse($handler->acceptRecovery(10, 'ABCDEFGH', self::GEN));
        $this->assertCount(1, $this->statements('UPDATE `xoops_tokens`'));
        $this->assertSame([], $this->statements('UPDATE `xoops_user_2fa`'));
        $this->assertSame('ROLLBACK', end($this->sql));

        // Nothing typed: no transaction at all.
        $this->sql = [];
        $this->assertFalse($handler->acceptRecovery(10, " \t ", self::GEN));
        $this->assertSame([], $this->sql);
    }

    /* ---------------------------------------------------------------- */
    /* transactions                                                      */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function withTransactionRefusesNestingRollsBackOnThrowAndOnFalse(): void
    {
        $handler = $this->handler();

        $this->assertSame('value', $handler->withTransaction(static fn (): string => 'value'));
        $this->assertSame(['START TRANSACTION', 'COMMIT'], $this->sql);

        $this->sql = [];
        try {
            $handler->withTransaction(static fn () => $handler->withTransaction(static fn (): bool => true));
            $this->fail('nesting was accepted');
        } catch (\LogicException) {
            $this->assertSame(['START TRANSACTION', 'ROLLBACK'], $this->sql);
        }

        $this->sql = [];
        try {
            $handler->withTransaction(static function (): void {
                throw new \RuntimeException('boom');
            });
            $this->fail('the exception was swallowed');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
            $this->assertSame(['START TRANSACTION', 'ROLLBACK'], $this->sql);
        }

        $this->sql = [];
        $this->assertFalse($handler->withTransaction(static fn (): bool => false));
        $this->assertSame(['START TRANSACTION', 'ROLLBACK'], $this->sql);

        // The guard is clear again after a throw or a rollback.
        $this->sql = [];
        $this->assertTrue($handler->withTransaction(static fn (): bool => true));
        $this->assertSame(['START TRANSACTION', 'COMMIT'], $this->sql);

        $this->sql        = [];
        $ran              = false;
        $this->execResult = static fn (string $s): bool => 'START TRANSACTION' !== $s;
        $this->assertFalse($handler->withTransaction(static function () use (&$ran): bool {
            $ran = true;

            return true;
        }));
        $this->assertFalse($ran, 'the work does not run without a transaction');
        $this->assertSame(['START TRANSACTION'], $this->sql);

        $this->sql        = [];
        $this->execResult = static fn (string $s): bool => 'COMMIT' !== $s;
        $this->assertFalse($handler->withTransaction(static fn (): string => 'value'));
        $this->assertSame(['START TRANSACTION', 'COMMIT', 'ROLLBACK'], $this->sql, 'a refused COMMIT is rolled back so the connection is not left in a transaction');

        // A refused ROLLBACK: the connection may still hold the transaction,
        // so no later withTransaction() may START on it.
        $this->sql        = [];
        $this->execResult = static fn (string $s): bool => 'ROLLBACK' !== $s;
        $poisoned         = $this->handler($this->crypto(), true, $this->db());
        $this->assertFalse($poisoned->withTransaction(static fn (): bool => false));
        $this->assertSame(['START TRANSACTION', 'ROLLBACK'], $this->sql);
        foreach ([
            static fn () => $poisoned->withTransaction(static fn (): bool => true),
            static fn () => $poisoned->getRow(10),
            static fn () => $poisoned->acceptTotp(10, 101, self::GEN, self::NOW),
            static fn () => $poisoned->deleteByUid(10),
        ] as $call) {
            try {
                $call();
                $this->fail('the handler used a connection whose ROLLBACK was refused');
            } catch (\RuntimeException) {
                $this->assertSame(['START TRANSACTION', 'ROLLBACK'], $this->sql, 'no statement reached the connection');
            }
        }
        $this->execResult = true;

        // lockRow() outside a transaction is a programming error.
        $this->expectException(\LogicException::class);
        $handler->lockRow(10);
    }

    #[Test]
    public function theNestingGuardIsPerConnectionNotPerHandler(): void
    {
        $db     = $this->db();
        $crypto = $this->crypto();
        $a      = $this->handler($crypto, true, $db);
        $b      = $this->handler($crypto, true, $db);

        try {
            $a->withTransaction(static fn () => $b->withTransaction(static fn (): bool => true));
            $this->fail('a second handler opened a transaction on a connection that already had one');
        } catch (\LogicException) {
            $this->assertSame(['START TRANSACTION', 'ROLLBACK'], $this->sql, 'no second START TRANSACTION reached the connection');
        }

        // Inside the connection's transaction, the other handler may lock rows.
        $this->sql  = [];
        $this->rows = [$this->row()];
        $this->assertIsArray($a->withTransaction(static fn (): ?array => $b->lockRow(10)));
        $this->assertStringEndsWith('FOR UPDATE', $this->sql[1]);

        // Another connection is another transaction.
        $other = $this->handler($crypto, true, $this->db());
        $this->assertTrue($a->withTransaction(static fn (): bool => $other->withTransaction(static fn (): bool => true)));

        // The guard clears with the transaction.
        $this->sql = [];
        $this->assertTrue($b->withTransaction(static fn (): bool => true));
        $this->assertSame(['START TRANSACTION', 'COMMIT'], $this->sql);
    }

    #[Test]
    public function aRollbackThatThrowsKeepsTheGuard(): void
    {
        $handler          = $this->handler();
        $this->execResult = static function (string $s): bool {
            if ('ROLLBACK' === $s) {
                throw new \RuntimeException('rollback failed');
            }

            return true;
        };

        try {
            $handler->withTransaction(static function (): void {
                throw new \RuntimeException('boom');
            });
            $this->fail('nothing propagated');
        } catch (\RuntimeException $e) {
            $this->assertSame('rollback failed', $e->getMessage());
        }

        // The connection may still hold the transaction: closed to a new one.
        $this->execResult = true;
        $this->sql        = [];
        try {
            $handler->withTransaction(static fn (): bool => true);
            $this->fail('a transaction started on a connection whose ROLLBACK threw');
        } catch (\RuntimeException) {
            $this->assertSame([], $this->sql, 'no START TRANSACTION reached the connection');
        }
    }

    /* ---------------------------------------------------------------- */
    /* enrol / disable / regenerate / delete                             */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function enrolInsertsANewRowOrReplacesADisabledOne(): void
    {
        $crypto  = $this->crypto();
        $handler = $this->handler($crypto);

        // No row: INSERT.
        $this->rows = [false];
        $result     = $handler->enrol(10, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 101, self::NOW);
        $this->assertIsArray($result);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $result['generation']);
        $this->assertCount(10, $result['codes']);
        $this->assertCount(10, array_unique($result['codes']));
        foreach ($result['codes'] as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z2-7]{26}$/', $code);
        }

        $inserts = $this->statements('INSERT INTO `xoops_user_2fa`');
        $this->assertCount(1, $inserts);
        $this->assertMatchesRegularExpression(
            '/^INSERT INTO `xoops_user_2fa` \(`uid`, `state`, `method`, `secret`, `confirmed_at`, `last_counter`, `failed_attempts`, `locked_until`, `generation`\)'
            . " VALUES \(10, 'enrolled', 'totp', 'v1:[A-Za-z0-9+\\/=]+', 1700000000, 101, 0, 0, '" . $result['generation'] . "'\)$/",
            $inserts[0]
        );
        preg_match("/'(v1:[^']+)'/", $inserts[0], $m);
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $crypto->open($m[1], XoopsTwoFactorCrypto::rowAad(10, 'totp')), 'sealed under the row domain');

        $this->assertCount(1, $this->statements('UPDATE `xoops_tokens`'), 'previous codes revoked once');
        $tokenInserts = $this->statements('INSERT INTO `xoops_tokens`');
        $this->assertCount(10, $tokenInserts);
        foreach ($result['codes'] as $i => $code) {
            $this->assertStringContainsString("VALUES (10, '2fa_recovery', '" . hash('sha256', $code) . "', ", $tokenInserts[$i]);
            $this->assertStringEndsWith(', 4294967295, 0)', $tokenInserts[$i], 'no expiry');
        }
        $this->assertSame('START TRANSACTION', $this->sql[0]);
        $this->assertSame('COMMIT', end($this->sql));

        // Disabled row: UPDATE in place.
        $this->sql  = [];
        $this->rows = [$this->row(['state' => 'disabled', 'secret' => null])];
        $result     = $handler->enrol(10, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 101, self::NOW);
        $this->assertIsArray($result);
        $this->assertSame([], $this->statements('INSERT INTO `xoops_user_2fa`'));
        $updates = $this->statements('UPDATE `xoops_user_2fa`');
        $this->assertCount(1, $updates);
        $this->assertMatchesRegularExpression(
            "/^UPDATE `xoops_user_2fa` SET `state` = 'enrolled', `method` = 'totp', `secret` = 'v1:[A-Za-z0-9+\\/=]+', `confirmed_at` = 1700000000, `last_counter` = 101,"
            . " `failed_attempts` = 0, `locked_until` = 0, `generation` = '" . $result['generation'] . "' WHERE `uid` = 10$/",
            $updates[0]
        );

        // Already enrolled (the second tab): refused, nothing written.
        $this->sql  = [];
        $this->rows = [$this->row()];
        $this->assertFalse($handler->enrol(10, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 101, self::NOW));
        $this->assertSame([], $this->statements('INSERT'));
        $this->assertSame([], $this->statements('UPDATE'));
        $this->assertSame('ROLLBACK', end($this->sql));

        // A state this code does not know is never overwritten.
        $this->sql  = [];
        $this->rows = [$this->row(['state' => 'pending'])];
        $this->assertFalse($handler->enrol(10, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 101, self::NOW));
        $this->assertSame([], $this->statements('INSERT'));
        $this->assertSame([], $this->statements('UPDATE'));
        $this->assertSame('ROLLBACK', end($this->sql));

        // No key: cannot seal, refused.
        $this->sql  = [];
        $this->rows = [false];
        $this->assertFalse($this->handler($this->crypto(false), true, $this->db())->enrol(10, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 101, self::NOW));
        $this->assertSame([], $this->statements('INSERT'));
        $this->assertSame('ROLLBACK', end($this->sql));
    }

    #[Test]
    public function disableNullsTheSecretRevokesCodesAndRotatesTheGeneration(): void
    {
        $handler    = $this->handler();
        $this->rows = [$this->row()];

        $generation = $handler->disable(10);
        $this->assertIsString($generation);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $generation);
        $this->assertNotSame(self::GEN, $generation);
        $this->assertSame(
            ["UPDATE `xoops_user_2fa` SET `state` = 'disabled', `secret` = NULL, `generation` = '{$generation}' WHERE `uid` = 10"],
            $this->statements('UPDATE `xoops_user_2fa`')
        );
        $revokes = $this->statements('UPDATE `xoops_tokens`');
        $this->assertCount(1, $revokes);
        $this->assertStringEndsWith("WHERE `uid` = 10 AND `scope` = '2fa_recovery' AND `used_at` = 0", $revokes[0]);
        $this->assertSame('COMMIT', end($this->sql));

        $this->sql  = [];
        $this->rows = [false];
        $this->assertFalse($handler->disable(10));
        $this->assertSame([], $this->statements('UPDATE'));
        $this->assertSame('ROLLBACK', end($this->sql));

        $this->sql        = [];
        $this->rows       = [$this->row()];
        $this->execResult = static fn (string $s): bool => !str_starts_with($s, 'UPDATE `xoops_tokens`');
        $this->assertFalse($handler->disable(10));
        $this->assertSame('ROLLBACK', end($this->sql));
    }

    #[Test]
    public function regenerateRecoveryCodesRevokesThenIssuesTenWithoutTouchingTheGeneration(): void
    {
        $handler    = $this->handler();
        $this->rows = [$this->row()];

        $codes = $handler->regenerateRecoveryCodes(10);
        $this->assertIsArray($codes);
        $this->assertCount(10, $codes);
        $this->assertCount(1, $this->statements('UPDATE `xoops_tokens`'));
        $this->assertCount(10, $this->statements('INSERT INTO `xoops_tokens`'));
        $this->assertSame([], $this->statements('UPDATE `xoops_user_2fa`'));
        $this->assertSame('COMMIT', end($this->sql));

        $this->sql  = [];
        $this->rows = [$this->row(['state' => 'disabled'])];
        $this->assertFalse($handler->regenerateRecoveryCodes(10));
        $this->assertSame([], $this->statements('INSERT'));
        $this->assertSame('ROLLBACK', end($this->sql));
    }

    #[Test]
    public function deleteByUidPinsItsStatement(): void
    {
        $handler = $this->handler();
        $this->assertTrue($handler->deleteByUid(10));
        $this->assertSame(['DELETE FROM `xoops_user_2fa` WHERE `uid` = 10'], $this->sql);

        $this->execResult = false;
        $this->assertFalse($handler->deleteByUid(10));
    }

    #[Test]
    public function newRecoveryCodeAndNewGenerationHaveTheirShapes(): void
    {
        $handler = $this->handler();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{26}$/', $handler->newRecoveryCode());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $handler->newGeneration());
        $this->assertNotSame($handler->newGeneration(), $handler->newGeneration());
        $this->assertSame('ABCDEFGH', $handler->canonicalRecoveryCode(" ab cd\nef\tgh "));
    }

    /* ---------------------------------------------------------------- */
    /* policy, row state, escape hatch                                   */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function policyIsOffWhenAbsentAndFallsBackToOptionalForUnknownValues(): void
    {
        $this->assertSame('off', XoopsUser2faHandler::policy([]));
        $this->assertSame('off', XoopsUser2faHandler::policy(['twofactor_mode' => 'off']));
        $this->assertSame('optional', XoopsUser2faHandler::policy(['twofactor_mode' => 'optional']));
        $this->assertSame('optional', XoopsUser2faHandler::policy(['twofactor_mode' => 'required']));
        $this->assertSame('optional', XoopsUser2faHandler::policy(['twofactor_mode' => 'OFF']));
    }

    #[Test]
    public function aChallengeIsRequiredOnlyWhenPolicyIsOnAndAFactorExists(): void
    {
        $this->assertFalse(XoopsUser2faHandler::mustChallenge('off', 'enrolled'));
        $this->assertFalse(XoopsUser2faHandler::mustChallenge('off', 'unavailable'));
        $this->assertFalse(XoopsUser2faHandler::mustChallenge('optional', 'none'));
        $this->assertTrue(XoopsUser2faHandler::mustChallenge('optional', 'enrolled'));
        $this->assertTrue(XoopsUser2faHandler::mustChallenge('optional', 'unavailable'));
    }

    #[Test]
    public function stateOfRowMapsAbsentDisabledEnrolledAndBroken(): void
    {
        $handler = $this->handler();
        $this->assertSame('none', $handler->stateOfRow(null));
        $this->assertSame('none', $handler->stateOfRow($this->row(['state' => 'disabled', 'secret' => null])));
        $this->assertSame('unavailable', $handler->stateOfRow($this->row(['secret' => 'v1:garbage'])));
        $this->assertSame('unavailable', $handler->stateOfRow($this->row(['method' => 'sms'])));
        $this->assertSame([], $this->sql);
    }

    private function hatchDir(): string
    {
        $dir = $this->dir . DIRECTORY_SEPARATOR . 'hatch-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);

        return $dir;
    }

    #[Test]
    public function theEscapeHatchIsConsumedOnceAndDisablesTheRow(): void
    {
        $dir = $this->hatchDir();
        file_put_contents($dir . '/2fa-reset-7.php', "<?php\nreturn true;\n");
        $handler    = $this->handler();
        $this->rows = [$this->row(['uid' => 7])];

        $this->assertTrue(@$handler->resetByEscapeHatch(7, $dir));
        $this->assertFileDoesNotExist($dir . '/2fa-reset-7.php');
        $this->assertFileExists($dir . '/2fa-reset-7.used');
        $this->assertCount(1, $this->statements('UPDATE `xoops_user_2fa`'));
        $this->assertStringContainsString('`uid` = 7', $this->statements('UPDATE `xoops_user_2fa`')[0]);

        // second use: the .used twin refuses even after the operator drops a fresh file
        $this->sql = [];
        file_put_contents($dir . '/2fa-reset-7.php', "<?php\nreturn true;\n");
        $this->assertFalse($handler->resetByEscapeHatch(7, $dir));
        $this->assertFileExists($dir . '/2fa-reset-7.php');
        $this->assertSame([], $this->sql);
    }

    #[Test]
    public function theEscapeHatchRefusesAMissingFileAFalseFileAndAnotherUidsFile(): void
    {
        $dir     = $this->hatchDir();
        $handler = $this->handler();
        $this->assertFalse($handler->resetByEscapeHatch(7, $dir));
        file_put_contents($dir . '/2fa-reset-7.php', "<?php\nreturn false;\n");
        $this->assertFalse($handler->resetByEscapeHatch(7, $dir));
        $this->assertFileExists($dir . '/2fa-reset-7.php');
        file_put_contents($dir . '/2fa-reset-8.php', "<?php\nreturn true;\n");
        $this->assertFalse($handler->resetByEscapeHatch(7, $dir));
        $this->assertFalse($handler->resetByEscapeHatch(7, $dir . '/does-not-exist'));
        $this->assertSame([], $this->sql);
    }

    #[Test]
    public function theEscapeHatchRefusesASymlink(): void
    {
        $dir = $this->hatchDir();
        file_put_contents($dir . '/real.php', "<?php\nreturn true;\n");
        if (!@symlink($dir . '/real.php', $dir . '/2fa-reset-7.php')) {
            $this->markTestSkipped('symlink() not permitted here');
        }
        $this->assertFalse($this->handler()->resetByEscapeHatch(7, $dir));
        $this->assertFileExists($dir . '/2fa-reset-7.php');
    }
}
