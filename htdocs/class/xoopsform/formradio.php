<?php

/**
 * XOOPS form radio compo
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright    (c) 2000-2017 XOOPS Project (www.xoops.org)
 * @license          GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package          kernel
 * @since            2.0
 * @author           Kazumi Ono (AKA onokazu) http://www.myweb.ne.jp/, http://jp.xoops.org/
 * @author           Taiwen Jiang <phppp@users.sourceforge.net>
 * @package          kernel
 * @subpackage       form
 * @todo             template
 */
class XoopsFormRadio extends XoopsFormElement
{
    /**
     * Array of Options
     *
     * @var array
     * @access private
     */
    public array $options = [];

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
     * @param string      $caption Caption
     * @param string      $name    "name" attribute
     * @param string|null $value   Pre-selected value
     * @param string $delimeter HTML to seperate the elements
     */
    public function __construct(string $caption, string $name, /**
     * Pre-selected value
     *
     * @access private
     */
     public $value = null, /**
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
    }

    /**
     * Get the "value" attribute
     *
     * @param  bool $encode To sanitizer the text?
     * @return string
     */
    public function getValue(bool $encode = false): ?string
    {
        return ($encode && $this->value !== null) ? htmlspecialchars((string) $this->value, ENT_QUOTES | ENT_HTML5) : $this->value;
    }

    /**
     * Set the pre-selected value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * Add an option
     *
     * @param string $value "value" attribute - This gets submitted as form-data.
     * @param string $name  "name" attribute - This is displayed. If empty, we use the "value" instead.
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
     * Adds multiple options
     *
     * @param array $options Associative array of value->name pairs.
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
     * Prepare HTML for output
     *
     * @return string HTML
     */
    public function render(): string
    {
        return XoopsFormRenderer::getInstance()->get()->renderFormRadio($this);
    }
}
