<?php
/**
 * Maintenance class manager
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
 * @package             system
 */

// Direct-access guard: this file is module/admin-only and must never
// execute outside a bootstrapped XOOPS context. Without this, the
// require_once below would fail with an "undefined constant" fatal
// when the file is hit via a direct URL, leaking server path details
// in the error message. Uses the project-standard one-liner shape
// for consistency with the rest of the codebase.
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

// xoops_remove_file_quietly() and friends live in include/file_safety.php
// — a deliberately side-effect-free file. Earlier revisions required
// include/cp_functions.php here, which defines XOOPS_CPFUNC_LOADED;
// include/functions.php keys off that constant to force redirect_header()
// into the 'default' theme. SystemMaintenance is also instantiated from
// upgrade/upd_2.5.10-to-2.5.11/index.php and upgrade/upd_2.5.11-to-2.7.0/
// index.php, so loading cp_functions.php at class-file-load time was
// silently overriding the configured theme during upgrades. Loading just
// the helpers avoids that side effect; CP and admin callers still see
// XOOPS_CPFUNC_LOADED via their own cp_header.php / page_moduleinstaller.php
// load paths.
require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

/**
 * System Maintenance
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @package             system
 */
class SystemMaintenance
{
    public $db;
    public $prefix;

    /**
     * Cached list of valid table names (without prefix).
     *
     * @var array|null
     */
    private $validTables = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        /** @var XoopsMySQLDatabase $db */
        $db           = XoopsDatabaseFactory::getDatabaseConnection();
        $this->db     = $db;
        $this->prefix = $this->db->prefix . '_';
    }

    /**
     * Validate that a table name (without prefix) exists in the database.
     *
     * Prevents SQL injection by checking the user-supplied table name
     * against the actual list of tables returned by SHOW TABLES.
     *
     * @param string $table Table name without prefix
     *
     * @return bool True if the table exists in the database
     */
    private function isValidTable($table)
    {
        if (!is_string($table)) {
            return false;
        }

        if ($this->validTables === null) {
            $tables = $this->displayTables(true);
            $this->validTables = is_array($tables) ? $tables : [];
        }

        return isset($this->validTables[$table]);
    }

    /**
     * Validate that a prefixed table name exists in the database.
     *
     * @param string $prefixedTable Full table name including prefix
     *
     * @return bool True if the table exists in the database
     */
    private function isValidPrefixedTable($prefixedTable)
    {
        if (!is_string($prefixedTable)) {
            return false;
        }

        $prefixLen = strlen($this->prefix);

        // Verify the table actually starts with the expected prefix
        if (strncmp($prefixedTable, $this->prefix, $prefixLen) !== 0) {
            return false;
        }

        // Strip the prefix to get the unprefixed name
        $unprefixed = substr($prefixedTable, $prefixLen);

        // Ensure the unprefixed remainder is non-empty
        if ($unprefixed === '' || $unprefixed === false) {
            return false;
        }

        return $this->isValidTable($unprefixed);
    }

    /**
     * Display Tables
     *
     * @param bool $array
     *
     * @internal param $array
     * @return array|string
     */
    public function displayTables($array = true)
    {
        $tables = [];
        $sql = 'SHOW TABLES';
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        while (false !== ($myrow = $this->db->fetchArray($result))) {
            $value          = array_values($myrow);
            $value          = substr((string) $value[0], strlen(XOOPS_DB_PREFIX) + 1);
            $tables[$value] = $value;
        }
        if (true === (bool) $array) {
            return $tables;
        } else {
            return implode(',', $tables);
        }
    }

    /**
     * Clear sessions
     *
     * @return bool
     */
    public function CleanSession()
    {
        $result = $this->db->exec('TRUNCATE TABLE ' . $this->db->prefix('session'));

        return true;
    }

    /**
     * CleanAvatar
     *
     * Clean up orphaned custom avatars left when a user is deleted.
     *
     * @author slider84 of Team FrXoops
     *
     * @return bool
     *
     * @throws \RuntimeException If the avatar table query fails.
     */
    public function CleanAvatar()
    {
        $sql = 'SELECT avatar_id, avatar_file FROM ' . $this->db->prefix('avatar') . " WHERE avatar_type='C' AND avatar_id IN (" . 'SELECT t1.avatar_id FROM ' . $this->db->prefix('avatar_user_link') . ' AS t1 ' . 'LEFT JOIN ' . $this->db->prefix('users') . ' AS t2 ON t2.uid=t1.user_id ' . 'WHERE t2.uid IS NULL)';
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }

        // Resolve the avatars subdirectory once for the whole sweep.
        // Custom avatars live under XOOPS_UPLOAD_PATH/avatars/ (see
        // kernel/avatar.php and the various admin/edituser writers, all
        // of which prepend 'avatars/' to the stored filename). Narrowing
        // the containment check to this subtree is defence-in-depth: an
        // avatar_file value that points elsewhere under uploads/ —
        // legacy data, custom-module write, or accidental insertion —
        // is now skipped instead of silently deleting an unrelated
        // upload. realpath() is constant for the run, so per-row
        // resolution would just be wasted filesystem work on
        // installations with large orphaned-avatar tables.
        $avatarRootPath   = XOOPS_UPLOAD_PATH . '/avatars';
        $avatarRootPrefix = is_dir($avatarRootPath)
            ? rtrim((string) realpath($avatarRootPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            : '';

        // Track whether every DELETE in the sweep succeeded. The method
        // contract is `@return bool`, but the previous implementation
        // unconditionally returned true even if a DELETE failed and an
        // orphaned row was left behind. Callers can now distinguish a
        // clean sweep from a partial one.
        $deleteOk = true;

        /** @var array $myrow */
        while (false !== ($myrow = $this->db->fetchArray($result))) {
            // Avatar files are stored as 'avatars/<filename>'
            // (kernel/avatar.php and 14 admin/edituser writers), so
            // basename() would silently bypass cleanup. Instead:
            //   - normalise backslashes to '/' (Windows-historic data)
            //   - strip leading slashes (defends against absolute paths
            //     accidentally or maliciously stored in the column)
            //   - resolve the parent directory via realpath() so any
            //     '../' segments collapse, and confirm the parent is
            //     inside the resolved avatars/ subdir (trailing-
            //     separator prefix so 'avatarsX/...' doesn't satisfy
            //     'avatars')
            //   - confirm the candidate is a regular file OR a symlink
            //     before removal.
            // Then invoke the cleanup helper on the ORIGINAL candidate
            // path (not the realpath result). This matters when the
            // avatar entry is a symlink: realpath() resolves to the
            // symlink target, so unlinking the resolved path would
            // delete the target file (potentially another avatar)
            // instead of the symlink itself. Resolving the PARENT only
            // keeps the containment check honest without following the
            // symlink into the wrong file.
            //
            // (int) cast on avatar_id is defence-in-depth: the value is
            // DB-origin so SQL injection is implausible, but the project
            // convention is to never concatenate non-cast values into
            // SQL strings. The cast also silences SonarCloud's
            // concatenation warning on these DELETE statements.
            $avatarId   = (int) ($myrow['avatar_id'] ?? 0);
            $avatarFile = ltrim(str_replace('\\', '/', (string) ($myrow['avatar_file'] ?? '')), '/');
            // Reject null-byte payloads BEFORE any filesystem call:
            // dirname(), realpath(), is_file(), and is_link() all raise
            // ValueError on PHP 8+ when the argument contains "\0".
            // Letting that propagate would abort the sweep mid-loop and
            // skip the avatar_user_link cleanup that follows. The DB
            // row is still deleted unconditionally below — a malformed
            // path should never block reclaiming the orphaned row.
            if ('' !== $avatarFile && !str_contains($avatarFile, "\0")) {
                $avatarCandidate = XOOPS_UPLOAD_PATH . '/' . $avatarFile;
                $avatarParent    = realpath(dirname($avatarCandidate));
                if (
                    is_string($avatarParent)
                    && '' !== $avatarRootPrefix
                    && $avatarRootPrefix !== DIRECTORY_SEPARATOR
                    && str_starts_with(rtrim($avatarParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $avatarRootPrefix)
                    && (is_file($avatarCandidate) || is_link($avatarCandidate))
                ) {
                    xoops_remove_file_quietly($avatarCandidate, 'orphaned avatar');
                }
            }
            //clean avatar table
            if (!$this->db->exec('DELETE FROM ' . $this->db->prefix('avatar') . ' WHERE avatar_id=' . $avatarId)) {
                $deleteOk = false;
            }
        }
        //clean any deleted users from avatar_user_link table
        if (!$this->db->exec('DELETE FROM ' . $this->db->prefix('avatar_user_link') . ' WHERE user_id NOT IN (SELECT uid FROM ' . $this->db->prefix('users') . ')')) {
            $deleteOk = false;
        }

        return $deleteOk;
    }

    /**
     * Build a non-sensitive path label for warning messages.
     *
     * @param string $filename
     *
     * @return string
     */
    private function getWarningPathLabel($filename)
    {
        $normalized = str_replace('\\', '/', $filename);
        $rootPrefix = rtrim(str_replace('\\', '/', XOOPS_ROOT_PATH), '/') . '/';

        if (strncmp($normalized, $rootPrefix, strlen($rootPrefix)) === 0) {
            return substr($normalized, strlen($rootPrefix));
        }

        return basename($filename);
    }

    /**
     * Write a file atomically and warn on failure or short write.
     *
     * @param string $filename
     * @param string $content
     *
     * @return void
     */
    private function writeFileWithWarning($filename, $content)
    {
        $label    = $this->getWarningPathLabel($filename);
        $expected = strlen($content);
        $tempFile = tempnam(dirname($filename), 'mtn');

        if ($tempFile === false) {
            trigger_error(sprintf('Failed to create temp file for %s', $label), E_USER_WARNING);

            return;
        }

        $result = file_put_contents($tempFile, $content, LOCK_EX);

        if ($result === false) {
            xoops_remove_file_quietly($tempFile, 'temp guard');
            trigger_error(sprintf('Failed to write guard file: %s', $label), E_USER_WARNING);
        } elseif ($result !== $expected) {
            xoops_remove_file_quietly($tempFile, 'temp guard');
            trigger_error(
                sprintf(
                    'Short write for guard file %s: wrote %d of %d bytes',
                    $label,
                    $result,
                    $expected
                ),
                E_USER_WARNING
            );
        } else {
            $targetPerms = 0644;
            if (file_exists($filename)) {
                $currentPerms = fileperms($filename);
                if ($currentPerms !== false) {
                    $targetPerms = $currentPerms & 0777;
                }
            }
            // Non-fatal: content is written, only the perms may not
            // take. Continue with the rename rather than aborting. The
            // helper suppresses the native PHP warning so a single
            // failure produces a single project-standard log line.
            xoops_chmod_quietly($tempFile, $targetPerms, 'temp guard');

            // The @rename(...) calls below are inside `if (!...)` checks —
            // failure is detected by the boolean return and reported via
            // trigger_error(). The `@` is retained to suppress PHP's
            // native warning, which would otherwise double-report
            // alongside our own diagnostic.
            $backupFile = null;
            if (file_exists($filename)) {
                $backupFile = tempnam(dirname($filename), 'mtb');
                if ($backupFile === false) {
                    xoops_remove_file_quietly($tempFile, 'temp guard');
                    trigger_error(sprintf('Failed to create backup file for %s', $label), E_USER_WARNING);

                    return;
                }
                // tempnam() created a 0-byte placeholder; remove it so
                // the rename below can take its slot.
                xoops_remove_file_quietly($backupFile, 'backup guard');
                if (!@rename($filename, $backupFile)) {
                    xoops_remove_file_quietly($tempFile, 'temp guard');
                    trigger_error(sprintf('Failed to back up guard file: %s', $label), E_USER_WARNING);

                    return;
                }
            }

            if (!@rename($tempFile, $filename)) {
                xoops_remove_file_quietly($tempFile, 'temp guard');
                // Track whether the backup-restore step succeeded so the
                // composite failure warning can communicate that the
                // original guard file is intact (vs. the worse case
                // where both replace and restore failed and manual
                // intervention may be required).
                $restoredBackup = false;
                if ($backupFile !== null) {
                    if (!@rename($backupFile, $filename)) {
                        trigger_error(sprintf('Failed to restore original guard file: %s', $label), E_USER_WARNING);
                    } else {
                        $restoredBackup = true;
                    }
                }

                trigger_error(
                    sprintf(
                        'Failed to replace guard file: %s%s',
                        $label,
                        $restoredBackup ? ' (original restored)' : ''
                    ),
                    E_USER_WARNING
                );
            } elseif ($backupFile !== null) {
                xoops_remove_file_quietly($backupFile, 'backup guard');
            }
        }
    }

    /**
     * Delete all files in a directory
     *
     * @param string $dir directory to clear
     *
     * @return void
     */
    protected function clearDirectory($dir)
    {
        if (is_dir($dir)) {
            if ($dirHandle = opendir($dir)) {
                while (($file = readdir($dirHandle)) !== false) {
                    if (filetype($dir . $file) === 'file') {
                        unlink($dir . $file);
                    }
                }
                closedir($dirHandle);
            }
            $guardFile = $dir . 'index.php';
            $content   = '<?php' . PHP_EOL . "http_response_code(404);" . PHP_EOL . 'exit;' . PHP_EOL;
            $this->writeFileWithWarning($guardFile, $content);
        }
    }

    /**
     * Clean cache 'xoops_data/caches/smarty_cache'
     *
     * @param array $cacheList int[] of cache "ids"
     *                         1 = smarty cache
     *                         2 = smarty compile
     *                         3 = xoops cache
     * @return bool
     */
    public function CleanCache($cacheList)
    {
        foreach ($cacheList as $cache) {
            switch ($cache) {
                case 1:
                    $this->clearDirectory(XOOPS_VAR_PATH . '/caches/smarty_cache/');
                    break;
                case 2:
                    $this->clearDirectory(XOOPS_VAR_PATH . '/caches/smarty_compile/');
                    break;
                case 3:
                    $this->clearDirectory(XOOPS_VAR_PATH . '/caches/xoops_cache/');
                    break;
                default:
                    return false;
            }
        }

        return true;
    }

    /**
     * Maintenance database
     *
     * @param array tables 'list of tables'
     * @param array maintenance 'optimize, check, repair, analyze'
     * @return array
     */
    public function CheckRepairAnalyzeOptimizeQueries($tables, $maintenance)
    {
        $ret = '<table class="outer"><th>' . _AM_SYSTEM_MAINTENANCE_TABLES1 . '</th><th>' . _AM_SYSTEM_MAINTENANCE_TABLES_OPTIMIZE . '</th><th>' . _AM_SYSTEM_MAINTENANCE_TABLES_CHECK . '</th><th>' . _AM_SYSTEM_MAINTENANCE_TABLES_REPAIR . '</th><th>' . _AM_SYSTEM_MAINTENANCE_TABLES_ANALYZE . '</th>';
        $tab = [];
        for ($i = 0; $i < 4; ++$i) {
            $tab[$i] = $i + 1;
        }
        $tab1 = [];
        for ($i = 0; $i < 4; ++$i) {
            if (in_array($tab[$i], $maintenance)) {
                $tab1[$i] = $tab[$i];
            } else {
                $tab1[$i] = '0';
            }
        }
        unset($tab);
        $class       = 'odd';
        $tablesCount = count($tables);
        for ($i = 0; $i < $tablesCount; ++$i) {
            if (!$this->isValidTable($tables[$i])) {
                continue;
            }
            $ret .= '<tr class="' . $class . '"><td align="center">' . $this->prefix . $tables[$i] . '</td>';
            for ($j = 0; $j < 4; ++$j) {
                if ($tab1[$j] == 1) {
                    // Optimize
                    $result = $this->db->exec('OPTIMIZE TABLE ' . $this->prefix . $tables[$i]);
                    if ($result) {
                        $ret .= '<td class="xo-actions txtcenter"><img src="' . system_AdminIcons('success.png') . '" /></td>';
                    } else {
                        $ret .= '<td class="xo-actions txtcenter"><img src="' . system_AdminIcons('cancel.png') . '" /></td>';
                    }
                } elseif ($tab1[$j] == 2) {
                    // Check tables
                    $result = $this->db->exec('CHECK TABLE ' . $this->prefix . $tables[$i]);
                    if ($result) {
                        $ret .= '<td class="xo-actions txtcenter"><img src="' . system_AdminIcons('success.png') . '" /></td>';
                    } else {
                        $ret .= '<td class="xo-actions txtcenter"><img src="' . system_AdminIcons('cancel.png') . '" /></td>';
                    }
                } elseif ($tab1[$j] == 3) {
                    // Repair
                    $result = $this->db->exec('REPAIR TABLE ' . $this->prefix . $tables[$i]);
                    if ($result) {
                        $ret .= '<td class="xo-actions txtcenter"><img src="' . system_AdminIcons('success.png') . '" /></td>';
                    } else {
                        $ret .= '<td class="xo-actions txtcenter"><img src="' . system_AdminIcons('cancel.png') . '" /></td>';
                    }
                } elseif ($tab1[$j] == 4) {
                    // Analyze
                    $result = $this->db->exec('ANALYZE TABLE ' . $this->prefix . $tables[$i]);
                    if ($result) {
                        $ret .= '<td class="xo-actions txtcenter"><img src="' . system_AdminIcons('success.png') . '" /></td>';
                    } else {
                        $ret .= '<td class="xo-actions txtcenter"><img src="' . system_AdminIcons('cancel.png') . '" /></td>';
                    }
                } else {
                    $ret .= '<td>&nbsp;</td>';
                }
            }
            $ret .= '</tr>';
            $class = ($class === 'even') ? 'odd' : 'even';
        }
        $ret .= '</table>';

        return $ret;
    }

    /**
     * Dump by tables
     *
     * @param array tables 'list of tables'
     * @param int   drop
     * @return array 'ret[0] = dump, ret[1] = display result
     */
    public function dump_tables($tables, $drop)
    {
        $ret    = [];
        $ret[0] = "# \n";
        $ret[0] .= "# Dump SQL, Generate by Xoops \n";
        $ret[0] .= '# Date : ' . date('d-m-Y - H:i') . " \n";
        $ret[1]      = '<table class="outer"><tr><th width="30%">' . _AM_SYSTEM_MAINTENANCE_DUMP_TABLES . '</th><th width="35%">' . _AM_SYSTEM_MAINTENANCE_DUMP_STRUCTURES . '</th><th  width="35%">' . _AM_SYSTEM_MAINTENANCE_DUMP_NB_RECORDS . '</th></tr>';
        $class       = 'odd';
        $tablesCount = count($tables);
        for ($i = 0; $i < $tablesCount; ++$i) {
            if (!$this->isValidTable($tables[$i])) {
                continue;
            }
            //structure
            $ret = $this->dump_table_structure($ret, $this->prefix . $tables[$i], $drop, $class);
            //data
            $ret   = $this->dump_table_datas($ret, $this->prefix . $tables[$i]);
            $class = ($class === 'even') ? 'odd' : 'even';
        }
        $ret = $this->dump_write($ret);
        $ret[1] .= '</table>';

        return $ret;
    }

    /**
     * Dump by modules
     *
     * @param array modules 'list of modules'
     * @param int   drop
     * @return array 'ret[0] = dump, ret[1] = display result
     */
    public function dump_modules($modules, $drop)
    {
        $ret    = [];
        $ret[0] = "# \n";
        $ret[0] .= "# Dump SQL, Generate by Xoops \n";
        $ret[0] .= '# Date : ' . date('d-m-Y - H:i') . " \n";
        $ret[0] .= "# \n\n";
        $ret[1]       = '<table class="outer"><tr><th width="30%">' . _AM_SYSTEM_MAINTENANCE_DUMP_TABLES . '</th><th width="35%">' . _AM_SYSTEM_MAINTENANCE_DUMP_STRUCTURES . '</th><th  width="35%">' . _AM_SYSTEM_MAINTENANCE_DUMP_NB_RECORDS . '</th></tr>';
        $class        = 'odd';
        $modulesCount = count($modules);
        for ($i = 0; $i < $modulesCount; ++$i) {
            /** @var XoopsModuleHandler $module_handler */
            $module_handler = xoops_getHandler('module');
            $module         = $module_handler->getByDirname($modules[$i]);
            $ret[1] .= '<tr><th colspan="3" align="left">' . ucfirst((string) $modules[$i]) . '</th></tr>';
            $modtables = $module->getInfo('tables');
            if ($modtables !== false && \is_array($modtables)) {
                foreach ($modtables as $table) {
                    if (!$this->isValidTable($table)) {
                        continue;
                    }
                    //structure
                    $ret = $this->dump_table_structure($ret, $this->prefix . $table, $drop, $class);
                    //data
                    $ret   = $this->dump_table_datas($ret, $this->prefix . $table);
                    $class = ($class === 'even') ? 'odd' : 'even';
                }
            } else {
                $ret[1] .= '<tr><td colspan="3" align="center">' . _AM_SYSTEM_MAINTENANCE_DUMP_NO_TABLES . '</td></tr>';
            }
        }
        $ret[1] .= '</table>';
        $ret = $this->dump_write($ret);

        return $ret;
    }

    /**
     * Dump table structure
     *
     * @param array
     * @param string table
     * @param int    drop
     * @param string class
     * @return array 'ret[0] = dump, ret[1] = display result
     */
    public function dump_table_structure($ret, $table, $drop, $class)
    {
        if (!$this->isValidPrefixedTable($table)) {
            return $ret;
        }
        $verif  = false;
        $sql = 'SHOW create table `' . $table . '`;';
        $result = $this->db->query($sql);
        if ($this->db->isResultSet($result)) {
            if ($row = $this->db->fetchArray($result)) {
                $ret[0] .= '# Table structure for table `' . $table . "` \n\n";
                if ($drop == 1) {
                    $ret[0] .= 'DROP TABLE IF EXISTS `' . $table . "`;\n\n";
                }
                $verif = true;
                $ret[0] .= $row['Create Table'] . ";\n\n";
            }
        }

        $ret[1] .= '<tr class="' . $class . '"><td align="center">' . $table . '</td><td class="xo-actions txtcenter">';
        $ret[1] .= ($verif === true) ? '<img src="' . system_AdminIcons('success.png') . '" />' : '<img src="' . system_AdminIcons('cancel.png') . '" />';
        $ret[1] .= '</td>';
        if ($this->db->isResultSet($result)) {
            $this->db->freeRecordSet($result);
        }

        return $ret;
    }

    /**
     * Dump table data
     *
     * @param array
     * @param string table
     * @return array 'ret[0] = dump, ret[1] = display result
     */
    public function dump_table_datas($ret, $table)
    {
        if (!$this->isValidPrefixedTable($table)) {
            return $ret;
        }
        $count  = 0;
        $sql = 'SELECT * FROM ' . $table . ';';
        $result = $this->db->query($sql);
        if ($this->db->isResultSet($result)) {
            $num_rows   = $this->db->getRowsNum($result);
            $num_fields = $this->db->getFieldsNum($result);

            if ($num_rows > 0) {
                $field_type = [];
                $i          = 0;
                while ($i < $num_fields) {
                    $meta = mysqli_fetch_field($result);
                    $field_type[] = $meta->type;
                    ++$i;
                }

                $ret[0] .= 'INSERT INTO `' . $table . "` values\n";
                $index = 0;
                while (false !== ($row = $this->db->fetchRow($result))) {
                    ++$count;
                    $ret[0] .= '(';
                    for ($i = 0; $i < $num_fields; ++$i) {
                        if (null === $row[$i]) {
                            $ret[0] .= 'null';
                        } else {
                            switch ($field_type[$i]) {
                                case 'int':
                                    $ret[0] .= $row[$i];
                                    break;
                                default:
                                    $ret[0] .= "'" . $this->db->escape($row[$i]) . "'";
                            }
                        }
                        if ($i < $num_fields - 1) {
                            $ret[0] .= ',';
                        }
                    }
                    $ret[0] .= ')';

                    if ($index < $num_rows - 1) {
                        $ret[0] .= ',';
                    } else {
                        $ret[0] .= ';';
                    }
                    $ret[0] .= "\n";
                    ++$index;
                }
            }
        }
        $ret[1] .= '<td align="center">';
        $ret[1] .= $count . '&nbsp;' . _AM_SYSTEM_MAINTENANCE_DUMP_RECORDS . '</td></tr>';
        $ret[0] .= "\n";
        if ($this->db->isResultSet($result)) {
            $this->db->freeRecordSet($result);
        }
        $ret[0] .= "\n";

        return $ret;
    }

    /**
     * Absolute path of the SQL-dump directory, created on demand.
     *
     * Dumps hold password hashes, e-mail addresses and the full configuration,
     * so they are written under XOOPS_VAR_PATH (outside the web root) and served
     * only through the admin-authenticated download action — never as a
     * directly fetchable file under the web root. A deny-all guard is dropped in
     * as belt-and-braces in case the data path is misconfigured to be public.
     *
     * @return string
     */
    public static function dumpDirectory(): string
    {
        // Never fall back to a web-accessible directory: if XOOPS_VAR_PATH is not
        // defined use the system temp dir (outside the web root) rather than
        // uploads/, so a dump can't land somewhere fetchable on non-Apache setups.
        $base = defined('XOOPS_VAR_PATH') ? XOOPS_VAR_PATH : \sys_get_temp_dir();
        $dir  = $base . '/dumps';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        }
        $indexHtml = $dir . '/index.html';
        if (!file_exists($indexHtml)) {
            @file_put_contents($indexHtml, '');
        }

        return $dir;
    }

    /**
     * Dump Write
     *
     * @param array
     * @return array 'ret[0] = dump, ret[1] = display result
     */
    public function dump_write($ret)
    {
        $dir       = self::dumpDirectory();
        // date component for readability + a CSPRNG suffix so the name cannot be
        // guessed from the creation time alone.
        $file_name = 'dump_' . date('Y.m.d_H.i.s') . '_' . bin2hex(random_bytes(8)) . '.sql';
        $path_file = $dir . '/' . $file_name;
        if (false !== file_put_contents($path_file, $ret[0])) {
            @chmod($path_file, 0600);
            $downloadUrl = XOOPS_URL . '/modules/system/admin.php?fct=maintenance&amp;op=dump_download&amp;file=' . urlencode($file_name);
            $safeName    = htmlspecialchars($file_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $ret[1] .= '<table class="outer"><tr><th colspan="2" align="center">' . _AM_SYSTEM_MAINTENANCE_DUMP_FILE_CREATED . '</th><th>' . _AM_SYSTEM_MAINTENANCE_DUMP_RESULT . '</th></tr><tr><td colspan="2" align="center"><a href="' . $downloadUrl . '">' . $safeName . '</a></td><td  class="xo-actions txtcenter"><img src="' . system_AdminIcons('success.png') . '" /></td><tr></table>';
        } else {
            $ret[1] .= '<table class="outer"><tr><th colspan="2" align="center">' . _AM_SYSTEM_MAINTENANCE_DUMP_FILE_CREATED . '</th><th>' . _AM_SYSTEM_MAINTENANCE_DUMP_RESULT . '</th></tr><tr><td colspan="2" class="xo-actions txtcenter">' . htmlspecialchars($file_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td><td  class="xo-actions txtcenter"><img src="' . system_AdminIcons('cancel.png') . '" /></td><tr></table>';
        }

        return $ret;
    }
}
