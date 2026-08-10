<?php
/**
 * Unit tests for LostPassSecurity
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
use LostPassSecurity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for LostPassSecurity class (rate limiting only).
 *
 * Token creation/verification is now handled by XoopsTokenHandler.
 * LostPassSecurity retains rate limiting via XoopsCache and Protector integration.
 *
 * @category  Test
 * @package   core
 * @author    XOOPS Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(LostPassSecurity::class)]
class LostPassSecurityTest extends KernelTestCase
{
    private LostPassSecurity $security;

    /**
     * The IP / identifier pairs these tests feed to isRateLimited().
     *
     * Kept in one place because the cache purge below has to know them. Add a case that
     * uses a new IP or identifier and it belongs here too, or the purge stops covering it.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const RATE_LIMIT_INPUTS = [
        ['127.0.0.1', 'test@example.com'],
        ['127.0.0.1', ''],
        ['192.168.1.1', 'uid:42'],
    ];

    /**
     * Rate-limit state is on DISK, and it outlives the process.
     *
     * isRateLimited() counts attempts in XoopsCache, which the file engine keeps under
     * xoops_data/caches/xoops_cache/. Nothing here ever removed them, so a second run of
     * the suite in the same working copy read the FIRST run's attempts, crossed the limit
     * and returned true where these tests assert false.
     *
     * The failure looks like a bug in whatever you changed most recently, which is what
     * makes it worth fixing rather than documenting: it is a test that accuses the
     * innocent. Purging on the way in as well as on the way out matters -- on the way out
     * alone still leaves a run that was interrupted, or predates this fix, to poison the
     * next one.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::purgeRateLimitCache();
    }

    public static function tearDownAfterClass(): void
    {
        self::purgeRateLimitCache();
        parent::tearDownAfterClass();
    }

    private static function purgeRateLimitCache(): void
    {
        if (! defined('XOOPS_ROOT_PATH')) {
            return;
        }
        if (! class_exists('XoopsCache', false)) {
            $file = XOOPS_ROOT_PATH . '/class/cache/xoopscache.php';
            if (! is_file($file)) {
                return;
            }
            require_once $file;
        }
        if (! class_exists('XoopsCache', false)) {
            return;
        }

        // Mirrors LostPassSecurity::isRateLimited() -- same prefix, same hashing, same
        // normalisation. Duplicated rather than exposed, because widening a private
        // constant's visibility for a test is a worse trade than six lines that a comment
        // ties to their original.
        foreach (self::RATE_LIMIT_INPUTS as [$ip, $identifier]) {
            \XoopsCache::delete('lostpass_rl_ip_' . hash('sha256', $ip));
            $idNorm = strtolower(trim($identifier));
            if ('' !== $idNorm) {
                \XoopsCache::delete('lostpass_rl_id_' . hash('sha256', $idNorm));
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        require_once XOOPS_ROOT_PATH . '/class/LostPassSecurity.php';
        $this->security = new LostPassSecurity();
    }

    /* ========================================================
     * Rate limiting (isRateLimited)
     * ====================================================== */

    #[Test]
    public function testIsRateLimitedReturnsFalseWhenCacheUnavailable(): void
    {
        // With no XoopsCache available, rate limiting should fail-open
        $this->assertFalse($this->security->isRateLimited('127.0.0.1', 'test@example.com'));
    }

    #[Test]
    public function testIsRateLimitedAcceptsEmptyIdentifier(): void
    {
        $this->assertFalse($this->security->isRateLimited('127.0.0.1', ''));
    }

    #[Test]
    public function testIsRateLimitedAcceptsUidIdentifier(): void
    {
        $testIp = sprintf('192.168.%d.%d', 1, 1);
        $this->assertFalse($this->security->isRateLimited($testIp, 'uid:42'));
    }

    /* ========================================================
     * Constructor parameter enforcement
     * ====================================================== */

    #[Test]
    public function testConstructorEnforcesMinimumWindow(): void
    {
        $sec = new LostPassSecurity(window: 10); // below 60 minimum
        $this->assertSame(60, $this->getProtectedProperty($sec, 'window'));
    }

    #[Test]
    public function testConstructorEnforcesMinimumLimits(): void
    {
        $sec = new LostPassSecurity(ipLimit: 0, idLimit: 0);
        $this->assertSame(1, $this->getProtectedProperty($sec, 'ipLimit'));
        $this->assertSame(1, $this->getProtectedProperty($sec, 'idLimit'));
    }

    #[Test]
    public function testConstructorAcceptsCustomValues(): void
    {
        $sec = new LostPassSecurity(window: 1800, ipLimit: 50, idLimit: 10);
        $this->assertSame(1800, $this->getProtectedProperty($sec, 'window'));
        $this->assertSame(50, $this->getProtectedProperty($sec, 'ipLimit'));
        $this->assertSame(10, $this->getProtectedProperty($sec, 'idLimit'));
    }
}
