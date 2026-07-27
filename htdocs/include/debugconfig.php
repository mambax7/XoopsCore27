<?php
/**
 * XOOPS debug configuration loader
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category            Debug
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             core
 * @link                https://xoops.org
 * @since               2.7.3
 * @author              XOOPS Team
 */

if (!defined('XOOPS_ROOT_PATH')) {
    die('Restricted access');
}

if (!function_exists('xoops_getDebugConfig')) {
    /**
     * Read xoops_data/data/debug.php, once per request.
     *
     * Returns an empty array when the file is absent, unreadable, does not return an
     * array, or has 'enabled' set to false — in every one of those cases XOOPS behaves
     * exactly as it did before this file existed. That is the point: the whole feature
     * is opt-in and its absence is the normal state.
     *
     * Loaded from two places, whichever comes first:
     *  - mainfile.php (new installs), early enough to define XOOPS_DEBUG itself;
     *  - include/common.php, which every install reaches, so a site whose mainfile.php
     *    predates 2.7.3 still gets the logging even though its XOOPS_DEBUG constant was
     *    already fixed by then.
     *
     * @return array the settings, or [] when debugging is not configured
     */
    function xoops_getDebugConfig()
    {
        static $config = null;

        if (null !== $config) {
            return $config;
        }
        $config = [];

        if (!defined('XOOPS_VAR_PATH')) {
            return $config;
        }

        $file = XOOPS_VAR_PATH . '/data/debug.php';
        if (!is_file($file) || !is_readable($file)) {
            return $config;
        }

        $loaded = include $file;
        if (!is_array($loaded) || empty($loaded['enabled'])) {
            return $config;
        }

        $config = $loaded;

        return $config;
    }
}

if (!function_exists('xoops_applyDebugConfig')) {
    /**
     * Apply the PHP-level settings: display_errors and error_reporting.
     *
     * Kept separate from xoops_getDebugConfig() so the configuration can be inspected
     * without side effects — the upgrade tooling and tests need to do exactly that.
     *
     * @return bool true when debugging is enabled and was applied
     */
    function xoops_applyDebugConfig()
    {
        $config = xoops_getDebugConfig();
        if ([] === $config) {
            return false;
        }

        if (array_key_exists('display_errors', $config)) {
            ini_set('display_errors', $config['display_errors'] ? '1' : '0');
        }
        if (array_key_exists('error_reporting', $config)) {
            error_reporting((int) $config['error_reporting']);
        }

        return true;
    }
}
