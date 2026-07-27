<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

/**
 * XOOPS global search
 *
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             core
 * @since               2.0.0
 * @author              Kazumi Ono (AKA onokazu)
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 * @todo                Modularize; Both search algorithms and interface will be redesigned
 */

use Xmf\Request;

include __DIR__ . '/mainfile.php';

xoops_loadLanguage('search');
/** @var XoopsConfigHandler $config_handler */
$config_handler    = xoops_getHandler('config');
$xoopsConfigSearch = $config_handler->getConfigsByCat(XOOPS_CONF_SEARCH);

if ($xoopsConfigSearch['enable_search'] != 1) {
    header('Location: ' . XOOPS_URL . '/index.php');
    exit();
}
$action = 'search';
$allowedActions = ['search', 'results', 'showall', 'showallbyuser'];
if (Request::hasVar('action', 'GET')) {
    $candidate = trim(strip_tags(Request::getString('action', '', 'GET')));
    if (in_array($candidate, $allowedActions, true)) {
        $action = $candidate;
    }
} elseif (Request::hasVar('action', 'POST')) {
    $candidate = trim(strip_tags(Request::getString('action', '', 'POST')));
    if (in_array($candidate, $allowedActions, true)) {
        $action = $candidate;
    }
}
$query = '';
if (Request::hasVar('query', 'GET')) {
    $query = trim(strip_tags(Request::getString('query', '', 'GET')));
} elseif (Request::hasVar('query', 'POST')) {
    $query = trim(strip_tags(Request::getString('query', '', 'POST')));
}
$andor = 'AND';
if (Request::hasVar('andor', 'GET')) {
    $andor = trim(strip_tags(Request::getString('andor', '', 'GET')));
} elseif (Request::hasVar('andor', 'POST')) {
    $andor = trim(strip_tags(Request::getString('andor', '', 'POST')));
}
$mid = $uid = $start = 0;
if (Request::hasVar('mid', 'GET')) {
    $mid = Request::getInt('mid', 0, 'GET');
} elseif (Request::hasVar('mid', 'POST')) {
    $mid = Request::getInt('mid', 0, 'POST');
}
if (Request::hasVar('uid', 'GET')) {
    $uid = Request::getInt('uid', 0, 'GET');
} elseif (Request::hasVar('uid', 'POST')) {
    $uid = Request::getInt('uid', 0, 'POST');
}
if (Request::hasVar('start', 'GET')) {
    $start = Request::getInt('start', 0, 'GET');
} elseif (Request::hasVar('start', 'POST')) {
    $start = Request::getInt('start', 0, 'POST');
}

$queries = [];

if ($action === 'results') {
    if ($query == '') {
        redirect_header('search.php', 1, _SR_PLZENTER);
    }
} elseif ($action === 'showall') {
    if ($query == '' || empty($mid)) {
        redirect_header('search.php', 1, _SR_PLZENTER);
    }
} elseif ($action === 'showallbyuser') {
    if (empty($mid) || empty($uid)) {
        redirect_header('search.php', 1, _SR_PLZENTER);
    }
}
$GLOBALS['xoopsOption']['template_main'] = 'system_search.tpl';
$groups            = is_object($xoopsUser) ? $xoopsUser->getGroups() : XOOPS_GROUP_ANONYMOUS;
/** @var  XoopsGroupPermHandler $gperm_handler */
$gperm_handler     = xoops_getHandler('groupperm');
$available_modules = $gperm_handler->getItemIds('module_read', $groups);
if ($action === 'search') {
    include $GLOBALS['xoops']->path('header.php');
    include $GLOBALS['xoops']->path('include/searchform.php');
    $xoopsTpl->assign('form', $search_form->render());
    include $GLOBALS['xoops']->path('footer.php');
    exit();
}
if ($andor !== 'OR' && $andor !== 'exact' && $andor !== 'AND') {
    $andor = 'AND';
}

$myts = \MyTextSanitizer::getInstance();
if ($action !== 'showallbyuser') {
    if ($andor !== 'exact') {
        $ignored_queries = []; // holds kewords that are shorter than allowed minmum length
        $temp_queries    = preg_split('/[\s,]+/', $query);
        foreach ($temp_queries as $q) {
            $q = trim($q);
            if (strlen($q) >= $xoopsConfigSearch['keyword_min']) {
                $queries[] = $xoopsDB->escape($q);
            } else {
                $ignored_queries[] = $xoopsDB->escape($q);
            }
        }
        if (count($queries) == 0) {
            redirect_header('search.php', 2, sprintf(_SR_KEYTOOSHORT, $xoopsConfigSearch['keyword_min']));
        }
    } else {
        $query = trim($query);
        if (strlen($query) < $xoopsConfigSearch['keyword_min']) {
            redirect_header('search.php', 2, sprintf(_SR_KEYTOOSHORT, $xoopsConfigSearch['keyword_min']));
        }
        $queries = [$xoopsDB->escape($query)];
    }
}
switch ($action) {
    case 'results':
        /** @var XoopsModuleHandler $module_handler */
        $module_handler = xoops_getHandler('module');
        $criteria       = new CriteriaCompo(new Criteria('hassearch', 1));
        $criteria->add(new Criteria('isactive', 1));
        $criteria->add(new Criteria('mid', '(' . implode(',', $available_modules) . ')', 'IN'));
        $modules = $module_handler->getObjects($criteria, true);
        $mids    = Request::hasVar('mids', 'POST') ? Request::getArray('mids', [], 'POST') : Request::getArray('mids', [], 'GET');
        if (empty($mids) || !is_array($mids)) {
            unset($mids);
            $mids = array_keys($modules);
        }
        $xoopsOption['xoops_pagetitle'] = _SR_SEARCHRESULTS . ': ' . implode(' ', $queries);
        include $GLOBALS['xoops']->path('header.php');
        $xoopsTpl->assign('results', true);
        $nomatch = true;
        $keywords = '';
        $error_length = '';
        $error_keywords = '';
        if ($andor !== 'exact') {
            foreach ($queries as $q) {
                $keywords .= htmlspecialchars(stripslashes($q), ENT_QUOTES | ENT_HTML5) . ' ';
            }
            if (!empty($ignored_queries)) {
                $error_length = sprintf(_SR_IGNOREDWORDS, $xoopsConfigSearch['keyword_min']);
                foreach ($ignored_queries as $q) {
                    $error_keywords .= htmlspecialchars(stripslashes($q), ENT_QUOTES | ENT_HTML5) . ' ';
                }
            }
        } else {
            $keywords .= '"' . htmlspecialchars(stripslashes($queries[0]), ENT_QUOTES | ENT_HTML5) . '"';
        }
        $xoopsTpl->assign('keywords', $keywords);
        $xoopsTpl->assign('error_length', $error_length);
        $xoopsTpl->assign('error_keywords', $error_keywords);
        $results_arr = [];
        foreach ($mids as $mid) {
            $mid = (int) $mid;
            if (in_array($mid, $available_modules)) {
                // $mids can come straight from the query string, and $modules only holds
                // modules that are active AND searchable. A readable-but-not-searchable
                // mid (e.g. mids[]=1) would otherwise index a missing key and yield null.
                if (!isset($modules[$mid]) || !is_object($modules[$mid])) {
                    continue;
                }
                $module = $modules[$mid];
                // Resolve the label BEFORE the try: the catch must never dereference
                // $module, or a failure there throws a second, uncaught error.
                $moduleDirname = $module->getVar('dirname', 'n');
                // No blind cast: getVar() can return an array, and converting that
                // raises a notice here, BEFORE the try -- which an ErrorException
                // handler would turn into an escape from the very guard below.
                $moduleLabel = is_scalar($moduleDirname) ? (string) $moduleDirname : 'mid:' . $mid;
                try {
                    $results = $module->search($queries, $andor, 5, 0);
                    // XoopsModule::search() returns mixed -- it hands off to whatever the
                    // module's search callback returns. A truthy non-array (true, an error
                    // string, an object) would reach count() below, which is a TypeError on
                    // PHP 8 raised OUTSIDE this try, defeating the whole guard. Normalise
                    // here, where a misbehaving module is still contained.
                    if (!is_array($results)) {
                        $results = [];
                    }
                    // Per ELEMENT too. Normalising only the outer array still let a
                    // string or object element reach $results[$i]['link'] further
                    // down -- outside this try -- which is a TypeError on PHP 8 and
                    // defeated the guard just as completely.
                    $results = array_values(array_filter($results, 'is_array'));
                } catch (\Throwable $e) {
                    // A broken module must not take down the whole search page.
                    $GLOBALS['xoopsLogger']->handleError(
                        E_USER_WARNING,
                        sprintf('Search failed in module "%s": %s', $moduleLabel, $e->getMessage()),
                        basename($e->getFile()),
                        $e->getLine()
                    );
                    unset($module);
                    continue;
                }
                if (empty($results)) {
                    $count = 0;
                } else {
                    $count = count($results);
                }
                if (is_array($results) && $count > 0) {
                    $nomatch = false;
                    $module_name = $module->getVar('name');
                    for ($i = 0; $i < $count; ++$i) {
                        if (isset($results[$i]['image']) && $results[$i]['image'] != '') {
                            $results_arr[$i]['image_link'] = 'modules/' . $module->getVar('dirname') . '/' . $results[$i]['image'];
                        } else {
                            $results_arr[$i]['image_link'] = 'images/icons/posticon2.gif';
                        }
                        $results_arr[$i]['image_title'] = $module->getVar('name');
                        if (!preg_match("/^http[s]*:\/\//i", $results[$i]['link'])) {
                            $results[$i]['link'] = 'modules/' . $module->getVar('dirname') . '/' . $results[$i]['link'];
                        }
                        $results_arr[$i]['link'] = $results[$i]['link'];
                        $results_arr[$i]['link_title'] = $myts->htmlSpecialChars($results[$i]['title']);

                        $results[$i]['uid'] = @(int) $results[$i]['uid'];
                        if (!empty($results[$i]['uid'])) {
                            $uname = XoopsUser::getUnameFromId($results[$i]['uid']);
                            $results_arr[$i]['uname'] = $uname;
                            $results_arr[$i]['uname_link'] = XOOPS_URL . '/userinfo.php?uid=' . $results[$i]['uid'];
                        }
                        if (!empty($results[$i]['time'])) {
                            $results_arr[$i]['time'] = formatTimestamp((int) $results[$i]['time']);
                        }
                    }
                    if ($count >= 5) {
                        $search_url = XOOPS_URL . '/search.php?query=' . urlencode(stripslashes(implode(' ', $queries)));
                        $search_url .= "&mid={$mid}&action=showall&andor={$andor}";
                        $search_arr['module_show_all'] = htmlspecialchars($search_url, ENT_QUOTES | ENT_HTML5);
                    }
                    $search_arr['module_name'] = $module_name;
                    $search_arr['module_data'] = $results_arr;
                    $xoopsTpl->appendByRef('search', $search_arr);
                    unset($results_arr, $search_arr);
                }
            }
            unset($results, $module, $module_name);
        }

        if ($nomatch) {
            $xoopsTpl->assign('nomatch', _SR_NOMATCH);
        }
        include $GLOBALS['xoops']->path('include/searchform.php');
        $xoopsTpl->assign('form', $search_form->render());
        break;

    case 'showall':
    case 'showallbyuser':
        include $GLOBALS['xoops']->path('header.php');
        $xoopsTpl->assign('showallbyuser', true);
        /** @var XoopsModuleHandler $module_handler */
        $module_handler = xoops_getHandler('module');
        /** @var XoopsModule $module */
        $module      = $module_handler->get($mid);
        // get() returns false for an unknown mid, and the mid arrives straight from the
        // query string, so /search.php?action=showall&mid=999999 is reachable by anyone.
        //
        // The checks must match the 'results' branch above, which selects modules with
        // hassearch = 1 AND isactive = 1 AND module_read permission. Testing only the
        // permission here would let this route run search() for a module the administrator
        // has deactivated, or one that never declared search support at all.
        if (!is_object($module)
            || !in_array((int) $mid, $available_modules, true)
            || 1 !== (int) $module->getVar('isactive')
            || 1 !== (int) $module->getVar('hassearch')) {
            redirect_header(XOOPS_URL . '/search.php', 2, _SR_NOMATCH);
        }
        // Resolve the label BEFORE the try: the catch must never dereference $module,
        // or a failure there throws a second, uncaught error.
        $moduleDirname = $module->getVar('dirname', 'n');
                // No blind cast: getVar() can return an array, and converting that
                // raises a notice here, BEFORE the try -- which an ErrorException
                // handler would turn into an escape from the very guard below.
                $moduleLabel = is_scalar($moduleDirname) ? (string) $moduleDirname : 'mid:' . $mid;
        try {
            $next_results = [];
            $results      = $module->search($queries, $andor, 20, $start, $uid);
            // search() returns mixed. A truthy non-array would reach count() further down,
            // outside this try, and a TypeError there would bypass the guard entirely.
            if (!is_array($results)) {
                $results = [];
            }
            // Per element too -- see the results route above.
            $results = array_values(array_filter($results, 'is_array'));
            // The next-page probe exists only to decide whether to render a "next" link,
            // which is meaningless when this page is already empty. Running it
            // unconditionally doubled the module and database work on every no-match
            // search, and gave a failing module a second chance to throw.
            if ([] !== $results) {
                $next_results = $module->search($queries, $andor, 1, $start + 20, $uid);
                if (!is_array($next_results)) {
                    $next_results = [];
                }
            }
        } catch (\Throwable $e) {
            // A broken module must not take down the whole search page.
            $GLOBALS['xoopsLogger']->handleError(
                E_USER_WARNING,
                sprintf('Search failed in module "%s": %s', $moduleLabel, $e->getMessage()),
                basename($e->getFile()),
                $e->getLine()
            );
            $results      = [];
            $next_results = [];
        }
        // Assigned unconditionally. The showall block in system_search.tpl renders even when
        // there are no results, so leaving these unset produced, on every empty showall page:
        //   Undefined array key "showing" / "module_name"
        //   Attempt to read property "value" on null   (Smarty's compiled tpl_vars access)
        // The results branch below overwrites 'showing' with the real range.
        $xoopsTpl->assign('module_name', $module->getVar('name'));
        $xoopsTpl->assign('showing', '');

        $results ? $count = count($results) : $count = 0;
        if (is_array($results) && $count > 0) {
            $next_count   = count($next_results);
            $has_next     = false;
            if (is_array($next_results) && $next_count == 1) {
                $has_next = true;
            }
            if ($action === 'showall') {
                $xoopsTpl->assign('showall', true);
                $keywords = '';
                if ($andor !== 'exact') {
                    foreach ($queries as $q) {
                        $keywords .= htmlspecialchars(stripslashes($q), ENT_QUOTES | ENT_HTML5);
                    }
                } else {
                    $keywords .= htmlspecialchars(stripslashes($queries[0]), ENT_QUOTES | ENT_HTML5);
                }
                $xoopsTpl->assign('keywords', $keywords);
            }
            $xoopsTpl->assign('showing', sprintf(_SR_SHOWING, $start + 1, $start + $count));
            $results_arr = [];
            for ($i = 0; $i < $count; ++$i) {
                if (isset($results[$i]['image']) && $results[$i]['image'] != '') {
                    $results_arr['image_link'] = 'modules/' . $module->getVar('dirname') . '/' . $results[$i]['image'];
                } else {
                    $results_arr['image_link'] = 'images/icons/posticon2.gif';
                }
                $results_arr['image_title'] = $module->getVar('name');
                if (!preg_match("/^http[s]*:\/\//i", $results[$i]['link'])) {
                    $results[$i]['link'] = 'modules/' . $module->getVar('dirname') . '/' . $results[$i]['link'];
                }
                $results_arr['link'] = $results[$i]['link'];
                $results_arr['link_title'] = $myts->htmlSpecialChars($results[$i]['title']);
                $results['uid'] = @(int) $results[$i]['uid'];
                if (!empty($results[$i]['uid'])) {
                    $uname = XoopsUser::getUnameFromId($results[$i]['uid']);
                    $results_arr['uname'] = $uname;
                    $results_arr['uname_link'] = XOOPS_URL . '/userinfo.php?uid=' . $results[$i]['uid'];
                }
                if (!empty($results[$i]['time'])) {
                    $results_arr['time'] = formatTimestamp((int) $results[$i]['time']);
                }
                $xoopsTpl->appendByRef('results_arr', $results_arr);
                unset($results_arr);
            }
            $search_url = XOOPS_URL . '/search.php?query=' . urlencode(stripslashes(implode(' ', $queries)));
            $search_url .= "&mid={$mid}&action={$action}&andor={$andor}";
            if ($action === 'showallbyuser') {
                $search_url .= "&uid={$uid}";
            }
            if ($start > 0) {
                $prev = $start - 20;
                $search_url_prev = $search_url . "&start={$prev}";
                $xoopsTpl->assign('previous', htmlspecialchars($search_url_prev, ENT_QUOTES | ENT_HTML5));
            }
            if (false !== $has_next) {
                $next            = $start + 20;
                $search_url_next = $search_url . "&start={$next}";
                $xoopsTpl->assign('next', htmlspecialchars($search_url_next, ENT_QUOTES | ENT_HTML5));
            }
        } else {
            $xoopsTpl->assign('nomatch', true);
        }
        include $GLOBALS['xoops']->path('include/searchform.php');
        $search_form->display();
        break;
}
include $GLOBALS['xoops']->path('footer.php');
