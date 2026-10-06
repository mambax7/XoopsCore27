<?php
/**
 * Cache handlers
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 * @since               1.00
 * @package             Frameworks
 * @subpackage          art
 */

if (!defined('FRAMEWORKS_ART_FUNCTIONS_CACHE')):
    define('FRAMEWORKS_ART_FUNCTIONS_CACHE', true);

    /**
     * @param array|null $groups
     *
     * @return string group segment for a cache name; '' when no usable cache-id key exists,
     *                in which case the caller must neither read nor write the cache
     */
    function mod_generateCacheId_byGroup($groups = null)
    {
        global $xoopsUser;

        if (!empty($groups) && \is_array($groups)) {
        } elseif (is_object($xoopsUser)) {
            $groups = $xoopsUser->getGroups();
        }
        if (!empty($groups) && \is_array($groups)) {
            require_once XOOPS_ROOT_PATH . '/include/file_safety.php';
            $contentCacheId = xoops_groupCacheKey($groups);
        } else {
            $contentCacheId = XOOPS_GROUP_ANONYMOUS;
        }

        return $contentCacheId;
    }

/**
 * @param mixed $groups
 *
 * @return string
 */
function mod_generateCacheId($groups = null)
{
    return mod_generateCacheId_byGroup($groups);
}

/**
 * @param mixed $data
 * @param string|null   $name
 * @param string|null   $dirname
 * @param string $root_path
 *
 * @return bool
 */
function mod_createFile($data, $name = null, $dirname = null, $root_path = XOOPS_CACHE_PATH)
{
    global $xoopsModule;

    $name    = $name ?: (string) time();
    $dirname = $dirname ?: (is_object($xoopsModule) ? $xoopsModule->getVar('dirname', 'n') : 'system');

    xoops_load('XoopsCache');
    $key = "{$dirname}_{$name}";

    return XoopsCache::write($key, $data);
}

/**
 * @param mixed $data
 * @param string|null $name
 * @param string|null $dirname
 *
 * @return bool
 */
function mod_createCacheFile($data, $name = null, $dirname = null)
{
    return mod_createFile($data, $name, $dirname);
}

/**
 * @param mixed $data
 * @param string|null $name
 * @param string|null $dirname
 * @param mixed $groups
 *
 * @return bool
 */
function mod_createCacheFile_byGroup($data, $name = null, $dirname = null, $groups = null)
{
    $groupId = mod_generateCacheId_byGroup($groups);
    if ('' === $groupId) {
        return false; // no usable cache-id key: fail closed, write nothing
    }

    return mod_createCacheFile($data, $name . $groupId, $dirname);
}

/**
 * @param string $name
 * @param string|null   $dirname
 * @param string $root_path
 *
 * @return mixed|null
 */
function mod_loadFile($name, $dirname = null, $root_path = XOOPS_CACHE_PATH)
{
    global $xoopsModule;

    $data = null;

    if (empty($name)) {
        return $data;
    }
    $dirname = $dirname ?: (is_object($xoopsModule) ? $xoopsModule->getVar('dirname', 'n') : 'system');
    xoops_load('XoopsCache');
    $key = "{$dirname}_{$name}";

    return XoopsCache::read($key);
}

/**
 * @param string $name
 * @param string|null $dirname
 *
 * @return mixed
 */
function mod_loadCacheFile($name, $dirname = null)
{
    $data = mod_loadFile($name, $dirname);

    return $data;
}

/**
 * @param string $name
 * @param string|null $dirname
 * @param mixed $groups
 *
 * @return mixed
 */
function mod_loadCacheFile_byGroup($name, $dirname = null, $groups = null)
{
    $groupId = mod_generateCacheId_byGroup($groups);
    if ('' === $groupId) {
        return null; // no usable cache-id key: fail closed, read nothing
    }

    return mod_loadFile($name . $groupId, $dirname);
}

/* Shall we use the function of glob for better performance ? */

/**
 * @param string $name
 * @param string|null   $dirname
 * @param string $root_path
 *
 * @return bool
 */
function mod_clearFile($name = '', $dirname = null, $root_path = XOOPS_CACHE_PATH)
{
    if (empty($dirname)) {
        // Quote the interpolated value so it cannot inject regex metacharacters
        // (ReDoS / unintended match). $dirname is empty in this branch, so the
        // pattern is the "any prefix" form (the old $dirname ? … ternary was dead).
        $nameQuoted = preg_quote((string) $name, '/');
        $pattern = "[^_]+_{$nameQuoted}.*\.php";
        if ($handle = opendir($root_path)) {
            require_once XOOPS_ROOT_PATH . '/include/file_safety.php';
            while (false !== ($file = readdir($handle))) {
                if (is_file($root_path . '/' . $file) && preg_match("/{$pattern}$/", $file)) {
                    xoops_remove_file_quietly($root_path . '/' . $file, 'cache');
                }
            }
            closedir($handle);
        }
    } else {
        // basename() strips path traversal; removing glob metacharacters (*, ?, [])
        // stops a crafted value from broadening the match to unrelated cache files.
        $safeDir  = str_replace(['*', '?', '[', ']'], '', basename((string) $dirname));
        $safeName = str_replace(['*', '?', '[', ']'], '', basename((string) $name));
        $files = (array) glob($root_path . "/*{$safeDir}_{$safeName}*.php");
        require_once XOOPS_ROOT_PATH . '/include/file_safety.php';
        foreach ($files as $file) {
            xoops_remove_file_quietly($file, 'cache');
        }
    }

    return true;
}

/**
 * @param string $name
 * @param string|null   $dirname
 *
 * @return bool
 */
function mod_clearCacheFile($name = '', $dirname = null)
{
    return mod_clearFile($name, $dirname);
}

/**
 * @param string $pattern
 *
 * @return bool
 */
function mod_clearSmartyCache($pattern = '')
{
    global $xoopsModule;

    if (empty($pattern)) {
        $dirname = (is_object($xoopsModule) ? $xoopsModule->getVar('dirname', 'n') : 'system');
        $pattern = "/(^{$dirname}\^.*\.html$|blk_{$dirname}_.*[^\.]*\.html$)/";
    }
    if ($handle = opendir(XOOPS_CACHE_PATH)) {
        require_once XOOPS_ROOT_PATH . '/include/file_safety.php';
        while (false !== ($file = readdir($handle))) {
            if (is_file(XOOPS_CACHE_PATH . '/' . $file) && preg_match($pattern, $file)) {
                xoops_remove_file_quietly(XOOPS_CACHE_PATH . '/' . $file, 'cache');
            }
        }
        closedir($handle);
    }

    return true;
}

endif;
