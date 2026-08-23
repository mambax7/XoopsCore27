<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Convention: no first-party constructor returns a value.
 *
 * PHP 8.6 deprecates returning a value from __construct() at compile time and
 * PHP 9.0 makes it fatal; new never delivered these values anyway. The four
 * historical sites were fixed for 8.6 (see Php86ConstructorReturnTest, which
 * pins them individually); this test widens that pin into a repository-wide
 * guard so the pattern cannot be reintroduced anywhere in first-party code.
 *
 * Bundled third-party code (composer vendor trees) is excluded - those fixes
 * arrive via dependency bumps, not local edits. The scanner is closure-aware:
 * a `function` nested inside a constructor body opens a region whose returns
 * are legitimate and ignored.
 */
final class ConstructorReturnConventionTest extends TestCase
{
    /** @var string[] bundled third-party trees, never hand-edited */
    private const EXCLUDED_PATH_FRAGMENTS = [
        '/class/libraries/vendor/',
        '/xoops_lib/vendor/',
    ];

    /** Every first-party constructor under htdocs/ must yield no value. */
    public function testNoFirstPartyConstructorReturnsAValue(): void
    {
        $violations = [];
        $scanned    = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(XOOPS_ROOT_PATH, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ('php' !== strtolower($file->getExtension())) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            foreach (self::EXCLUDED_PATH_FRAGMENTS as $fragment) {
                if (false !== strpos($path, $fragment)) {
                    continue 2;
                }
            }
            $source = file_get_contents($path);
            // An unreadable file must FAIL the guard, not silently shrink
            // its coverage (review catch).
            $this->assertIsString($source, 'unreadable file: ' . $path);
            // Case-insensitive prefilter: __CONSTRUCT is legal PHP.
            if (false === stripos($source, '__construct')) {
                continue;
            }
            $scanned++;
            foreach ($this->constructorValueReturns($source) as $line) {
                $violations[] = sprintf(
                    '%s:%d returns a value from __construct()',
                    substr($path, strlen(XOOPS_ROOT_PATH) + 1),
                    $line
                );
            }
        }

        $this->assertGreaterThan(0, $scanned, 'the scan must actually cover files');
        $this->assertSame(
            [],
            $violations,
            "PHP 8.6 deprecates (and 9.0 forbids) returning a value from __construct():\n"
            . implode("\n", $violations)
        );
    }

    /**
     * Token-scan a file for value-carrying returns inside __construct().
     *
     * @return array<int, int> source line numbers, empty when clean
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
                for ($j = $index + 1; $j < count($tokens); $j++) {
                    $next = $tokens[$j];
                    // Skip trivia AND the by-ref ampersand of "function &name()"
                    // - stopping at either misclassified the function
                    // (review catches: doc-commented closures, by-ref).
                    if ('&' === $next
                        || (is_array($next) && in_array($next[0], [
                            T_WHITESPACE,
                            T_COMMENT,
                            T_DOC_COMMENT,
                            T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                            T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
                        ], true))) {
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
            if (';' === $token && $expectCtor) {
                // Bodyless declaration (interface / abstract): there is no
                // constructor body - the next brace belongs to something
                // else (review catch).
                $expectCtor = false;
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
                for ($j = $index + 1; $j < count($tokens); $j++) {
                    $next = $tokens[$j];
                    if (is_array($next) && (T_WHITESPACE === $next[0] || T_COMMENT === $next[0] || T_DOC_COMMENT === $next[0])) {
                        continue;
                    }
                    if (';' !== $next) {
                        $violations[] = (int) $token[2];
                    }
                    break;
                }
            }
        }

        return $violations;
    }
}
