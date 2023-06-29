<?php
needRole('vhost');
class IndexControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'));
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function tt()
	{
	}

	/**
	 * sync前,ajax取得多节点CDN节点数。
	 */
	public function getNode()
	{
		$nodes = daocall('manynode', 'get', array());

		if ($nodes) {
			exit('200');
			return NULL;
		}

		exit('404');
	}

	public function module()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		$module = $user['module'];

		if (!$module) {
			$this->_tpl->assign('msg', 'module错误');
			return $this->main();
		}

		$msg = modcall($module, $module . '_call', array($user));

		if ($msg) {
			$this->_tpl->assign('msg', $msg);
		}

		return $this->main();
	}

	public function phpset()
	{
		$vhost = getRole('vhost');
		$versions = modcall('php', 'php_get_version');
		if ($_REQUEST['op'] == 'change') {
			$v = trim($_REQUEST['v']);
			if(empty($v) || !array_key_exists($v, $versions))exit('参数错误');

			$arr['value'] = '1,cmd:' . $v . ',*';

			if (!apicall('vhost', 'updateInfo', array($vhost, '1,php', $arr, 3))) {
				exit('修改失败');
			}

			if (!apicall('vhostinfo', 'set2', array($vhost, 'moduleversion', 101, $v))) {
				exit('修改失败');
			}
			exit('修改成功');
		}

		$vhostinfo = apicall('vhostinfo', 'get2', array(getRole('vhost'), 'moduleversion', 101));
		$value = $vhostinfo['value'];
		$value = 'PHP-'.substr($value,-2,1).'.'.substr($value,-1,1);

		$this->_tpl->assign('versions', $versions);
		$this->_tpl->assign('version', $value);
		return $this->_tpl->fetch('phpset.html');
	}

	public function index()
	{
		$this->_tpl->display('kpanel.html');
	}

	public function rebootProcess()
	{
		$vh = getRole('vhost');
		$result = apicall('vhost', 'rebootProcess', array($vh));

		if ($result) {
			exit('重启成功');
		}

		exit('重启失败');
	}

	public function sync()
	{
		apicall('cdn', 'sync_vhost_all', array());
		exit();
	}

	public function top()
	{
		$vhost = getRole('vhost');
		$this->assign('vhost', $vhost);
		$user = $_SESSION['user'][$vhost];
		$node = $user['node'];
		$hasEnv = apicall('tplenv', 'hasEnv', array($user['templete'], $user['subtemplete']));
		$this->_tpl->assign('hasEnv', $hasEnv);
		$webftp_url = '?c=index&a=webftp';
		$this->assign('webftp_url', $webftp_url);
		$quota = $_SESSION['quota'][$vhost];
		$ssl = 0;
		if (strchr($user['port'], 's')) {
			$ssl = 1;
		}
		$this->_tpl->assign('ssl', $ssl);
		return $this->_tpl->fetch('top.html');
	}

	public function left()
	{
		$this->_tpl->display('left.html');
	}

	public function controltop()
	{
		$this->_tpl->display('controltop.html');
	}

	public function controlleft()
	{
		$this->_tpl->display('controlleft.html');
	}

	public function main()
	{
		$vhost = getRole('vhost');
		$admin = getRole('admin');
		$user = daocall('vhost', 'getVhost', array($vhost));
		$vhost_domain = daocall('setting', 'get', array('vhost_domain'));
		$quota = $_SESSION['quota'][$vhost];

		if ($vhost_domain) {
			$this->_tpl->assign('vhost_domain', $vhost_domain);
		}

		$webftp_url = '?c=index&a=webftp';
		$this->assign('webftp_url', $webftp_url);
		$user['node'] = 'localhost';

		if ($user) {
			$info = apicall('nodes', 'getKangleInfo', array('localhost'));
			$_SESSION['kangle_info']['kangle_version'] = (string) $info->get('version');
			$_SESSION['kangle_info']['kangle_type'] = (string) $info->get('type');
			if (0 < $user['db_quota'] && $user['db_type'] != 'sqlsrv') {
				$dbadmin_url = 'http://' . $_SERVER['SERVER_NAME'] . ':3313/mysql/?db=' . $user['db_name'];
				if(is_https()) $dbadmin_url = 'https://' . $_SERVER['SERVER_NAME'] . ':4413/mysql/?db=' . $user['db_name'];
				$this->_tpl->assign('dbadmin_url', $dbadmin_url);
			}

			$_SESSION['user'][$vhost] = $user;
			$node_info = apicall('nodes', 'getInfo', array($user['node']));

			if ($node_info) {
				$this->_tpl->assign('node_host', $node_info['host']);
			}

			$this->_tpl->assign('node', $node_info);
			$this->_tpl->assign('product', $user);
			//$user['product_name'] = $product_info['name'];
			$quota = apicall('vhost', 'getQuota', array($user));

			if ($quota) {
				$_SESSION['quota'][$vhost] = $quota;
				$this->_tpl->assign('quota', $quota);
			}

			$flow = apicall('flow', 'getCurrentMonthFlow', array($vhost));
			$this->_tpl->assign('flow', $flow);
			$subtempletes = apicall('nodes', 'listSubTemplete', array($user['node'], $user['templete']));
			$this->_tpl->assign('subtempletes', $subtempletes);
			$ssl = 0;

			if (strchr($user['port'], 's')) {
				$ssl = 1;
			}

			$this->_tpl->assign('ssl', $ssl);
			$module = $user['module'];

			if ($module) {
				$module_link = modcall($module, $module . '_link', $user);

				if ($module_link) {
					$this->_tpl->assign('module_link', $module_link);
				}
			}
		}

		if ($admin) {
			$this->_tpl->assign('admin', $admin);
		}

		$this->_tpl->assign('user', $user);
		return $this->_tpl->fetch('kfinfo.html');
	}

	public function changeSubtemplete()
	{
		$vhost = getRole('vhost');
		apicall('vhost', 'changeSubtemplete', array('localhost', $vhost, filterParam($_REQUEST['subtemplete'])));
		return $this->main();
	}

	public function webftp()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		$_SESSION['webftp_docroot'] = $user['doc_root'];
		$_SESSION['webftp_user'] = $user['uid'];
		$_SESSION['webftp_group'] = $user['gid'];
		ob_clean();
		header('Location: ?c=webftp&a=enter');
	}

	public function dbadmin()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		$dbadmin_url = 'http://' . $_SERVER['SERVER_NAME'] . ':3313/mysql/?db=' . $user['db_name'];
		if(is_https()) $dbadmin_url = 'https://' . $_SERVER['SERVER_NAME'] . ':4413/mysql/?db=' . $user['db_name'];
		ob_clean();
		header('Location: '.$dbadmin_url);
	}

	public function ftp()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		$ftp_subdir = $_REQUEST['ftp_subdir'];

		if (strstr($ftp_subdir, '..')) {
			exit('ftp目录设置错误');
		}

		$ftp_subdir = str_replace('\\', '/', $ftp_subdir);
		daocall('vhost', 'updateFtp', array(
	$vhost,
	array('ftp' => intval($_REQUEST['ftp']), 'ftp_subdir' => filterParam($ftp_subdir))
	));
		return $this->ftpForm();
	}

	public function ftpForm()
	{
		$vhost = getRole('vhost');
		$user = $user = daocall('vhost', 'getVhost', array($vhost));
		$this->_tpl->assign('user', $user);
		return $this->_tpl->fetch('ftp.html');
	}

	public function refreshDbUsed()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		if($user['status']!=3 || $user['db_quota']==0) exit('无需刷新');

		$db = apicall('nodes', 'makeDbProduct', array('localhost'));
		$db_used = $db->used($vhost, true);

		if($db_used!==false){
			if($db_used < $user['db_quota']){
				apicall('vhost', 'changeStatus', array('localhost', $vhost, 0));
				exit('网站状态恢复成功');
			}else{
				exit('请先清理数据或升级容量后再刷新');
			}
		}
		exit('刷新失败');
	}
}

?>