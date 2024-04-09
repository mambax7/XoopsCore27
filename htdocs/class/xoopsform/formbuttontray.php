<?php

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * XoopsFormButtonTray
 *
 * @copyright       (c) 2000-2017 XOOPS Project (www.xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             kernel
 * @subpackage          form
 * @since               2.4.0
 * @author              John Neill <catzwolf@xoops.org>
 *
 */

class XoopsFormButtonTray extends XoopsFormElement
{

    /**
     * Constructor
     *
     * @param mixed  $name
     * @param string $value
     * @param string $type Type of the button. This could be either "button", "submit", or "reset"
     * @param string $onclick
     * @param bool   $showDelete
     */
    public function __construct(mixed         $name,
                                public string $value = '',
                                public string $type = 'submit',
                                string        $onclick = '',
                                public bool   $showDelete = false)
    {
        $this->setName($name);
        $this->setExtra($onclick);

    }

    /**
     * XoopsFormButtonTray::getValue()
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * XoopsFormButtonTray::getType()
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * XoopsFormButtonTray::render()
     *
     * @return string|void
     */
    public function render()
    {
        return XoopsFormRenderer::getInstance()->get()->renderFormButtonTray($this);
    }
}
