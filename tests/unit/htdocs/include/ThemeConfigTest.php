<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/include/theme_config.php';

/**
 * Coverage for xoops_validateThemeName() and xoops_resolveThemeConfig()
 * in htdocs/include/theme_config.php. The helpers consolidate the
 * defensive normalisation previously inlined in
 * b_system_themes_show() so that every runtime reader of
 * theme_set / theme_set_allowed shares one source of truth.
 *
 * Validation contract is fail-empty: invalid names return '', invalid
 * config entries are dropped, current theme always survives in the
 * allowed list, and a totally-corrupted config falls back to 'default'.
 */
#[CoversFunction('xoops_validateThemeName')]
#[CoversFunction('xoops_resolveThemeConfig')]
class ThemeConfigTest extends TestCase
{
    #[Test]
    public function validateRejectsEmptyString(): void
    {
        $this->assertSame('', xoops_validateThemeName(''));
    }

    #[Test]
    public function validateTrimsSurroundingWhitespace(): void
    {
        $this->assertSame('default', xoops_validateThemeName('  default  '));
    }

    #[Test]
    public function validateRejectsLeadingDot(): void
    {
        $this->assertSame('', xoops_validateThemeName('.hidden'));
        $this->assertSame('', xoops_validateThemeName('..'));
    }

    #[Test]
    public function validateRejectsForwardSlash(): void
    {
        $this->assertSame('', xoops_validateThemeName('foo/bar'));
    }

    #[Test]
    public function validateRejectsBackslash(): void
    {
        $this->assertSame('', xoops_validateThemeName('foo\\bar'));
    }

    #[Test]
    public function validateRejectsNullByte(): void
    {
        $this->assertSame('', xoops_validateThemeName("foo\0bar"));
    }

    #[Test]
    public function validateRejectsParentDirSegment(): void
    {
        $this->assertSame('', xoops_validateThemeName('foo/../bar'));
        $this->assertSame('', xoops_validateThemeName('foo\\..\\bar'));
    }

    #[Test]
    public function validateRejectsHtmlMetacharacters(): void
    {
        $this->assertSame('', xoops_validateThemeName('foo<bar'));
        $this->assertSame('', xoops_validateThemeName('foo>bar'));
        $this->assertSame('', xoops_validateThemeName('foo&bar'));
        $this->assertSame('', xoops_validateThemeName('foo"bar'));
        $this->assertSame('', xoops_validateThemeName("foo'bar"));
    }

    #[Test]
    public function validateAcceptsThemeNamedZero(): void
    {
        $this->assertSame('0', xoops_validateThemeName('0'));
    }

    #[Test]
    public function validateAcceptsSpaces(): void
    {
        $this->assertSame('My Theme', xoops_validateThemeName('My Theme'));
    }

    #[Test]
    public function validateAcceptsNonAsciiNames(): void
    {
        $this->assertSame('テーマ', xoops_validateThemeName('テーマ'));
        $this->assertSame('주제', xoops_validateThemeName('주제'));
    }

    #[Test]
    public function resolveFallsBackToDefaultOnFullyMissingConfig(): void
    {
        $result = xoops_resolveThemeConfig([]);
        $this->assertSame('default', $result['theme_set']);
        $this->assertSame(['default'], $result['theme_set_allowed']);
    }

    #[Test]
    public function resolveSplitsPipeStringAllowedList(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => 'default',
            'theme_set_allowed' => 'default|xswatch5|xtailwind2',
        ]);
        $this->assertSame(
            ['default', 'xswatch5', 'xtailwind2'],
            $result['theme_set_allowed']
        );
    }

    #[Test]
    public function resolveSkipsNonScalarAllowedEntries(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => 'default',
            'theme_set_allowed' => ['default', ['nested'], (object) ['x' => 1], null, 'xswatch5'],
        ]);
        $this->assertSame(['default', 'xswatch5'], $result['theme_set_allowed']);
    }

    #[Test]
    public function resolveDropsInvalidAllowedNames(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => 'default',
            'theme_set_allowed' => ['default', '../etc', 'foo<script>', "evil\0null", 'xswatch5'],
        ]);
        $this->assertSame(['default', 'xswatch5'], $result['theme_set_allowed']);
    }

    #[Test]
    public function resolveRetainsThemeNamedZero(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => '0',
            'theme_set_allowed' => ['0', 'default'],
        ]);
        $this->assertSame('0', $result['theme_set']);
        $this->assertSame(['0', 'default'], $result['theme_set_allowed']);
    }

    #[Test]
    public function resolveSplitsPipeStringRetainingZero(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => '0',
            'theme_set_allowed' => '0|default',
        ]);
        $this->assertSame(['0', 'default'], $result['theme_set_allowed']);
    }

    #[Test]
    public function resolveInjectsCurrentThemeWhenValidButAbsent(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => 'mytheme',
            'theme_set_allowed' => ['default', 'xswatch5'],
        ]);
        $this->assertSame('mytheme', $result['theme_set']);
        $this->assertSame(['mytheme', 'default', 'xswatch5'], $result['theme_set_allowed']);
    }

    #[Test]
    public function resolveFallsCurrentBackToDefaultWhenCorrupted(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => '../etc/passwd',
            'theme_set_allowed' => ['default', 'xswatch5'],
        ]);
        $this->assertSame('default', $result['theme_set']);
        $this->assertContains('default', $result['theme_set_allowed']);
    }

    #[Test]
    public function resolveFallsAllowedToCurrentWhenAllInvalid(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => 'default',
            'theme_set_allowed' => ['../etc', "evil\0null", ['nested']],
        ]);
        $this->assertSame('default', $result['theme_set']);
        $this->assertSame(['default'], $result['theme_set_allowed']);
    }

    #[Test]
    public function resolveSkipsNonStringCurrentTheme(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => ['array', 'value'],
            'theme_set_allowed' => ['xswatch5'],
        ]);
        $this->assertSame('default', $result['theme_set']);
    }

    #[Test]
    public function resolveDedupesAllowedList(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => 'default',
            'theme_set_allowed' => ['default', 'xswatch5', 'default', 'xswatch5'],
        ]);
        $this->assertSame(['default', 'xswatch5'], $result['theme_set_allowed']);
    }

    #[Test]
    public function resolveTreatsNonArrayNonStringAllowedAsEmpty(): void
    {
        $result = xoops_resolveThemeConfig([
            'theme_set'         => 'default',
            'theme_set_allowed' => 42,
        ]);
        $this->assertSame(['default'], $result['theme_set_allowed']);
    }
}
