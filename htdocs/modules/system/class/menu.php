<?php
/**
 * Class for tab navigation
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2016 XOOPS Project (www.xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              John Neill (AKA Catzwolf)
 * @author              Andricq Nicolas (AKA MusS)
 */

// defined('XOOPS_ROOT_PATH') || exit('XOOPS root path not defined');

/**
 * Class SystemMenuHandler
 */
class SystemMenuHandler
{
    /**
     *
     * @var string
     */
    public $_menutop  = [];
    public $_menutabs = [];
    public $_obj;
    public $_header;
    public $_subheader;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $xoopsModule;
        $this->_obj = $xoopsModule;
    }

    /**
     * @param $addon
     */
    public function getAddon($addon)
    {
        $this->_obj =& $addon;
    }

    /**
     * @param        $value
     * @param string $name
     */
    public function addMenuTop($value, $name = '')
    {
        if ($name !== '') {
            $this->_menutop[$value] = $name;
        } else {
            $this->_menutop[$value] = $value;
        }
    }

    /**
     * @param      $options
     * @param bool $multi
     */
    public function addMenuTopArray($options, $multi = true)
    {
        if (is_array($options)) {
            if ($multi === true) {
                foreach ($options as $k => $v) {
                    $this->addOptionTop($k, $v);
                }
            } else {
                foreach ($options as $k) {
                    $this->addOptiontop($k, $k);
                }
            }
        }
    }

    /**
     * @param        $value
     * @param string $name
     */
    public function addMenuTabs($value, $name = '')
    {
        if ($name !== '') {
            $this->_menutabs[$value] = $name;
        } else {
            $this->_menutabs[$value] = $value;
        }
    }

    /**
     * @param      $options
     * @param bool $multi
     */
    public function addMenuTabsArray($options, $multi = true)
    {
        if (is_array($options)) {
            if ($multi === true) {
                foreach ($options as $k => $v) {
                    $this->addMenuTabsTop($k, $v);
                }
            } else {
                foreach ($options as $k) {
                    $this->addMenuTabsTop($k, $k);
                }
            }
        }
    }

    /**
     * @param $value
     */
    public function addHeader($value)
    {
        $this->_header = $value;
    }

    /**
     * @param $value
     */
    public function addSubHeader($value)
    {
        $this->_subheader = $value;
    }

    /**
     * @param string $basename
     *
     * @return string
     */
    public function breadcrumb_nav($basename = 'Home')
    {
        global $bc_site, $bc_label;
        $site       = $bc_site;
        $return_str = "<a href=\"/\">$basename</a>";
        $str        = substr(dirname(xoops_getenv('PHP_SELF')), 1);

        $arr = explode('/', $str);
        $num = count($arr);

        if ($num > 1) {
            foreach ($arr as $val) {
                $return_str .= ' &gt; <a href="' . $site . $val . '/">' . $bc_label[$val] . '</a>';
                $site .= $val . '/';
            }
        } elseif ($num == 1) {
            $arr = $str;
            $return_str .= ' &gt; <a href="' . $bc_site . $arr . '/">' . $bc_label[$arr] . '</a>';
        }

        return $return_str;
    }

    /**
     * @param int  $currentoption
     * @param bool $display
     *
     * @return string
     */
    public function render($currentoption = 1, $display = true)
    {
        global $modversion, $xoopsTpl;
        $_dirname = $this->_obj->getVar('dirname');
        $i        = 0;

        /**
         * Select current menu tab, sets id names for menu tabs
         */
        $j=0;
        foreach ($this->_menutabs as $k => $menus) {
            if ($j == $currentoption) {
                $breadcrumb = $menus;
            }
            $menuItems[] = 'modmenu_' . $j++;
        }

        $xoopsTpl->assign('module_name', $this->_obj->getVar('name'));
        $xoopsTpl->assign('module_dirname', $this->_obj->getVar('dirname'));
        $xoopsTpl->assign('page', $breadcrumb);
        $xoopsTpl->assign('menutop', $this->_menutop);
        $xoopsTpl->assign('menutabs', $this->_menutabs);
        // Display Module Menu
        $xoopsTpl->display('db:system_modules_menu.tpl');
    }
}
