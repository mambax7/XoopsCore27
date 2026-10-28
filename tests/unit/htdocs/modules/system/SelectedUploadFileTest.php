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

namespace modulessystem;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The avatar, smilie and rank managers accept an existing image picked from
 * the upload folder instead of an upload. That name is later printed inside
 * HTML attributes (popup selectors, edit previews), so only a plain file name
 * that exists in the folder may be stored; the popup templates also escape
 * what they print.
 *
 * @category  Xoops
 * @package   System
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversFunction('system_selectedUploadFile')]
final class SelectedUploadFileTest extends TestCase
{
    protected function setUp(): void
    {
        if (function_exists('system_selectedUploadFile')) {
            return;
        }
        // The bootstrap stubs other functions of this file, so it cannot be
        // included whole; load only the function under test from its source.
        $src   = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/system/include/functions.php');
        $start = strpos($src, 'function system_selectedUploadFile(');
        $this->assertNotFalse($start);
        $end = strpos($src, "\n}\n", $start);
        $this->assertNotFalse($end);
        $tmp = tempnam(sys_get_temp_dir(), 'xsuf');
        file_put_contents($tmp, "<?php\n" . substr($src, $start, $end + 3 - $start));
        require $tmp;
        unlink($tmp);
    }

    #[Test]
    public function existingPlainFileNameIsAccepted(): void
    {
        foreach (['avatars', 'smilies', 'ranks'] as $folder) {
            $this->assertSame($folder . '/blank.gif', system_selectedUploadFile($folder, 'blank.gif'));
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedNames(): array
    {
        return [
            'empty'               => [''],
            'missing file'        => ['nothere.gif'],
            'attribute breakout'  => ['blank.gif" onerror="alert(1)'],
            'single quote'        => ["blank.gif'x"],
            'space'               => ['blank .gif'],
            'traversal'           => ['../avatars/blank.gif'],
            'subfolder'           => ['x/blank.gif'],
            'hidden file'         => ['.htaccess'],
            'trailing newline'    => ["blank.gif\n"],
        ];
    }

    #[Test]
    #[DataProvider('rejectedNames')]
    public function anythingElseIsRejected(string $name): void
    {
        $this->assertNull(system_selectedUploadFile('avatars', $name));
    }

    #[Test]
    public function saveBranchesUseTheValidator(): void
    {
        foreach ([
            'avatars/main.php'  => "system_selectedUploadFile('avatars', ",
            'smilies/main.php'  => "system_selectedUploadFile('smilies', ",
            'userrank/main.php' => "system_selectedUploadFile('ranks', ",
        ] as $file => $call) {
            $src = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/' . $file);
            $this->assertStringContainsString($call, $src, $file);
            $this->assertStringNotContainsString("'/' . basename(Request::getString(", $src, $file);
            $this->assertStringNotContainsString("basename(Request::getString('avatar_file'", $src, $file);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function popupTemplates(): array
    {
        return [
            'system smilies'   => ['modules/system/templates/system_misc_smilies.tpl'],
            'xswatch5 smilies' => ['themes/xswatch5/modules/system/system_misc_smilies.tpl'],
        ];
    }

    #[Test]
    #[DataProvider('popupTemplates')]
    public function smiliePopupEscapesEveryFieldAndKeepsTheCodeOutOfTheScript(string $tpl): void
    {
        $src = (string) file_get_contents(XOOPS_ROOT_PATH . '/' . $tpl);

        $this->assertDoesNotMatchRegularExpression('/<\{\$smile\.\w+\}>/', $src, 'every smile field is escaped');
        $this->assertStringContainsString('data-code="<{$smile.code|escape}>"', $src);
        $this->assertStringContainsString("doSmilie(' ' + this.getAttribute('data-code') + ' ');", $src);
    }

    #[Test]
    public function avatarPopupsEscapeTheFileName(): void
    {
        foreach ([
            'modules/system/templates/system_misc_avatars.tpl',
            'themes/xswatch5/modules/system/system_misc_avatars.tpl',
        ] as $tpl) {
            $src = (string) file_get_contents(XOOPS_ROOT_PATH . '/' . $tpl);
            $this->assertStringContainsString('<{$upload_url}><{$file|escape}>', $src, $tpl);
        }
    }
}
