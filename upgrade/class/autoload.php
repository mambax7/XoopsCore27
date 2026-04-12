<?php
/**
 * PSR-4 autoloader for Xoops\Upgrade namespace.
 *
 * Maps Xoops\Upgrade\* to class/Xoops/Upgrade/*.php
 * Self-contained — no Composer dependency.
 *
 * @category  Xoops\Upgrade
 * @package   Upgrade
 * @author    XOOPS Development Team
 * @copyright XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Xoops\\Upgrade\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/Xoops/Upgrade/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
