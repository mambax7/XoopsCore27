<?php
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

declare(strict_types=1);

namespace include_tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * xoops_groupCacheKey(): the group segment of a cache id.
 *
 * @category  Xoops
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright 2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversFunction('xoops_groupCacheKey')]
class GroupCacheKeyTest extends TestCase
{
    /** @var string[] cacheid key files that did not exist before this class ran */
    private static array $createdKeyFiles = [];

    public static function setUpBeforeClass(): void
    {
        require_once XOOPS_ROOT_PATH . '/include/file_safety.php';
        // The first call creates the site's cacheid key through the production
        // FileStorage; remember whether it was there so the checkout is left as found.
        $before = glob(XOOPS_VAR_PATH . '/data/*-key-cacheid.php') ?: [];
        xoops_groupCacheKey([1]);
        $after = glob(XOOPS_VAR_PATH . '/data/*-key-cacheid.php') ?: [];
        self::$createdKeyFiles = array_diff($after, $before);
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$createdKeyFiles as $file) {
            @unlink($file);
        }
    }

    #[Test]
    public function keyIsSixteenHexCharactersAndOrderIndependent(): void
    {
        $key = xoops_groupCacheKey([3, 1, 2]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $key);
        $this->assertSame($key, xoops_groupCacheKey([2, 3, 1]));
        $this->assertSame($key, xoops_groupCacheKey(['1', '2', '3']));
    }

    #[Test]
    public function keyDiffersBetweenGroupSets(): void
    {
        $this->assertNotSame(xoops_groupCacheKey([1]), xoops_groupCacheKey([1, 2]));
    }

    #[Test]
    public function keyIsNotDerivedFromTheDatabaseCredentials(): void
    {
        $groups = [1, 2];
        sort($groups);
        $legacy = substr(md5(implode('-', $groups)), 0, 8) . '-' . substr(md5(XOOPS_DB_PASS . XOOPS_DB_NAME . XOOPS_DB_USER), 0, 8);

        $this->assertNotSame($legacy, xoops_groupCacheKey($groups));
        $this->assertNotSame(substr(md5('1-2'), 0, 16), xoops_groupCacheKey($groups));
    }
}
