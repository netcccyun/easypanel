<?php
needRole('admin');
class NodesControl extends Control
{
	public function __construct()
	{
		parent::__construct();
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function testDnsdun()
	{
		$json['code'] = 400;
		$domain = $_REQUEST['domain'] ? $_REQUEST['domain'] : null;
		$domainkey = $_REQUEST['domainkey'] ? $_REQUEST['domainkey'] : null;

		if (apicall('record', 'test', array($domain, $domainkey))) {
			$json['code'] = 200;
		}
		else {
			$json['msg'] = $GLOBALS['last_error'];
		}

		exit(json_encode($json));
	}

	public function checkEnt()
	{
		$json['code'] = 10;

		if (!isEnt()) {
			$json['message'] = '没有授权信息';
			exit(json_encode($json));
		}

		$ret = apicall('access', 'checkEntAccess', array());

		if (!$ret) {
			$json['message'] = $GLOBALS['last_error'];
			exit(json_encode($json));
		}

		$json['code'] = 1;
		$json['expire'] = EP_ENT_EXPIRE;
		$file = $GLOBALS['safe_dir'] . '/index.html';

		if (!file_exists($file)) {
			$json['warning'] = $file . '不存在';
		}

		exit(json_encode($json));
	}

	/**
	 * 虚拟主机设置
	 */
	public function virtualhost()
	{
		$this->_tpl->display('virtualhost/virtualhost.html');
	}

	/**
	 * 验证状态,ajax
	 * @return string
	 */
	public function ajaxCheckNode()
	{
		$node = $_REQUEST['node'];
		$result = apicall('nodes', 'checkNode', array($_REQUEST['node']));
		header('Content-Type: text/xml; charset=utf-8');
		$str = '<?xml version="1.0" encoding="utf-8"?>';
		$str .= '<result node=\'' . $_REQUEST['node'] . '\' whm=\'';
		$str .= $result['whm'];
		$str .= '\' db=\'' . $result['db'] . '\' sqlsrv=\'' . $result['sqlsrv'] . '\'/>';
		return $str;
	}

	/**
	 * 服务器设置界面
	 */
	public function editForm()
	{
		$name = $_REQUEST['name'];
		$node = $GLOBALS['node_cfg'][$name];

		if (!is_array($node)) {
			$this->assign('msg', '没有这个节点,请重开浏览器再试');
		}

		switch ($_REQUEST['e']) {
		case 'dev':
			$this->assign('msg', '磁盘不能为空');
			break;

		case 'mysql':
			$this->assign('msg', 'mysql连接错误,请确认账号密码是否正确<br>如果不需要mysql,可不填写账号密码');
			break;

		case 'flushconfig':
			$this->assign('msg', '更新配置文件失败' . $GLOBALS['last_error']);
			break;

		default:
			break;
		}

		if ($_REQUEST['success'] == 1) {
			$this->assign('msg', '更新成功,需要重新初始化服务器,<a href=?c=nodes&a=initForm&name=localhost><b class=red>点击进入</b></a>');
		}

		$whm = apicall('nodes', 'makeWhm', array($name));
		$whmCall = new WhmCall('vhost.whm', 'list_dev');
		$result = $whm->call($whmCall, 10);

		if ($result) {
			$this->assign('devs', $result->getAll('dev'));
		}

		$viewdir = dirname(__FILE__) . '/../../vhost/view/';
		$op = opendir($viewdir);

		while (($dir = readdir($op)) !== false) {
			if ($dir == '.' || $dir == '..') {
				continue;
			}

			if (is_dir($viewdir . $dir)) {
				$view_dir[] = $dir;
			}
		}

		$view_dir_count = count($view_dir);

		if ($view_dir_count < 0) {
			$view_dir[] = 'default';
		}

		$phpversions = modcall('php', 'php_get_version');

		$setting = daocall('setting', 'getAll');
		if(!isset($setting['phpcli_version'])){
			$setting['phpcli_version'] = modcall('php', 'php_get_cli_version');
		}

		$this->_tpl->assign('view_dir_count', $view_dir_count);
		$this->_tpl->assign('view_dir', $view_dir);
		$this->_tpl->assign('phpversions', $phpversions);
		$os = substr(php_uname('s'), 0, 3);
		$this->_tpl->assign('os', $os);
		$this->_tpl->assign('action', 'edit');
		$this->_tpl->assign('node', $node);
		$this->assign('setting', $setting);
		return $this->display('nodes/addnode.html');
	}

	/**
	 * 服务器初始化界面
	 */
	public function initForm()
	{
		$this->_tpl->assign('name', $_REQUEST['name']);
		$dev = $GLOBALS['node_cfg']['localhost']['dev'];
		$this->_tpl->assign('dev', $dev);
		$this->_tpl->display('nodes/init.html');
	}

	/**
	 * 服务器设置
	 */
	public function edit()
	{
		$name = trim($_REQUEST['name']);

		if (!$_REQUEST['dev']) {
			header('Location:?c=nodes&a=editForm&e=dev&name=' . $name);
			exit();
		}

		daocall('setting', 'add', array('logs_day', intval($_REQUEST['logs_day'])));
		daocall('setting', 'add', array('view_dir', trim($_REQUEST['view_dir'])));
		daocall('setting', 'add', array('domain_note', $_REQUEST['domain_note']));
		daocall('setting', 'add', array('footer', $_REQUEST['footer']));
		daocall('setting', 'add', array('ftp_port', trim($_REQUEST['ftp_port'])));
		daocall('setting', 'add', array('ftp_pasv_port', trim($_REQUEST['ftp_pasv_port'])));
		daocall('setting', 'add', array('no_del_data', intval($_REQUEST['no_del_data'])));

		if ($_REQUEST['skey']) {
			daocall('setting', 'add', array('skey', trim($_REQUEST['skey'])));
		}

		daocall('setting', 'add', array('webalizer', $_REQUEST['webalizer']));
		daocall('setting', 'add', array('vhost_domain', trim($_REQUEST['vhost_domain'])));
		daocall('setting', 'add', array('dnsdundomain', $_REQUEST['dnsdundomain']));
		daocall('setting', 'add', array('dnsdundomainkey', $_REQUEST['dnsdundomainkey']));
		daocall('setting', 'add', array('cname_host', $_REQUEST['cname_host']));
		daocall('setting', 'add', array('domain_bind', $_REQUEST['domain_bind']));
		daocall('setting', 'add', array('default_version', $_REQUEST['default_version']));
		daocall('setting', 'add', array('phpcli_version', $_REQUEST['phpcli_version']));

		modcall('php', 'php_set_cli_version', [$_REQUEST['phpcli_version']]);

		if (daocall('setting', 'get', array('kangle_type')) == 'enterprise') {
			daocall('setting', 'add', array('title', $_REQUEST['title']));
		}

		$GLOBALS['node_cfg'][$name]['db_user'] = trim($_REQUEST['db_user']);
		$GLOBALS['node_cfg'][$name]['dev'] = trim($_REQUEST['dev']);
		$GLOBALS['node_cfg'][$name]['ep_port'] = $_REQUEST['ep_port'] ? trim($_REQUEST['ep_port']) : '3312';
		$GLOBALS['node_cfg'][$name]['port'] = $_REQUEST['port'] ? trim($_REQUEST['port']) : '3311';
		$GLOBALS['node_cfg'][$name]['db_port'] = $_REQUEST['db_port'] ? trim($_REQUEST['db_port']) : '3306';
		$GLOBALS['node_cfg'][$name]['db_host'] = $_REQUEST['db_host'] ? trim($_REQUEST['db_host']) : 'localhost';
		$db_result = apicall('utils', 'fixPriv', array($_REQUEST['db_host'], trim($_REQUEST['db_user']), $GLOBALS['node_cfg'][$name]['db_passwd'], $_REQUEST['db_passwd']));

		if ($db_result) {
			$GLOBALS['node_cfg'][$name]['db_passwd'] = $_REQUEST['db_passwd'];
		}

		if ($_REQUEST['db_user'] && $_REQUEST['db_passwd'] && $_REQUEST['del_test_database']) {
			apicall('nodes', 'delMysqlTestDatabase', array('localhost'));
		}

		$GLOBALS['node_cfg'][$name]['sqlsrv_user'] = trim($_REQUEST['sqlsrv_user']);
		$GLOBALS['node_cfg'][$name]['sqlsrv_passwd'] = $_REQUEST['sqlsrv_passwd'];
		$GLOBALS['node_cfg'][$name]['sqlsrv_port'] = $_REQUEST['sqlsrv_port'] ? trim($_REQUEST['sqlsrv_port']) : '1433';
		$result = apicall('utils', 'writeConfig', array($GLOBALS['node_cfg'], 'name', 'node', $GLOBALS['safe_dir']));

		if (!$result) {
			header('Location:?c=nodes&a=editForm&e=flushconfig&name=' . $name);
			exit();
		}

		if ($GLOBALS['node_cfg'][$name]['db_user'] != '') {
			$db = apicall('nodes', 'makeDbProduct', array('localhost'));

			if (!$db) {
				header('Location:?c=nodes&a=editForm&e=mysql&name=' . $name);
				exit();
			}
		}

		$tpl = tpl::template_config(dirname(dirname(__FILE__)) . '/template_config/');
		$node = $GLOBALS['node_cfg'][$name];
		$tpl->assign('node', $node);
		$tpl->assign('db_skey', getRandPasswd(16));
		$tpl->assign('auth_type', extension_loaded('mcrypt') ? 'cookie' : 'http');
		$phpmyadmin_config = $tpl->fetch('phpmyadmin_config.html');
		$fp = fopen(dirname($GLOBALS['safe_dir']) . '/nodewww/dbadmin/mysql/config.inc.php', 'wb');

		if ($fp) {
			fwrite($fp, $phpmyadmin_config);
			fclose($fp);
		}

		if ($_SESSION['setup_wizard'] == 1) {
			return $this->initForm();
		}

		header('Location:?c=nodes&a=editForm&success=1&name=' . $name);
		exit();
	}

	/**
	 * 初始化服务器
	 * @return Ambigous <string, void, unknown>
	 */
	public function init()
	{
		if (apicall('nodes', 'init', array($_REQUEST['name'], $_REQUEST['config_flag'], $_REQUEST['init_flag'], $_REQUEST['reboot_flag']))) {
			if ($_REQUEST['reboot_flag'] == 1) {
				$msg = '<img src=\'?t=' . time() . '&c=nodes&a=reboot&name=' . $_REQUEST['name'] . '\' border=0 width=1 height=1/>';
			}

			if ($_SESSION['setup_wizard'] == 1) {
				$_SESSION['setup_wizard'] = 0;
				$this->assign('msg', '设置成功，<a href="?c=product&a=sellForm&product=vhost">点这里现在可以增加网站了</a>.' . $msg);
			}
			else {
				$this->_tpl->assign('msg', '初始化成功,现在可以在新增网站里开通网站了' . $msg);
			}
		}
		else {
			$this->_tpl->assign('msg', '初始化失败');
		}

		return $this->fetch('msg.html');
	}

	/**
	 * 重启kangle
	 */
	public function reboot()
	{
		apicall('nodes', 'reboot', array($_REQUEST['name']));
		header('Cache-Control: no-cache');
		exit();
	}
}

?>