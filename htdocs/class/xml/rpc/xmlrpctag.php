<?php
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * @copyright    XOOPS Project https://xoops.org/
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package
 * @since
 * @author       XOOPS Development Team, Kazumi Ono (AKA onokazu)
 */

/**
 * Class XoopsXmlRpcDocument
 */
class XoopsXmlRpcDocument
{
    public $_tags = [];

    /**
     * XoopsXmlRpcDocument constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param $tagobj
     */
    public function add(&$tagobj)
    {
        $this->_tags[] =& $tagobj;
    }

    public function render()
    {
    }
}

/**
 * Class XoopsXmlRpcResponse
 */
class XoopsXmlRpcResponse extends XoopsXmlRpcDocument
{
    /**
     * @return string
     */
    public function render()
    {
        $count   = count($this->_tags);
        $payload = '';
        for ($i = 0; $i < $count; ++$i) {
            if (!$this->_tags[$i]->isFault()) {
                $payload .= $this->_tags[$i]->render();
            } else {
                return '<?xml version="1.0"?><methodResponse>' . $this->_tags[$i]->render() . '</methodResponse>';
            }
        }

        return '<?xml version="1.0"?><methodResponse><params><param>' . $payload . '</param></params></methodResponse>';
    }
}

/**
 * Class XoopsXmlRpcRequest
 */
class XoopsXmlRpcRequest extends XoopsXmlRpcDocument
{
    public $methodName;

    /**
     * @param $methodName
     */
    public function __construct($methodName)
    {
        $this->methodName = trim((string) $methodName);
    }

    /**
     * @return string
     */
    public function render()
    {
        $count   = count($this->_tags);
        $payload = '';
        for ($i = 0; $i < $count; ++$i) {
            $payload .= '<param>' . $this->_tags[$i]->render() . '</param>';
        }

        return '<?xml version="1.0"?><methodCall><methodName>' . $this->methodName . '</methodName><params>' . $payload . '</params></methodCall>';
    }
}

/**
 * Class XoopsXmlRpcTag
 */
class XoopsXmlRpcTag
{
    public $_fault = false;

    /**
     * XoopsXmlRpcTag constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param $text
     *
     * @return mixed
     */
    public function &encode(&$text)
    {
        $text = preg_replace(["/\&([a-z\d\#]+)\;/i", "/\&/", "/\#\|\|([a-z\d\#]+)\|\|\#/i"], [
            "#||\\1||#",
            '&amp;',
            "&\\1;"
        ],                   str_replace([
                                      '<',
                                      '>'
                                         ], [
                                      '&lt;',
                                      '&gt;'
                                         ], (string) $text));

        return $text;
    }

    /**
     * @param bool $fault
     */
    public function setFault($fault = true)
    {
        $this->_fault = ((int)$fault > 0);// ? true : false;
    }

    /**
     * @return bool
     */
    public function isFault()
    {
        return $this->_fault;
    }

    public function render()
    {
    }
}

/**
 * Class XoopsXmlRpcFault
 */
class XoopsXmlRpcFault extends XoopsXmlRpcTag
{
    public $_code;
    public $_extra;

    /**
     * @param      $code
     * @param null $extra
     */
    public function __construct($code, $extra = null)
    {
        $this->setFault(true);
        $this->_code  = (int)$code;
        $this->_extra = isset($extra) ? trim($extra) : '';
    }

    /**
     * @return string
     */
    public function render()
    {
        $string = match ($this->_code) {
            101 => 'Invalid server URI',
            102 => 'Parser parse error',
            103 => 'Module not found',
            104 => 'User authentication failed',
            105 => 'Module API not found',
            106 => 'Method response error',
            107 => 'Method not supported',
            108 => 'Invalid parameter',
            109 => 'Missing parameters',
            110 => 'Selected blog application does not exist',
            111 => 'Method permission denied',
            default => 'Method response error',
        };
        $string .= "\n" . $this->_extra;

        return '<fault><value><struct><member><name>faultCode</name><value>' . $this->_code . '</value></member><member><name>faultString</name><value>' . $this->encode($string) . '</value></member></struct></value></fault>';
    }
}

/**
 * Class XoopsXmlRpcInt
 */
class XoopsXmlRpcInt extends XoopsXmlRpcTag
{
    public $_value;

    /**
     * @param $value
     */
    public function __construct($value)
    {
        $this->_value = (int)$value;
    }

    /**
     * @return string
     */
    public function render()
    {
        return '<value><int>' . $this->_value . '</int></value>';
    }
}

/**
 * Class XoopsXmlRpcDouble
 */
class XoopsXmlRpcDouble extends XoopsXmlRpcTag
{
    public $_value;

    /**
     * @param $value
     */
    public function __construct($value)
    {
        $this->_value = (float)$value;
    }

    /**
     * @return string
     */
    public function render()
    {
        return '<value><double>' . $this->_value . '</double></value>';
    }
}

/**
 * Class XoopsXmlRpcBoolean
 */
class XoopsXmlRpcBoolean extends XoopsXmlRpcTag
{
    public $_value;

    /**
     * @param $value
     */
    public function __construct($value)
    {
        $this->_value = (!empty($value) && $value != false) ? 1 : 0;
    }

    /**
     * @return string
     */
    public function render()
    {
        return '<value><boolean>' . $this->_value . '</boolean></value>';
    }
}

/**
 * Class XoopsXmlRpcString
 */
class XoopsXmlRpcString extends XoopsXmlRpcTag
{
    public $_value;

    /**
     * @param $value
     */
    public function __construct($value)
    {
        $this->_value = (string)$value;
    }

    /**
     * @return string
     */
    public function render()
    {
        return '<value><string>' . $this->encode($this->_value) . '</string></value>';
    }
}

/**
 * Class XoopsXmlRpcDatetime
 */
class XoopsXmlRpcDatetime extends XoopsXmlRpcTag
{
    public $_value;

    /**
     * @param $value
     */
    public function __construct($value)
    {
        if (!is_numeric($value)) {
            $this->_value = strtotime((string) $value);
        } else {
            $this->_value = (int)$value;
        }
    }

    /**
     * @return string
     */
    public function render()
    {
        return '<value><dateTime.iso8601>' . gmstrftime('%Y%m%dT%H:%M:%S', $this->_value) . '</dateTime.iso8601></value>';
    }
}

/**
 * Class XoopsXmlRpcBase64
 */
class XoopsXmlRpcBase64 extends XoopsXmlRpcTag
{
    public $_value;

    /**
     * @param $value
     */
    public function __construct($value)
    {
        $this->_value = base64_encode((string) $value);
    }

    /**
     * @return string
     */
    public function render()
    {
        return '<value><base64>' . $this->_value . '</base64></value>';
    }
}

/**
 * Class XoopsXmlRpcArray
 */
class XoopsXmlRpcArray extends XoopsXmlRpcTag
{
    public $_tags = [];

    /**
     * XoopsXmlRpcArray constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param $tagobj
     */
    public function add(&$tagobj)
    {
        $this->_tags[] =& $tagobj;
    }

    /**
     * @return string
     */
    public function render()
    {
        $count = count($this->_tags);
        $ret   = '<value><array><data>';
        for ($i = 0; $i < $count; ++$i) {
            $ret .= $this->_tags[$i]->render();
        }
        $ret .= '</data></array></value>';

        return $ret;
    }
}

/**
 * Class XoopsXmlRpcStruct
 */
class XoopsXmlRpcStruct extends XoopsXmlRpcTag
{
    public $_tags = [];

    /**
     * XoopsXmlRpcStruct constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param $name
     * @param $tagobj
     */
    public function add($name, &$tagobj)
    {
        $this->_tags[] = ['name' => $name, 'value' => $tagobj];
    }

    /**
     * @return string
     */
    public function render()
    {
        $count = count($this->_tags);
        $ret   = '<value><struct>';
        for ($i = 0; $i < $count; ++$i) {
            $ret .= '<member><name>' . $this->encode($this->_tags[$i]['name']) . '</name>' . $this->_tags[$i]['value']->render() . '</member>';
        }
        $ret .= '</struct></value>';

        return $ret;
    }
}
