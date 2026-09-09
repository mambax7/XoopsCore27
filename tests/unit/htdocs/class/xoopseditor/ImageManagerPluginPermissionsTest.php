<?php
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

declare(strict_types=1);

namespace Tests\Unit\ClassDir\XoopsEditor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__, 2) . '/modules/system/SourceFileTestTrait.php';

/**
 * The editor image-manager plugin (one copy per TinyMCE version) let anyone
 * with read access to a single category reach the upload and delete
 * branches, which then checked only the request token. Each mutating branch
 * must authorise against the category it acts on, and the listing branch
 * must check read access to the category it shows. The core image manager
 * template must not pass image titles through an inline event handler.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ImageManagerPluginPermissionsTest extends TestCase
{
    use SourceFileTestTrait;

    /**
     * @return array<string, array{string}>
     */
    public static function pluginCopies(): array
    {
        return [
            'tinymce7' => ['htdocs/class/xoopseditor/tinymce7/js/tinymce/plugins/xoopsimagemanager/xoopsimagemanager.php'],
            'tinymce5' => ['htdocs/class/xoopseditor/tinymce5/js/tinymce/plugins/xoopsimagemanager/xoopsimagemanager.php'],
        ];
    }

    #[Test]
    #[DataProvider('pluginCopies')]
    public function uploadBranchRequiresWriteAccessToTheSelectedCategory(string $path): void
    {
        $this->loadSourceFile($path);
        $branch = $this->slice("'addfile' === Request::getString('op', '', 'POST')", "include_once XOOPS_ROOT_PATH . '/class/uploader.php';");

        self::assertStringContainsString("checkRight('imgcat_write', \$imgcat_id, \$groups)", $branch);
        self::assertStringContainsString('_NOPERM', $branch);
    }

    #[Test]
    #[DataProvider('pluginCopies')]
    public function deleteBranchAuthorisesAgainstTheImagesOwnCategory(string $path): void
    {
        $this->loadSourceFile($path);
        $branch = $this->slice("\$op === 'delfileok'", '$image_handler->delete($image)');

        self::assertStringContainsString("checkRight('imgcat_write', (int) \$image->getVar('imgcat_id'), \$groups)", $branch);
        self::assertStringContainsString('_NOPERM', $branch);
    }

    #[Test]
    #[DataProvider('pluginCopies')]
    public function listingBranchRequiresReadAccessToTheCategoryShown(string $path): void
    {
        $this->loadSourceFile($path);
        // The listing branch is the last "listimg" test in the file; an earlier
        // one only prints a heading.
        $start = strrpos($this->sourceContent, "\$op === 'listimg'");
        self::assertNotFalse($start);
        $end = strpos($this->sourceContent, "new Criteria('imgcat_id', \$imgcat_id)", $start);
        self::assertNotFalse($end);
        $branch = substr($this->sourceContent, $start, $end - $start);

        self::assertStringContainsString("checkRight('imgcat_read', \$imgcat_id, \$groups)", $branch);
    }

    #[Test]
    public function imageManagerTemplateInsertsCodesAsDataNotAsInlineScript(): void
    {
        $this->loadSourceFile('htdocs/modules/system/templates/system_imagemanager.tpl');

        self::assertStringNotContainsString('onclick="appendCode(', $this->sourceContent);
        self::assertSame(3, substr_count($this->sourceContent, 'data-xo-code="<{$images[i].'));
        self::assertStringContainsString("closest('[data-xo-code]')", $this->sourceContent);
        self::assertStringContainsString("appendCode(button.getAttribute('data-xo-code'))", $this->sourceContent);
    }

    /**
     * Return the part of the loaded source between two unique markers, so an
     * assertion pins the guard to the branch it belongs to.
     */
    private function slice(string $from, string $to): string
    {
        $start = strpos($this->sourceContent, $from);
        self::assertNotFalse($start, "start marker not found: $from");
        $end = strpos($this->sourceContent, $to, $start);
        self::assertNotFalse($end, "end marker not found after start: $to");

        return substr($this->sourceContent, $start, $end - $start);
    }
}
