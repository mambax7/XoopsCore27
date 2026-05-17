<?php
/**
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * TinyMCE7 adapter for XOOPS
 *
 * @category  XoopsEditor
 * @package   TinyMCE7
 * @author    Gregory Mage
 * @author    Taiwen Jiang <phppp@users.sourceforge.net>
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 * @link      http://xoops.org
 */

xoops_load('XoopsEditor');

/**
 * Class XoopsFormTinymce
 */
class XoopsFormTinymce7 extends XoopsEditor
{
    private const TINYMCE7_LANGUAGE_MAP = [
        'ar'    => 'ar',
        'bg'    => 'bg_BG',
        'bg-bg' => 'bg_BG',
        'ca'    => 'ca',
        'cs'    => 'cs',
        'da'    => 'da',
        'de'    => 'de',
        'el'    => 'el',
        'en'    => 'en',
        'en-us' => 'en',
        'es'    => 'es',
        'eu'    => 'eu',
        'fa'    => 'fa',
        'fi'    => 'fi',
        'fr'    => 'fr_FR',
        'fr-fr' => 'fr_FR',
        'he'    => 'he_IL',
        'he-il' => 'he_IL',
        'hi'    => 'hi',
        'hr'    => 'hr',
        'hu'    => 'hu_HU',
        'hu-hu' => 'hu_HU',
        'id'    => 'id',
        'in'    => 'id',
        'iw'    => 'he_IL',
        'it'    => 'it',
        'ja'    => 'ja',
        'kk'    => 'kk',
        'ko'    => 'ko_KR',
        'ko-kr' => 'ko_KR',
        'ms'    => 'ms',
        'nb'    => 'nb_NO',
        'nb-no' => 'nb_NO',
        'nl'    => 'nl',
        'no'    => 'nb_NO',
        'pl'    => 'pl',
        'pt'    => 'pt_PT',
        'pt-br' => 'pt_BR',
        'pt-pt' => 'pt_PT',
        'ro'    => 'ro',
        'ru'    => 'ru',
        'sk'    => 'sk',
        'sl'    => 'sl_SI',
        'sl-si' => 'sl_SI',
        'sv'    => 'sv_SE',
        'sv-se' => 'sv_SE',
        'th'    => 'th_TH',
        'th-th' => 'th_TH',
        'tr'    => 'tr',
        'uk'    => 'uk',
        'vi'    => 'vi',
        'zh'      => 'zh_CN',
        'zh-cn'   => 'zh_CN',
        'zh-hans' => 'zh_CN',
        'zh-hant' => 'zh_TW',
        'zh-hk'   => 'zh_TW',
        'zh-tw'   => 'zh_TW',
        // Collapse the common country variants of languages whose only
        // TinyMCE 7 pack is the bare code (no de_DE.js/es_ES.js/...). A
        // genuine regional pack (e.g. es_MX) is intentionally NOT aliased
        // here so it still resolves via the generic path.
        'de-de'   => 'de',
        'es-es'   => 'es',
        'it-it'   => 'it',
        'ja-jp'   => 'ja',
        'nl-nl'   => 'nl',
        'pl-pl'   => 'pl',
        'ru-ru'   => 'ru',
        'tr-tr'   => 'tr',
        'uk-ua'   => 'uk',
        'vi-vn'   => 'vi',
    ];

    public $language;
    public $width  = '100%';
    public $height = '500px';

    public $editor;

    /**
     * Constructor
     *
     * @param array $configs Editor Options
     */
    public function __construct($configs)
    {
        $current_path = __FILE__;
        if (DIRECTORY_SEPARATOR !== '/') {
            $current_path = str_replace(strpos($current_path, "\\\\", 2) ? "\\\\" : DIRECTORY_SEPARATOR, '/', $current_path);
        }

        $this->rootPath = '/class/xoopseditor/tinymce7';
        parent::__construct($configs);
//        $this->configs['elements']    = $this->getName();
		$this->configs['selector'] = '#' . $this->getName();
        $this->configs['language']    = $this->getLanguage();
        $this->configs['rootpath']    = $this->rootPath;
        $this->configs['area_width']  = $this->configs['width'] ?? $this->width;
        $this->configs['area_height'] = $this->configs['height'] ?? $this->height;

//        require_once __DIR__ . '/tinymce7.php';
        require_once __DIR__ . '/TinyMCE.php';


        $this->editor = new TinyMCE($this->configs);
    }

    /**
     * Renders the Javascript function needed for client-side for validation
     *
     * I'VE USED THIS EXAMPLE TO WRITE VALIDATION CODE
     * http://tinymce.moxiecode.com/punbb/viewtopic.php?id=12616
     *
     * @return string
     */
    public function renderValidationJS()
    {
        if ($this->isRequired() && $eltname = $this->getName()) {
            //$eltname = $this->getName();
            $eltcaption = $this->getCaption();
            $eltmsg     = empty($eltcaption) ? sprintf(_FORM_ENTER, $eltname) : sprintf(_FORM_ENTER, $eltcaption);
            $eltmsg     = str_replace('"', '\"', stripslashes($eltmsg));
            $ret        = "\n";
            $ret .= "if ( tinyMCE.get('{$eltname}').getContent() == \"\" || tinyMCE.get('{$eltname}').getContent() == null) ";
            $ret .= "{ window.alert(\"{$eltmsg}\"); tinyMCE.get('{$eltname}').focus(); return false; }";

            return $ret;
        }

        return '';
    }

    /**
     * get language
     *
     * @return string
     */
    public function getLanguage()
    {
        if ($this->language) {
            return $this->language;
        }
        if (defined('_XOOPS_EDITOR_TINYMCE7_LANGUAGE')) {
            $this->language = constant('_XOOPS_EDITOR_TINYMCE7_LANGUAGE');
        } else {
            $langcode = defined('_LANGCODE') ? (string) constant('_LANGCODE') : 'en';
            $this->language = self::normalizeLanguageCode($langcode);
        }

        return $this->language;
    }

    /**
     * Convert XOOPS language codes to TinyMCE 7 language-pack filenames.
     *
     * TinyMCE 7 dropped the legacy TinyMCE 3 "_utf8" suffix and requires
     * case-sensitive language codes matching the pack filename, e.g. zh_TW.
     */
    protected static function normalizeLanguageCode(string $languageCode): string
    {
        $languageCode = trim($languageCode);
        if ($languageCode === '') {
            return 'en';
        }

        $key = strtolower(str_replace('_', '-', $languageCode));
        $key = preg_replace('/[-.]utf-?8$/', '', $key) ?? $key;

        if (isset(self::TINYMCE7_LANGUAGE_MAP[$key])) {
            return self::TINYMCE7_LANGUAGE_MAP[$key];
        }

        [$language, $region] = array_pad(explode('-', $key, 2), 2, '');

        // Reject malformed tokens: a TinyMCE 7 locale is a 2-3 letter
        // language, optionally with a 2-letter region. Anything else
        // (symbols, digits, junk) degrades to the built-in English.
        if (!preg_match('/^[a-z]{2,3}$/', $language)) {
            return 'en';
        }
        if ($region === '') {
            return $language;
        }
        if (!preg_match('/^[a-z]{2}$/', $region)) {
            return 'en';
        }

        return $language . '_' . strtoupper($region);
    }

    /**
     * prepare HTML for output
     *
     * @return string HTML
     */
    public function render()
    {
        $ret = $this->editor->render();
        $ret .= parent::render();

        return $ret;
    }

    /**
     * Check if compatible
     *
     * @return bool
     */
    public function isActive()
    {
//        return is_readable(XOOPS_ROOT_PATH . $this->rootPath . '/tinymce7.php');
 return is_readable(XOOPS_ROOT_PATH . $this->rootPath . '/TinyMCE.php')
 	&& is_readable(XOOPS_ROOT_PATH . $this->rootPath . '/js/tinymce/tinymce.min.js');
    }
}
