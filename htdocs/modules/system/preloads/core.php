<?php
/**
 * System Preloads
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              Cointin Maxime (AKA Kraven30)
 * @author              Andricq Nicolas (AKA MusS)
 */

//if (!defined('XOOPS_ROOT_PATH')) {
//    throw new \RuntimeException('XOOPS root path not defined');
//}

/**
 * Class SystemCorePreload
 */
class SystemCorePreload extends XoopsPreloadItem
{
    /**
     * @param $args
     */
    public static function eventCoreIncludeFunctionsRedirectheader($args)
    {
        global $xoopsConfig;
        $url = $args[0];
        if (preg_match("/[\\0-\\31]|about:|script:/i", (string) $url)) {
            if (!preg_match('/^\b(java)?script:([\s]*)history\.go\(-\d*\)([\s]*[;]*[\s]*)$/si', (string) $url)) {
                $url = XOOPS_URL;
            }
        }
        if (!headers_sent() && isset($xoopsConfig['redirect_message_ajax']) && $xoopsConfig['redirect_message_ajax']) {
            $_SESSION['redirect_message'] = $args[2];
            header('Location: ' . preg_replace('/[&]amp;/i', '&', (string) $url));
            exit();
        }
    }

    /**
     * Record the current visitor in the online table.
     *
     * This used to live inside b_system_online_show() in the system module's blocks.
     * That made tracking a side effect of RENDERING a block, so it only ran when the
     * "Who is Online" block happened to be displayed — which meant the block's group
     * permissions silently decided who got counted, and any page without the block
     * (including whole modules with page caching on) recorded nobody at all. A block
     * permission is meant to control who can SEE a block, not who is measured.
     *
     * Fires on core.include.common.end, which is after authentication (so $xoopsUser
     * is resolved) and after the module is resolved at include/common.php:343 (so the
     * "browsing <module>" figure still works), and before any page cache short-circuit
     * in header.php — so cached pages are counted too.
     *
     * Turn it off with the "Track who is online?" preference under General Settings.
     *
     * Never throws. Every failure path — an unloadable handler, a failed write, a corrupt
     * table — is caught and downgraded to E_USER_WARNING, because this runs on EVERY request
     * and statistics must never be able to take down a page. Hence no @throws tag: propagating
     * an exception from here would be a defect, not part of the contract.
     *
     * @param  mixed $args event arguments (unused)
     * @return void
     */
    public static function eventCoreIncludeCommonEnd($args)
    {
        // Default to enabled so an install that predates the preference keeps working.
        if (isset($GLOBALS['xoopsConfig']['enable_online_tracking'])
            && !$GLOBALS['xoopsConfig']['enable_online_tracking']) {
            return;
        }

        // Only track real HTTP visitors. A CLI or cron script that includes mainfile.php
        // would otherwise be recorded as a guest at 0.0.0.0 (IPAddress::fromRequest()
        // falls back to that when REMOTE_ADDR is absent) and, if it runs often enough,
        // would sit in "Who is Online" permanently.
        if ('cli' === PHP_SAPI || 'phpdbg' === PHP_SAPI || empty($_SERVER['REMOTE_ADDR'])) {
            return;
        }

        // EVERYTHING below is inside the try. Statistics must never take down a page, and
        // that includes acquiring the handler (xoops_getHandler raises E_USER_ERROR when
        // the class cannot be loaded) and the garbage collection sweep — not just write().
        try {
            // The second argument is $optional. Without it a missing or broken
            // XoopsOnlineHandler raises E_USER_ERROR, and XoopsLogger's handler calls
            // exit() on that — exit() is not a Throwable, so the catch below would never
            // run and the whole page would simply stop. With $optional = true the same
            // condition is an E_USER_WARNING and the call returns false, which the
            // is_object() check then absorbs.
            /** @var XoopsOnlineHandler|false $onlineHandler */
            $onlineHandler = xoops_getHandler('online', true);
            if (!is_object($onlineHandler)) {
                return;
            }

            // Probabilistic garbage collection: roughly one request in ten clears out
            // anything older than five minutes.
            if (mt_rand(1, 100) < 11) {
                $onlineHandler->gc(300);
            }

            $xoopsUser = $GLOBALS['xoopsUser'] ?? null;
            if ($xoopsUser instanceof XoopsUser) {
                $uid   = $xoopsUser->getVar('uid');
                $uname = $xoopsUser->getVar('uname');
            } else {
                $uid   = 0;
                $uname = '';
            }

            $requestIp = \Xmf\IPAddress::fromRequest()->asReadable();
            $requestIp = (false === $requestIp) ? '0.0.0.0' : $requestIp;

            $xoopsModule = $GLOBALS['xoopsModule'] ?? null;
            $mid         = is_object($xoopsModule) ? $xoopsModule->getVar('mid') : 0;

            // write() returns false rather than throwing on a failed INSERT/UPDATE, so a
            // read-only account or a corrupt table would otherwise stop tracking silently.
            if (false === $onlineHandler->write($uid, $uname, time(), $mid, $requestIp)) {
                trigger_error('Online tracking: write to the online table failed', E_USER_WARNING);
            }
        } catch (\Throwable $e) {
            // basename() only: the logger output is visible wherever debug rendering is on,
            // and absolute server paths do not belong there.
            trigger_error(
                sprintf(
                    'Online tracking failed: %s (%s:%d)',
                    $e->getMessage(),
                    basename($e->getFile()),
                    $e->getLine()
                ),
                E_USER_WARNING
            );
        }
    }

    /**
     * @param $args
     */
    public static function eventCoreHeaderCheckcache($args)
    {
        if (!empty($_SESSION['redirect_message'])) {
            $GLOBALS['xoTheme']->contentCacheLifetime = 0;
            unset($_SESSION['redirect_message']);
        }
    }

    /**
     * @param $args
     */
    public static function eventCoreHeaderAddmeta($args)
    {
        $GLOBALS['xoTheme']->addStylesheet('media/font-awesome6/css/fontawesome.min.css');
        $GLOBALS['xoTheme']->addStylesheet('media/font-awesome6/css/solid.min.css');
        $GLOBALS['xoTheme']->addStylesheet('media/font-awesome6/css/brands.min.css');
        $GLOBALS['xoTheme']->addStylesheet('media/font-awesome6/css/v4-shims.min.css');
        if (defined('XOOPS_STARTPAGE_REDIRECTED') || (isset($GLOBALS['xoopsOption']['template_main']) && $GLOBALS['xoopsOption']['template_main'] === 'db:system_homepage.tpl')) {
            if (is_object($GLOBALS['xoopsTpl'])) {
                $GLOBALS['xoopsTpl']->assign('homepage', true);
            }
        }

        if (!empty($_SESSION['redirect_message'])) {
            /**
             * Don't load jquery if already done by the theme
             */
            $GLOBALS['xoTheme']->addScript(
                '',
                ['type' => 'text/javascript'],
                "
                if (typeof jQuery == 'undefined') {
                    var tag = '<scr' + 'ipt type=\'text/javascript\' src=\'" . XOOPS_URL . "/browse.php?Frameworks/jquery/jquery.js\'></scr' + 'ipt>';            	    
                    document.write(tag);            	    
	            };",
            );
            $GLOBALS['xoTheme']->addScript('browse.php?Frameworks/jquery/plugins/jquery.jgrowl.js');
            $GLOBALS['xoTheme']->addScript('', ['type' => 'text/javascript'], '
            (function($){
                $(document).ready(function(){
                $.jGrowl("' . $_SESSION['redirect_message'] . '", {  life:3000 , position: "center", speed: "slow" });
            });
            })(jQuery);
            ');
        }
    }

    /**
     * @param $args
     */
    public static function eventSystemClassGuiHeader($args)
    {
        $GLOBALS['xoTheme']->addStylesheet('media/font-awesome6/css/fontawesome.min.css');
        $GLOBALS['xoTheme']->addStylesheet('media/font-awesome6/css/solid.min.css');
        $GLOBALS['xoTheme']->addStylesheet('media/font-awesome6/css/brands.min.css');
        $GLOBALS['xoTheme']->addStylesheet('media/font-awesome6/css/v4-shims.min.css');
        if (!empty($_SESSION['redirect_message'])) {
            //$GLOBALS['xoTheme']->addStylesheet('xoops.css');
            $GLOBALS['xoTheme']->addScript('browse.php?Frameworks/jquery/jquery.js');
            $GLOBALS['xoTheme']->addScript('browse.php?Frameworks/jquery/plugins/jquery.jgrowl.js');
            $GLOBALS['xoTheme']->addScript('', ['type' => 'text/javascript'], '
            (function($){
            $(document).ready(function(){
                $.jGrowl("' . $_SESSION['redirect_message'] . '", {  life:3000 , position: "center", speed: "slow" });
            });
            })(jQuery);
            ');
            unset($_SESSION['redirect_message']);
        }
    }
}
