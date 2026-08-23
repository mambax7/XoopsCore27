<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use testsupport\ConstructorReturnScanner;

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
 * arrive via dependency bumps, not local edits. The scan itself lives in the
 * shared testsupport\ConstructorReturnScanner (one implementation for both
 * convention tests, pinned by its own truth-table test - review catch); it is
 * frame-stack based, so nested functions and nested constructors attribute
 * correctly by construction.
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
        // No CATCH_GET_CHILD: an unreadable child directory must FAIL the
        // guard explicitly, never shrink its coverage. The wrap turns the
        // iterator's UnexpectedValueException into a named test failure
        // (review catch).
        try {
            $files = iterator_to_array($iterator);
        } catch (UnexpectedValueException $e) {
            $this->fail('directory traversal failed - unreadable path during scan: ' . $e->getMessage());
        }
        foreach ($files as $file) {
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
            foreach (ConstructorReturnScanner::scan($source) as $line) {
                $violations[] = sprintf(
                    '%s:%d returns a value from __construct()',
                    substr($path, strlen(XOOPS_ROOT_PATH) + 1),
                    $line
                );
            }
        }

        // Floor well above 1: a wrong root, broken extension check, or
        // over-broad exclusion would otherwise shrink the guard to
        // near-zero coverage while staying green (review catch). $scanned
        // counts files that CONTAIN __construct after the prefilter - 173
        // of 1,082 first-party files when this floor was set, both
        // measured by execution; the reviewer-suggested floor of 500
        // conflated the two counts and would have failed immediately.
        $this->assertGreaterThan(
            100,
            $scanned,
            sprintf('the scan covered only %d __construct-bearing files; the repository-wide guard has lost most of its coverage', $scanned)
        );
        $this->assertSame(
            [],
            $violations,
            "PHP 8.6 deprecates (and 9.0 forbids) returning a value from __construct():\n"
            . implode("\n", $violations)
        );
    }

}
