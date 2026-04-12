<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

use Xoops\Upgrade\XoopsUpgrade;
use Xoops\Upgrade\UpgradeControl;

/**
 * Upgrade from 2.0.17 to 2.0.18
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since     2.0.18
 * @author    XOOPS Team
 */
class Upgrade_2018 extends XoopsUpgrade
{
    protected array $fields = [];

    /**
     * @return bool
     */
    public function check_config_type(): bool
    {
        $sql    = 'SHOW COLUMNS FROM ' . $this->db->prefix('config') . " LIKE 'conf_title'";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            $this->logs[] = \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error();

            return false;
        }
        while (false !== ($row = $this->db->fetchArray($result))) {
            if (strtolower(trim($row['Type'])) === 'varchar(255)') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $sql
     *
     * @return bool true on success, false on failure (error logged)
     */
    protected function query(string $sql): bool
    {
        if ($this->db->exec($sql)) {
            return true;
        }
        $this->logs[] = $this->db->error();

        return false;
    }

    /**
     * @return bool
     */
    public function apply_config_type(): bool
    {
        $this->fields = [
            'config' => [
                'conf_title' => "varchar(255) NOT NULL default ''",
                'conf_desc' => "varchar(255) NOT NULL default ''",
            ],
            'configcategory' => ['confcat_name' => "varchar(255) NOT NULL default ''"],
        ];

        foreach ($this->fields as $table => $data) {
            foreach ($data as $field => $property) {
                $sql = 'ALTER TABLE ' . $this->db->prefix($table) . " CHANGE `$field` `$field` $property";
                if (!$this->query($sql)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['config_type'];
    }
}

return Upgrade_2018::class;
