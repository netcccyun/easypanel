<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Shanghai');
define('APPLICATON_ROOT', dirname(__FILE__));
define('SYS_ROOT', dirname(dirname(__FILE__)) . '/framework');
define('DEFAULT_CONTROL', 'index');
include SYS_ROOT . '/runtime.php';
$c = isset($_REQUEST['c']) ? ep_str($_REQUEST['c']) : '';
$a = isset($_REQUEST['a']) ? ep_str($_REQUEST['a']) : '';
// 登录/验证码/升级/SSO 入口与首页允许从外部站点跳转进入
if (!($c === 'sso'
	|| (($c === '' || $c === 'index') && ($a === '' || $a === 'index'))
	|| ($c === 'session' && in_array($a, array('loginForm', 'login', 'initImg', 'upgrade', 'logout'), true)))) {
	ep_same_origin_guard();
}
$tpl = TPL::singleton();
$tpl->assign('title', getTitle());
loadSetting($tpl);
startFramework();
