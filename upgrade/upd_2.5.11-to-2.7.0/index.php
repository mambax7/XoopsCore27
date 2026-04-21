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
 * 11. normalizeprofilefieldname   — Normalize profile_field.field_name column + unique index (prefix 64)
 * 12. normalizeprofileregstepsort — Normalize profile_regstep.sort index (step_name prefix 100)
 * 13. cleancache             — Clear compiled templates and cache files
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
     * @var string Session key set when apply_normalizeprofilefieldname has
     *             already taken the skip-with-warning path for a widened
     *             column this session. Read by check_ so the patch doesn't
     *             stay permanently "not applied" on widened-column installs.
     */
    protected string $widenedFieldNameSkipKey = 'normalize-profilefield-widened-270';

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
            'normalizeprofilefieldname',
            'normalizeprofileregstepsort',
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
    // Task 11: normalizeprofilefieldname — Normalize profile_field.field_name
    //
    // Older installations have profile_field.field_name as varchar(64) with a
    // plain UNIQUE index on the full column (no explicit prefix). The
    // normalize task enforces the full column and index shape defined by the
    // module's own install SQL:
    //   - column:  varchar(64) NOT NULL DEFAULT ''
    //   - index:   UNIQUE `field_name` (`field_name`(64)) USING BTREE
    // All four column attributes (type, length, nullability, default) are
    // checked and rewritten when any diverge, so the task is truly idempotent
    // and genuinely converges sites to the documented target. A site that has
    // intentionally made the column nullable or changed its default will have
    // those properties reset; existing NULL values would be coerced to '' by
    // the MODIFY and a warning is logged so admins can audit.
    //
    // The explicit (64) prefix standardises DDL across installs so index
    // definitions read identically whether captured from schema dump or ALTER.
    // =========================================================================

    /**
     * Check if profile_field.field_name matches the full canonical shape:
     *   varchar(64) NOT NULL DEFAULT '' AND
     *   UNIQUE `field_name` (`field_name`(64)).
     *
     * Verifies data type, length, nullability, default, and full index
     * layout — not just prefix length — so a non-UNIQUE index, composite
     * index, nullable column, or different default does not incorrectly
     * pass the normalise check. Skipped silently when the table is absent
     * (Profile module not installed).
     *
     * @return bool true if already normalised (no action needed)
     */
    public function check_normalizeprofilefieldname(): bool
    {
        $table = $this->db->prefix('profile_field');

        $exists = $this->tableExists($table);
        if (null === $exists) {
            // Don't silently skip — the DB is in an indeterminate state.
            return false;
        }
        if (!$exists) {
            // Profile module not installed → nothing to normalise; treat as done.
            return true;
        }

        // Column must be varchar(64) NOT NULL DEFAULT ''
        $sql    = "SELECT `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH`, `IS_NULLABLE`, `COLUMN_DEFAULT`"
                . " FROM `information_schema`.`COLUMNS`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND `TABLE_NAME` = " . $this->db->quote($table)
                . " AND `COLUMN_NAME` = 'field_name' LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }
        $row = $this->db->fetchRow($result);

        // Skip-with-warning flag is honoured only while the column is STILL
        // widened AND actual stored data still exceeds 64 chars (the
        // condition that still requires manual action). If the admin
        // narrowed the data or the column in the same session, the flag is
        // stale — clear it and fall through to the normal canonical-shape
        // check so the column/index can still be normalised without waiting
        // for a new session.
        if (!empty($_SESSION[$this->widenedFieldNameSkipKey])) {
            if ($row && (int) $row[1] > 64) {
                $maxStoredLength = $this->getMaxCharLength($table, 'field_name');
                if (null === $maxStoredLength) {
                    return false;
                }
                if ($maxStoredLength > 64) {
                    return true;  // still widened with overlong data — defer to manual action
                }
            }
            unset($_SESSION[$this->widenedFieldNameSkipKey]);
        }

        if (!$row
            || 'varchar' !== strtolower((string) $row[0])
            || 64 !== (int) $row[1]
            || 'NO' !== strtoupper((string) $row[2])
            || '' !== self::normaliseColumnDefault($row[3])  // reject NULL default
        ) {
            return false;
        }

        // Unique index `field_name` must be the sole, single-column entry
        // with SUB_PART = 64 and NON_UNIQUE = 0.
        $sql    = "SELECT `COLUMN_NAME`, `SEQ_IN_INDEX`, `SUB_PART`, `NON_UNIQUE`"
                . " FROM `information_schema`.`STATISTICS`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND `TABLE_NAME` = " . $this->db->quote($table)
                . " AND `INDEX_NAME` = 'field_name'"
                . " ORDER BY `SEQ_IN_INDEX`";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }

        $rows = [];
        while ($row = $this->db->fetchRow($result)) {
            $rows[] = $row;
        }

        // Expect exactly: [('field_name', 1, 64, 0)]
        if (1 !== count($rows)) {
            return false;
        }

        return 'field_name' === $rows[0][0]
            && 1 === (int) $rows[0][1]
            && 64 === (int) $rows[0][2]
            && 0 === (int) $rows[0][3];
    }

    /**
     * Ensure profile_field.field_name is varchar(64) NOT NULL DEFAULT ''
     * and its unique index uses an explicit (64) prefix.
     *
     * Rewrites the column when any of type/length/nullability/default
     * diverge from the target. A divergence on nullability or default is
     * logged explicitly so admins can audit — NULL values are coerced to
     * empty strings by the MODIFY.
     *
     * Skipped silently when the table is absent (Profile module not
     * installed). Skipped with a warning in the log when the column has
     * been widened beyond 64 chars locally — the admin must resolve that
     * manually, but the rest of the 2.7.0 upgrade is not blocked.
     *
     * @return bool true on success, table absent, or skipped with warning
     */
    public function apply_normalizeprofilefieldname(): bool
    {
        $table = $this->db->prefix('profile_field');

        $exists = $this->tableExists($table);
        if (null === $exists) {
            $this->logs[] = sprintf(
                'Cannot verify existence of `%s`; information_schema query failed.',
                $table
            );
            return false;
        }
        if (!$exists) {
            return true;
        }

        // Step 1: Ensure the column is varchar(64) NOT NULL DEFAULT ''.
        $sql    = "SELECT `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH`, `IS_NULLABLE`, `COLUMN_DEFAULT`"
                . " FROM `information_schema`.`COLUMNS`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND `TABLE_NAME` = " . $this->db->quote($table)
                . " AND `COLUMN_NAME` = 'field_name' LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();
            return false;
        }
        $row = $this->db->fetchRow($result);
        if (!$row) {
            $this->logs[] = 'Unable to read profile_field.field_name column metadata';
            return false;
        }
        $dataType       = strtolower((string) $row[0]);
        $currentLen     = (int) $row[1];
        $isNullable     = 'YES' === strtoupper((string) $row[2]);
        $currentDefault = self::normaliseColumnDefault($row[3]);

        // Guard 1: reject unexpected types outright — admin must intervene.
        // Checked before the length guard so a `text` column (length 65535)
        // produces a "type mismatch" diagnostic instead of a misleading
        // "refusing to narrow" message.
        if (!in_array($dataType, ['varchar', 'char'], true)) {
            $this->logs[] = sprintf(
                'profile_field.field_name has unexpected data type `%s`; refusing to convert to varchar(64) automatically.',
                $dataType
            );
            return false;
        }

        // Guard 2: when the column has been widened, inspect actual stored
        // data before deciding whether to skip. A wider declaration alone is
        // not a problem if every value already fits within 64 chars. Only
        // skip when overlong data is present, because narrowing in that case
        // could silently truncate data (if sql_mode lacks STRICT_ALL_TABLES)
        // and recreating the UNIQUE index with prefix(64) would weaken
        // uniqueness from "full width" to "first 64 chars".
        if ($currentLen > 64) {
            $maxStoredLength = $this->getMaxCharLength($table, 'field_name');
            if (null === $maxStoredLength) {
                return false;
            }
            if ($maxStoredLength > 64) {
                $this->logs[] = sprintf(
                    'profile_field.field_name is %s(%d) and contains values up to %d chars; skipping index normalisation. Reduce or rename the overlong values so all fit within 64 chars, then re-run the upgrade.',
                    $dataType,
                    $currentLen,
                    $maxStoredLength
                );
                $_SESSION[$this->widenedFieldNameSkipKey] = true;
                return true;
            }

            $this->logs[] = sprintf(
                'profile_field.field_name is declared as %s(%d), but all stored values fit within 64 chars (max %d); rewriting to varchar(64) NOT NULL DEFAULT \'\'.',
                htmlspecialchars($dataType, ENT_QUOTES, 'UTF-8'),
                $currentLen,
                $maxStoredLength
            );
        }

        // MODIFY if any of the four column attributes diverge from the target:
        //   type = varchar, length = 64, NOT NULL, DEFAULT ''
        $columnDiverges = ('varchar' !== $dataType)
                      || (64 !== $currentLen)
                      || $isNullable
                      || ('' !== $currentDefault);
        if ($columnDiverges) {
            // Guard 3: when the column is nullable and the UNIQUE index still
            // exists, multiple rows may legally share NULL (SQL treats NULL !=
            // NULL for uniqueness). Coercing NULL → '' via MODIFY NOT NULL
            // would collapse them into duplicate '' values and either the
            // MODIFY itself or the later ADD UNIQUE would fail with a low-
            // signal duplicate-key error. Pre-flight a COUNT and refuse with
            // an actionable message when > 1 row would collide.
            if ($isNullable) {
                $countSql = "SELECT COUNT(*) FROM `{$table}`"
                          . " WHERE `field_name` IS NULL OR `field_name` = ''";
                $countResult = $this->db->query($countSql);
                // Treat query failure as a hard error — falling through here
                // would defeat the whole purpose of the pre-flight and let
                // the subsequent MODIFY abort with a low-signal duplicate-key
                // error instead of the actionable message below.
                if (!$this->db->isResultSet($countResult) || !($countResult instanceof \mysqli_result)) {
                    $this->logs[] = \sprintf(_DB_QUERY_ERROR, $countSql) . $this->db->error();
                    return false;
                }
                $countRow = $this->db->fetchRow($countResult);
                $conflictCount = $countRow ? (int) $countRow[0] : 0;
                if ($conflictCount > 1) {
                    $this->logs[] = sprintf(
                        'profile_field has %d rows where field_name is NULL or empty; coercing them via MODIFY ... NOT NULL DEFAULT \'\' would violate the UNIQUE index on field_name. Resolve these rows manually (assign distinct values or delete duplicates) and re-run the upgrade.',
                        $conflictCount
                    );
                    return false;
                }
            }

            // Log the specific divergence so admins can audit what changed —
            // especially important when nullability or default is being
            // rewritten (NULL values are coerced to '' by the MODIFY).
            // Log values are htmlspecialchars-escaped because the upgrader
            // renders $this->logs entries as HTML (joined with <br>).
            if ($isNullable || '' !== $currentDefault) {
                $this->logs[] = sprintf(
                    'profile_field.field_name is %s(%d) %s DEFAULT %s — rewriting to varchar(64) NOT NULL DEFAULT \'\'. Any existing NULL values will be coerced to empty strings.',
                    htmlspecialchars($dataType, ENT_QUOTES, 'UTF-8'),
                    $currentLen,
                    $isNullable ? 'NULL' : 'NOT NULL',
                    null === $currentDefault
                        ? 'NULL'
                        : "'" . htmlspecialchars($currentDefault, ENT_QUOTES, 'UTF-8') . "'"
                );
            }
            $alter = "ALTER TABLE `{$table}` MODIFY `field_name` varchar(64) NOT NULL DEFAULT ''";
            if (!$this->execOrFail($alter)) {
                return false;
            }
        }

        // Step 2: Recreate the unique index with an explicit prefix of 64.
        $hasIndex = $this->indexExists($table, 'field_name');
        if (null === $hasIndex) {
            $this->logs[] = sprintf(
                'Cannot verify existence of index `field_name` on `%s`; information_schema query failed.',
                $table
            );
            return false;
        }
        if ($hasIndex && !$this->execOrFail("ALTER TABLE `{$table}` DROP INDEX `field_name`")) {
            return false;
        }
        $addIndex = "ALTER TABLE `{$table}` ADD UNIQUE `field_name` (`field_name`(64)) USING BTREE";

        return $this->execOrFail($addIndex);
    }

    // =========================================================================
    // Task 12: normalizeprofileregstepsort — Normalize profile_regstep.sort index
    //
    // The historical prefix (100) on step_name is the canonical form. Target:
    //   KEY `sort` (`step_order`, `step_name`(100)) USING BTREE
    // Composite size under utf8mb4: 2 (smallint) + 100*4 = 402 bytes — safe
    // on MyISAM (1000), default InnoDB 5.7 (767), and modern InnoDB (3072).
    // =========================================================================

    /**
     * Check if the `sort` index already uses the canonical layout:
     *   KEY `sort` (`step_order`, `step_name`(100)) — non-UNIQUE.
     *
     * Verifies the full layout (column order, prefix lengths, AND that the
     * index is non-UNIQUE), not just the prefix length on step_name — a
     * partial hand-patch that left step_order out, or upgraded the index
     * to UNIQUE, must still be rebuilt. Skipped silently when the table is
     * absent (Profile module not installed).
     *
     * @return bool true if already normalised (no action needed)
     */
    public function check_normalizeprofileregstepsort(): bool
    {
        $table = $this->db->prefix('profile_regstep');

        $exists = $this->tableExists($table);
        if (null === $exists) {
            return false;
        }
        if (!$exists) {
            return true;
        }

        $sql    = "SELECT `COLUMN_NAME`, `SEQ_IN_INDEX`, `SUB_PART`, `NON_UNIQUE`"
                . " FROM `information_schema`.`STATISTICS`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND `TABLE_NAME` = " . $this->db->quote($table)
                . " AND `INDEX_NAME` = 'sort'"
                . " ORDER BY `SEQ_IN_INDEX`";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return false;
        }

        $rows = [];
        while ($row = $this->db->fetchRow($result)) {
            $rows[] = $row;
        }

        // Expect exactly two non-UNIQUE rows:
        //   [('step_order', 1, NULL, 1), ('step_name', 2, 100, 1)]
        if (2 !== count($rows)) {
            return false;
        }
        if ('step_order' !== $rows[0][0]
            || 1 !== (int) $rows[0][1]
            || null !== $rows[0][2]
            || 1 !== (int) $rows[0][3]
        ) {
            return false;
        }
        if ('step_name' !== $rows[1][0]
            || 2 !== (int) $rows[1][1]
            || 100 !== (int) $rows[1][2]
            || 1 !== (int) $rows[1][3]
        ) {
            return false;
        }

        return true;
    }

    /**
     * Recreate the `sort` index with the canonical layout
     *   (step_order, step_name(100)) USING BTREE.
     *
     * Skipped silently when the table is absent (Profile module not installed).
     *
     * @return bool true on success (or table absent)
     */
    public function apply_normalizeprofileregstepsort(): bool
    {
        $table = $this->db->prefix('profile_regstep');

        $exists = $this->tableExists($table);
        if (null === $exists) {
            $this->logs[] = sprintf(
                'Cannot verify existence of `%s`; information_schema query failed.',
                $table
            );
            return false;
        }
        if (!$exists) {
            return true;
        }

        $hasIndex = $this->indexExists($table, 'sort');
        if (null === $hasIndex) {
            $this->logs[] = sprintf(
                'Cannot verify existence of index `sort` on `%s`; information_schema query failed.',
                $table
            );
            return false;
        }
        if ($hasIndex && !$this->execOrFail("ALTER TABLE `{$table}` DROP INDEX `sort`")) {
            return false;
        }
        $sql = "ALTER TABLE `{$table}` ADD KEY `sort` (`step_order`, `step_name`(100)) USING BTREE";

        return $this->execOrFail($sql);
    }

    // =========================================================================
    // Task 13: cleancache — Clear compiled templates and cache files
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

    /**
     * Get the maximum CHAR_LENGTH currently stored in a character column.
     *
     * Returns null on query failure so callers can abort with the emitted DB
     * error instead of making a schema decision on incomplete information.
     *
     * @param string $table  fully prefixed trusted table name
     * @param string $column trusted column name
     */
    private function getMaxCharLength(string $table, string $column): ?int
    {
        $sql    = "SELECT COALESCE(MAX(CHAR_LENGTH(`{$column}`)), 0) FROM `{$table}`";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();
            return null;
        }

        $row = $this->db->fetchRow($result);

        return $row ? (int) $row[0] : 0;
    }

    /**
     * Normalise an information_schema.COLUMNS.COLUMN_DEFAULT value.
     *
     * MariaDB 10.2.7+ wraps string-literal defaults in single quotes, so a
     * column `DEFAULT ''` comes back as the 2-char string "''". MySQL (all
     * versions) and MariaDB ≤10.2.6 return the bare literal (the empty
     * string). This helper returns the unwrapped literal, or null when no
     * default is set, so downstream callers can compare against the raw
     * target value consistently across MySQL and MariaDB.
     *
     * @param mixed $default raw COLUMN_DEFAULT value as returned by mysqli
     * @return string|null   unwrapped literal, or null when no default set
     */
    private static function normaliseColumnDefault(mixed $default): ?string
    {
        if (null === $default) {
            return null;
        }
        $value = (string) $default;
        if (strlen($value) >= 2
            && str_starts_with($value, "'")
            && str_ends_with($value, "'")
        ) {
            return substr($value, 1, -1);
        }
        return $value;
    }

    /**
     * Check whether a table exists in the current database.
     *
     * Tri-state so callers can distinguish "module not installed" from
     * "we don't know":
     *   true  → table exists
     *   false → table is absent (caller should skip)
     *   null  → information_schema query failed (caller should log + fail,
     *           never silently skip a possibly-present table)
     *
     * @param string $table fully prefixed table name
     */
    private function tableExists(string $table): ?bool
    {
        $sql    = "SELECT 1 FROM `information_schema`.`TABLES`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND `TABLE_NAME` = " . $this->db->quote($table)
                . " LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return null;
        }

        return (bool) $this->db->fetchArray($result);
    }

    /**
     * Check whether an index exists on a table.
     *
     * Tri-state so callers can distinguish "index genuinely absent" from
     * "we don't know" — mirrors tableExists():
     *   true  → index exists
     *   false → index is absent (caller can safely skip a DROP)
     *   null  → information_schema query failed (caller should log + fail
     *           rather than risk an ADD against an index that actually exists)
     *
     * @param string $table     fully prefixed table name
     * @param string $indexName index name (use 'PRIMARY' for the primary key)
     */
    private function indexExists(string $table, string $indexName): ?bool
    {
        $sql    = "SELECT 1 FROM `information_schema`.`STATISTICS`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND `TABLE_NAME` = " . $this->db->quote($table)
                . " AND `INDEX_NAME` = " . $this->db->quote($indexName)
                . " LIMIT 1";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return null;
        }

        return (bool) $this->db->fetchArray($result);
    }
}

return Upgrade_270::class;
