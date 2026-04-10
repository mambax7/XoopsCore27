<?php
/**
 * xTailwind theme autorun
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

    /* DaisyUI themes bundled with the compiled CSS.
       Update this list when enabling different themes in tailwind.config.js. */
    $daisyThemes = [
        ['name' => 'light',      'label' => 'Light',      'mode' => 'light'],
        ['name' => 'dark',       'label' => 'Dark',       'mode' => 'dark'],
        ['name' => 'cupcake',    'label' => 'Cupcake',    'mode' => 'light'],
        ['name' => 'bumblebee',  'label' => 'Bumblebee',  'mode' => 'light'],
        ['name' => 'emerald',    'label' => 'Emerald',    'mode' => 'light'],
        ['name' => 'corporate',  'label' => 'Corporate',  'mode' => 'light'],
        ['name' => 'synthwave',  'label' => 'Synthwave',  'mode' => 'dark'],
        ['name' => 'retro',      'label' => 'Retro',      'mode' => 'light'],
        ['name' => 'cyberpunk',  'label' => 'Cyberpunk',  'mode' => 'light'],
        ['name' => 'valentine',  'label' => 'Valentine',  'mode' => 'light'],
        ['name' => 'halloween',  'label' => 'Halloween',  'mode' => 'dark'],
        ['name' => 'garden',     'label' => 'Garden',     'mode' => 'light'],
        ['name' => 'forest',     'label' => 'Forest',     'mode' => 'dark'],
        ['name' => 'aqua',       'label' => 'Aqua',       'mode' => 'dark'],
        ['name' => 'lofi',       'label' => 'Lo-Fi',      'mode' => 'light'],
        ['name' => 'pastel',     'label' => 'Pastel',     'mode' => 'light'],
        ['name' => 'fantasy',    'label' => 'Fantasy',    'mode' => 'light'],
        ['name' => 'wireframe',  'label' => 'Wireframe',  'mode' => 'light'],
        ['name' => 'black',      'label' => 'Black',      'mode' => 'dark'],
        ['name' => 'luxury',     'label' => 'Luxury',     'mode' => 'dark'],
        ['name' => 'dracula',    'label' => 'Dracula',    'mode' => 'dark'],
        ['name' => 'cmyk',       'label' => 'CMYK',       'mode' => 'light'],
        ['name' => 'autumn',     'label' => 'Autumn',     'mode' => 'light'],
        ['name' => 'business',   'label' => 'Business',   'mode' => 'dark'],
        ['name' => 'acid',       'label' => 'Acid',       'mode' => 'light'],
        ['name' => 'lemonade',   'label' => 'Lemonade',   'mode' => 'light'],
        ['name' => 'night',      'label' => 'Night',      'mode' => 'dark'],
        ['name' => 'coffee',     'label' => 'Coffee',     'mode' => 'dark'],
        ['name' => 'winter',     'label' => 'Winter',     'mode' => 'light'],
        ['name' => 'dim',        'label' => 'Dim',        'mode' => 'dark'],
        ['name' => 'nord',       'label' => 'Nord',       'mode' => 'light'],
        ['name' => 'sunset',     'label' => 'Sunset',     'mode' => 'dark'],
    ];
    $xoopsTpl->assign('xtailwindThemes', $daisyThemes);
}
