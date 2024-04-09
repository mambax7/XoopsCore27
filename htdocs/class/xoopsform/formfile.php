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
 * A file upload field
 */
class XoopsFormFile extends XoopsFormElement
{
    /**
     * Maximum size for an uploaded file
     *
     * @var int
     * @access private
     */
    public int $_maxFileSize;

    /**
     * Constructor
     *
     * @param string $caption     Caption
     * @param string $name        "name" attribute
     * @param int    $maxfilesize Maximum size for an uploaded file
     */
    public function __construct(string $caption, string $name, int $maxfilesize)
    {
        $this->setCaption($caption);
        $this->setName($name);
        $this->_maxFileSize = $maxfilesize;
    }

    /**
     * Get the maximum filesize
     *
     * @return int
     */
    public function getMaxFileSize(): int
    {
        return $this->_maxFileSize;
    }

    /**
     * prepare HTML for output
     *
     * @return string HTML
     */
    public function render(): string
    {
        return XoopsFormRenderer::getInstance()->get()->renderFormFile($this);
    }
}
