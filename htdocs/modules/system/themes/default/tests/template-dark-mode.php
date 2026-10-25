<?php
/**
 * Render the Default theme with the core's Smarty engine, without a database.
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = $argv[1] ?? dirname(__DIR__, 5);
require $root . '/xoops_lib/vendor/autoload.php';
require dirname(__DIR__) . '/language/english/admin.php';
define('_YOURHOME', 'Home');
define('_LOGOUT', 'Logout');
define('_AM_SYSTEM_HELP', 'Help');
$smarty = new \Smarty();
$smarty->setLeftDelimiter('<{');
$smarty->setRightDelimiter('}>');
$temp = sys_get_temp_dir() . '/default-dark-render-' . bin2hex(random_bytes(6));
if (!mkdir($temp, 0700)) {
    throw new RuntimeException('Cannot create temporary directory');
}
$smarty->setCompileDir($temp);
$url = static function ($params): string {
    return trim((string) ($params[0] ?? ''), " '\"");
};
$smarty->registerPlugin('compiler', 'xoAppUrl', static fn($params) => var_export('/' . $url($params), true));
$smarty->registerPlugin('compiler', 'xoImgUrl', static fn($params) => var_export('/theme/' . $url($params), true));
$files = [];
try {
    foreach (['xo_head', 'xo_globalnav', 'xo_toolbar', 'xo_metas', 'xo_scripts', 'xo_page', 'xo_footer'] as $name) {
        $file = $temp . '/' . $name . '.tpl';
        $files[] = $file;
        $content = in_array($name, ['xo_head', 'xo_globalnav', 'xo_toolbar'], true)
            ? file_get_contents(dirname(__DIR__) . '/xotpl/' . $name . '.tpl') : '';
        file_put_contents($file, $content);
    }
    file_put_contents($temp . '/xo_metas.tpl', '<meta charset="utf-8"><link rel="stylesheet" href="/admin.css"><link rel="stylesheet" href="/theme/css/style.css">'
        . '<link rel="stylesheet" href="/theme/css/dark.css" title="dark"><link rel="stylesheet" href="/theme/css/silver.css" title="silver">'
        . '<link rel="stylesheet" href="/theme/css/orange.css" title="orange"><link rel="stylesheet" href="/theme/css/dark-mode.css">');
    file_put_contents($temp . '/xo_scripts.tpl', '<script src="/jquery.js"></script><script src="/theme/js/menu.js"></script>'
        . '<script src="/theme/js/styleswitch.js"></script><script src="/theme/js/dark-mode.js"></script>');
    file_put_contents($temp . '/xo_page.tpl', <<<'HTML'
<div id="xo-body"><div id="xo-content">
<ul id="xo-breadcrumb" style="background-image:url('/breadcrumb.png')"><li><a href="#">System</a></li><li>Modules</li></ul>
<div class="tips"><ul><li>Set module preferences and permissions after installation.</li><li>Drag rows to change the menu order.</li></ul></div>
<h2 class="xo-title">Module administration</h2>
<table id="xo-module-sort" class="outer"><thead><tr><th>Module</th><th>Version</th><th>Active</th><th>Actions</th></tr></thead><tbody>
<tr class="head"><td><a href="#">System</a></td><td>2.8</td><td>Active</td><td><button type="button">Information</button></td></tr>
<tr class="odd"><td><input type="text" value="Private Messaging"></td><td>1.1.4</td><td>Active</td><td><a href="#">Preferences</a></td></tr>
<tr class="even"><td><input type="text" value="Content"></td><td>1.0</td><td>Active</td><td><a href="#">Preferences</a></td></tr></tbody></table>
<fieldset><legend>Preferences</legend><label>Editor <select><option>Default editor</option></select></label>
<label><input type="checkbox" checked> Enable notifications</label><p><textarea>Sample content</textarea></p>
<input type="submit" value="Save changes"><button type="button">Cancel</button></fieldset>
<div id="accordion"><h5>System information</h5><div class="pane" style="display:block">PHP and XOOPS version information.</div></div>
<div class="help"><h3>Help</h3><p>Documentation and <a href="#">support resources</a>.</p></div>
<div class="panes"><ul><li>Useful information</li></ul></div>
<div class="errorMsg">Unable to save this item. <a href="#">Details</a></div>
<div class="resultMsg">Preferences saved successfully.</div>
<div class="confirmMsg">Confirm the requested action.</div>
<div class="ui-dialog">Dialog content</div>
</div></div>
HTML);
    $smarty->assign([
        'theme_tpl' => $temp, 'xoops_langcode' => 'en', 'xoops_dirname' => 'system',
        'theme_css' => '/theme/css', 'modname' => 'System options', 'moddir' => 'system',
        'theme_icons' => '/icons', 'mod_options' => [
            ['link' => '#', 'title' => 'Avatars', 'icon' => '/icons/avatar.png'],
            ['link' => '#', 'title' => 'Modules', 'icon' => '/icons/modules.png'],
            ['link' => '#', 'title' => 'Preferences', 'icon' => '/icons/prefs.png'],
        ],
        'navitems' => [['link' => '#', 'text' => 'Control Panel', 'menu' => [
            ['link' => '#', 'title' => 'System preferences'],
        ]]],
    ]);
    foreach (['ltr', 'rtl'] as $direction) {
        foreach ([false, true] as $dark) {
            $smarty->assign(['dark_mode' => $dark, 'xoops_text_direction' => $direction, 'xoops_is_rtl' => $direction === 'rtl']);
            $html = $smarty->fetch(dirname(__DIR__) . '/theme.tpl');
            if (!str_contains($html, 'aria-pressed="' . ($dark ? 'true' : 'false') . '"')
                || !str_contains($html, 'dir="' . $direction . '"')
                || (str_contains($html, ' dark-mode') !== $dark)
                || !str_contains($html, 'class="icon-moon"') || !str_contains($html, 'class="icon-sun"')) {
                throw new RuntimeException('Template state mismatch');
            }
            if (isset($argv[2]) && !$dark && $direction === 'ltr') {
                file_put_contents($argv[2], $html);
            }
        }
    }
    echo "PASS: real Default templates render light/dark and LTR/RTL\n";
} finally {
    $smarty->clearCompiledTemplate();
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($temp);
}
