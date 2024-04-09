<?php
/**
 * XOOPS form element
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2017 XOOPS Project (www.xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             kernel
 * @subpackage          form
 * @since               2.0.0
 * @author              Kazumi Ono (AKA onokazu) http://www.myweb.ne.jp/, http://jp.xoops.org/
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * A text label
 */
class XoopsFormLabel extends XoopsFormElement
{
    /**
     * Constructor
     *
     * @param string $caption Caption
     * @param string $value   Text
     * @param string $name
     */
    public function __construct(string $caption = '', /**
     * Text
     *
     * @access private
     */
    public $value = '', string $name = '')
    {
        $this->setCaption($caption);
        $this->setName($name);
    }

    /**
     * Get the "value" attribute
     *
     * @param  bool $encode To sanitizer the text?
     * @return string
     */
    public function getValue(bool $encode = false): string
    {
        return $encode ? htmlspecialchars($this->value, ENT_QUOTES | ENT_HTML5) : $this->value;
    }

    /**
     * Prepare HTML for output
     *
     * @return string
     */
    public function render(): string
    {
        return XoopsFormRenderer::getInstance()->get()->renderFormLabel($this);
    }
}
