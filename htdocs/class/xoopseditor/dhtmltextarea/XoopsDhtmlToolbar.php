<?php
/**
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Shared DHTML editor toolbar for {@link XoopsFormDhtmlTextArea}.
 *
 * All five form renderers (Legacy, Bootstrap3, Bootstrap4, Bootstrap5, Tailwind) used to carry
 * their own hand-written copy of the xoopscode button row and the typography row, which is why the
 * admin control panel and the front end could show a different toolbar for the very same editor.
 * This class is the single source of truth for that markup: every renderer's
 * {@see XoopsFormRendererInterface::renderFormDhtmlTextArea()} obtains the toolbar from
 * {@see self::render()} and only supplies its own "chrome" (the `<textarea>`, the preview
 * fieldset, and the JS loader shim).
 *
 * The markup is framework-neutral: it uses its own `xo-edtb-*` classes (styled by
 * `assets/toolbar.css`, driven by CSS custom properties) instead of Bootstrap/DaisyUI classes, so
 * it renders identically whether or not a CSS framework is loaded — the admin themes load none.
 *
 * {@see self::renderCodeButtons()}, {@see self::renderTypography()} and
 * {@see self::renderCheckLength()} are `public`, not `protected`: five unrelated renderer classes
 * (not subclasses of this one) need to call them directly to build their thin
 * `renderFormDhtmlTAXoopsCode()`/`renderFormDhtmlTATypography()` delegates, and PHP's `protected`
 * only allows access from the same class or a subclass. They remain overridable by a subclass that
 * wants to adjust a single row.
 *
 * @category  XoopsEditor
 * @package   XoopsDhtmlToolbar
 * @author    XOOPS Project
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class XoopsDhtmlToolbar
{
    /** @var string Base button class */
    private const BTN = 'xo-edtb-btn';

    /** @var string Small button class (button row / dropdown toggles) */
    private const BTN_SM = 'xo-edtb-btn xo-edtb-btn-sm';

    /** @var string Class for a role="group" cluster of related buttons */
    private const GROUP_CLASS = 'xo-edtb-group';

    /** @var string Class for the outer role="toolbar" container */
    private const TOOLBAR_CLASS = 'xo-edtb-toolbar';

    /** @var string Class for a <details> based dropdown */
    private const DROPDOWN_CLASS = 'xo-edtb-dropdown';

    /** @var string Class for a dropdown's <ul> menu */
    private const MENU_CLASS = 'xo-edtb-menu';

    /** @var string Class for a dropdown menu <a> item */
    private const MENU_ITEM_CLASS = 'xo-edtb-menu-item';

    /** @var string Class for the small colour preview swatch in the colour dropdown */
    private const SWATCH_CLASS = 'xo-edtb-swatch';

    /** @var string aria-label for the outer toolbar (deliberately not localized — see report) */
    private const ARIA_TOOLBAR = 'Editor toolbar';

    /**
     * Default fonts used when $GLOBALS['formtextdhtml_fonts'] is not set, matching the default
     * every renderer used before this refactor.
     *
     * @var string[]
     */
    private const DEFAULT_FONTS = [
        'Arial',
        'Courier',
        'Georgia',
        'Helvetica',
        'Impact',
        'Verdana',
        'Haettenschweiler',
    ];

    /**
     * Curated colour palette: 'Display name' => 'hex value'.
     *
     * Legacy's original color dropdown generated a 216-entry list of every 6x6x6 web-safe hex
     * value via a nested JavaScript loop and `document.write` — unusable in a dropdown and not
     * server-rendered. This is a deliberate reduction to 11 curated, editable colours (the palette
     * already used by three of the five renderers before this refactor).
     *
     * @var array<string, string>
     */
    private const COLOR_PALETTE = [
        'Black'  => '000000',
        'Blue'   => '38AAFF',
        'Brown'  => '987857',
        'Green'  => '79D271',
        'Grey'   => '888888',
        'Orange' => 'FFA700',
        'Paper'  => 'E0E0E0',
        'Purple' => '363E98',
        'Red'    => 'FF211E',
        'White'  => 'FEFEFE',
        'Yellow' => 'FFD628',
    ];

    /**
     * Guards stylesheet injection so N editors on a single page only add the `<link>`/theme
     * stylesheet reference once.
     *
     * @var bool
     */
    private static $styleInjected = false;

    /**
     * Render the complete DHTML editor toolbar: the xoopscode button row, the typography row,
     * and the check-length button, wrapped in a single `role="toolbar"` container.
     *
     * @param XoopsFormDhtmlTextArea $element form element the toolbar acts on
     *
     * @return string rendered toolbar HTML
     */
    public function render(XoopsFormDhtmlTextArea $element): string
    {
        $ret  = $this->injectStylesheet();
        $ret .= '<div class="' . self::TOOLBAR_CLASS . '" role="toolbar" aria-label="' . self::ARIA_TOOLBAR . '">';
        $ret .= $this->renderCodeButtons($element);
        $ret .= $this->renderTypography($element);
        $ret .= $this->renderCheckLength($element);
        $ret .= '</div>';

        return $ret;
    }

    /**
     * Render the xoopscode button row (URL, email, image, image manager, smilies, TextSanitizer
     * extension buttons, code, quote), and fire the `codeicon` preload event.
     *
     * @param XoopsFormDhtmlTextArea $element form element (its ->js may be appended to by
     *                                        TextSanitizer extensions)
     *
     * @return string rendered button group
     */
    public function renderCodeButtons(XoopsFormDhtmlTextArea $element): string
    {
        // Raw name deliberately (getName(false)): the value is escaped per-context downstream —
        // json_encode() inside jsCall() for JS arguments, rawurlencode() for the URL query params
        // below — so do NOT "helpfully" restore the default HTML-encoded getName() here.
        $textareaId = $element->getName(false);
        $btn        = self::BTN_SM;

        // Ensure toolbar.css/toolbar.js load even when a renderer subclass calls this helper
        // directly instead of going through render(). The static guard in injectStylesheet()
        // makes this a no-op (empty string) when render() already injected on this request.
        $code  = $this->injectStylesheet();
        $code .= '<a name="moresmiley"></a>';
        // No aria-label/role="group" here (unlike the other groups in this class): the group
        // holds URL, email, image, image-manager, smilies, an open-ended list of TextSanitizer
        // extension buttons, code and quote -- there is no single existing language constant
        // that describes it, and concatenating one from the individual button labels (as the
        // typography groups below do) isn't possible because the extension buttons are appended
        // dynamically. Adding a new constant would require a docs/lang_diff.txt entry for a
        // one-off label, so each button's own aria-label carries the meaning instead.
        $code .= '<div class="' . self::GROUP_CLASS . '">';
        $code .= $this->button($btn, $this->jsCall('xoopsCodeUrl', [$textareaId, _ENTERURL, _ENTERWEBTITLE]), _XOOPS_FORM_ALT_URL, 'fa-solid fa-link');
        $code .= $this->button($btn, $this->jsCall('xoopsCodeEmail', [$textareaId, _ENTEREMAIL, _ENTERWEBTITLE]), _XOOPS_FORM_ALT_EMAIL, 'fa-solid fa-envelope');
        $code .= $this->button($btn, $this->jsCall('xoopsCodeImg', [$textareaId, _ENTERIMGURL, _ENTERIMGPOS, _IMGPOSRORL, _ERRORIMGPOS, _XOOPS_FORM_ALT_ENTERWIDTH]), _XOOPS_FORM_ALT_IMG, 'fa-solid fa-file-image');
        $code .= $this->button($btn, $this->jsCall('openWithSelfMain', [XOOPS_URL . '/imagemanager.php?target=' . rawurlencode($textareaId), 'imgmanager', 400, 430]), _XOOPS_FORM_ALT_IMAGE, 'fa-solid fa-file-image', '<span style="font-size:75%;"> Manager</span>');
        $code .= $this->button($btn, $this->jsCall('openWithSelfMain', [XOOPS_URL . '/misc.php?action=showpopups&type=smilies&target=' . rawurlencode($textareaId), 'smilies', 300, 475]), _XOOPS_FORM_ALT_SMILEY, 'fa-solid fa-face-smile');

        $myts       = \MyTextSanitizer::getInstance();
        $extensions = array_filter($myts->config['extensions']);
        foreach (array_keys($extensions) as $key) {
            $extension = $myts->loadExtension($key);
            if (!$extension) {
                continue;
            }
            $result = $extension->encode($textareaId);
            $encode = $result[0] ?? '';
            $js     = $result[1] ?? '';
            if (empty($encode)) {
                continue;
            }
            // Contract: TextSanitizer extensions hardcode 'btn btn-default btn-sm' in their own
            // encode() output — rewrite it onto our neutral classes here, once, rather than each
            // renderer having its own (previously inconsistent) rewrite rule.
            $code .= $this->rewriteExtensionButtonClasses($encode);
            if (!empty($js)) {
                $element->js .= $js;
            }
        }

        $code .= $this->button($btn, $this->jsCall('xoopsCodeCode', [$textareaId, _ENTERCODE]), _XOOPS_FORM_ALT_CODE, 'fa-solid fa-code');
        $code .= $this->button($btn, $this->jsCall('xoopsCodeQuote', [$textareaId, _ENTERQUOTE]), _XOOPS_FORM_ALT_QUOTE, 'fa-solid fa-quote-right');
        $code .= '</div>';

        // Contract: fired by reference so third-party modules can append buttons, at the same
        // logical point as before — after the core buttons and the extensions, immediately before
        // the code row is returned.
        $xoopsPreload = \XoopsPreload::getInstance();
        $xoopsPreload->triggerEvent('core.class.xoopsform.formdhtmltextarea.codeicon', [&$code]);

        return $code;
    }

    /**
     * Render the typography row: size / font / colour dropdowns, then bold / italic / underline /
     * strikethrough and left / center / right alignment button groups.
     *
     * @param XoopsFormDhtmlTextArea $element form element
     *
     * @return string rendered typography groups
     */
    public function renderTypography(XoopsFormDhtmlTextArea $element): string
    {
        // Raw name deliberately (getName(false)): the value is escaped per-context downstream —
        // json_encode() inside jsCall() for every JS argument below — so do NOT "helpfully"
        // restore the default HTML-encoded getName() here.
        $textareaId = $element->getName(false);
        $hiddenText = (string) $element->_hiddenText;

        // Same direct-call asset guarantee as renderCodeButtons() — no-op after first injection.
        $assets = $this->injectStylesheet();

        // Both globals are documented override points set by arbitrary module code, so their
        // shape cannot be trusted: a non-array would reach array_combine() (TypeError) or
        // dropdown()'s foreach (warning), and a font entry that is not a scalar string would
        // make array_combine() throw on an illegal key type.
        $sizes = isset($GLOBALS['formtextdhtml_sizes']) && is_array($GLOBALS['formtextdhtml_sizes'])
            ? $GLOBALS['formtextdhtml_sizes']
            : [];
        $fonts = !empty($GLOBALS['formtextdhtml_fonts']) && is_array($GLOBALS['formtextdhtml_fonts'])
            ? array_values(array_filter(array_map('strval', array_filter($GLOBALS['formtextdhtml_fonts'], 'is_scalar')), 'strlen'))
            : self::DEFAULT_FONTS;
        if ([] === $fonts) {
            $fonts = self::DEFAULT_FONTS;
        }

        // No size options (the global is normally populated by the formdhtmltextarea language
        // file) means no Size dropdown at all — an empty <details> toggle would render a
        // control that opens an empty menu and does nothing.
        $hasSizes = [] !== $sizes;

        $ret  = $assets . '<div class="' . self::GROUP_CLASS . '" role="group" aria-label="'
            . $this->esc(($hasSizes ? _SIZE . '/' : '') . _FONT . '/' . _COLOR) . '">';
        if ($hasSizes) {
            $ret .= $this->dropdown($textareaId, $hiddenText, 'size', _SIZE, 'fa-solid fa-text-height', $sizes);
        }
        $ret .= $this->dropdown($textareaId, $hiddenText, 'font', _FONT, 'fa-solid fa-font', array_combine($fonts, $fonts));
        $ret .= $this->dropdown($textareaId, $hiddenText, 'color', _COLOR, 'fa-solid fa-palette', array_flip(self::COLOR_PALETTE), true);
        $ret .= '</div>';

        $btn = self::BTN_SM;
        // Unlike the code-buttons group above, this group's contents are a fixed, known set, so
        // (as with the size/font/colour and alignment groups) the aria-label is built from the
        // concatenation of the individual button labels rather than dropped.
        $ret .= '<div class="' . self::GROUP_CLASS . '" role="group" aria-label="' . $this->esc(_XOOPS_FORM_ALT_BOLD . '/' . _XOOPS_FORM_ALT_ITALIC . '/' . _XOOPS_FORM_ALT_UNDERLINE . '/' . _XOOPS_FORM_ALT_LINETHROUGH) . '">';
        $ret .= $this->button($btn, $this->jsCall('xoopsMakeBold', [$hiddenText, $textareaId]), _XOOPS_FORM_ALT_BOLD, 'fa-solid fa-bold');
        $ret .= $this->button($btn, $this->jsCall('xoopsMakeItalic', [$hiddenText, $textareaId]), _XOOPS_FORM_ALT_ITALIC, 'fa-solid fa-italic');
        $ret .= $this->button($btn, $this->jsCall('xoopsMakeUnderline', [$hiddenText, $textareaId]), _XOOPS_FORM_ALT_UNDERLINE, 'fa-solid fa-underline');
        $ret .= $this->button($btn, $this->jsCall('xoopsMakeLineThrough', [$hiddenText, $textareaId]), _XOOPS_FORM_ALT_LINETHROUGH, 'fa-solid fa-strikethrough');
        $ret .= '</div>';

        $ret .= '<div class="' . self::GROUP_CLASS . '" role="group" aria-label="' . $this->esc(_XOOPS_FORM_ALT_LEFT . '/' . _XOOPS_FORM_ALT_CENTER . '/' . _XOOPS_FORM_ALT_RIGHT) . '">';
        $ret .= $this->button($btn, $this->jsCall('xoopsMakeLeft', [$hiddenText, $textareaId]), _XOOPS_FORM_ALT_LEFT, 'fa-solid fa-align-left');
        $ret .= $this->button($btn, $this->jsCall('xoopsMakeCenter', [$hiddenText, $textareaId]), _XOOPS_FORM_ALT_CENTER, 'fa-solid fa-align-center');
        $ret .= $this->button($btn, $this->jsCall('xoopsMakeRight', [$hiddenText, $textareaId]), _XOOPS_FORM_ALT_RIGHT, 'fa-solid fa-align-right');
        $ret .= '</div>';

        return $ret;
    }

    /**
     * Render the check-length button, reading `configs['maxlength']` off the element the same way
     * every renderer did before this refactor.
     *
     * @param XoopsFormDhtmlTextArea $element form element
     *
     * @return string rendered check-length button group
     */
    public function renderCheckLength(XoopsFormDhtmlTextArea $element): string
    {
        $maxlength = 0;
        // 'configs' is declared on XoopsFormDhtmlTextArea, but legacy callers overwrite it with
        // arbitrary values — validate the shape before reading maxlength.
        if (property_exists($element, 'configs') && is_array($element->configs) && isset($element->configs['maxlength'])) {
            $maxlength = (int) $element->configs['maxlength'];
        }

        // Raw name deliberately (getName(false)): the value is escaped per-context downstream —
        // json_encode() inside jsCall() for JS arguments — so do NOT "helpfully" restore the
        // default HTML-encoded getName() here.
        $onclick = $this->jsCall('XoopsCheckLength', [$element->getName(false), (string) $maxlength, _XOOPS_FORM_ALT_LENGTH, _XOOPS_FORM_ALT_LENGTH_MAX]);

        return '<div class="' . self::GROUP_CLASS . '" role="group" aria-label="' . $this->esc(_XOOPS_FORM_ALT_CHECKLENGTH) . '">'
            . $this->button(self::BTN_SM, $onclick, _XOOPS_FORM_ALT_CHECKLENGTH, 'fa-solid fa-square-check')
            . '</div>';
    }

    /**
     * Rewrite the class attribute TextSanitizer extensions hardcode ('btn btn-default btn-sm' or
     * 'btn btn-default') onto this toolbar's neutral classes. Also catches the per-renderer
     * remaps that existed before this refactor ('btn-secondary'), so extension output is
     * consistent no matter which renderer/version produced it.
     *
     * @param string $html extension-supplied button HTML
     *
     * @return string HTML with neutral classes substituted in
     */
    protected function rewriteExtensionButtonClasses(string $html): string
    {
        return str_replace(
            ['btn btn-default btn-sm', 'btn btn-default', 'btn-secondary', 'btn-default'],
            [self::BTN_SM, self::BTN, self::BTN, self::BTN],
            $html
        );
    }

    /**
     * Escape a value for an HTML attribute context.
     *
     * @param string $value raw value
     *
     * @return string escaped value
     */
    protected function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Render a single toolbar button.
     *
     * @param string $class        button CSS class(es)
     * @param string $onclickJs    JavaScript expression for the onclick handler (already a
     *                             complete statement, e.g. from {@see self::jsCall()})
     * @param string $title        tooltip text (escaped internally)
     * @param string $iconClass    Font Awesome icon class string
     * @param string $trailingHtml optional pre-built HTML appended after the icon
     *
     * @return string rendered <button> element
     */
    protected function button(string $class, string $onclickJs, string $title, string $iconClass, string $trailingHtml = ''): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return "<button type='button' class='" . $class . "' onclick='" . $onclickJs . "' title='"
            . $escapedTitle . "' aria-label='" . $escapedTitle . "'>"
            . "<span class='" . $iconClass . "' aria-hidden='true'></span>" . $trailingHtml . '</button>';
    }

    /**
     * Render a single `<details>`/`<summary>` dropdown (size, font, or colour). Using native
     * `<details>` keeps the toolbar framework-neutral and script-free for the menu interaction
     * itself — no Bootstrap/DaisyUI JS or data-attributes are required.
     *
     * @param string               $textareaId id of the textarea the dropdown acts on
     * @param string               $hiddenText hidden-text element id passed to xoopsSetElementAttribute
     * @param string               $attr       attribute name passed to xoopsSetElementAttribute ('size'|'font'|'color')
     * @param string               $label      dropdown button tooltip / aria-label
     * @param string               $iconClass  Font Awesome icon class string for the toggle button
     * @param array<int|string, string> $options    attrValue => display label
     * @param bool                 $isColor    when true, render a colour swatch (attrValue is a hex string)
     *
     * @return string rendered dropdown
     */
    protected function dropdown(string $textareaId, string $hiddenText, string $attr, string $label, string $iconClass, array $options, bool $isColor = false): string
    {
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // name= makes sibling <details> mutually exclusive natively (HTML "exclusive accordion"),
        // so opening Font closes Size with no script at all. Scoped per textarea id so two
        // editors on one page do not close each other's menus. assets/toolbar.js provides the
        // same behaviour for browsers that predate the attribute, plus click-outside/Escape.
        $groupName = 'xo-edtb-' . preg_replace('/[^A-Za-z0-9_-]/', '', $textareaId);

        $ret = '<details class="' . self::DROPDOWN_CLASS . '" name="' . $groupName . '">'
            . '<summary class="' . self::BTN_SM . '" title="' . $escapedLabel . '" aria-label="' . $escapedLabel . '">'
            . '<span class="' . $iconClass . '" aria-hidden="true"></span></summary>'
            . '<ul class="' . self::MENU_CLASS . '">';

        foreach ($options as $attrValue => $display) {
            $onclick = $this->jsCall('xoopsSetElementAttribute', [$attr, (string) $attrValue, $textareaId, $hiddenText]);
            $escapedDisplay = htmlspecialchars((string) $display, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $swatch = '';
            if ($isColor) {
                $swatch = '<span class="' . self::SWATCH_CLASS . '" style="background-color:#'
                    . htmlspecialchars((string) $attrValue, ENT_QUOTES | ENT_HTML5, 'UTF-8') . ';"></span>';
            }
            $ret .= '<li><a href="#" class="' . self::MENU_ITEM_CLASS . '" onclick=\'' . $onclick
                . ' this.closest("details").removeAttribute("open"); return false;\'>' . $swatch . $escapedDisplay . '</a></li>';
        }

        $ret .= '</ul></details>';

        return $ret;
    }

    /**
     * Build a JavaScript function-call expression as a complete statement (trailing `;`), string
     * arguments JSON-encoded so the result is safe inside a single-quoted `onclick='...'` attribute.
     * Integer/float arguments are emitted unquoted.
     *
     * The value crosses two decoders before it runs: the browser's HTML-attribute decoder, then the
     * JS parser. `htmlspecialchars()` only escapes for the first — the browser decodes `&quot;` back
     * to `"` before the JS parser ever sees it, so a string argument containing `"` can close the JS
     * string literal early (e.g. `x");alert(1);//`) and inject arbitrary script. `json_encode()`
     * with the `JSON_HEX_*` flags instead emits `"`, `<`, `&`, `'` — sequences
     * that survive HTML-attribute decoding unchanged, so the value stays a single JS string literal
     * no matter which decoder runs first. json_encode() also supplies its own surrounding quotes.
     *
     * @param string                    $fn   JavaScript function name (hard-coded literal — never
     *                                        build this from user input)
     * @param array<int, int|float|string> $args arguments, in order
     *
     * @return string JavaScript statement, e.g. `xoopsCodeUrl("id", "Enter URL");`
     */
    protected function jsCall(string $fn, array $args): string
    {
        $parts = [];
        foreach ($args as $arg) {
            if (is_int($arg) || is_float($arg)) {
                $parts[] = (string) $arg;
            } else {
                try {
                    $parts[] = json_encode((string) $arg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    // A malformed argument (typically invalid UTF-8 in a translated label) must
                    // degrade to one broken button argument, not abort the whole form render.
                    trigger_error('XoopsDhtmlToolbar: could not encode a toolbar argument for ' . $fn . '(): ' . $e->getMessage(), E_USER_WARNING);
                    $parts[] = '""';
                }
            }
        }

        return $fn . '(' . implode(', ', $parts) . ');';
    }

    /**
     * Inject the toolbar stylesheet once per request: via `$xoTheme->addStylesheet()` when a theme
     * is available, otherwise as an inline `<link>` tag — mirroring how the renderers already fall
     * back for `image.js`. Guarded by a static flag so N editors on one page inject it once.
     *
     * @return string a `<link>` tag when no theme is available, otherwise an empty string
     */
    protected function injectStylesheet(): string
    {
        if (self::$styleInjected) {
            return '';
        }
        self::$styleInjected = true;

        $href = 'class/xoopseditor/dhtmltextarea/assets/toolbar.css';
        $src  = 'class/xoopseditor/dhtmltextarea/assets/toolbar.js';

        if (!empty($GLOBALS['xoTheme']) && is_object($GLOBALS['xoTheme'])) {
            $GLOBALS['xoTheme']->addStylesheet($href);
            $GLOBALS['xoTheme']->addScript($src);

            return '';
        }

        return '<link rel="stylesheet" type="text/css" href="' . XOOPS_URL . '/' . $href . '">' . "\n"
            . '<script src="' . XOOPS_URL . '/' . $src . '"></script>' . "\n";
    }
}
