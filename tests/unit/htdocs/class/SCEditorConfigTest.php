<?php

declare(strict_types=1);

/**
 * SCEditor preferences: defaults, saved values and the rendered toolbar.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/class/SCEditorConfig.php';

#[CoversClass(SCEditorConfig::class)]
final class SCEditorConfigTest extends TestCase
{
    #[Test]
    public function withoutSavedPreferencesTheFullToolbarIsTheDefault(): void
    {
        $settings = SCEditorConfig::settings([]);

        $this->assertSame(
            'bold,italic,underline,strike,subscript,superscript|left,center,right,justify,ltr,rtl|'
            . 'font,size,color,removeformat|cut,copy,paste,pastetext|bulletlist,orderedlist,indent,outdent,table|'
            . 'link,unlink,siteurl,email,image,youtube,mp3|quote,code,wikipage|horizontalrule,date,time,emoticon|'
            . 'print,maximize,source',
            SCEditorConfig::toolbar($settings),
        );
        $this->assertSame([], $settings['plugins']);
        $this->assertTrue($settings['emoticons']);
        $this->assertSame('100%', $settings['width']);
    }

    #[Test]
    public function toolbarKeepsOrderAndGroupsAndDropsEmptyGroups(): void
    {
        $settings = SCEditorConfig::settings([
            // Saved order must not matter; unknown names are ignored.
            'sceditor_toolbar'   => ['source', 'cut', 'copy', 'paste', 'bold', 'bogus'],
            'sceditor_plugins'   => ['undo', 'dragdrop', 'format'],
            'sceditor_emoticons' => 0,
        ]);

        $this->assertSame(['undo'], $settings['plugins'], 'dragdrop and format are not offered');
        $this->assertSame('bold|cut,copy,paste|source', SCEditorConfig::toolbar($settings));
    }

    #[Test]
    public function emoticonsOffHidesTheEmoticonButton(): void
    {
        $settings = SCEditorConfig::settings(['sceditor_toolbar' => ['emoticon', 'bold'], 'sceditor_emoticons' => 0]);

        $this->assertSame('bold', SCEditorConfig::toolbar($settings));
    }

    #[Test]
    public function preferenceRowsCarryTheirDefaultsAndOptions(): void
    {
        $items = SCEditorConfig::items();

        $this->assertSame(SCEditorConfig::buttons(), unserialize($items['sceditor_toolbar']['value'], ['allowed_classes' => false]));
        $this->assertSame(SCEditorConfig::buttons(), $items['sceditor_toolbar']['options']);
        $this->assertSame(serialize([]), $items['sceditor_plugins']['value']);
        $this->assertSame(SCEditorConfig::PLUGINS, $items['sceditor_plugins']['options']);
        $this->assertNotContains('alternative-lists', SCEditorConfig::PLUGINS, 'it writes [list], which XOOPS does not decode');
        $this->assertSame('_MD_AM_SCEDITOR_WIDTHDSC', $items['sceditor_width']['desc']);
        foreach (SCEditorConfig::PLUGINS as $plugin) {
            $this->assertFileExists(XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/minified/plugins/' . $plugin . '.js');
        }
        $language = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/system/language/english/admin/preferences.php');
        foreach ($items as $item) {
            $this->assertStringContainsString("define('" . $item['title'] . "',", $language);
            $this->assertStringContainsString("define('" . $item['desc'] . "',", $language);
        }
    }
}
