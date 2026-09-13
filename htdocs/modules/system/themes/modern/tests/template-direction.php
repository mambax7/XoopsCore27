<?php
// CLI only. Optionally pass the Composer autoloader of an installed XOOPS 2.7.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require $argv[1] ?? dirname(__DIR__, 5) . '/xoops_lib/vendor/autoload.php';

$smarty = new Smarty();
$smarty->left_delimiter = '<{';
$smarty->right_delimiter = '}>';
$compile = sys_get_temp_dir() . '/modern-direction-' . bin2hex(random_bytes(6));
if (!mkdir($compile, 0700)) {
    throw new RuntimeException('Cannot create temporary compile directory');
}
$smarty->setCompileDir($compile);
// Render the real outer template; unrelated admin partials need no site/database.
$smarty->registerResource('empty', new class extends Smarty_Resource_Custom {
    protected function fetch($name, &$source, &$mtime)
    {
        $source = '';
        $mtime = 1;
    }
});
$smarty->assign(['theme_tpl' => 'empty:', 'xoops_langcode' => 'en', 'xoops_dirname' => 'system', 'dark_mode' => '0']);
try {
    foreach ([null, 'ltr', 'rtl'] as $direction) {
        $smarty->clearAssign('xoops_text_direction');
        if ($direction !== null) {
            $smarty->assign('xoops_text_direction', $direction);
        }
        $html = $smarty->fetch(dirname(__DIR__) . '/theme.tpl');
        if (!preg_match('/<html\b[^>]*\bdir="' . ($direction ?? 'ltr') . '"/', $html)) {
            throw new RuntimeException('Rendered theme direction mismatch: ' . ($direction ?? 'default'));
        }
    }
    echo "PASS: rendered Modern template uses LTR, RTL and LTR fallback\n";
} finally {
    $smarty->clearCompiledTemplate();
    rmdir($compile);
}
