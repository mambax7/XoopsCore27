<?php

namespace xoopsforms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/bootstrap.php';

xoops_load('XoopsFormElement');
xoops_load('XoopsFormElementTray');
xoops_load('XoopsFormTabTray');
xoops_load('XoopsFormTabRendererInterface');
xoops_load('XoopsFormText');
xoops_load('XoopsFormHidden');
xoops_load('XoopsFormRenderer');
xoops_load('XoopsFormRendererInterface');
xoops_load('XoopsFormRendererLegacy');
xoops_load('XoopsFormRendererBootstrap3');
xoops_load('XoopsFormRendererBootstrap4');
xoops_load('XoopsFormRendererBootstrap5');
xoops_load('XoopsFormRendererTailwind');

/**
 * Tests for XoopsFormTabTray
 */
#[CoversClass(\XoopsFormTabTray::class)]
class XoopsFormTabTrayTest extends TestCase
{
    /**
     * @var \XoopsFormTabTray
     */
    protected $tray;

    protected function setUp(): void
    {
        // Start each test with a renderer that has no tab support, so render()
        // exercises the framework-neutral fallback unless a test opts in.
        \XoopsFormRenderer::getInstance()->set(new \XoopsFormRendererLegacy());
        $this->tray = new \XoopsFormTabTray('Caption', 'myTabs');
    }

    // ------------------------------------------------------------------
    //  Constructor / container semantics
    // ------------------------------------------------------------------

    public function testConstructorSetsCaptionAndName(): void
    {
        $this->assertSame('Caption', $this->tray->getCaption());
        $this->assertSame('myTabs', $this->tray->getName(false));
    }

    public function testIsContainerReturnsTrue(): void
    {
        $this->assertTrue($this->tray->isContainer());
    }

    public function testIsRequiredReturnsFalseWhenEmpty(): void
    {
        $this->assertFalse($this->tray->isRequired());
    }

    public function testIsRequiredReturnsTrueWhenChildIsRequired(): void
    {
        $this->tray->addTab('General');
        $this->tray->addElement(new \XoopsFormText('Name', 'name', 25, 100), true);
        $this->assertTrue($this->tray->isRequired());
    }

    // ------------------------------------------------------------------
    //  addTab / addElement
    // ------------------------------------------------------------------

    public function testAddTabReturnsIncrementingIndex(): void
    {
        $this->assertSame(0, $this->tray->addTab('First'));
        $this->assertSame(1, $this->tray->addTab('Second'));
    }

    public function testAddElementWithoutTabAutoCreatesFirstTab(): void
    {
        $this->tray->addElement(new \XoopsFormText('Name', 'name', 25, 100));

        $tabs = $this->tray->getTabs();
        $this->assertCount(1, $tabs);
        $this->assertSame('', $tabs[0]['title']);
        $this->assertCount(1, $tabs[0]['elements']);
    }

    public function testElementsLandInTheCurrentTab(): void
    {
        $this->tray->addTab('One');
        $this->tray->addElement(new \XoopsFormText('A', 'a', 25, 100));
        $this->tray->addTab('Two');
        $this->tray->addElement(new \XoopsFormText('B', 'b', 25, 100));
        $this->tray->addElement(new \XoopsFormText('C', 'c', 25, 100));

        $tabs = $this->tray->getTabs();
        $this->assertCount(2, $tabs);
        $this->assertSame('One', $tabs[0]['title']);
        $this->assertCount(1, $tabs[0]['elements']);
        $this->assertSame('Two', $tabs[1]['title']);
        $this->assertCount(2, $tabs[1]['elements']);
    }

    // ------------------------------------------------------------------
    //  getElements (recursive vs non-recursive)
    // ------------------------------------------------------------------

    public function testGetElementsNonRecursiveReturnsDirectChildren(): void
    {
        $inner = new \XoopsFormElementTray('Inner');
        $inner->addElement(new \XoopsFormText('Inner', 'inner', 25, 100));

        $this->tray->addTab('Tab');
        $this->tray->addElement(new \XoopsFormText('Outer', 'outer', 25, 100));
        $this->tray->addElement($inner);

        $elements = $this->tray->getElements(false);
        $this->assertCount(2, $elements);
        $this->assertInstanceOf(\XoopsFormElementTray::class, $elements[1]);
    }

    public function testGetElementsRecursiveFlattensNestedContainers(): void
    {
        $inner = new \XoopsFormElementTray('Inner');
        $inner->addElement(new \XoopsFormText('Inner', 'inner', 25, 100));

        $this->tray->addTab('Tab');
        $this->tray->addElement(new \XoopsFormText('Outer', 'outer', 25, 100));
        $this->tray->addElement($inner);

        $elements = $this->tray->getElements(true);
        $this->assertCount(2, $elements);
        $this->assertSame('outer', $elements[0]->getName(false));
        $this->assertSame('inner', $elements[1]->getName(false));
    }

    // ------------------------------------------------------------------
    //  getRequired (required bubbling)
    // ------------------------------------------------------------------

    public function testGetRequiredReturnsEmptyArrayByDefault(): void
    {
        $required = $this->tray->getRequired();
        $this->assertIsArray($required);
        $this->assertCount(0, $required);
    }

    public function testGetRequiredCollectsRequiredLeavesAcrossTabs(): void
    {
        $this->tray->addTab('One');
        $req = new \XoopsFormText('Req', 'req', 25, 100);
        $this->tray->addElement($req, true);
        $this->tray->addElement(new \XoopsFormText('Opt', 'opt', 25, 100), false);

        $this->tray->addTab('Two');
        $this->tray->addElement(new \XoopsFormText('Req2', 'req2', 25, 100), true);

        $required = $this->tray->getRequired();
        $this->assertCount(2, $required);
        $this->assertSame($req, $required[0]);
        $this->assertTrue($req->isRequired());
    }

    public function testGetRequiredBubblesFromNestedContainer(): void
    {
        $inner = new \XoopsFormElementTray('Inner');
        $deep  = new \XoopsFormText('Deep', 'deep', 25, 100);
        $inner->addElement($deep, true);

        $this->tray->addTab('Tab');
        $this->tray->addElement($inner);

        $required = $this->tray->getRequired();
        $this->assertCount(1, $required);
        $this->assertSame($deep, $required[0]);
        $this->assertTrue($this->tray->isRequired());
    }

    public function testGetRequiredReturnsByReference(): void
    {
        $this->tray->addTab('Tab');
        $text = new \XoopsFormText('Name', 'name', 25, 100);
        $this->tray->addElement($text, true);

        $required = &$this->tray->getRequired();
        $this->assertSame($text, $required[0]);
    }

    // ------------------------------------------------------------------
    //  render() — fallback path
    // ------------------------------------------------------------------

    public function testFallbackRenderEmitsTabSemantics(): void
    {
        $this->tray->addTab('One');
        $this->tray->addElement(new \XoopsFormText('Name', 'name', 25, 100));
        $this->tray->addTab('Two');
        $this->tray->addElement(new \XoopsFormText('Mail', 'mail', 25, 100));

        $html = $this->tray->render();

        $this->assertIsString($html);
        $this->assertStringContainsString('class="xoops-tabs"', $html);
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('role="tab"', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
        $this->assertStringContainsString('aria-controls="', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('aria-labelledby="', $html);
    }

    public function testFallbackRenderPlacesHiddenFieldsOutsidePanes(): void
    {
        $this->tray->addTab('One');
        $this->tray->addElement(new \XoopsFormText('Name', 'name', 25, 100));
        $this->tray->addElement(new \XoopsFormHidden('secret', 'value'));

        $html = $this->tray->render();

        $this->assertStringContainsString('name="secret"', $html);
        // The hidden input is emitted after the tab panes, never inside a pane,
        // so it appears later in the markup than the last closing </fieldset>.
        $hiddenPos        = strpos($html, 'name="secret"');
        $lastPaneClosePos = strrpos($html, '</fieldset>');
        $this->assertNotFalse($hiddenPos);
        $this->assertNotFalse($lastPaneClosePos);
        $this->assertGreaterThan($lastPaneClosePos, $hiddenPos);
    }

    // ------------------------------------------------------------------
    //  render() — delegation to a tab-aware renderer
    // ------------------------------------------------------------------

    public function testRenderDelegatesToTabAwareRenderer(): void
    {
        $renderer = new class extends \XoopsFormRendererLegacy implements \XoopsFormTabRendererInterface {
            public function renderFormTabTray(\XoopsFormTabTray $element): string
            {
                return 'DELEGATED:' . count($element->getTabs());
            }
        };
        \XoopsFormRenderer::getInstance()->set($renderer);

        $this->tray->addTab('One');
        $this->tray->addTab('Two');

        $this->assertSame('DELEGATED:2', $this->tray->render());
    }

    // ------------------------------------------------------------------
    //  Themed renderer output (Bootstrap 5 / Tailwind)
    // ------------------------------------------------------------------

    public function testBootstrap5RendererEmitsNavTabsAndBs5Classes(): void
    {
        $this->tray->addTab('One');
        $this->tray->addElement(new \XoopsFormText('Name', 'name', 25, 100));

        $html = (new \XoopsFormRendererBootstrap5())->renderFormTabTray($this->tray);

        $this->assertStringContainsString('nav nav-tabs', $html);
        $this->assertStringContainsString('data-bs-toggle="tab"', $html);
        $this->assertStringContainsString('tab-pane', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
        // Bootstrap 5 row classes (not the removed form-group / col-xs-*).
        $this->assertStringContainsString('row mb-3', $html);
        $this->assertStringContainsString('col-12', $html);
        $this->assertStringNotContainsString('form-group', $html);
        $this->assertStringNotContainsString('col-xs-12', $html);
    }

    public function testTailwindRendererEmitsRadioTablistWithAria(): void
    {
        $this->tray->addTab('One');
        $this->tray->addElement(new \XoopsFormText('Name', 'name', 25, 100));

        $html = (new \XoopsFormRendererTailwind())->renderFormTabTray($this->tray);

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('role="tab"', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
        $this->assertStringContainsString('aria-controls="', $html);
        $this->assertStringContainsString('aria-labelledby="', $html);
    }

    public function testBootstrap3RendererEmitsNavTabs(): void
    {
        $this->tray->addTab('One');
        $this->tray->addElement(new \XoopsFormText('Name', 'name', 25, 100));

        $html = (new \XoopsFormRendererBootstrap3())->renderFormTabTray($this->tray);

        $this->assertStringContainsString('nav nav-tabs', $html);
        $this->assertStringContainsString('data-toggle="tab"', $html);
        $this->assertStringContainsString('tab-pane', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
    }

    public function testBootstrap4RendererEmitsNavTabs(): void
    {
        $this->tray->addTab('One');
        $this->tray->addElement(new \XoopsFormText('Name', 'name', 25, 100));

        $html = (new \XoopsFormRendererBootstrap4())->renderFormTabTray($this->tray);

        $this->assertStringContainsString('nav nav-tabs', $html);
        $this->assertStringContainsString('data-toggle="tab"', $html);
        $this->assertStringContainsString('tab-pane', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
    }

    // ------------------------------------------------------------------
    //  Active tab management
    // ------------------------------------------------------------------

    public function testGetActiveTabDefaultsToZero(): void
    {
        $this->assertSame(0, $this->tray->getActiveTab());
    }

    public function testSetActiveTabStoresIndex(): void
    {
        $this->tray->addTab('One');
        $this->tray->addTab('Two');
        $this->tray->setActiveTab(1);
        $this->assertSame(1, $this->tray->getActiveTab());
    }

    public function testSetActiveTabClampsOutOfRangeIndex(): void
    {
        $this->tray->addTab('One');
        $this->tray->addTab('Two');
        $this->tray->setActiveTab(99);
        // Clamped to the last existing tab, never beyond.
        $this->assertSame(1, $this->tray->getActiveTab());
    }

    public function testSetActiveTabClampsNegativeIndex(): void
    {
        $this->tray->addTab('One');
        $this->tray->addTab('Two');
        $this->tray->setActiveTab(-5);
        $this->assertSame(0, $this->tray->getActiveTab());
    }

    public function testFallbackDefaultsToFirstTabActive(): void
    {
        $this->tray->addTab('One');
        $this->tray->addElement(new \XoopsFormText('A', 'a', 25, 100));
        $this->tray->addTab('Two');
        $this->tray->addElement(new \XoopsFormText('B', 'b', 25, 100));

        $html = $this->tray->render();

        // Exactly one tab is selected, and it is the first one (appears before
        // the unselected tab in document order).
        $this->assertSame(1, substr_count($html, 'aria-selected="true"'));
        $this->assertLessThan(
            strpos($html, 'aria-selected="false"'),
            strpos($html, 'aria-selected="true"')
        );
    }

    public function testFallbackRespectsActiveTab(): void
    {
        $this->tray->addTab('One');
        $this->tray->addElement(new \XoopsFormText('A', 'a', 25, 100));
        $this->tray->addTab('Two');
        $this->tray->addElement(new \XoopsFormText('B', 'b', 25, 100));
        $this->tray->setActiveTab(1);

        $html = $this->tray->render();

        // Still exactly one selected tab, now the second one (appears after the
        // unselected first tab in document order).
        $this->assertSame(1, substr_count($html, 'aria-selected="true"'));
        $this->assertGreaterThan(
            strpos($html, 'aria-selected="false"'),
            strpos($html, 'aria-selected="true"')
        );
    }

    public function testBootstrap5RespectsActiveTab(): void
    {
        $this->tray->addTab('One');
        $this->tray->addElement(new \XoopsFormText('A', 'a', 25, 100));
        $this->tray->addTab('Two');
        $this->tray->addElement(new \XoopsFormText('B', 'b', 25, 100));
        $this->tray->setActiveTab(1);

        $html = (new \XoopsFormRendererBootstrap5())->renderFormTabTray($this->tray);

        // The second nav button carries the active state.
        $this->assertSame(1, substr_count($html, 'aria-selected="true"'));
        $this->assertGreaterThan(
            strpos($html, 'aria-selected="false"'),
            strpos($html, 'aria-selected="true"')
        );
    }
}
