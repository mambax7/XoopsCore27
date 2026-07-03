<?php
/**
 * Register xoops/smartyextensions on every XoopsTpl (Smarty 4 today, Smarty 5 later).
 *
 * Hooks the core.class.template.new event fired at the end of XoopsTpl::__construct(),
 * so no edit to class/template.php is needed. ExtensionRegistry::registerAll() auto-detects
 * the Smarty version (Smarty 4 -> registerPlugin, Smarty 5 -> addExtension via Smarty5Adapter).
 *
 * Collision policy: register the full catalogue EXCEPT RayDebugExtension, which
 * duplicates core's class/smarty3_plugins/{function,modifier}.ray*.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */

use Xoops\SmartyExtensions\ExtensionRegistry;
use Xoops\SmartyExtensions\Extension\AssetExtension;
use Xoops\SmartyExtensions\Extension\DataExtension;
use Xoops\SmartyExtensions\Extension\FormatExtension;
use Xoops\SmartyExtensions\Extension\FormExtension;
use Xoops\SmartyExtensions\Extension\NavigationExtension;
use Xoops\SmartyExtensions\Extension\SecurityExtension;
use Xoops\SmartyExtensions\Extension\TextExtension;
use Xoops\SmartyExtensions\Extension\XoopsCoreExtension;

/**
 * Class SystemSmartyextensionsPreload
 *
 * @category  Preload
 * @package   system
 * @author    XOOPS Project (https://xoops.org)
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class SystemSmartyextensionsPreload extends XoopsPreloadItem
{
    /** @var ExtensionRegistry|null built once per request (Asset queue is shared across templates) */
    private static ?ExtensionRegistry $registry = null;

    /**
     * @param array $args $args[0] is the new XoopsTpl (Smarty) instance
     * @return void
     */
    public static function eventCoreClassTemplateNew($args)
    {
        $tpl = $args[0] ?? null;
        if (!is_object($tpl) || !class_exists(ExtensionRegistry::class)) {
            return; // package absent or unexpected arg — fail safe, never break rendering
        }

        try {
            if (null === self::$registry) {
                /** @var \XoopsSecurity|null $security */
                $security = $GLOBALS['xoopsSecurity'] ?? null;
                // xoops_getHandler() returns false on failure; SecurityExtension expects
                // ?XoopsGroupPermHandler, so normalise anything else to null.
                $permHandler = function_exists('xoops_getHandler') ? xoops_getHandler('groupperm') : null;
                if (!($permHandler instanceof \XoopsGroupPermHandler)) {
                    $permHandler = null;
                }

                $registry = new ExtensionRegistry();
                $registry->add(new TextExtension());
                $registry->add(new FormatExtension());
                $registry->add(new NavigationExtension());
                $registry->add(new SecurityExtension($security, $permHandler));
                $registry->add(new FormExtension($security));
                $registry->add(new DataExtension());
                $registry->add(new AssetExtension());
                $registry->add(new XoopsCoreExtension());
                // RayDebugExtension intentionally omitted — duplicates core ray plugins.

                self::$registry = $registry;
            }

            self::$registry->registerAll($tpl);
        } catch (\Throwable $e) {
            // Non-fatal: a registration problem must never white-screen the site. Emit a
            // generic warning for the error handler and keep the exception detail in the
            // debug log only, so internals are never leaked into displayed warning text.
            trigger_error('SmartyExtensions registration failed.', E_USER_WARNING);
            if (isset($GLOBALS['xoopsLogger']) && \is_object($GLOBALS['xoopsLogger'])) {
                $GLOBALS['xoopsLogger']->addExtra('SmartyExtensions', $e->getMessage());
            }
        }
    }
}
