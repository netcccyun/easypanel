<?php
needRole('admin');
class IndexControl extends Control
{
	public function __construct()
	{
		parent::__construct();
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function index()
	{
		$this->display('kpanel.html');
	}

	public function foot()
	{
		$this->assign('EASYPANEL_VERSION', EASYPANEL_VERSION);
		return $this->display('common/foot.html');
	}

	public function top()
	{
		return $this->display('top.html');
	}

	public function controltop()
	{
		$this->display('controltop.html');
	}

	public function left()
	{
		$php_ini = urlencode($GLOBALS['safe_dir'] . '../ext/tpl_php52/php-templete.ini');

		// 走面板入口签发一次性令牌，实现 phpMyAdmin 单点登录
		$dbadmin_url = '?c=index&a=dbadmin';
		$this->_tpl->assign('dbadmin_url', $dbadmin_url);

		if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
			$os = 'win';
		}
		else {
			$os = 'lin';
		}

		$this->assign('node', $GLOBALS['node_cfg']['localhost']);
		$this->assign('os', $os);
		$this->assign('php_ini', $php_ini);
		$this->display('left.html');
	}

	public function controlleft()
	{
		$this->display('controlleft.html');
	}

	/**
	 * 打开 phpMyAdmin，已配置 mysql 账号时自动提交登录表单
	 */
	public function dbadmin()
	{
		$node = isset($GLOBALS['node_cfg']['localhost']) ? $GLOBALS['node_cfg']['localhost'] : array();
		$db_user = isset($node['db_user']) ? $node['db_user'] : '';
		$db_passwd = isset($node['db_passwd']) ? $node['db_passwd'] : '';
		$base_url = (is_https() ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . (is_https() ? ':4413' : ':3313') . '/mysql/';

		if ($db_user === '' || $db_passwd === '') {
			header('Location: ' . $base_url);
			exit();
		}

		// 换 IP/域名后自动更新 SignonURL，避免回退到旧地址
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

		if (preg_match('/^(?:[A-Za-z0-9.\-]+|\[[0-9A-Fa-f:]+\])(?::\d+)?$/', $host)) {
			$config_file = dirname($GLOBALS['safe_dir']) . '/nodewww/dbadmin/mysql/config.inc.php';
			$content = @file_get_contents($config_file);

			if ($content !== false && strpos($content, "'SignonURL'") !== false) {
				$signon_url = (is_https() ? 'https://' : 'http://') . $host . '/admin/?c=index&a=dbadmin';
				$line = "\$cfg['Servers'][\$i]['SignonURL'] = '" . $signon_url . "';";
				$updated = preg_replace_callback("/\\\$cfg\\['Servers'\\]\\[\\\$i\\]\\['SignonURL'\\] = '[^']*';/", function () use ($line) {
					return $line;
				}, $content, 1);

				if ($updated !== null && $updated !== $content) {
					@file_put_contents($config_file, $updated, LOCK_EX);
				}
			}
		}

		$escape = function ($value) {
			return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		};
		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: no-store');
		header('Referrer-Policy: no-referrer');
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>正在进入数据库管理</title></head><body>'
			. '<form id="epSignon" method="post" action="' . $escape($base_url . 'ep_signon.php') . '">'
			. '<input type="hidden" name="user" value="' . $escape($db_user) . '">'
			. '<input type="hidden" name="password" value="' . $escape($db_passwd) . '">'
			. '<button type="submit">进入数据库管理</button>'
			. '</form><script>document.getElementById("epSignon").submit();</script></body></html>';
		exit();
	}

	public function rebootSystem()
	{
		apicall('utils', 'reboot_system', array());
	}

	public function getSysInfo()
	{
		load_lib('pub:sysinfo');
		$sysinfo = sys_info();

		if (!is_array($sysinfo)) {
			$sysinfo = array();
		}

		$sysinfo['os'] = is_win() ? 'windows' : 'linux';
		exit(json_encode($sysinfo));
	}

	public function checkBind()
	{
		$json = array();
		$json['code'] = 201;
		$bind_dir = apicall('bind', 'getBindDir', array());
		if (!file_exists($bind_dir) || is_win()) {
			$json['code'] = 400;
			exit(json_encode($json));
		}

		if (apicall('bind', 'checkBind', array())) {
			$json['code'] = 200;
		}

		exit(json_encode($json));
	}

	public function main()
	{
		if ($_SESSION['setup_wizard'] == 1) {
			header('Location: ?c=nodes&a=editForm&name=localhost');
			exit();
		}

		$dbisok = daocall('vhost', 'isok', null);
		$this->assign('dbisok', is_array($dbisok) && isset($dbisok['integrity_check']) ? $dbisok['integrity_check'] : null);
		$info = apicall('nodes', 'getKangleInfo', array('localhost'));
		$this->assign('info', $info);
		$this->assign('EASYPANEL_VERSION', EASYPANEL_VERSION);
		$kangle_console_url = 'http://' . $_SERVER['SERVER_NAME'] . ':3311/';
		$this->assign('kangle_console_url', $kangle_console_url);
		$this->display('main.html');
	}
}
