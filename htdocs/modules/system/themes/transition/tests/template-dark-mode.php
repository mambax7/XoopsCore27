<?php
/**
 * XOOPS Transition theme — render the real shell and toggle without a database.
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 5) . '/xoops_lib/vendor/autoload.php';
require dirname(__DIR__) . '/language/english/admin.php';
define('_YOURHOME', 'Home');
define('_LOGOUT', 'Logout');

$smarty = new Smarty();
$smarty->left_delimiter = '<{';
$smarty->right_delimiter = '}>';
$compile = sys_get_temp_dir() . '/transition-mode-' . bin2hex(random_bytes(6));
if (!mkdir($compile, 0700)) {
    throw new RuntimeException('Cannot create temporary compile directory');
}
$smarty->setCompileDir($compile);
$smarty->registerResource('fixture', new class extends Smarty_Resource_Custom {
    protected function fetch($name, &$source, &$mtime)
    {
        $source = $name === '/xo_head.tpl' ? file_get_contents(dirname(__DIR__) . '/xotpl/xo_head.tpl') : '';
        $mtime = 1;
    }
});
$smarty->registerPlugin('compiler', 'xoAppUrl', static fn() => '/');
$smarty->registerPlugin('compiler', 'xoImgUrl', static fn() => '/logo.png');
$smarty->assign(['theme_tpl' => 'fixture:', 'xoops_langcode' => 'en', 'xoops_dirname' => 'system', 'xoops_url' => '']);
try {
    foreach ([null, false, true] as $dark) {
        $smarty->clearAssign('dark_mode');
        if ($dark !== null) {
            $smarty->assign('dark_mode', $dark);
        }
        $html = $smarty->fetch(dirname(__DIR__) . '/theme.tpl');
        $expectedClass = $dark ? 'en dark-mode' : 'en';
        if (!str_contains($html, 'class="' . $expectedClass . '"')
            || !str_contains($html, 'aria-pressed="' . ($dark ? 'true' : 'false') . '"')
            || !str_contains($html, 'id="darkModeToggle"')) {
            throw new RuntimeException('Rendered mode/toggle mismatch');
        }
    }
    echo "PASS: real Smarty shell and toggle render light, dark and unset preference\n";
} finally {
    $smarty->clearCompiledTemplate();
    rmdir($compile);
}
