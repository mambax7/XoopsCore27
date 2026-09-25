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
 * SCEditor runs in its normal visual mode and exposes its source-mode switch.
 * The XOOPS BBCode dialect in js/xoops-bbcode.js covers the tags supported by
 * the server renderer; users can still use source mode for tags not represented
 * by the visual format table.
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
 * @since               2.7.3
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

    /** @var array{toolbar: array<int, string>, plugins: array<int, string>, emoticons: bool, resize: bool, autoexpand: bool, spellcheck: bool, width: string, height: string, dragdrop_cat: int} */
    private array $settings;

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
     * Accept a CSS length as string or number; bare numbers become pixels. Anything that is
     * not a single plain length keeps the current value — the result lands inside a style
     * attribute, and HTML escaping alone would not stop a value like
     * '100%;display:none;background-image:url(...)' from injecting extra declarations.
     *
     * @param mixed  $value   incoming configuration value
     * @param string $current value to keep when the input is unusable
     *
     * @return string normalized CSS length
     */
    private function normalizeCssLength($value, string $current): string
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            // Numeric coercion, not string concatenation: is_numeric() also accepts
            // forms like '100.' and '1e3', which would concatenate into invalid CSS
            // ('100.px', '1e3px'). Guard the coerced number too — '1e400' overflows
            // to INF ('INFpx'), an INF/NAN float can arrive directly, and a negative
            // length is not a usable dimension; all of those keep the fallback.
            // The guard must read the ORIGINAL value: (string) INF is 'INF', which
            // (float)-casts back to 0.0 and would slip through a string-based check
            // only to fatal on the 0 + 'INF' coercion below.
            $number = is_string($value) ? (float) trim($value) : (float) $value;
            if (!is_finite($number) || $number < 0) {
                return $current;
            }

            return (string) (0 + trim((string) $value)) . 'px';
        }
        if (is_string($value) && preg_match('/^\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw|pt|ch|ex)$/i', trim($value))) {
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
        require_once __DIR__ . '/class/SCEditorConfig.php';
        $this->settings = SCEditorConfig::settings($this->savedPreferences());
        // [mp3] is decoded only while its sanitizer extension is on (class/textsanitizer/
        // config.php); otherwise the button would publish literal BBCode.
        $extensions = class_exists('MyTextSanitizer') ? (MyTextSanitizer::getInstance()->config['extensions'] ?? []) : [];
        if (empty($extensions['mp3'])) {
            $this->settings['toolbar'] = array_values(array_diff($this->settings['toolbar'], ['mp3']));
        }
        // Site defaults first; a width/height the calling module passes still wins,
        // because parent::__construct() routes it through setWidth()/setHeight().
        $this->setWidth($this->settings['width']);
        $this->setHeight($this->settings['height']);
        parent::__construct($configs);
    }

    /**
     * The saved System > Preferences > Editors values; empty (defaults apply) before
     * the 2.7.4 upgrade has added the category, or without a database.
     *
     * @return array<string, mixed>
     */
    private function savedPreferences(): array
    {
        if (!defined('XOOPS_CONF_EDITOR') || !function_exists('xoops_getHandler')) {
            return [];
        }
        try {
            /** @var XoopsConfigHandler $configHandler */
            $configHandler = xoops_getHandler('config');
            return $configHandler->getConfigsByCat(XOOPS_CONF_EDITOR);
        } catch (Throwable $e) {
            return [];
        }
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

        // is_file() as well as is_readable(): is_readable() alone returns true for a
        // DIRECTORY of the same name, which would activate an editor that cannot load.
        $assets = [
            $root . '/minified/sceditor.min.js',
            $root . '/minified/formats/bbcode.js',
            $root . '/js/xoops-bbcode.js',
            $root . '/minified/themes/default.min.css',
            $root . '/minified/themes/content/default.min.css',
        ];

        $this->isEnabled = true;
        foreach ($assets as $asset) {
            if (!is_file($asset) || !is_readable($asset)) {
                $this->isEnabled = false;
                break;
            }
        }

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
        // ENT_SUBSTITUTE throughout: without it one invalid UTF-8 byte makes htmlspecialchars()
        // return '' and the textarea renders EMPTY — saving would then erase the original
        // content. Substituted characters degrade one value, not the whole field.
        $width   = htmlspecialchars($this->width, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $htmlName     = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // XOOPS form builders commonly pass the edit value through one
        // htmlspecialchars() layer before constructing the editor. Remove exactly
        // that layer, as the core renderers do (XoopsFormRendererValueEscapeTrait);
        // the textarea escaping below adds it back. html_entity_decode() would also
        // turn literal text such as &eacute; or &colon; into characters.
        $value = htmlspecialchars_decode((string) $value, ENT_QUOTES | ENT_HTML5);
        $escapedValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $jsId         = json_encode($name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
        $emoticons    = $this->emoticonsConfig();

        $editorPath = XOOPS_URL . $this->rootPath;
        $html = '';

        $plugins  = $this->settings['plugins'];
        $dragdrop = $this->dragdropConfig($this->settings['dragdrop_cat']);
        if (null !== $dragdrop) {
            $plugins[] = 'dragdrop';
        }

        // Include CSS/JS assets only once per page
        if (!$assetsIncluded) {
            // Load order matters: core, then the stock bbcode format, then our overrides.
            // js/xoops-bbcode.js redefines tags on sceditor.formats.bbcode, so the stock
            // format must already exist when it runs.
            $html .= '<link rel="stylesheet" href="' . $editorPath . '/minified/themes/default.min.css">' . "\n";
            // Icons for the XOOPS-only buttons, which the stock sprite does not have.
            $html .= '<link rel="stylesheet" href="' . $editorPath . '/css/xoops-icons.css">' . "\n";
            $html .= '<script src="' . $editorPath . '/minified/sceditor.min.js"></script>' . "\n";
            $html .= '<script src="' . $editorPath . '/minified/formats/bbcode.js"></script>' . "\n";
            // Localized labels/prompts for the toolbar commands; must be published before
            // xoops-bbcode.js loads because that file reads them at registration time.
            $html .= '<script>window.xoopsSCEditorLang = ' . json_encode($this->commandLanguage(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) . ';</script>' . "\n";
            $html .= '<script src="' . $editorPath . '/js/xoops-bbcode.js"></script>' . "\n";
            // Plugins enabled in System > Preferences > Editors; names come from the
            // SCEditorConfig::PLUGINS allowlist, never from request data.
            foreach ($plugins as $plugin) {
                $html .= '<script src="' . $editorPath . '/minified/plugins/' . $plugin . '.js"></script>' . "\n";
            }
            if (null !== $dragdrop) {
                $html .= '<script src="' . $editorPath . '/js/xoops-dragdrop.js"></script>' . "\n";
            }
            $assetsIncluded = true;
        }

        // Textarea (SCEditor attaches to this)
        $html .= '<textarea id="' . $htmlName . '" name="' . $htmlName . '" '
               . 'cols="' . $cols . '" rows="' . $rows . '" '
               . 'style="width:' . $width . ';">'
               . $escapedValue
               . '</textarea>' . "\n";

        // Initialize SCEditor in its normal visual mode. The toolbar includes the
        // built-in source command, so users can inspect/edit the exact BBCode at any time.
        // Defensive: a missing or failed library must leave a plain, fully
        // usable textarea rather than a dead control.
        $html .= '<script>' . "\n";
        $html .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
        $html .= '  if (typeof sceditor === "undefined") { return; }' . "\n";
        $html .= '  var el = document.getElementById(' . $jsId . ');' . "\n";
        $html .= '  if (!el) { return; }' . "\n";
        $html .= '  sceditor.create(el, {' . "\n";
        $html .= '    format: "bbcode",' . "\n";
        $html .= '    startInSourceMode: false,' . "\n";
        // autoUpdate keeps the original textarea's value continuously in sync. SCEditor
        // does sync on form submit by itself, but XOOPS validation runs from the form's
        // inline onsubmit attribute, which can fire before SCEditor's own submit listener
        // - without this, required-field validation could read a stale (empty) value.
        $html .= '    autoUpdate: true,' . "\n";
        // Content stylesheet for the editing area, per the upstream usage docs.
        $html .= '    style: ' . json_encode($editorPath . '/minified/themes/content/default.min.css', JSON_INVALID_UTF8_SUBSTITUTE) . ',' . "\n";
        $html .= '    toolbar: ' . json_encode(SCEditorConfig::toolbar($this->settings)) . ',' . "\n";
        $html .= '    plugins: ' . json_encode(implode(',', $plugins)) . ',' . "\n";
        if (null !== $dragdrop) {
            $html .= '    dragdrop: xoopsSCEditorDragdrop(' . json_encode($dragdrop, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) . '),' . "\n";
        }
        $html .= '    emoticonsEnabled: ' . ($this->settings['emoticons'] && ($emoticons['dropdown'] !== [] || $emoticons['more'] !== []) ? 'true' : 'false') . ",\n";
        $html .= '    emoticons: ' . json_encode($emoticons, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) . ",\n";
        $html .= '    resizeEnabled: ' . ($this->settings['resize'] ? 'true' : 'false') . ',' . "\n";
        $html .= '    autoExpand: ' . ($this->settings['autoexpand'] ? 'true' : 'false') . ',' . "\n";
        $html .= '    spellcheck: ' . ($this->settings['spellcheck'] ? 'true' : 'false') . ',' . "\n";
        $html .= '    width: ' . json_encode($this->width, JSON_INVALID_UTF8_SUBSTITUTE) . ',' . "\n";
        $html .= '    height: ' . json_encode($this->height, JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        $html .= '  });' . "\n";
        $html .= '});' . "\n";
        $html .= '</script>' . "\n";

        return $html;
    }

    /**
     * Upload settings for the dragdrop plugin, or null when it must stay off: no
     * category chosen, a guest, an unknown category, or no imgcat_write right on it.
     * Guests never get it, even where the category grants anonymous uploads.
     *
     * @param int $imgcatId sceditor_dragdrop_cat preference
     *
     * @return array{endpoint: string, token: string, maxSize: int}|null
     */
    protected function dragdropConfig(int $imgcatId): ?array
    {
        $user = $GLOBALS['xoopsUser'] ?? null;
        if ($imgcatId < 1 || !($user instanceof XoopsUser) || !function_exists('xoops_getHandler')) {
            return null;
        }
        try {
            $imgcat = $this->handler('imagecategory')->get($imgcatId);
            if (!($imgcat instanceof XoopsImagecategory)
                || !$this->handler('groupperm')->checkRight('imgcat_write', $imgcatId, $user->getGroups())) {
                return null;
            }
            XoopsLoad::load('fineuploadhandler', 'system');
            XoopsLoad::load('fineimuploadhandler', 'system');
            $token = SystemFineImUploadHandler::uploadToken($imgcatId, (int) $user->id());
        } catch (Throwable $e) {
            return null;
        }

        return [
            'endpoint' => XOOPS_URL . '/ajaxfineupload.php',
            'token'    => $token,
            'maxSize'  => (int) $imgcat->getVar('imgcat_maxsize'),
        ];
    }

    /**
     * Kernel handler lookup; tests override it to stub the category and
     * permission checks.
     *
     * @param string $name handler name
     *
     * @return object
     *
     * @throws RuntimeException when the handler does not exist
     */
    protected function handler(string $name): object
    {
        return xoops_getHandler($name, true) ?: throw new RuntimeException('No handler: ' . $name);
    }

    /**
     * Build the SCEditor emoticon map from the site's configured XOOPS smileys.
     * SCEditor otherwise falls back to its demo paths, which are not shipped by XOOPS.
     *
     * @return array{dropdown: array<string, string>, more: array<string, string>, hidden: array<string, string>}
     */
    protected function emoticonsConfig(): array
    {
        $config = ['dropdown' => [], 'more' => [], 'hidden' => []];
        if (!class_exists('MyTextSanitizer') || !defined('XOOPS_UPLOAD_URL')) {
            return $config;
        }
        try {
            $smileys = MyTextSanitizer::getInstance()->getSmileys(true);
        } catch (Throwable $e) {
            return $config;
        }
        foreach ($smileys as $smiley) {
            $code = trim((string) ($smiley['code'] ?? ''));
            $file = ltrim((string) ($smiley['smile_url'] ?? ''), '/');
            if ($code === '' || $file === '') {
                continue;
            }
            $bucket = !empty($smiley['display']) ? 'dropdown' : 'more';
            $config[$bucket][$code] = XOOPS_UPLOAD_URL . '/' . $file;
        }
        // SCEditor's own emoticons are ordinary smileys (SCEditorEmoticons, installed
        // by the installer and the 2.7.4 upgrade), so posts store codes, not images.
        return $config;
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
            'mp3'           => '_XOOPS_EDITOR_SCEDITOR_MP3',
            'mp3Prompt'     => '_XOOPS_EDITOR_SCEDITOR_MP3_PROMPT',
            'uploadFailed'  => '_XOOPS_EDITOR_SCEDITOR_UPLOAD_FAILED',
            'uploadTooBig'  => '_XOOPS_EDITOR_SCEDITOR_UPLOAD_TOOBIG',
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
