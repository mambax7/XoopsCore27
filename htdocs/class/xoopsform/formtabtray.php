<?php
/**
 * XOOPS form element - tabbed group of elements
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

require_once __DIR__ . '/renderer/XoopsFormTabRendererInterface.php';

/**
 * A tabbed group of form elements.
 *
 * Like {@link XoopsFormElementTray} this is a container element: you add it to a
 * form with $form->addElement($tabTray), and the elements you place inside it
 * stay part of the owning form, so "required" handling and the form's generated
 * client-side validation work across every tab automatically.
 *
 * Usage:
 * <code>
 * $tabs = new XoopsFormTabTray('', 'mytabs');
 * $tabs->addTab(_MD_CONTENT);
 * $tabs->addElement(new XoopsFormText(...), true);
 * $tabs->addTab(_MD_META);
 * $tabs->addElement(new XoopsFormText(...));
 * $form->addElement($tabs);
 * </code>
 *
 * Presentation is delegated: when the active renderer implements
 * {@link XoopsFormTabRendererInterface} that renderer produces the markup
 * (allowing Bootstrap/Tailwind/etc. tab styles); otherwise the element emits a
 * framework-neutral fallback whose panes are only collapsed once JavaScript adds
 * the `js-xoops-tabs` hook — so with scripting disabled every pane stays visible
 * and the form remains fully usable.
 *
 * @category  XoopsForm
 * @package   XoopsFormTabTray
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class XoopsFormTabTray extends XoopsFormElement
{
    /**
     * Tab panes: each entry is ['title' => string, 'elements' => XoopsFormElement[]].
     *
     * @var array
     */
    private $_tabs = [];

    /**
     * Flat list of every child element, in add order (mirrors XoopsFormElementTray
     * so getElements() can be returned by reference for the owning form).
     *
     * @var XoopsFormElement[]
     */
    private $_elements = [];

    /**
     * Required child elements, bubbled up to the owning form.
     *
     * @var XoopsFormElement[]
     */
    public $_required = [];

    /**
     * Index of the tab currently receiving addElement(); -1 until the first tab.
     *
     * @var int
     */
    private $_currentTab = -1;

    /**
     * Index of the tab that should be shown first when rendered.
     *
     * @var int
     */
    private $_activeTab = 0;

    /**
     * Process-wide counter giving each rendered tray a unique DOM id even when
     * the element has no name.
     *
     * @var int
     */
    private static $idSeq = 0;

    /**
     * Emit the shared fallback CSS/JS only once per request.
     *
     * @var bool
     */
    protected static $assetsRendered = false;

    /**
     * Constructor.
     *
     * @param string $caption caption for the group (often empty for tabs)
     * @param string $name    element name, used as a DOM id prefix when present
     */
    public function __construct($caption = '', $name = '')
    {
        // XoopsFormElement::__construct() is a guard that exit()s to prevent
        // instantiating the base class, so containers set name/caption directly
        // (matching XoopsFormElementTray) rather than chaining to the parent.
        $this->setName($name);
        $this->setCaption($caption);
    }

    /**
     * This element contains other elements.
     *
     * @return bool true
     */
    public function isContainer()
    {
        return true;
    }

    /**
     * Are there any required elements anywhere in the tabs?
     *
     * @return bool
     */
    public function isRequired()
    {
        return !empty($this->_required);
    }

    /**
     * Start a new tab pane. Elements added afterwards belong to it until the
     * next addTab() call.
     *
     * @param string $title tab label
     *
     * @return int the new tab's index
     */
    public function addTab($title)
    {
        $this->_tabs[]     = ['title' => (string) $title, 'elements' => []];
        $this->_currentTab = count($this->_tabs) - 1;

        return $this->_currentTab;
    }

    /**
     * Add an element to the current tab. If no tab has been started yet, an
     * untitled one is created so the element is never lost.
     *
     * @param XoopsFormElement $formElement element to add
     * @param bool             $required    mark the element required?
     */
    public function addElement(XoopsFormElement $formElement, $required = false)
    {
        if ($this->_currentTab < 0) {
            $this->addTab('');
        }
        $this->_tabs[$this->_currentTab]['elements'][] = $formElement;
        $this->_elements[]                             = $formElement;

        if (!$formElement->isContainer()) {
            if ($required) {
                $formElement->_required = true;
                $this->_required[]      = $formElement;
            }
        } else {
            $required_elements = $formElement->getRequired();
            $count             = count($required_elements);
            for ($i = 0; $i < $count; ++$i) {
                $this->_required[] = &$required_elements[$i];
            }
        }
    }

    /**
     * Get the required elements (by reference, so the owning form can merge them).
     *
     * @return XoopsFormElement[]
     */
    public function &getRequired()
    {
        return $this->_required;
    }

    /**
     * Get the tab panes for a renderer to lay out.
     *
     * @return array list of ['title' => string, 'elements' => XoopsFormElement[]]
     */
    public function getTabs()
    {
        return $this->_tabs;
    }

    /**
     * Get the index of the active tab.
     *
     * @return int
     */
    public function getActiveTab()
    {
        return $this->_activeTab;
    }

    /**
     * Set the index of the active tab. The value is clamped to the range of
     * existing tabs so an out-of-range index can never leave every pane hidden.
     *
     * @param int $index tab index
     *
     * @return void
     */
    public function setActiveTab($index)
    {
        $index = (int) $index;
        $count = count($this->_tabs);
        $this->_activeTab = ($count > 0) ? max(0, min($index, $count - 1)) : 0;
    }

    /**
     * Get the child elements. With $recurse, nested containers are flattened so
     * the owning form sees every leaf element (for validation JS, etc.).
     *
     * @param bool $recurse flatten nested containers?
     *
     * @return XoopsFormElement[]
     */
    public function &getElements($recurse = false)
    {
        if (!$recurse) {
            return $this->_elements;
        }

        $ret   = [];
        $count = count($this->_elements);
        for ($i = 0; $i < $count; ++$i) {
            if (!$this->_elements[$i]->isContainer()) {
                $ret[] = &$this->_elements[$i];
            } else {
                $elements = &$this->_elements[$i]->getElements(true);
                $count2   = count($elements);
                for ($j = 0; $j < $count2; ++$j) {
                    $ret[] = &$elements[$j];
                }
                unset($elements);
            }
        }

        return $ret;
    }

    /**
     * Render the tabbed group. Delegates to a tab-aware renderer when one is
     * active; otherwise emits the self-contained fallback.
     *
     * @return string
     */
    public function render()
    {
        $renderer = XoopsFormRenderer::getInstance()->get();
        if ($renderer instanceof XoopsFormTabRendererInterface) {
            return $renderer->renderFormTabTray($this);
        }

        return $this->renderFallback();
    }

    /**
     * Framework-neutral rendering used when the active renderer has no tab
     * support. Panes are hidden only under the JS-added `js-xoops-tabs` hook, so
     * the form degrades to all-panes-visible without JavaScript.
     *
     * @return string
     */
    protected function renderFallback()
    {
        $base   = $this->getName(false);
        $id     = 'xoops_tabs_' . ('' !== $base ? preg_replace('/[^a-z0-9]+/i', '', $base) . '_' : '') . ++self::$idSeq;
        $hidden = '';
        $nav    = '';
        $panes  = '';

        foreach ($this->_tabs as $k => $tab) {
            $isActive = ($this->_activeTab === $k);
            $active   = $isActive ? ' xoops-tab-active' : '';
            $paneId   = $id . '_' . $k;
            $tabId    = $id . '_tab_' . $k;

            $nav .= '<li class="xoops-tab' . $active . '" role="presentation">'
                  . '<a id="' . $tabId . '" href="#' . $paneId . '" data-xoops-tab="' . $k . '"'
                  . ' role="tab" aria-controls="' . $paneId . '" aria-selected="' . ($isActive ? 'true' : 'false') . '"'
                  . ' title="' . htmlspecialchars((string) $tab['title'], ENT_QUOTES | ENT_HTML5) . '">'
                  . htmlspecialchars((string) $tab['title'], ENT_QUOTES | ENT_HTML5) . '</a></li>';

            $rows = '';
            foreach ($tab['elements'] as $ele) {
                if ($ele->isHidden()) {
                    $hidden .= $ele->render();
                    continue;
                }
                $rows .= $this->renderRow($ele);
            }
            $panes .= '<fieldset class="xoops-tabpane' . $active . '" id="' . $paneId . '"'
                    . ' role="tabpanel" aria-labelledby="' . $tabId . '">'
                    . '<table class="outer" cellspacing="1">' . $rows . '</table></fieldset>';
        }

        return '<div class="xoops-tabs" id="' . $id . '"><ul class="xoops-tabnav" role="tablist">' . $nav . '</ul>'
            . $panes . '</div>' . $hidden . $this->renderTabAssets();
    }

    /**
     * Render one caption/control row for a visible element.
     *
     * @param XoopsFormElement $ele element to render
     *
     * @return string
     */
    protected function renderRow(XoopsFormElement $ele)
    {
        $ret     = '<tr valign="top" align="left"><td class="head">';
        $caption = $ele->getCaption();
        if ('' !== $caption) {
            $ret .= '<div class="xoops-form-element-caption' . ($ele->isRequired() ? '-required' : '') . '">'
                  . '<span class="caption-text">' . $caption . '</span>';
            if ($ele->isRequired()) {
                $ret .= '<span class="caption-marker">*</span>';
            }
            $ret .= '</div>';
        }
        $desc = $ele->getDescription();
        if ('' !== $desc) {
            $ret .= '<div class="xoops-form-element-help">' . $desc . '</div>';
        }
        $ret .= '</td><td class="even">' . $ele->render() . '</td></tr>';

        return $ret;
    }

    /**
     * Inline CSS + vanilla-JS tab switcher for the fallback, emitted once per
     * request. The script adds `js-xoops-tabs`, under which inactive panes are
     * hidden; without JavaScript no pane is ever hidden.
     *
     * @return string
     */
    protected function renderTabAssets()
    {
        if (self::$assetsRendered) {
            return '';
        }
        self::$assetsRendered = true;

        $css = '<style>'
             . '.xoops-tabnav{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:2px;border-bottom:1px solid #ccc}'
             . '.xoops-tabnav li a{display:block;padding:6px 14px;text-decoration:none;border:1px solid transparent;border-bottom:none}'
             . '.xoops-tabnav li.xoops-tab-active a{border-color:#ccc;background:#fff;font-weight:bold;border-radius:4px 4px 0 0}'
             . '.xoops-tabpane{border:1px solid #ccc;border-top:none;padding:10px;margin:0 0 10px}'
             . '.js-xoops-tabs .xoops-tabpane:not(.xoops-tab-active){display:none}'
             . '</style>';

        $js = '<script>(function(){'
            . "var roots=document.querySelectorAll('.xoops-tabs');"
            . 'Array.prototype.forEach.call(roots,function(root){'
            . "if(root.classList.contains('js-xoops-tabs')){return;}"
            . "root.classList.add('js-xoops-tabs');"
            . "var links=root.querySelectorAll('.xoops-tabnav a');"
            . 'Array.prototype.forEach.call(links,function(link){'
            . "link.addEventListener('click',function(e){"
            . 'e.preventDefault();'
            . "var k=link.getAttribute('data-xoops-tab');"
            . "Array.prototype.forEach.call(root.querySelectorAll('.xoops-tabnav li'),function(li){li.classList.remove('xoops-tab-active');});"
            . "Array.prototype.forEach.call(root.querySelectorAll('.xoops-tabnav a'),function(a){a.setAttribute('aria-selected','false');});"
            . "link.parentNode.classList.add('xoops-tab-active');"
            . "link.setAttribute('aria-selected','true');"
            . "Array.prototype.forEach.call(root.querySelectorAll('.xoops-tabpane'),function(p){p.classList.remove('xoops-tab-active');});"
            . "var pane=document.getElementById(root.id+'_'+k);"
            . "if(pane){pane.classList.add('xoops-tab-active');}"
            . '});});});'
            . '})();</script>';

        return $css . $js;
    }
}
