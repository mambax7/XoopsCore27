<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * XOOPS core Smarty functions and modifiers.
 *
 * Wraps the procedural plugins from htdocs/class/smarty4_plugins/ as methods
 * so they can be registered through the extension registry.
 *
 * No constructor — XOOPS globals are accessed lazily inside each method.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class XoopsCoreExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            'xo_get_config'        => $this->xoGetConfig(...),
            'xo_get_current_user'  => $this->xoGetCurrentUser(...),
            'xo_get_module_info'   => $this->xoGetModuleInfo(...),
            'xo_get_notifications' => $this->xoGetNotifications(...),
            'xo_module_url'        => $this->xoModuleUrl(...),
            'xo_render_block'      => $this->xoRenderBlock(...),
            'xo_render_menu'       => $this->xoRenderMenu(...),
            'xo_avatar'            => $this->xoAvatar(...),
            'xo_debug'             => $this->xoDebug(...),
        ];
    }

    public function getModifiers(): array
    {
        return [
            'translate' => $this->translate(...),
        ];
    }

    // ---------------------------------------------------------------
    //  Functions
    // ---------------------------------------------------------------

    /**
     * Retrieves a XOOPS configuration value by name.
     *
     * Usage: <{xo_get_config name="sitename" assign="val"}>
     */
    public function xoGetConfig(array $params, object $template): string
    {
        global $xoopsConfig;

        $name = $params['name'] ?? '';
        $result = $xoopsConfig[$name] ?? null;

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return (string) ($result ?? '');
    }

    /**
     * Returns the current logged-in XOOPS user as an associative array.
     *
     * Usage: <{xo_get_current_user assign="user"}>
     */
    public function xoGetCurrentUser(array $params, object $template): string
    {
        global $xoopsUser;

        $result = null;
        if ($xoopsUser instanceof \XoopsUser) {
            $result = [
                'uid'      => $xoopsUser->getVar('uid'),
                'uname'    => $xoopsUser->getVar('uname'),
                'name'     => $xoopsUser->getVar('name'),
                'email'    => $xoopsUser->getVar('email'),
                'groups'   => $xoopsUser->getGroups(),
                'is_admin' => $xoopsUser->isAdmin(),
            ];
        }

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return '';
    }

    /**
     * Retrieves module information by dirname.
     *
     * Usage: <{xo_get_module_info dirname="news" assign="mod"}>
     */
    public function xoGetModuleInfo(array $params, object $template): string
    {
        $dirname = $params['dirname'] ?? '';
        $result = null;

        if ($dirname !== '') {
            $module = \XoopsModule::getByDirname($dirname);
            if ($module instanceof \XoopsModule) {
                $result = [
                    'mid'         => $module->getVar('mid'),
                    'name'        => $module->getVar('name'),
                    'version'     => $module->getVar('version'),
                    'description' => $module->getVar('description'),
                    'dirname'     => $module->getVar('dirname'),
                    'isactive'    => (bool) $module->getVar('isactive'),
                ];
            }
        }

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return '';
    }

    /**
     * Retrieves the current user's notifications.
     *
     * Usage: <{xo_get_notifications assign="notifications"}>
     */
    public function xoGetNotifications(array $params, object $template): string
    {
        global $xoopsUser;

        $result = [];

        if ($xoopsUser instanceof \XoopsUser) {
            $notificationHandler = \xoops_getHandler('notification');
            $notifications = $notificationHandler->getByUser($xoopsUser->getVar('uid'));

            foreach ($notifications as $notification) {
                $result[] = [
                    'not_id'   => $notification->getVar('not_id'),
                    'modid'    => $notification->getVar('not_modid'),
                    'category' => $notification->getVar('not_category'),
                    'itemid'   => $notification->getVar('not_itemid'),
                    'event'    => $notification->getVar('not_event'),
                ];
            }
        }

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return '';
    }

    /**
     * Generates a module-relative URL using XOOPS_URL.
     *
     * Usage: <{xo_module_url module="news" path="article.php" params=$queryParams}>
     */
    public function xoModuleUrl(array $params, object $template): string
    {
        $module = $params['module'] ?? '';
        $path = \ltrim($params['path'] ?? '', '/');
        $queryParams = $params['params'] ?? [];

        $baseUrl = \defined('XOOPS_URL') ? \rtrim(XOOPS_URL, '/') : '';
        $url = $baseUrl . '/modules/' . $module;

        if ($path !== '') {
            $url .= '/' . $path;
        }

        if (!empty($queryParams) && \is_array($queryParams)) {
            $url .= '?' . \http_build_query($queryParams);
        }

        if (!empty($params['assign'])) {
            // assign stores the raw URL; callers escape when interpolating into HTML
            $template->assign($params['assign'], $url);
            return '';
        }

        return \htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Renders a XOOPS block by its options array.
     *
     * Usage: <{xo_render_block options=$blockOptions}>
     */
    public function xoRenderBlock(array $params, object $template): string
    {
        $options = $params['options'] ?? [];
        $block = $options['block'] ?? null;
        $result = '';

        if (\is_object($block) && \method_exists($block, 'getContent')) {
            $result = (string) $block->getContent();
        }

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return $result;
    }

    /**
     * Renders a module's admin menu as an HTML list.
     *
     * Usage: <{xo_render_menu module="news"}>
     */
    public function xoRenderMenu(array $params, object $template): string
    {
        $moduleDirname = $params['module'] ?? '';
        $result = '';

        if ($moduleDirname !== '') {
            $moduleHandler = \xoops_getHandler('module');
            $module = $moduleHandler->getByDirname($moduleDirname);
            if ($module) {
                $menu = $module->getAdminMenu();
                $result = '<ul class="nav">';
                foreach ($menu as $item) {
                    $link = htmlspecialchars($item['link'] ?? '', ENT_QUOTES, 'UTF-8');
                    $title = htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8');
                    $result .= '<li class="nav-item"><a class="nav-link" href="' . $link . '">' . $title . '</a></li>';
                }
                $result .= '</ul>';
            }
        }

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $result);
            return '';
        }

        return $result;
    }

    /**
     * Renders a user avatar image with Gravatar fallback.
     *
     * Usage: <{xo_avatar uid=$userId size=64}>
     *        <{xo_avatar email=$email size=48 class="rounded-circle"}>
     */
    public function xoAvatar(array $params, object $template): string
    {
        $uid = (int) ($params['uid'] ?? 0);
        $email = $params['email'] ?? '';
        $size = (int) ($params['size'] ?? 64);
        $class = $params['class'] ?? 'avatar';

        $avatarUrl = '';

        // Try XOOPS user avatar first
        if ($uid > 0) {
            $memberHandler = \xoops_getHandler('member');
            $user = $memberHandler->getUser($uid);
            if ($user instanceof \XoopsUser) {
                $avatar = $user->getVar('user_avatar');
                if ($avatar && $avatar !== 'blank.gif') {
                    $avatarUrl = (\defined('XOOPS_UPLOAD_URL') ? XOOPS_UPLOAD_URL : 'uploads') . '/' . $avatar;
                }
                if ($email === '') {
                    $email = $user->getVar('email', 'n');
                }
            }
        }

        // Gravatar fallback
        if ($avatarUrl === '' && $email !== '') {
            $hash = \md5(\strtolower(\trim($email)));
            $avatarUrl = 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size . '&d=mp';
        }

        if ($avatarUrl === '') {
            return '';
        }

        $safeUrl = \htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8');
        $safeClass = \htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $html = '<img src="' . $safeUrl . '" width="' . $size . '" height="' . $size . '" class="' . $safeClass . '" alt="Avatar" loading="lazy">';

        if (!empty($params['assign'])) {
            $template->assign($params['assign'], $html);
            return '';
        }

        return $html;
    }

    /**
     * Dumps a variable for debugging. Only outputs when XOOPS debug mode is active.
     *
     * Usage: <{xo_debug var=$someVariable}>
     *        <{xo_debug var=$someVariable label="My Var"}>
     */
    public function xoDebug(array $params, object $template): string
    {
        // Only output in debug mode
        global $xoopsConfig;
        $debugMode = (int) ($xoopsConfig['debug_mode'] ?? 0);
        if ($debugMode === 0) {
            return '';
        }

        $var = $params['var'] ?? null;
        $label = $params['label'] ?? 'debug';

        $dump = \htmlspecialchars(\print_r($var, true), ENT_QUOTES, 'UTF-8');
        $safeLabel = \htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return '<details class="xo-debug" style="background:#f8f9fa;border:1px solid #dee2e6;padding:0.5em;margin:0.5em 0;font-family:monospace;font-size:0.85em;">'
            . '<summary><strong>' . $safeLabel . '</strong></summary>'
            . '<pre style="margin:0.5em 0 0;white-space:pre-wrap;">' . $dump . '</pre>'
            . '</details>';
    }

    // ---------------------------------------------------------------
    //  Modifiers
    // ---------------------------------------------------------------

    /**
     * Looks up a XOOPS language constant. Returns the constant value if defined,
     * or the original string as a fallback.
     *
     * Usage: <{$string|translate}>
     *        <{"_MI_NEWS_TITLE"|translate}>
     */
    public function translate(string $string): string
    {
        if (\defined($string)) {
            return \constant($string);
        }

        return $string;
    }
}
