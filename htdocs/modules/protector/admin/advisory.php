<?php

require dirname(__DIR__, 3) . '/mainfile.php';
defined('XOOPS_TRUST_PATH') || exit('set XOOPS_TRUST_PATH in mainfile.php');

$mydirname = basename(dirname(__DIR__));
$mydirpath = dirname(__DIR__);
require $mydirpath . '/mytrustdirname.php'; // set $mytrustdirname

require XOOPS_TRUST_PATH . '/modules/' . $mytrustdirname . '/admin/advisory.php';
