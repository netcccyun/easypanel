<?php
function da_list($attr)
{
	$msg = '';

	foreach ($attr as $a) {
		if ($msg) {
			$msg .= '&';
		}

		$msg .= 'list[]=' . $a;
	}

	return $msg;
}

class DaControl extends Control
{
	public function CMD_API_SHOW_ADMINS()
	{
		$this->checkAuth();
		exit(da_list(array('admin')));
	}

	public function CMD_API_MANAGE_RESELLER_PACKAGES()
	{
		exit('error=0 text=Saved');
	}

	public function CMD_ACCOUNT_RESELLER()
	{
		exit('error=0 created successfully');
	}

	public function CMD_LOGIN()
	{
		session_start();
		$user = apicall('vhost', 'checkPassword', array($_REQUEST['username'], $_REQUEST['password']));

		if (!$user) {
			header('Content-Type: text/html; charset=utf-8');
			exit('登录错误');
		}

		registerRole('vhost', $user['name']);
		$_SESSION['login_from'] = 'vhost';
		header('Location: /vhost/');
		exit();
	}

	public function CMD_API_MANAGE_USER_PACKAGES()
	{
		$this->checkAuth();

		if ($_REQUEST['delete']) {
			$product = daocall('product', 'getProductByName', array($_REQUEST['delete1']));

			if (!$product) {
				exit('delect product failed product not found');
			}

			if (!daocall('product', 'delProduct', array($product['id']))) {
				exit('delect product failed');
			}

			exit('error=0 text=Deleted');
		}

		$arr['product_name'] = $_REQUEST['packagename'];
		$arr['web_quota'] = $_REQUEST['quota'];
		$arr['domain'] = $_REQUEST['nsubdomains'];

		if (0 < $_REQUEST['mysql']) {
			$arr['db_type'] = 'mysql';
			$arr['db_quota'] = $_REQUEST['quota'];
		}

		$arr['ftp'] = $_REQUEST['ftp'];

		if ($_REQUEST['php'] == 'ON') {
			$arr['templete'] = 'php';
		}
		else {
			$arr['templete'] = 'all_in_one';
		}

		$arr['htaccess'] = '.htaccess';
		$arr['access'] = 'access.xml';
		$arr['log_file'] = 'logs/access.log';
		$arr['log_handle'] = 1;

		if (apicall('vhostproduct', 'add', array($arr))) {
			exit('error=0 text=Saved');
		}

		exit('error');
	}

	public function CMD_API_ACCOUNT_USER()
	{
		$this->checkAuth();
		$arr = $_REQUEST;
		$package = $_REQUEST['package'];

		if ($package == '') {
			$package = $_REQUEST['quota'];
		}

		$arr['product_name'] = $package;
		$arr['name'] = $_REQUEST['username'];
		$arr['vhost_domains'] = $_REQUEST['domain'];
		apicall('vhost', 'addVhost', array($arr));
		exit('error=0 created successfully');
	}

	public function CMD_API_USER_PASSWD()
	{
		$this->checkAuth();

		if (apicall('vhost', 'changePassword', array('localhost', $_REQUEST['username'], $_REQUEST['passwd']))) {
			exit('error=0 Password Changed');
		}

		exit('changed failed');
	}

	public function CMD_API_SELECT_USERS()
	{
		$this->daSelectUser();
	}

	public function CMD_SELECT_USERS()
	{
		$this->daSelectUser();
	}

	public function CMD_API_DOMAIN_POINTER()
	{
		$this->bindDomain();
	}

	public function CMD_DOMAIN_POINTER()
	{
		$this->bindDomain();
	}

	public function CMD_API_MODIFY_USER()
	{
		$product_name = intval($_REQUEST['package']);
		$product_info = daocall('product', 'getProductByName', array($product_name));

		if (!$product_info) {
			exit('product not found');
		}

		$vhost = trim($_REQUEST['user']);

		if (!$vhost) {
			exit('param error');
		}

		if (daocall('vhost', 'updateVhost', array($vhost, $product_info))) {
			exit('error=0 change successfuly');
		}

		exit(' change failed');
	}

	private function checkAuth()
	{
		$skey = daocall('setting', 'get', array('skey'));

		if (!$skey) {
			exit('skey cann\'t be empty');
		}

		if (trim($_SERVER['AUTH_PASSWORD']) == $skey || trim($_SERVER['PHP_AUTH_PW']) == $skey) {
			return NULL;
		}

		exit('skey is error');
	}

	private function daSelectUser()
	{
		$this->checkAuth();

		foreach ($_REQUEST as $k => $v) {
			if (strncasecmp($k, 'select', 6) == 0) {
				if ($_REQUEST['dounsuspend']) {
					apicall('vhost', 'changeStatus', array('localhost', $v, 0));
				}
				else if ($_REQUEST['dosuspend']) {
					apicall('vhost', 'changeStatus', array('localhost', $v, 1));
				}
				else {
					if ($_REQUEST['delete'] == 'yes') {
						apicall('vhost', 'del', array('localhost', $v));
						apicall('cdn', 'del_cdn', array($v));
					}
				}
			}
		}

		exit('error=0 done');
	}

	private function bindDomain()
	{
		$user = $_SERVER['PHP_AUTH_USER'];
		$passwd = $_SERVER['PHP_AUTH_PW'];

		if (!apicall('vhost', 'checkPassword', array($user, $passwd))) {
			exit('login failed');
		}

		exit('not support');
	}
}

?>