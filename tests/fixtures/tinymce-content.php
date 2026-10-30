<?php

declare(strict_types=1);

define('XOOPS_ROOT_PATH', dirname(__DIR__, 2) . '/htdocs');
define('XOOPS_URL', 'http://xoops.test');
define('XOOPS_THEME_URL', XOOPS_URL . '/themes');
define('XOOPS_THEME_PATH', XOOPS_ROOT_PATH . '/themes');
$GLOBALS['xoopsConfig'] = ['theme_set' => 'default'];
$GLOBALS['xoops'] = new class {
    public function path(string $path): string
    {
        return __DIR__ . '/missing-config/' . $path;
    }
};
class XoopsLists
{
    public static function getDirListAsArray(string $path): array
    {
        return [];
    }
}
function xoops_getcss(string $theme): string
{
    return XOOPS_THEME_URL . '/' . $theme . '/style.css';
}
$version = ($argv[1] ?? '7') === '5' ? '5' : '7';
require XOOPS_ROOT_PATH . '/class/xoopseditor/tinymce' . $version . ($version === '5' ? '/tinymce.php' : '/TinyMCE.php');
$editor = new TinyMCE(['rootpath' => '/class/xoopseditor/tinymce' . $version, 'elements' => 'message', 'language' => 'en']);
$editor->init();
echo json_encode($editor->setting, JSON_THROW_ON_ERROR);
