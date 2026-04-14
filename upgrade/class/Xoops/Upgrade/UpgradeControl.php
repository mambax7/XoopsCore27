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

namespace Xoops\Upgrade;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

use XoopsMySQLDatabase;

/**
 * XOOPS Upgrade Controller
 *
 * Namespaced, DI-enabled replacement for the legacy upgrade/class/control.php.
 * Manages the upgrade queue, language loading, and patch instantiation via
 * the createPatch() factory method.
 *
 * @category  Xoops\Upgrade
 * @package   Xoops
 * @author    Taiwen Jiang <phppp@users.sourceforge.net>
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class UpgradeControl
{
    /** @var PatchStatus[] $upgradeQueue keyed by patch directory name */
    public array $upgradeQueue = [];

    /** @var string[] $needWriteFiles files that must be writable before upgrade can proceed */
    public array $needWriteFiles = [];

    /** @var bool $needUpgrade true if at least one patch has not been applied */
    public bool $needUpgrade = false;

    /** @var array<string, array{url: string, title: string}> $supportSites support site metadata pulled from language files */
    public array $supportSites = [];

    /** @var bool $needMainfileRewrite true if mainfile.php must be rewritten */
    public bool $needMainfileRewrite = false;

    /** @var string[] $mainfileKeys configuration keys to rewrite into mainfile.php */
    public array $mainfileKeys = [];

    /** @var string $upgradeLanguage language being used in the upgrade process */
    public string $upgradeLanguage = 'english';

    /**
     * Constructor.
     *
     * @param XoopsMySQLDatabase $db database connection
     */
    public function __construct(
        private readonly XoopsMySQLDatabase $db,
    ) {}

    /**
     * Instantiate a patch class by name, injecting db and control dependencies.
     *
     * @param string $className fully-qualified patch class name returned by a patch index.php
     *
     * @return XoopsUpgrade
     *
     * @throws \InvalidArgumentException if the class does not exist or does not extend XoopsUpgrade
     */
    public function createPatch(string $className): XoopsUpgrade
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException(
                sprintf('Upgrade patch class "%s" does not exist.', $className)
            );
        }
        if ($className !== XoopsUpgrade::class && !is_subclass_of($className, XoopsUpgrade::class)) {
            throw new \InvalidArgumentException(
                sprintf('Upgrade patch class "%s" must extend %s.', $className, XoopsUpgrade::class)
            );
        }

        return new $className($this->db, $this);
    }

    /**
     * Get a list of directories inside a directory.
     *
     * @param string $dirname directory to search
     *
     * @return string[] sorted list of subdirectory names
     */
    public function getDirList(string $dirname): array
    {
        $dirlist = [];
        if (is_dir($dirname) && $handle = opendir($dirname)) {
            while (false !== ($file = readdir($handle))) {
                if (!str_starts_with($file, '.') && strtolower($file) !== 'cvs') {
                    if (is_dir("{$dirname}/{$file}")) {
                        $dirlist[] = $file;
                    }
                }
            }
            closedir($handle);
            asort($dirlist);
            reset($dirlist);
        }

        return $dirlist;
    }

    /**
     * Return the list of available language directories.
     *
     * @return string[] list of language folder names
     */
    public function availableLanguages(): array
    {
        $upgradeDir = dirname(__DIR__, 3); // class/Xoops/Upgrade/ -> upgrade/
        return $this->getDirList("{$upgradeDir}/language/");
    }

    /**
     * Normalize a requested upgrade language to a known language directory.
     *
     * @param string|null $language requested language code
     *
     * @return string validated language code, falling back to english
     */
    public function normalizeLanguage(?string $language): string
    {
        $language = (string) ($language ?? '');
        $availableLanguages = $this->availableLanguages();

        return in_array($language, $availableLanguages, true) ? $language : 'english';
    }

    /**
     * Load a language file for the upgrade process.
     *
     * Falls back to english if the requested language file does not exist.
     * Language files may define a $supports variable; if set, its entries
     * are merged into $this->supportSites.
     *
     * @param string      $domain   language file name (without .php extension)
     * @param string|null $language language folder to use; defaults to $this->upgradeLanguage
     *
     * @return void
     */
    public function loadLanguage(string $domain, ?string $language = null): void
    {
        // Upgrade language files populate $supports in local scope for the controller to merge.
        $supports = null;

        $language   = $this->normalizeLanguage($language ?? $this->upgradeLanguage);
        $upgradeDir = dirname(__DIR__, 3); // class/Xoops/Upgrade/ -> upgrade/
        $languageRoot = realpath("{$upgradeDir}/language");

        $candidate = false !== $languageRoot
            ? realpath($languageRoot . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . "{$domain}.php")
            : false;
        $fallback = false !== $languageRoot
            ? realpath($languageRoot . DIRECTORY_SEPARATOR . 'english' . DIRECTORY_SEPARATOR . "{$domain}.php")
            : false;

        if (false !== $languageRoot && false !== $candidate && str_starts_with($candidate, $languageRoot . DIRECTORY_SEPARATOR)) {
            include_once $candidate;
        } elseif (false !== $languageRoot && false !== $fallback && str_starts_with($fallback, $languageRoot . DIRECTORY_SEPARATOR)) {
            include_once $fallback;
        }

        if (null !== $supports) {
            $this->supportSites = array_merge($this->supportSites, $supports);
        }
    }

    /**
     * Determine the language to use during the upgrade process.
     *
     * Priority (highest to lowest):
     *  1. lang= GET/POST parameter
     *  2. xo_upgrade_lang cookie
     *  3. $xoopsConfig['language']
     *  4. 'english' fallback
     *
     * The resolved language is stored in a cookie for subsequent requests.
     *
     * @return string the language to use in the upgrade process
     */
    public function determineLanguage(): string
    {
        global $xoopsConfig;

        $upgrade_language = $xoopsConfig['language'] ?? null;
        $upgrade_language = \Xmf\Request::getString('xo_upgrade_lang', $upgrade_language, 'COOKIE');
        $upgrade_language = \Xmf\Request::getString('lang', $upgrade_language);
        $upgrade_language = $this->normalizeLanguage($upgrade_language);
        xoops_setcookie('xo_upgrade_lang', $upgrade_language, 0, null, null);

        $this->upgradeLanguage = $upgrade_language;
        $this->loadLanguage('upgrade');

        return $this->upgradeLanguage;
    }

    /**
     * Examine upgrade directories and determine which patches need to run and
     * which files need to be writable.
     *
     * Each patch directory whose name contains '-to-' must contain an index.php
     * that returns the fully-qualified class name of a XoopsUpgrade subclass.
     *
     * @return bool true if an upgrade is needed
     */
    public function buildUpgradeQueue(): bool
    {
        $upgradeRoot = dirname(__DIR__, 3);
        $dirs = $this->getDirList($upgradeRoot);

        /** @var PatchStatus[] $results */
        $results           = [];
        $files             = [];
        $this->needUpgrade = false;

        foreach ($dirs as $dir) {
            if (str_contains($dir, '-to-')) {
                $patchFile = $upgradeRoot . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . 'index.php';
                if (!file_exists($patchFile)) {
                    continue;
                }
                try {
                    $className = include $patchFile;
                } catch (\Throwable $e) {
                    // Stale directories from a previous version (e.g.
                    // upd_2.5.11-to-2.5.12 from the pre-rename beta cycle) can
                    // fail to load because they reference classes that no longer
                    // exist. Emit a visible warning and skip — crashing the
                    // entire upgrade is worse than skipping one broken patch.
                    trigger_error(
                        sprintf('Upgrade patch %s could not be loaded: %s', $dir, $e->getMessage()),
                        E_USER_WARNING
                    );
                    continue;
                }
                if (is_string($className) && class_exists($className)) {
                    $upg           = $this->createPatch($className);
                    $results[$dir] = $upg->isApplied();
                    if (!($results[$dir]->applied)) {
                        $this->needUpgrade = true;
                        if (!empty($results[$dir]->files)) {
                            $files = array_merge($files, $results[$dir]->files);
                        }
                    }
                }
            }
        }

        if ($this->needUpgrade && !empty($files)) {
            foreach ($files as $k => $file) {
                $testFile = preg_match('/^([.\/\\\\:])|([a-z]:)/i', $file) ? $file : "../{$file}";
                if (is_writable($testFile) || !file_exists($testFile)) {
                    unset($files[$k]);
                }
            }
        }

        $this->upgradeQueue    = $results;
        $this->needWriteFiles  = $files;

        return $this->needUpgrade;
    }

    /**
     * Get the count of patch sets that need to be applied.
     *
     * Calls buildUpgradeQueue() if the queue has not yet been populated.
     *
     * @return int number of unapplied patches
     */
    public function countUpgradeQueue(): int
    {
        if (empty($this->upgradeQueue)) {
            $this->buildUpgradeQueue();
        }
        $count = 0;
        foreach ($this->upgradeQueue as $patch) {
            $count += ($patch->applied) ? 0 : 1;
        }
        return $count;
    }

    /**
     * Return the directory name of the next unapplied patch.
     *
     * Calls buildUpgradeQueue() if the queue has not yet been populated.
     *
     * @return string|false directory name of next pending patch, or false when all applied
     */
    public function getNextPatch(): string|false
    {
        if (empty($this->upgradeQueue)) {
            $this->buildUpgradeQueue();
        }
        $next = false;

        foreach ($this->upgradeQueue as $directory => $patch) {
            if (!$patch->applied) {
                $next = $directory;
                break;
            }
        }
        return $next;
    }

    /**
     * Return a form consisting of a single continue button.
     *
     * @param string               $action     URL for the form action attribute
     * @param array<string, string> $parameters hidden input name/value pairs
     *
     * @return string HTML form markup
     */
    public function oneButtonContinueForm(string $action = 'index.php', array $parameters = ['action' => 'next']): string
    {
        $actionAttr = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
        $form  = '<form action="' . $actionAttr . '" method="post">';
        $form .= '<button class="btn btn-lg btn-success" type="submit">' . _CONTINUE;
        $form .= '  <span class="fa-solid fa-caret-right"></span></button>';
        foreach ($parameters as $name => $value) {
            $nameAttr = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
            $valueAttr = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $form .= '<input type="hidden" name="' . $nameAttr . '" value="' . $valueAttr . '">';
        }
        $form .= '</form>';

        return $form;
    }

    /**
     * Store the result of a mainfile check.
     *
     * @param bool     $needMainfileRewrite true if mainfile.php needs to be rewritten
     * @param string[] $mainfileKeys        configuration keys to update in mainfile.php
     *
     * @return void
     */
    public function storeMainfileCheck(bool $needMainfileRewrite, array $mainfileKeys): void
    {
        $this->needMainfileRewrite = $needMainfileRewrite;
        $this->mainfileKeys = $needMainfileRewrite ? $mainfileKeys : [];
    }
}
