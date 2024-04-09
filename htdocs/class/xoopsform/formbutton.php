<?php
/**
 * XOOPS form element of button
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
defined('XOOPS_ROOT_PATH') || exit('XOOPS root path not defined');

xoops_load('XoopsFormElement');

class XoopsFormButton extends XoopsFormElement
{

    /**
     * Constructor
     *
     * @param string $caption Caption
     * @param string $name
     * @param string $value
     * @param string $type    Type of the button. Potential values: "button", "submit", or "reset"
     */
    public function __construct(string $caption, string $name,
        /**
         * value
         * @access    private
         */
                                public $value = '', /**
         * Type of the button. This could be either "button", "submit", or "reset"
         * @access    private
         */
                                public $type = 'button')
    {
        $this->setCaption($caption);
        $this->setName($name);

    }

    /**
     * Get the initial value
     *
     * @param bool $encode To sanitizer the text?
     * @return string
     */
    public function getValue(bool $encode = false): string
    {
        return $encode ? htmlspecialchars($this->value, ENT_QUOTES | ENT_HTML5) : $this->value;
    }


    /**
     * Get the type
     *
     * @return string
     */
    public function getType(): string
    {
        return in_array(strtolower($this->type), ['button', 'submit', 'reset']) ? $this->type : 'button';
    }

    /**
     * prepare HTML for output
     *
     * @return string
     */
    public function render(): string
    {
        return XoopsFormRenderer::getInstance()->get()->renderFormButton($this);
    }
}
