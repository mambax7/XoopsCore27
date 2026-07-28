<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

/**
 * Same-origin redirect validation (findings H-1 / M-13).
 *
 * redirect_header() is stubbed in the test bootstrap, so the host check it now
 * relies on is exercised through its pure helper xoops_isLocalUrl(). XOOPS_URL is
 * 'http://localhost' in the bootstrap.
 */
final class RedirectValidationTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function unsafeUrls(): array
    {
        return [
            'prefix host'   => ['http://localhost.evil.test/'],
            'userinfo'      => ['http://localhost@evil.test/'],
            'userinfo + pw' => ['http://user:pass@evil.test/'],
            'other host'    => ['https://evil.test/'],
            'scheme rel'    => ['//evil.test/'],
            'port mismatch' => ['http://localhost:8443/'],
            'raw crlf'      => ["https://localhost/\r\nX: y"],
            'encoded host'  => ['https://evil.test/%0d%0aSet-Cookie:x=y'],
            'javascript'    => ['javascript:alert(1)'],
            'data scheme'   => ['data:text/html,<script>alert(1)</script>'],
            'mailto'        => ['mailto:a@b.test'],
            'scheme no host'  => ['http:/evil.test/'],
            'scheme no host2' => ['https:/evil.test/path'],
        ];
    }

    #[Test]
    #[DataProvider('unsafeUrls')]
    public function rejectsNonLocalTargets(string $url): void
    {
        self::assertFalse(xoops_isLocalUrl($url), $url);
    }

    /** @return array<string, array{string}> */
    public static function safeUrls(): array
    {
        return [
            'same host'         => ['http://localhost/modules/news/index.php'],
            'same host w/port'  => ['http://localhost'],
            'default http port' => ['http://localhost:80/admin.php'],
            'scheme-rel host'   => ['//localhost/admin.php'],
            'root relative'     => ['/admin.php?fct=preferences'],
        ];
    }

    #[Test]
    #[DataProvider('safeUrls')]
    public function acceptsSameOriginAndRootRelative(string $url): void
    {
        self::assertTrue(xoops_isLocalUrl($url), $url);
    }

    /** @return array<string, array{string, string}> */
    public static function separatorUrls(): array
    {
        return [
            // The live xoops.org entry: three rounds of escaping, with the semicolons
            // percent-encoded so the browser could no longer collapse them.
            'three layers, encoded' => [
                '/modules/newbb/viewpost.php?start=0&amp%3Bamp%3Bamp%3Bforum=0',
                '/modules/newbb/viewpost.php?start=0&forum=0',
            ],
            'one layer, literal'  => ['/a.php?x=1&amp;forum=0', '/a.php?x=1&forum=0'],
            'two layers, literal' => ['/a.php?x=1&amp;amp;forum=0', '/a.php?x=1&forum=0'],
            'mixed spellings'     => ['/a.php?x=1&amp;amp%3Bforum=0', '/a.php?x=1&forum=0'],
            'already correct'     => ['/a.php?x=1&forum=0', '/a.php?x=1&forum=0'],
            // Must not eat a parameter that is genuinely named "amp", nor a value.
            'parameter named amp' => ['/a.php?amp=1&other=2', '/a.php?amp=1&other=2'],
            'value containing amp' => ['/a.php?q=amp;stuff&x=1', '/a.php?q=amp;stuff&x=1'],
        ];
    }

    #[Test]
    #[DataProvider('separatorUrls')]
    public function escapedAmpersandLayersAreCollapsed(string $url, string $expected): void
    {
        self::assertSame($expected, xoops_normalizeUrlSeparators($url));
    }

    #[Test]
    public function collapsingIsIdempotent(): void
    {
        // The property that matters: twice must equal once, or the helper becomes another
        // source of drift in a chain that already had one.
        $url  = '/modules/newbb/viewpost.php?start=0&amp%3Bamp%3Bamp%3Bforum=0&amp;viewmode=flat';
        $once = xoops_normalizeUrlSeparators($url);

        self::assertSame($once, xoops_normalizeUrlSeparators($once));
    }

    #[Test]
    public function redirectHeaderDoesNotPutAnHtmlEntityInTheQueryString(): void
    {
        // "&amp;xoops_redirect=" names the parameter "amp;xoops_redirect" to anything that
        // parses the URL rather than rendering it, and it seeded the layering above.
        $src = file_get_contents(XOOPS_ROOT_PATH . '/include/functions.php');
        self::assertNotFalse($src);
        self::assertSame(
            0,
            preg_match('/&amp;xoops_redirect=/', $src),
            'redirect_header() must use a plain & separator; escaping belongs at output.'
        );
    }

    #[Test]
    public function commentDeleteDoesNotRedirectToRawReferer(): void
    {
        $src = file_get_contents(XOOPS_ROOT_PATH . '/include/comment_delete.php');
        self::assertNotFalse($src);
        self::assertSame(
            0,
            preg_match('/redirect_header\(\s*\$ref\b/', $src),
            'comment_delete.php must not redirect to the raw HTTP_REFERER (M-13).'
        );
    }
}
