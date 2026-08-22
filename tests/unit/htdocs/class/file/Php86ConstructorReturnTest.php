<?php

declare(strict_types=1);

namespace xoopsfile;

use PHPUnit\Framework\TestCase;
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

            $violations = $this->constructorValueReturns($source);

            $this->assertSame(
                [],
                $violations,
                $relativePath . ': __construct() must not return a value (PHP 8.6 deprecation)'
            );
        }
    }

    /**
     * Token-scan a file for value-carrying returns inside __construct().
     *
     * Closure-aware: a `function` keyword inside a constructor body opens a
     * nested region whose returns are legitimate and ignored - the false
     * positive the naive grep-based sweeps hit during the 8.6 review.
     *
     * @return array<int, string> "line N: return ..." entries, empty when clean
     */
    private function constructorValueReturns(string $source): array
    {
        $tokens     = token_get_all($source);
        $violations = [];
        $inCtor     = false;  // between __construct's opening { and its matching }
        $ctorDepth  = 0;      // brace depth where the constructor body opened
        $depth      = 0;      // current brace depth
        $closures   = [];     // stack of brace depths where nested functions opened
        $expectCtor = false;  // saw "function __construct", waiting for its {

        foreach ($tokens as $index => $token) {
            if (is_array($token) && T_FUNCTION === $token[0]) {
                // Which function follows? Peek for the name.
                for ($j = $index + 1; $j < count($tokens); $j++) {
                    $next = $tokens[$j];
                    if (is_array($next) && (T_WHITESPACE === $next[0] || T_COMMENT === $next[0])) {
                        continue;
                    }
                    if (is_array($next) && T_STRING === $next[0] && '__construct' === strtolower($next[1])) {
                        $expectCtor = true;
                    } elseif ($inCtor) {
                        $closures[] = $depth; // named or anonymous function nested in the ctor
                    }
                    break;
                }
                continue;
            }
            if ('{' === $token || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                if ($expectCtor && !$inCtor) {
                    $inCtor     = true;
                    $ctorDepth  = $depth;
                    $expectCtor = false;
                }
                continue;
            }
            if ('}' === $token) {
                $depth--;
                while ($closures && end($closures) >= $depth) {
                    array_pop($closures);
                }
                if ($inCtor && $depth < $ctorDepth) {
                    $inCtor = false;
                }
                continue;
            }
            if ($inCtor && empty($closures) && is_array($token) && T_RETURN === $token[0]) {
                // A value-carrying return has a non-';' meaningful token next.
                for ($j = $index + 1; $j < count($tokens); $j++) {
                    $next = $tokens[$j];
                    if (is_array($next) && (T_WHITESPACE === $next[0] || T_COMMENT === $next[0] || T_DOC_COMMENT === $next[0])) {
                        continue;
                    }
                    if (';' !== $next) {
                        $violations[] = sprintf('line %d: return with a value', $token[2]);
                    }
                    break;
                }
            }
        }

        return $violations;
    }
}
