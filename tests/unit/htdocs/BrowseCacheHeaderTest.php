<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * browse.php must describe its caching policy with directives that exist.
 * It sent "Cache-Control: maxage=<seconds>"; HTTP defines "max-age" (RFC 9111
 * 5.2.2.1), and an unrecognised directive is ignored, so the field carried no
 * lifetime for any cache reading it -- every asset served through browse.php,
 * including the bundled jQuery most themes load in <head>.
 *
 * These are source contracts rather than response assertions. browse.php is a
 * front controller: it boots through mainfile.php, streams the file body, and
 * ends in exit(), so including it would end the test run, and inspecting real
 * response headers would need an installed site rather than a unit suite.
 * BrowsePathContainmentTest guards its other contract the same way.
 */
final class BrowseCacheHeaderTest extends TestCase
{
    private function browseSource(): string
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/browse.php');
        self::assertNotFalse($src, 'browse.php should be readable');

        return $src;
    }

    #[Test]
    public function browseSendsAHyphenatedMaxAgeDirective(): void
    {
        // Matched against the header() call, not the file text, so a comment
        // naming the old spelling cannot pass or fail the test.
        $src = $this->browseSource();
        self::assertSame(
            0,
            preg_match('/header\(\s*[\'"]Cache-Control:\s*maxage/i', $src),
            'browse.php must not emit "maxage"; HTTP has no such directive.'
        );
        self::assertStringContainsString("header('Cache-Control: public, max-age='", $src);
    }

    #[Test]
    public function browseDoesNotSendPragmaPublic(): void
    {
        // Pragma is a request header whose only defined directive is no-cache.
        // "public" belongs to Cache-Control, where it now is.
        self::assertSame(
            0,
            preg_match('/header\(\s*[\'"]Pragma:/i', $this->browseSource()),
            'Pragma: public has no defined meaning as a response header.'
        );
    }

    #[Test]
    public function browseKeepsTheExpiresFallback(): void
    {
        // Expires is what kept these assets cacheable while the directive was
        // misspelled; it stays for caches that do not read Cache-Control.
        self::assertStringContainsString("header('Expires: '", $this->browseSource());
    }

    #[Test]
    public function browseDoesNotMarkAssetsImmutable(): void
    {
        // browse.php URLs are plain paths, not content-fingerprinted, so an
        // immutable response would pin a stale asset for the full lifetime.
        self::assertSame(
            0,
            preg_match('/immutable/i', $this->browseSource()),
            'Assets served by path must not be declared immutable.'
        );
    }

    #[Test]
    public function bothFreshnessHeadersDeriveFromTheFifteenDayLifetime(): void
    {
        $src = $this->browseSource();

        // Read the lifetime out of browse.php rather than restating it here.
        // The factors are multiplied as written, so refactoring the expression
        // is free; changing the policy fails this test, which is the point.
        self::assertSame(
            1,
            preg_match('/\$expires\s*=\s*([\d\s*]+?);/', $src, $m),
            'browse.php should declare its cache lifetime as an $expires expression'
        );
        $lifetime = array_product(array_map('intval', preg_split('/\s*\*\s*/', trim($m[1]))));
        self::assertSame(1296000, $lifetime, 'The cache lifetime should stay at 15 days');

        // Both freshness headers have to be built from that one value, or they
        // can drift apart and describe two different policies.
        self::assertStringContainsString(
            "header('Cache-Control: public, max-age=' . \$expires);",
            $src,
            'max-age should come from $expires, not a literal'
        );
        self::assertStringContainsString(
            "time() + \$expires",
            $src,
            'Expires should be computed from the same $expires'
        );
    }
}
