<?php
/**
 * select form element
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
 * @author              John Neill <catzwolf@xoops.org>
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

xoops_load('XoopsFormElement');

class XoopsFormSelect extends XoopsFormElement
{
    /**
     * Options
     *
     * @var array
     * @access private
     */
    public array $options = [];


    /**
     * Constructor
     *
     * @param string $caption  Caption
     * @param string $name     "name" attribute
     * @param mixed  $value    Pre-selected value (or array of them).
     * @param int    $size     Number of rows. "1" makes a drop-down-list
     * @param bool $multiple Allow multiple selections?
     */
    public function __construct(string       $caption, string $name, /**
     * Pre-selcted values
     *
     * @access private
     */
     public mixed $value = [], /**
         * Number of rows. "1" makes a dropdown list.
     *
     * @access private
     */
     public int $size = 1, /**
     * Allow multiple selections?
     *
     * @access private
     */
    public $multiple = false)
    {
        $this->setCaption($caption);
        $this->setName($name);
        if (isset($value)) {
            $this->setValue($value);
        }
    }

    /**
     * Are multiple selections allowed?
     *
     * @return bool
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Get the size
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get an array of pre-selected values
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
     * Set pre-selected values
     */
    public function setValue(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                $this->_value[] = $v;
            }
        } elseif (isset($value)) {
            $this->_value[] = $value;
        }
    }

    /**
     * Add an option
     *
     * @param string $value "value" attribute
     * @param string $name  "name" attribute
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
     * Add multiple options
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
     * Note: both name and value should be sanitized. However, for backward compatibility, only value is sanitized for now.
     *
     * @param bool|int $encode To sanitizer the text? potential values: 0 - skip; 1 - only for value; 2 - for both value and name
     *
     * @return array Associative array of value->name pairs
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
     * Prepare HTML for output
     *
     * @return string HTML
     */
    public function render(): string
    {
        return XoopsFormRenderer::getInstance()->get()->renderFormSelect($this);
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
            return implode("\n", $this->customValidationCode);
            // generate validation code if required
        } elseif ($this->isRequired()) {
            $eltname    = $this->getName();
            $eltcaption = $this->getCaption();
            $eltmsg     = empty($eltcaption) ? sprintf(_FORM_ENTER, $eltname) : sprintf(_FORM_ENTER, $eltcaption);
            $eltmsg     = str_replace('"', '\"', stripslashes($eltmsg));

            return "\nvar hasSelected = false; var selectBox = myform.{$eltname};" . "for (i = 0; i < selectBox.options.length; i++) { if (selectBox.options[i].selected == true && selectBox.options[i].value != '') { hasSelected = true; break; } }" . "if (!hasSelected) { window.alert(\"{$eltmsg}\"); selectBox.focus(); return false; }";
        }

        return '';
    }
}
