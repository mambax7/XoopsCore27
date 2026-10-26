<?php
declare(strict_types=1);

$core = realpath($argv[1]);
define('XOOPS_ROOT_PATH', str_replace('\\', '/', $core));
define('XOOPS_PATH', XOOPS_ROOT_PATH . '/xoops_lib');
define('XOOPS_ADMINTHEME_PATH', XOOPS_ROOT_PATH . '/modules/system/themes');
define('XOOPS_ADMINTHEME_URL', 'https://example.invalid/modules/system/themes');
$GLOBALS['xoopsConfig'] = ['language' => $argv[3] ?? 'english'];
require XOOPS_ROOT_PATH . '/class/theme.php';
function xoops_load($name, $type = 'core') {}
class HeaderVerified extends RuntimeException {}
class HeaderTheme extends xos_opal_Theme
{
    public function addScript($src = '', $attributes = [], $content = '', $name = '')
    {
        $method = new ReflectionMethod(XoopsGuiModern::class, 'getContentStats');
        $method->invokeArgs($GLOBALS['probeGui'], [&$GLOBALS['probeTpl']]);
        throw new HeaderVerified(_MODERN_MOD_ARTICLES);
    }
}
class XoopsSystemGui
{
    public $xoTheme;
    public $template;
    public function header()
    {
        // The real admin factory corrects the theme path after frontend initialization.
        $this->xoTheme = new HeaderTheme();
        $this->xoTheme->path = XOOPS_ADMINTHEME_PATH . '/modern';
        $this->xoTheme->addLanguage('admin');
        $GLOBALS['xoTheme'] = $this->xoTheme;
        $this->template = $GLOBALS['probeTpl'];
    }
}
$GLOBALS['xoopsDB'] = new class {
    public function prefix($name) { return $name; }
    public function escape($name) { return $name; }
    public function query($sql) { return false; }
    public function isResultSet($result) { return false; }
};
$GLOBALS['probeTpl'] = new class {
    public function assign($name, $value) {}
};
require $argv[2] ?? XOOPS_ADMINTHEME_PATH . '/modern/modern.php';
if (defined('_MODERN_MOD_ARTICLES')) {
    throw new RuntimeException('Theme discovery eagerly loaded Modern language.');
}
$GLOBALS['probeGui'] = new XoopsGuiModern();
try {
    $GLOBALS['probeGui']->header();
    throw new RuntimeException('Header probe did not run.');
} catch (HeaderVerified $result) {
    if ($result->getMessage() !== 'Articles') {
        throw new RuntimeException('Expected English label/fallback.');
    }
    echo "PASS: discovery stayed lazy; selected Modern header loaded content labels.\n";
}
