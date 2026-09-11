<?php
/**
 * Unit tests for XoopsTwoFactorCrypto
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

namespace xoopsclass;

use kernel\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Xmf\Key\FileStorage;
use XoopsTwoFactorCrypto;

/**
 * Pins the site key file to single provisioning under a lock, strict reading,
 * and refusal to replace a lost key while encrypted rows exist; and the cipher
 * to its associated-data binding.
 *
 * @category  Test
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(XoopsTwoFactorCrypto::class)]
class XoopsTwoFactorCryptoTest extends KernelTestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('ext-sodium is required');
        }
        require_once XOOPS_ROOT_PATH . '/class/XoopsTwoFactorCrypto.php';
        $this->dir = sys_get_temp_dir() . '/xoops2fa-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        // tearDown runs after a skipped setUp too: only touch our own directory.
        if ('' !== $this->dir && is_dir($this->dir)) {
            foreach ((array) glob($this->dir . '/*') as $file) {
                unlink($file);
            }
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    private function storage(): FileStorage
    {
        return new FileStorage($this->dir, 'test');
    }

    private function crypto(): XoopsTwoFactorCrypto
    {
        return new XoopsTwoFactorCrypto($this->storage(), $this->dir . '/twofactor.lock');
    }

    #[Test]
    public function provisionWritesOneKeyAndKeepsItOnASecondCall(): void
    {
        $crypto = $this->crypto();
        $this->assertFalse($crypto->hasKey());
        $this->assertTrue($crypto->provisionKey(false));
        $this->assertTrue($crypto->hasKey());
        $first = $crypto->loadKey();
        $this->assertSame(32, strlen((string) $first));

        $this->assertTrue($crypto->provisionKey(false));
        $this->assertSame($first, $crypto->loadKey());
    }

    #[Test]
    public function encryptedRowCallbackRunsUnderTheProvisioningLock(): void
    {
        $crypto = $this->crypto();
        $called = false;
        self::assertFalse($crypto->provisionKey(function () use (&$called): bool {
            $called = true;
            $other = fopen($this->dir . '/twofactor.lock', 'c');
            try {
                self::assertFalse(flock($other, LOCK_EX | LOCK_NB));
            } finally {
                fclose($other);
            }
            return true;
        }));
        self::assertTrue($called);
        self::assertFalse($crypto->hasKey());
    }

    #[Test]
    public function provisionRefusesToReplaceALostKeyWhileEncryptedRowsExist(): void
    {
        $crypto = $this->crypto();
        $this->assertFalse($crypto->provisionKey(true));
        $this->assertFalse($crypto->hasKey());
    }

    #[Test]
    public function provisionReplacesAMalformedKeyOnlyWhileNoEncryptedRowsExist(): void
    {
        $crypto = $this->crypto();
        $this->storage()->save(XoopsTwoFactorCrypto::KEY_NAME, 'not base64!');

        $this->assertFalse($crypto->provisionKey(true), 'a malformed key is not replaced while secrets depend on it');
        $this->assertSame('not base64!', $this->storage()->fetch(XoopsTwoFactorCrypto::KEY_NAME));

        $this->assertTrue($crypto->provisionKey(false));
        $this->assertSame(32, strlen((string) $crypto->loadKey()));
    }

    #[Test]
    public function provisionReportsFailureWhenTheLockCannotBeOpened(): void
    {
        $crypto = new XoopsTwoFactorCrypto($this->storage(), $this->dir . '/missing/twofactor.lock');
        $this->assertFalse($crypto->provisionKey(false));
        $this->assertFalse($crypto->hasKey());
    }

    #[Test]
    public function loadKeyIsNullForAMissingMalformedOrShortKey(): void
    {
        $crypto = $this->crypto();
        $this->assertNull($crypto->loadKey());

        $this->storage()->save(XoopsTwoFactorCrypto::KEY_NAME, 'not base64!');
        $this->assertNull(@$crypto->loadKey());

        $this->storage()->save(XoopsTwoFactorCrypto::KEY_NAME, base64_encode(random_bytes(31)));
        $this->assertNull(@$crypto->loadKey());

        $this->storage()->save(XoopsTwoFactorCrypto::KEY_NAME, base64_encode(random_bytes(32)));
        $this->assertSame(32, strlen((string) $crypto->loadKey()));
    }

    #[Test]
    public function sealAndOpenRoundTripUnderTheSameAssociatedData(): void
    {
        $crypto = $this->crypto();
        $crypto->provisionKey(false);
        $blob = $crypto->seal('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', XoopsTwoFactorCrypto::rowAad(7, 'totp'));

        $this->assertIsString($blob);
        $this->assertStringStartsWith('v1:', $blob);
        $this->assertLessThanOrEqual(255, strlen($blob));
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $crypto->open($blob, 'row:7:totp'));
        $this->assertNotSame($blob, $crypto->seal('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'row:7:totp'), 'fresh nonce each time');
    }

    #[Test]
    public function openRefusesAnotherUidAnotherDomainOrATamperedBlob(): void
    {
        $crypto = $this->crypto();
        $crypto->provisionKey(false);
        $blob = (string) $crypto->seal('secret', XoopsTwoFactorCrypto::rowAad(7, 'totp'));

        $this->assertNull($crypto->open($blob, XoopsTwoFactorCrypto::rowAad(8, 'totp')));
        $this->assertNull($crypto->open($blob, XoopsTwoFactorCrypto::pendingAad(7)));
        $this->assertNull($crypto->open('v0:' . substr($blob, 3), 'row:7:totp'));
        $this->assertNull($crypto->open('v1:!!!', 'row:7:totp'));
        $this->assertNull($crypto->open('v1:' . base64_encode('short'), 'row:7:totp'));
        $raw = (string) base64_decode(substr($blob, 3), true);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
        $this->assertNull($crypto->open('v1:' . base64_encode($raw), 'row:7:totp'));
    }

    #[Test]
    public function sealAndOpenAreNullWithoutAKey(): void
    {
        $crypto = $this->crypto();
        $this->assertNull($crypto->seal('secret', 'row:7:totp'));
        $this->assertNull($crypto->open('v1:AAAA', 'row:7:totp'));
    }
}
