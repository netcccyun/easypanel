<?php
date_default_timezone_set('Asia/Shanghai');
define('APPLICATON_ROOT', dirname(__FILE__));
define('SYS_ROOT', dirname(dirname(__FILE__)) . '/framework');
$url = $_SERVER['REQUEST_URI'];
$a = strrchr($url, '/');
$p = strpos($a, '?');

if ($p) {
	$a = substr($a, 0, $p);
}

$_REQUEST['c'] = 'da';
$_REQUEST['a'] = substr($a, 1);
include SYS_ROOT . '/runtime.php';
$tpl = TPL::singleton();
$tpl->assign('title', getTitle());
startFramework();

?>