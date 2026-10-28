<?php

declare(strict_types=1);

namespace xoopscache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use XoopsCache;

#[CoversClass(XoopsCache::class)]
class XoopsCacheKeyTest extends TestCase
{
    private XoopsCache $cache;

    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/cache/xoopscache.php';
        $this->cache = new XoopsCache();
    }

    // ---------------------------------------------------------------
    // key() tests
    // ---------------------------------------------------------------

    #[Test]
    public function keyReturnsFalseForEmptyString(): void
    {
        $this->assertFalse($this->cache->key(''));
    }

    #[Test]
    public function keyReturnsFalseForNull(): void
    {
        $this->assertFalse($this->cache->key(null));
    }

    #[Test]
    public function keyReturnsFalseForZero(): void
    {
        // empty(0) is true in PHP
        $this->assertFalse($this->cache->key(0));
    }

    #[Test]
    public function keyReturnsStringForValidInput(): void
    {
        $result = $this->cache->key('test_key');
        $this->assertIsString($result);
        $this->assertSame('test_key', $result);
    }

    #[Test]
    public function keyReplacesSlashWithUnderscore(): void
    {
        $result = $this->cache->key('path/to/key');
        $this->assertSame('path_to_key', $result);
    }

    #[Test]
    public function keyReplacesBackslashWithUnderscore(): void
    {
        // A Windows path separator must not survive into a cache file name.
        $this->assertSame('______x', $this->cache->key('..' . chr(92) . '..' . chr(92) . 'x'));
    }

    #[Test]
    public function fileEngineRefusesKeysThatLeaveItsFolder(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/cache/file.php';
        $dir = sys_get_temp_dir() . '/xoops-cachekey-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $escaped = dirname($dir) . '/escaped.txt';
        if (is_file($escaped)) {
            unlink($escaped);
        }
        try {
            $engine = new \XoopsCacheFile();
            $this->assertTrue($engine->init(['path' => $dir, 'prefix' => '', 'extension' => '.txt']));
            // Engine-level guard, for callers that bypass XoopsCache::key().
            foreach (['../escaped', '..' . chr(92) . 'escaped', 'a/b', "a\0b", ''] as $key) {
                $this->assertFalse($engine->write($key, 'x', 60), var_export($key, true));
            }
            $this->assertTrue($engine->write('plain_key', 'x', 60));
            $this->assertFileExists($dir . '/plain_key.txt');
            $this->assertFileDoesNotExist(dirname($dir) . '/escaped.txt');

            // clear() maps each file back to its key (the extension used to be
            // appended twice, so nothing was ever removed) and leaves guard files.
            file_put_contents($dir . '/index.html', '');
            $this->assertTrue($engine->clear(false));
            $this->assertFileDoesNotExist($dir . '/plain_key.txt');
            $this->assertFileExists($dir . '/index.html');
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    #[Test]
    public function keyReplacesDotWithUnderscore(): void
    {
        $result = $this->cache->key('file.cache');
        $this->assertSame('file_cache', $result);
    }

    #[Test]
    public function keyReplacesMultipleSpecialChars(): void
    {
        $result = $this->cache->key('a/b.c/d.e');
        $this->assertSame('a_b_c_d_e', $result);
    }

    #[Test]
    public function keyPreservesUnderscores(): void
    {
        $result = $this->cache->key('already_safe_key');
        $this->assertSame('already_safe_key', $result);
    }

    #[Test]
    public function keyPreservesDashes(): void
    {
        $result = $this->cache->key('key-with-dashes');
        $this->assertSame('key-with-dashes', $result);
    }

    // ---------------------------------------------------------------
    // isInitialized() tests
    // ---------------------------------------------------------------

    #[Test]
    public function isInitializedReturnsFalseWhenNoEngineSet(): void
    {
        $cache = new XoopsCache();
        $this->assertFalse($cache->isInitialized('nonexistent'));
    }

    #[Test]
    public function isInitializedReturnsBoolForNullEngine(): void
    {
        $cache = new XoopsCache();
        $result = $cache->isInitialized(null);
        $this->assertIsBool($result);
    }

    // ---------------------------------------------------------------
    // settings() tests
    // ---------------------------------------------------------------

    #[Test]
    public function settingsReturnsArrayWhenNoEngine(): void
    {
        $cache = new XoopsCache();
        $result = $cache->settings('nonexistent');
        $this->assertIsArray($result);
    }

    #[Test]
    public function settingsReturnsArrayForNullEngine(): void
    {
        $cache = new XoopsCache();
        $result = $cache->settings(null);
        $this->assertIsArray($result);
    }

    // ---------------------------------------------------------------
    // getInstance tests
    // ---------------------------------------------------------------

    #[Test]
    public function getInstanceReturnsSingleton(): void
    {
        $a = XoopsCache::getInstance();
        $b = XoopsCache::getInstance();
        $this->assertSame($a, $b);
    }

    #[Test]
    public function getInstanceReturnsXoopsCacheType(): void
    {
        $this->assertInstanceOf(XoopsCache::class, XoopsCache::getInstance());
    }
}
