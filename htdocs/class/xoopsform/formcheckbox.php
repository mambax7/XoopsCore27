<?php
/**
 * XOOPS form checkbox compo
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
 * @since               2.0
 * @author              Kazumi Ono (AKA onokazu) http://www.myweb.ne.jp/, http://jp.xoops.org/
 * @author              Skalpa Keo <skalpa@xoops.org>
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

xoops_load('XoopsFormElement');

/**
 * Class XoopsFormCheckBox
 */
class XoopsFormCheckBox extends XoopsFormElement
{
    /**
     * Availlable options
     *
     * @var array
     * @access private
     */
    public array $options = [];

    /**
     * pre-selected values in array
     *
     * @var array
     * @access private
     */
    public array $_value = [];

    /**
     * Columns per line for rendering
     * Leave unset (null) to put all options in one line
     * Set to 1 to put each option on its own line
     * Any other positive integer 'n' to put 'n' options on each line
     *
     * @var int
     * @access public
     */
    public int $columns;

    /**
     * Constructor
     *
     * @param string $caption
     * @param string $name
     * @param mixed|null $value     Either one value as a string or an array of them.
     * @param string $delimeter HTML to separate the elements
     */
    public function __construct(string $caption, string $name, mixed $value = null, /**
     * HTML to seperate the elements
     *
     * @access private
     */
    public $delimeter = '&nbsp;')
    {
        $this->setCaption($caption);
        $this->setName($name);
        if (isset($value)) {
            $this->setValue($value);
        }
        $this->setFormType('checkbox');
    }

    /**
     * Get the "value"
     *
     * @param  bool $encode To sanitizer the text?
     * @return array
     */
    public function getValue(bool $encode = false): array
    {
        if (!$encode) {
            return $this->_value;
        }
        $value = [];
        foreach ($this->_value as $val) {
            $value[] = $val ? htmlspecialchars((string) $val, ENT_QUOTES | ENT_HTML5) : $val;
        }

        return $value;
    }

    /**
     * Set the "value"
     *
     *
     */
    public function setValue(array $value): void
    {
        $this->_value = [];
        if (is_array($value)) {
            foreach ($value as $v) {
                $this->_value[] = $v;
            }
        } else {
            $this->_value[] = $value;
        }
    }

    /**
     * Add an option
     */
    public function addOption(string $value, string $name = ''): void
    {
        if ($name != '') {
            $this->options[$value] = $name;
        } else {
            $this->options[$value] = $value;
        }
    }

    /**
     * Add multiple Options at once
     *
     * @param array $options Associative array of value->name pairs
     */
    public function addOptionArray(array $options): void
    {
        if (is_array($options)) {
            foreach ($options as $k => $v) {
                $this->addOption($k, $v);
            }
        }
    }

    /**
     * Get an array with all the options
     *
     * @param  bool|int $encode To sanitizer the text? potential values: 0 - skip; 1 - only for value; 2 - for both value and name
     * @return array    Associative array of value->name pairs
     */
    public function getOptions(bool|int $encode = false): array
    {
        if (!$encode) {
            return $this->options;
        }
        $value = [];
        foreach ($this->options as $val => $name) {
            $value[$encode ? htmlspecialchars((string) $val, ENT_QUOTES | ENT_HTML5) : $val] = ($encode > 1) ? htmlspecialchars((string) $name, ENT_QUOTES | ENT_HTML5) : $name;
        }

        return $value;
    }

    /**
     * Get the delimiter of this group
     *
     * @param  bool $encode To sanitizer the text?
     * @return string The delimiter
     */
    public function getDelimeter(bool $encode = false): string
    {
        return $encode ? htmlspecialchars(str_replace('&nbsp;', ' ', $this->delimeter), ENT_QUOTES | ENT_HTML5) : $this->delimeter;
    }

    /**
     * prepare HTML for output
     *
     * @return string
     */
    public function render(): string
    {
        return XoopsFormRenderer::getInstance()->get()->renderFormCheckBox($this);
    }

    /**
     * Render custom javascript validation code
     *
     * @seealso XoopsForm::renderValidationJS
     */
    public function renderValidationJS()
    {
        // render custom validation code if any
        if (!empty($this->customValidationCode)) {
            return implode(NWLINE, $this->customValidationCode);
            // generate validation code if required
        } elseif ($this->isRequired()) {
            $eltname    = $this->getName();
            $eltcaption = $this->getCaption();
            $eltmsg     = empty($eltcaption) ? sprintf(_FORM_ENTER, $eltname) : sprintf(_FORM_ENTER, $eltcaption);
            $eltmsg     = str_replace('"', '\"', stripslashes($eltmsg));

            return NWLINE . "var hasChecked = false; var checkBox = myform.elements['{$eltname}']; if (checkBox.length) {for (var i = 0; i < checkBox.length; i++) {if (checkBox[i].checked == true) {hasChecked = true; break;}}} else {if (checkBox.checked == true) {hasChecked = true;}}if (!hasChecked) {window.alert(\"{$eltmsg}\");if (checkBox.length) {checkBox[0].focus();} else {checkBox.focus();}return false;}";
        }

        return '';
    }
}
