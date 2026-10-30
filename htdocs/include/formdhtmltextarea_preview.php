<?php
/**
 * Preview of dhtml editor content
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
 * @package             xoopsform
 * @since               2.3.0
 * @author              Vinod <smartvinu@gmail.com>
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 */
include_once dirname(__DIR__) . '/mainfile.php';

$xoopsLogger->activated = false;
$myts                   = \MyTextSanitizer::getInstance();

XoopsLoad::load('XoopsRequest');
// PHP has already decoded the form body once; decoding again would turn
// literal %3C...%3E text into markup.
$content = XoopsRequest::getText('text', '', 'POST');

if (!$GLOBALS['xoopsSecurity']->validateToken(XoopsRequest::getString('token', '', 'POST'), false)) {
    $content = 'Direct access is not allowed!!!';
}
$html    = \Xmf\Request::getInt('html', 0, 'POST') ? 1 : 0;
// Nothing may decode the output after this: displayTarea() is the last step,
// so its escaping is what the browser sees.
$content = $myts->displayTarea($content, $html, 1, 1, 1, 1);

if (!headers_sent()) {
    $charset = (defined('_CHARSET') ? _CHARSET : 'UTF-8');
    header('Content-Type:text/html; charset=' . $charset);
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Cache-Control: private, no-cache');
    header('Pragma: no-cache');
}
echo '<div>' . $content . '</div>';
