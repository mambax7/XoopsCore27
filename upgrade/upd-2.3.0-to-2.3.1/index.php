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
 * Upgrader from 2.3.0 to 2.3.1
 *
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package          upgrader
 * @since            2.3.0
 * @author           Taiwen Jiang <phppp@users.sourceforge.net>
 */
class Upgrade_231 extends XoopsUpgrade
{
    /**
     * Upgrade_231 constructor.
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['field'];
    }

    /**
     * Check if field type already fixed for mysql strict mode
     *
     */
    public function check_field(): bool
    {
        $fields = [
            'cache_data' => 'cache_model',
            'htmlcode' => 'banner',
            'extrainfo' => 'bannerclient',
            'com_text' => 'xoopscomments',
            'conf_value' => 'config',
            'description' => 'groups',
            'imgsetimg_body' => 'imgsetimg',
            'content' => 'newblocks',
            'msg_text' => 'priv_msgs',
            'sess_data' => 'session',
            'tplset_credits' => 'tplset',
            'tpl_source' => 'tplsource',
            'user_sig' => 'users',
            'bio' => 'users',
        ];
        foreach ($fields as $field => $table) {
            $sql = 'SHOW COLUMNS FROM `' . $this->db->prefix($table) . "` LIKE '{$field}'";
            $result = $this->db->query($sql);
            if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
                return false;
            }
            while (false !== ($row = $this->db->fetchArray($result))) {
                if ($row['Field'] != $field) {
                    continue;
                }
                if (strtoupper($row['Null']) !== 'YES') {
                    return false;
                }
            }
        }

        return true;
    }

    public function apply_field(): bool
    {
        $allowWebChanges           = $this->db->allowWebChanges;
        $this->db->allowWebChanges = true;
        $result                    = $this->db->queryFromFile(__DIR__ . '/mysql.structure.sql');
        $this->db->allowWebChanges = $allowWebChanges;

        return $result;
    }
}

return Upgrade_231::class;
