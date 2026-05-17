<?php

declare(strict_types=1);

namespace xoopseditor\tinymce7;

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
}
