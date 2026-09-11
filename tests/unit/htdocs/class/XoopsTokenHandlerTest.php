<?php
/**
 * Unit tests for XoopsTokenHandler
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
 * @since     2.7.0
 */

declare(strict_types=1);

namespace xoopsclass;

use kernel\KernelTestCase;
use XoopsTokenHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for XoopsTokenHandler class.
 *
 * @category  Test
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(XoopsTokenHandler::class)]
class XoopsTokenHandlerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once XOOPS_ROOT_PATH . '/class/XoopsTokenHandler.php';
    }

    /* ========================================================
     * create()
     * ====================================================== */

    #[Test]
    public function testCreateReturnsUrlSafeToken(): void
    {
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturn(true);
        $db->method('getAffectedRows')->willReturn(0);

        $handler  = new XoopsTokenHandler($db);
        $rawToken = $handler->create(1, 'lostpass', 3600, false);

        $this->assertIsString($rawToken);
        $this->assertNotEmpty($rawToken);
        // 32 random bytes → base64url → 43 chars (no padding)
        $this->assertGreaterThanOrEqual(40, strlen($rawToken));
        // Must be URL-safe: no +, /, or =
        $this->assertDoesNotMatchRegularExpression('/[+\\/=]/', $rawToken);
    }

    #[Test]
    public function testCreateReturnsFalseOnDbFailure(): void
    {
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturn(false);

        $handler = new XoopsTokenHandler($db);
        $result  = $handler->create(1, 'lostpass', 3600, false);

        $this->assertFalse($result);
    }

    #[Test]
    public function testCreateRevokesExistingTokensByDefault(): void
    {
        $queries = [];
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$queries) {
            $queries[] = $sql;
            return true;
        });
        $db->method('getAffectedRows')->willReturn(0);

        $handler = new XoopsTokenHandler($db);
        $handler->create(42, 'lostpass');

        // First query should be the UPDATE (revoke), second the INSERT (create)
        $this->assertCount(2, $queries);
        $this->assertStringContainsString('UPDATE', $queries[0]);
        $this->assertStringContainsString('INSERT', $queries[1]);
    }

    #[Test]
    public function testCreateSkipsRevokeWhenFlagIsFalse(): void
    {
        $queries = [];
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$queries) {
            $queries[] = $sql;
            return true;
        });

        $handler = new XoopsTokenHandler($db);
        $handler->create(42, 'lostpass', 3600, false);

        // Only the INSERT query, no revoke UPDATE
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT', $queries[0]);
    }

    #[Test]
    public function testCreateTokensAreUnique(): void
    {
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturn(true);

        $handler = new XoopsTokenHandler($db);
        $tokens  = [];
        for ($i = 0; $i < 50; $i++) {
            $tokens[] = $handler->create(1, 'lostpass', 3600, false);
        }
        $this->assertCount(50, array_unique($tokens), 'Tokens should be unique');
    }

    #[Test]
    public function testCreateEnforcesMinimumTtl(): void
    {
        $capturedSql = '';
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });

        $handler = new XoopsTokenHandler($db);
        $handler->create(1, 'test', 10, false); // 10 seconds, below MIN_TTL of 60

        // The expires_at should be at least now + 60, not now + 10
        $this->assertMatchesRegularExpression('/\d+, \d+, 0\)$/', $capturedSql);
        // Extract the expires_at value from the SQL
        $matchResult = preg_match('/VALUES \(\d+, .+?, .+?, (\d+), (\d+), 0\)/', $capturedSql, $m);
        $this->assertSame(1, $matchResult, 'Failed to extract issuedAt and expiresAt from SQL');
        $issuedAt  = (int)$m[1];
        $expiresAt = (int)$m[2];
        $this->assertGreaterThanOrEqual(60, $expiresAt - $issuedAt);
    }

    #[Test]
    public function testCreateInsertsCorrectScope(): void
    {
        $capturedSql = '';
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });

        $handler = new XoopsTokenHandler($db);
        $handler->create(99, 'activation', 86400, false);

        $this->assertStringContainsString("'activation'", $capturedSql);
        $this->assertStringContainsString('xoops_tokens', $capturedSql);
    }

    #[Test]
    public function testCreateReturnsFalseForInvalidUid(): void
    {
        $db = $this->createMockDatabase();
        $handler = new XoopsTokenHandler($db);

        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings) {
            $warnings[] = $errstr;
            return true;
        }, E_USER_WARNING);

        $this->assertFalse($handler->create(0, 'lostpass', 3600));
        $this->assertFalse($handler->create(-1, 'lostpass', 3600));

        restore_error_handler();

        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('uid > 0', $warnings[0]);
    }

    #[Test]
    public function testCreateReturnsFalseForEmptyScope(): void
    {
        $db = $this->createMockDatabase();
        $handler = new XoopsTokenHandler($db);

        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings) {
            $warnings[] = $errstr;
            return true;
        }, E_USER_WARNING);

        $this->assertFalse($handler->create(1, '', 3600));
        $this->assertFalse($handler->create(1, '   ', 3600));

        restore_error_handler();

        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('non-empty scope', $warnings[0]);
    }

    /* ========================================================
     * verify() — atomic UPDATE approach
     * ====================================================== */

    #[Test]
    public function testVerifyReturnsTrueWhenTokenIsValid(): void
    {
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturn(true);
        $db->method('getAffectedRows')->willReturn(1);

        $handler = new XoopsTokenHandler($db);
        $result  = $handler->verify(1, 'lostpass', 'some-raw-token');

        $this->assertTrue($result);
    }

    #[Test]
    public function testVerifyReturnsFalseWhenNoRowAffected(): void
    {
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturn(true);
        $db->method('getAffectedRows')->willReturn(0);

        $handler = new XoopsTokenHandler($db);
        $result  = $handler->verify(1, 'lostpass', 'wrong-token');

        $this->assertFalse($result);
    }

    #[Test]
    public function testVerifyReturnsFalseOnDbFailure(): void
    {
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturn(false);

        $handler = new XoopsTokenHandler($db);
        $result  = $handler->verify(1, 'lostpass', 'some-token');

        $this->assertFalse($result);
    }

    #[Test]
    public function testVerifyUsesAtomicUpdate(): void
    {
        $capturedSql = '';
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });
        $db->method('getAffectedRows')->willReturn(1);

        $handler = new XoopsTokenHandler($db);
        $handler->verify(1, 'lostpass', 'test-token');

        // Should be a single UPDATE, not a SELECT
        $this->assertStringContainsString('UPDATE', $capturedSql);
        $this->assertStringNotContainsString('SELECT', $capturedSql);
        $this->assertStringContainsString('used_at', $capturedSql);
        $this->assertStringContainsString('expires_at', $capturedSql);
    }

    #[Test]
    public function testVerifyHashesTokenWithSha256(): void
    {
        $capturedSql = '';
        $rawToken = 'my-test-token-abc';
        $expectedHash = hash('sha256', $rawToken);

        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });
        $db->method('getAffectedRows')->willReturn(0);

        $handler = new XoopsTokenHandler($db);
        $handler->verify(1, 'lostpass', $rawToken);

        $this->assertStringContainsString($expectedHash, $capturedSql);
    }

    /* ========================================================
     * revokeByScope()
     * ====================================================== */

    #[Test]
    public function testRevokeByScopeUpdatesUnusedTokens(): void
    {
        $capturedSql = '';
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });

        $handler = new XoopsTokenHandler($db);
        $handler->revokeByScope(42, 'lostpass');

        $this->assertStringContainsString('UPDATE', $capturedSql);
        $this->assertStringContainsString('`used_at` = 0', $capturedSql);
        $this->assertStringContainsString("'lostpass'", $capturedSql);
    }

    /* ========================================================
     * countRecent()
     * ====================================================== */

    #[Test]
    public function testCountRecentReturnsCountFromDb(): void
    {
        $mockResult = $this->createMock(\mysqli_result::class);
        $db = $this->createMockDatabase();
        $db->method('query')->willReturn($mockResult);
        $db->method('isResultSet')->willReturn(true);
        $db->method('fetchArray')->willReturn(['cnt' => '3']);

        $handler = new XoopsTokenHandler($db);
        $count   = $handler->countRecent(1, 'lostpass', 900);

        $this->assertSame(3, $count);
    }

    #[Test]
    public function testCountRecentReturnsZeroOnFailure(): void
    {
        $db = $this->createMockDatabase();
        $db->method('query')->willReturn(false);
        $db->method('isResultSet')->willReturn(false);

        $handler = new XoopsTokenHandler($db);
        $count   = $handler->countRecent(1, 'lostpass', 900);

        $this->assertSame(0, $count);
    }

    #[Test]
    public function testCountRecentReturnsZeroWhenFetchReturnsFalse(): void
    {
        $mockResult = $this->createMock(\mysqli_result::class);
        $db = $this->createMockDatabase();
        $db->method('query')->willReturn($mockResult);
        $db->method('isResultSet')->willReturn(true);
        $db->method('fetchArray')->willReturn(false);

        $handler = new XoopsTokenHandler($db);
        $count   = $handler->countRecent(1, 'lostpass', 900);

        $this->assertSame(0, $count);
    }

    #[Test]
    public function testCountRecentQueriesCorrectScope(): void
    {
        $capturedSql = '';
        $mockResult = $this->createMock(\mysqli_result::class);
        $db = $this->createMockDatabase();
        $db->method('query')->willReturnCallback(function ($sql) use (&$capturedSql, $mockResult) {
            $capturedSql = $sql;
            return $mockResult;
        });
        $db->method('isResultSet')->willReturn(true);
        $db->method('fetchArray')->willReturn(['cnt' => '0']);

        $handler = new XoopsTokenHandler($db);
        $handler->countRecent(42, 'activation', 1800);

        $this->assertStringContainsString('COUNT(*)', $capturedSql);
        $this->assertStringContainsString("'activation'", $capturedSql);
    }

    /* ========================================================
     * purgeExpired()
     * ====================================================== */

    #[Test]
    public function testPurgeExpiredDeletesOldUsedAndExpiredTokens(): void
    {
        $capturedSql = '';
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });

        $handler = new XoopsTokenHandler($db);
        $handler->purgeExpired(604800);

        $this->assertStringContainsString('DELETE FROM', $capturedSql);
        // Must handle both expired and used tokens
        $this->assertStringContainsString('expires_at', $capturedSql);
        $this->assertStringContainsString('used_at', $capturedSql);
        $this->assertStringContainsString('issued_at', $capturedSql);
    }

    #[Test]
    public function testPurgeExpiredIncludesUsedTokensInDeletion(): void
    {
        $capturedSql = '';
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });

        $handler = new XoopsTokenHandler($db);
        $handler->purgeExpired();

        // The query must include OR `used_at` > 0 to clean up consumed tokens
        $this->assertStringContainsString('`used_at` > 0', $capturedSql);
    }

    /* ========================================================
     * End-to-end: create → verify round-trip
     * ====================================================== */

    #[Test]
    public function testEndToEndCreateAndVerifyShareSameHash(): void
    {
        $insertedHash = '';
        $verifiedHash = '';
        $queryCount   = 0;

        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(
            function ($sql) use (&$insertedHash, &$verifiedHash, &$queryCount) {
                $queryCount++;
                // Capture hash from INSERT (create, after revoke)
                if (str_contains($sql, 'INSERT')) {
                    preg_match("/hash.*?'([a-f0-9]{64})'/", $sql, $m);
                    if (!empty($m[1])) {
                        $insertedHash = $m[1];
                    }
                }
                // Capture hash from UPDATE (verify)
                if (str_contains($sql, 'UPDATE') && str_contains($sql, 'expires_at')) {
                    preg_match("/'([a-f0-9]{64})'/", $sql, $m);
                    if (!empty($m[1])) {
                        $verifiedHash = $m[1];
                    }
                }
                return true;
            }
        );
        $db->method('getAffectedRows')->willReturn(1);

        $handler  = new XoopsTokenHandler($db);
        $rawToken = $handler->create(1, 'lostpass', 3600, false);
        $this->assertIsString($rawToken);

        $handler->verify(1, 'lostpass', $rawToken);

        $this->assertNotEmpty($insertedHash);
        $this->assertNotEmpty($verifiedHash);
        $this->assertSame($insertedHash, $verifiedHash, 'create() and verify() must use the same hash');
    }

    /* ========================================================
     * Recovery-code support: caller token, no-expiry ttl,
     * revoke outcome, delete by uid, purge semantics
     * ====================================================== */

    #[Test]
    public function createAcceptsACallerSuppliedTokenAndHashesItAsGiven(): void
    {
        $capturedSql = '';
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });
        $handler = new XoopsTokenHandler($db);

        // lower-case with a space: the handler must not canonicalise
        $raw = 'abcd efgh';
        $this->assertSame($raw, $handler->create(7, '2fa_recovery', null, false, $raw));
        $this->assertStringContainsString("'" . hash('sha256', $raw) . "'", $capturedSql);
        $this->assertStringNotContainsString(hash('sha256', 'ABCDEFGH'), $capturedSql);
    }

    #[Test]
    public function createRefusesAnEmptyCallerToken(): void
    {
        $db = $this->createMockDatabase();
        $db->expects($this->never())->method('exec');
        $handler  = new XoopsTokenHandler($db);
        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings) {
            $warnings[] = $errstr;
            return true;
        }, E_USER_WARNING);
        try {
            $result = $handler->create(7, '2fa_recovery', null, false, '');
        } finally {
            restore_error_handler();
        }
        $this->assertFalse($result);
        $this->assertCount(1, $warnings);
    }

    #[Test]
    public function createWithNullTtlWritesTheNoExpirySentinel(): void
    {
        $capturedSql = '';
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });
        $handler = new XoopsTokenHandler($db);

        $this->assertNotFalse($handler->create(7, '2fa_recovery', null, false));
        $this->assertMatchesRegularExpression('/VALUES \(7, \'2fa_recovery\', \'[0-9a-f]{64}\', \d+, 4294967295, 0\)/', $capturedSql);
    }

    #[Test]
    public function createWithZeroTtlStillAppliesTheMinimum(): void
    {
        $capturedSql = '';
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });
        $handler = new XoopsTokenHandler($db);

        $before = time();
        $this->assertNotFalse($handler->create(7, 'lostpass', 0, false));
        $this->assertSame(1, preg_match('/, (\d+), (\d+), 0\)$/', $capturedSql, $m));
        $this->assertGreaterThanOrEqual($before + 60, (int) $m[2]);
        $this->assertLessThanOrEqual(time() + 60, (int) $m[2]);
    }

    #[Test]
    public function createFailsWhenTheRevokeFails(): void
    {
        $queries = [];
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$queries) {
            $queries[] = $sql;
            return !str_starts_with($sql, 'UPDATE');
        });
        $handler = new XoopsTokenHandler($db);

        $this->assertFalse($handler->create(7, 'lostpass'));
        $this->assertCount(1, $queries);
        $this->assertStringStartsWith('UPDATE', $queries[0]);
    }

    #[Test]
    public function revokeByScopeReturnsTheOutcome(): void
    {
        $ok = $this->createMockDatabase();
        $ok->method('exec')->willReturn(true);
        $this->assertTrue((new XoopsTokenHandler($ok))->revokeByScope(7, 'lostpass'));

        $failed = $this->createMockDatabase();
        $failed->method('exec')->willReturn(false);
        $this->assertFalse((new XoopsTokenHandler($failed))->revokeByScope(7, 'lostpass'));
    }

    #[Test]
    public function aBatchWithRevokePreviousFalseInsertsEveryTokenAndRevokesNothing(): void
    {
        $queries = [];
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$queries) {
            $queries[] = $sql;
            return true;
        });
        $handler = new XoopsTokenHandler($db);

        $hashes = [];
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame("code$i", $handler->create(7, '2fa_recovery', null, false, "code$i"));
            $hashes[] = hash('sha256', "code$i");
        }
        $this->assertCount(10, $queries);
        $this->assertCount(10, array_filter($queries, static fn (string $q): bool => str_starts_with($q, 'INSERT')));
        $this->assertCount(10, array_unique($hashes));
    }

    #[Test]
    public function deleteByUidRemovesEveryScopeAndRefusesAnInvalidUid(): void
    {
        $capturedSql = '';
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use (&$capturedSql) {
            $capturedSql = $sql;
            return true;
        });
        $handler = new XoopsTokenHandler($db);

        $this->assertTrue($handler->deleteByUid(7));
        $this->assertSame('DELETE FROM `xoops_tokens` WHERE `uid` = 7', $capturedSql);

        $failed = $this->createMockDatabase();
        $failed->method('exec')->willReturn(false);
        $this->assertFalse((new XoopsTokenHandler($failed))->deleteByUid(7));

        $untouched = $this->createMockDatabase();
        $untouched->expects($this->never())->method('exec');
        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings) {
            $warnings[] = $errstr;
            return true;
        }, E_USER_WARNING);
        try {
            $result = (new XoopsTokenHandler($untouched))->deleteByUid(0);
        } finally {
            restore_error_handler();
        }
        $this->assertFalse($result);
        $this->assertCount(1, $warnings);
    }

    #[Test]
    public function noExpiryTokensSurvivePurgeWhileUsedAndExpiredOnesAreDeleted(): void
    {
        // The handler's own SQL runs against an in-memory SQLite table with
        // the install DDL's columns, so the predicate is executed, not matched.
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required to execute the handler SQL');
        }
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE xoops_tokens ('
            . 'token_id INTEGER PRIMARY KEY AUTOINCREMENT, uid INTEGER NOT NULL DEFAULT 0,'
            . " scope TEXT NOT NULL DEFAULT '', hash TEXT NOT NULL DEFAULT '',"
            . ' issued_at INTEGER NOT NULL DEFAULT 0, expires_at INTEGER NOT NULL DEFAULT 0,'
            . ' used_at INTEGER NOT NULL DEFAULT 0, UNIQUE (uid, scope, hash))');
        $now      = time();
        $eightDay = $now - 8 * 86400;
        $insert   = $pdo->prepare('INSERT INTO xoops_tokens (uid, scope, hash, issued_at, expires_at, used_at) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([7, '2fa_recovery', hash('sha256', 'CODE-A'), $eightDay, 4294967295, 0]);
        $insert->execute([7, '2fa_recovery', hash('sha256', 'CODE-B'), $eightDay, 4294967295, $now - 1000]);
        $insert->execute([7, 'lostpass', hash('sha256', 'old-link'), $eightDay, $eightDay + 3600, 0]);
        // issued now, expiring this very second: too young to purge, too old to verify
        $insert->execute([7, 'lostpass', hash('sha256', 'edge'), $now, $now, 0]);

        $affected = 0;
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function ($sql) use ($pdo, &$affected) {
            $affected = (int) $pdo->exec($sql);
            return true; // mysqli reports success for a statement that matched no row
        });
        $db->method('getAffectedRows')->willReturnCallback(function () use (&$affected) {
            return $affected;
        });
        $handler = new XoopsTokenHandler($db);

        $handler->purgeExpired(604800);
        $left = $pdo->query('SELECT hash, used_at FROM xoops_tokens ORDER BY token_id')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertSame([
            ['hash' => hash('sha256', 'CODE-A'), 'used_at' => 0],
            ['hash' => hash('sha256', 'edge'), 'used_at' => 0],
        ], $left, 'only the unused no-expiry code and the young row survive');

        // ... and it is still acceptable years later, exactly once.
        $this->assertTrue($handler->verify(7, '2fa_recovery', 'CODE-A'));
        $this->assertFalse($handler->verify(7, '2fa_recovery', 'CODE-A'));
        $this->assertFalse($handler->verify(7, '2fa_recovery', 'code-a'), 'the handler compares the token as given');
        $this->assertFalse($handler->verify(7, 'lostpass', 'edge'), 'expires_at must be strictly in the future');
    }
}
