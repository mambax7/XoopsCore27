<?php
/**
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */

declare(strict_types=1);

// Run: php tests/form-option-markup.php (no database or writes).
define('XOOPS_ROOT_PATH', dirname(__DIR__) . '/htdocs');
require XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/formradio.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererInterface.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererTailwind.php';
$radio = new XoopsFormRadio('', 'icon', '1');
$radio->addOption('1', '<img src="/images/subject/icon1.gif" alt="Message">');
$html = (new XoopsFormRendererTailwind())->renderFormRadio($radio);
if (!str_contains($html, '<img src="/images/subject/icon1.gif"')) {
    throw new RuntimeException('Tailwind must render module-supplied icon markup, like the other form renderers.');
}
if (!str_contains($html, 'title=""')) {
    throw new RuntimeException('The image-only option must keep a valid empty input title.');
}
$radio = new XoopsFormRadio('', 'icon', '1');
$radio->addOption('1', '<img src="/images/subject/icon1.gif" alt="Message" onerror="alert(1)"><script>alert(2)</script>');
$html = (new XoopsFormRendererTailwind())->renderFormRadio($radio);
if (str_contains($html, 'onerror=') || str_contains($html, '<script>')) {
    throw new RuntimeException('Icon labels must not carry event handlers or additional markup.');
}
$radio = new XoopsFormRadio('', 'icon', '1');
$radio->addOption('1', '<img src="javascript:alert(1)" alt="Message">');
$html = (new XoopsFormRendererTailwind())->renderFormRadio($radio);
if (str_contains($html, '<img')) {
    throw new RuntimeException('Icon labels must reject unsafe URL schemes.');
}
echo "PASS: Tailwind radio option markup renders as an image.\n";
