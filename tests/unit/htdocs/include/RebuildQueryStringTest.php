<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

/**
 * xoops_rebuildQueryString() is the one implementation behind every redirect
 * that reflects the request's query string (the pm and profile preload events
 * and profile's register.php activation redirect). It must emit only
 * RFC 3986-safe bytes or valid escapes, keep legitimate long xoops_redirect
 * targets intact, and return '' for anything it cannot parse.
 */
final class RebuildQueryStringTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function rebuiltStrings(): array
    {
        return [
            'plain pair'        => ['uid=5', '?uid=5'],
            'multiple pairs'    => ['send=1&to_userid=5', '?send=1&to_userid=5'],
            'token passthrough' => ['op=logout&XOOPS_TOKEN_REQUEST=abc123', '?op=logout&XOOPS_TOKEN_REQUEST=abc123'],
            'malformed escape'  => ['a=%ZZ', '?a=%25ZZ'],
            'trailing percent'  => ['a=%', '?a=%25'],
            'encoded crlf'      => ['evil=%0d%0aSet-Cookie:x=1', '?evil=%0D%0ASet-Cookie%3Ax%3D1'],
            'markup'            => ['a=<script>', '?a=%3Cscript%3E'],
        ];
    }

    #[Test]
    #[DataProvider('rebuiltStrings')]
    public function rebuildsToSafeEncoding(string $raw, string $expected): void
    {
        self::assertSame($expected, xoops_rebuildQueryString($raw));
    }

    /** @return array<string, array{string}> */
    public static function droppedStrings(): array
    {
        return [
            'empty'           => [''],
            'separators only' => ['&&&'],
            'bare equals'     => ['='],
            'over the cap'    => [str_repeat('x', 2001)],
        ];
    }

    #[Test]
    #[DataProvider('droppedStrings')]
    public function dropsUnusableInput(string $raw): void
    {
        self::assertSame('', xoops_rebuildQueryString($raw));
    }

    #[Test]
    public function everyEmittedByteIsSafeOrAValidEscape(): void
    {
        $rebuilt = xoops_rebuildQueryString('evil=%0d%0a&b[]=<">&c=100% sure');
        self::assertMatchesRegularExpression('/^\?(?:[A-Za-z0-9_.~=&\-]|%[0-9A-F]{2})+$/', $rebuilt);
    }

    #[Test]
    public function longLoginRedirectSurvivesAndRoundTrips(): void
    {
        // The scenario the replaced allowlist cap broke: a login redirect back
        // to a publisher search with a multi-category filter, urlencoded past
        // 512 bytes. The rebuilt string must parse identically at the target.
        $query = 'xoops_redirect=' . urlencode('/modules/publisher/search.php?' . str_repeat('category[]=12&', 45) . 'andor=AND&sortby=itemid');
        self::assertGreaterThan(512, strlen($query));

        $rebuilt = xoops_rebuildQueryString($query);
        self::assertNotSame('', $rebuilt);

        parse_str($query, $original);
        parse_str(substr($rebuilt, 1), $reparsed);
        self::assertSame($original, $reparsed);
    }
}
