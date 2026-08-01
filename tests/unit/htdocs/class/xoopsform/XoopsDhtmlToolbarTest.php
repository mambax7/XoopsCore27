<?php
/**
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Project
 */

declare(strict_types=1);

namespace xoopsform;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use XoopsDhtmlToolbar;
use XoopsFormDhtmlTextArea;
use XoopsPreload;
use XoopsPreloadItem;

require_once XOOPS_ROOT_PATH . '/class/xoopseditor/dhtmltextarea/XoopsDhtmlToolbar.php';

xoops_load('XoopsFormDhtmlTextArea');
xoops_load('XoopsFormTextArea');
xoops_load('XoopsFormElement');
xoops_load('XoopsFormRenderer');
xoops_load('XoopsFormRendererInterface');
xoops_load('XoopsFormRendererLegacy');
xoops_load('XoopsFormRendererBootstrap3');
xoops_load('XoopsFormRendererBootstrap4');
xoops_load('XoopsFormRendererBootstrap5');
xoops_load('XoopsFormRendererTailwind');

/**
 * Preload test double for the `codeicon` by-reference contract (Test 2).
 *
 * The method name must be the normalized event name ('core.class.xoopsform.
 * formdhtmltextarea.codeicon' with dots stripped and lowercased) prefixed with 'event', matching
 * {@see \XoopsPreload::setEvents()}'s discovery convention — but here it is registered directly
 * via reflection (as {@see \XoopsPreloadTest} does), so the name only needs to match what
 * {@see XoopsDhtmlToolbarTest::injectCodeiconHandler()} registers it under.
 */
class ToolbarCodeiconTestPreload extends XoopsPreloadItem
{
    /** @var bool */
    public static $called = false;

    /**
     * @param array $args by-reference args; $args[0] is the code row markup so far
     */
    public static function eventCodeiconHandler($args): void
    {
        self::$called = true;
        $args[0] .= '<button data-testid="thirdparty-codeicon">3rd</button>';
    }
}

/**
 * Unit tests for the shared {@see XoopsDhtmlToolbar}.
 *
 * Test 1 is the acceptance test for the whole refactor: all five form renderers must delegate to
 * the shared toolbar and therefore produce byte-identical toolbar markup for the same element.
 */
class XoopsDhtmlToolbarTest extends TestCase
{
    private const RENDERER_CLASSES = [
        'XoopsFormRendererLegacy',
        'XoopsFormRendererBootstrap3',
        'XoopsFormRendererBootstrap4',
        'XoopsFormRendererBootstrap5',
        'XoopsFormRendererTailwind',
    ];

    /** @var array */
    private $originalPreloadEvents;

    protected function setUp(): void
    {
        $GLOBALS['formtextdhtml_sizes'] = [
            'xx-small' => 'xx-Small',
            'small'    => 'Small',
            'medium'   => 'Medium',
        ];
        $GLOBALS['formtextdhtml_fonts'] = ['Arial', 'Courier', 'Georgia'];

        $instance = XoopsPreload::getInstance();
        $ref      = new ReflectionClass($instance);
        $prop     = $ref->getProperty('_events');
        $prop->setAccessible(true);
        $this->originalPreloadEvents = $prop->getValue($instance);

        ToolbarCodeiconTestPreload::$called = false;

        $this->resetStylesheetGuard();
    }

    protected function tearDown(): void
    {
        $instance = XoopsPreload::getInstance();
        $ref      = new ReflectionClass($instance);
        $prop     = $ref->getProperty('_events');
        $prop->setAccessible(true);
        $prop->setValue($instance, $this->originalPreloadEvents);

        unset($GLOBALS['formtextdhtml_sizes'], $GLOBALS['formtextdhtml_fonts']);
    }

    // =========================================================================
    // Test 1 — the acceptance test
    // =========================================================================

    /**
     * All five renderers must produce the IDENTICAL toolbar string for the same element. This is
     * the whole point of the refactor.
     */
    public function testAllFiveRenderersProduceIdenticalToolbarOutput(): void
    {
        $results = [];
        foreach (self::RENDERER_CLASSES as $class) {
            $element  = $this->makeElement();
            $renderer = new $class();
            $code       = $this->callProtected($renderer, 'renderFormDhtmlTAXoopsCode', [$element]);
            $typography = $this->callProtected($renderer, 'renderFormDhtmlTATypography', [$element]);
            $results[$class] = $code . $typography;
        }

        $expected = reset($results);
        $this->assertNotSame('', $expected, 'Precondition: toolbar output must not be empty');
        $this->assertStringContainsString('xo-edtb-btn', $expected);

        foreach ($results as $class => $actual) {
            $this->assertSame($expected, $actual, "{$class}'s toolbar output diverges from the others");
        }
    }

    /**
     * Prove genuine delegation, not coincidence: the per-renderer combined output must equal the
     * shared toolbar's own rows built directly (renderCodeButtons + renderTypography +
     * renderCheckLength), for every one of the five renderers.
     */
    public function testEveryRendererDelegatesToTheSameSharedToolbarInstanceShape(): void
    {
        $toolbarElement = $this->makeElement();
        $toolbar        = new XoopsDhtmlToolbar();
        $direct = $toolbar->renderCodeButtons($toolbarElement)
            . $toolbar->renderTypography($toolbarElement)
            . $toolbar->renderCheckLength($toolbarElement);

        foreach (self::RENDERER_CLASSES as $class) {
            $element  = $this->makeElement();
            $renderer = new $class();
            $code       = $this->callProtected($renderer, 'renderFormDhtmlTAXoopsCode', [$element]);
            $typography = $this->callProtected($renderer, 'renderFormDhtmlTATypography', [$element]);
            $this->assertSame($direct, $code . $typography, "{$class} did not delegate to XoopsDhtmlToolbar");
        }
    }

    // =========================================================================
    // Test 2 — the codeicon preload event contract
    // =========================================================================

    /**
     * The `core.class.xoopsform.formdhtmltextarea.codeicon` event fires with the code row by
     * reference, after the core buttons and TextSanitizer extensions, immediately before the code
     * row is returned — so a listener's appended markup must be present, and at the tail, of the
     * returned string.
     */
    public function testCodeiconPreloadEventFiresByReferenceAtSameLogicalPoint(): void
    {
        $this->injectCodeiconHandler();

        $element = $this->makeElement();
        $toolbar = new XoopsDhtmlToolbar();
        $code    = $toolbar->renderCodeButtons($element);

        $this->assertTrue(ToolbarCodeiconTestPreload::$called, 'codeicon preload handler was not invoked');
        $this->assertStringContainsString('data-testid="thirdparty-codeicon"', $code);
        $this->assertStringEndsWith('<button data-testid="thirdparty-codeicon">3rd</button>', $code);
    }

    // =========================================================================
    // Test 3 — TextSanitizer extension contract
    // =========================================================================

    /**
     * Extension-supplied 'btn btn-default btn-sm' markup is rewritten to the toolbar's neutral
     * classes, and any $js an extension returns lands on $element->js. Exercised end to end via
     * the bundled 'youtube' extension, which is enabled in the shipped textsanitizer config and
     * hardcodes those Bootstrap classes in its own encode() output.
     */
    public function testExtensionButtonClassesAreRewrittenAndJsLandsOnElement(): void
    {
        $element = $this->makeElement();
        $toolbar = new XoopsDhtmlToolbar();
        $code    = $toolbar->renderCodeButtons($element);

        $this->assertStringNotContainsString('btn-default', $code);
        $this->assertStringContainsString('xo-edtb-btn xo-edtb-btn-sm', $code);
        $this->assertStringContainsString('xoopsCodeYoutube', $code);
        $this->assertStringContainsString('function xoopsCodeYoutube', $element->js);
    }

    /**
     * Focused unit test of the rewrite rule itself, independent of which bundled extension
     * happens to be enabled.
     */
    public function testRewriteExtensionButtonClassesReplacesKnownBootstrapClasses(): void
    {
        $toolbar = new XoopsDhtmlToolbar();
        $input   = "<button class='btn btn-default btn-sm'>x</button><button class='btn btn-default'>y</button>";

        $output = $this->callProtected($toolbar, 'rewriteExtensionButtonClasses', [$input]);

        $this->assertStringNotContainsString('btn-default', $output);
        $this->assertStringNotContainsString('btn-secondary', $output);
        $this->assertStringContainsString("class='xo-edtb-btn xo-edtb-btn-sm'", $output);
        $this->assertStringContainsString("class='xo-edtb-btn'>y", $output);
    }

    // =========================================================================
    // Test 4 — no document.write, no eval(
    // =========================================================================

    public function testOutputContainsNoDocumentWriteOrEval(): void
    {
        $element = $this->makeElement();
        $toolbar = new XoopsDhtmlToolbar();
        $full    = $toolbar->render($element);

        $this->assertStringNotContainsString('document.write', $full);
        $this->assertStringNotContainsString('eval(', $full);
    }

    // =========================================================================
    // Test 5 — $GLOBALS['formtextdhtml_sizes']/['formtextdhtml_fonts'] overrides
    // =========================================================================

    public function testGlobalSizeAndFontOverridesAreReflectedInOutput(): void
    {
        $GLOBALS['formtextdhtml_sizes'] = ['huge' => 'Huge Size'];
        $GLOBALS['formtextdhtml_fonts'] = ['CustomFontXYZ'];

        $element    = $this->makeElement();
        $toolbar    = new XoopsDhtmlToolbar();
        $typography = $toolbar->renderTypography($element);

        $this->assertStringContainsString('Huge Size', $typography);
        $this->assertStringContainsString('huge', $typography);
        $this->assertStringContainsString('CustomFontXYZ', $typography);
    }

    // =========================================================================
    // Test 6 — check-length button
    // =========================================================================

    public function testCheckLengthButtonCarriesConfiguredMaxlength(): void
    {
        $element          = $this->makeElement();
        $element->configs = ['maxlength' => 12345];

        $toolbar     = new XoopsDhtmlToolbar();
        $checkLength = $toolbar->renderCheckLength($element);

        $this->assertStringContainsString('XoopsCheckLength', $checkLength);
        $this->assertStringContainsString('12345', $checkLength);
        $this->assertStringContainsString($element->getName(), $checkLength);
    }

    public function testCheckLengthButtonDefaultsToZeroWhenConfigsNotSet(): void
    {
        $element = $this->makeElement();
        $toolbar = new XoopsDhtmlToolbar();

        $checkLength = $toolbar->renderCheckLength($element);

        $this->assertStringContainsString('XoopsCheckLength', $checkLength);
        $this->assertStringContainsString('"0"', $checkLength);
    }

    // =========================================================================
    // Stylesheet injection — Part 3 contract
    // =========================================================================

    public function testStylesheetIsInjectedOnceAcrossMultipleEditorsOnOnePage(): void
    {
        $toolbar = new XoopsDhtmlToolbar();

        $first  = $toolbar->render($this->makeElement('editorOne'));
        $second = $toolbar->render($this->makeElement('editorTwo'));

        $this->assertStringContainsString('toolbar.css', $first);
        $this->assertStringNotContainsString('toolbar.css', $second);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeElement(string $name = 'toolbarfield'): XoopsFormDhtmlTextArea
    {
        return new XoopsFormDhtmlTextArea('Caption', $name, 'value', 5, 50, 'xoopsHiddenText');
    }

    /**
     * @param object $obj
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    private function callProtected(object $obj, string $method, array $args = [])
    {
        $ref = new ReflectionMethod($obj, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($obj, $args);
    }

    private function resetStylesheetGuard(): void
    {
        $ref  = new ReflectionClass(XoopsDhtmlToolbar::class);
        $prop = $ref->getProperty('styleInjected');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
    }

    private function injectCodeiconHandler(): void
    {
        $instance = XoopsPreload::getInstance();
        $ref      = new ReflectionClass($instance);
        $prop     = $ref->getProperty('_events');
        $prop->setAccessible(true);
        $events = $prop->getValue($instance);

        // Normalized form of 'core.class.xoopsform.formdhtmltextarea.codeicon': dots stripped,
        // lowercased — see XoopsPreload::triggerEvent().
        $events['coreclassxoopsformformdhtmltextareacodeicon'][] = [
            'class_name' => ToolbarCodeiconTestPreload::class,
            'method'     => 'eventCodeiconHandler',
        ];
        $prop->setValue($instance, $events);
    }
}
