<?php
/**
 * XOOPS form tab renderer interface
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

/**
 * Optional, additive companion to {@link XoopsFormRendererInterface}.
 *
 * A renderer MAY implement this interface to emit themed (Bootstrap, Tailwind,
 * …) markup for {@link XoopsFormTabTray}. It is intentionally kept separate from
 * XoopsFormRendererInterface so that adding tab support is a purely additive
 * change: the core contract and every existing third-party renderer stay
 * source-compatible. XoopsFormTabTray detects support with `instanceof`; when a
 * renderer does not implement this interface the element falls back to its own
 * framework-neutral, progressively-enhanced output.
 *
 * Who calls it: XoopsFormTabTray::render().
 *
 * @category  XoopsForm
 * @package   XoopsFormTabRendererInterface
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
interface XoopsFormTabRendererInterface
{
    /**
     * Render a tabbed group of form elements.
     *
     * @param XoopsFormTabTray $element the tab tray to render
     *
     * @return string rendered HTML
     */
    public function renderFormTabTray(XoopsFormTabTray $element): string;
}
