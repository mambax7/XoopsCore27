<?php
/**
 * EasyMDE Markdown Editor for XOOPS
 *
 * A simple, embeddable Markdown editor based on EasyMDE.
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
 * @since               2.7.0
 * @author              XOOPS Development Team
 * @see                 https://github.com/Ionaru/easy-markdown-editor
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

xoops_load('XoopsEditor');

/**
 * Class FormEasyMDE
 */
class FormEasyMDE extends XoopsEditor
{
    public string $width  = '100%';
    public string $height = '400px';

    /**
     * FormEasyMDE::__construct()
     *
     * @param array $configs
     */
    public function __construct(array $configs = [])
    {
        $this->rootPath = '/class/xoopseditor/easymde';
        parent::__construct($configs);
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return is_readable(XOOPS_ROOT_PATH . $this->rootPath . '/js/easymde.min.js');
    }

    /**
     * FormEasyMDE::render()
     *
     * @return string
     */
    public function render()
    {
        static $assetsIncluded = false;

        require_once XOOPS_ROOT_PATH . '/class/xoopsmarkdown.php';

        $name    = $this->getName(false);
        $value   = XoopsMarkdown::editorSource((string) $this->getValue());
        $marked  = XoopsMarkdown::source((string) $this->getValue()) !== null;
        if ($value === (string) $this->getValue()) {
            // Match core form rendering for getVar('e') values, including HTML
            // accidentally opened here because of a remembered editor choice.
            $value = htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5);
        }
        $stateKey = hash('sha256', $name);
        $previousState = \Xmf\Request::getArray('_xoops_markdown_state', [], 'POST');
        $previous = $previousState[$stateKey] ?? [];
        $initial = is_array($previous) && is_string($previous['initial'] ?? null)
            && preg_match('/\A[0-9a-f]{64}\z/', $previous['initial'])
            ? $previous['initial'] : XoopsMarkdown::fingerprint($value);
        $marked = $marked || (is_array($previous) && ($previous['marked'] ?? null) === '1');
        $cols    = (int) $this->getCols();
        $rows    = (int) $this->getRows();
        $configs = (array) $this->configs;
        $width   = htmlspecialchars($configs['width'] ?? $this->width, ENT_QUOTES, 'UTF-8');
        $height  = htmlspecialchars($configs['height'] ?? $this->height, ENT_QUOTES, 'UTF-8');

        $htmlName     = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $escapedValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // HTML5 consumes one initial textarea newline. Supply that extra byte
        // so an untouched leading newline still matches the edit fingerprint.
        if (str_starts_with($value, "\n") || str_starts_with($value, "\r")) {
            $escapedValue = "\n" . $escapedValue;
        }
        $jsId         = json_encode($name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        $editorPath = XOOPS_URL . $this->rootPath;
        $previewUrl = json_encode($editorPath . '/preview.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        $html = '';

        // Include CSS/JS assets only once per page
        if (!$assetsIncluded) {
            $html .= '<link rel="stylesheet" href="' . $editorPath . '/css/easymde.min.css">' . "\n";
            $html .= '<script src="' . $editorPath . '/js/easymde.min.js"></script>' . "\n";
            $assetsIncluded = true;
        }

        // Carry the baseline through previews; only a Save submit opts into conversion.
        $html .= '<input type="hidden" name="_xoops_markdown[]" value="' . $htmlName . '">' . "\n";
        $html .= '<input type="hidden" name="_xoops_markdown_state[' . $stateKey . '][initial]" value="' . $initial . '">' . "\n";
        $html .= '<input type="hidden" name="_xoops_markdown_state[' . $stateKey . '][marked]" value="' . ($marked ? '1' : '0') . '">' . "\n";
        $html .= '<input type="hidden" name="_xoops_markdown_save" value="0">' . "\n";

        // Textarea (EasyMDE attaches to this)
        $html .= '<textarea id="' . $htmlName . '" name="' . $htmlName . '" '
               . 'cols="' . $cols . '" rows="' . $rows . '" '
               . 'style="width:' . $width . ';">'
               . $escapedValue
               . '</textarea>' . "\n";

        // Initialize EasyMDE
        $html .= '<script>' . "\n";
        $html .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
        $html .= '  var field = document.getElementById(' . $jsId . '), form = field.form;' . "\n";
        $html .= '  if (form && !form.xoopsMarkdownSubmit) {' . "\n";
        $html .= '    form.xoopsMarkdownSubmit = true;' . "\n";
        $html .= '    function intent(value) { form.querySelectorAll(\'input[name="_xoops_markdown_save"]\').forEach(function(input) { input.value = value; }); }' . "\n";
        $html .= '    form.addEventListener("click", function() { intent("0"); }, true);' . "\n";
        $html .= '    form.addEventListener("change", function() { intent("0"); }, true);' . "\n";
        $html .= '    form.addEventListener("submit", function(event) {' . "\n";
        $html .= '      var button = event.submitter, action = button && button.getAttribute("data-xoops-action");' . "\n";
        // ponytail: legacy submit-button names identify previews/uploads; custom
        // actions should use data-xoops-action="save" or "preview" explicitly.
        $html .= '      var save = action ? action === "save" : !button || !/preview|upload|cancel/i.test(button.name + " " + button.id);' . "\n";
        $html .= '      intent(!event.defaultPrevented && save ? "1" : "0");' . "\n";
        $html .= '    });' . "\n";
        $html .= '  }' . "\n";
        $html .= '  if (typeof EasyMDE !== "undefined") {' . "\n";
        $html .= '    new EasyMDE({' . "\n";
        $html .= '      element: document.getElementById(' . $jsId . '),' . "\n";
        $html .= '      spellChecker: false,' . "\n";
        $html .= '      minHeight: ' . json_encode($configs['height'] ?? $this->height, JSON_THROW_ON_ERROR) . ',' . "\n";
        $html .= '      autoDownloadFontAwesome: false,' . "\n";
        $html .= '      forceSync: true,' . "\n";
        // The bundled client parser permits raw HTML. Use core safe-mode rendering
        // for both preview buttons, and ignore stale asynchronous responses.
        $html .= '      previewRender: function(source, preview) {' . "\n";
        $html .= '        var request = {}; preview.xoopsMarkdownRequest = request;' . "\n";
        $html .= '        fetch(' . $previewUrl . ', {method: "POST", credentials: "same-origin", body: new URLSearchParams({markdown: source})})' . "\n";
        $html .= '          .then(function(response) { if (!response.ok) { throw new Error("Preview"); } return response.json(); })' . "\n";
        $html .= '          .then(function(data) { if (preview.xoopsMarkdownRequest === request && typeof data.html === "string") { preview.innerHTML = data.html; } })' . "\n";
        $html .= '          .catch(function() { if (preview.xoopsMarkdownRequest === request) { preview.textContent = source; } });' . "\n";
        $html .= '        return "";' . "\n";
        $html .= '      },' . "\n";
        $html .= '      toolbar: [' . "\n";
        $html .= '        "bold", "italic", "heading", "|",' . "\n";
        $html .= '        "quote", "unordered-list", "ordered-list", "|",' . "\n";
        $html .= '        "link", "image", "table", "horizontal-rule", "|",' . "\n";
        $html .= '        "preview", "side-by-side", "fullscreen", "|",' . "\n";
        $html .= '        "guide"' . "\n";
        $html .= '      ],' . "\n";
        $html .= '      status: ["lines", "words", "cursor"]' . "\n";
        $html .= '    });' . "\n";
        $html .= '  }' . "\n";
        $html .= '});' . "\n";
        $html .= '</script>' . "\n";

        return $html;
    }

    /**
     * FormEasyMDE::renderValidationJS()
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
