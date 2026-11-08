<?php
date_default_timezone_set('Asia/Shanghai');
header('Cache-Control: no-cache, must-revalidate');
define('APPLICATON_ROOT', dirname(__FILE__));
define('SYS_ROOT', dirname(dirname(__FILE__)) . '/framework');
define('VHOST_PATH', 1);
include SYS_ROOT . '/runtime.php';
$c = isset($_REQUEST['c']) ? $_REQUEST['c'] : '';
$a = isset($_REQUEST['a']) ? $_REQUEST['a'] : '';

if ($c == '') {
	$_REQUEST['c'] = $c = 'index';
	$_REQUEST['a'] = $a = 'main';
}

// 登录/验证码/SSO 入口与首页允许从外部站点跳转进入
if (!($c === 'sso'
	|| ($c === 'session' && in_array($a, array('loginForm', 'login', 'initImg', 'sso'), true))
	|| ($c === 'index' && $a === 'main'))) {
	ep_same_origin_guard();
}

$tpl = TPL::singleton();
loadSetting($tpl);
$vhost = getRole('vhost');

if ($vhost) {
	$GLOBALS['user'] = $_SESSION['user'][$vhost];

	if ($GLOBALS['user']) {
		$tpl->assign('user', $GLOBALS['user']);
	}
}

header('Content-Type: text/html; charset=utf-8');
$main = dispatch($c, $a);
$tpl->assign('main', $main);
$tpl->assign('width', '960');
$tpl->assign('title', getTitle());
$tpl->display('noframe.html');
