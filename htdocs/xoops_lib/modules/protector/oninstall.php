<?php
// start hack by Trabis
if (!class_exists('ProtectorRegistry')) {
    exit('Registry not found');
}

$registry  = ProtectorRegistry::getInstance();
$mydirname = $registry->getEntry('mydirname');
$mydirpath = $registry->getEntry('mydirpath');
$language  = $registry->getEntry('language');
// end hack by Trabis

eval(' function xoops_module_install_' . $mydirname . '( $module ) { return protector_oninstall_base( $module , "' . $mydirname . '" ) ; } ');

if (!function_exists('protector_oninstall_base')) {

    /**
     * @param $module
     * @param $mydirname
     *
     * @return bool
     */
    function protector_oninstall_base($module, $mydirname)
    {
        /** @var XoopsModule $module */
        // translations on module install

        global $ret; // TODO :-D

        if (!is_array($ret)) {
            $ret = [];
        }

        /** @var XoopsMySQLDatabase $db */
        $db  = XoopsDatabaseFactory::getDatabaseConnection();
        $mid = $module->getVar('mid');

        // TABLES (loading mysql.sql)
        $sql_file_path = __DIR__ . '/sql/mysql.sql';
        $prefix_mod    = $db->prefix() . '_' . $mydirname;
        if (file_exists($sql_file_path)) {
            $ret[] = 'SQL file found at <b>' . htmlspecialchars((string) $sql_file_path, ENT_QUOTES | ENT_HTML5) . '</b>.<br> Creating tables...<br>';

            include_once XOOPS_ROOT_PATH . '/class/database/sqlutility.php';
            $sqlutil = new SqlUtility; //old code is -> $sqlutil =& new SqlUtility ; //hack by Trabis

            $sql_query = trim(file_get_contents($sql_file_path));
            $sqlutil::splitMySqlFile($pieces, $sql_query);
            $created_tables = [];
            foreach ($pieces as $piece) {
                $prefixed_query = $sqlutil::prefixQuery($piece, $prefix_mod);
                if (!$prefixed_query) {
                    $ret[] = 'Invalid SQL <b>' . htmlspecialchars((string) $piece, ENT_QUOTES | ENT_HTML5) . '</b><br>';

                    return false;
                }
                if (!$db->query($prefixed_query[0])) {
                    $ret[] = '<b>' . htmlspecialchars((string) $db->error(), ENT_QUOTES | ENT_HTML5) . '</b><br>';

                    //var_dump( $db->error() ) ;
                    return false;
                } else {
                    if (!in_array($prefixed_query[4], $created_tables)) {
                        $ret[]            = 'Table <b>' . htmlspecialchars((string) $prefix_mod . '_' . $prefixed_query[4], ENT_QUOTES | ENT_HTML5) . '</b> created.<br>';
                        $created_tables[] = $prefixed_query[4];
                    } else {
                        $ret[] = 'Data inserted to table <b>' . htmlspecialchars((string) $prefix_mod . '_' . $prefixed_query[4], ENT_QUOTES | ENT_HTML5) . '</b>.</br />';
                    }
                }
            }
        }

        // TEMPLATES
        /** @var XoopsTplfileHandler $tplfile_handler */
        $tplfile_handler = xoops_getHandler('tplfile');
        $tpl_path        = __DIR__ . '/templates';
        // Check if the directory exists
        if (is_dir($tpl_path) && is_readable($tpl_path)) {
            // Try to open the directory
             if ($handler = opendir($tpl_path . '/')) {
                while (($file = readdir($handler)) !== false) {
                    if (str_starts_with($file, '.')) {
                        continue;
                    }
                    $file_path = $tpl_path . '/' . $file;
                    if (is_file($file_path) && in_array(strrchr($file, '.'), ['.html', '.css', '.js'])) {
                        $mtime   = (int)(@filemtime($file_path));
                        $tplfile = $tplfile_handler->create();
                        $tplfile->setVar('tpl_source', file_get_contents($file_path), true);
                        $tplfile->setVar('tpl_refid', $mid);
                        $tplfile->setVar('tpl_tplset', 'default');
                        $tplfile->setVar('tpl_file', $mydirname . '_' . $file);
                        $tplfile->setVar('tpl_desc', '', true);
                        $tplfile->setVar('tpl_module', $mydirname);
                        $tplfile->setVar('tpl_lastmodified', $mtime);
                        $tplfile->setVar('tpl_lastimported', 0);
                        $tplfile->setVar('tpl_type', 'module');
                        if (!$tplfile_handler->insert($tplfile)) {
                            $ret[] = '<span style="color:#ff0000;">ERROR: Could not insert template <b>' . htmlspecialchars((string) $mydirname . '_' . $file, ENT_QUOTES | ENT_HTML5) . '</b> to the database.</span><br>';
                        } else {
                            $tplid = $tplfile->getVar('tpl_id');
                            $ret[] = 'Template <b>' . htmlspecialchars((string) $mydirname . '_' . $file, ENT_QUOTES | ENT_HTML5) . '</b> added to the database. (ID: <b>' . $tplid . '</b>)<br>';
                            // generate compiled file
                            include_once XOOPS_ROOT_PATH . '/class/xoopsblock.php';
                            include_once XOOPS_ROOT_PATH . '/class/template.php';
                            if (!xoops_template_touch((string)$tplid)) {
                                $ret[] = '<span style="color:#ff0000;">ERROR: Failed compiling template <b>' . htmlspecialchars((string) $mydirname . '_' . $file, ENT_QUOTES | ENT_HTML5) . '</b>.</span><br>';
                            } else {
                                $ret[] = 'Template <b>' . htmlspecialchars((string) $mydirname . '_' . $file, ENT_QUOTES | ENT_HTML5) . '</b> compiled.</span><br>';
                            }
                        }
                    }
                }
                closedir($handler);
            } else {
                // Handle the error condition when opendir fails
                $ret[] = '<span style="color:#ff0000;">ERROR: Could not open the template directory:  <b>' . htmlspecialchars((string) $tpl_path, ENT_QUOTES | ENT_HTML5) . '</b>.</span><br>';
            }
        } else {
            // Directory does not exist; handle this condition
            $ret[] = '<span style="color:#ff0000;">ERROR: The template directory does not exist or is not readable: <b>' . htmlspecialchars((string) $tpl_path, ENT_QUOTES | ENT_HTML5) . '</b>.</span><br>';
        }
        include_once XOOPS_ROOT_PATH . '/class/xoopsblock.php';
        include_once XOOPS_ROOT_PATH . '/class/template.php';
        xoops_template_clear_module_cache($mid);

        return true;
    }

    /**
     * @param $module_obj
     * @param $log
     */
    function protector_message_append_oninstall(&$module_obj, &$log)
    {
        if (isset($GLOBALS['ret']) && is_array($GLOBALS['ret'])) {
            foreach ($GLOBALS['ret'] as $message) {
                $log->add(strip_tags((string) $message));
            }
        }

        // use mLog->addWarning() or mLog->addError() if necessary
    }
}
