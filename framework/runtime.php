<?php
function get_number_version()
{
	$versions = explode('.', EASYPANEL_VERSION);
	return intval($versions[0] * 10000 + $versions[1] * 100 + $versions[2]);
}

function is_mobile_request()
{
	$_SERVER['ALL_HTTP'] = isset($_SERVER['ALL_HTTP']) ? $_SERVER['ALL_HTTP'] : '';
	$mobile_browser = 0;

	if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
		++$mobile_browser;
	}

	if (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/vnd.wap.xhtml+xml') !== false) {
		++$mobile_browser;
	}

	if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
		++$mobile_browser;
	}

	if (isset($_SERVER['HTTP_PROFILE'])) {
		++$mobile_browser;
	}

	$mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
	$mobile_agents = array('w3c ', 'acs-', 'alav', 'alca', 'amoi', 'audi', 'avan', 'benq', 'bird', 'blac', 'blaz', 'brew', 'cell', 'cldc', 'cmd-', 'dang', 'doco', 'eric', 'hipt', 'inno', 'ipaq', 'java', 'jigs', 'kddi', 'keji', 'leno', 'lg-c', 'lg-d', 'lg-g', 'lge-', 'maui', 'maxo', 'midp', 'mits', 'mmef', 'mobi', 'mot-', 'moto', 'mwbp', 'nec-', 'newt', 'noki', 'oper', 'palm', 'pana', 'pant', 'phil', 'play', 'port', 'prox', 'qwap', 'sage', 'sams', 'sany', 'sch-', 'sec-', 'send', 'seri', 'sgh-', 'shar', 'sie-', 'siem', 'smal', 'smar', 'sony', 'sph-', 'symb', 't-mo', 'teli', 'tim-', 'tosh', 'tsm-', 'upg1', 'upsi', 'vk-v', 'voda', 'wap-', 'wapa', 'wapi', 'wapp', 'wapr', 'webc', 'winw', 'winw', 'xda', 'xda-');

	if (in_array($mobile_ua, $mobile_agents)) {
		++$mobile_browser;
	}

	if (strpos(strtolower($_SERVER['ALL_HTTP']), 'operamini') !== false) {
		++$mobile_browser;
	}

	if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows') !== false) {
		$mobile_browser = 0;
	}

	if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows phone') !== false) {
		++$mobile_browser;
	}

	if (0 < $mobile_browser) {
		return true;
	}

	return false;
}

function loadSetting($tpl)
{
	$tpl->assign('EASYPANEL_VERSION', EASYPANEL_VERSION);
	$partner_file = dirname(dirname(__FILE__)) . '/partner.txt';

	if (file_exists($partner_file)) {
		$line = file($partner_file);
		$partner_id = trim($line[0]);

		if ($partner_id != '') {
			$tpl->assign('partner_id', $partner_id);
		}
	}
}

function filterParam($param, $type = 'param')
{
	return trim($param);
}

function is_win()
{
	if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
		return true;
	}

	return false;
}

function isEnt()
{
	return defined('EP_ENT_KEYS') && defined('EP_ENT_EXPIRE') && defined('EP_ENT_HOST');
}

function setLastError($errormsg)
{
	$GLOBALS['last_error'] = $errormsg;
}

function __load_core($file, $dir = '', $return = false)
{
	global $__core_env;
	$tag = '';
	$pos = strpos($file, ':');

	if ($pos !== false) {
		$tag = substr($file, 0, $pos);
		$file = substr($file, $pos + 1);
	}

	if (!preg_match('/^[A-Za-z0-9_.-]+$/', $file, $ret)) {
		exit('incorrect file include');
	}

	if (!in_array(substr($file, 0, 1), array('/', '\\'))) {
		$file = '/' . $file;
	}

	$file = $dir . $file . '.php';

	if (substr($file, 0, 1) == '/') {
		$file = substr($file, 1);
	}

	switch ($tag) {
	case 'core':
		$file = SYS_ROOT . '/' . $file;
		break;

	case 'pub':
		$file = SYS_ROOT . '/' . $file;
		break;

	case 'app':
	default:
		$file = APPLICATON_ROOT . '/' . $file;
		break;
	}

	$__core_env['last_load_file'] = $file;

	if (file_exists($file)) {
		if ($return) {
			return $file;
		}

		include_once $file;
		return true;
	}

	trigger_error('文件不存在: ' . $file, E_USER_WARNING);
	return false;
}

function change_to_super()
{
	if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
		if (function_exists('posix_seteuid')) {
			@posix_seteuid(0);
			@posix_setegid(0);
			return NULL;
		}
	}
	else {
		if (function_exists('win32_logout')) {
			win32_logout();
		}
	}
}

function change_to_user($user, $group)
{
	if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
		if (function_exists('posix_seteuid')) {
			$ret = @posix_getgrnam($group);

			if (is_array($ret)) {
				$group = $ret['gid'];
			}

			$ret = @posix_getpwnam($user);

			if (is_array($ret)) {
				$user = $ret['uid'];
			}

			posix_setegid($group);
			posix_seteuid($user);

			if (posix_geteuid() != $user) {
				exit('程序指行出错,请联系管理员');
				return NULL;
			}
		}
	}
	else {
		if (!function_exists('win32_logon')) {
			return NULL;
		}

		if (!win32_logon($user, $group)) {
			exit('logon failed');
		}
	}
}

function __get_last_load()
{
	global $__core_env;
	return $__core_env['last_load_file'];
}

function load_lib($file)
{
	__load_core($file . '.lib', 'lib');
}

function load_conf($file)
{
	__load_core($file . '.cfg', 'configs');
}

function load_ctl($file)
{
	__load_core($file . '.ctl', 'control');
}

function load_api($file)
{
	__load_core('pub:' . $file . '.api', 'api');
}

function load_lng($file)
{
	__load_core('pub:' . $file . '.lng', 'lng');
}

function load_dao($file)
{
	__load_core('pub:' . $file . '.dao', 'dao');
}

function load_mod($name)
{
	$model_dir = defined(MODULE_DIR) == true ? MODULE_DIR : dirname(dirname(__FILE__)) . '/modules/';
	$model_dir .= '/' . $name;

	if (!file_exists($model_dir)) {
		exit($model_dir . ' 不存在');
	}

	include_once $model_dir . '/' . $name . '.php';
}

function ctlcall($module, $method, $args = array())
{
	$module = str_replace(array('-'), array('/'), $module);
	load_ctl($module);
	$pos = strrpos($module, '/');
	$class = $module;

	if (false !== $pos) {
		$class = substr($class, $pos + 1, 100);
	}

	$class[0] = strtoupper($class[0]);
	$className = $class . 'Control';
	return BaseCall('ctl', $className, $method, $args);
}

function getListDir($dir)
{
	$list = false;
	$op = opendir($dir);

	if (!$op) {
		trigger_error('不能打开目录 ' . $dir . ' 请检查');
		return false;
	}

	while ($read = readdir($op)) {
		if ($read == '.' || $read == '..') {
			continue;
		}

		if (substr($dir, 0 - 1) != '/') {
			$dir .= '/';
		}

		if (is_dir($dir . $read)) {
			$list[] = $read;
		}
	}

	closedir($op);
	return $list;
}

function modlist()
{
	$model_dir = defined(MODULE_DIR) == true ? MODULE_DIR : dirname(dirname(__FILE__)) . '/modules/';
	return getlistdir($model_dir);
}

function modcall($module, $function, $args = array())
{
	load_mod($module);

	if (function_exists($function)) {
		return call_user_func_array($function, $args);
	}

	return false;
}

function apicall($module, $method, $args = null)
{
	load_api($module);
	$className = exportClass($module, 'API');
	return BaseCall('api', $className, $method, $args);
}

function newapi($module)
{
	load_api($module);
	$className = exportClass($module, 'API');
	return Container::getinstance()->newObj($module, $className, true);
}

function daocall($module, $method, $args = null, $is_stat = true)
{
	load_dao($module);
	$className = exportClass($module, 'DAO');
	return BaseCall('dao', $className, $method, $args, false, $is_stat);
}

function newdao($module)
{
	load_dao($module);
	$className = exportClass($module, 'DAO');
	return Container::getinstance()->newObj($module, $className, true);
}

function exportClass($module, $lay)
{
	$module_clips = explode('_', $module);
	$className = '';

	foreach ($module_clips as $clip) {
		$clip[0] = strtoupper($clip[0]);
		$className .= $clip;
	}

	$className .= $lay;
	return $className;
}

function BaseCall($module, $className, $method, $args, $mul_mod = false, $is_stat = true)
{
	$start = 0;
	global $__core_env;
	$__core_env['DEBUG'] === true && $__core_env['STRACE'][$module . '/' . $className . '/' . $method]['start'] = microtime_float();
	$object = Container::getinstance()->newObj($module, $className, $mul_mod);

	if (method_exists($object, $method)) {
		if ($args && !is_array($args)) {
			debug_print_backtrace();
		}

		$result = call_user_func_array(array($object, $method), $args == null ? array() : $args);
		return $result;
	}

	return false;
}

function getRoles()
{
	global $_SESSION;
	return $_SESSION['janbao_role'];
}

function getRole($role)
{
	global $_SESSION;
	return $_SESSION['janbao_role'][$role];
}

function unregisterRole($role)
{
	global $_SESSION;
	unset($_SESSION['janbao_role_ip'][$role]);
	unset($_SESSION['janbao_role'][$role]);
}

function registerRole($role, $user)
{
	global $_SESSION;
	$_SESSION['janbao_role_ip'][$role] = $_SERVER['REMOTE_ADDR'];
	$_SESSION['janbao_role'][$role] = $user;
	assert(isRole($role));
}

function isRole($role)
{
	$user = getrole($role);
	if ($user == null || $user == '') {
		return false;
	}

	return true;
}

function notice_cdn_changed($vh = null)
{
	if (!$vh) {
		$vh = getrole('vhost');
	}

	if (!$vh) {
		return false;
	}

	return apicall('vhost', 'updateVhostSyncseq', array($vh));
}

function setTitle($title)
{
	global $__core_env;
	$__core_env['title'] = $title;
}

function getTitle()
{
	global $__core_env;

	if ($__core_env['title'] == '') {
		if (file_exists('../config.php')) {
			$title = daocall('setting', 'get', array('title'));
		}

		$__core_env['title'] = $title ? $title : 'easypanel 虚拟主机控制面板';
	}

	return $__core_env['title'] . ' - Powered by ' . $__core_env['title'];
}

function getRandPasswd($len = 8)
{
	$base_passwd = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_-0123456789';
	srand((double) microtime() * 1000000);
	$base_len = strlen($base_passwd);

	if ($len < 8) {
		$len = 8;
	}

	$passwd = 'K~w';
	$i = 0;

	while ($i < $len) {
		$passwd .= $base_passwd[rand() % $base_len];
		++$i;
	}

	return $passwd;
}

function needRole($role)
{
	if (!isrole($role)) {
		if ($_SERVER['QUERY_STRING'] == 'c=session&a=loginForm') {
			exit('');
		}

		exit('<html><body><script language="javascript">window.top.location.href="?c=session&a=loginForm";</script></body></html>');
	}
}

function microtime_float()
{
	list($usec, $sec) = explode(' ', microtime());
	return (double) $usec + (double) $sec;
}

function startFramework()
{
	if (!defined('CORE_DAEMON')) {
		__dispatch_init();
		echo __dispatch_start();
	}
}

function checkIfActive($string) {
	$array=explode(',',$string);
	if (in_array($_GET['c'],$array)){
		return 'active';
	}elseif ($_GET['c']=='index' && in_array($_GET['a'],$array)){
		return 'active';
	}else
		return null;
}

function checkIfIn($string) {
	$array=explode(',',$string);
	if (in_array($_GET['c'],$array)){
		return 'in';
	}elseif ($_GET['c']=='index' && in_array($_GET['a'],$array)){
		return 'in';
	}else
		return null;
}

function checkDomain($domain){
	if(empty($domain) || !preg_match('/^[-$a-z0-9_*.]{2,512}$/i', $domain) || (stripos($domain, '.') === false) || substr($domain, -1) == '.' || substr($domain, 0 ,1) == '.' || substr($domain, 0 ,1) == '*' && substr($domain, 1 ,1) != '.' || substr_count($domain, '*')>1) return false;
	return true;
}

function checkIp($ip)
{
	if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		return false;
	}

	return true;
}

function is_https(){
	if(isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] === '1')){
		return true;
	}elseif(isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && $_SERVER['HTTP_X_CLIENT_SCHEME'] == 'https'){
		return true;
	}elseif(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'){
		return true;
	}
	return false;
}

error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
define('EASYPANEL_VERSION', '2.6.29');
define('PHP_DEFAULT_VERSION', 'php56');
define('IIS_DEFAULT_VERSION', 'v2.0.50727');
define('ASDF_10_BVCX', 'ZHNhZmRqb2ozbzBqZmQwb2p1WzA0LTIzOT0yMy09aWUtZmpvc2lkZ');
define('S_IFDIR', 16384);
define('EP_KEY_FILE', $GLOBALS['safe_dir'] . '../ep_license.txt');
@set_time_limit(0);

if (!defined('SYS_ROOT')) {
	trigger_error('未定义常量 SYS_ROOT.', E_USER_ERROR);
}

global $__core_env;
change_to_super();
session_save_path(SYS_ROOT . '/../../tmp/');
session_start();
@include_once SYS_ROOT . '/../config.php';
$__core_env['DEBUG'] = false;
load_lng('zh');
__load_core('core:control');
__load_core('core:model');
__load_core('core:dao');
__load_core('core:api');
__load_core('core:tpl');
__load_core('core:container');
__load_core('core:dispatch');

?>