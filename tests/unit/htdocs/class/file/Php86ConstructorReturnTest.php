<?php

declare(strict_types=1);

namespace xoopsfile;

use PHPUnit\Framework\TestCase;
use testsupport\ConstructorReturnScanner;
use ReflectionClass;
use XoopsFile;
use XoopsFileHandler;

/**
 * PHP 8.6 pins for the constructor-return fix (compat/php86-constructor-returns).
 *
 * PHP 8.6 deprecates returning a value from __construct() at compile time;
 * bare return stays legal and new never delivered these values. `new` cannot
 * observe the change, so the behavioral pin invokes __construct() directly:
 * before the fix the early exit returned false, after it the call yields
 * null - red/green provable rather than merely token-scanned.
 *
 * Only the XoopsFileHandler site is pinned behaviorally: the other three
 * fixed constructors (XoopsMediaUploader, XoopsFormDhtmlTextArea,
 * XoopsFormSelectUser) depend on globals or bundled data tables that make a
 * direct __construct() call impractical here. For those, the token-scan test
 * below is the regression pin: it parses all four fixed files and fails on
 * any value-carrying return inside __construct(), ignoring returns that
 * belong to closures nested in a constructor body.
 */
final class Php86ConstructorReturnTest extends TestCase
{
    /** @var string[] the four files fixed by this branch, relative to XOOPS_ROOT_PATH */
    private const FIXED_FILES = [
        'class/file/file.php',
        'class/uploader.php',
        'class/xoopsform/formdhtmltextarea.php',
        'class/xoopsform/formselectuser.php',
    ];

    protected function setUp(): void
    {
        // The global class is not autoloadable by namespace; load it the same
        // way XoopsFileHandlerTest does, so this file passes when run alone.
        XoopsFile::load('file');
    }

    /** The create=false early exit for a missing file must yield no value. */
    public function testMissingFileEarlyExitYieldsNoValue(): void
    {
        $handler = (new ReflectionClass(XoopsFileHandler::class))->newInstanceWithoutConstructor();

        $result = $handler->__construct(
            sys_get_temp_dir() . '/php86-ctor-' . bin2hex(random_bytes(6)) . '/missing.txt',
            false
        );

        $this->assertNull($result, 'the early exit must not return a value (PHP 8.6 deprecation)');
    }

    /** The normal fall-through path yields no value either. */
    public function testExistingFileConstructionYieldsNoValue(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'php86ctor');
        $this->assertNotFalse($path, 'tempnam() must provide a fixture file');
        try {
            $handler = (new ReflectionClass(XoopsFileHandler::class))->newInstanceWithoutConstructor();

            $this->assertNull($handler->__construct($path, false));
        } finally {
            if (is_file($path)) {
                $this->assertTrue(unlink($path), 'cleanup must remove the fixture file');
            }
        }
    }

    /** Static pin for all four fixed files: no value-carrying return in __construct(). */
    public function testNoConstructorInTheFixedFilesReturnsAValue(): void
    {
        foreach (self::FIXED_FILES as $relativePath) {
            $source = file_get_contents(XOOPS_ROOT_PATH . '/' . $relativePath);
            $this->assertIsString($source, $relativePath . ' must be readable');

            $violations = ConstructorReturnScanner::scan($source);

            $this->assertSame(
                [],
                $violations,
                $relativePath . ': __construct() must not return a value (PHP 8.6 deprecation)'
            );
        }
    }

}
