<?php
/**
 * SCEditor (BBCode) Editor for XOOPS
 *
 * A lightweight BBCode SOURCE-mode editor based on SCEditor
 * (https://github.com/samclarke/SCEditor, MIT licensed — see INSTALL.md).
 * The SCEditor distribution ships with XOOPS under minified/ in its upstream
 * release layout; isActive() still verifies the required files are readable,
 * so a deployment that strips the library out leaves this editor inert (it
 * simply does not appear in the editor list) rather than broken.
 *
 * This plugin only ever runs SCEditor in BBCode source mode, never WYSIWYG:
 * in WYSIWYG mode any tag SCEditor's format table does not recognise is
 * silently stripped when content round-trips through HTML. Source mode never
 * performs that round-trip, so existing posts using XOOPS-specific or
 * unrecognised BBCode (including arbitrary smilie text codes) cannot be
 * corrupted.
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             class
 * @subpackage          editor
 * @since               2.8.0
 * @author              XOOPS Development Team
 * @see                 https://github.com/samclarke/SCEditor
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

xoops_load('XoopsEditor');

/**
 * Class FormSCEditor
 *
 * @category  XoopsEditor
 * @package   SCEditor
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class FormSCEditor extends XoopsEditor
{
    public string $width  = '100%';
    public string $height = '400px';

    /**
     * Normalize a configured width before it reaches the typed property.
     * XoopsEditor::__construct() routes config keys through set*() methods when they exist,
     * so without this a caller passing an int (e.g. 400) would hit a TypeError.
     *
     * @param mixed $width CSS length or bare number (treated as pixels)
     *
     * @return void
     */
    public function setWidth($width): void
    {
        $this->width = $this->normalizeCssLength($width, $this->width);
    }

    /**
     * Normalize a configured height before it reaches the typed property.
     *
     * @param mixed $height CSS length or bare number (treated as pixels)
     *
     * @return void
     */
    public function setHeight($height): void
    {
        $this->height = $this->normalizeCssLength($height, $this->height);
    }

    /**
     * Accept a CSS length as string or number; bare numbers become pixels, anything
     * non-scalar keeps the current value.
     *
     * @param mixed  $value   incoming configuration value
     * @param string $current value to keep when the input is unusable
     *
     * @return string normalized CSS length
     */
    private function normalizeCssLength($value, string $current): string
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            return trim((string) $value) . 'px';
        }
        if (is_string($value) && '' !== trim($value)) {
            return trim($value);
        }

        return $current;
    }

    /**
     * FormSCEditor::__construct()
     *
     * @param array $configs
     */
    public function __construct(array $configs = [])
    {
        $this->rootPath = '/class/xoopseditor/sceditor';
        parent::__construct($configs);
    }

    /**
     * Returns true only when both the SCEditor core library and our BBCode
     * dialect are readable, so a half-installed library (only one of the two
     * files present) never renders a broken editor.
     *
     * @return bool
     */
    public function isActive()
    {
        // Paths follow the upstream SCEditor release layout, which ships everything under
        // minified/ (sceditor.min.js, formats/, themes/). formats/bbcode.js is required, not
        // optional: without it sceditor has no "bbcode" format and create() would fail.
        // js/xoops-bbcode.js is ours and layers the XOOPS dialect on top of that format.
        $root = XOOPS_ROOT_PATH . $this->rootPath;

        $this->isEnabled = is_readable($root . '/minified/sceditor.min.js')
            && is_readable($root . '/minified/formats/bbcode.js')
            && is_readable($root . '/js/xoops-bbcode.js');

        return $this->isEnabled;
    }

    /**
     * FormSCEditor::render()
     *
     * @return string
     */
    public function render()
    {
        static $assetsIncluded = false;

        $name    = $this->getName();
        $value   = $this->getValue();
        $cols    = (int) $this->getCols();
        $rows    = (int) $this->getRows();
        // width/height configuration never lands in $this->configs: XoopsEditor::__construct()
        // routes those keys to setWidth()/setHeight() above, so the properties are already
        // normalized here.
        $width   = htmlspecialchars($this->width, ENT_QUOTES, 'UTF-8');

        $htmlName     = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $jsId         = json_encode($name, JSON_THROW_ON_ERROR);

        $editorPath = XOOPS_URL . $this->rootPath;

        $html = '';

        // Include CSS/JS assets only once per page
        if (!$assetsIncluded) {
            // Load order matters: core, then the stock bbcode format, then our overrides.
            // js/xoops-bbcode.js redefines tags on sceditor.formats.bbcode, so the stock
            // format must already exist when it runs.
            $html .= '<link rel="stylesheet" href="' . $editorPath . '/minified/themes/default.min.css">' . "\n";
            $html .= '<script src="' . $editorPath . '/minified/sceditor.min.js"></script>' . "\n";
            $html .= '<script src="' . $editorPath . '/minified/formats/bbcode.js"></script>' . "\n";
            // Localized labels/prompts for the toolbar commands; must be published before
            // xoops-bbcode.js loads because that file reads them at registration time.
            $html .= '<script>window.xoopsSCEditorLang = ' . json_encode($this->commandLanguage(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) . ';</script>' . "\n";
            $html .= '<script src="' . $editorPath . '/js/xoops-bbcode.js"></script>' . "\n";
            $assetsIncluded = true;
        }

        // Textarea (SCEditor attaches to this)
        $html .= '<textarea id="' . $htmlName . '" name="' . $htmlName . '" '
               . 'cols="' . $cols . '" rows="' . $rows . '" '
               . 'style="width:' . $width . ';">'
               . $escapedValue
               . '</textarea>' . "\n";

        // Initialize SCEditor: BBCode format, permanently in source mode.
        // startInSourceMode matters for correctness, not just preference: creating the
        // instance in the default WYSIWYG mode would parse the existing BBCode into HTML
        // and re-serialise it on the way back to source — exactly the round-trip that can
        // rewrite or drop XOOPS-specific tags. Starting in source mode means the content
        // never enters that conversion path.
        // Defensive: a missing or failed library must leave a plain, fully
        // usable textarea rather than a dead control.
        $html .= '<script>' . "\n";
        $html .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
        $html .= '  if (typeof sceditor === "undefined") { return; }' . "\n";
        $html .= '  var el = document.getElementById(' . $jsId . ');' . "\n";
        $html .= '  if (!el) { return; }' . "\n";
        $html .= '  sceditor.create(el, {' . "\n";
        $html .= '    format: "bbcode",' . "\n";
        $html .= '    startInSourceMode: true,' . "\n";
        // Content stylesheet for the editing area, per the upstream usage docs.
        $html .= '    style: ' . json_encode($editorPath . '/minified/themes/content/default.min.css', JSON_THROW_ON_ERROR) . ',' . "\n";
        $html .= '    toolbar: (typeof xoopsBBCodeToolbar !== "undefined") ? xoopsBBCodeToolbar : "bold,italic,underline,strike",' . "\n";
        $html .= '    emoticonsEnabled: false,' . "\n";
        $html .= '    resizeEnabled: true,' . "\n";
        $html .= '    width: ' . json_encode($this->width, JSON_THROW_ON_ERROR) . ',' . "\n";
        $html .= '    height: ' . json_encode($this->height, JSON_THROW_ON_ERROR) . "\n";
        $html .= '  });' . "\n";
        // Belt and braces for the source-mode-only promise: the toolbar exposes no source
        // toggle, but any stray script calling sourceMode(false) would trigger the exact
        // BBCode->HTML conversion this integration exists to prevent. Keep the getter and
        // sourceMode(true) working; swallow only the switch to WYSIWYG.
        $html .= '  var instance = sceditor.instance(el);' . "\n";
        $html .= '  if (instance && typeof instance.sourceMode === "function") {' . "\n";
        $html .= '    var xoopsOrigSourceMode = instance.sourceMode.bind(instance);' . "\n";
        $html .= '    instance.sourceMode = function (enable) {' . "\n";
        $html .= '      if (false === enable) { return; }' . "\n";
        $html .= '      return xoopsOrigSourceMode.apply(null, arguments);' . "\n";
        $html .= '    };' . "\n";
        $html .= '  }' . "\n";
        $html .= '});' . "\n";
        $html .= '</script>' . "\n";

        return $html;
    }

    /**
     * Localized command labels/prompts for js/xoops-bbcode.js, keyed by the names that file
     * looks up. Constants are guarded because a direct instantiation may not have loaded the
     * editor language file; the JS side carries English fallbacks for missing keys anyway.
     *
     * @return array<string, string>
     */
    protected function commandLanguage(): array
    {
        $map = [
            'strike'        => '_XOOPS_EDITOR_SCEDITOR_STRIKE',
            'left'          => '_XOOPS_EDITOR_SCEDITOR_LEFT',
            'center'        => '_XOOPS_EDITOR_SCEDITOR_CENTER',
            'right'         => '_XOOPS_EDITOR_SCEDITOR_RIGHT',
            'size'          => '_XOOPS_EDITOR_SCEDITOR_SIZE',
            'sizePrompt'    => '_XOOPS_EDITOR_SCEDITOR_SIZE_PROMPT',
            'email'         => '_XOOPS_EDITOR_SCEDITOR_EMAIL',
            'emailPrompt'   => '_XOOPS_EDITOR_SCEDITOR_EMAIL_PROMPT',
            'siteurl'       => '_XOOPS_EDITOR_SCEDITOR_SITEURL',
            'siteurlPrompt' => '_XOOPS_EDITOR_SCEDITOR_SITEURL_PROMPT',
            'quote'         => '_XOOPS_EDITOR_SCEDITOR_QUOTE',
            'code'          => '_XOOPS_EDITOR_SCEDITOR_CODE',
            'list'          => '_XOOPS_EDITOR_SCEDITOR_LIST',
            'image'         => '_XOOPS_EDITOR_SCEDITOR_IMAGE',
            'imagePrompt'   => '_XOOPS_EDITOR_SCEDITOR_IMAGE_PROMPT',
            'youtube'       => '_XOOPS_EDITOR_SCEDITOR_YOUTUBE',
            'youtubePrompt' => '_XOOPS_EDITOR_SCEDITOR_YOUTUBE_PROMPT',
            'widthPrompt'   => '_XOOPS_EDITOR_SCEDITOR_WIDTH_PROMPT',
            'heightPrompt'  => '_XOOPS_EDITOR_SCEDITOR_HEIGHT_PROMPT',
            'wiki'          => '_XOOPS_EDITOR_SCEDITOR_WIKI',
            'wikiPrompt'    => '_XOOPS_EDITOR_SCEDITOR_WIKI_PROMPT',
        ];

        $lang = [];
        foreach ($map as $key => $constant) {
            if (defined($constant)) {
                $lang[$key] = (string) constant($constant);
            }
        }

        return $lang;
    }

    /**
     * FormSCEditor::renderValidationJS()
     *
     * @return string
     */
    public function renderValidationJS()
    {
        $eltname = $this->getName();
        if ($this->isRequired() && $eltname) {
            $eltcaption = $this->getCaption();
            $eltmsg     = empty($eltcaption)
                ? sprintf(_FORM_ENTER, $eltname)
                : sprintf(_FORM_ENTER, $eltcaption);

            $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;
            $jsName    = json_encode((string) $eltname, $jsonFlags);
            $jsMessage = json_encode(stripslashes($eltmsg), $jsonFlags);

            return "\nvar sceditorField = document.getElementById({$jsName});"
                 . "\nif (sceditorField && sceditorField.value == '') "
                 . "{ window.alert({$jsMessage}); return false; }";
        }

        return '';
    }
}
