<?php
/**
 * XOOPS tree handler
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
 * @package             kernel
 * @since               2.0.0
 * @author              Kazumi Ono (AKA onokazu) http://www.myweb.ne.jp/, http://jp.xoops.org/
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Abstract base class for forms
 *
 * @author              Kazumi Ono <onokazu@xoops.org>
 * @author              John Neill <catzwolf@xoops.org>
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @package             kernel
 * @subpackage          XoopsTree
 * @access              public
 */
class XoopsTree
{
    public $table; //table with parent-child structure
    public $id; //name of unique id for records in table $table
    public $pid; // name of parent id used in table $table
    public $order; //specifies the order of query results
    public $title; // name of a field in table $table which will be used when  selection box and paths are generated
    /**
     * @var \XoopsMySQLDatabase
     */
    public $db;

    //constructor of class XoopsTree
    //sets the names of table, unique id, and parent id
    /**
     * @param $table_name
     * @param $id_name
     * @param $pid_name
     */
    public function __construct($table_name, $id_name, $pid_name)
    {
        $GLOBALS['xoopsLogger']->addDeprecated("Class '" . self::class . "' is deprecated, check 'XoopsObjectTree' in tree.php");
        $this->db = XoopsDatabaseFactory::getDatabaseConnection();
        $this->table = $table_name;
        $this->id    = $id_name;
        $this->pid   = $pid_name;
    }

    /**
     * Whether $name can name a column in an ORDER BY term: plain,
     * table-qualified or backtick-quoted.
     *
     * A backtick-quoted part may hold any character MySQL allows in a
     * quoted identifier (e.g. `display-name`) except a backtick, so it can
     * never close the quotes early, and except whitespace, control
     * characters and commas, which orderByClause() uses to split terms.
     *
     * @param mixed $name
     * @return bool
     */
    private static function isOrderColumn($name): bool
    {
        $part = '(?:`[^`\x00-\x20\x7F,]+`|[A-Za-z_][A-Za-z0-9_$]*)';

        return is_string($name) && 1 === preg_match('/^' . $part . '(?:\.' . $part . ')?\z/', $name);
    }

    /**
     * Build the ORDER BY clause for $order.
     *
     * $order is kept only when it is a string holding a comma-separated list
     * of column names, each optionally followed by ASC or DESC. Anything else
     * (a non-string, expressions, functions, numeric positions, subqueries,
     * comments, a second statement) is dropped with an E_USER_WARNING, so the
     * query still runs, unordered. '' and null mean "no order".
     *
     * @param mixed $order
     * @return string ' ORDER BY ...' or ''
     */
    private static function orderByClause($order): string
    {
        if (null === $order || '' === $order) {
            return '';
        }
        if (!is_string($order)) {
            trigger_error('XoopsTree: ignored an ORDER BY clause that is not a list of column names', E_USER_WARNING);

            return '';
        }
        $order = trim($order);
        if ('' === $order) {
            return '';
        }
        foreach (explode(',', $order) as $term) {
            if (1 !== preg_match('/^\s*(\S+)(?:\s+(?:ASC|DESC))?\s*\z/i', $term, $m) || !self::isOrderColumn($m[1])) {
                trigger_error('XoopsTree: ignored an ORDER BY clause that is not a list of column names', E_USER_WARNING);

                return '';
            }
        }

        return ' ORDER BY ' . $order;
    }

    /**
     * Whether $title can be used as the column in a SELECT list; warns if not.
     *
     * Only a plain column name is accepted: makeMySelBox() reads the fetched
     * row by $title, and a row is keyed by the bare column name.
     *
     * @param mixed $title
     * @return bool
     */
    private static function acceptTitle($title): bool
    {
        if (is_string($title) && 1 === preg_match('/^[A-Za-z_][A-Za-z0-9_]*\z/', $title)) {
            return true;
        }
        trigger_error('XoopsTree: refused a title that is not a plain column name', E_USER_WARNING);

        return false;
    }

    // returns an array of first child objects for a given id($sel_id)
    /**
     * @param        $sel_id
     * @param string $order
     *
     * @return array
     */
    public function getFirstChild($sel_id, $order = '')
    {
        $sel_id = (int) $sel_id;
        $arr    = [];
        $sql    = 'SELECT * FROM ' . $this->table . ' WHERE ' . $this->pid . '=' . $sel_id . '';
        $orderBy = self::orderByClause($order);
        $sql .= $orderBy;
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        $count  = $this->db->getRowsNum($result);
        if ($count == 0) {
            return $arr;
        }
        while (false !== ($myrow = $this->db->fetchArray($result))) {
            $arr[] = $myrow;
        }

        return $arr;
    }

    // returns an array of all FIRST child ids of a given id($sel_id)
    /**
     * @param $sel_id
     *
     * @return array
     */
    public function getFirstChildId($sel_id)
    {
        $sel_id  = (int) $sel_id;
        $idarray = [];
        $sql  = 'SELECT ' . $this->id . ' FROM ' . $this->table . ' WHERE ' . $this->pid . '=' . $sel_id . '';
        $result  = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        $count   = $this->db->getRowsNum($result);
        if ($count == 0) {
            return $idarray;
        }
        while (false !== ($row = $this->db->fetchRow($result))) {
            [$id] = $row;
            $idarray[] = $id;
        }

        return $idarray;
    }

    //returns an array of ALL child ids for a given id($sel_id)
    /**
     * @param        $sel_id
     * @param string $order
     * @param array  $idarray
     *
     * @return array
     */
    public function getAllChildId($sel_id, $order = '', $idarray = [])
    {
        $sel_id = (int) $sel_id;
        $sql    = 'SELECT ' . $this->id . ' FROM ' . $this->table . ' WHERE ' . $this->pid . '=' . $sel_id . '';
        $orderBy = self::orderByClause($order);
        $sql .= $orderBy;
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        $count  = $this->db->getRowsNum($result);
        if ($count == 0) {
            return $idarray;
        }
        while (false !== ($row = $this->db->fetchRow($result))) {
            [$r_id] = $row;
            $idarray[] = $r_id;
            $idarray   = $this->getAllChildId($r_id, '' === $orderBy ? '' : $order, $idarray);
        }

        return $idarray;
    }

    //returns an array of ALL parent ids for a given id($sel_id)

    /**
     * @param string|int $sel_id
     * @param string     $order
     * @param array      $idarray
     *
     * @return array
     */
    public function getAllParentId($sel_id, $order = '', $idarray = [])
    {
        $sel_id = (int) $sel_id;
        $sql    = 'SELECT ' . $this->pid . ' FROM ' . $this->table . ' WHERE ' . $this->id . '=' . $sel_id . '';
        $orderBy = self::orderByClause($order);
        $sql .= $orderBy;
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        [$r_id] = $this->db->fetchRow($result);
        $r_id = (int) $r_id;
        if ($r_id === 0) {
            return $idarray;
        }
        $idarray[] = $r_id;
        $idarray   = $this->getAllParentId($r_id, '' === $orderBy ? '' : $order, $idarray);

        return $idarray;
    }

    //generates path from the root id to a given id($sel_id)
    // the path is delimited with "/"
    /**
     * @param string|int $sel_id
     * @param string     $title
     * @param string     $path
     *
     * @return string
     */
    public function getPathFromId($sel_id, $title, $path = '')
    {
        if (!self::acceptTitle($title)) {
            return $path;
        }
        $sel_id = (int) $sel_id;
        $sql = 'SELECT ' . $this->pid . ', ' . $title . ' FROM ' . $this->table . ' WHERE ' . $this->id . "=$sel_id";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        if ($this->db->getRowsNum($result) == 0) {
            return $path;
        }
        [$parentid, $name] = $this->db->fetchRow($result);
        $myts = \MyTextSanitizer::getInstance();
        $parentid = (int) $parentid;
        $name = $myts->htmlSpecialChars($name);
        $path = '/' . $name . $path . '';
        if ($parentid === 0) {
            return $path;
        }
        $path = $this->getPathFromId($parentid, $title, $path);

        return $path;
    }

    //makes a nicely ordered selection box
    //$preset_id is used to specify a preselected item
    //set $none to 1 to add an option with value 0
    /**
     * @param        $title
     * @param string $order
     * @param int    $preset_id
     * @param int    $none
     * @param string $sel_name
     * @param string $onchange
     */
    public function makeMySelBox($title, $order = '', $preset_id = 0, $none = 0, $sel_name = '', $onchange = '')
    {
        if (!self::acceptTitle($title)) {
            return;
        }
        if ($sel_name == '') {
            $sel_name = $this->id;
        }
        $myts = \MyTextSanitizer::getInstance();
        echo "<select name='" . $sel_name . "'";
        if ($onchange != '') {
            echo " onchange='" . $onchange . "'";
        }
        echo ">\n";
        $sql = 'SELECT ' . $this->id . ', ' . $title . ' FROM ' . $this->table . ' WHERE ' . $this->pid . '=0';
        $orderBy = self::orderByClause($order);
        $sql .= $orderBy;
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        if ($none) {
            echo "<option value='0'>----</option>\n";
        }
        while (false !== ($row = $this->db->fetchRow($result))) {
            [$catid, $name] = $row;
            $sel = '';
            if ($catid == $preset_id) {
                $sel = " selected";
            }
            echo "<option value='$catid'$sel>$name</option>\n";
            $sel = '';
            $arr = $this->getChildTreeArray($catid, '' === $orderBy ? '' : $order);
            foreach ($arr as $option) {
                $option['prefix'] = str_replace('.', '--', $option['prefix']);
                $catpath          = $option['prefix'] . '&nbsp;' . $myts->htmlSpecialChars($option[$title]);
                if ($option[$this->id] == $preset_id) {
                    $sel = " selected";
                }
                echo "<option value='" . $option[$this->id] . "'$sel>$catpath</option>\n";
                $sel = '';
            }
        }
        echo "</select>\n";
    }

    //generates nicely formatted linked path from the root id to a given id
    /**
     * @param string|int    $sel_id
     * @param string $title
     * @param string $funcURL
     * @param string $path
     *
     * @return string
     */
    public function getNicePathFromId($sel_id, $title, $funcURL, $path = '')
    {
        $path   = !empty($path) ? '&nbsp;:&nbsp;' . $path : $path;
        if (!self::acceptTitle($title)) {
            return $path;
        }
        $sel_id = (int) $sel_id;
        $sql    = 'SELECT ' . $this->pid . ', ' . $title . ' FROM ' . $this->table . ' WHERE ' . $this->id . "=$sel_id";
        $result  = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        if ($this->db->getRowsNum($result) == 0) {
            return $path;
        }
        [$parentid, $name] = $this->db->fetchRow($result);
        $myts = \MyTextSanitizer::getInstance();
        $name = $myts->htmlSpecialChars($name);
        $parentid = (int) $parentid;
        $path = "<a href='" . $funcURL . '&amp;' . $this->id . '=' . $sel_id . "'>" . $name . '</a>' . $path . '';
        if ($parentid === 0) {
            return $path;
        }
        $path = $this->getNicePathFromId($parentid, $title, $funcURL, $path);

        return $path;
    }

    //generates id path from the root id to a given id
    // the path is delimited with "/"
    /**
     * @param string|int $sel_id
     * @param string     $path
     *
     * @return string
     */
    public function getIdPathFromId($sel_id, $path = '')
    {
        $sel_id = (int) $sel_id;
        $sql    = 'SELECT ' . $this->pid . ' FROM ' . $this->table . ' WHERE ' . $this->id . "=$sel_id";
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        if ($this->db->getRowsNum($result) == 0) {
            return $path;
        }
        [$parentid] = $this->db->fetchRow($result);
        $path = '/' . $sel_id . $path . '';
        $parentid = (int) $parentid;
        if ($parentid === 0) {
            return $path;
        }
        $path = $this->getIdPathFromId($parentid, $path);

        return $path;
    }

    /**
     * Enter description here...
     *
     * @param int|mixed    $sel_id
     * @param string|mixed $order
     * @param array|mixed  $parray
     *
     * @return mixed
     */
    public function getAllChild($sel_id = 0, $order = '', $parray = [])
    {
        $sel_id = (int) $sel_id;
        $sql    = 'SELECT * FROM ' . $this->table . ' WHERE ' . $this->pid . '=' . $sel_id . '';
        $orderBy = self::orderByClause($order);
        $sql .= $orderBy;
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        $count  = $this->db->getRowsNum($result);
        if ($count == 0) {
            return $parray;
        }
        while (false !== ($row = $this->db->fetchArray($result))) {
            $parray[] = $row;
            $parray   = $this->getAllChild($row[$this->id], '' === $orderBy ? '' : $order, $parray);
        }

        return $parray;
    }

    /**
     * Enter description here...
     *
     * @param  int|mixed    $sel_id
     * @param  string|mixed $order
     * @param  array|mixed  $parray
     * @param  string|mixed $r_prefix
     * @return mixed
     */
    public function getChildTreeArray($sel_id = 0, $order = '', $parray = [], $r_prefix = '')
    {
        $sel_id = (int) $sel_id;
        $sql    = 'SELECT * FROM ' . $this->table . ' WHERE ' . $this->pid . '=' . $sel_id . '';
        $orderBy = self::orderByClause($order);
        $sql .= $orderBy;
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $this->db->error(),
                E_USER_ERROR,
            );
        }
        $count  = $this->db->getRowsNum($result);
        if ($count == 0) {
            return $parray;
        }
        while (false !== ($row = $this->db->fetchArray($result))) {
            $row['prefix'] = $r_prefix . '.';
            $parray[]      = $row;
            $parray        = $this->getChildTreeArray($row[$this->id], '' === $orderBy ? '' : $order, $parray, $row['prefix']);
        }

        return $parray;
    }
}
