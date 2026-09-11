<?php
/**
 * XOOPS two-factor site key and secret cipher
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

use Xmf\Key\FileStorage;

/**
 * The 2FA site key and the cipher for TOTP secrets.
 *
 * One 32-byte XChaCha20-Poly1305 key per site, stored by Xmf FileStorage
 * under the name 'twofactor'. Provisioning is serialised with flock() on a
 * stable lock file and never replaces a key while encrypted rows exist.
 * Blobs are 'v1:' + base64(nonce || ciphertext); the associated data binds
 * a blob to one row or to one pending enrolment session.
 *
 * @category  Kernel
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsTwoFactorCrypto
{
    public const KEY_NAME  = 'twofactor';
    public const KEY_BYTES = 32;
    public const PREFIX    = 'v1:';

    /**
     * @param FileStorage $storage  key storage (XOOPS_VAR_PATH/data in production)
     * @param string      $lockFile stable lock file path for provisioning
     */
    public function __construct(private readonly FileStorage $storage, private readonly string $lockFile)
    {
    }

    /**
     * @param int    $uid    account
     * @param string $method factor method ('totp')
     *
     * @return string associated data for a stored row blob
     */
    public static function rowAad(int $uid, string $method): string
    {
        return 'row:' . $uid . ':' . $method;
    }

    /**
     * @param int $uid account
     *
     * @return string associated data for a secret held in the enrolling session
     */
    public static function pendingAad(int $uid): string
    {
        return 'pending:' . $uid;
    }

    /**
     * @return bool whether the sodium extension is loaded
     */
    public function isAvailable(): bool
    {
        return extension_loaded('sodium');
    }

    /**
     * @return bool whether a key file exists (usable or not)
     */
    public function hasKey(): bool
    {
        return $this->storage->exists(self::KEY_NAME);
    }

    /**
     * Read the key: exists, strict base64, exactly 32 bytes; anything else is null.
     *
     * The include runs under a temporary error handler so a warning carrying
     * a filesystem path never reaches a page; the failure is reported without it.
     *
     * @return string|null 32 raw bytes, or null when unavailable
     */
    public function loadKey(): ?string
    {
        if (!$this->isAvailable() || !$this->hasKey()) {
            return null;
        }
        $key = $this->readKey();
        if (null === $key) {
            trigger_error('XoopsTwoFactorCrypto: key file unreadable or malformed', E_USER_WARNING);
        }

        return $key;
    }

    /**
     * The key file's contents when they decode to a key, without reporting.
     *
     * @return string|null
     */
    private function readKey(): ?string
    {
        set_error_handler(static fn (): bool => true);
        try {
            $stored = $this->storage->fetch(self::KEY_NAME);
        } catch (\Throwable) {
            $stored = false;
        } finally {
            restore_error_handler();
        }
        $key = is_string($stored) ? base64_decode($stored, true) : false;

        return (is_string($key) && strlen($key) === self::KEY_BYTES) ? $key : null;
    }

    /**
     * Create the site key if no usable one exists.
     *
     * A key file that does not decode (a crash during the first write) is
     * replaced the same way as a missing one, but only while no user_2fa row
     * holds a secret: a lost key is never replaced while one does.
     *
     * ponytail: FileStorage::save() writes the final file in place, so a reader
     * can see the file between creation and completion during the site's one
     * first provisioning and report the factor unavailable for that request.
     * Publish atomically (temp file + rename) once FileStorage supports it.
     *
     * @param bool|callable $encryptedRowsExist callback checked under the provisioning lock, or a known fixed result
     *
     * @return bool true when a usable key exists afterwards
     */
    public function provisionKey(bool|callable $encryptedRowsExist): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        if ($this->hasKey() && null !== $this->readKey()) {
            return true;
        }
        if (true === $encryptedRowsExist) {
            return false;
        }
        set_error_handler(static fn (): bool => true);
        try {
            $lock = fopen($this->lockFile, 'c');
        } finally {
            restore_error_handler();
        }
        if (false === $lock) {
            return false;
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                return false;
            }
            if (!$this->hasKey() || null === $this->readKey()) {
                if (is_callable($encryptedRowsExist) && $encryptedRowsExist()) {
                    return false;
                }
                $key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
                // Same rule as loadKey(): a write warning carries the key
                // file's path, which must not reach a page.
                set_error_handler(static fn (): bool => true);
                try {
                    $saved = $this->storage->save(self::KEY_NAME, base64_encode($key));
                } finally {
                    restore_error_handler();
                }
                if (!$saved) {
                    trigger_error('XoopsTwoFactorCrypto: key file could not be written', E_USER_WARNING);

                    return false;
                }
            }
            flock($lock, LOCK_UN);
        } finally {
            fclose($lock);
        }

        return null !== $this->loadKey();
    }

    /**
     * @param string $plain plaintext
     * @param string $aad   associated data (rowAad() or pendingAad())
     *
     * @return string|null 'v1:' + base64(nonce || ciphertext), or null without a key
     */
    public function seal(string $plain, string $aad): ?string
    {
        $key = $this->loadKey();
        if (null === $key) {
            return null;
        }
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $aad, $nonce, $key));
    }

    /**
     * @param string $blob stored blob
     * @param string $aad  associated data it was sealed under
     *
     * @return string|null plaintext, or null on any failure
     */
    public function open(string $blob, string $aad): ?string
    {
        if (!str_starts_with($blob, self::PREFIX)) {
            return null;
        }
        $key = $this->loadKey();
        if (null === $key) {
            return null;
        }
        $raw         = base64_decode(substr($blob, strlen(self::PREFIX)), true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (!is_string($raw) || strlen($raw) < $nonceLength + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            return null;
        }
        try {
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, $nonceLength), $aad, substr($raw, 0, $nonceLength), $key);
        } catch (\SodiumException) {
            return null;
        }

        return false === $plain ? null : $plain;
    }
}
