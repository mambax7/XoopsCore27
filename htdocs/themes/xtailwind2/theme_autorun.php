<?php
/**
 * xTailwind2 theme autorun
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license         GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

/* Pick the best available form renderer.
   XoopsFormRendererTailwind ships with XOOPS 2.5.13+. On older XOOPS versions
   that lack the Tailwind renderer, fall back to Bootstrap 5 — DaisyUI aliases
   most common BS5 form classes (form-control, btn, form-select) so rendered
   forms remain functional and mostly styled, even if not pixel-perfect.
   If neither renderer is available (ancient XOOPS), the framework keeps its
   default legacy renderer and forms still work with plain HTML output. */
$tailwindRendererFile = XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererTailwind.php';
if (file_exists($tailwindRendererFile)) {
    require_once $tailwindRendererFile;
    XoopsFormRenderer::getInstance()->set(new XoopsFormRendererTailwind());
} else {
    xoops_load('XoopsFormRendererBootstrap5');
    if (class_exists('XoopsFormRendererBootstrap5')) {
        XoopsFormRenderer::getInstance()->set(new XoopsFormRendererBootstrap5());
    }
}
unset($tailwindRendererFile);

/** @var XoopsTpl $xoopsTpl */
global $xoopsTpl;
if (!empty($xoopsTpl)) {
    $xoopsTpl->addConfigDir(__DIR__);

    $themes = [
        ['name' => 'atelier',   'label' => 'Atelier',   'mode' => 'light'],
        ['name' => 'fjord',     'label' => 'Fjord',     'mode' => 'light'],
        ['name' => 'pulsefire', 'label' => 'Pulsefire', 'mode' => 'light'],
        ['name' => 'noctis',    'label' => 'Noctis',    'mode' => 'dark'],
        ['name' => 'velvet',    'label' => 'Velvet',    'mode' => 'dark'],
        ['name' => 'graphite',  'label' => 'Graphite',  'mode' => 'dark'],
    ];

    $xoopsTpl->assign('xtailwindThemes', $themes);
}
