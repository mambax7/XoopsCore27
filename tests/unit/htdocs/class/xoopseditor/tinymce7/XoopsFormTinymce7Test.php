<?php

declare(strict_types=1);

namespace xoopseditor\tinymce7;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/xoopseditor/tinymce7/formtinymce.php';

/**
 * Unit tests for XoopsFormTinymce7.
 */
class XoopsFormTinymce7Test extends TestCase
{
    /**
     * getLanguage() must return the configured editor locale verbatim
     * (e.g. zh_TW), not lower-cased — TinyMCE 7 language packs are
     * case-sensitive.
     *
     * Runs in a separate process: the test has to define() the global
     * _XOOPS_EDITOR_TINYMCE7_LANGUAGE constant, and a PHP constant cannot
     * be unset — without isolation it would leak into every later test in
     * the run and make execution order significant.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetLanguagePreservesConfiguredLocaleCase(): void
    {
        if (defined('_XOOPS_EDITOR_TINYMCE7_LANGUAGE')) {
            self::markTestSkipped('_XOOPS_EDITOR_TINYMCE7_LANGUAGE already defined; cannot exercise the configured-locale path in isolation.');
        }

        define('_XOOPS_EDITOR_TINYMCE7_LANGUAGE', 'zh_TW');

        // Anonymous subclass: skip the heavy parent constructor without
        // reflection. getLanguage() only needs $this->language (unset here)
        // and the constant, so this faithfully exercises the target path.
        $editor = new class extends \XoopsFormTinymce7 {
            public function __construct()
            {
                // Intentionally empty: bypass XoopsFormTinymce7::__construct(),
                // which builds a full TinyMCE editor and is irrelevant to
                // getLanguage().
            }
        };

        self::assertSame('zh_TW', $editor->getLanguage());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function fallbackLanguageCodeProvider(): array
    {
        return [
            'english remains default TinyMCE code' => ['en', 'en'],
            'french maps to TinyMCE 7 filename' => ['fr', 'fr_FR'],
            'traditional chinese underscore' => ['zh_TW', 'zh_TW'],
            'traditional chinese hyphen' => ['zh-tw', 'zh_TW'],
            'legacy TinyMCE 3 utf8 suffix is stripped' => ['zh-tw_utf8', 'zh_TW'],
            'brazilian portuguese preserves region case' => ['pt_BR', 'pt_BR'],
            'swedish maps to TinyMCE 7 filename' => ['sv', 'sv_SE'],
            'custom regional packs keep TinyMCE 7 separator style' => ['es-mx', 'es_MX'],
            'germany country variant collapses to bare pack' => ['de_DE', 'de'],
            'spain country variant collapses to bare pack' => ['es_ES', 'es'],
            'malformed token falls back to english' => ['@@', 'en'],
            'invalid region falls back to english' => ['es-123', 'en'],
            'empty language falls back to english' => ['', 'en'],
        ];
    }

    #[DataProvider('fallbackLanguageCodeProvider')]
    public function testFallbackLanguageCodesUseTinymce7LocaleFormat(string $langcode, string $expected): void
    {
        $editor = new class extends \XoopsFormTinymce7 {
            public function __construct()
            {
                // Intentionally empty: this test only exercises the language
                // formatter used by the getLanguage() fallback branch.
            }

            public function normalizeForTest(string $langcode): string
            {
                return self::normalizeLanguageCode($langcode);
            }
        };

        self::assertSame($expected, $editor->normalizeForTest($langcode));
    }

    /**
     * End-to-end guard: getLanguage() must actually run _LANGCODE through
     * the normalizer when _XOOPS_EDITOR_TINYMCE7_LANGUAGE is absent — i.e.
     * proves the wiring of the #76 fix, not just the helper in isolation.
     *
     * Separate process because it has to define() the _LANGCODE global
     * constant (a PHP constant cannot be unset). Local Windows PHPUnit
     * process isolation has been flaky in this repo, but CI runs the
     * existing isolated test reliably.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetLanguageFallbackUsesLangcodeNormalizer(): void
    {
        if (defined('_XOOPS_EDITOR_TINYMCE7_LANGUAGE') || defined('_LANGCODE')) {
            self::markTestSkipped('Locale constants already defined; cannot exercise the fallback branch in isolation.');
        }

        define('_LANGCODE', 'zh-tw_utf8');

        $editor = new class extends \XoopsFormTinymce7 {
            public function __construct()
            {
                // Intentionally empty: bypass the heavy parent constructor;
                // only the getLanguage() fallback branch is under test.
            }
        };

        self::assertSame('zh_TW', $editor->getLanguage());
    }
}
