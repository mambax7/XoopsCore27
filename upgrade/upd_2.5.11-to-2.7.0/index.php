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

use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

/**
 * Upgrade from 2.5.11 to 2.7.0
 *
 * Tasks:
 *  1. deletepurifier        — Delete obsolete HTMLPurifier folder from Protector
 *  2. deletephpmailer       — Delete obsolete PHPMailer folder
 *  3. createtokenstable     — Create `tokens` table (InnoDB) for scoped tokens
 *  4. widenbannerclientpasswd — Expand bannerclient.passwd varchar(10) → varchar(255)
 *  5. addsessioncookieprefs  — Add session cookie SameSite/Secure config prefs
 *  6. widenconfid            — Expand config.conf_id + configoption.conf_id smallint → int
 *  7. widenimagename         — Expand image.image_name varchar(30) → varchar(191)
 *  8. cleanuplibraries       — Delete obsolete build artifacts from class/libraries/
 *  9. deletetinymce5nested   — Delete duplicate nested tinymce5/tinymce5/ directory
 * 10. deleteflashsanitizer   — Delete obsolete Flash text sanitizer plugin
 * 11. cleancache             — Clear compiled templates and cache files
 *
 * @category     Upgrade
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package      XOOPS
 * @link         https://xoops.org
 * @since            2.7.0
 * @author           XOOPS Team
 */
class Upgrade_270 extends XoopsUpgrade
{
    /** @var string[] Paths to verify as writable/accessible during pre-flight */
    public array $pathsToCheck = [];

    /** @var string Session key to track cache cleanup completion */
    protected string $cleanCacheKey = 'cache-cleaned-270';

    /**
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = [
            // --- Existing tasks ---
            'deletepurifier',
            'deletephpmailer',
            'createtokenstable',
            'widenbannerclientpasswd',
            'addsessioncookieprefs',
            // --- New tasks for 2.7.0 ---
            'widenconfid',
            'widenimagename',
            'cleanuplibraries',
            'deletetinymce5nested',
            'deleteflashsanitizer',
            'cleancache',
        ];
        $this->usedFiles = [];
        $this->pathsToCheck = [
            XOOPS_ROOT_PATH . '/class/mail/phpmailer',
            XOOPS_TRUST_PATH . '/modules/protector/library',
        ];
        $this->usedFiles = array_merge($this->usedFiles, $this->pathsToCheck);
    }

    // =========================================================================
    // Task 1: deletepurifier — Delete obsolete HTMLPurifier folder
    // =========================================================================

    /**
     * Check if the obsolete HTMLPurifier folder still exists.
     *
     * @return bool true if already gone (no action needed)
     */
    public function check_deletepurifier(): bool
    {
        return !is_dir(XOOPS_TRUST_PATH . '/modules/protector/library/');
    }

    /**
     * Delete obsolete HTMLPurifier folder from Protector module.
     *
     * @return bool true on success
     */
    public function apply_deletepurifier(): bool
    {
        $folderToDelete = XOOPS_TRUST_PATH . '/modules/protector/library/';
        return self::deleteFolder($folderToDelete);
    }

    // =========================================================================
    // Task 2: deletephpmailer — Delete obsolete PHPMailer folder
    // =========================================================================

    /**
     * Check if the obsolete phpmailer folder still exists.
     *
     * @return bool true if already gone (no action needed)
     */
    public function check_deletephpmailer(): bool
    {
        return !is_dir(XOOPS_ROOT_PATH . '/class/mail/phpmailer/');
    }

    /**
     * Delete obsolete phpmailer folder.
     *
     * @return bool true on success
     */
    public function apply_deletephpmailer(): bool
    {
        $folderToDelete = XOOPS_ROOT_PATH . '/class/mail/phpmailer/';
        return self::deleteFolder($folderToDelete);
    }

    // =========================================================================
    // Task 3: createtokenstable — Create tokens table for scoped tokens
    // =========================================================================

    /**
     * Check if the tokens table already exists.
     *
     * @return bool true if table exists (patch applied)
     */
    public function check_createtokenstable(): bool
    {
        $table  = $this->db->prefix('tokens');
        $sql    = "SELECT 1 FROM `information_schema`.`TABLES`"
                . " WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = " . $this->db->quote($table)
                . " LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        return (bool) $this->db->fetchArray($result);
    }

    /**
     * Create the tokens table for generic scoped tokens (lostpass, activation, emailchange).
     *
     * @return bool true on success
     */
    public function apply_createtokenstable(): bool
    {
        $table = $this->db->prefix('tokens');
        $sql   = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `token_id`   int unsigned        NOT NULL AUTO_INCREMENT,
            `uid`        mediumint unsigned  NOT NULL DEFAULT 0,
            `scope`      varchar(32)         NOT NULL DEFAULT '',
            `hash`       char(64)            NOT NULL DEFAULT '',
            `issued_at`  int unsigned        NOT NULL DEFAULT 0,
            `expires_at` int unsigned        NOT NULL DEFAULT 0,
            `used_at`    int unsigned        NOT NULL DEFAULT 0,
            PRIMARY KEY (`token_id`),
            UNIQUE KEY `uq_uid_scope_hash` (`uid`, `scope`, `hash`),
            KEY `idx_uid_scope_issued` (`uid`, `scope`, `issued_at`),
            KEY `idx_issued_at` (`issued_at`)
        ) ENGINE=InnoDB;";

        return $this->execOrFail($sql);
    }

    // =========================================================================
    // Task 4: widenbannerclientpasswd — Expand passwd column for password hashes
    // =========================================================================

    /**
     * Check if bannerclient.passwd column is wide enough for password hashes.
     *
     * @return bool true if column is already >= 255 chars
     */
    public function check_widenbannerclientpasswd(): bool
    {
        $table  = $this->db->prefix('bannerclient');
        $sql    = "SELECT CHARACTER_MAXIMUM_LENGTH FROM `information_schema`.`COLUMNS`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND `TABLE_NAME` = " . $this->db->quote($table)
                . " AND `COLUMN_NAME` = 'passwd' LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        return $row && (int) $row[0] >= 255;
    }

    /**
     * Widen bannerclient.passwd column to accommodate bcrypt/argon2 hashes.
     *
     * @return bool true on success
     */
    public function apply_widenbannerclientpasswd(): bool
    {
        $table = $this->db->prefix('bannerclient');
        $sql   = "ALTER TABLE `{$table}` MODIFY `passwd` varchar(255) NOT NULL DEFAULT ''";

        return $this->execOrFail($sql);
    }

    // =========================================================================
    // Task 5: addsessioncookieprefs — Add session cookie SameSite/Secure prefs
    // =========================================================================

    /**
     * Check if session cookie preferences already exist (config rows + options).
     *
     * @return bool true if fully present (no action needed)
     */
    public function check_addsessioncookieprefs(): bool
    {
        // Check both core config rows exist (scoped to conf_modid=0, conf_catid=1)
        $sql = 'SELECT COUNT(DISTINCT conf_name) FROM `' . $this->db->prefix('config')
            . "` WHERE conf_modid = 0 AND conf_catid = 1"
            . " AND `conf_name` IN ('session_cookie_samesite', 'session_cookie_secure')";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        if (!$row || (int) $row[0] < 2) {
            return false;
        }

        // Check SameSite options exist (Lax, Strict, None)
        $sql = "SELECT COUNT(*) FROM `" . $this->db->prefix('configoption') . "` co"
            . " INNER JOIN `" . $this->db->prefix('config') . "` c ON co.conf_id = c.conf_id"
            . " WHERE c.conf_name = 'session_cookie_samesite' AND c.conf_modid = 0";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        return $row && (int) $row[0] >= 3;
    }

    /**
     * Add session cookie SameSite and Secure preferences (idempotent).
     *
     * @return bool true on success
     */
    public function apply_addsessioncookieprefs(): bool
    {
        $configTable = $this->db->prefix('config');
        $optionTable = $this->db->prefix('configoption');

        // Insert SameSite preference (skip if exists)
        $sql = "SELECT conf_id FROM `{$configTable}` WHERE conf_name = 'session_cookie_samesite' AND conf_modid = 0";
        $result = $this->db->query($sql);
        $sameSiteRow = ($this->db->isResultSet($result) && ($result instanceof \mysqli_result))
            ? $this->db->fetchRow($result)
            : false;

        if (!$sameSiteRow) {
            $sql = "INSERT INTO `{$configTable}` (conf_modid, conf_catid, conf_name, conf_title, conf_value, conf_desc, conf_formtype, conf_valuetype, conf_order) VALUES (0, 1, 'session_cookie_samesite', '_MD_AM_SESSSAMESITE', 'Lax', '_MD_AM_SESSSAMESITE_DSC', 'select', 'text', 43)";
            if (!$this->execOrFail($sql)) {
                return false;
            }
            // Re-fetch the conf_id
            $sql    = "SELECT conf_id FROM `{$configTable}` WHERE conf_name = 'session_cookie_samesite' AND conf_modid = 0";
            $result = $this->db->query($sql);
            if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
                $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

                return false;
            }
            $sameSiteRow = $this->db->fetchRow($result);
            if (!$sameSiteRow) {
                $this->logs[] = 'Failed to retrieve session_cookie_samesite conf_id after insert';

                return false;
            }
        }

        // Insert Secure preference (skip if exists)
        $sql    = "SELECT conf_id FROM `{$configTable}` WHERE conf_name = 'session_cookie_secure' AND conf_modid = 0";
        $result = $this->db->query($sql);
        $secureRow = ($this->db->isResultSet($result) && ($result instanceof \mysqli_result))
            ? $this->db->fetchRow($result)
            : false;

        if (!$secureRow) {
            $sql = "INSERT INTO `{$configTable}` (conf_modid, conf_catid, conf_name, conf_title, conf_value, conf_desc, conf_formtype, conf_valuetype, conf_order) VALUES (0, 1, 'session_cookie_secure', '_MD_AM_SESSSECURE', '0', '_MD_AM_SESSSECURE_DSC', 'yesno', 'int', 44)";
            if (!$this->execOrFail($sql)) {
                return false;
            }
        }

        // Add select options for SameSite — delete and recreate to avoid duplicates
        $confId = (int) $sameSiteRow[0];
        if (!$this->execOrFail("DELETE FROM `{$optionTable}` WHERE conf_id = {$confId}")) {
            return false;
        }
        foreach (['Lax', 'Strict', 'None'] as $opt) {
            if (!$this->execOrFail("INSERT INTO `{$optionTable}` (confop_name, confop_value, conf_id) VALUES ('{$opt}', '{$opt}', {$confId})")) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // Task 6: widenconfid — Expand config.conf_id + configoption.conf_id
    //         from smallint(5) unsigned to int(10) unsigned
    // =========================================================================

    /**
     * Check if config.conf_id AND configoption.conf_id are already int (not smallint).
     *
     * Both tables must be widened for the upgrade to be considered complete.
     * Checking only config.conf_id would leave configoption.conf_id as smallint
     * if the 2.5.11 patch had already widened config.conf_id.
     *
     * @return bool true if both tables are already widened (no action needed)
     */
    public function check_widenconfid(): bool
    {
        // Check config.conf_id (parent PK)
        $configTable = $this->db->prefix('config');
        $sql = "SELECT DATA_TYPE FROM `information_schema`.`COLUMNS`"
             . " WHERE `TABLE_SCHEMA` = DATABASE()"
             . " AND `TABLE_NAME` = " . $this->db->quote($configTable)
             . " AND `COLUMN_NAME` = 'conf_id' LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        if (!$row || 'int' !== $row[0]) {
            return false;
        }

        // Check configoption.conf_id (FK child) — must ALSO be int
        $optionTable = $this->db->prefix('configoption');
        $sql = "SELECT DATA_TYPE FROM `information_schema`.`COLUMNS`"
             . " WHERE `TABLE_SCHEMA` = DATABASE()"
             . " AND `TABLE_NAME` = " . $this->db->quote($optionTable)
             . " AND `COLUMN_NAME` = 'conf_id' LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        return $row && 'int' === $row[0];
    }

    /**
     * Widen config.conf_id and configoption.conf_id from smallint to int.
     *
     * Order: configoption (FK child) first, then config (PK parent).
     * No formal FK constraint exists (MyISAM legacy), but widening the
     * child first is safest practice.
     *
     * @return bool true on success
     */
    public function apply_widenconfid(): bool
    {
        // Step 1: Widen the FK child column first
        $optionTable = $this->db->prefix('configoption');
        $sql         = "ALTER TABLE `{$optionTable}` MODIFY `conf_id` int unsigned NOT NULL DEFAULT 0";
        if (!$this->execOrFail($sql)) {
            return false;
        }

        // Step 2: Widen the PK parent column
        $configTable = $this->db->prefix('config');
        $sql         = "ALTER TABLE `{$configTable}` MODIFY `conf_id` int unsigned NOT NULL AUTO_INCREMENT";

        return $this->execOrFail($sql);
    }

    // =========================================================================
    // Task 7: widenimagename — Expand image.image_name varchar(30) → varchar(191)
    // =========================================================================

    /**
     * Check if image.image_name is already wide enough (>= 191).
     *
     * @return bool true if already widened (no action needed)
     */
    public function check_widenimagename(): bool
    {
        $table  = $this->db->prefix('image');
        $sql    = "SELECT CHARACTER_MAXIMUM_LENGTH FROM `information_schema`.`COLUMNS`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND `TABLE_NAME` = " . $this->db->quote($table)
                . " AND `COLUMN_NAME` = 'image_name' LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);
        return $row && (int) $row[0] >= 191;
    }

    /**
     * Widen image.image_name to varchar(191) for longer filenames and UTF-8mb4 safety.
     *
     * @return bool true on success
     */
    public function apply_widenimagename(): bool
    {
        $table = $this->db->prefix('image');
        $sql   = "ALTER TABLE `{$table}` MODIFY `image_name` varchar(191) NOT NULL DEFAULT ''";

        return $this->execOrFail($sql);
    }

    // =========================================================================
    // Task 8: cleanuplibraries — Delete obsolete build artifacts from class/libraries/
    //
    // In 2.5.11, class/libraries/ contained build tooling and vendor packages
    // that have been relocated to xoops_lib/ in 2.7.0. The entire
    // class/libraries/ tree is now obsolete and should be removed.
    // =========================================================================

    /** @var string[] Top-level items under class/libraries/ to delete */
    private array $librariesTopLevel = [
        'README.md',
        'build',
        'composer.dist.json',
        'composer.dist.lock',
        'local',
        'patches',
    ];

    /** @var string[] Obsolete vendor subdirectories/files under class/libraries/vendor/ */
    private array $librariesObsoleteVendors = [
        'autoload.php',
        'bin',
        'boenrobot',
        'composer',
        'firebase',
        'geekwright',
        'ircmaxell',
        'kint-php',
        'paragonie',
        'smarty',
        'smottt',
        'symfony',
        'webmozart',
        'xoops',
    ];

    /**
     * Check if class/libraries/ has already been cleaned up.
     *
     * Uses the presence of README.md as the sentinel — it is always present
     * in a pre-cleanup 2.5.11 installation and never present in 2.7.0.
     *
     * @return bool true if already cleaned up (no action needed)
     */
    public function check_cleanuplibraries(): bool
    {
        $basePath = XOOPS_ROOT_PATH . '/class/libraries/';
        foreach ($this->librariesTopLevel as $item) {
            if (file_exists($basePath . $item)) {
                return false;
            }
        }

        $vendorPath = $basePath . 'vendor/';
        foreach ($this->librariesObsoleteVendors as $item) {
            if (file_exists($vendorPath . $item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Delete obsolete files and directories from class/libraries/.
     *
     * All vendor packages have been relocated to xoops_lib/vendor/.
     * The entire class/libraries/ tree is removed, including vendor/composer/
     * and vendor/firebase/ which are now redundant.
     *
     * @return bool true on success (best-effort — continues on individual item failure)
     */
    public function apply_cleanuplibraries(): bool
    {
        $basePath   = XOOPS_ROOT_PATH . '/class/libraries/';
        $vendorPath = $basePath . 'vendor/';
        $success    = true;

        // Delete top-level obsolete items
        foreach ($this->librariesTopLevel as $item) {
            $path = $basePath . $item;
            if (is_dir($path)) {
                if (!self::deleteFolder($path)) {
                    $this->logs[] = sprintf('Failed to delete directory: %s', $this->relativePath($path));
                    $success = false;
                }
            } elseif (is_file($path)) {
                if (!unlink($path)) {
                    $this->logs[] = sprintf('Failed to delete file: %s', $this->relativePath($path));
                    $success = false;
                }
            }
            // Item doesn't exist — already cleaned, skip silently
        }

        // Delete obsolete vendor subdirectories/files
        foreach ($this->librariesObsoleteVendors as $item) {
            $path = $vendorPath . $item;
            if (is_dir($path)) {
                if (!self::deleteFolder($path)) {
                    $this->logs[] = sprintf('Failed to delete vendor directory: %s', $this->relativePath($path));
                    $success = false;
                }
            } elseif (is_file($path)) {
                if (!unlink($path)) {
                    $this->logs[] = sprintf('Failed to delete vendor file: %s', $this->relativePath($path));
                    $success = false;
                }
            }
        }

        // Remove the now-empty vendor/ and class/libraries/ directories
        if ($success) {
            if (is_dir($vendorPath)) {
                @rmdir($vendorPath);
            }
            if (is_dir($basePath)) {
                @rmdir($basePath);
            }
        }

        return $success;
    }

    // =========================================================================
    // Task 9: deletetinymce5nested — Delete duplicate nested tinymce5/ directory
    //
    // A duplicate nested tinymce5/ directory inside the TinyMCE 5 editor plugin.
    // Present in 2.5.11, removed in 2.7.0.
    // =========================================================================

    /**
     * Check if the nested tinymce5/tinymce5/ directory still exists.
     *
     * @return bool true if already gone (no action needed)
     */
    public function check_deletetinymce5nested(): bool
    {
        return !is_dir(XOOPS_ROOT_PATH . '/class/xoopseditor/tinymce5/tinymce5/');
    }

    /**
     * Delete the duplicate nested tinymce5/ directory.
     *
     * @return bool true on success
     */
    public function apply_deletetinymce5nested(): bool
    {
        $path = XOOPS_ROOT_PATH . '/class/xoopseditor/tinymce5/tinymce5/';
        if (!self::deleteFolder($path)) {
            $this->logs[] = sprintf('Failed to delete nested tinymce5 directory: %s', $this->relativePath($path));
            return false;
        }
        return true;
    }

    // =========================================================================
    // Task 10: deleteflashsanitizer — Delete obsolete Flash text sanitizer
    //
    // Flash Player has been EOL since December 2020. The Flash text sanitizer
    // plugin is present in 2.5.11 but removed in 2.7.0.
    // =========================================================================

    /**
     * Check if the Flash text sanitizer directory still exists.
     *
     * @return bool true if already gone (no action needed)
     */
    public function check_deleteflashsanitizer(): bool
    {
        return !is_dir(XOOPS_ROOT_PATH . '/class/textsanitizer/flash/');
    }

    /**
     * Delete the obsolete Flash text sanitizer plugin.
     *
     * @return bool true on success
     */
    public function apply_deleteflashsanitizer(): bool
    {
        $path = XOOPS_ROOT_PATH . '/class/textsanitizer/flash/';
        if (!self::deleteFolder($path)) {
            $this->logs[] = sprintf('Failed to delete Flash sanitizer directory: %s', $this->relativePath($path));
            return false;
        }
        return true;
    }

    // =========================================================================
    // Task 11: cleancache — Clear compiled templates and cache files
    // =========================================================================

    /**
     * Check if cache has already been cleaned during this upgrade session.
     *
     * @return bool true if cache was already cleaned (no action needed)
     */
    public function check_cleancache(): bool
    {
        return isset($_SESSION[$this->cleanCacheKey])
            && true === $_SESSION[$this->cleanCacheKey];
    }

    /**
     * Clear compiled Smarty templates and module caches.
     *
     * Uses SystemMaintenance::CleanCache() with folder IDs:
     *   1 = Smarty cache, 2 = compiled templates, 3 = xoops_cache
     *
     * @return bool true on success
     */
    public function apply_cleancache(): bool
    {
        require_once XOOPS_ROOT_PATH . '/modules/system/class/maintenance.php';
        try {
            $maintenance = new \SystemMaintenance();
            $result = $maintenance->CleanCache([1, 2, 3]);
            if (true === $result) {
                $_SESSION[$this->cleanCacheKey] = true;
            }
            return $result;
        } catch (\Throwable $e) {
            $this->logs[] = 'Failed to clean cache: ' . $this->sanitizeLogMessage($e->getMessage());

            return false;
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param string $folderPath absolute path to the directory to delete
     *
     * @return bool true if the directory was fully removed
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
        if (false === $resolvedRoot || !self::isAllowedDeleteRoot($resolvedRoot)) {
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

    private static function isAllowedDeleteRoot(string $path): bool
    {
        foreach ([XOOPS_ROOT_PATH, XOOPS_TRUST_PATH] as $basePath) {
            $resolvedBase = realpath($basePath);
            if (false === $resolvedBase) {
                continue;
            }

            // Only allow subpaths — never the base directory itself
            $prefix = rtrim($resolvedBase, '\\/') . DIRECTORY_SEPARATOR;
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $path): string
    {
        $rootWithSeparator = rtrim(XOOPS_ROOT_PATH, '\\/') . DIRECTORY_SEPARATOR;
        $trustWithSeparator = rtrim(XOOPS_TRUST_PATH, '\\/') . DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $rootWithSeparator)) {
            return str_replace('\\', '/', substr($path, strlen($rootWithSeparator)));
        }

        if (str_starts_with($path, $trustWithSeparator)) {
            return 'xoops_trust_path/' . str_replace('\\', '/', substr($path, strlen($trustWithSeparator)));
        }

        return basename($path);
    }

    private function sanitizeLogMessage(string $message): string
    {
        return (string) preg_replace_callback(
            '/([A-Za-z]:)?[\\\\\\/][^\\s]*/',
            static function (array $matches): string {
                return basename(str_replace('\\', '/', $matches[0]));
            },
            $message
        );
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

return Upgrade_270::class;
