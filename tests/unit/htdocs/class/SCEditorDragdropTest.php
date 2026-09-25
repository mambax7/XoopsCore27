<?php

declare(strict_types=1);

/**
 * The SCEditor dragdrop plugin is handed an upload token only when an image
 * category is configured and the viewer is a logged-in user who may upload to it.
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

require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formtextarea.php';
require_once XOOPS_ROOT_PATH . '/class/xoopseditor/xoopseditor.php';
require_once XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/sceditor.php';
require_once XOOPS_ROOT_PATH . '/kernel/user.php';

#[CoversClass(FormSCEditor::class)]
final class SCEditorDragdropTest extends TestCase
{
    private mixed $savedUser = null;

    private mixed $errorBefore = null;
    private mixed $exceptionBefore = null;

    protected function setUp(): void
    {
        $this->savedUser = $GLOBALS['xoopsUser'] ?? null;
        // set_*_handler(null) + restore_*_handler() is the only way to read the current handler.
        $this->errorBefore = set_error_handler(null);
        restore_error_handler();
        $this->exceptionBefore = set_exception_handler(null);
        restore_exception_handler();
    }

    protected function tearDown(): void
    {
        $GLOBALS['xoopsUser'] = $this->savedUser;
        // Issuing a token starts XoopsLogger, which installs its own handlers; unwind them
        // (bounded) so they do not leak into later tests.
        for ($i = 0; $i < 16 && set_error_handler(null) !== $this->errorBefore; ++$i) {
            restore_error_handler();
            restore_error_handler();
        }
        restore_error_handler();
        for ($i = 0; $i < 16 && set_exception_handler(null) !== $this->exceptionBefore; ++$i) {
            restore_exception_handler();
            restore_exception_handler();
        }
        restore_exception_handler();
    }

    private function config(int $imgcatId): ?array
    {
        $editor = (new ReflectionClass(FormSCEditor::class))->newInstanceWithoutConstructor();
        return (new ReflectionMethod($editor, 'dragdropConfig'))->invoke($editor, $imgcatId);
    }

    private function member(): XoopsUser
    {
        $user = new XoopsUser();
        $user->assignVar('uid', 5);
        $user->setGroups([XOOPS_GROUP_USERS]);
        return $user;
    }

    #[Test]
    public function offWhenNoCategoryIsConfigured(): void
    {
        $GLOBALS['xoopsUser'] = $this->member();
        $this->assertNull($this->config(0));
    }

    #[Test]
    public function offForGuestsEvenWithACategory(): void
    {
        $GLOBALS['xoopsUser'] = '';
        $this->assertNull($this->config(3));
    }

    #[Test]
    public function offWhenTheCategoryDoesNotExist(): void
    {
        // The test database stub finds no rows, so category 3 is unknown.
        $GLOBALS['xoopsUser'] = $this->member();
        $this->assertNull($this->config(3));
    }

    /**
     * Editor whose category lookup finds category $imgcatId (limit 2048 bytes)
     * and whose permission check answers $canWrite.
     */
    private function configWithStubs(int $imgcatId, bool $canWrite): ?array
    {
        require_once XOOPS_ROOT_PATH . '/kernel/imagecategory.php';
        $imgcat = new XoopsImagecategory();
        $imgcat->assignVar('imgcat_id', $imgcatId);
        $imgcat->assignVar('imgcat_maxsize', 2048);

        $categories = new class ($imgcat) {
            public function __construct(private XoopsImagecategory $imgcat) {}
            public function get(int $id): ?XoopsImagecategory
            {
                return $id === (int) $this->imgcat->getVar('imgcat_id') ? $this->imgcat : null;
            }
        };
        $perms = new class ($canWrite) {
            public array $asked = [];
            public function __construct(private bool $canWrite) {}
            public function checkRight(string $name, int $id, array $groups): bool
            {
                $this->asked[] = [$name, $id];
                return $this->canWrite;
            }
        };

        $editor = (new ReflectionClass(SCEditorDragdropStubbed::class))->newInstanceWithoutConstructor();
        $editor->stubs = ['imagecategory' => $categories, 'groupperm' => $perms];
        $result = (new ReflectionMethod($editor, 'dragdropConfig'))->invoke($editor, $imgcatId);
        $this->assertSame([['imgcat_write', $imgcatId]], $perms->asked);

        return $result;
    }

    #[Test]
    public function offForAMemberWithoutWriteRight(): void
    {
        $GLOBALS['xoopsUser'] = $this->member();
        $this->assertNull($this->configWithStubs(3, false));
    }

    #[Test]
    public function aMemberWithWriteRightGetsAToken(): void
    {
        $GLOBALS['xoopsUser'] = $this->member();
        $config = $this->configWithStubs(3, true);

        $this->assertIsArray($config);
        $this->assertSame(XOOPS_URL . '/ajaxfineupload.php', $config['endpoint']);
        $this->assertIsString($config['token']);
        $this->assertNotSame('', $config['token']);
        $this->assertSame(2048, $config['maxSize']);
    }

    #[Test]
    public function negativeOrMissingPreferenceMeansOff(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/class/SCEditorConfig.php';
        $this->assertSame(0, SCEditorConfig::settings([])['dragdrop_cat']);
        $this->assertSame(0, SCEditorConfig::settings(['sceditor_dragdrop_cat' => '-4'])['dragdrop_cat']);
        $this->assertSame(12, SCEditorConfig::settings(['sceditor_dragdrop_cat' => '12'])['dragdrop_cat']);
        $this->assertSame('0', SCEditorConfig::items()['sceditor_dragdrop_cat']['value']);
    }
}

/** FormSCEditor with its kernel handler lookups replaced by test stubs. */
final class SCEditorDragdropStubbed extends FormSCEditor
{
    /** @var array<string, object> */
    public array $stubs = [];

    protected function handler(string $name): object
    {
        return $this->stubs[$name];
    }
}
