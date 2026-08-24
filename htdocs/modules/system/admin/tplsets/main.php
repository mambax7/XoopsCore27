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
 * @copyright    2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package
 * @since
 * @author       XOOPS Development Team, Kazumi Ono (AKA onokazu)
 */

use Xmf\Request;

// Check users rights
if (!is_object($GLOBALS['xoopsUser']) || !is_object($GLOBALS['xoopsModule']) || !$GLOBALS['xoopsUser']->isAdmin($GLOBALS['xoopsModule']->mid())) {
    exit(_NOPERM);
}

// Get Action type
$op = Request::getString('op', 'list');

// Define main template
$GLOBALS['xoopsOption']['template_main'] = 'system_templates.tpl';
// Call Header
xoops_cp_header();

// Define scripts
$xoTheme->addScript('browse.php?Frameworks/jquery/jquery.js');
$xoTheme->addScript('browse.php?Frameworks/jquery/plugins/jquery.ui.js');
$xoTheme->addScript('modules/system/js/jquery.easing.js');
$xoTheme->addScript('modules/system/js/jqueryFileTree.js');
$xoTheme->addScript('modules/system/js/admin.js');
$xoTheme->addScript('modules/system/js/templates.js');
$xoTheme->addScript('modules/system/js/code_mirror/codemirror.js');
// Define Stylesheet
$xoTheme->addStylesheet(XOOPS_URL . '/modules/system/css/admin.css');
$xoTheme->addStylesheet(XOOPS_URL . '/modules/system/css/code_mirror/docs.css');

// Define Breadcrumb and tips
$xoBreadCrumb->addLink(_AM_SYSTEM_CONFIG, XOOPS_URL . '/modules/system/admin.php');
if ('list' === $op) {
    $xoBreadCrumb->addLink(_AM_SYSTEM_TEMPLATES_NAV_MAIN);
} else {
    $xoBreadCrumb->addLink(_AM_SYSTEM_TEMPLATES_NAV_MAIN, system_adminVersion('tplsets', 'adminpath'));
}

switch ($op) {
    //index
    default:
        // Assign Breadcrumb menu
        $xoBreadCrumb->addHelp(system_adminVersion('tplsets', 'help'));
        $xoBreadCrumb->addTips(_AM_SYSTEM_TEMPLATES_NAV_TIPS);
        $xoBreadCrumb->render();

        $GLOBALS['xoopsTpl']->assign('index', true);

        $form = new XoopsThemeForm(_AM_SYSTEM_TEMPLATES_GENERATE, 'form', 'admin.php?fct=tplsets', 'post', true);

        $ele            = new XoopsFormSelect(_AM_SYSTEM_TEMPLATES_SET, 'tplset', $GLOBALS['xoopsConfig']['template_set']);
        /** @var  XoopsTplsetHandler $tplset_handler */
        $tplset_handler = xoops_getHandler('tplset');
        $tplsetlist     = $tplset_handler->getList();
        asort($tplsetlist);
        foreach ($tplsetlist as $key => $name) {
            $ele->addOption($key, $name);
        }
        $form->addElement($ele);
        $form->addElement(new XoopsFormSelectTheme(_AM_SYSTEM_TEMPLATES_SELECT_THEME, 'select_theme', 1, 5), true);
        $form->addElement(new XoopsFormRadioYN(_AM_SYSTEM_TEMPLATES_FORCE_GENERATED, 'force_generated', 0, _YES, _NO), true);

        $modules        = new XoopsFormSelect(_AM_SYSTEM_TEMPLATES_SELECT_MODULES, 'select_modules');
        /** @var XoopsModuleHandler $module_handler */
        $module_handler = xoops_getHandler('module');
        $criteria       = new CriteriaCompo(new Criteria('isactive', 1));
        $moduleslist    = $module_handler->getList($criteria, true);
        $modules->addOption(0, _AM_SYSTEM_TEMPLATES_ALL_MODULES);
        $modules->addOptionArray($moduleslist);
        $form->addElement($modules, true);

        $form->addElement(new XoopsFormHidden('active_templates', '0'));
        $form->addElement(new XoopsFormHidden('active_modules', '0'));
        $form->addElement(new XoopsFormHidden('op', 'tpls_generate_surcharge'));
        $form->addElement(new XoopsFormButton('', 'submit', _SUBMIT, 'submit'));
        $xoopsTpl->assign('form', $form->render());
        break;

    //generate surcharge
    case 'tpls_generate_surcharge':
        if (!$GLOBALS['xoopsSecurity']->check()) {
            redirect_header('admin.php?fct=tplsets', 3, implode('<br>', $GLOBALS['xoopsSecurity']->getErrors()));
        }
        // Assign Breadcrumb menu
        $xoBreadCrumb->addHelp(system_adminVersion('tplsets', 'help') . '#override');
        $xoBreadCrumb->addLink(_AM_SYSTEM_TEMPLATES_NAV_FILE_GENERATED);
        $xoBreadCrumb->render();

        $selectModules = Request::getString('select_modules', '0');
        $activeModules = Request::getString('active_modules', '0');
        $selectTheme = Request::getString('select_theme', '');
        // Confine select_theme to a real installed theme leaf name — a separator, ../, or
        // an EMPTY value would let the generate/write paths below (theme_surcharge =
        // themes/<x>/modules) escape XOOPS_THEME_PATH or write to the themes/ root
        // (SECURITY.md A2-M-2). This generate op always requires a valid theme, so the
        // check is unconditional ('' fails the `+` regex). Covers every downstream write.
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $selectTheme)
            || !is_dir(XOOPS_THEME_PATH . '/' . $selectTheme)) {
            redirect_header('admin.php?fct=tplsets', 3, _AM_SYSTEM_TEMPLATES_ERROR);
            exit();
        }
        $forceGenerated = Request::getInt('force_generated', 0);
        if (  '0' === $selectModules ||  '1' === $activeModules) {
            //Generate modules
            if (Request::hasVar('select_theme') && Request::hasVar('force_generated')) {
                //we check if the module folder exists
                $theme_surcharge = XOOPS_THEME_PATH . '/' . $selectTheme . '/modules';
                $indexFile       = XOOPS_ROOT_PATH . '/modules/system/include/index.html';
                $verif_write     = false;
                $text            = '';

                // Shared writer for the four generate loops below: fopen()
                // checked - an unchecked false handle reaches fwrite() as a
                // TypeError on PHP 8 - and success requires the FULL byte
                // count, not merely a non-false fwrite(): a short write
                // would leave a truncated template reported as written
                // (review catch, three reviewers). An empty source still
                // passes (0 === strlen('')). fflush() and fclose() are part
                // of success so buffered bytes that never reach disk count
                // as failure. No retry loop: on a local file a short write
                // is a disk-level fault a retry will not repair - failing
                // the row (red icon, $verif_write stays false) is the
                // honest outcome. Scoped handler keeps the streams' native
                // full-path warnings out of the error handlers; the
                // explicit diagnostic names only the basename.
                $writeTemplate = static function (string $physicalFile, string $source): bool {
                    $ok = false;
                    set_error_handler(static function (): bool {
                        return true;
                    }, E_WARNING);
                    try {
                        $handle = fopen($physicalFile, 'w+');
                        if (false !== $handle) {
                            $written = fwrite($handle, $source);
                            $flushed = fflush($handle);
                            $closed  = fclose($handle);
                            $ok      = strlen($source) === $written && $flushed && $closed;
                        }
                    } finally {
                        restore_error_handler();
                    }
                    if (!$ok) {
                        trigger_error('Template write failed for ' . basename($physicalFile), E_USER_WARNING);
                    }

                    return $ok;
                };

                if (!is_dir($theme_surcharge)) {
                    //Create the modules folder

                    if (!is_dir($theme_surcharge)) {
                        mkdir($theme_surcharge, 0777);
                    }
                    chmod($theme_surcharge, 0777);
                    copy($indexFile, $theme_surcharge . '/index.html');
                }

                $tplset = Request::getString('tplset', 'default');

                //we only create templates that do not exist
                /** @var XoopsModuleHandler $module_handler */
                $module_handler = xoops_getHandler('module');
                /** @var  XoopsTplsetHandler $tplset_handler */
                $tplset_handler = xoops_getHandler('tplset');
                /** @var  XoopsTplfileHandler $tpltpl_handler */
                $tpltpl_handler = xoops_getHandler('tplfile');

                $criteria = new CriteriaCompo();
                $criteria->add(new Criteria('tplset_name', $tplset));
                $tplsets_arr = $tplset_handler->getObjects();
                $tcount      = $tplset_handler->getCount();

                $tpltpl_handler = xoops_getHandler('tplfile');
                $installed_mods = $tpltpl_handler->getModuleTplCount($tplset);

                //all templates or only one template
                if ((Request::getInt('active_templates', 0, 'GET') ?: Request::getInt('active_templates', 0, 'POST')) === 0) {
                    foreach (array_keys($tplsets_arr) as $i) {
                        $tplsetname = $tplsets_arr[$i]->getVar('tplset_name');
                        $tplstats   = $tpltpl_handler->getModuleTplCount($tplsetname);

                        if (count($tplstats) > 0) {
                            foreach ($tplstats as $moddir => $filecount) {
                                if (!preg_match('/^[a-zA-Z0-9_-]+$/', (string) $moddir)) {
                                    continue;
                                }
                                $module = $module_handler->getByDirname($moddir);
                                if (is_object($module)) {
                                    // create module folder
                                    if (!is_dir($theme_surcharge . '/' . $module->getVar('dirname'))) {
                                        mkdir($theme_surcharge . '/' . $module->getVar('dirname'), 0777);
                                        chmod($theme_surcharge . '/' . $module->getVar('dirname'), 0777);
                                        copy($indexFile, $theme_surcharge . '/' . $module->getVar('dirname') . '/index.html');
                                    }

                                    // create block folder
                                    if (!is_dir($theme_surcharge . '/' . $module->getVar('dirname') . '/blocks')) {
                                        if (!is_dir($theme_surcharge . '/' . $module->getVar('dirname') . '/blocks')) {
                                            mkdir($theme_surcharge . '/' . $module->getVar('dirname') . '/blocks', 0777);
                                        }
                                        chmod($theme_surcharge . '/' . $module->getVar('dirname') . '/blocks', 0777);
                                        copy($indexFile, $theme_surcharge . '/' . $module->getVar('dirname') . '/blocks' . '/index.html');
                                    }

                                    $class = 'odd';
                                    $text .= '<table cellspacing="1" class="outer"><tr><th colspan="3" align="center">' . _AM_SYSTEM_TEMPLATES_MODULES . ucfirst((string) $module->getVar('dirname')) . '</th></tr><tr><th align="center">' . _AM_SYSTEM_TEMPLATES_TYPES . '</th><th  align="center">' . _AM_SYSTEM_TEMPLATES_FILES . '</th><th>' . _AM_SYSTEM_TEMPLATES_STATUS . '</th></tr>';

                                    // create template
                                    $templates      = $tpltpl_handler->find($tplsetname, 'module', null, $moddir);
                                    $templatesCount = count($templates);
                                    for ($j = 0; $j < $templatesCount; ++$j) {
                                        $filename = basename((string) $templates[$j]->getVar('tpl_file'));
                                        if ($tplsetname == $tplset) {
                                            $physical_file = XOOPS_THEME_PATH . '/' . $selectTheme . '/modules/' . $moddir . '/' . $filename;

                                            $tplfile = $tpltpl_handler->get($templates[$j]->getVar('tpl_id'), true);

                                            if (is_object($tplfile)) {
                                                if (!file_exists($physical_file) || 1 == $forceGenerated) {
                                                    if ($writeTemplate($physical_file, (string) $tplfile->getVar('tpl_source', 'n'))) {
                                                        $text .= '<tr class="' . $class . '"><td align="center">' . _AM_SYSTEM_TEMPLATES_TEMPLATES . '</td><td>' . $physical_file . '</td><td align="center">';
                                                        if (file_exists($physical_file)) {
                                                            $text .= '<img width="16" src="' . system_AdminIcons('success.png') . '" /></td></tr>';
                                                        } else {
                                                            $text .= '<img width="16" src="' . system_AdminIcons('cancel.png') . '" /></td></tr>';
                                                        }
                                                        $verif_write = true;
                                                    }
                                                    $class = ($class === 'even') ? 'odd' : 'even';
                                                }
                                            }
                                        }
                                    }

                                    // create block template
                                    $btemplates      = $tpltpl_handler->find($tplsetname, 'block', null, $moddir);
                                    $btemplatesCount = count($btemplates);
                                    for ($k = 0; $k < $btemplatesCount; ++$k) {
                                        $filename = basename((string) $btemplates[$k]->getVar('tpl_file'));
                                        if ($tplsetname == $tplset) {
                                            $physical_file = XOOPS_THEME_PATH . '/' . $selectTheme . '/modules/' . $moddir . '/blocks/' . $filename;
                                            $btplfile      = $tpltpl_handler->get($btemplates[$k]->getVar('tpl_id'), true);

                                            if (is_object($btplfile)) {
                                                if (!file_exists($physical_file) || 1 == $forceGenerated) {
                                                    if ($writeTemplate($physical_file, (string) $btplfile->getVar('tpl_source', 'n'))) {
                                                        $text .= '<tr class="' . $class . '"><td align="center">' . _AM_SYSTEM_TEMPLATES_BLOCKS . '</td><td>' . $physical_file . '</td><td align="center">';
                                                        if (file_exists($physical_file)) {
                                                            $text .= '<img width="16" src="' . system_AdminIcons('success.png') . '" /></td></tr>';
                                                        } else {
                                                            $text .= '<img width="16" src="' . system_AdminIcons('cancel.png') . '" /></td></tr>';
                                                        }
                                                        $verif_write = true;
                                                    }
                                                    $class = ($class === 'even') ? 'odd' : 'even';
                                                }
                                            }
                                        }
                                    }
                                    $text .= '</table>';
                                }
                            }
                            unset($module);
                        }
                    }
                } else {
                    foreach (array_keys($tplsets_arr) as $i) {
                        $tplsetname = $tplsets_arr[$i]->getVar('tplset_name');
                        $tplstats   = $tpltpl_handler->getModuleTplCount($tplsetname);

                        if (count($tplstats) > 0) {
                            $moddir = $selectModules;
                            if (!preg_match('/^[a-zA-Z0-9_-]+$/', (string) $moddir)) {
                                continue;
                            }
                            $module = $module_handler->getByDirname($moddir);
                            if (is_object($module)) {
                                // create module folder
                                if (!is_dir($theme_surcharge . '/' . $module->getVar('dirname'))) {
                                    mkdir($theme_surcharge . '/' . $module->getVar('dirname'), 0777);
                                    chmod($theme_surcharge . '/' . $module->getVar('dirname'), 0777);
                                    copy($indexFile, $theme_surcharge . '/' . $module->getVar('dirname') . '/index.html');
                                }

                                // create block folder
                                if (!is_dir($theme_surcharge . '/' . $module->getVar('dirname') . '/blocks')) {
                                    if (!is_dir($theme_surcharge . '/' . $module->getVar('dirname') . '/blocks')) {
                                        mkdir($theme_surcharge . '/' . $module->getVar('dirname') . '/blocks', 0777);
                                    }
                                    chmod($theme_surcharge . '/' . $module->getVar('dirname') . '/blocks', 0777);
                                    copy($indexFile, $theme_surcharge . '/' . $module->getVar('dirname') . '/blocks' . '/index.html');
                                }

                                $class = 'odd';
                                $text .= '<table cellspacing="1" class="outer"><tr><th colspan="3" align="center">' . _AM_SYSTEM_TEMPLATES_MODULES . ucfirst((string) $module->getVar('dirname')) . '</th></tr><tr><th align="center">' . _AM_SYSTEM_TEMPLATES_TYPES . '</th><th  align="center">' . _AM_SYSTEM_TEMPLATES_FILES . '</th><th>' . _AM_SYSTEM_TEMPLATES_STATUS . '</th></tr>';
                                $select_templates_modules = Request::getArray('select_templates_modules', [], 'POST');
                                $tempCount                = count($select_templates_modules);
                                for ($l = 0; $l < $tempCount; ++$l) {
                                    // create template
                                    $templates      = $tpltpl_handler->find($tplsetname, 'module', null, $moddir);
                                    $templatesCount = count($templates);
                                    for ($j = 0; $j < $templatesCount; ++$j) {
                                        $filename = basename((string) $templates[$j]->getVar('tpl_file'));
                                        if ($tplsetname == $tplset) {
                                            $physical_file = XOOPS_THEME_PATH . '/' . $selectTheme . '/modules/' . $moddir . '/' . $filename;

                                            $tplfile = $tpltpl_handler->get($templates[$j]->getVar('tpl_id'), true);

                                            if (is_object($tplfile)) {
                                                if (!file_exists($physical_file) || 1 == $forceGenerated) {
                                                    if ($select_templates_modules[$l] == $filename) {
                                                        if ($writeTemplate($physical_file, (string) $tplfile->getVar('tpl_source', 'n'))) {
                                                            $text .= '<tr class="' . $class . '"><td align="center">' . _AM_SYSTEM_TEMPLATES_TEMPLATES . '</td><td>' . $physical_file . '</td><td align="center">';
                                                            if (file_exists($physical_file)) {
                                                                $text .= '<img width="16" src="' . system_AdminIcons('success.png') . '" /></td></tr>';
                                                            } else {
                                                                $text .= '<img width="16" src="' . system_AdminIcons('cancel.png') . '" /></td></tr>';
                                                            }
                                                            $verif_write = true;
                                                        }
                                                    }
                                                    $class = ($class === 'even') ? 'odd' : 'even';
                                                }
                                            }
                                        }
                                    }

                                    // create block template
                                    $btemplates      = $tpltpl_handler->find($tplsetname, 'block', null, $moddir);
                                    $btemplatesCount = count($btemplates);
                                    for ($k = 0; $k < $btemplatesCount; ++$k) {
                                        $filename = basename((string) $btemplates[$k]->getVar('tpl_file'));
                                        if ($tplsetname == $tplset) {
                                            $physical_file = XOOPS_THEME_PATH . '/' . $selectTheme . '/modules/' . $moddir . '/blocks/' . $filename;
                                            $btplfile      = $tpltpl_handler->get($btemplates[$k]->getVar('tpl_id'), true);

                                            if (is_object($btplfile)) {
                                                if (!file_exists($physical_file) || 1 == $forceGenerated) {
                                                    if ($select_templates_modules[$l] == $filename) {
                                                        if ($writeTemplate($physical_file, (string) $btplfile->getVar('tpl_source', 'n'))) {
                                                            $text .= '<tr class="' . $class . '"><td align="center">' . _AM_SYSTEM_TEMPLATES_BLOCKS . '</td><td>' . $physical_file . '</td><td align="center">';
                                                            if (file_exists($physical_file)) {
                                                                $text .= '<img width="16" src="' . system_AdminIcons('success.png') . '" /></td></tr>';
                                                            } else {
                                                                $text .= '<img width="16" src="' . system_AdminIcons('cancel.png') . '" /></td></tr>';
                                                            }
                                                            $verif_write = true;
                                                        }
                                                    }
                                                    $class = ($class === 'even') ? 'odd' : 'even';
                                                }
                                            }
                                        }
                                    }
                                }
                                $text .= '</table>';
                            }
                            unset($module);
                        }
                    }
                }
                $xoopsTpl->assign('infos', $text);
                $xoopsTpl->assign('verif', $verif_write);
            } else {
                redirect_header('admin.php?fct=tplsets', 2, _AM_SYSTEM_TEMPLATES_SAVE);
            }
        } else {
            // Generate one module
            $GLOBALS['xoopsTpl']->assign('index', true);

            $tplset = Request::getString('tplset', 'default');

            $form = new XoopsThemeForm(_AM_SYSTEM_TEMPLATES_SELECT_TEMPLATES, 'form', 'admin.php?fct=tplsets', 'post', true);

            $tpltpl_handler = xoops_getHandler('tplfile');
            $templates_arr  = $tpltpl_handler->find($tplset, '', null, $selectModules);

            $modules = new XoopsFormSelect(_AM_SYSTEM_TEMPLATES_SELECT_TEMPLATES, 'select_templates_modules', null, 10, true);
            foreach (array_keys($templates_arr) as $i) {
                $modules->addOption($templates_arr[$i]->getVar('tpl_file'));
            }
            $form->addElement($modules);

            $form->addElement(new XoopsFormHidden('active_templates', '1'));
            $form->addElement(new XoopsFormHidden('force_generated', (string)$forceGenerated));
            $form->addElement(new XoopsFormHidden('select_modules', $selectModules));
            $form->addElement(new XoopsFormHidden('active_modules', '1'));
            $form->addElement(new XoopsFormHidden('select_theme', $selectTheme));
            $form->addElement(new XoopsFormHidden('op', 'tpls_generate_surcharge'));
            $form->addElement(new XoopsFormButton('', 'submit', _SUBMIT, 'submit'));
            $xoopsTpl->assign('form', $form->render());
        }
        break;

    // save
    case 'tpls_save':
        if (!$GLOBALS['xoopsSecurity']->check()) {
            redirect_header('admin.php?fct=tplsets', 2, implode('<br>', $GLOBALS['xoopsSecurity']->getErrors()));
        }
        // getString() kept deliberately (getPath()'s filter truncates at
        // the first non-ASCII byte, breaking accented or CJK theme paths).
        // PathGuard file mode carries the rest of the contract (SECURITY.md
        // M-9): NUL refusal with ValueError backstop, false-realpath
        // refusal, boundary-aware themes/ containment, is_file() so a
        // directory named like "x.css" never reaches the file handlers,
        // and the case-insensitive template-extension allowlist - pinned
        // by the PathGuard truth-table test.
        $clean_path_file = Request::getString('path_file', '', 'POST');
        if (!empty($clean_path_file)) {
            require_once XOOPS_ROOT_PATH . '/class/PathGuard.php';
            $path_file = PathGuard::resolveFile(XOOPS_ROOT_PATH . '/themes', trim($clean_path_file), ['css', 'html', 'htm', 'tpl']);
            if (false === $path_file) {
                redirect_header('admin.php?fct=tplsets', 3, _AM_SYSTEM_TEMPLATES_ERROR);
                exit();
            }
            $path_file = str_replace('\\', '/', $path_file);
            // copy file - a failed backup must abort the save, or the
            // overwrite below destroys the only copy. Delegated to the
            // shared xoops_write_file_atomically() helper (loaded with
            // cp_functions.php by admin.php): tempnam() in the target
            // directory, short-write check, permissions carried with a
            // 0644 fallback, and an atomic rename() - which REPLACES a
            // planted symlink at .back instead of following it. A direct
            // copy() follows an existing link and PHP's emulated fopen('x')
            // follows a dangling one - both verified by execution - which
            // rules those routes out. The residual window (the helper
            // reopens its temp file by name) requires an attacker who can
            // already write inside themes/, and such an attacker can
            // replace the templates directly: that is the threat boundary
            // here, not a gap this call path can close. The helper's
            // diagnostics name only the file's basename; the scoped
            // handler keeps file_get_contents()' native full-path warning
            // out of the error handlers the same way (review catch,
            // mirrors file_safety.php).
            $copy_file = $path_file . '.back';
            $content   = false;
            set_error_handler(static function (): bool {
                return true;
            }, E_WARNING);
            try {
                $content = file_get_contents($path_file);
            } finally {
                restore_error_handler();
            }
            $copied = (false !== $content) && xoops_write_file_atomically($copy_file, $content);
            if (!$copied) {
                trigger_error('Template backup failed for ' . basename($path_file), E_USER_WARNING);
                redirect_header('admin.php?fct=tplsets', 2, _AM_SYSTEM_TEMPLATES_ERROR);
                exit;
            }
            // Save modif
            if (Request::hasVar('templates', 'POST')) {
                $open = fopen($path_file, 'w+');
                if ($open === false) {
                    redirect_header('admin.php?fct=tplsets', 2, _AM_SYSTEM_TEMPLATES_ERROR);
                }
                $temp = Request::getText('templates', '', 'POST');
                // === false, not falsy: clearing a template writes 0 bytes,
                // and fwrite() returns int 0 for that legitimate save - the
                // same 0-is-falsy trap the backup path already dodges
                // (review catch). redirect_header() exits internally, so
                // the failure branch never falls through.
                if (false === fwrite($open, xoops_utf8_encode($temp))) {
                    fclose($open);
                    redirect_header('admin.php?fct=tplsets', 2, _AM_SYSTEM_TEMPLATES_ERROR);
                }
                fclose($open);
            }
        }
        redirect_header('admin.php?fct=tplsets', 2, _AM_SYSTEM_TEMPLATES_SAVE);
        break;
}
// Call Footer
xoops_cp_footer();
