<?php
/**
 * Unit tests for the two-factor management paths of XoopsUser2faHandler
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

namespace kernel;

use Xmf\Key\FileStorage;
use XoopsTokenHandler;
use XoopsTotp;
use XoopsTwoFactorCrypto;
use XoopsUser2faHandler;

/**
 * Management paths of XoopsUser2faHandler: enrol, disable, recovery replacement and admin reset
 *
 * @category  Test
 * @package   Kernel
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsTwoFactorManagementHandlerTest extends KernelTestCase
{
    private const GEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const SECRET = 'JBSWY3DPEHPK3PXP';
    private const NOW = 1700000000;
    private array $sql = [];
    private array|false $row = false;
    private string $fail = '';
    private bool $queryFails = false;
    private int $affected = 1;
    private string $dir = '';
    private XoopsTwoFactorCrypto $crypto;

    protected function setUp(): void
    {
        if (!extension_loaded('sodium') || !extension_loaded('mysqli')) {
            self::markTestSkipped('sodium and mysqli required');
        }
        require_once XOOPS_ROOT_PATH . '/kernel/user2fa.php';
        $this->dir = sys_get_temp_dir() . '/xoops2fa-management-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->crypto = new XoopsTwoFactorCrypto(new FileStorage($this->dir, 'test'), $this->dir . '/key.lock');
        self::assertTrue($this->crypto->provisionKey(false));
        $this->row = [
            'uid' => 10, 'state' => 'enrolled', 'method' => 'totp',
            'secret' => $this->crypto->seal(self::SECRET, XoopsTwoFactorCrypto::rowAad(10, 'totp')),
            'confirmed_at' => 1, 'last_counter' => 0, 'failed_attempts' => 0,
            'locked_until' => 0, 'generation' => self::GEN,
        ];
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '') {
            foreach (glob($this->dir . '/*') as $file) {
                unlink($file);
            }
            rmdir($this->dir);
        }
    }

    private function handler(?XoopsTwoFactorCrypto $crypto = null, bool $installed = true): XoopsUser2faHandler
    {
        $db = $this->createMockDatabase();
        $db->method('exec')->willReturnCallback(function (string $sql): bool {
            $this->sql[] = $sql;
            if ($this->fail === 'revoke') {
                return !str_starts_with($sql, 'UPDATE `xoops_tokens`') || str_contains($sql, '`hash`');
            }
            return $this->fail === '' || !str_starts_with($sql, $this->fail);
        });
        $db->method('query')->willReturnCallback(function (string $sql) {
            $this->sql[] = $sql;
            return $this->queryFails ? false : (new \ReflectionClass(\mysqli_result::class))->newInstanceWithoutConstructor();
        });
        $db->method('isResultSet')->willReturnCallback(static fn ($result) => $result instanceof \mysqli_result);
        $db->method('fetchArray')->willReturnCallback(fn () => $this->row);
        $db->method('getAffectedRows')->willReturnCallback(fn () => $this->affected);
        return new XoopsUser2faHandler($db, new XoopsTokenHandler($db), $crypto ?? $this->crypto, $installed);
    }

    private function code(): string
    {
        return XoopsTotp::codeAt(self::SECRET, XoopsTotp::stepAt(self::NOW));
    }

    public function testEncryptedSecretProbeHandlesPresenceAbsenceAndQueryFailure(): void
    {
        self::assertTrue($this->handler()->hasEncryptedSecrets());
        self::assertStringContainsString('`secret` IS NOT NULL', $this->sql[0]);
        self::assertStringContainsString('LIMIT 1', $this->sql[0]);
        $this->row = false;
        self::assertFalse($this->handler()->hasEncryptedSecrets());
        $this->queryFails = true;
        self::assertFalse($this->handler(installed: false)->hasEncryptedSecrets());
        $this->expectException(\RuntimeException::class);
        $this->handler()->hasEncryptedSecrets();
    }

    public function testEnrolmentRejectsStaleGenerationBeforeEncryptingOrWriting(): void
    {
        $this->row['state'] = 'disabled';
        self::assertFalse($this->handler()->enrol(10, self::SECRET, 1, self::NOW, str_repeat('b', 32)));
        self::assertCount(3, $this->sql);
        self::assertSame('ROLLBACK', $this->sql[2]);
        $this->sql = [];
        $this->row = false;
        self::assertFalse($this->handler()->enrol(10, self::SECRET, 1, self::NOW, self::GEN));
        self::assertCount(3, $this->sql);
        self::assertIsArray($this->handler()->enrol(10, self::SECRET, 1, self::NOW, ''));
    }

    public function testTotpDisableHoldsOneLockUntilMutationCommits(): void
    {
        $generation = $this->handler()->manage(10, self::GEN, $this->code(), '', 'disable', self::NOW);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $generation);
        self::assertNotSame(self::GEN, $generation);
        self::assertSame('START TRANSACTION', $this->sql[0]);
        self::assertStringEndsWith('FOR UPDATE', $this->sql[1]);
        self::assertStringContainsString('SET `last_counter`', $this->sql[2]);
        self::assertStringContainsString("SET `state` = 'disabled', `secret` = NULL", $this->sql[3]);
        self::assertStringContainsString('UPDATE `xoops_tokens` SET `used_at`', $this->sql[4]);
        self::assertSame('COMMIT', $this->sql[5]);
    }

    public function testRecoveryRegenerationDoesNotNeedEncryptionKeyAndReturnsTenFreshCodes(): void
    {
        $missingKey = new XoopsTwoFactorCrypto(new FileStorage($this->dir, 'missing'), $this->dir . '/missing.lock');
        $this->row['locked_until'] = self::NOW + 1000;
        $codes = $this->handler($missingKey)->manage(10, self::GEN, '', ' ab cd ', 'regenerate', self::NOW);
        self::assertCount(10, $codes);
        self::assertCount(10, array_unique($codes));
        self::assertStringContainsString('UPDATE `xoops_tokens`', $this->sql[2]);
        self::assertStringContainsString(hash('sha256', 'ABCD'), $this->sql[2]);
        self::assertStringContainsString('SET `failed_attempts` = 0, `locked_until` = 0', $this->sql[3]);
        self::assertSame('COMMIT', end($this->sql));
        self::assertSame(1, count(array_filter($this->sql, static fn ($sql) => $sql === 'START TRANSACTION')));
    }

    public function testBadCodeStaleGenerationLockAndUnknownStateCannotMutate(): void
    {
        $original = $this->row;
        foreach (['badcode', 'generation', 'locked', 'disabled', 'method', 'replay'] as $case) {
            $this->row = $original;
            $this->sql = [];
            $code = $this->code();
            if ($case === 'badcode') {
                $code = 'invalid';
            }
            if ($case === 'generation') {
                $this->row['generation'] = str_repeat('b', 32);
            }
            if ($case === 'locked') {
                $this->row['locked_until'] = self::NOW + 1;
            }
            if ($case === 'disabled') {
                $this->row['state'] = 'disabled';
            }
            if ($case === 'method') {
                $this->row['method'] = 'unknown';
            }
            if ($case === 'replay') {
                $this->row['last_counter'] = XoopsTotp::stepAt(self::NOW) + 1;
            }
            self::assertFalse($this->handler()->manage(10, self::GEN, $code, '', 'disable', self::NOW), $case);
            self::assertCount(3, $this->sql, $case);
            self::assertSame('ROLLBACK', end($this->sql));
        }
    }

    public function testRecoveryConsumptionFailureRollsBackAndReturnsFalse(): void
    {
        $this->affected = 0;
        self::assertFalse($this->handler()->manage(10, self::GEN, '', 'BAD', 'disable', self::NOW));
        self::assertCount(4, $this->sql);
        self::assertSame('ROLLBACK', end($this->sql));
    }

    public function testMissingKeyIsInfrastructureFailureRatherThanBadCode(): void
    {
        $missingKey = new XoopsTwoFactorCrypto(new FileStorage($this->dir, 'missing'), $this->dir . '/missing.lock');
        $thrown = false;
        try {
            $this->handler($missingKey)->manage(10, self::GEN, $this->code(), '', 'disable', self::NOW);
        } catch (\RuntimeException $e) {
            $thrown = true;
            self::assertSame('Two-factor verification unavailable', $e->getMessage());
            self::assertCount(3, $this->sql);
            self::assertSame('ROLLBACK', end($this->sql));
        }
        self::assertTrue($thrown, 'Missing key must throw');
    }

    public function testDisableMutationAndRevocationFailuresRollBackAcceptedTotp(): void
    {
        foreach (["UPDATE `xoops_user_2fa` SET `state`", 'revoke'] as $failure) {
            $this->fail = $failure;
            $this->sql = [];
            $thrown = false;
            try {
                $this->handler()->manage(10, self::GEN, $this->code(), '', 'disable', self::NOW);
            } catch (\RuntimeException $e) {
                $thrown = true;
                self::assertStringContainsString('SET `last_counter`', $this->sql[2]);
                self::assertSame('ROLLBACK', end($this->sql));
                self::assertNotContains('COMMIT', $this->sql);
            }
            self::assertTrue($thrown, 'Mutation failure must throw');
        }
    }

    public function testEveryManagementStorageFailureThrowsAndRollsBack(): void
    {
        foreach (['START TRANSACTION', 'UPDATE `xoops_tokens`', 'UPDATE `xoops_user_2fa`', 'revoke', 'INSERT INTO `xoops_tokens`', 'COMMIT'] as $failure) {
            $this->fail = $failure;
            $this->sql = [];
            $thrown = false;
            try {
                $this->handler()->manage(10, self::GEN, '', 'GOOD', 'regenerate', self::NOW);
            } catch (\RuntimeException $e) {
                $thrown = true;
                self::assertSame($failure === 'START TRANSACTION' ? $failure : 'ROLLBACK', end($this->sql));
                $beforeRollback = array_slice($this->sql, 0, -1);
                if ('COMMIT' === $failure) {
                    self::assertSame('COMMIT', end($beforeRollback), 'the refused COMMIT is the last statement before the rollback');
                } else {
                    self::assertNotContains('COMMIT', $beforeRollback);
                }
            }
            self::assertTrue($thrown, 'Storage failure was not thrown: ' . $failure);
        }
    }
}
