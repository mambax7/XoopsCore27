<?php

use Xmf\Database\Tables;
use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

/**
 * Upgrade from 2.5.8 to 2.5.9
 *
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package          Upgrade
 * @since            2.5.9
 * @author           XOOPS Team
 */
class Upgrade_259 extends XoopsUpgrade
{
    /**
     * __construct
     *
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['sess_id', 'mainfile', 'zaplegacy'];
        // check_mainfile() reads UpgradeControl::$needMainfileRewrite, computed from
        // the mainfile.php loaded at request start.
        $this->noRecheck = ['mainfile'];
        $this->usedFiles = [
            'mainfile.php',
            XOOPS_VAR_PATH . '/data/secure.php',
            'modules/system/themes/legacy/legacy.php',
        ];
    }

    /**
     * Return the length of a database table column
     *
     * @param string $table  table name
     * @param string $column column name
     *
     * @return int column length or zero on error
     */
    private function getColumnLength(string $table, string $column): int
    {
        $dbname = constant('XOOPS_DB_NAME');
        $table = $this->db->prefix($table);

        $sql = sprintf(
            'SELECT `CHARACTER_MAXIMUM_LENGTH` FROM `information_schema`.`COLUMNS` '
            . "WHERE TABLE_SCHEMA = '%s'AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s'",
            $this->db->escape($dbname),
            $this->db->escape($table),
            $this->db->escape($column),
        );

        $result = $this->db->query($sql);
        if ($this->db->isResultSet($result) && ($result instanceof \mysqli_result)) {
            $row = $this->db->fetchRow($result);
            if ($row) {
                $columnLength = $row[0];
                return (int) $columnLength;
            }
        }
        return 0;
    }

    /**
     * In PHP 7.1 Session ID length could be any length between 22 and 256
     *
     * @return bool
     */
    public function check_sess_id(): bool
    {
        return (bool) ($this->getColumnLength('session', 'sess_id') >= 256);
    }

    /**
     * Expand session id column to varchar(256) to accommodate expanded size possible in PHP 7.1
     * Force ascii character set to prevent key length issues.
     *
     * @return bool
     */
    public function apply_sess_id(): bool
    {
        $migrate = new Tables();
        $migrate->useTable('session');
        $migrate->alterColumn('session', 'sess_id', "varchar(256) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT ''");
        return $migrate->executeQueue(true);
    }

    /**
     * Copy a configuration file from template, then rewrite with actual configuration values
     *
     * @param string[] $vars       config values
     * @param string   $path       directory path where files reside
     * @param string   $sourceName template file name
     * @param string   $fileName   configuration file name
     *
     * @return true|string true on success, error message on failure
     */
    protected function writeConfigurationFile($vars, $path, $sourceName, $fileName)
    {
        $path .= '/';
        clearstatcache();
        if (!$inFile = fopen($path . $sourceName, 'r')) {
            return sprintf(_FILE_ACCESS_ERROR, $sourceName);
        } else {
            $content = fread($inFile, filesize($path . $sourceName));
            fclose($inFile);

            // Tokenise, so only an executable define(NAME, LITERAL) call is
            // rewritten, never one quoted in a line comment where a value holding
            // a PHP close tag would start executable PHP; var_export() keeps
            // backslashes, quotes and close tags inside the generated literal.
            $tokens = token_get_all($content);
            $code   = [];
            foreach ($tokens as $i => $t) {
                if (!is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $code[] = $i;
                }
            }
            foreach ($code as $k => $i) {
                $t = $tokens[$i];
                if (!is_array($t) || T_STRING !== $t[0] || 0 !== strcasecmp('define', $t[1])) {
                    continue;
                }
                $name  = $tokens[$code[$k + 2] ?? -1] ?? null;
                $index = $code[$k + 4] ?? null;
                $value = null === $index ? null : $tokens[$index];
                if ('(' !== ($tokens[$code[$k + 1] ?? -1] ?? null)
                    || !is_array($name) || T_CONSTANT_ENCAPSED_STRING !== $name[0]
                    || ',' !== ($tokens[$code[$k + 3] ?? -1] ?? null)
                    || !is_array($value)
                    || ')' !== ($tokens[$code[$k + 5] ?? -1] ?? null)
                    || !preg_match('/^(["\'])(\w+)\1$/', $name[1], $m)
                    || !array_key_exists($m[2], $vars)
                ) {
                    continue;
                }
                $val = $vars[$m[2]];
                if (T_LNUMBER === $value[0] && is_int($val)) {
                    $tokens[$index][1] = (string) $val;
                } elseif (T_CONSTANT_ENCAPSED_STRING === $value[0]) {
                    $tokens[$index][1] = var_export((string) $val, true);
                }
            }
            $content = implode('', array_map(static fn($t): string => is_array($t) ? $t[1] : $t, $tokens));
            $outFile = fopen($path . $fileName, 'w');
            if (false === $outFile) {
                return sprintf(_FILE_ACCESS_ERROR, $fileName);
            }
            $writeResult = fwrite($outFile, $content);
            fclose($outFile);
            if (false === $writeResult) {
                return sprintf(_FILE_ACCESS_ERROR, $fileName);
            }
        }
        return true;
    }

    /**
     * Do we need to rewrite mainfile and secure?
     *
     * @return bool
     */
    public function check_mainfile(): bool
    {
        return !$this->control->needMainfileRewrite;
    }

    /**
     * Rewrite mainfile and secure file with current templates
     *
     * @return bool
     */
    public function apply_mainfile(): bool
    {
        if (null === $this->control->mainfileKeys['XOOPS_COOKIE_DOMAIN']) {
            $this->control->mainfileKeys['XOOPS_COOKIE_DOMAIN'] = xoops_getBaseDomain(XOOPS_URL);
        }
        $result = $this->writeConfigurationFile(
            $this->control->mainfileKeys,
            XOOPS_ROOT_PATH,
            'mainfile.dist.php',
            'mainfile.php',
        );
        if ($result !== true) {
            $this->logs[] = $result;
        } else {
            $result = $this->writeConfigurationFile(
                $this->control->mainfileKeys,
                XOOPS_VAR_PATH . '/data',
                'secure.dist.php',
                'secure.php',
            );
            if ($result !== true) {
                $this->logs[] = $result;
            }
        }
        return ($result === true);
    }

    //modules/system/themes/legacy/legacy.php
    /**
     * Do we need to rewrite mainfile and secure?
     *
     * @return bool
     */
    public function check_zaplegacy(): bool
    {
        return !file_exists(XOOPS_ROOT_PATH . '/modules/system/themes/legacy/legacy.php');
    }

    /**
     * Rewrite mainfile and secure file with current templates
     *
     * @return bool
     */
    public function apply_zaplegacy(): bool
    {
        $fileName = XOOPS_ROOT_PATH . '/modules/system/themes/legacy/legacy.php';
        $result = rename($fileName, $fileName . '.bak');
        if (false === $result) {
            $this->logs[] = sprintf(defined('_FILE_ACCESS_ERROR') ? _FILE_ACCESS_ERROR : 'File access error: %s', $fileName);
            return false;
        }
        return true;
    }
}

return Upgrade_259::class;
