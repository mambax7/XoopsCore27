<?php

declare(strict_types=1);

namespace modulessystem;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/modules/system/class/fineuploadhandler.php';
require_once XOOPS_ROOT_PATH . '/modules/system/class/fineimuploadhandler.php';
require_once XOOPS_ROOT_PATH . '/kernel/imagecategory.php';
require_once XOOPS_ROOT_PATH . '/kernel/image.php';

/**
 * The image upload handler enforces the category's byte and pixel limits on the
 * server, and returns the new image id so an editor can insert [img id=N].
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class FineImUploadLimitsTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        unset($_FILES['qqfile']);
    }

    private function category(int $maxSize, int $maxWidth, int $maxHeight, string $store = 'file'): \XoopsImagecategory
    {
        $imgcat = new \XoopsImagecategory();
        $imgcat->assignVar('imgcat_id', 7);
        $imgcat->assignVar('imgcat_maxsize', $maxSize);
        $imgcat->assignVar('imgcat_maxwidth', $maxWidth);
        $imgcat->assignVar('imgcat_maxheight', $maxHeight);
        $imgcat->assignVar('imgcat_storetype', $store);
        return $imgcat;
    }

    private function handler(?\XoopsImagecategory $imgcat): \SystemFineImUploadHandler
    {
        $claims = (object) ['cat' => 7];
        return new class ($claims, $imgcat) extends \SystemFineImUploadHandler {
            private ?\XoopsImagecategory $fixture;

            public function __construct(\stdClass $claims, ?\XoopsImagecategory $fixture)
            {
                $this->fixture = $fixture;
                parent::__construct($claims);
            }

            protected function category(): ?\XoopsImagecategory
            {
                return $this->fixture;
            }
        };
    }

    private function png(int $width, int $height): string
    {
        $file = tempnam(sys_get_temp_dir(), 'imt');
        $this->tempFiles[] = $file;
        // A PNG signature plus IHDR is all getimagesize() reads; no GD needed.
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        file_put_contents($file, "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr)));
        return $file;
    }

    /**
     * @return mixed
     */
    private function call(object $obj, string $method, mixed ...$args)
    {
        return (new \ReflectionMethod($obj, $method))->invoke($obj, ...$args);
    }

    #[Test]
    public function categoryByteLimitBecomesServerSizeLimit(): void
    {
        self::assertSame(1000, $this->handler($this->category(1000, 0, 0))->sizeLimit);
    }

    #[Test]
    public function zeroByteLimitMeansNoLimit(): void
    {
        self::assertNull($this->handler($this->category(0, 0, 0))->sizeLimit);
    }

    #[Test]
    public function byteLimitIsCappedAtPhpUploadLimit(): void
    {
        $handler = $this->handler($this->category(PHP_INT_MAX, 0, 0));
        self::assertNotNull($handler->sizeLimit);
        self::assertLessThan(PHP_INT_MAX, $handler->sizeLimit);
    }

    #[Test]
    public function overWideImageIsRejected(): void
    {
        $imgcat = $this->category(0, 100, 0);
        self::assertNotNull($this->call($this->handler($imgcat), 'dimensionError', $this->png(101, 10), $imgcat));
    }

    #[Test]
    public function overTallImageIsRejected(): void
    {
        $imgcat = $this->category(0, 0, 100);
        self::assertNotNull($this->call($this->handler($imgcat), 'dimensionError', $this->png(10, 101), $imgcat));
    }

    #[Test]
    public function imageWithinLimitsIsAccepted(): void
    {
        $imgcat = $this->category(0, 100, 100);
        self::assertNull($this->call($this->handler($imgcat), 'dimensionError', $this->png(100, 100), $imgcat));
    }

    #[Test]
    public function nonImageIsRejected(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'imt');
        $this->tempFiles[] = $file;
        file_put_contents($file, 'not an image at all');
        $imgcat = $this->category(0, 0, 0);
        self::assertNotNull($this->call($this->handler($imgcat), 'dimensionError', $file, $imgcat));
    }

    #[Test]
    public function missingCategoryIsRejectedBeforeStoring(): void
    {
        $_FILES['qqfile'] = ['tmp_name' => $this->png(10, 10), 'name' => 'a.png'];
        $result = $this->call($this->handler(null), 'storeUploadedFile', '', 'image/png', 'u1');
        self::assertArrayHasKey('error', $result);
    }

    #[Test]
    public function oversizeImageIsRejectedBeforeStoring(): void
    {
        $_FILES['qqfile'] = ['tmp_name' => $this->png(200, 10), 'name' => 'a.png'];
        $result = $this->call($this->handler($this->category(0, 100, 100)), 'storeUploadedFile', '', 'image/png', 'u1');
        self::assertArrayHasKey('error', $result);
        self::assertArrayNotHasKey('success', $result);
    }

    #[Test]
    public function successReturnsTheNewImageId(): void
    {
        $db = new class extends \XoopsTestStubDatabase {
            public function exec(string $sql): bool
            {
                return true;
            }

            public function getInsertId()
            {
                return 42;
            }
        };
        $imageHandler = xoops_getHandler('image');
        $prop = new \ReflectionProperty($imageHandler, 'db');
        $original = $prop->getValue($imageHandler);
        $prop->setValue($imageHandler, $db);

        try {
            $_FILES['qqfile'] = ['tmp_name' => $this->png(10, 10), 'name' => 'my-photo.png'];
            $_REQUEST['qqfilename'] = 'my-photo.png';
            $result = $this->call($this->handler($this->category(0, 100, 100, 'db')), 'storeUploadedFile', '', 'image/png', 'u1');
        } finally {
            $prop->setValue($imageHandler, $original);
            unset($_REQUEST['qqfilename']);
        }

        self::assertTrue($result['success'] ?? false);
        self::assertSame(42, $result['image_id']);
        self::assertSame('my photo', $result['name']);
    }
}
