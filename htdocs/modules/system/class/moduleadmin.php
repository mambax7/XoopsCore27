<?php
/**
 * Module Admin
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright   (c) 2000-2016 XOOPS Project (www.xoops.org)
 * @license         GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author          Grégory Mage (Aka Mage)
 */

 class ModuleAdmin {

    private $_itemButton        = [];
    private $_itemInfoBox       = [];
    private $_itemInfoBoxLine   = [];
    private $_itemConfigBoxLine = [];

    /**
     * @var XoopsModule
     */
    private $_obj;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $xoopsModule;
        $this->_obj =& $xoopsModule;
    }

    /**
     * addAssets - add assets to theme, if it is established
     *
     * @return void
     */
    private function addAssets()
    {
        static $added;

        if (empty($added) && !empty($GLOBALS['xoTheme'])) {
            $added = true;
            $GLOBALS['xoTheme']->addStylesheet("modules/system/css/admin.css");
        }
    }

    /**
     * Add Config Line
     * 
     * @param string $value
     * @param string $status
     * @param string $type
     *
     * @return bool
     */
    public function addConfigBoxLine($value = '', $status = '', $type = 'default')
    {
        $line = '';
        
        switch ($type) {
            default:
            case 'default':
                $this->_itemConfigBoxLine['items'][] = [
                    'type' => $status,
                    'msg'  => $value,
                ];
                break;

            case 'folder':
                if (!is_dir($value)) {
                    $this->_itemConfigBoxLine['items'][] = [
                        'type' => 'error',
                        'msg'  => sprintf(_AM_MODULEADMIN_CONFIG_FOLDERKO, $value),
                    ];
                } else {
                    $this->_itemConfigBoxLine['items'][] = [
                        'type' => 'success',
                        'msg'  => sprintf(_AM_MODULEADMIN_CONFIG_FOLDERKO, $value),
                    ];
                }
                break;

            case 'chmod':
                if (is_dir($value[0])) {
                    if (substr(decoct(fileperms($value[0])), 2) != $value[1]) {
                        $this->_itemConfigBoxLine['items'][] = [
                            'type' => 'error',
                            'msg'  => sprintf(_AM_MODULEADMIN_CONFIG_CHMOD, $value[0], $value[1], substr(decoct(fileperms($value[0])), 2)),
                        ];
                    } else {
                        $this->_itemConfigBoxLine['items'][] = [
                            'type' => 'success',
                            'msg'  => sprintf(_AM_MODULEADMIN_CONFIG_CHMOD, $value[0], $value[1], substr(decoct(fileperms($value[0])), 2)),
                        ];
                    }
                }
                break;
        }

        return true;
    }

    /**
     * @param        $title
     * @param        $link
     * @param string $icon
     * @param string $extra
     *
     * @return bool
     */
    public function addItemButton($title, $link, $icon = 'add', $extra = '')
    {
        $ret = [];
        $ret['title']        = $title;
        $ret['link']         = $link;
        $ret['icon']         = $icon . '.png';
        $ret['extra']        = $extra;
        $this->_itemButton[] = $ret;

        return true;
    }

    /**
     * Add Nivagition Menu
     * 
     * @param string $menu
     * @return void
     */
    public function addNavigation($menu = '')
    {
        global $xoops, $xoopsTpl;

        $this->addAssets();
        $path = $xoops->url('modules/' . $this->_obj->getVar('dirname') . '/');

        $this->_obj->loadAdminMenu();

        $xoopsTpl->assign('path', $path);
        $xoopsTpl->assign('menu', $menu);
        

        foreach (array_keys((array) $this->_obj->adminmenu) as $i) {
            if ($this->_obj->adminmenu[$i]['link'] == 'admin/' . $menu) {
                $xoopsTpl->assign('icon', $this->_obj->adminmenu[$i]['icon']);
                $xoopsTpl->assign('title', $this->_obj->adminmenu[$i]['title']);
                $xoopsTpl->assign('link', $this->_obj->adminmenu[$i]['link']);
            }
        }
        // Display Navigation
        $xoopsTpl->display('db:system_modules_navigation.tpl'); 
    }

    /**
     * Display Admin About page
     *
     * @param string $business the PAYPAL business email or Merchant Account ID
     * @param bool   $logo_xoops true to display XOOPS logo and link on page
     * @return void
     */
    public function renderAbout($business = '', $logo_xoops = true)
    {
        global $xoops, $xoopsTpl;

        $this->addAssets();

        $date         = preg_replace('/-\\\/', '/', $this->_obj->getInfo('release_date')); // make format a little more forgiving
        $date         = explode('/', $date);
        $author       = explode(',', $this->_obj->getInfo('author'));
        $nickname     = explode(',', $this->_obj->getInfo('nickname'));
        $release_date = formatTimestamp(mktime(0, 0, 0, $date[1], $date[2], $date[0]), 's');
        $module_dir   = $this->_obj->getVar('dirname');

        $license_url = $this->_obj->getInfo('license_url');
        $license_url = preg_match('%^(https?:)?//%', $license_url) ? $license_url : 'http://' . $license_url;
        $website = $this->_obj->getInfo('website');
        $website = preg_match('%^(https?:)?//%', $website) ? $website : 'http://' . $website;
        
        $xoopsTpl->assign('module_dir', $module_dir);
        $xoopsTpl->assign('module_name', $this->_obj->getVar('name'));
        $xoopsTpl->assign('module_version', $this->_obj->getVar('version'));
        $xoopsTpl->assign('module_img', $xoops->url('modules/' . $module_dir . '/' . $this->_obj->getInfo('image')));

        // Author
        $authorArray  = [];
        foreach ( $author as $k => $aName ) {
            $authorArray[$k] = ( isset( $nickname[$k] ) && ( '' != $nickname[$k] ) ) ? "{$aName} ({$nickname[$k]})" : (string)($aName);
        }
        $xoopsTpl->assign('author', implode(', ', $authorArray));
        $xoopsTpl->assign('release_date', $release_date);
        $xoopsTpl->assign('license', $this->_obj->getInfo('license'));
        $xoopsTpl->assign('license_url', $license_url);

        // Module Info
        $xoopsTpl->assign('module_description', $this->_obj->getInfo('description'));
        $xoopsTpl->assign('module_last_update', formatTimestamp($this->_obj->getVar('last_update'), 'm'));
        $xoopsTpl->assign('module_status', $this->_obj->getStatus());
        $xoopsTpl->assign('module_website_name', $this->_obj->getInfo('module_website_name'));
        $xoopsTpl->assign('module_website_url', $this->_obj->getInfo('module_website_url'));

        // Donation
        if ((1 !== preg_match('/[^a-zA-Z0-9]/', $business)) || (false !== checkEmail($business))) {
            $xoopsTpl->assign('business', $business);
        }

        // Changelog
        $changelog = '';
        $language = empty( $GLOBALS['xoopsConfig']['language'] ) ? 'english' : $GLOBALS['xoopsConfig']['language'];
        $file     = XOOPS_ROOT_PATH . "/modules/{$module_dir}/language/{$language}/changelog.txt";
        if ( !is_file( $file ) && ( 'english' !== $language ) ) {
            $file = XOOPS_ROOT_PATH . "/modules/{$module_dir}/language/english/changelog.txt";
        }
        if ( is_readable( $file ) ) {
            $changelog .= ( implode( '<br>', file( $file ) ) ) . "\n";
        } else {
            $file = XOOPS_ROOT_PATH . "/modules/{$module_dir}/docs/changelog.txt";
            if ( is_readable( $file ) ) {
                $changelog .= implode( '<br>', file( $file ) ) . "\n";
            }
        }
        $xoopsTpl->assign('changelog', $changelog);

        // Display page
        $xoopsTpl->display('db:system_modules_about.tpl'); 
    }

    /**
     * @param string $position
     * @param string $delimeter
     *
     * @return string
     */
    public function renderButton($position = 'right', $delimeter = '&nbsp;')
    {
        global $xoops, $xoopsTpl;
        
        $icons = xoops_getModuleOption('typeicons', 'system');
        if ($icons == '') {
            $icons = 'default';
        }
        $path = $xoops->url('modules/system/images/icons/' . $icons . '/');
        

        $this->addAssets();

        $xoopsTpl->assign('path', $path);
        $xoopsTpl->assign('buttons', $this->_itemButton );
        $xoopsTpl->assign('position', $position);

        $xoopsTpl->display('db:system_modules_button.tpl');

        $path = XOOPS_URL . '/Frameworks/moduleclasses/icons/32/';
    }

    /**
     * Display Admin Index page
     * 
     * @return void
     */
    public function renderIndex()
    {
        global $xoops, $xoopsTpl;

        $this->addAssets();
        $path = XOOPS_URL . '/modules/' . $this->_obj->getVar('dirname') . '/';
        $pathsystem = XOOPS_URL . '/modules/system/';
        
        $this->_obj->loadAdminMenu();

        // Help page
        if ($this->_obj->getInfo('help')) {
            $icons = xoops_getModuleOption('typeicons', 'system');
            if ($icons == '') {
                $icons = 'default';
            }
            $xoopsTpl->assign('helpurl',$xoops->url('modules/system/help.php?mid=' . $this->_obj->getVar('mid', 's') . '&amp;page=' . $this->_obj->getInfo('help')));
            $xoopsTpl->assign('helpicon', $xoops->url('modules/system/images/icons/' . $icons . '/help.png'));
            $xoopsTpl->assign('help', $this->_obj->getInfo('help'));
        }

        // Menu
        $xoopsTpl->assign('path', $path);
        $xoopsTpl->assign('modulenmenu', $this->_obj->adminmenu);

        // Info Box
        if ($this->_obj->getInfo('min_php') || $this->_obj->getInfo('min_xoops') || !empty($this->_itemConfigBoxLine)) {

            // PHP version
            if ($this->_obj->getInfo('min_php')) {
                if (version_compare(phpversion(), strtolower($this->_obj->getInfo('min_php')), '<')) {
                    $this->addConfigBoxLine( sprintf(_AM_MODULEADMIN_CONFIG_PHP, $this->_obj->getInfo('min_php'), phpversion()), 'error');
                } else {
                    $this->addConfigBoxLine( sprintf(_AM_MODULEADMIN_CONFIG_PHP, $this->_obj->getInfo('min_php'), phpversion()), 'success');
                }
            }

            // Database version
            $dbarray = $this->_obj->getInfo('min_db');
            if ($dbarray!=false) {
                // changes from redheadedrod to use connector specific version info
                switch (XOOPS_DB_TYPE) {
                    // server should be the same in both cases
                    case 'mysql':
                    case 'mysqli':
                        global $xoopsDB;
                        $dbCurrentVersion = $xoopsDB->getServerVersion();
                        break;
                    default: // don't really support anything other than mysql
                        $dbCurrentVersion = '0';
                        break;
                }
                $currentVerParts   = explode('.', (string)$dbCurrentVersion);
                $iCurrentVerParts  = array_map('intval', $currentVerParts);
                $dbRequiredVersion = $dbarray[XOOPS_DB_TYPE];
                $reqVerParts       = explode('.', (string)$dbRequiredVersion);
                $iReqVerParts      = array_map('intval', $reqVerParts);
                $icount            = $j = count($iReqVerParts);
                $reqVer            = $curVer = 0;
                for ($i = 0; $i < $icount; ++$i) {
                    $j--;
                    $reqVer += $iReqVerParts[$i] * 10 ** $j;
                    if (isset($iCurrentVerParts[$i])) {
                        $curVer += $iCurrentVerParts[$i] * 10 ** $j;
                    } else {
                        $curVer *= 10 ** $j;
                    }
                }
                if ($reqVer > $curVer) {
                    $this->addConfigBoxLine( sprintf(XOOPS_DB_TYPE . ' ' . _AM_MODULEADMIN_CONFIG_DB, $dbRequiredVersion, $dbCurrentVersion), 'error');
                } else {
                    $this->addConfigBoxLine( sprintf(XOOPS_DB_TYPE . ' ' . _AM_MODULEADMIN_CONFIG_DB, $dbRequiredVersion, $dbCurrentVersion), 'success');
                }
            }

            // Xoops version
            if ($this->_obj->getInfo('min_xoops')) {
                $currentXoopsVersion = strtolower(str_replace('XOOPS ', '', XOOPS_VERSION));
                if ($this->_obj->versionCompare($currentXoopsVersion, strtolower($this->_obj->getInfo('min_xoops')), '<')) {
                    $this->addConfigBoxLine( sprintf(_AM_MODULEADMIN_CONFIG_XOOPS, $this->_obj->getInfo('min_xoops'), substr(XOOPS_VERSION, 6, strlen(XOOPS_VERSION) - 6)), 'error');
                } else {
                    $this->addConfigBoxLine( sprintf(_AM_MODULEADMIN_CONFIG_XOOPS, $this->_obj->getInfo('min_xoops'), substr(XOOPS_VERSION, 6)), 'success');
                }
            }

            $xoopsTpl->assign('ret_info', $this->_itemConfigBoxLine);
        }

        // Display page
        $xoopsTpl->display('db:system_modules_index.tpl');
    }
 }