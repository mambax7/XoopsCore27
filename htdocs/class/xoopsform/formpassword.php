<?php
/**
 * Password form element
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
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Password Field
 */
class XoopsFormPassword extends XoopsFormElement
{
    /**
     * Size of the field.
     *
     * @var int
     * @access private
     */
    public int $_size;

    /**
     * Maximum length of the text
     *
     * @var int
     * @access private
     */
    public int $_maxlength;

    /**
     * Initial content of the field.
     *
     * @var string
     * @access private
     */
    public string $_value;

    /**
     * Cache password with browser. Disabled by default for security consideration
     * Added in 2.3.1
     *
     * @var boolean
     * @access public
     */
    public bool $autoComplete = false;

    /**
     * Constructor
     *
     * @param string $caption      Caption
     * @param string $name         "name" attribute
     * @param int    $size         Size of the field
     * @param int    $maxlength    Maximum length of the text
     * @param string $value        Initial value of the field.
     *                             <strong>Warning:</strong> this is readable in cleartext in the page's source!
     * @param bool   $autoComplete To enable autoComplete or browser cache
     */
    public function __construct(string $caption, string $name, int $size, int $maxlength, string $value = '', bool $autoComplete = false)
    {
        $this->setCaption($caption);
        $this->setName($name);
        $this->_size      = $size;
        $this->_maxlength = $maxlength;
        $this->setValue($value);
        $this->autoComplete = !empty($autoComplete);
    }

    /**
     * Get the field size
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->_size;
    }

    /**
     * Get the max length
     *
     * @return int
     */
    public function getMaxlength(): int
    {
        return $this->_maxlength;
    }

    /**
     * Get the "value" attribute
     *
     * @param  bool $encode To sanitizer the text?
     * @return string
     */
    public function getValue(bool $encode = false): string
    {
        return $encode ? htmlspecialchars($this->_value, ENT_QUOTES | ENT_HTML5) : $this->_value;
    }

    /**
     * Set the initial value
     *
     * @patam $value    string
     * @param $value
     */
    public function setValue($value): void
    {
        $this->_value = $value;
    }

    /**
     * Prepare HTML for output
     *
     * @return string HTML
     */
    public function render(): string
    {
        return XoopsFormRenderer::getInstance()->get()->renderFormPassword($this);
    }
}
