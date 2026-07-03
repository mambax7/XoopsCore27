<?php
/**
 * XOOPS form container interface
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Formal contract for form elements that group other form elements.
 *
 * Implemented by {@link XoopsFormElementTray} and {@link XoopsFormTabTray}. A
 * container keeps its children part of the owning form, so the form's required
 * handling and generated client-side validation reach every nested element.
 *
 * The legacy discriminator for "is this a container?" was the {@link
 * XoopsFormElement::isContainer()} method. This interface formalises that role
 * so callers can detect containers with `instanceof` while the return types of
 * getRequired()/getElements() stay PHPDoc-only — they are long-standing public
 * signatures used across the module ecosystem and MUST NOT gain native return
 * types, which would be a backward-compatibility break.
 *
 * @category  XoopsForm
 * @package   XoopsFormContainerInterface
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
interface XoopsFormContainerInterface
{
    /**
     * Is this element a container of other elements?
     *
     * @return bool
     */
    public function isContainer();

    /**
     * Get the required child elements (by reference, so the owning form can
     * merge them into its own required list).
     *
     * @return XoopsFormElement[] array of {@link XoopsFormElement}s
     */
    public function &getRequired();

    /**
     * Get the child elements. With $recurse, nested containers are flattened so
     * the owning form sees every leaf element.
     *
     * @param bool $recurse flatten nested containers?
     *
     * @return XoopsFormElement[] array of {@link XoopsFormElement}s
     */
    public function &getElements($recurse = false);
}
