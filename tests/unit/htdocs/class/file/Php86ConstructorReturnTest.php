<?php

declare(strict_types=1);

namespace xoopsfile;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use XoopsFileHandler;

/**
 * PHP 8.6 pin for the constructor-return fix (compat/php86-constructor-returns).
 *
 * PHP 8.6 deprecates returning a value from __construct() at compile time;
 * bare return stays legal and new never delivered these values. `new` cannot
 * observe the change, so this test invokes __construct() directly: before the
 * fix the early exit returned false, after it the call yields null - making
 * the fix red/green provable rather than merely token-scanned.
 *
 * Only the XoopsFileHandler site is pinned directly: the other three fixed
 * constructors (XoopsMediaUploader, XoopsFormDhtmlTextArea,
 * XoopsFormSelectUser) depend on globals or bundled data tables that make a
 * direct __construct() call impractical here. They are covered by the
 * repository-wide token scan (7 value-returns before the fix, 0 after) and by
 * the existing tests that construct them via new.
 */
final class Php86ConstructorReturnTest extends TestCase
{
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
        try {
            $handler = (new ReflectionClass(XoopsFileHandler::class))->newInstanceWithoutConstructor();

            $this->assertNull($handler->__construct($path, false));
        } finally {
            @unlink($path);
        }
    }
}
