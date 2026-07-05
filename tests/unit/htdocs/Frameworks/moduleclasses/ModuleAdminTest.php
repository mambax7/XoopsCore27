<?php

declare(strict_types=1);

namespace frameworksmoduleclasses;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ModuleAdmin;

#[CoversClass(ModuleAdmin::class)]
class ModuleAdminTest extends TestCase
{
    private static bool $loaded = false;
    private ModuleAdmin $admin;

    public static function setUpBeforeClass(): void
    {
        if (!self::$loaded) {
            // ModuleAdmin needs these
            if (!defined('XOOPS_FRAMEWORKS_MODULEADMIN_VERSION')) {
                require_once XOOPS_ROOT_PATH . '/Frameworks/moduleclasses/moduleadmin/xoops_version.php';
            }
            require_once XOOPS_ROOT_PATH . '/Frameworks/moduleclasses/moduleadmin/moduleadmin.php';
            self::$loaded = true;
        }
    }

    protected function setUp(): void
    {
        // Set up a mock XoopsModule in globals
        $GLOBALS['xoopsModule'] = $this->createMockXoopsModule();
        $GLOBALS['xoopsConfig'] = ['language' => 'english'];

        $this->admin = new ModuleAdmin();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['xoTheme'], $GLOBALS['xoopsModule'], $GLOBALS['xoopsConfig']);
    }

    // ---------------------------------------------------------------
    // Constructor tests
    // ---------------------------------------------------------------

    #[Test]
    public function constructorCreatesInstance(): void
    {
        $this->assertInstanceOf(ModuleAdmin::class, $this->admin);
    }

    // ---------------------------------------------------------------
    // getVersion tests
    // ---------------------------------------------------------------

    #[Test]
    public function getVersionReturnsString(): void
    {
        $version = $this->admin->getVersion();
        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }

    #[Test]
    public function getVersionReturnsDefinedConstant(): void
    {
        $this->assertSame(XOOPS_FRAMEWORKS_MODULEADMIN_VERSION, $this->admin->getVersion());
    }

    // ---------------------------------------------------------------
    // getReleaseDate tests
    // ---------------------------------------------------------------

    #[Test]
    public function getReleaseDateReturnsString(): void
    {
        $date = $this->admin->getReleaseDate();
        $this->assertIsString($date);
        $this->assertNotEmpty($date);
    }

    #[Test]
    public function getReleaseDateReturnsDefinedConstant(): void
    {
        $this->assertSame(XOOPS_FRAMEWORKS_MODULEADMIN_RELEASEDATE, $this->admin->getReleaseDate());
    }

    // ---------------------------------------------------------------
    // getClassMethods tests
    // ---------------------------------------------------------------

    #[Test]
    public function getClassMethodsReturnsArray(): void
    {
        $methods = $this->admin->getClassMethods();
        $this->assertIsArray($methods);
    }

    #[Test]
    public function getClassMethodsContainsExpectedMethods(): void
    {
        $methods = $this->admin->getClassMethods();
        $this->assertContains('getVersion', $methods);
        $this->assertContains('getReleaseDate', $methods);
        $this->assertContains('addItemButton', $methods);
        $this->assertContains('renderButton', $methods);
        $this->assertContains('addInfoBox', $methods);
        $this->assertContains('addInfoBoxLine', $methods);
        $this->assertContains('renderInfoBox', $methods);
        $this->assertContains('addConfigBoxLine', $methods);
        $this->assertContains('addNavigation', $methods);
    }

    // ---------------------------------------------------------------
    // getInfo tests
    // ---------------------------------------------------------------

    #[Test]
    public function getInfoReturnsArray(): void
    {
        $info = $this->admin->getInfo();
        $this->assertIsArray($info);
    }

    #[Test]
    public function getInfoContainsVersionKey(): void
    {
        $info = $this->admin->getInfo();
        $this->assertArrayHasKey('version', $info);
        $this->assertSame(XOOPS_FRAMEWORKS_MODULEADMIN_VERSION, $info['version']);
    }

    #[Test]
    public function getInfoContainsReleaseDateKey(): void
    {
        $info = $this->admin->getInfo();
        $this->assertArrayHasKey('releasedate', $info);
    }

    #[Test]
    public function getInfoContainsMethodsKey(): void
    {
        $info = $this->admin->getInfo();
        $this->assertArrayHasKey('methods', $info);
        $this->assertIsArray($info['methods']);
    }

    // ---------------------------------------------------------------
    // addItemButton tests
    // ---------------------------------------------------------------

    #[Test]
    public function addItemButtonReturnsTrue(): void
    {
        $result = $this->admin->addItemButton('Add Item', 'admin/add.php');
        $this->assertTrue($result);
    }

    #[Test]
    public function addItemButtonWithCustomIcon(): void
    {
        $result = $this->admin->addItemButton('Edit', 'admin/edit.php', 'edit', 'class="special"');
        $this->assertTrue($result);
    }

    // ---------------------------------------------------------------
    // renderButton tests
    // ---------------------------------------------------------------

    #[Test]
    public function renderButtonReturnsHtmlString(): void
    {
        $this->admin->addItemButton('Test', 'admin/test.php');
        $result = $this->admin->renderButton();
        $this->assertIsString($result);
        $this->assertStringContainsString('xo-buttons', $result);
    }

    #[Test]
    public function renderButtonWithRightPosition(): void
    {
        $this->admin->addItemButton('Test', 'admin/test.php');
        $result = $this->admin->renderButton('right');
        $this->assertStringContainsString('floatright', $result);
    }

    #[Test]
    public function renderButtonWithLeftPosition(): void
    {
        $this->admin->addItemButton('Test', 'admin/test.php');
        $result = $this->admin->renderButton('left');
        $this->assertStringContainsString('floatleft', $result);
    }

    #[Test]
    public function renderButtonWithCenterPosition(): void
    {
        $this->admin->addItemButton('Test', 'admin/test.php');
        $result = $this->admin->renderButton('center');
        $this->assertStringContainsString('aligncenter', $result);
    }

    #[Test]
    public function renderButtonContainsButtonLink(): void
    {
        $this->admin->addItemButton('My Button', 'admin/mypage.php', 'add');
        $result = $this->admin->renderButton();
        $this->assertStringContainsString('admin/mypage.php', $result);
        $this->assertStringContainsString('My Button', $result);
    }

    #[Test]
    public function renderButtonWithCustomDelimiter(): void
    {
        $this->admin->addItemButton('Test', 'admin/test.php');
        $result = $this->admin->renderButton('right', ' | ');
        $this->assertStringContainsString(' | ', $result);
    }

    // ---------------------------------------------------------------
    // addConfigBoxLine tests
    // ---------------------------------------------------------------

    #[Test]
    public function addConfigBoxLineDefaultType(): void
    {
        $result = $this->admin->addConfigBoxLine('Some config value');
        $this->assertTrue($result);
    }

    #[Test]
    public function addConfigBoxLineFolderTypeExistingDir(): void
    {
        $result = $this->admin->addConfigBoxLine(XOOPS_ROOT_PATH, 'folder');
        $this->assertTrue($result);
    }

    #[Test]
    public function addConfigBoxLineFolderTypeNonExistingDir(): void
    {
        $result = $this->admin->addConfigBoxLine('/nonexistent/path/xyz', 'folder');
        $this->assertTrue($result);
    }

    #[Test]
    public function addConfigBoxLineChmodType(): void
    {
        // chmod type expects array [path, expected_chmod]
        $result = $this->admin->addConfigBoxLine([XOOPS_ROOT_PATH, '0755'], 'chmod');
        $this->assertTrue($result);
    }

    // ---------------------------------------------------------------
    // addInfoBox tests
    // ---------------------------------------------------------------

    #[Test]
    public function addInfoBoxReturnsTrue(): void
    {
        $result = $this->admin->addInfoBox('Server Info');
        $this->assertTrue($result);
    }

    // ---------------------------------------------------------------
    // addInfoBoxLine tests
    // ---------------------------------------------------------------

    #[Test]
    public function addInfoBoxLineDefaultType(): void
    {
        $result = $this->admin->addInfoBoxLine('Server Info', 'PHP Version: %s', phpversion(), 'green');
        $this->assertTrue($result);
    }

    #[Test]
    public function addInfoBoxLineInformationType(): void
    {
        $result = $this->admin->addInfoBoxLine('Server Info', 'Running on test environment', '', '', 'information');
        $this->assertTrue($result);
    }

    // ---------------------------------------------------------------
    // renderInfoBox tests
    // ---------------------------------------------------------------

    #[Test]
    public function renderInfoBoxReturnsEmptyStringWhenNoBoxes(): void
    {
        // Fresh admin instance, no boxes added
        $freshAdmin = new ModuleAdmin();
        $result = $freshAdmin->renderInfoBox();
        $this->assertSame('', $result);
    }

    #[Test]
    public function renderInfoBoxReturnsHtmlWithBoxes(): void
    {
        $this->admin->addInfoBox('Test Box');
        $this->admin->addInfoBoxLine('Test Box', 'Items: %s', '42', 'blue');
        $result = $this->admin->renderInfoBox();
        $this->assertStringContainsString('Test Box', $result);
        $this->assertStringContainsString('42', $result);
        $this->assertStringContainsString('fieldset', $result);
    }

    #[Test]
    public function renderInfoBoxOnlyShowsLinesMatchingBox(): void
    {
        $this->admin->addInfoBox('Box A');
        $this->admin->addInfoBox('Box B');
        $this->admin->addInfoBoxLine('Box A', 'Line for A: %s', 'AAA');
        $this->admin->addInfoBoxLine('Box B', 'Line for B: %s', 'BBB');
        $result = $this->admin->renderInfoBox();
        $this->assertStringContainsString('Box A', $result);
        $this->assertStringContainsString('AAA', $result);
        $this->assertStringContainsString('Box B', $result);
        $this->assertStringContainsString('BBB', $result);
    }

    // ---------------------------------------------------------------
    // loadLanguage tests
    // ---------------------------------------------------------------

    #[Test]
    public function loadLanguageReturnsTrueForEnglish(): void
    {
        $GLOBALS['xoopsConfig']['language'] = 'english';
        $admin = new ModuleAdmin();
        // loadLanguage is called in constructor; we can call it again
        $result = $admin->loadLanguage();
        // include_once returns true or 1
        $this->assertNotFalse($result);
    }

    #[Test]
    public function loadLanguageFallsBackToEnglish(): void
    {
        $GLOBALS['xoopsConfig']['language'] = 'nonexistent_language_xyz';
        $admin = new ModuleAdmin();
        $result = $admin->loadLanguage();
        $this->assertNotFalse($result);
    }

    // ---------------------------------------------------------------
    // addNavigation tests
    // ---------------------------------------------------------------

    #[Test]
    public function addNavigationReturnsString(): void
    {
        $result = $this->admin->addNavigation('index.php');
        $this->assertIsString($result);
    }

    // ---------------------------------------------------------------
    // renderMenuIndex tests
    // ---------------------------------------------------------------

    #[Test]
    public function renderMenuIndexReturnsHtmlString(): void
    {
        $result = $this->admin->renderMenuIndex();
        $this->assertIsString($result);
        $this->assertStringContainsString('rmmenuicon', $result);
    }

    // ---------------------------------------------------------------
    // Footer Info tests
    // ---------------------------------------------------------------

    #[Test]
    public function testRenderFooterInfoWithLogoContainsXoopsLinkAndFooterText(): void
    {
        $html = $this->admin->renderFooterInfo(true);

        $this->assertStringContainsString('xoopsmicrobutton.gif', $html, 'Should contain XOOPS logo image');
        $this->assertStringContainsString('href="https://xoops.org"', $html, 'Should contain XOOPS link');
        $this->assertStringContainsString('rel="external noopener noreferrer"', $html, 'Logo link should have noopener noreferrer');
        $this->assertStringContainsString(_AM_MODULEADMIN_ADMIN_FOOTER, $html, 'Should contain footer text constant');
    }

    #[Test]
    public function testRenderFooterInfoWithoutLogoOmitsImageButIncludesFooterText(): void
    {
        $html = $this->admin->renderFooterInfo(false);

        $this->assertStringNotContainsString('xoopsmicrobutton.gif', $html, 'Should not contain XOOPS logo');
        $this->assertStringNotContainsString('href="https://xoops.org"', $html, 'Should not contain XOOPS logo link');
        $this->assertStringContainsString(_AM_MODULEADMIN_ADMIN_FOOTER, $html, 'Should still contain footer text');
    }

    #[Test]
    public function testDisplayFooterInfoEchoesSameMarkupAsRender(): void
    {
        $expected = $this->admin->renderFooterInfo(true);

        ob_start();
        $this->admin->displayFooterInfo(true);
        $actual = ob_get_clean();

        $this->assertSame($expected, $actual, 'displayFooterInfo should echo exactly what renderFooterInfo returns');
    }

    #[Test]
    public function testDisplayFooterInfoWithoutLogoEchoesSameAsRenderWithoutLogo(): void
    {
        $expected = $this->admin->renderFooterInfo(false);

        ob_start();
        $this->admin->displayFooterInfo(false);
        $actual = ob_get_clean();

        $this->assertSame($expected, $actual, 'displayFooterInfo(false) should echo what renderFooterInfo(false) returns');
    }

    // ---------------------------------------------------------------
    // renderAbout integration tests
    // ---------------------------------------------------------------

    #[Test]
    public function testRenderAboutIncludesFooterWithLogo(): void
    {
        $html = $this->admin->renderAbout('', true);

        $this->assertStringContainsString('xoopsmicrobutton.gif', $html, 'About page should include XOOPS logo');
        $this->assertStringContainsString(_AM_MODULEADMIN_ADMIN_FOOTER, $html, 'About page should include footer text');
    }

    #[Test]
    public function testRenderAboutWithoutLogoOmitsLogoButIncludesFooter(): void
    {
        $html = $this->admin->renderAbout('', false);

        $this->assertStringNotContainsString('xoopsmicrobutton.gif', $html, 'About page should not include XOOPS logo');
        $this->assertStringContainsString(_AM_MODULEADMIN_ADMIN_FOOTER, $html, 'About page should still include footer text');
    }

    // ---------------------------------------------------------------
    // Changelog sanitizer tests
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function changelogSanitizerProvider(): array
    {
        return [
            'allowed heading survives'      => ['<h5>1.1.0 RC 1</h5>', '<h5>1.1.0 RC 1</h5>'],
            'allowed strong survives'       => ['<strong>Added</strong>', '<strong>Added</strong>'],
            'allowed void hr survives'      => ['<hr>', '<hr>'],
            'self-closing br survives'      => ['line<br/>break', 'line<br/>break'],
            'uppercase tag normalized'      => ['<H5>Head</H5>', '<h5>Head</h5>'],
            'disallowed tag kept as text'   => ['Added support for <table> tags', 'Added support for &lt;table&gt; tags'],
            'literal comparison preserved'  => ['requires PHP <= 8.0 & up', 'requires PHP &lt;= 8.0 &amp; up'],
            'bare ampersand escaped'        => ['AT&T', 'AT&amp;T'],
            'attribute on allowed tag inert'=> ['<strong onclick="x()">y</strong>', '&lt;strong onclick=&quot;x()&quot;&gt;y</strong>'],
            'script tag inert'              => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'],
            'javascript href inert'         => ['<a href="javascript:alert(1)">x</a>', '&lt;a href=&quot;javascript:alert(1)&quot;&gt;x&lt;/a&gt;'],
        ];
    }

    #[Test]
    #[DataProvider('changelogSanitizerProvider')]
    public function testSanitizeChangelogLine(string $input, string $expected): void
    {
        $this->assertSame($expected, ModuleAdmin::sanitizeChangelogLine($input));
    }

    #[Test]
    public function testSanitizeChangelogLineNeverEmitsScriptTag(): void
    {
        $output = ModuleAdmin::sanitizeChangelogLine('<script>alert(document.cookie)</script>');

        $this->assertStringNotContainsString('<script>', $output, 'A poisoned changelog must never emit an executable <script> tag');
    }

    // ---------------------------------------------------------------
    // Helper: mock XoopsModule
    // ---------------------------------------------------------------

    private function createMockXoopsModule(): object
    {
        return new class {
            public array $adminmenu = [];

            public function getVar(string $key, string $format = 'e')
            {
                if ($key === 'dirname') return 'testmod';
                if ($key === 'mid') return 1;
                if ($key === 'version') return '1.0.0';
                if ($key === 'last_update') return time();
                return '';
            }

            public function getInfo(string $key = '')
            {
                $info = [
                    'name' => 'Test Module',
                    'description' => 'A test module',
                    'help' => '',
                    'image' => 'images/logo.png',
                    'release_date' => '2025/01/15',
                    'author' => 'Test Author',
                    'nickname' => 'tester',
                    'license' => 'GPL-2.0',
                    'license_url' => 'https://www.gnu.org/licenses/gpl-2.0.html',
                    'website' => 'https://xoops.org',
                    'module_website_url' => 'xoops.org',
                    'module_website_name' => 'XOOPS',
                    'min_php' => false,
                    'min_xoops' => false,
                    'min_admin' => false,
                    'min_db' => false,
                ];
                return $key !== '' ? ($info[$key] ?? false) : $info;
            }

            public function loadAdminMenu(): void
            {
                $this->adminmenu = [
                    ['title' => 'Home', 'link' => 'admin/index.php', 'icon' => 'images/admin/home.png', 'desc' => 'Home page'],
                    ['title' => 'Items', 'link' => 'admin/items.php', 'icon' => 'images/admin/items.png', 'desc' => 'Manage items'],
                ];
            }

            public function getStatus(): string
            {
                return 'Active';
            }

            public function versionCompare($a, $b, $op): bool
            {
                return version_compare($a, $b, $op);
            }
        };
    }
}
