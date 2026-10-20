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

namespace xoopsclass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/include/theme_config.php';
require_once XOOPS_ROOT_PATH . '/class/theme.php';

class TestableThemeFactory extends \xos_opal_ThemeFactory
{
    public function resolve(array $options = []): array
    {
        return $this->resolveFolderName($options);
    }
}

/**
 * Theme folder resolution in xos_opal_ThemeFactory::createInstance().
 *
 * The factory, the kernel's theme selector (POST) and login all keep the
 * user's theme in $_SESSION['xoopsUserTheme']. The factory used to keep a
 * second copy under its own bundle key, so a theme chosen once with
 * ?xoops_theme_select= overrode every later choice made in the selector.
 *
 * @category  Xoops
 * @package   Core
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(\xos_opal_ThemeFactory::class)]
final class XoopsThemeFactoryTest extends TestCase
{
    private array $savedPost;
    private array $savedGet;
    private ?array $savedSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedPost    = $_POST;
        $this->savedGet     = $_GET;
        $this->savedSession = $_SESSION ?? null;
        $_POST    = [];
        $_GET     = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->savedPost;
        $_GET  = $this->savedGet;
        if (null === $this->savedSession) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->savedSession;
        }
        parent::tearDown();
    }

    private function factory(): TestableThemeFactory
    {
        $factory                = new TestableThemeFactory();
        $factory->allowedThemes = ['default', 'alpha', 'beta', '0'];
        $factory->defaultTheme  = 'default';

        return $factory;
    }

    #[Test]
    public function explicitFolderNameIsKeptAndNothingIsRemembered(): void
    {
        $_GET['xoops_theme_select'] = 'alpha';

        $options = $this->factory()->resolve(['folderName' => 'beta']);

        $this->assertSame('beta', $options['folderName']);
        $this->assertArrayNotHasKey('xoopsUserTheme', $_SESSION);
    }

    #[Test]
    public function explicitZeroFolderNameIsKept(): void
    {
        $options = $this->factory()->resolve(['folderName' => '0']);

        $this->assertSame('0', $options['folderName']);
    }

    #[Test]
    public function postSelectionWinsAndIsRemembered(): void
    {
        $_POST['xoops_theme_select'] = 'alpha';
        $_GET['xoops_theme_select']  = 'beta';

        $options = $this->factory()->resolve();

        $this->assertSame('alpha', $options['folderName']);
        $this->assertSame('alpha', $_SESSION['xoopsUserTheme']);
    }

    #[Test]
    public function getSelectionIsUsedAndRememberedInTheSharedSessionKey(): void
    {
        $_GET['xoops_theme_select'] = 'beta';

        $options = $this->factory()->resolve();

        $this->assertSame('beta', $options['folderName']);
        $this->assertSame(['xoopsUserTheme' => 'beta'], $_SESSION);
    }

    #[Test]
    public function selectorChoiceAfterAGetSelectionIsNotOverridden(): void
    {
        // Request 1: theme chosen with ?xoops_theme_select=alpha
        $_GET['xoops_theme_select'] = 'alpha';
        $this->factory()->resolve();

        // Request 2: the kernel handles the selector POST and stores beta,
        // then redirects; the follow-up page carries no selection.
        $_GET                       = [];
        $_SESSION['xoopsUserTheme'] = 'beta';

        $options = $this->factory()->resolve();

        $this->assertSame('beta', $options['folderName']);
    }

    #[Test]
    public function disallowedSelectionFallsBackToTheRememberedTheme(): void
    {
        $_SESSION['xoopsUserTheme'] = 'alpha';
        $_GET['xoops_theme_select'] = 'notallowed';

        $options = $this->factory()->resolve();

        $this->assertSame('alpha', $options['folderName']);
        $this->assertSame('alpha', $_SESSION['xoopsUserTheme']);
    }

    #[Test]
    public function invalidSelectionNameIsIgnored(): void
    {
        $_GET['xoops_theme_select'] = '../alpha';

        $options = $this->factory()->resolve();

        $this->assertSame('default', $options['folderName']);
        $this->assertArrayNotHasKey('xoopsUserTheme', $_SESSION);
    }

    #[Test]
    public function rememberedThemeNoLongerAllowedFallsBackToDefault(): void
    {
        $_SESSION['xoopsUserTheme'] = 'removed';

        $options = $this->factory()->resolve();

        $this->assertSame('default', $options['folderName']);
    }

    #[Test]
    public function poisonedRememberedThemeFallsBackToDefault(): void
    {
        $_SESSION['xoopsUserTheme'] = ['alpha'];

        $options = $this->factory()->resolve();

        $this->assertSame('default', $options['folderName']);
    }

    #[Test]
    public function noSelectionAndNoRememberedThemeUsesDefault(): void
    {
        $options = $this->factory()->resolve(['folderName' => '']);

        $this->assertSame('default', $options['folderName']);
        $this->assertArrayNotHasKey('xoopsUserTheme', $_SESSION);
    }

    #[Test]
    public function selectionIsNotRememberedWhenUserSelectionIsDisabled(): void
    {
        $factory                     = $this->factory();
        $factory->allowUserSelection = false;
        $_GET['xoops_theme_select']  = 'alpha';

        $options = $factory->resolve();

        $this->assertSame('alpha', $options['folderName']);
        $this->assertArrayNotHasKey('xoopsUserTheme', $_SESSION);
    }

    #[Test]
    public function selectionWithoutASessionIsUsedButNotRemembered(): void
    {
        unset($_SESSION);
        $_GET['xoops_theme_select'] = 'alpha';

        $options = $this->factory()->resolve();

        $this->assertSame('alpha', $options['folderName']);
        $this->assertFalse(isset($_SESSION));
    }

    #[Test]
    public function emptyAllowedListAllowsAnyValidName(): void
    {
        $factory                    = new TestableThemeFactory();
        $_GET['xoops_theme_select'] = 'gamma';

        $this->assertSame('gamma', $factory->resolve()['folderName']);
    }
}
