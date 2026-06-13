<?php
/*
    itemsCount:        Total number of items in the current list
    pageSize:        Number of items in each page
    offset:            Index of the 1st item currently displayed
    linksCount:        Number of direct links to show (default to 5)
    url:            URL mask used to generate links (%s will be replaced by offset)
    itemsCount=$items_count pageSize=$module_config.perpage offset=$offset
    url="viewcat.php?cid=`$entity.cid`&orderby=`$sort_order`&offset=%s"
*/

/**
 * @param $params
 * @param $smarty
 *
 * @return string
 */
function smarty_function_xoPageNav($params, &$smarty)
{
    global $xoops;

    // Read params explicitly rather than expanding them into local scope (R-011).
    $itemsCount = (int) ($params['itemsCount'] ?? 0);
    $pageSize   = (int) ($params['pageSize'] ?? 0);
    $offset     = (int) ($params['offset'] ?? 0);
    $linksCount = (int) ($params['linksCount'] ?? 5);
    $url        = (string) ($params['url'] ?? '');
    $class      = (string) ($params['class'] ?? '');
    if ($pageSize < 1) {
        $pageSize = 10;
    }
    $pagesCount = (int)($itemsCount / $pageSize);
    if ($itemsCount <= $pageSize || $pagesCount < 2) {
        return '';
    }
    $str         = '';
    $currentPage = (int)($offset / $pageSize) + 1;
    $lastPage    = (int)($itemsCount / $pageSize) + 1;

    $minPage = min(1, ceil($currentPage - $linksCount / 2));
    $maxPage = max($lastPage, floor($currentPage + $linksCount / 2));

    //TODO Remove this hardcoded strings
    if ($currentPage > 1) {
        $prevUrl = htmlspecialchars($xoops->url(str_replace('%s', (string) ($offset - $pageSize), $url)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $str .= '<a href="' . $prevUrl . '">Previous</a>';
    }
    for ($i = $minPage; $i <= $maxPage; ++$i) {
        $tgt = htmlspecialchars($xoops->url(str_replace('%s', (string) (($i - 1) * $pageSize), $url)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $str .= "<a href='$tgt'>$i</a>";
    }
    if ($currentPage < $lastPage) {
        $nextUrl = htmlspecialchars($xoops->url(str_replace('%s', (string) ($offset + $pageSize), $url)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $str .= '<a href="' . $nextUrl . '">Next</a>';
    }
    $class = '' !== $class ? htmlspecialchars($class, ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'pagenav';

    $str = "<div class='{$class}'>{$str}</div>";

    return $str;
}
