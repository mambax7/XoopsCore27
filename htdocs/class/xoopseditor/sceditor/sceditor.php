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
 */
class FormSCEditor extends XoopsEditor
{
    public string $width  = '100%';
    public string $height = '400px';

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
        $configs = (array) $this->configs;
        $width   = htmlspecialchars($configs['width'] ?? $this->width, ENT_QUOTES, 'UTF-8');
        $height  = htmlspecialchars($configs['height'] ?? $this->height, ENT_QUOTES, 'UTF-8');

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
        $html .= '    width: ' . json_encode($configs['width'] ?? $this->width, JSON_THROW_ON_ERROR) . ',' . "\n";
        $html .= '    height: ' . json_encode($configs['height'] ?? $this->height, JSON_THROW_ON_ERROR) . "\n";
        $html .= '  });' . "\n";
        $html .= '});' . "\n";
        $html .= '</script>' . "\n";

        return $html;
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
            $eltmsg = str_replace('"', '\"', stripslashes($eltmsg));

            return "\nif (document.getElementById('{$eltname}').value == '') "
                 . "{ window.alert(\"{$eltmsg}\"); return false; }";
        }

        return '';
    }
}
