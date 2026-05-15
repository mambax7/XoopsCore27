<?php

declare(strict_types=1);

namespace install;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the installer's required-extension helpers.
 *
 * Covers the two pure helpers added to harden the installer against a
 * missing PHP extension (notably mysqli, which has no fallback driver):
 *
 *   - xoInstallerExtensionAvailable($ext, $symbols): capability probe used
 *     by both the requirements-page gate and the server-side DB guard.
 *   - xoInstallerMissingRequired($wizard): single source of truth for the
 *     missing-extension label list, consumed by every install entry point.
 *   - xoInstallerBlockedHtml($labels): builds the escaped "extension
 *     missing" alert markup.
 *
 * install/include/functions.php is pure function declarations (no
 * top-level code), so it is safe to require once here.
 *
 * @see \xoInstallerExtensionAvailable
 * @see \xoInstallerMissingRequired
 * @see \xoInstallerBlockedHtml
 */
#[CoversFunction('xoInstallerExtensionAvailable')]
#[CoversFunction('xoInstallerMissingRequired')]
#[CoversFunction('xoInstallerBlockedHtml')]
class InstallerRequiredExtensionsTest extends TestCase
{
    protected function setUp(): void
    {
        // Installer language constants consumed by xoInstallerBlockedHtml().
        // Defined here (guarded) because the install language file is not
        // part of the test bootstrap.
        if (!defined('MISSING_REQUIRED_EXTENSIONS')) {
            define('MISSING_REQUIRED_EXTENSIONS', 'Required PHP extensions are missing');
        }
        if (!defined('MISSING_REQUIRED_EXTENSIONS_MSG')) {
            define('MISSING_REQUIRED_EXTENSIONS_MSG', 'Missing: %s. Enable it and reload.');
        }
        // xoInstallerBlockedHtml() escapes via installerHtmlSpecialChars(),
        // which references _INSTALL_CHARSET (set by the install language
        // file in production; not part of the test bootstrap).
        if (!defined('_INSTALL_CHARSET')) {
            define('_INSTALL_CHARSET', 'UTF-8');
        }

        require_once XOOPS_ROOT_PATH . '/install/include/functions.php';
        // xoInstallerMissingRequired() type-hints XoopsInstallWizard; the
        // class file is plain (no access guard) and the class has no
        // constructor, so it is safe to load and instantiate here.
        require_once XOOPS_ROOT_PATH . '/install/class/installwizard.php';
    }

    private function wizardWith(array $extensionsRequired): \XoopsInstallWizard
    {
        $wizard                                  = new \XoopsInstallWizard();
        $wizard->configs['extensions_required'] = $extensionsRequired;

        return $wizard;
    }

    // =====================================================================
    // xoInstallerExtensionAvailable()
    // =====================================================================

    public function testReturnsTrueForLoadedExtensionWithNoSymbols(): void
    {
        // json is compiled in and always loaded on supported PHP versions.
        self::assertTrue(extension_loaded('json'), 'precondition: json loaded');
        self::assertTrue(xoInstallerExtensionAvailable('json'));
    }

    public function testReturnsFalseForUnknownExtension(): void
    {
        self::assertFalse(xoInstallerExtensionAvailable('totally_not_a_real_extension'));
    }

    public function testReturnsTrueWhenAllSymbolsExist(): void
    {
        // json_encode (function) and JsonException (class) both ship with
        // the json extension.
        self::assertTrue(
            xoInstallerExtensionAvailable('json', ['json_encode', 'JsonException'])
        );
    }

    public function testReturnsFalseWhenARequiredSymbolIsMissing(): void
    {
        // Extension is loaded but a symbol the caller needs is absent —
        // this is the partial-build case the symbol list guards against.
        self::assertFalse(
            xoInstallerExtensionAvailable('json', ['json_encode', 'xoops_no_such_symbol_xyz'])
        );
    }

    public function testDoesNotTriggerAutoloadForClassSymbolProbe(): void
    {
        $autoloadHits = [];
        $probe = static function ($class) use (&$autoloadHits) {
            $autoloadHits[] = $class;
        };
        spl_autoload_register($probe);
        try {
            // json IS loaded, so the function does not short-circuit on
            // extension_loaded() — it reaches the symbol loop. The missing
            // class symbol must be probed with class_exists(.., false) so no
            // registered autoloader is invoked for it.
            xoInstallerExtensionAvailable('json', ['Xoops_Unloadable_Probe_Class']);
        } finally {
            spl_autoload_unregister($probe);
        }

        self::assertNotContains('Xoops_Unloadable_Probe_Class', $autoloadHits);
    }

    // =====================================================================
    // xoInstallerMissingRequired()
    // =====================================================================

    public function testMissingRequiredEmptyWhenAllPresent(): void
    {
        // json and pcre are compiled in on every supported PHP build.
        $wizard = $this->wizardWith([
            'json' => ['JSON', []],
            'pcre' => ['PCRE', []],
        ]);

        self::assertSame([], xoInstallerMissingRequired($wizard));
    }

    public function testMissingRequiredReportsAbsentExtension(): void
    {
        $wizard = $this->wizardWith([
            'json'             => ['JSON', []],
            'totally_fake_ext' => ['FakeExt', []],
        ]);

        self::assertSame(['FakeExt'], xoInstallerMissingRequired($wizard));
    }

    public function testMissingRequiredHonoursSymbolList(): void
    {
        // Extension present, but a required symbol is not — must be reported.
        $wizard = $this->wizardWith([
            'json' => ['JSON', ['xoops_no_such_symbol_xyz']],
        ]);

        self::assertSame(['JSON'], xoInstallerMissingRequired($wizard));
    }

    public function testMissingRequiredPreservesConfigOrder(): void
    {
        $wizard = $this->wizardWith([
            'totally_fake_ext' => ['FakeExt', []],
            'json'             => ['JSON', []],
            'another_fake_ext' => ['OtherFake', []],
        ]);

        self::assertSame(['FakeExt', 'OtherFake'], xoInstallerMissingRequired($wizard));
    }

    // =====================================================================
    // xoInstallerBlockedHtml()
    // =====================================================================

    public function testBlockedHtmlContainsHeadingAndFormattedLabel(): void
    {
        $html = xoInstallerBlockedHtml('MySQLi');

        self::assertStringContainsString('alert alert-danger', $html);
        self::assertStringContainsString(MISSING_REQUIRED_EXTENSIONS, $html);
        self::assertStringContainsString('Missing: MySQLi.', $html);
    }

    public function testBlockedHtmlEscapesTheLabel(): void
    {
        $html = xoInstallerBlockedHtml('<script>x</script>');

        self::assertStringNotContainsString('<script>x</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}
