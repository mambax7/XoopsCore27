<?php

declare(strict_types=1);

namespace xoopseditor\tinymce7;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once XOOPS_ROOT_PATH . '/class/xoopseditor/tinymce7/formtinymce.php';

/**
 * Unit tests for XoopsFormTinymce7.
 */
class XoopsFormTinymce7Test extends TestCase
{
    public function testGetLanguagePreservesConfiguredLocaleCase(): void
    {
        define('_XOOPS_EDITOR_TINYMCE7_LANGUAGE', 'zh_TW');

        $reflection = new ReflectionClass(\XoopsFormTinymce7::class);
        $editor = $reflection->newInstanceWithoutConstructor();

        $this->assertSame('zh_TW', $editor->getLanguage());
    }
}
