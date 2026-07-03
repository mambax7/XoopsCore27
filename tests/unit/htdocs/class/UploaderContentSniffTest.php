<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/uploader.php';
require_once XOOPS_ROOT_PATH . '/language/english/uploader.php';

/**
 * Exposes checkMimeType() without the XOOPS-bootstrap-dependent constructor
 * (the real constructor loads the MIME map via $GLOBALS['xoops']).
 */
class UploaderContentSniffProbe extends \XoopsMediaUploader
{
    public function __construct()
    {
        // Intentionally skip the parent constructor.
    }
}

/**
 * Verifies the finfo content-sniffing added to XoopsMediaUploader::checkMimeType()
 * (SECURITY.md M-12): a file whose real bytes are PHP/HTML must be rejected even
 * when the client-supplied type and extension claim it is an image.
 */
#[CoversClass(\XoopsMediaUploader::class)]
class UploaderContentSniffTest extends TestCase
{
    private string $tmp = '';

    protected function tearDown(): void
    {
        if ('' !== $this->tmp && is_file($this->tmp)) {
            unlink($this->tmp);
        }
    }

    private function makeProbe(string $bytes): UploaderContentSniffProbe
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        $this->assertNotFalse($tmp, 'tempnam() failed to create a temp file');
        $this->assertNotFalse(file_put_contents($tmp, $bytes), 'failed to write temp fixture');
        $this->tmp = $tmp;

        $probe                = new UploaderContentSniffProbe();
        $probe->mediaTmpName  = $this->tmp;
        $probe->mediaType     = 'image/png'; // what a malicious client would claim
        $probe->mediaRealType = 'image/png'; // extension-derived
        return $probe;
    }

    #[Test]
    public function phpContentDisguisedAsImageIsRejected(): void
    {
        if (!class_exists('finfo')) {
            $this->markTestSkipped('fileinfo extension not available');
        }
        $probe = $this->makeProbe("<?php echo 'pwned'; ?>\n");
        $this->assertFalse($probe->checkMimeType());
    }

    #[Test]
    public function htmlContentDisguisedAsImageIsRejected(): void
    {
        if (!class_exists('finfo')) {
            $this->markTestSkipped('fileinfo extension not available');
        }
        $probe = $this->makeProbe('<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');
        $this->assertFalse($probe->checkMimeType());
    }

    #[Test]
    public function genuinePngIsAccepted(): void
    {
        if (!class_exists('finfo')) {
            $this->markTestSkipped('fileinfo extension not available');
        }
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }
        $img = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        $probe                   = $this->makeProbe($png);
        $probe->allowedMimeTypes = ['image/png'];
        $this->assertTrue($probe->checkMimeType());
    }
}
