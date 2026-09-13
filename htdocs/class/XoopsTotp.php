<?php
/**
 * XOOPS RFC 6238 TOTP helper
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

/**
 * Time-based one-time passwords (RFC 6238 over RFC 4226), SHA-1, six digits,
 * thirty-second steps, with base32 (RFC 4648) for the shared secret.
 *
 * Pure arithmetic: no clock, no storage, no randomness except newSecret().
 * The parameters are fixed by the 2FA design and are not preferences.
 *
 * @category  Kernel
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsTotp
{
    public const PERIOD       = 30;
    public const DIGITS       = 6;
    public const WINDOW       = 1;
    public const SECRET_BYTES = 20;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * A fresh 160-bit secret as 32 base32 characters.
     *
     * @return string
     */
    public static function newSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_BYTES));
    }

    /**
     * RFC 4648 base32 without padding.
     *
     * @param string $bytes raw bytes
     *
     * @return string
     */
    public static function base32Encode(string $bytes): string
    {
        $out    = '';
        $buffer = 0;
        $bits   = 0;
        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits  += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out  .= self::ALPHABET[($buffer >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $out .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $out;
    }

    /**
     * Decode base32, tolerating case, whitespace and padding.
     *
     * @param string $text base32 text
     *
     * @return string|false raw bytes, or false when a character is outside the alphabet or nothing decodes
     */
    public static function base32Decode(string $text): string|false
    {
        $clean = strtoupper((string) preg_replace('/[\s=]+/', '', $text));
        if ('' === $clean) {
            return false;
        }
        $out    = '';
        $buffer = 0;
        $bits   = 0;
        foreach (str_split($clean) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if (false === $value) {
                return false;
            }
            $buffer = ($buffer << 5) | $value;
            $bits  += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out  .= chr(($buffer >> $bits) & 255);
            }
        }

        return '' === $out ? false : $out;
    }

    /**
     * @param int $time unix time
     *
     * @return int the step counter for that time
     */
    public static function stepAt(int $time): int
    {
        return intdiv($time, self::PERIOD);
    }

    /**
     * The code for one step (RFC 4226 section 5.3 dynamic truncation).
     *
     * @param string $secretBase32 shared secret in base32
     * @param int    $step         step counter
     *
     * @return string|false six digits, or false when the secret does not decode
     */
    public static function codeAt(string $secretBase32, int $step): string|false
    {
        $secret = self::base32Decode($secretBase32);
        if (false === $secret) {
            return false;
        }
        $hash   = hash_hmac('sha1', pack('J', $step), $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Which step within the window does this code belong to?
     *
     * Only steps above $lastCounter are considered, so a step is accepted
     * once and a code for N-1 is refused after N: the window tolerates
     * clock skew, not reuse. The caller records the returned step.
     *
     * @param string $secretBase32 shared secret in base32
     * @param string $code         code as typed
     * @param int    $now          unix time
     * @param int    $lastCounter  highest step accepted so far
     *
     * @return int|false the matching step, or false
     */
    public static function matchStep(string $secretBase32, string $code, int $now, int $lastCounter): int|false
    {
        if (strlen($code) !== self::DIGITS || !ctype_digit($code)) {
            return false;
        }
        $current = self::stepAt($now);
        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            $step = $current + $offset;
            if ($step <= $lastCounter) {
                continue;
            }
            $expected = self::codeAt($secretBase32, $step);
            if (false === $expected) {
                return false;
            }
            if (hash_equals($expected, $code)) {
                return $step;
            }
        }

        return false;
    }
}
