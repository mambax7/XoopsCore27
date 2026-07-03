<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for the iframe BBCode single-quote breakout (SECURITY.md M-7).
 *
 * The src capture must exclude both quote types and angle brackets so a ' cannot
 * break out of the single-quoted src='...' attribute it is rendered into.
 *
 * @see \MytsIframe
 */
final class IframeBreakoutTest extends TestCase
{
    /** The hardened pattern, mirroring MytsIframe::load(). */
    private const PATTERN = '/\[iframe=([\'"]?)([^"\']*)\1]((?:https?:\/\/|\/\/)[^"\'<>]*)\[\/iframe\]/sU';

    #[Test]
    public function sourcePatternHardensSrc(): void
    {
        $src = (string) file_get_contents(XOOPS_ROOT_PATH . '/class/textsanitizer/iframe/iframe.php');
        // The src group (3rd capture) must exclude quotes/angle brackets AND require an
        // http(s):// or // scheme prefix.
        self::assertStringContainsString('[^\\"\'<>]*', $src, 'iframe src capture is not quote-hardened');
        self::assertStringContainsString('(?:https?:\/\/|\/\/)', $src, 'iframe src is not scheme-restricted');
    }

    #[Test]
    public function benignIframeStillMatches(): void
    {
        $ok = preg_match(self::PATTERN, '[iframe=300]https://example.com/embed[/iframe]', $m);
        self::assertSame(1, $ok);
        self::assertSame('https://example.com/embed', $m[3]);
    }

    #[Test]
    public function singleQuoteBreakoutNoLongerMatches(): void
    {
        // The ' breaks the [^"'<>] class, so the tag no longer matches the closing
        // [/iframe] and is left as inert text instead of becoming an iframe src.
        self::assertSame(0, preg_match(self::PATTERN, "[iframe=300]https://x/' onload='alert(1)[/iframe]"));
    }

    #[Test]
    public function angleBracketInjectionNoLongerMatches(): void
    {
        self::assertSame(0, preg_match(self::PATTERN, '[iframe=300]https://x/"><script>[/iframe]'));
    }

    #[Test]
    public function dangerousSchemeNoLongerMatches(): void
    {
        // javascript:/data: do not start with http(s):// or // → no match → inert text.
        self::assertSame(0, preg_match(self::PATTERN, '[iframe=300]javascript:alert(1)[/iframe]'));
        self::assertSame(0, preg_match(self::PATTERN, '[iframe=300]data:text/html,1[/iframe]'));
    }

    #[Test]
    public function protocolRelativeSrcStillMatches(): void
    {
        $ok = preg_match(self::PATTERN, '[iframe=300]//example.com/embed[/iframe]', $m);
        self::assertSame(1, $ok);
        self::assertSame('//example.com/embed', $m[3]);
    }
}
