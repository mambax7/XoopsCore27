<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/
/**
 * Installer language selection page
 *
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package          installer
 * @since            2.3.0
 * @author           Haruki Setoyama  <haruki@planewave.org>
 * @author           Kazumi Ono <webmaster@myweb.ne.jp>
 * @author           Skalpa Keo <skalpa@xoops.org>
 * @author           Taiwen Jiang <phppp@users.sourceforge.net>
 * @author           DuGris <dugris@frxoops.org>
 * @author           DuGris (aka L. JEN) <dugris@frxoops.org>
 **/

require_once __DIR__ . '/include/common.inc.php';
defined('XOOPS_INSTALL') || die('XOOPS Installation wizard die');

xoops_setcookie('xo_install_lang', 'english', 0, '', '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Raw $_POST here — XMF autoloader is not available until the user
    // configures the xoops_lib path on the pathsettings page (page 4).
    // Validate using the same pattern as initLanguage() — strip invalid
    // characters and verify the language directory exists.
    $rawLang = $_POST['lang'] ?? '';
    $lang    = 'english';
    if (is_string($rawLang) && $rawLang !== '') {
        $lang = preg_replace('/[^a-z0-9_\-]/i', '', trim($rawLang));
        if (!is_string($lang) || $lang === '' || !file_exists(XOOPS_INSTALL_PATH . "/language/{$lang}/install.php")) {
            $lang = 'english';
        }
    }
    if (!file_exists(XOOPS_INSTALL_PATH . "/language/{$lang}/install.php")) {
        $lang = 'english';
    }
    xoops_setcookie('xo_install_lang', $lang, 0, '', '');

    $wizard->redirectToPage('+1');
    exit();
}

$_SESSION['settings'] = [];
xoops_setcookie('xo_install_user', '', 0, '', '');

$pageHasForm = true;
$title = LANGUAGE_SELECTION;
$label = 'Available Languages';
$content =<<<EOT
<div class="form-group col-md-4">
    <label for="lang" class="control-label">{$label}</label>
    <select name="lang" id="lang" class="form-control">
EOT;

// List installer languages (not site languages) to match initLanguage() validation
$languages = getDirList(XOOPS_INSTALL_PATH . '/language/');
foreach ($languages as $lang) {
    $sel = ($lang == $wizard->language) ? ' selected' : '';
    $escapedLang = installerHtmlSpecialChars($lang);
    $content .= "<option value=\"{$escapedLang}\"{$sel}>{$escapedLang}</option>\n";
}
$content .=<<<EOB
    </select>
</div><div class="clearfix"></div>
EOB;


include __DIR__ . '/include/install_tpl.php';
