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

/**
 * 同源校验：浏览器跨站请求（Origin/Referer 指向其他站点）直接拒绝，缓解 CSRF。
 * 无 Origin/Referer 的请求（直接访问、命令行、部分隐私设置）放行，避免影响正常使用。
 * 登录/验证码/SSO 入口跳过。
 */
function vhost_same_origin_guard($c, $a)
{
	if ($c === 'session' && in_array($a, array('loginForm', 'login', 'initImg', 'sso'), true)) {
		return true;
	}

	$source = '';
	if (!empty($_SERVER['HTTP_ORIGIN'])) {
		$source = $_SERVER['HTTP_ORIGIN'];
	}
	elseif (!empty($_SERVER['HTTP_REFERER'])) {
		$source = $_SERVER['HTTP_REFERER'];
	}

	if ($source === '') {
		return true;
	}

	$source_host = parse_url($source, PHP_URL_HOST);
	if ($source_host === false || $source_host === null || $source_host === '') {
		return true;
	}

	$allowed = array();
	foreach (array('HTTP_HOST', 'HTTP_X_FORWARDED_HOST') as $key) {
		if (empty($_SERVER[$key])) {
			continue;
		}

		$parts = explode(',', $_SERVER[$key]);
		$host = trim($parts[0]);
		if ($host === '') {
			continue;
		}
		$parsed = parse_url('http://' . $host, PHP_URL_HOST);
		$allowed[] = strtolower($parsed === false || $parsed === null ? $host : $parsed);
	}

	if (in_array(strtolower($source_host), $allowed, true)) {
		return true;
	}

	header('Content-Type: text/html; charset=utf-8');
	http_response_code(403);
	exit('非法请求来源');
}

vhost_same_origin_guard($c, $a);

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
