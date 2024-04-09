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
 * A group of form elements
 */
class XoopsFormElementTray extends XoopsFormElement
{
    public const ORIENTATION_HORIZONTAL = 'horizontal';
    public const ORIENTATION_VERTICAL   = 'vertical';

    /**
     * array of form element objects
     *
     * @var array
     * @access private
     */
    private array $_elements = [];

    /**
     * required elements
     *
     * @var array
     */
    public mixed $required = [];

    protected string $orientation;

    /**
     * constructor
     *
     * @param string $caption   Caption for the group.
     * @param string $delimeter HTML to separate the elements
     * @param string $name
     */
    public function __construct(string  $caption, /**
     * HTML to seperate the elements
     *
     * @access private
     */
                                            private $delimeter = '&nbsp;', string $name = '')
    {
        $this->setName($name);
        $this->setCaption($caption);
    }

    /**
     * Is this element a container of other elements?
     *
     * @return bool true
     */
    public function isContainer(): bool
    {
        return true;
    }

    /**
     * Find out if there are required elements.
     *
     * @return bool
     */
    public function isRequired(): bool
    {
        return !empty($this->required);
    }

    /**
     * Add an element to the group
     *
     * @param XoopsFormElement $formElement {@link XoopsFormElement} to add
     *
     */
    public function addElement(XoopsFormElement $formElement, bool $required = false): void
    {
        $this->_elements[] = $formElement;
        if (!$formElement->isContainer()) {
            if ($required) {
                $formElement->required = true;
                $this->required[]      = $formElement;
            }
        } else {
            $required_elements = $formElement->getRequired();
            $count             = count($required_elements);
            for ($i = 0; $i < $count; ++$i) {
                $this->required[] = &$required_elements[$i];
            }
        }
    }

    /**
     * get an array of "required" form elements
     *
     * @return array array of {@link XoopsFormElement}s
     */
    public function &getRequired(): array
    {
        return $this->required;
    }

    /**
     * Get an array of the elements in this group
     *
     * @param bool $recurse get elements recursively?
     * @return XoopsFormElement[]  Array of {@link XoopsFormElement} objects.
     */
    public function &getElements(bool $recurse = false): array
    {
        if (!$recurse) {
            return $this->_elements;
        } else {
            $ret   = [];
            $count = count($this->_elements);
            for ($i = 0; $i < $count; ++$i) {
                if (!$this->_elements[$i]->isContainer()) {
                    $ret[] = &$this->_elements[$i];
                } else {
                    $elements = &$this->_elements[$i]->getElements(true);
                    $count2   = count($elements);
                    for ($j = 0; $j < $count2; ++$j) {
                        $ret[] = &$elements[$j];
                    }
                    unset($elements);
                }
            }

            return $ret;
        }
    }

    /**
     * Get the delimiter of this group
     *
     * @param bool $encode To sanitizer the text?
     * @return string The delimiter
     */
    public function getDelimeter(bool $encode = false): string
    {
        return $encode ? htmlspecialchars(str_replace('&nbsp;', ' ', $this->delimeter), ENT_QUOTES | ENT_HTML5) : $this->delimeter;
    }

    /**
     * setOrientation() communicate to renderer the expected tray orientation
     *   \XoopsFormElementTray::ORIENTATION_HORIZONTAL for across
     *   \XoopsFormElementTray::ORIENTATION_VERTICAL for up and down
     *
     * If not set explicitly, a default value will be assigned on getOrientation()
     *
     * @param string $direction ORIENTATION constant
     */
    public function setOrientation(string $direction): void
    {
        if ($direction !== self::ORIENTATION_VERTICAL) {
            $direction = self::ORIENTATION_HORIZONTAL;
        }
        $this->orientation = $direction;
    }

    /**
     * getOrientation() return the expected tray orientation
     *
     * The value will be assigned a default value if not previously set.
     *
     * The default logic considers the presence of an HTML br tag in delimeter
     * as implying ORIENTATION_VERTICAL for bc
     *
     * @return string either \XoopsFormElementTray::ORIENTATION_HORIZONTAL
     *                    or \XoopsFormElementTray::ORIENTATION_VERTICAL\
    */
    public function getOrientation(): string
    {
        if (!isset($this->orientation)) {
            if(false !== stripos($this->delimeter, '<br')) {
                $this->orientation = self::ORIENTATION_VERTICAL;
                // strip tag as renderer should supply the relevant html
            } else {
                $this->orientation = self::ORIENTATION_HORIZONTAL;
            }
        }
        $this->delimeter = preg_replace('#<br ?\/?>#i', '', $this->delimeter);
        return $this->orientation;
    }

    /**
     * prepare HTML to output this group
     *
     * @return string HTML output
     */
    public function render(): string
    {
        return XoopsFormRenderer::getInstance()->get()->renderFormElementTray($this);
    }
}
