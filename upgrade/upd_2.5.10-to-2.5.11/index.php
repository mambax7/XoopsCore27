<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

if (!class_exists('Db_manager', false)) {
    require_once XOOPS_ROOT_PATH . '/install/class/dbmanager.php';
}

use Xmf\Database\Tables;
use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

/**
 * Upgrade from 2.5.10 to 2.5.11
 *
 * @category  XOOPS
 * @package   upgrade
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 * @since     2.5.11
 * @author    XOOPS Team
 */

class Upgrade_2511 extends XoopsUpgrade
{
    private string $mailMethodConfigName = 'mailmethod';

    /**
     * __construct
     *
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = [
            'cleancache',
            'bannerintsize',
            'captchadata',
            'configkey',
            'modulesvarchar',
            'qmail',
            'rmindexhtml',
            'textsanitizer',
            'xoopsconfig',
            'templates',
            'templatesadmin',
            'zapsmarty',
            'notificationmethod',
        ];
        $this->usedFiles = [];
        $this->pathsToCheck = [
            XOOPS_ROOT_PATH . '/cache',
            XOOPS_ROOT_PATH . '/class',
            XOOPS_ROOT_PATH . '/Frameworks',
            XOOPS_ROOT_PATH . '/images',
            XOOPS_ROOT_PATH . '/include',
            XOOPS_ROOT_PATH . '/kernel',
            XOOPS_ROOT_PATH . '/language',
            XOOPS_ROOT_PATH . '/media',
            XOOPS_ROOT_PATH . '/modules/pm',
            XOOPS_ROOT_PATH . '/modules/profile',
            XOOPS_ROOT_PATH . '/modules/protector',
            XOOPS_ROOT_PATH . '/modules/system',
            XOOPS_ROOT_PATH . '/templates_c',
            XOOPS_ROOT_PATH . '/themes/default',
            XOOPS_ROOT_PATH . '/themes/xbootstrap',
            XOOPS_ROOT_PATH . '/themes/xswatch',
            XOOPS_ROOT_PATH . '/themes/xswatch4',
            XOOPS_ROOT_PATH . '/uploads',
            XOOPS_VAR_PATH,
            XOOPS_PATH,
        ];
        $this->usedFiles = array_merge($this->usedFiles, $this->pathsToCheck);
    }

    protected $cleanCacheKey = 'cache-cleaned';

    /**
     * We must remove stale template caches and compiles
     *
     * @return bool true if patch IS applied, false if NOT applied
     */
    public function check_cleancache(): bool
    {
        if (!array_key_exists($this->cleanCacheKey, $_SESSION)
            || false === $_SESSION[$this->cleanCacheKey]) {
            return false;
        }
        return true;
    }

    /**
     * Remove  all caches and compiles
     *
     * @return bool true if applied, false if failed
     */
    public function apply_cleancache(): bool
    {
        require_once XOOPS_ROOT_PATH . '/modules/system/class/maintenance.php';
        $maintenance = new SystemMaintenance();
        $result  = $maintenance->CleanCache([1, 2, 3]);
        if (true === $result) {
            $_SESSION[$this->cleanCacheKey] = true;
        }
        return $result;
    }

    /**
     * Determine if columns are declared mediumint, and if
     * so, queue ddl to alter to int.
     *
     * @param Tables   $migrate
     * @param string   $bannerTableName
     * @param string[] $bannerColumnNames array of columns to check
     *
     * @return integer count of queue items added
     */
    protected function fromMediumToInt(Tables $migrate, $bannerTableName, $bannerColumnNames)
    {
        $migrate->useTable($bannerTableName);
        $count = 0;
        foreach ($bannerColumnNames as $column) {
            $attributes = $migrate->getColumnAttributes($bannerTableName, $column);
            if (0 === strpos(trim($attributes), 'mediumint')) {
                $count++;
                $migrate->alterColumn($bannerTableName, $column, 'int(10) UNSIGNED NOT NULL DEFAULT \'0\'');
            }
        }
        return $count;
    }

    private $bannerTableName = 'banner';
    private $bannerColumnNames = ['impmade', 'clicks'];

    /**
     * Increase count columns from mediumint to int
     *
     * @return bool true if patch IS applied, false if NOT applied
     */
    public function check_bannerintsize(): bool
    {
        $migrate = new Tables();
        $count = $this->fromMediumToInt($migrate, $this->bannerTableName, $this->bannerColumnNames);

        return 0 == $count;
    }

    /**
     * Increase count columns from mediumint to int (Think BIG!)
     *
     * @return bool true if applied, false if failed
     */
    public function apply_bannerintsize(): bool
    {
        $migrate = new Tables();

        $count = $this->fromMediumToInt($migrate, $this->bannerTableName, $this->bannerColumnNames);

        $result = $migrate->executeQueue(true);
        if (false === $result) {
            $this->logs[] = sprintf(
                'Migration of %s table failed. Error: %s - %s',
                $this->bannerTableName,
                $migrate->getLastErrNo(),
                $migrate->getLastError(),
            );
            return false;
        }

        return 0 !== $count;
    }

    /**
     * Add qmail as valid mailmethod
     *
     * @return bool
     */
    public function check_qmail(): bool
    {
        $table = $this->db->prefix('configoption');
        $confId = $this->getMailerMethodConfigId();
        if (null === $confId) {
            return false;
        }

        $sql = sprintf(
            'SELECT count(*) FROM `%s` '
            . "WHERE `conf_id` = %d AND `confop_name` = 'qmail'",
            $this->db->escape($table),
            $confId,
        );

        /** @var mysqli_result $result */
        $result = $this->db->query($sql);
        if ($this->db->isResultSet($result) && ($result instanceof \mysqli_result)) {
            $row = $this->db->fetchRow($result);
            if ($row) {
                $count = $row[0];
                return (0 === (int) $count) ? false : true;
            }
        }
        return false;
    }

    /**
     * Add qmail as valid mailmethod
     *
     * phpMailer has qmail support, similar to but slightly different than sendmail
     * This will allow webmasters to utilize qmail if it is provisioned on server.
     *
     * @return bool
     */
    public function apply_qmail(): bool
    {
        $confId = $this->getMailerMethodConfigId();
        if (null === $confId) {
            $this->logs[] = 'Unable to locate the mailmethod configuration row for qmail option insertion.';

            return false;
        }

        $migrate = new Tables();
        $migrate->useTable('configoption');
        $migrate->insert(
            'configoption',
            ['confop_name' => 'qmail', 'confop_value' => 'qmail', 'conf_id' => $confId],
        );
        return $migrate->executeQueue(true);
    }

    /**
     * Return the config row id for the mailer method preference.
     *
     * @return int|null
     */
    private function getMailerMethodConfigId(): ?int
    {
        $table = $this->db->prefix('config');
        $sql = sprintf(
            'SELECT `conf_id` FROM `%s` WHERE `conf_name` = %s LIMIT 1',
            $this->db->escape($table),
            $this->db->quote($this->mailMethodConfigName),
        );
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return null;
        }

        $row = $this->db->fetchRow($result);

        return $row ? (int) $row[0] : null;
    }

    /**
     * Do we need to move captcha writable data?
     *
     * @return bool true if patch IS applied, false if NOT applied
     */
    public function check_captchadata(): bool
    {
        $captchaConfigFile = XOOPS_VAR_PATH . '/configs/captcha/config.php';
        $oldCaptchaConfigFile = XOOPS_ROOT_PATH . '/class/captcha/config.php';
        if (!file_exists($oldCaptchaConfigFile)) { // nothing to copy
            return true;
        }
        return file_exists($captchaConfigFile);
    }

    /**
     * Attempt to make the supplied path
     *
     * @param string $newPath
     *
     * @return bool
     */
    private function makeDirectory($newPath)
    {
        if (!mkdir($newPath) && !is_dir($newPath)) {
            $this->logs[] = sprintf('Captcha config directory %s was not created', $newPath);
            return false;
        }
        return true;
    }

    /**
     * Copy file $source to $destination
     *
     * @param string $source
     * @param string $destination
     *
     * @return bool true if successful, false on error
     */
    private function copyFile($source, $destination)
    {
        if (!file_exists($destination)) { // don't overwrite anything
            $result = copy($source, $destination);
            if (false === $result) {
                $this->logs[] = sprintf('Captcha config file copy %s failed', basename($source));
                return false;
            }
        }
        return true;
    }

    /**
     * Move captcha configs to xoops_data to segregate writable data
     *
     * @return bool
     */
    public function apply_captchadata(): bool
    {
        $returnResult = true;
        $sourcePath = XOOPS_ROOT_PATH . '/class/captcha/';
        $destinationPath = XOOPS_VAR_PATH . '/configs/captcha/';

        if (!file_exists($destinationPath)) {
            $this->makeDirectory($destinationPath);
        }
        $directory = dir($sourcePath);
        if (false === $directory) {
            $this->logs[] = sprintf('Failed to read source %s', $sourcePath);
            return false;
        }
        while (false !== ($entry = $directory->read())) {
            if (false === strpos($entry, '.dist.')
                && 0 === strpos($entry, 'config.')
                && '.php' === substr($entry, -4)) {
                $src = $sourcePath . $entry;
                $dest = $destinationPath . $entry;
                $status = $this->copyFile($src, $dest);
                if (false === $status) {
                    $returnResult = false;
                }
            }
        }
        $directory->close();

        return $returnResult;
    }

    //config
    /**
     * Increase primary key columns from smallint to int
     *
     * @return bool true if patch IS applied, false if NOT applied
     */
    public function check_configkey(): bool
    {
        $tableName = 'config';
        $columnName = 'conf_id';

        $migrate = new Tables();
        $migrate->useTable($tableName);
        $count = 0;
        $attributes = $migrate->getColumnAttributes($tableName, $columnName);
        if (0 === strpos(trim($attributes), 'smallint')) {
            $count++;
            $migrate->alterColumn($tableName, $columnName, 'int(10) UNSIGNED NOT NULL');
        }

        return 0 == $count;
    }

    /**
     * Increase primary key columns from smallint to int
     *
     * @return bool true if applied, false if failed
     */
    public function apply_configkey(): bool
    {
        $tableName = 'config';
        $columnName = 'conf_id';

        $migrate = new Tables();
        $migrate->useTable($tableName);
        $count = 0;
        $attributes = $migrate->getColumnAttributes($tableName, $columnName);
        if (0 === strpos(trim($attributes), 'smallint')) {
            $count++;
            $migrate->alterColumn($tableName, $columnName, 'int(10) UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        $result = $migrate->executeQueue(true);
        if (false === $result) {
            $this->logs[] = sprintf(
                'Migration of %s table failed. Error: %s - %s',
                $tableName,
                $migrate->getLastErrNo(),
                $migrate->getLastError(),
            );
            return false;
        }

        return 0 !== $count;
    }
    //configend

    /**
     * Do we need to create a xoops_data/configs/xoopsconfig.php?
     *
     * @return bool true if patch IS applied, false if NOT applied
     */
    public function check_xoopsconfig(): bool
    {
        $xoopsConfigFile = XOOPS_VAR_PATH . '/configs/xoopsconfig.php';
        return file_exists($xoopsConfigFile);
    }

    /**
     * Create xoops_data/configs/xoopsconfig.php from xoopsconfig.dist.php
     *
     * @return bool true if applied, false if failed
     */
    public function apply_xoopsconfig(): bool
    {
        $source = XOOPS_VAR_PATH . '/configs/xoopsconfig.dist.php';
        $destination = XOOPS_VAR_PATH . '/configs/xoopsconfig.php';
        if (!file_exists($destination)) { // don't overwrite anything
            $result = copy($source, $destination);
            if (false === $result) {
                $this->logs[] = 'xoopsconfig.php file copy failed';
                return false;
            }
        }
        return true;
    }

    /**
     * This is a default list based on extensions as supplied by XOOPS.
     * If possible, we will build a list based on contents of class/textsanitizer/
     * key is file path relative to XOOPS_ROOT_PATH . '/class/textsanitizer/
     * value is file path relative to XOOPS_VAR_PATH . '/configs/textsanitizer/'
     *
     * @var string[]
     */
    protected $textsanitizerConfigFiles = [
        'config.php' => 'config.php',
        'censor/config.php' => 'config.censor.php',
        'flash/config.php' => 'config.flash.php',
        'image/config.php' => 'config.image.php',
        'mms/config.php' => 'config.mms.php',
        'rtsp/config.php' => 'config.rtsp.php',
        'syntaxhighlight/config.php' => 'config.syntaxhighlight.php',
        'textfilter/config.php' => 'config.textfilter.php',
        'wiki/config.php' => 'config.wiki.php',
        'wmp/config.php' => 'config.wmp.php',
    ];

    /**
     * Build a list of config files using the existing textsanitizer/config.php
     * each as source name => destination name in $this->textsanitizerConfigFiles
     *
     * This should prevent some issues with customized systems.
     *
     * @return void
     */
    protected function buildListTSConfigs()
    {
        if (file_exists(XOOPS_ROOT_PATH . '/class/textsanitizer/config.php')) {
            $config = include XOOPS_ROOT_PATH . '/class/textsanitizer/config.php';
            if (is_array($config) && array_key_exists('extentions', $config)) {
                $this->textsanitizerConfigFiles = [
                    'config.php' => 'config.php',
                ];
                foreach ($config['extentions'] as $module => $enabled) {
                    $source = "{$module}/config.php";
                    if (file_exists(XOOPS_ROOT_PATH . '/class/textsanitizer/' . $source)) {
                        $destination = "{$module}/config.{$module}.php";
                        $this->textsanitizerConfigFiles[$source] = $destination;
                    }
                }
            }
        }
        return;
    }

    /**
     * Do we need to move any existing files to xoops_data/configs/textsanitizer/ ?
     *
     * @return bool true if patch IS applied, false if NOT applied
     */
    public function check_textsanitizer(): bool
    {
        $this->buildListTSConfigs();
        foreach ($this->textsanitizerConfigFiles as $source => $destination) {
            $src  = XOOPS_ROOT_PATH . '/class/textsanitizer/' . $source;
            $dest = XOOPS_VAR_PATH . '/configs/textsanitizer/' . $destination;
            if (!file_exists($dest) && file_exists($src)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Copy and rename any existing class/textsanitizer/ config files to xoops_data/configs/textsanitizer/
     *
     * @return bool true if applied, false if failed
     */
    public function apply_textsanitizer(): bool
    {
        $this->buildListTSConfigs();
        $return = true;
        foreach ($this->textsanitizerConfigFiles as $source => $destination) {
            $src  = XOOPS_ROOT_PATH . '/class/textsanitizer/' . $source;
            $dest = XOOPS_VAR_PATH . '/configs/textsanitizer/' . $destination;
            if (!file_exists($dest) && file_exists($src)) {
                $result = copy($src, $dest);
                if (false === $result) {
                    $this->logs[] = sprintf('textsanitizer file copy to %s failed', $destination);
                    $return = false;
                }
            }
        }
        return $return;
    }

    /**
     * Attempt to remove index.html files replaced by index.php
     */
    /**
     * List of directories supplied by XOOPS. This is used to try and keep us out
     * of things added to the system locally. (Set in __construct() for php BC.)
     *
     * @var string[]
     */
    private $pathsToCheck;

    /**
     * Do we need to remove any index.html files that were replaced by index.php files?
     *
     * @return bool true if patch IS applied, false if NOT applied
     */
    public function check_rmindexhtml(): bool
    {
        /**
         * If we find an index.html that is writable, we know there is work to do
         *
         * @param string $name file name to check
         *
         * @return bool  true to continue, false to stop scan
         */
        $stopIfFound = function ($name) {
            $ok = is_writable($name);
            return !($ok);
        };

        clearstatcache();

        return $this->dirWalker($stopIfFound);
    }

    /**
     * Unlink any index.html files that have been replaced by index.php files
     *
     * @return bool true if patch applied, false if failed
     */
    public function apply_rmindexhtml(): bool
    {
        /**
         * Do unlink() on file
         * Always return true so we process each writable index.html
         *
         * @param string $name file name to unlink
         *
         * @return true always report true, even if we can't delete -- best effort only
         */
        $unlinkByName = function ($name) {
            if (is_writable($name)) {
                $result = unlink($name);
            }
            return true;
        };


        return $this->dirWalker($unlinkByName);
    }

    /**
     * Walk list of directories in $pathsToCheck
     *
     * @param \Closure $onFound
     *
     * @return bool
     */
    private function dirWalker(\Closure $onFound)
    {
        $check = true;
        foreach ($this->pathsToCheck as $path) {
            $check = $this->checkDirForIndexHtml($path, $onFound);
            if (false === $check) {
                break;
            }
        }
        if (false !== $check) {
            $check = true;
        }
        return $check;
    }

    /**
     * Recursively check for index.html files that have a corresponding index.php file
     * in the supplied path.
     *
     * @param string   $startingPath
     * @param \Closure $onFound
     *
     * @return false|int false if onFound returned false (don't continue) else count of matches
     */
    private function checkDirForIndexHtml($startingPath, \Closure $onFound)
    {
        if (!is_dir($startingPath)) {
            return 0;
        }
        $i = 0;
        $rdi = new \RecursiveDirectoryIterator($startingPath);
        $rii = new \RecursiveIteratorIterator($rdi);
        /** @var \SplFileInfo $fileinfo */
        foreach ($rii as $fileinfo) {
            if ($fileinfo->isFile() && 'index.html' === $fileinfo->getFilename() && 60 > $fileinfo->getSize()) {
                $path = $fileinfo->getPath();
                $testFilename = $path . '/index.php';
                if (file_exists($testFilename)) {
                    $unlinkName = $path . '/' . $fileinfo->getFilename();
                    ++$i;
                    $continue = $onFound($unlinkName);
                    if (false === $continue) {
                        return $continue;
                    }
                }
            }
        }
        return $i;
    }

    /**
     * Determine if columns are declared smallint, and if
     * so, queue ddl to alter to varchar.
     *
     * @param Tables   $migrate
     * @param string   $modulesTableName
     * @param string[] $modulesColumnNames  array of columns to check
     *
     * @return integer count of queue items added
     */
    protected function fromSmallintToVarchar(Tables $migrate, $modulesTableName, $modulesColumnNames)
    {
        $migrate->useTable($modulesTableName);
        $count = 0;
        foreach ($modulesColumnNames as $column) {
            $attributes = $migrate->getColumnAttributes($modulesTableName, $column);
            if (is_string($attributes) && 0 === strpos(trim($attributes), 'smallint')) {
                $count++;
                $migrate->alterColumn($modulesTableName, $column, 'varchar(32) NOT NULL DEFAULT \'\'');
            }
        }
        return $count;
    }

    private $modulesTableName = 'modules';
    private $modulesColumnNames = ['version'];

    /**
     * Increase version columns from smallint to varchar
     *
     * @return bool true if patch IS applied, false if NOT applied
     */
    public function check_modulesvarchar(): bool
    {
        $migrate = new Tables();
        $count = $this->fromSmallintToVarchar($migrate, $this->modulesTableName, $this->modulesColumnNames);
        return 0 == $count;
    }

    /**
     * Increase version columns from smallint to varchar
     *
     * @return bool true if applied, false if failed
     */
    public function apply_modulesvarchar(): bool
    {
        $migrate = new Tables();

        $count = $this->fromSmallintToVarchar($migrate, $this->modulesTableName, $this->modulesColumnNames);

        $result = $migrate->executeQueue(true);
        if (false === $result) {
            $this->logs[] = sprintf(
                'Migration of %s table failed. Error: %s - %s',
                $this->modulesTableName,
                $migrate->getLastErrNo(),
                $migrate->getLastError(),
            );
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function check_templates(): bool
    {
        $templates = $this->getSystemTemplatesByType('module');
        if ([] === $templates) {
            return false;
        }

        $quotedTemplates = array_map([$this->db, 'quote'], array_column($templates, 'file'));
        $sql = 'SELECT COUNT(DISTINCT tf.`tpl_file`)'
            . ' FROM `' . $this->db->prefix('tplfile') . '` tf'
            . ' INNER JOIN `' . $this->db->prefix('tplsource') . '` ts ON ts.`tpl_id` = tf.`tpl_id`'
            . ' WHERE tf.`tpl_file` IN (' . implode(', ', $quotedTemplates) . ") AND tf.`tpl_type` = 'module'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        if (!$row) {
            return false;
        }
        $count = (int) $row[0];

        return count($templates) === $count;
    }


    /**
     * @return bool
     */
    public function apply_templates(): bool
    {
        return $this->applyTemplateSet('module', XOOPS_ROOT_PATH . '/modules/system/templates');
    }

    /**
     * @return bool
     */
    public function check_templatesadmin(): bool
    {
        $templates = $this->getSystemTemplatesByType('admin');
        if ([] === $templates) {
            return false;
        }

        $quotedTemplates = array_map([$this->db, 'quote'], array_column($templates, 'file'));
        $sql = 'SELECT COUNT(DISTINCT tf.`tpl_file`)'
            . ' FROM `' . $this->db->prefix('tplfile') . '` tf'
            . ' INNER JOIN `' . $this->db->prefix('tplsource') . '` ts ON ts.`tpl_id` = tf.`tpl_id`'
            . ' WHERE tf.`tpl_file` IN (' . implode(', ', $quotedTemplates) . ") AND tf.`tpl_type` = 'admin'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        if (!$row) {
            return false;
        }
        $count = (int) $row[0];

        return count($templates) === $count;
    }

    /**
     * @return bool
     */
    public function apply_templatesadmin(): bool
    {
        return $this->applyTemplateSet('admin', XOOPS_ROOT_PATH . '/modules/system/templates/admin');
    }

    //modules/system/themes/legacy/legacy.php
    /**
     * Do we need to delete obsolete Smarty files?
     *
     * @return bool
     */
    public function check_zapsmarty(): bool
    {
        return !file_exists(XOOPS_ROOT_PATH . '/class/smarty/Smarty.class.php');
    }

    /**
     * Delete obsolete Smarty files
     *
     * @return bool
     */
    public function apply_zapsmarty(): bool
    {
        // Define the base directory
        $baseDir = XOOPS_ROOT_PATH . '/class/smarty/';

        // List of sub-folders and files to delete
        $itemsToDelete = [
            'configs',
            'internals',
            'xoops_plugins',
            'Config_File.class.php',
            'debug.tpl',
            'Smarty.class.php',
            'Smarty_Compiler.class.php',
        ];

        // Loop through each item and delete it
        foreach ($itemsToDelete as $item) {
            $path = $baseDir . $item;

            if (is_link($path)) {
                if (!unlink($path)) {
                    $this->logs[] = 'Failed to delete Smarty symlink: ' . basename($path);

                    return false;
                }
            } elseif (is_dir($path)) {
                if (!self::deleteFolder($path)) {
                    $this->logs[] = 'Failed to delete Smarty directory: ' . basename($path);

                    return false;
                }
            } elseif (is_file($path)) {
                if (!unlink($path)) {
                    $this->logs[] = 'Failed to delete Smarty file: ' . basename($path);

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Recursively delete a folder and all its contents
     *
     * @param string $folderPath path to the folder to delete
     *
     * @return bool true on success, false on failure
     */
    private static function deleteFolder(string $folderPath): bool
    {
        if (!is_dir($folderPath)) {
            return true;
        }

        if (is_link($folderPath)) {
            return unlink($folderPath);
        }

        $resolvedRoot = realpath($folderPath);
        $allowedBase  = realpath(XOOPS_ROOT_PATH . '/class/smarty');
        if (false === $resolvedRoot || false === $allowedBase) {
            return false;
        }

        $allowedPrefix = rtrim($allowedBase, '\\/') . DIRECTORY_SEPARATOR;
        if ($resolvedRoot !== $allowedBase && !str_starts_with($resolvedRoot, $allowedPrefix)) {
            return false;
        }

        $scanned = scandir($resolvedRoot);
        if (false === $scanned) {
            return false;
        }

        $files = array_diff($scanned, ['.', '..']);
        foreach ($files as $file) {
            $filePath = $resolvedRoot . DIRECTORY_SEPARATOR . $file;
            if (is_link($filePath)) {
                if (!unlink($filePath)) {
                    return false;
                }
            } elseif (is_dir($filePath)) {
                if (!self::deleteFolder($filePath)) {
                    return false;
                }
            } else {
                if (!unlink($filePath)) {
                    return false;
                }
            }
        }
        return rmdir($resolvedRoot);
    }

    /**
     * Check if default notification method already exists
     *
     */
    public function check_notificationmethod(): bool
    {
        $sql = 'SELECT `conf_id` FROM `' . $this->db->prefix('config') . "` WHERE `conf_name` = 'default_notification' LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        if (!$row) {
            return false;
        }

        $configId = (int) $row[0];
        $expectedOptions = [
            '_MI_DEFAULT_NOTIFICATION_METHOD_DISABLE' => '0',
            '_MI_DEFAULT_NOTIFICATION_METHOD_PM'      => '1',
            '_MI_DEFAULT_NOTIFICATION_METHOD_EMAIL'   => '2',
        ];
        $sql = 'SELECT COUNT(*) FROM `' . $this->db->prefix('configoption') . '`'
            . ' WHERE `conf_id` = ' . $configId
            . ' AND ('
            . implode(
                ' OR ',
                array_map(
                    fn(string $name, string $value): string => sprintf(
                        '(`confop_name` = %s AND `confop_value` = %s)',
                        $this->db->quote($name),
                        $this->db->quote($value)
                    ),
                    array_keys($expectedOptions),
                    $expectedOptions
                )
            )
            . ')';
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);

        return $row && count($expectedOptions) === (int) $row[0];
    }

    /**
     * @return bool
     */
    public function apply_notificationmethod(): bool
    {
        $expectedOptions = [
            '_MI_DEFAULT_NOTIFICATION_METHOD_DISABLE' => '0',
            '_MI_DEFAULT_NOTIFICATION_METHOD_PM'      => '1',
            '_MI_DEFAULT_NOTIFICATION_METHOD_EMAIL'   => '2',
        ];
        $sql    = 'SELECT `conf_id` FROM `' . $this->db->prefix('config') . "` WHERE `conf_name` = 'default_notification' LIMIT 1";
        $result = $this->db->query($sql);
        $row    = ($this->db->isResultSet($result) && ($result instanceof \mysqli_result))
            ? $this->db->fetchRow($result)
            : false;

        if (!$row) {
            $sql = 'INSERT INTO ' . $this->db->prefix('config') . ' (conf_id, conf_modid, conf_catid, conf_name, conf_title, conf_value, conf_desc, conf_formtype, conf_valuetype, conf_order) ' . ' VALUES ' . " (NULL, 0, 2, 'default_notification', '_MD_AM_DEFAULT_NOTIFICATION_METHOD', '1', '_MD_AM_DEFAULT_NOTIFICATION_METHOD_DESC', 'select', 'int', 3)";

            if (!$this->execOrFail($sql)) {
                return false;
            }
            $configId = (int) $this->db->getInsertId();
        } else {
            $configId = (int) $row[0];
        }

        foreach ($expectedOptions as $name => $value) {
            $sql = 'SELECT COUNT(*) FROM `' . $this->db->prefix('configoption') . '`'
                . ' WHERE `conf_id` = ' . $configId
                . ' AND `confop_name` = ' . $this->db->quote($name)
                . ' AND `confop_value` = ' . $this->db->quote($value);
            $result = $this->db->query($sql);
            if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
                return false;
            }
            $row = $this->db->fetchRow($result);
            if (!$row || 0 === (int) $row[0]) {
                $sql = 'INSERT INTO ' . $this->db->prefix('configoption')
                    . ' (confop_id, confop_name, confop_value, conf_id) VALUES'
                    . ' (NULL, ' . $this->db->quote($name) . ', ' . $this->db->quote($value) . ', ' . $configId . ')';
                if (!$this->execOrFail($sql)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param string $type template type to filter (`module` or `admin`)
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSystemTemplatesByType(string $type): array
    {
        $modversion  = [];
        $versionFile = XOOPS_ROOT_PATH . '/modules/system/xoops_version.php';
        if (!file_exists($versionFile)) {
            return [];
        }

        include $versionFile;
        if (!isset($modversion['templates']) || !is_array($modversion['templates'])) {
            return [];
        }

        return array_values(array_filter(
            $modversion['templates'],
            static function (array $tplfile) use ($type): bool {
                $tplType = $tplfile['type'] ?? 'module';

                return $tplType === $type;
            }
        ));
    }

    private function applyTemplateSet(string $type, string $templateBasePath): bool
    {
        $templates = $this->getSystemTemplatesByType($type);
        if ([] === $templates) {
            return false;
        }

        $dbm  = new Db_manager();
        $time = time();
        foreach ($templates as $tplfile) {
            $fileName = (string) ($tplfile['file'] ?? '');
            if ('' === $fileName) {
                $this->logs[] = sprintf('Missing template file name in system template metadata for %s templates.', $type);

                return false;
            }

            $filePath = $templateBasePath . '/' . $fileName;
            if (!is_readable($filePath)) {
                $this->logs[] = sprintf('Template file is not readable: %s', $fileName);

                return false;
            }

            $tplsource = file_get_contents($filePath);
            if (false === $tplsource) {
                $this->logs[] = sprintf('Failed to read template file: %s', $fileName);

                return false;
            }

            $sql = 'SELECT tf.`tpl_id`, COUNT(ts.`tpl_id`) AS source_count'
                . ' FROM `' . $this->db->prefix('tplfile') . '` tf'
                . ' LEFT JOIN `' . $this->db->prefix('tplsource') . '` ts ON ts.`tpl_id` = tf.`tpl_id`'
                . ' WHERE tf.`tpl_file` = ' . $this->db->quote($fileName)
                . ' AND tf.`tpl_type` = ' . $this->db->quote($type)
                . ' GROUP BY tf.`tpl_id`'
                . ' ORDER BY tf.`tpl_id` ASC LIMIT 1';
            $result = $this->db->query($sql);
            if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
                $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

                return false;
            }

            $existingTemplate = $this->db->fetchRow($result);
            if ($existingTemplate) {
                $tplId = (int) $existingTemplate[0];
                if ((int) $existingTemplate[1] > 0) {
                    continue;
                }

                if (!$dbm->insert(
                    'tplsource',
                    ' (tpl_id, tpl_source) VALUES (' . $tplId . ', ' . $this->db->quote($tplsource) . ')'
                )) {
                    $this->logs[] = sprintf('Failed to backfill tplsource row for %s', $fileName);

                    return false;
                }

                continue;
            }

            $newtplid = $dbm->insert(
                'tplfile',
                " VALUES (0, 1, 'system', 'default', "
                . $this->db->quote($fileName)
                . ', '
                . $this->db->quote((string) ($tplfile['description'] ?? ''))
                . ', '
                . $time
                . ', '
                . $time
                . ', '
                . $this->db->quote($type)
                . ')'
            );
            if (!$newtplid) {
                $this->logs[] = sprintf('Failed to insert tplfile row for %s', $fileName);

                return false;
            }

            if (!$dbm->insert(
                'tplsource',
                ' (tpl_id, tpl_source) VALUES (' . (int) $newtplid . ', ' . $this->db->quote($tplsource) . ')'
            )) {
                $this->logs[] = sprintf('Failed to insert tplsource row for %s', $fileName);

                return false;
            }
        }

        return true;
    }

    private function execOrFail(string $sql): bool
    {
        if ($this->db->exec($sql)) {
            return true;
        }

        $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

        return false;
    }
}

return Upgrade_2511::class;
