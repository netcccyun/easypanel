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
				$dbadmin_url = 'http://' . $_SERVER['SERVER_NAME'] . ':3313/mysql/?pma_username=' . $user['db_name'];
				if(is_https()) $dbadmin_url = 'https://' . $_SERVER['SERVER_NAME'] . ':4413/mysql/?pma_username=' . $user['db_name'];
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
		$dbadmin_url = 'http://' . $_SERVER['SERVER_NAME'] . ':3313/mysql/?pma_username=' . $user['db_name'];
		if(is_https()) $dbadmin_url = 'https://' . $_SERVER['SERVER_NAME'] . ':4413/mysql/?pma_username=' . $user['db_name'];
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

	public function sslForm()
	{
		$vhost = getRole('vhost');
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (strpos($user['port'], 's')===false) {
			exit("<script language='javascript'>alert('您的账号不支持设置SSL证书');history.go(-1);</script>");
		}
		change_to_user($user['uid'], $user['gid']);

		if ($user['certificate']) {
			$file = $user['doc_root'] . '/' . $user['certificate'];

			if (is_link($file)) {
				unlink($file);
			}
			else {
				$fp = @fopen($file, 'rb');

				if ($fp) {
					$certificate = fread($fp, 1024000);
					fclose($fp);
					$this->_tpl->assign('certificate', $certificate);
				}
			}
		}

		if ($user['certificate_key']) {
			$keyfile = $user['doc_root'] . '/' . $user['certificate_key'];

			if (is_link($keyfile)) {
				unlink($keyfile);
			}
			else {
				$fp = @fopen($keyfile, 'rb');

				if ($fp) {
					$certificate_key = fread($fp, 1024000);
					fclose($fp);
					$this->_tpl->assign('certificate_key', $certificate_key);
				}
			}
		}

		$ssl = apicall('vhost', 'check_ssl', array($vhost));
		$this->_tpl->assign('ssl', $ssl);
		if($ssl){
			$this->_tpl->assign('http2', $user['http2']);
		}

		$find_result = $this->access->findChain('BEGIN', '!ssl_rewrite');
		if ($find_result) {
			if($ssl==0){
				$this->access->delChainByName('BEGIN', '!ssl_rewrite');
			}else{
				$this->_tpl->assign('ssl_rewrite', 1);
			}
		}

		change_to_super();
		return $this->_tpl->fetch('ssl.html');
	}

	public function ssl()
	{
		$certificate = $_REQUEST['certificate'];
		$certificate_key = $_REQUEST['certificate_key'];
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];

		if (empty($certificate) && empty($certificate_key) && ($user['certificate'] || $user['certificate_key'])){
			$arr = array('certificate' => null, 'certificate_key' => null);
			daocall('vhost', 'updateVhost', array($vhost, $arr));
		}
		elseif (!$user['certificate'] || !$user['certificate_key']) {
			$user['certificate'] = 'ssl.crt';
			$user['certificate_key'] = 'ssl.key';
			$arr = array('certificate' => $user['certificate'], 'certificate_key' => $user['certificate_key']);
			daocall('vhost', 'updateVhost', array($vhost, $arr));
		}

		change_to_user($user['uid'], $user['gid']);
		$crt_file = $user['doc_root'] . '/' . $user['certificate'];
		$key_file = $user['doc_root'] . '/' . $user['certificate_key'];

		if (is_link($crt_file)) {
			unlink($crt_file);
		}

		if (is_link($key_file)) {
			unlink($key_file);
		}

		if (empty($certificate) && empty($certificate_key)){
			@unlink($crt_file);
			@unlink($key_file);
		}else{

			$fp = @fopen($crt_file, 'wb');
			$fp2 = @fopen($key_file, 'wb');

			if ($fp) {
				fwrite($fp, $certificate);
				fclose($fp);
			}

			if ($fp2) {
				fwrite($fp2, $certificate_key);
				fclose($fp2);
			}

			apicall('vhost', 'setSystemFile', array(
		$vhost,
		$user['doc_root'],
		array($user['certificate'], $user['certificate_key'])
		));
		}
		change_to_super();
		apicall('vhost', 'noticeChange', array('localhost', $vhost));
		exit("<script language='javascript'>alert('保存成功');history.go(-1);</script>");
	}

	public function sslRewrite()
	{
		$find_result = $this->access->findChain('BEGIN', '!ssl_rewrite');
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			if ($find_result == false) {
				$arr['action'] = 'continue';
				$arr['name'] = '!ssl_rewrite';
				$models['mark_url_rewrite'] = array('url' => '^http://(.*)$', 'dst' => 'https://$1', 'nc' => '1', 'code' => '301');
				$result = $this->access->addChain('BEGIN', $arr, $models);
				break;
			}
		case 2:
			if ($find_result != null) {
				$result = $this->access->delChainByName('BEGIN', '!ssl_rewrite');
				break;
			}
		default:
			break;
		}
		if($result){
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}else{
			exit('失败');
		}
	}

	public function domainSslForm()
	{
		$domain = strtolower(trim($_GET['domain']));
		$vhost = getRole('vhost');
		$info = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if(!$info){
			exit("<script language='javascript'>alert('域名不存在');history.go(-1);</script>");
		}
		$info = $info[0];
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (strpos($user['port'], 's')===false) {
			exit("<script language='javascript'>alert('您的账号不支持设置SSL证书');history.go(-1);</script>");
		}
		if ($user['cdn']==0) {
			exit("<script language='javascript'>alert('虚拟主机不支持设置单域名SSL证书');history.go(-1);</script>");
		}
		if(strpos($info['value'], 'proto=tcp')){
			exit("<script language='javascript'>alert('TCP回源协议的不支持设置SSL证书');history.go(-1);</script>");
		}
		$this->_tpl->assign('domain', $domain);

		$ssl = 0;

		if (strncasecmp($info['value'], 'server://', 9) == 0 && strpos($info['value'],';') && strpos($info['value'],'.crt') && strpos($info['value'],'.key')){
			$ssl = 1;

			$file = $user['doc_root'] . '/' . $info['name'] . '.crt';

			if (is_link($file)) {
				unlink($file);
			}
			else {
				$fp = @fopen($file, 'rb');

				if ($fp) {
					$certificate = fread($fp, 1024000);
					fclose($fp);
					$this->_tpl->assign('certificate', $certificate);
				} else {
					$ssl = 0;
				}
			}

			$keyfile = $user['doc_root'] . '/' . $info['name'] . '.key';

			if (is_link($keyfile)) {
				unlink($keyfile);
			}
			else {
				$fp = @fopen($keyfile, 'rb');

				if ($fp) {
					$certificate_key = fread($fp, 1024000);
					fclose($fp);
					$this->_tpl->assign('certificate_key', $certificate_key);
				} else {
					$ssl = 0;
				}
			}
		}

		$this->_tpl->assign('ssl', $ssl);
		if($ssl){
			$this->_tpl->assign('http2', $user['http2']);
		}

		$table_name = '!ssl_rewrite_'.substr(md5($domain),0,6);

		$find_result = $this->access->findChain('BEGIN', $table_name);
		if ($find_result) {
			if($ssl==0){
				$this->access->delChainByName('BEGIN', $table_name);
			}else{
				$this->_tpl->assign('ssl_rewrite', 1);
			}
		}

		return $this->_tpl->fetch('domainSsl.html');
	}

	public function domainSsl()
	{
		$domain = strtolower(trim($_POST['domain']));
		$vhost = getRole('vhost');
		$info = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if(!$info){
			exit("<script language='javascript'>alert('域名不存在');history.go(-1);</script>");
		}
		$info = $info[0];
		$certificate = $_REQUEST['certificate'];
		$certificate_key = $_REQUEST['certificate_key'];
		$user = $_SESSION['user'][$vhost];
		if (empty($certificate) && empty($certificate_key) && strncasecmp($info['value'], 'server://', 9) == 0 && strpos($info['value'],';') && strpos($info['value'],'.crt') && strpos($info['value'],'.key')){
			$temp = explode(';',$info['value']);
			$arr['value'] = $temp[0];
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}
		elseif (strncasecmp($info['value'], 'server://', 9) == 0 && strpos($info['value'],';')===false && strpos($info['value'],'.crt')===false && strpos($info['value'],'.key')===false) {
			$arr['value'] = $info['value'] . ';' . $domain . '.crt|' . $domain . '.key';
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}
		elseif (strncasecmp($info['value'], 'server://', 9) != 0 && strncasecmp($info['value'], 'http://', 7) == 0) {
			$ip = substr($info['value'], 7);
			$ip = trim($ip, '/');
			$arr['value'] = 'server://proto=http/nodes=' . $ip . ':80:0:1' . ';' . $domain . '.crt|' . $domain . '.key';
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}

		change_to_user($user['uid'], $user['gid']);
		$crt_file = $user['doc_root'] . '/' . $info['name'] . '.crt';
		$key_file = $user['doc_root'] . '/' . $info['name'] . '.key';

		if (is_link($crt_file)) {
			unlink($crt_file);
		}

		if (is_link($key_file)) {
			unlink($key_file);
		}

		if (empty($certificate) && empty($certificate_key)){
			@unlink($crt_file);
			@unlink($key_file);
		}else{

			$fp = @fopen($crt_file, 'wb');
			$fp2 = @fopen($key_file, 'wb');

			if ($fp) {
				fwrite($fp, $certificate);
				fclose($fp);
			}

			if ($fp2) {
				fwrite($fp2, $certificate_key);
				fclose($fp2);
			}

			apicall('vhost', 'setSystemFile', array(
		$vhost,
		$user['doc_root'],
		array($info['name'] . '.crt', $info['name'] . '.key')
		));
		}
		change_to_super();
		apicall('vhost', 'noticeChange', array('localhost', $vhost));
		exit("<script language='javascript'>alert('保存成功');history.go(-1);</script>");
	}

	public function domainSslRewrite()
	{
		$domain = strtolower(trim($_POST['domain']));
		$vhost = getRole('vhost');
		$info = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if(!$info){
			exit('域名不存在');
		}
		$table_name = '!ssl_rewrite_'.substr(md5($domain),0,6);
		$find_result = $this->access->findChain('BEGIN', $table_name);
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			if ($find_result == false) {
				$arr['action'] = 'continue';
				$arr['name'] = $table_name;
				$models['acl_wide_host'] = array('v' => $domain.'|');
				$models['mark_url_rewrite'] = array('url' => '^http://(.*)$', 'dst' => 'https://$1', 'nc' => '1', 'code' => '301');
				$result = $this->access->addChain('BEGIN', $arr, $models);
				break;
			}
		case 2:
			if ($find_result != null) {
				$result = $this->access->delChainByName('BEGIN', $table_name);
				break;
			}
		default:
			break;
		}
		if($result){
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}else{
			exit('失败');
		}
	}

	public function http2()
	{
		$status = intval($_REQUEST['status']);

		$vhost = getRole('vhost');
		$arr = array('http2' => $status);
		$result = daocall('vhost', 'updateVhost', array($vhost, $arr));

		if($result){
			apicall('vhost', 'noticeChange', array('localhost', $vhost));
			exit('成功');
		}else{
			exit('失败');
		}
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