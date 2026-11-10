<?php
header('Content-Type: text/plain; charset=utf-8');
http_response_code(403);
exit('da.php is disabled');

date_default_timezone_set('Asia/Shanghai');
define('APPLICATON_ROOT', dirname(__FILE__));
define('SYS_ROOT', dirname(dirname(__FILE__)) . '/framework');
$url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$a = strrchr($url, '/');

if ($a === false) {
	$a = '';
}

$p = strpos($a, '?');

if ($p !== false) {
	$a = substr($a, 0, $p);
}

$action = substr($a, 1);

if (!preg_match('/^[A-Za-z0-9_]+$/', $action)) {
	exit('invalid action');
}

$_REQUEST['c'] = 'da';
$_REQUEST['a'] = $action;
include SYS_ROOT . '/runtime.php';
$tpl = TPL::singleton();
$tpl->assign('title', getTitle());
startFramework();
